<?php

namespace Nozule\Modules\Rooms\Controllers;

use Nozule\Modules\Rooms\Models\RoomInventory;
use Nozule\Modules\Rooms\Repositories\InventoryRepository;
use Nozule\Modules\Rooms\Services\RoomService;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * REST controller for room inventory management (admin only).
 *
 * Routes:
 *   GET  /nozule/v1/admin/inventory                  Get inventory grid for a date range
 *   POST /nozule/v1/admin/inventory/bulk-update       Bulk update inventory
 *   POST /nozule/v1/admin/inventory/initialize        Initialize inventory for a room type
 */
class InventoryController {

	private InventoryRepository $inventoryRepository;
	private RoomService $roomService;

	public function __construct(
		InventoryRepository $inventoryRepository,
		RoomService $roomService
	) {
		$this->inventoryRepository = $inventoryRepository;
		$this->roomService         = $roomService;
	}

	/**
	 * Register REST routes.
	 */
	public function registerRoutes(): void {
		$namespace = 'nozule/v1';

		// Get inventory for a date range (room_type_id optional — returns all types if omitted).
		register_rest_route( $namespace, '/admin/inventory', [
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => [ $this, 'getInventory' ],
			'permission_callback' => [ $this, 'checkStaffPermission' ],
			'args'                => [
				'room_type_id' => [
					'required'          => false,
					'validate_callback' => fn( $v ) => $v === '' || $v === null || ( is_numeric( $v ) && (int) $v > 0 ),
					'sanitize_callback' => 'absint',
				],
				'start_date' => [
					'required'          => true,
					'validate_callback' => fn( $v ) => (bool) strtotime( $v ),
					'sanitize_callback' => 'sanitize_text_field',
				],
				'end_date' => [
					'required'          => true,
					'validate_callback' => fn( $v ) => (bool) strtotime( $v ),
					'sanitize_callback' => 'sanitize_text_field',
				],
			],
		] );

		// Bulk update inventory.
		register_rest_route( $namespace, '/admin/inventory/bulk-update', [
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => [ $this, 'bulkUpdate' ],
			'permission_callback' => [ $this, 'checkAdminPermission' ],
		] );

		// Initialize inventory.
		register_rest_route( $namespace, '/admin/inventory/initialize', [
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => [ $this, 'initialize' ],
			'permission_callback' => [ $this, 'checkAdminPermission' ],
		] );
	}

	/**
	 * Alias for getInventory — used by RestController CRUD routes.
	 */
	public function index( WP_REST_Request $request ): WP_REST_Response {
		return $this->getInventory( $request );
	}

	/**
	 * Get inventory grid for a date range.
	 *
	 * Returns data grouped by room type with availability indexed by date,
	 * matching the format expected by the admin inventory template.
	 */
	public function getInventory( WP_REST_Request $request ): WP_REST_Response {
		$roomTypeId = absint( $request->get_param( 'room_type_id' ) );
		$startDate  = sanitize_text_field( $request->get_param( 'start_date' ) );
		$endDate    = sanitize_text_field( $request->get_param( 'end_date' ) );

		if ( ! $startDate || ! $endDate ) {
			return new WP_REST_Response( [
				'success' => false,
				'error'   => [
					'code'    => 'MISSING_DATES',
					'message' => __( 'start_date and end_date are required.', 'nozule' ),
				],
			], 400 );
		}

		// Build dates array.
		$dates   = [];
		$current = new \DateTimeImmutable( $startDate );
		$end     = new \DateTimeImmutable( $endDate );
		while ( $current <= $end ) {
			$dates[] = $current->format( 'Y-m-d' );
			$current = $current->modify( '+1 day' );
		}

		// Determine which room types to include.
		if ( $roomTypeId ) {
			$roomType  = $this->roomService->findRoomType( $roomTypeId );
			$roomTypes = $roomType ? [ $roomType ] : [];
		} else {
			$roomTypes = $this->roomService->getActiveRoomTypes();
		}

		// Build inventory data grouped by room type.
		$inventory = [];
		foreach ( $roomTypes as $rt ) {
			$records      = $this->inventoryRepository->getForDateRange( $rt->id, $startDate, $endDate );
			$availability = [];
			$totalRooms   = 0;

			foreach ( $records as $record ) {
				$availability[ $record->date ] = (int) $record->available_rooms;
				if ( ! $totalRooms && $record->total_rooms ) {
					$totalRooms = (int) $record->total_rooms;
				}
			}

			$inventory[] = [
				'id'           => $rt->id,
				'name'         => $rt->name,
				'total_rooms'  => $totalRooms ?: ( $rt->total_rooms ?? 0 ),
				'availability' => $availability,
			];
		}

		return new WP_REST_Response( [
			'success' => true,
			'data'    => [
				'inventory' => $inventory,
				'dates'     => $dates,
			],
		], 200 );
	}

	/**
	 * Bulk update inventory for a room type across a date range.
	 *
	 * Accepts fields: total_rooms, price_override, stop_sell, min_stay.
	 */
	public function bulkUpdate( WP_REST_Request $request ): WP_REST_Response {
		$roomTypeId = absint( $request->get_param( 'room_type_id' ) );
		$startDate  = sanitize_text_field( $request->get_param( 'start_date' ) ?? '' );
		$endDate    = sanitize_text_field( $request->get_param( 'end_date' ) ?? '' );

		if ( ! $roomTypeId || ! $startDate || ! $endDate ) {
			return new WP_REST_Response( [
				'success' => false,
				'message' => __( 'room_type_id, start_date, and end_date are required.', 'nozule' ),
			], 422 );
		}

		if ( strtotime( $endDate ) < strtotime( $startDate ) ) {
			return new WP_REST_Response( [
				'success' => false,
				'message' => __( 'end_date must be on or after start_date.', 'nozule' ),
			], 422 );
		}

		// Extract updateable fields.
		$updateData = [];
		$fields     = [ 'total_rooms', 'price_override', 'stop_sell', 'min_stay' ];

		foreach ( $fields as $field ) {
			$value = $request->get_param( $field );
			if ( $value !== null ) {
				$updateData[ $field ] = $value;
			}
		}

		if ( empty( $updateData ) ) {
			return new WP_REST_Response( [
				'success' => false,
				'message' => __( 'No fields provided for update.', 'nozule' ),
			], 422 );
		}

		// Sanitize values.
		if ( isset( $updateData['total_rooms'] ) ) {
			$updateData['total_rooms'] = absint( $updateData['total_rooms'] );
		}
		if ( isset( $updateData['price_override'] ) ) {
			$updateData['price_override'] = $updateData['price_override'] === '' ? null : (float) $updateData['price_override'];
		}
		if ( isset( $updateData['stop_sell'] ) ) {
			$updateData['stop_sell'] = (int) (bool) $updateData['stop_sell'];
		}
		if ( isset( $updateData['min_stay'] ) ) {
			$updateData['min_stay'] = max( 1, absint( $updateData['min_stay'] ) );
		}

		$success = $this->inventoryRepository->bulkUpdate( $roomTypeId, $startDate, $endDate, $updateData );

		if ( $success ) {
			return new WP_REST_Response( [
				'success' => true,
				'message' => __( 'Inventory updated successfully.', 'nozule' ),
			], 200 );
		}

		return new WP_REST_Response( [
			'success' => false,
			'message' => __( 'Failed to update inventory.', 'nozule' ),
		], 500 );
	}

	/**
	 * Initialize inventory records for a room type.
	 *
	 * Creates inventory rows for dates that do not yet have them.
	 */
	public function initialize( WP_REST_Request $request ): WP_REST_Response {
		$roomTypeId = absint( $request->get_param( 'room_type_id' ) );
		$startDate  = sanitize_text_field( $request->get_param( 'start_date' ) ?? '' );
		$endDate    = sanitize_text_field( $request->get_param( 'end_date' ) ?? '' );

		if ( ! $roomTypeId || ! $startDate || ! $endDate ) {
			return new WP_REST_Response( [
				'success' => false,
				'message' => __( 'room_type_id, start_date, and end_date are required.', 'nozule' ),
			], 422 );
		}

		$roomType = $this->roomService->findRoomType( $roomTypeId );
		if ( ! $roomType ) {
			return new WP_REST_Response( [
				'success' => false,
				'message' => __( 'Room type not found.', 'nozule' ),
			], 404 );
		}

		$created = $this->roomService->initializeInventory( $roomTypeId, $startDate, $endDate );

		return new WP_REST_Response( [
			'success' => true,
			'message' => sprintf(
				__( 'Inventory initialized: %d new day(s) created.', 'nozule' ),
				$created
			),
			'data'    => [
				'days_created' => $created,
			],
		], 200 );
	}

	/**
	 * Permission callback: require manage_options or nzl_manage_inventory capability.
	 */
	public function checkAdminPermission( WP_REST_Request $request ): bool {
		return current_user_can( 'manage_options' ) || current_user_can( 'nzl_manage_inventory' );
	}

	/**
	 * Permission callback: require manage_options or nzl_staff capability.
	 */
	public function checkStaffPermission( WP_REST_Request $request ): bool {
		return current_user_can( 'manage_options' ) || current_user_can( 'nzl_staff' );
	}
}
