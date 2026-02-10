<?php

namespace Venezia\Modules\Rooms\Controllers;

use Venezia\Modules\Rooms\Models\Room;
use Venezia\Modules\Rooms\Services\RoomService;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * REST controller for individual room administration.
 *
 * Routes (all admin-only):
 *   GET    /venezia/v1/admin/rooms            List rooms (filterable)
 *   GET    /venezia/v1/admin/rooms/{id}       Get single room
 *   POST   /venezia/v1/admin/rooms            Create room
 *   PUT    /venezia/v1/admin/rooms/{id}       Update room
 *   DELETE /venezia/v1/admin/rooms/{id}       Delete room
 *   PATCH  /venezia/v1/admin/rooms/{id}/status  Update room status
 */
class RoomController {

	private RoomService $roomService;

	public function __construct( RoomService $roomService ) {
		$this->roomService = $roomService;
	}

	/**
	 * Register REST routes.
	 */
	public function registerRoutes(): void {
		$namespace = 'venezia/v1';

		// List and create rooms.
		register_rest_route( $namespace, '/admin/rooms', [
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'index' ],
				'permission_callback' => [ $this, 'checkStaffPermission' ],
				'args'                => [
					'room_type_id' => [
						'required'          => false,
						'validate_callback' => fn( $v ) => is_numeric( $v ),
						'sanitize_callback' => 'absint',
					],
					'status' => [
						'required'          => false,
						'validate_callback' => fn( $v ) => in_array( $v, Room::validStatuses(), true ),
						'sanitize_callback' => 'sanitize_text_field',
					],
				],
			],
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'create' ],
				'permission_callback' => [ $this, 'checkAdminPermission' ],
			],
		] );

		// Single room operations.
		register_rest_route( $namespace, '/admin/rooms/(?P<id>\d+)', [
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'show' ],
				'permission_callback' => [ $this, 'checkStaffPermission' ],
				'args'                => $this->getIdArgs(),
			],
			[
				'methods'             => WP_REST_Server::EDITABLE,
				'callback'            => [ $this, 'update' ],
				'permission_callback' => [ $this, 'checkAdminPermission' ],
				'args'                => $this->getIdArgs(),
			],
			[
				'methods'             => WP_REST_Server::DELETABLE,
				'callback'            => [ $this, 'delete' ],
				'permission_callback' => [ $this, 'checkAdminPermission' ],
				'args'                => $this->getIdArgs(),
			],
		] );

		// Status update endpoint.
		register_rest_route( $namespace, '/admin/rooms/(?P<id>\d+)/status', [
			'methods'             => WP_REST_Server::EDITABLE,
			'callback'            => [ $this, 'updateStatus' ],
			'permission_callback' => [ $this, 'checkStaffPermission' ],
			'args'                => $this->getIdArgs(),
		] );
	}

	/**
	 * List rooms with optional filters.
	 */
	public function index( WP_REST_Request $request ): WP_REST_Response {
		$roomTypeId = $request->get_param( 'room_type_id' ) ? absint( $request->get_param( 'room_type_id' ) ) : null;
		$status     = $request->get_param( 'status' );

		$rooms = $this->roomService->getRooms( $roomTypeId, $status );

		$data = array_map(
			fn( Room $room ) => $room->toArray(),
			$rooms
		);

		return new WP_REST_Response( [
			'success' => true,
			'data'    => $data,
			'total'   => count( $data ),
		], 200 );
	}

	/**
	 * Get a single room.
	 */
	public function show( WP_REST_Request $request ): WP_REST_Response {
		$id   = (int) $request->get_param( 'id' );
		$room = $this->roomService->findRoom( $id );

		if ( ! $room ) {
			return new WP_REST_Response( [
				'success' => false,
				'message' => __( 'Room not found.', 'venezia-hotel' ),
			], 404 );
		}

		return new WP_REST_Response( [
			'success' => true,
			'data'    => $room->toArray(),
		], 200 );
	}

	/**
	 * Create a new room.
	 */
	public function create( WP_REST_Request $request ): WP_REST_Response {
		$data = $this->extractRoomData( $request );

		$result = $this->roomService->createRoom( $data );

		if ( $result instanceof Room ) {
			return new WP_REST_Response( [
				'success' => true,
				'message' => __( 'Room created successfully.', 'venezia-hotel' ),
				'data'    => $result->toArray(),
			], 201 );
		}

		return new WP_REST_Response( [
			'success' => false,
			'message' => __( 'Validation failed.', 'venezia-hotel' ),
			'errors'  => $result,
		], 422 );
	}

	/**
	 * Update an existing room.
	 */
	public function update( WP_REST_Request $request ): WP_REST_Response {
		$id   = (int) $request->get_param( 'id' );
		$data = $this->extractRoomData( $request );

		$result = $this->roomService->updateRoom( $id, $data );

		if ( $result instanceof Room ) {
			return new WP_REST_Response( [
				'success' => true,
				'message' => __( 'Room updated successfully.', 'venezia-hotel' ),
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
			'message' => __( 'Validation failed.', 'venezia-hotel' ),
			'errors'  => $result,
		], 422 );
	}

	/**
	 * Delete a room.
	 */
	public function delete( WP_REST_Request $request ): WP_REST_Response {
		$id     = (int) $request->get_param( 'id' );
		$result = $this->roomService->deleteRoom( $id );

		if ( $result === true ) {
			return new WP_REST_Response( [
				'success' => true,
				'message' => __( 'Room deleted successfully.', 'venezia-hotel' ),
			], 200 );
		}

		$statusCode = isset( $result['id'] ) ? 404 : 422;

		return new WP_REST_Response( [
			'success' => false,
			'message' => __( 'Failed to delete room.', 'venezia-hotel' ),
			'errors'  => $result,
		], $statusCode );
	}

	/**
	 * Update room status.
	 */
	public function updateStatus( WP_REST_Request $request ): WP_REST_Response {
		$id     = (int) $request->get_param( 'id' );
		$status = sanitize_text_field( $request->get_param( 'status' ) ?? '' );

		if ( ! $status ) {
			return new WP_REST_Response( [
				'success' => false,
				'message' => __( 'Status is required.', 'venezia-hotel' ),
			], 422 );
		}

		$result = $this->roomService->updateRoomStatus( $id, $status );

		if ( $result instanceof Room ) {
			return new WP_REST_Response( [
				'success' => true,
				'message' => __( 'Room status updated.', 'venezia-hotel' ),
				'data'    => $result->toArray(),
			], 200 );
		}

		return new WP_REST_Response( [
			'success' => false,
			'message' => __( 'Failed to update room status.', 'venezia-hotel' ),
			'errors'  => $result,
		], 422 );
	}

	/**
	 * Extract room data from the request.
	 */
	private function extractRoomData( WP_REST_Request $request ): array {
		$fields = [ 'room_type_id', 'room_number', 'floor', 'status', 'notes' ];
		$data   = [];

		foreach ( $fields as $field ) {
			$value = $request->get_param( $field );
			if ( $value !== null ) {
				$data[ $field ] = $value;
			}
		}

		// Sanitize.
		if ( isset( $data['room_number'] ) ) {
			$data['room_number'] = sanitize_text_field( $data['room_number'] );
		}
		if ( isset( $data['room_type_id'] ) ) {
			$data['room_type_id'] = absint( $data['room_type_id'] );
		}
		if ( isset( $data['floor'] ) ) {
			$data['floor'] = (int) $data['floor'];
		}
		if ( isset( $data['status'] ) ) {
			$data['status'] = sanitize_text_field( $data['status'] );
		}
		if ( isset( $data['notes'] ) ) {
			$data['notes'] = sanitize_textarea_field( $data['notes'] );
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
	 * Permission callback: require vhm_admin capability.
	 */
	public function checkAdminPermission( WP_REST_Request $request ): bool {
		return current_user_can( 'manage_options' ) || current_user_can( 'vhm_admin' );
	}

	/**
	 * Permission callback: require vhm_staff or vhm_admin capability.
	 */
	public function checkStaffPermission( WP_REST_Request $request ): bool {
		return current_user_can( 'manage_options' ) || current_user_can( 'vhm_admin' ) || current_user_can( 'vhm_staff' );
	}

	// Standard CRUD aliases (used by RestController admin routes)

	public function store( WP_REST_Request $request ): WP_REST_Response {
		return $this->create( $request );
	}

	public function destroy( WP_REST_Request $request ): WP_REST_Response {
		return $this->delete( $request );
	}
}
