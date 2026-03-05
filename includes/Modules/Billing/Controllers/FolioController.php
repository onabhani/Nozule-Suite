<?php

namespace Nozule\Modules\Billing\Controllers;

use Nozule\Core\PropertyScope;
use Nozule\Core\ResponseHelper;
use Nozule\Modules\Billing\Models\Folio;
use Nozule\Modules\Billing\Models\FolioItem;
use Nozule\Modules\Billing\Repositories\FolioRepository;
use Nozule\Modules\Billing\Services\FolioService;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * REST controller for folio administration.
 *
 * Routes:
 *   GET    /nozule/v1/admin/folios                  List folios (filter by status, booking_id, guest_id)
 *   POST   /nozule/v1/admin/folios                  Create folio
 *   GET    /nozule/v1/admin/folios/{id}             Show folio with items
 *   POST   /nozule/v1/admin/folios/{id}/items       Add item to folio
 *   DELETE /nozule/v1/admin/folios/items/{id}       Remove item
 *   PUT    /nozule/v1/admin/folios/{id}/close       Close folio
 *   PUT    /nozule/v1/admin/folios/{id}/void        Void folio
 */
class FolioController {

	private FolioService $folioService;
	private FolioRepository $folioRepository;
	private PropertyScope $propertyScope;

	public function __construct( FolioService $folioService, FolioRepository $folioRepository, PropertyScope $propertyScope ) {
		$this->folioService    = $folioService;
		$this->folioRepository = $folioRepository;
		$this->propertyScope   = $propertyScope;
	}

	/**
	 * Register REST routes.
	 */
	public function registerRoutes(): void {
		$namespace = 'nozule/v1';

		// List and create folios.
		register_rest_route( $namespace, '/admin/folios', [
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'index' ],
				'permission_callback' => [ $this, 'checkStaffPermission' ],
				'args'                => [
					'status' => [
						'required'          => false,
						'validate_callback' => fn( $v ) => in_array( $v, Folio::validStatuses(), true ),
						'sanitize_callback' => 'sanitize_text_field',
					],
					'booking_id' => [
						'required'          => false,
						'validate_callback' => fn( $v ) => is_numeric( $v ),
						'sanitize_callback' => 'absint',
					],
					'guest_id' => [
						'required'          => false,
						'validate_callback' => fn( $v ) => is_numeric( $v ),
						'sanitize_callback' => 'absint',
					],
				],
			],
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'create' ],
				'permission_callback' => [ $this, 'checkAdminPermission' ],
			],
		] );

		// Single folio operations.
		register_rest_route( $namespace, '/admin/folios/(?P<id>\d+)', [
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'show' ],
				'permission_callback' => [ $this, 'checkStaffPermission' ],
				'args'                => $this->getIdArgs(),
			],
		] );

		// Add item to folio.
		register_rest_route( $namespace, '/admin/folios/(?P<id>\d+)/items', [
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => [ $this, 'addItem' ],
			'permission_callback' => [ $this, 'checkAdminPermission' ],
			'args'                => $this->getIdArgs(),
		] );

		// Remove item.
		register_rest_route( $namespace, '/admin/folios/items/(?P<id>\d+)', [
			'methods'             => WP_REST_Server::DELETABLE,
			'callback'            => [ $this, 'removeItem' ],
			'permission_callback' => [ $this, 'checkAdminPermission' ],
			'args'                => $this->getIdArgs(),
		] );

		// Close folio.
		register_rest_route( $namespace, '/admin/folios/(?P<id>\d+)/close', [
			'methods'             => WP_REST_Server::EDITABLE,
			'callback'            => [ $this, 'close' ],
			'permission_callback' => [ $this, 'checkAdminPermission' ],
			'args'                => $this->getIdArgs(),
		] );

		// Void folio.
		register_rest_route( $namespace, '/admin/folios/(?P<id>\d+)/void', [
			'methods'             => WP_REST_Server::EDITABLE,
			'callback'            => [ $this, 'void' ],
			'permission_callback' => [ $this, 'checkAdminPermission' ],
			'args'                => $this->getIdArgs(),
		] );
	}

	/**
	 * List folios with optional filters.
	 */
	public function index( WP_REST_Request $request ): WP_REST_Response {
		$status    = $request->get_param( 'status' );
		$bookingId = $request->get_param( 'booking_id' ) ? absint( $request->get_param( 'booking_id' ) ) : null;
		$guestId   = $request->get_param( 'guest_id' ) ? absint( $request->get_param( 'guest_id' ) ) : null;

		$folios = $this->folioRepository
			->scopeToProperty( $this->propertyScope->getActivePropertyId() )
			->getAllFiltered( $status, $bookingId, $guestId );

		$data = array_map(
			fn( Folio $folio ) => $folio->toArray(),
			$folios
		);

		return ResponseHelper::success( $data, null, ['total' => count( $data )] );
	}

	/**
	 * Create a new folio.
	 */
	public function create( WP_REST_Request $request ): WP_REST_Response {
		$bookingId      = $request->get_param( 'booking_id' ) ? absint( $request->get_param( 'booking_id' ) ) : null;
		$groupBookingId = $request->get_param( 'group_booking_id' ) ? absint( $request->get_param( 'group_booking_id' ) ) : null;
		$guestId        = absint( $request->get_param( 'guest_id' ) );

		if ( ! $guestId ) {
			return ResponseHelper::error( __( 'guest_id is required.', 'nozule' ), 422 );
		}

		if ( $groupBookingId ) {
			$result = $this->folioService->createFolioForGroup( $groupBookingId, $guestId );
		} elseif ( $bookingId ) {
			$result = $this->folioService->createFolioForBooking( $bookingId, $guestId );
		} else {
			// Create a standalone folio for a guest.
			$result = $this->folioService->createFolioForBooking( 0, $guestId );
		}

		if ( $result instanceof Folio ) {
			return ResponseHelper::created( $result->toArray(), __( 'Folio created successfully.', 'nozule' ) );
		}

		return ResponseHelper::error( __( 'Validation failed.', 'nozule' ), 422, $result );
	}

	/**
	 * Show a folio with all its items.
	 */
	public function show( WP_REST_Request $request ): WP_REST_Response {
		$id     = (int) $request->get_param( 'id' );
		$result = $this->folioService->getFolioWithItems( $id );

		if ( ! $result ) {
			return ResponseHelper::notFound( __( 'Folio not found.', 'nozule' ) );
		}

		$folio = $result['folio'];
		if ( ! $this->propertyScope->canAccessAllProperties() && ( $folio->property_id ?? null ) !== $this->propertyScope->getActivePropertyId() ) {
			return ResponseHelper::forbidden( __( 'Forbidden', 'nozule' ) );
		}

		$items = array_map(
			fn( FolioItem $item ) => $item->toPublicArray(),
			$result['items']
		);

		return ResponseHelper::success( [
				'folio' => $result['folio']->toArray(),
				'items' => $items,
			] );
	}

	/**
	 * Add an item to a folio.
	 */
	public function addItem( WP_REST_Request $request ): WP_REST_Response {
		$folioId = (int) $request->get_param( 'id' );
		$data    = $this->extractItemData( $request );

		$result = $this->folioService->addItem( $folioId, $data );

		if ( $result instanceof FolioItem ) {
			return ResponseHelper::created( $result->toPublicArray(), __( 'Item added to folio successfully.', 'nozule' ) );
		}

		if ( isset( $result['folio_id'] ) ) {
			$message    = is_array( $result['folio_id'] ) && isset( $result['folio_id'][0] ) ? $result['folio_id'][0] : __( 'Invalid folio.', 'nozule' );
			$statusCode = isset( $result['http_status'] ) ? (int) $result['http_status'] : ( isset( $result['id'] ) ? 404 : 422 );

			return ResponseHelper::error( $message, $statusCode, $result );
		}

		return ResponseHelper::error( __( 'Validation failed.', 'nozule' ), 422, $result );
	}

	/**
	 * Remove an item from a folio.
	 */
	public function removeItem( WP_REST_Request $request ): WP_REST_Response {
		$itemId = (int) $request->get_param( 'id' );
		$result = $this->folioService->removeItem( $itemId );

		if ( $result === true ) {
			return ResponseHelper::success( null, __( 'Folio item removed successfully.', 'nozule' ) );
		}

		$statusCode = isset( $result['id'] ) ? 404 : 422;

		return ResponseHelper::error( __( 'Failed to remove folio item.', 'nozule' ), $statusCode, $result );
	}

	/**
	 * Close a folio.
	 */
	public function close( WP_REST_Request $request ): WP_REST_Response {
		$id     = (int) $request->get_param( 'id' );
		$result = $this->folioService->closeFolio( $id );

		if ( $result instanceof Folio ) {
			return ResponseHelper::success( $result->toArray(), __( 'Folio closed successfully.', 'nozule' ) );
		}

		if ( isset( $result['id'] ) ) {
			return ResponseHelper::error( $result['id'][0], 404, $result );
		}

		return ResponseHelper::error( __( 'Failed to close folio.', 'nozule' ), 422, $result );
	}

	/**
	 * Void a folio.
	 */
	public function void( WP_REST_Request $request ): WP_REST_Response {
		$id     = (int) $request->get_param( 'id' );
		$result = $this->folioService->voidFolio( $id );

		if ( $result instanceof Folio ) {
			return ResponseHelper::success( $result->toArray(), __( 'Folio voided successfully.', 'nozule' ) );
		}

		if ( isset( $result['id'] ) ) {
			return ResponseHelper::error( $result['id'][0], 404, $result );
		}

		return ResponseHelper::error( __( 'Failed to void folio.', 'nozule' ), 422, $result );
	}

	/**
	 * Extract item data from the request.
	 */
	private function extractItemData( WP_REST_Request $request ): array {
		$fields = [ 'category', 'description', 'description_ar', 'unit_price', 'quantity', 'date' ];
		$data   = [];

		foreach ( $fields as $field ) {
			$value = $request->get_param( $field );
			if ( $value !== null ) {
				$data[ $field ] = $value;
			}
		}

		// Sanitize.
		if ( isset( $data['category'] ) ) {
			$data['category'] = sanitize_text_field( $data['category'] );
		}
		if ( isset( $data['description'] ) ) {
			$data['description'] = sanitize_text_field( $data['description'] );
		}
		if ( isset( $data['description_ar'] ) ) {
			$data['description_ar'] = sanitize_text_field( $data['description_ar'] );
		}
		if ( isset( $data['unit_price'] ) ) {
			$data['unit_price'] = (float) $data['unit_price'];
		}
		if ( isset( $data['quantity'] ) ) {
			$data['quantity'] = (int) $data['quantity'];
		}
		if ( isset( $data['date'] ) ) {
			$data['date'] = sanitize_text_field( $data['date'] );
		}

		return $data;
	}

	/**
	 * Common ID argument definition.
	 */
	private function getIdArgs(): array {
		return [
			'id' => [
				'required'          => true,
				'validate_callback' => fn( $value ) => is_numeric( $value ) && $value > 0,
				'sanitize_callback' => 'absint',
			],
		];
	}

	/**
	 * Permission callback: require nzl_admin capability.
	 */
	public function checkAdminPermission( WP_REST_Request $request ): bool {
		return current_user_can( 'manage_options' ) || current_user_can( 'nzl_admin' );
	}

	/**
	 * Permission callback: require nzl_staff or nzl_admin capability.
	 */
	public function checkStaffPermission( WP_REST_Request $request ): bool {
		return current_user_can( 'manage_options' ) || current_user_can( 'nzl_admin' ) || current_user_can( 'nzl_staff' );
	}

	// Standard CRUD aliases (used by RestController admin routes).

	public function store( WP_REST_Request $request ): WP_REST_Response {
		return $this->create( $request );
	}
}
