<?php

namespace Nozule\Modules\Billing\Controllers;

use Nozule\Modules\Billing\Models\Folio;
use Nozule\Modules\Billing\Models\FolioItem;
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

	public function __construct( FolioService $folioService ) {
		$this->folioService = $folioService;
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

		$folios = $this->folioService->getFolios( $status, $bookingId, $guestId );

		$data = array_map(
			fn( Folio $folio ) => $folio->toArray(),
			$folios
		);

		return new WP_REST_Response( [
			'success' => true,
			'data'    => $data,
			'total'   => count( $data ),
		], 200 );
	}

	/**
	 * Create a new folio.
	 */
	public function create( WP_REST_Request $request ): WP_REST_Response {
		$bookingId      = $request->get_param( 'booking_id' ) ? absint( $request->get_param( 'booking_id' ) ) : null;
		$groupBookingId = $request->get_param( 'group_booking_id' ) ? absint( $request->get_param( 'group_booking_id' ) ) : null;
		$guestId        = absint( $request->get_param( 'guest_id' ) );

		if ( ! $guestId ) {
			return new WP_REST_Response( [
				'success' => false,
				'message' => __( 'guest_id is required.', 'nozule' ),
			], 422 );
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
			return new WP_REST_Response( [
				'success' => true,
				'message' => __( 'Folio created successfully.', 'nozule' ),
				'data'    => $result->toArray(),
			], 201 );
		}

		return new WP_REST_Response( [
			'success' => false,
			'message' => __( 'Validation failed.', 'nozule' ),
			'errors'  => $result,
		], 422 );
	}

	/**
	 * Show a folio with all its items.
	 */
	public function show( WP_REST_Request $request ): WP_REST_Response {
		$id     = (int) $request->get_param( 'id' );
		$result = $this->folioService->getFolioWithItems( $id );

		if ( ! $result ) {
			return new WP_REST_Response( [
				'success' => false,
				'message' => __( 'Folio not found.', 'nozule' ),
			], 404 );
		}

		$items = array_map(
			fn( FolioItem $item ) => $item->toPublicArray(),
			$result['items']
		);

		return new WP_REST_Response( [
			'success' => true,
			'data'    => [
				'folio' => $result['folio']->toArray(),
				'items' => $items,
			],
		], 200 );
	}

	/**
	 * Add an item to a folio.
	 */
	public function addItem( WP_REST_Request $request ): WP_REST_Response {
		$folioId = (int) $request->get_param( 'id' );
		$data    = $this->extractItemData( $request );

		$result = $this->folioService->addItem( $folioId, $data );

		if ( $result instanceof FolioItem ) {
			return new WP_REST_Response( [
				'success' => true,
				'message' => __( 'Item added to folio successfully.', 'nozule' ),
				'data'    => $result->toPublicArray(),
			], 201 );
		}

		if ( isset( $result['folio_id'] ) ) {
			return new WP_REST_Response( [
				'success' => false,
				'message' => $result['folio_id'][0],
				'errors'  => $result,
			], isset( $result['folio_id'] ) && strpos( $result['folio_id'][0], 'not found' ) !== false ? 404 : 422 );
		}

		return new WP_REST_Response( [
			'success' => false,
			'message' => __( 'Validation failed.', 'nozule' ),
			'errors'  => $result,
		], 422 );
	}

	/**
	 * Remove an item from a folio.
	 */
	public function removeItem( WP_REST_Request $request ): WP_REST_Response {
		$itemId = (int) $request->get_param( 'id' );
		$result = $this->folioService->removeItem( $itemId );

		if ( $result === true ) {
			return new WP_REST_Response( [
				'success' => true,
				'message' => __( 'Folio item removed successfully.', 'nozule' ),
			], 200 );
		}

		$statusCode = isset( $result['id'] ) ? 404 : 422;

		return new WP_REST_Response( [
			'success' => false,
			'message' => __( 'Failed to remove folio item.', 'nozule' ),
			'errors'  => $result,
		], $statusCode );
	}

	/**
	 * Close a folio.
	 */
	public function close( WP_REST_Request $request ): WP_REST_Response {
		$id     = (int) $request->get_param( 'id' );
		$result = $this->folioService->closeFolio( $id );

		if ( $result instanceof Folio ) {
			return new WP_REST_Response( [
				'success' => true,
				'message' => __( 'Folio closed successfully.', 'nozule' ),
				'data'    => $result->toArray(),
			], 200 );
		}

		if ( isset( $result['id'] ) ) {
			return new WP_REST_Response( [
				'success' => false,
				'message' => $result['id'][0],
				'errors'  => $result,
			], 404 );
		}

		return new WP_REST_Response( [
			'success' => false,
			'message' => __( 'Failed to close folio.', 'nozule' ),
			'errors'  => $result,
		], 422 );
	}

	/**
	 * Void a folio.
	 */
	public function void( WP_REST_Request $request ): WP_REST_Response {
		$id     = (int) $request->get_param( 'id' );
		$result = $this->folioService->voidFolio( $id );

		if ( $result instanceof Folio ) {
			return new WP_REST_Response( [
				'success' => true,
				'message' => __( 'Folio voided successfully.', 'nozule' ),
				'data'    => $result->toArray(),
			], 200 );
		}

		if ( isset( $result['id'] ) ) {
			return new WP_REST_Response( [
				'success' => false,
				'message' => $result['id'][0],
				'errors'  => $result,
			], 404 );
		}

		return new WP_REST_Response( [
			'success' => false,
			'message' => __( 'Failed to void folio.', 'nozule' ),
			'errors'  => $result,
		], 422 );
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
