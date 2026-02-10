<?php

namespace Venezia\Modules\Rooms\Controllers;

use Venezia\Modules\Rooms\Models\RoomType;
use Venezia\Modules\Rooms\Services\RoomService;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * REST controller for room type CRUD operations and public listing.
 *
 * Routes:
 *   GET    /venezia/v1/room-types          (public)  List active room types
 *   GET    /venezia/v1/room-types/{id}     (public)  Get single room type
 *   POST   /venezia/v1/room-types          (admin)   Create room type
 *   PUT    /venezia/v1/room-types/{id}     (admin)   Update room type
 *   DELETE /venezia/v1/room-types/{id}     (admin)   Delete room type
 *   POST   /venezia/v1/room-types/reorder  (admin)   Reorder room types
 *   GET    /venezia/v1/admin/room-types    (admin)   List all room types (including inactive)
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
		$namespace = 'venezia/v1';

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

		return new WP_REST_Response( [
			'success' => true,
			'data'    => $data,
		], 200 );
	}

	/**
	 * Public: get a single room type by ID.
	 */
	public function show( WP_REST_Request $request ): WP_REST_Response {
		$id       = (int) $request->get_param( 'id' );
		$roomType = $this->roomService->findRoomType( $id );

		if ( ! $roomType ) {
			return new WP_REST_Response( [
				'success' => false,
				'message' => __( 'Room type not found.', 'venezia-hotel' ),
			], 404 );
		}

		return new WP_REST_Response( [
			'success' => true,
			'data'    => $roomType->toPublicArray(),
		], 200 );
	}

	/**
	 * Admin: list all room types (including inactive).
	 */
	public function listAll( WP_REST_Request $request ): WP_REST_Response {
		$roomTypes = $this->roomService->getAllRoomTypes();

		$data = array_map(
			fn( RoomType $rt ) => $rt->toPublicArray(),
			$roomTypes
		);

		return new WP_REST_Response( [
			'success' => true,
			'data'    => $data,
		], 200 );
	}

	/**
	 * Admin: create a new room type.
	 */
	public function create( WP_REST_Request $request ): WP_REST_Response {
		$data = $this->extractRoomTypeData( $request );

		$result = $this->roomService->createRoomType( $data );

		if ( $result instanceof RoomType ) {
			return new WP_REST_Response( [
				'success' => true,
				'message' => __( 'Room type created successfully.', 'venezia-hotel' ),
				'data'    => $result->toPublicArray(),
			], 201 );
		}

		return new WP_REST_Response( [
			'success' => false,
			'message' => __( 'Validation failed.', 'venezia-hotel' ),
			'errors'  => $result,
		], 422 );
	}

	/**
	 * Admin: update an existing room type.
	 */
	public function update( WP_REST_Request $request ): WP_REST_Response {
		$id   = (int) $request->get_param( 'id' );
		$data = $this->extractRoomTypeData( $request );

		$result = $this->roomService->updateRoomType( $id, $data );

		if ( $result instanceof RoomType ) {
			return new WP_REST_Response( [
				'success' => true,
				'message' => __( 'Room type updated successfully.', 'venezia-hotel' ),
				'data'    => $result->toPublicArray(),
			], 200 );
		}

		// Check if the error is "not found".
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
	 * Admin: delete a room type.
	 */
	public function delete( WP_REST_Request $request ): WP_REST_Response {
		$id     = (int) $request->get_param( 'id' );
		$result = $this->roomService->deleteRoomType( $id );

		if ( $result === true ) {
			return new WP_REST_Response( [
				'success' => true,
				'message' => __( 'Room type deleted successfully.', 'venezia-hotel' ),
			], 200 );
		}

		$statusCode = isset( $result['id'] ) ? 404 : 422;

		return new WP_REST_Response( [
			'success' => false,
			'message' => __( 'Failed to delete room type.', 'venezia-hotel' ),
			'errors'  => $result,
		], $statusCode );
	}

	/**
	 * Admin: reorder room types.
	 */
	public function reorder( WP_REST_Request $request ): WP_REST_Response {
		$orderedIds = $request->get_param( 'ordered_ids' );

		if ( ! is_array( $orderedIds ) || empty( $orderedIds ) ) {
			return new WP_REST_Response( [
				'success' => false,
				'message' => __( 'ordered_ids is required and must be a non-empty array.', 'venezia-hotel' ),
			], 422 );
		}

		$orderedIds = array_map( 'absint', $orderedIds );
		$success    = $this->roomService->reorderRoomTypes( $orderedIds );

		if ( $success ) {
			return new WP_REST_Response( [
				'success' => true,
				'message' => __( 'Room types reordered successfully.', 'venezia-hotel' ),
			], 200 );
		}

		return new WP_REST_Response( [
			'success' => false,
			'message' => __( 'Failed to reorder room types.', 'venezia-hotel' ),
		], 500 );
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
