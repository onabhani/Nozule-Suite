<?php

namespace Nozule\Modules\Rooms\Controllers;

use Nozule\Core\ResponseHelper;
use Nozule\Modules\Rooms\Models\RoomType;
use Nozule\Modules\Rooms\Services\RoomService;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * REST controller for room type CRUD operations and public listing.
 *
 * Routes:
 *   GET    /nozule/v1/room-types          (public)  List active room types
 *   GET    /nozule/v1/room-types/{id}     (public)  Get single room type
 *   POST   /nozule/v1/room-types          (admin)   Create room type
 *   PUT    /nozule/v1/room-types/{id}     (admin)   Update room type
 *   DELETE /nozule/v1/room-types/{id}     (admin)   Delete room type
 *   POST   /nozule/v1/room-types/reorder  (admin)   Reorder room types
 *   GET    /nozule/v1/admin/room-types    (admin)   List all room types (including inactive)
 */
class RoomTypeController {

	private RoomService $roomService;

	public function __construct( RoomService $roomService ) {
		$this->roomService = $roomService;
	}

	/**
	 * Register REST routes.
	 */
	public function registerRoutes(): void {
		$namespace = 'nozule/v1';

		// Public: list active room types.
		register_rest_route( $namespace, '/room-types', [
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'listPublic' ],
				'permission_callback' => '__return_true',
			],
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'create' ],
				'permission_callback' => [ $this, 'checkAdminPermission' ],
			],
		] );

		// Public: get single room type.
		register_rest_route( $namespace, '/room-types/(?P<id>\d+)', [
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'show' ],
				'permission_callback' => '__return_true',
				'args'                => [
					'id' => [
						'required'          => true,
						'validate_callback' => fn( $value ) => is_numeric( $value ) && $value > 0,
						'sanitize_callback' => 'absint',
					],
				],
			],
			[
				'methods'             => WP_REST_Server::EDITABLE,
				'callback'            => [ $this, 'update' ],
				'permission_callback' => [ $this, 'checkAdminPermission' ],
				'args'                => [
					'id' => [
						'required'          => true,
						'validate_callback' => fn( $value ) => is_numeric( $value ) && $value > 0,
						'sanitize_callback' => 'absint',
					],
				],
			],
			[
				'methods'             => WP_REST_Server::DELETABLE,
				'callback'            => [ $this, 'delete' ],
				'permission_callback' => [ $this, 'checkAdminPermission' ],
				'args'                => [
					'id' => [
						'required'          => true,
						'validate_callback' => fn( $value ) => is_numeric( $value ) && $value > 0,
						'sanitize_callback' => 'absint',
					],
				],
			],
		] );

		// Admin: reorder room types.
		register_rest_route( $namespace, '/room-types/reorder', [
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => [ $this, 'reorder' ],
			'permission_callback' => [ $this, 'checkAdminPermission' ],
		] );

		// Admin: list all room types (including inactive).
		register_rest_route( $namespace, '/admin/room-types', [
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => [ $this, 'listAll' ],
			'permission_callback' => [ $this, 'checkStaffPermission' ],
		] );
	}

	/**
	 * Public: list active room types.
	 */
	public function listPublic( WP_REST_Request $request ): WP_REST_Response {
		$roomTypes = $this->roomService->getActiveRoomTypes();

		$data = array_map(
			fn( RoomType $rt ) => $rt->toPublicArray(),
			$roomTypes
		);

		return ResponseHelper::success( $data );
	}

	/**
	 * Public: get a single room type by ID.
	 */
	public function show( WP_REST_Request $request ): WP_REST_Response {
		$id       = (int) $request->get_param( 'id' );
		$roomType = $this->roomService->findRoomType( $id );

		if ( ! $roomType ) {
			return ResponseHelper::notFound( __( 'Room type not found.', 'nozule' ) );
		}

		return ResponseHelper::success( $roomType->toPublicArray() );
	}

	/**
	 * Admin: list all room types (including inactive).
	 */
	public function listAll( WP_REST_Request $request ): WP_REST_Response {
		$roomTypes = $this->roomService->getAllRoomTypesFresh();

		$data = array_map(
			fn( RoomType $rt ) => $rt->toPublicArray(),
			$roomTypes
		);

		return ResponseHelper::success( $data );
	}

	/**
	 * Admin: create a new room type.
	 */
	public function create( WP_REST_Request $request ): WP_REST_Response {
		$data = $this->extractRoomTypeData( $request );

		$result = $this->roomService->createRoomType( $data );

		if ( $result instanceof RoomType ) {
			return ResponseHelper::created( $result->toPublicArray(), __( 'Room type created successfully.', 'nozule' ) );
		}

		return ResponseHelper::error( __( 'Validation failed.', 'nozule' ), 422, $result );
	}

	/**
	 * Admin: update an existing room type.
	 */
	public function update( WP_REST_Request $request ): WP_REST_Response {
		$id   = (int) $request->get_param( 'id' );
		$data = $this->extractRoomTypeData( $request );

		$result = $this->roomService->updateRoomType( $id, $data );

		if ( $result instanceof RoomType ) {
			return ResponseHelper::success( $result->toPublicArray(), __( 'Room type updated successfully.', 'nozule' ) );
		}

		// Check if the error is "not found".
		if ( isset( $result['id'] ) ) {
			return ResponseHelper::error( $result['id'][0], 404, $result );
		}

		return ResponseHelper::error( __( 'Validation failed.', 'nozule' ), 422, $result );
	}

	/**
	 * Admin: delete a room type.
	 */
	public function delete( WP_REST_Request $request ): WP_REST_Response {
		$id     = (int) $request->get_param( 'id' );
		$result = $this->roomService->deleteRoomType( $id );

		if ( $result === true ) {
			return ResponseHelper::success( null, __( 'Room type deleted successfully.', 'nozule' ) );
		}

		$statusCode = isset( $result['id'] ) ? 404 : 422;

		return ResponseHelper::error( __( 'Failed to delete room type.', 'nozule' ), $statusCode, $result );
	}

	/**
	 * Admin: reorder room types.
	 */
	public function reorder( WP_REST_Request $request ): WP_REST_Response {
		$orderedIds = $request->get_param( 'ordered_ids' );

		if ( ! is_array( $orderedIds ) || empty( $orderedIds ) ) {
			return ResponseHelper::error( __( 'ordered_ids is required and must be a non-empty array.', 'nozule' ), 422 );
		}

		$orderedIds = array_map( 'absint', $orderedIds );
		$success    = $this->roomService->reorderRoomTypes( $orderedIds );

		if ( $success ) {
			return ResponseHelper::success( null, __( 'Room types reordered successfully.', 'nozule' ) );
		}

		return ResponseHelper::error( __( 'Failed to reorder room types.', 'nozule' ), 500 );
	}

	/**
	 * Extract room type data from the request.
	 */
	private function extractRoomTypeData( WP_REST_Request $request ): array {
		$fields = [
			'name',
			'slug',
			'description',
			'max_occupancy',
			'base_occupancy',
			'base_price',
			'extra_adult_price',
			'extra_child_price',
			'amenities',
			'images',
			'sort_order',
			'status',
		];

		$data = [];

		foreach ( $fields as $field ) {
			$value = $request->get_param( $field );
			if ( $value !== null ) {
				$data[ $field ] = $value;
			}
		}

		// Sanitize text fields.
		if ( isset( $data['name'] ) ) {
			$data['name'] = sanitize_text_field( $data['name'] );
		}
		if ( isset( $data['slug'] ) ) {
			$data['slug'] = sanitize_title( $data['slug'] );
		}
		if ( isset( $data['description'] ) ) {
			$data['description'] = sanitize_textarea_field( $data['description'] );
		}

		// Cast numeric fields.
		if ( isset( $data['max_occupancy'] ) ) {
			$data['max_occupancy'] = absint( $data['max_occupancy'] );
		}
		if ( isset( $data['base_occupancy'] ) ) {
			$data['base_occupancy'] = absint( $data['base_occupancy'] );
		}
		if ( isset( $data['base_price'] ) ) {
			$data['base_price'] = (float) $data['base_price'];
		}
		if ( isset( $data['extra_adult_price'] ) ) {
			$data['extra_adult_price'] = (float) $data['extra_adult_price'];
		}
		if ( isset( $data['extra_child_price'] ) ) {
			$data['extra_child_price'] = (float) $data['extra_child_price'];
		}
		if ( isset( $data['sort_order'] ) ) {
			$data['sort_order'] = absint( $data['sort_order'] );
		}

		return $data;
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

	// Standard CRUD aliases (used by RestController admin routes)

	public function index( WP_REST_Request $request ): WP_REST_Response {
		return $this->listAll( $request );
	}

	public function store( WP_REST_Request $request ): WP_REST_Response {
		return $this->create( $request );
	}

	public function destroy( WP_REST_Request $request ): WP_REST_Response {
		return $this->delete( $request );
	}
}
