<?php

namespace Nozule\Modules\Groups\Controllers;

use Nozule\Modules\Groups\Models\GroupBooking;
use Nozule\Modules\Groups\Services\GroupBookingService;

/**
 * REST controller for admin group booking management.
 *
 * Route namespace: nozule/v1
 */
class GroupBookingController {

	private GroupBookingService $service;

	private const NAMESPACE = 'nozule/v1';

	public function __construct( GroupBookingService $service ) {
		$this->service = $service;
	}

	/**
	 * Register admin REST routes.
	 */
	public function registerRoutes(): void {
		$admin_perm = [ $this, 'checkAdminPermission' ];
		$staff_perm = [ $this, 'checkStaffPermission' ];

		// GET /admin/groups -- list groups.
		register_rest_route( self::NAMESPACE, '/admin/groups', [
			'methods'             => \WP_REST_Server::READABLE,
			'callback'            => [ $this, 'index' ],
			'permission_callback' => $staff_perm,
			'args'                => $this->getListArgs(),
		] );

		// POST /admin/groups -- create group.
		register_rest_route( self::NAMESPACE, '/admin/groups', [
			'methods'             => \WP_REST_Server::CREATABLE,
			'callback'            => [ $this, 'store' ],
			'permission_callback' => $admin_perm,
		] );

		// GET /admin/groups/(?P<id>\d+) -- show group with rooms.
		register_rest_route( self::NAMESPACE, '/admin/groups/(?P<id>\d+)', [
			'methods'             => \WP_REST_Server::READABLE,
			'callback'            => [ $this, 'show' ],
			'permission_callback' => $staff_perm,
		] );

		// PUT /admin/groups/(?P<id>\d+) -- update group.
		register_rest_route( self::NAMESPACE, '/admin/groups/(?P<id>\d+)', [
			'methods'             => 'PUT, PATCH',
			'callback'            => [ $this, 'update' ],
			'permission_callback' => $admin_perm,
		] );

		// DELETE /admin/groups/(?P<id>\d+) -- delete/cancel group.
		register_rest_route( self::NAMESPACE, '/admin/groups/(?P<id>\d+)', [
			'methods'             => \WP_REST_Server::DELETABLE,
			'callback'            => [ $this, 'destroy' ],
			'permission_callback' => $admin_perm,
			'args'                => [
				'reason' => [
					'type'              => 'string',
					'default'           => '',
					'sanitize_callback' => 'sanitize_textarea_field',
				],
			],
		] );

		// PUT /admin/groups/(?P<id>\d+)/confirm -- confirm group.
		register_rest_route( self::NAMESPACE, '/admin/groups/(?P<id>\d+)/confirm', [
			'methods'             => 'PUT',
			'callback'            => [ $this, 'confirm' ],
			'permission_callback' => $admin_perm,
		] );

		// PUT /admin/groups/(?P<id>\d+)/check-in -- bulk check-in.
		register_rest_route( self::NAMESPACE, '/admin/groups/(?P<id>\d+)/check-in', [
			'methods'             => 'PUT',
			'callback'            => [ $this, 'checkIn' ],
			'permission_callback' => $admin_perm,
		] );

		// PUT /admin/groups/(?P<id>\d+)/check-out -- bulk check-out.
		register_rest_route( self::NAMESPACE, '/admin/groups/(?P<id>\d+)/check-out', [
			'methods'             => 'PUT',
			'callback'            => [ $this, 'checkOut' ],
			'permission_callback' => $admin_perm,
		] );

		// POST /admin/groups/(?P<id>\d+)/rooms -- add room to group.
		register_rest_route( self::NAMESPACE, '/admin/groups/(?P<id>\d+)/rooms', [
			'methods'             => \WP_REST_Server::CREATABLE,
			'callback'            => [ $this, 'addRoom' ],
			'permission_callback' => $admin_perm,
		] );

		// DELETE /admin/groups/rooms/(?P<id>\d+) -- remove room allocation.
		register_rest_route( self::NAMESPACE, '/admin/groups/rooms/(?P<id>\d+)', [
			'methods'             => \WP_REST_Server::DELETABLE,
			'callback'            => [ $this, 'removeRoom' ],
			'permission_callback' => $admin_perm,
		] );
	}

	// ── Permission Checks ───────────────────────────────────────────

	/**
	 * Permission check: current user has manage_options (admin).
	 */
	public function checkAdminPermission(): bool {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Permission check: current user can at least edit_posts (staff).
	 */
	public function checkStaffPermission(): bool {
		return current_user_can( 'edit_posts' );
	}

	// ── Endpoints ───────────────────────────────────────────────────

	/**
	 * GET /admin/groups
	 *
	 * List group bookings with filters, search, and pagination.
	 */
	public function index( \WP_REST_Request $request ): \WP_REST_Response {
		$result = $this->service->getGroups( [
			'status'    => $request->get_param( 'status' ) ?? '',
			'date_from' => $request->get_param( 'date_from' ) ?? '',
			'date_to'   => $request->get_param( 'date_to' ) ?? '',
			'search'    => $request->get_param( 'search' ) ?? '',
			'orderby'   => $request->get_param( 'orderby' ) ?? 'created_at',
			'order'     => $request->get_param( 'order' ) ?? 'DESC',
			'per_page'  => (int) ( $request->get_param( 'per_page' ) ?? 20 ),
			'page'      => (int) ( $request->get_param( 'page' ) ?? 1 ),
		] );

		return new \WP_REST_Response( [
			'success' => true,
			'data'    => array_map( fn( GroupBooking $g ) => $g->toArray(), $result['groups'] ),
			'meta'    => [
				'total' => $result['total'],
				'pages' => $result['pages'],
			],
		], 200 );
	}

	/**
	 * POST /admin/groups
	 *
	 * Create a new group booking.
	 */
	public function store( \WP_REST_Request $request ): \WP_REST_Response {
		try {
			$group = $this->service->createGroup( $request->get_params() );

			return new \WP_REST_Response( [
				'success' => true,
				'data'    => $group->toArray(),
			], 201 );
		} catch ( \InvalidArgumentException $e ) {
			return new \WP_REST_Response( [
				'success' => false,
				'message' => $e->getMessage(),
			], 400 );
		} catch ( \Throwable $e ) {
			return new \WP_REST_Response( [
				'success' => false,
				'message' => __( 'Failed to create group booking.', 'nozule' ),
			], 500 );
		}
	}

	/**
	 * GET /admin/groups/{id}
	 *
	 * Get a single group booking with all room allocations.
	 */
	public function show( \WP_REST_Request $request ): \WP_REST_Response {
		$data = $this->service->getGroupWithRooms( (int) $request->get_param( 'id' ) );

		if ( ! $data ) {
			return new \WP_REST_Response( [
				'success' => false,
				'message' => __( 'Group booking not found.', 'nozule' ),
			], 404 );
		}

		return new \WP_REST_Response( [
			'success' => true,
			'data'    => $data,
		], 200 );
	}

	/**
	 * PUT /admin/groups/{id}
	 *
	 * Update group booking fields.
	 */
	public function update( \WP_REST_Request $request ): \WP_REST_Response {
		try {
			$group = $this->service->updateGroup(
				(int) $request->get_param( 'id' ),
				$request->get_params()
			);

			return new \WP_REST_Response( [
				'success' => true,
				'data'    => $group->toArray(),
			], 200 );
		} catch ( \InvalidArgumentException $e ) {
			return new \WP_REST_Response( [
				'success' => false,
				'message' => $e->getMessage(),
			], 400 );
		} catch ( \RuntimeException $e ) {
			return new \WP_REST_Response( [
				'success' => false,
				'message' => $e->getMessage(),
			], 404 );
		} catch ( \Throwable $e ) {
			return new \WP_REST_Response( [
				'success' => false,
				'message' => __( 'Failed to update group booking.', 'nozule' ),
			], 500 );
		}
	}

	/**
	 * DELETE /admin/groups/{id}
	 *
	 * Cancel a group booking (soft delete via status change).
	 */
	public function destroy( \WP_REST_Request $request ): \WP_REST_Response {
		try {
			$group = $this->service->cancelGroup(
				(int) $request->get_param( 'id' ),
				$request->get_param( 'reason' ) ?? ''
			);

			return new \WP_REST_Response( [
				'success' => true,
				'data'    => $group->toArray(),
			], 200 );
		} catch ( \InvalidArgumentException $e ) {
			return new \WP_REST_Response( [
				'success' => false,
				'message' => $e->getMessage(),
			], 409 );
		} catch ( \RuntimeException $e ) {
			return new \WP_REST_Response( [
				'success' => false,
				'message' => $e->getMessage(),
			], 404 );
		} catch ( \Throwable $e ) {
			return new \WP_REST_Response( [
				'success' => false,
				'message' => __( 'Failed to cancel group booking.', 'nozule' ),
			], 500 );
		}
	}

	/**
	 * PUT /admin/groups/{id}/confirm
	 *
	 * Confirm a tentative group booking.
	 */
	public function confirm( \WP_REST_Request $request ): \WP_REST_Response {
		try {
			$group = $this->service->confirmGroup( (int) $request->get_param( 'id' ) );

			return new \WP_REST_Response( [
				'success' => true,
				'data'    => $group->toArray(),
			], 200 );
		} catch ( \InvalidArgumentException $e ) {
			return new \WP_REST_Response( [
				'success' => false,
				'message' => $e->getMessage(),
			], 409 );
		} catch ( \Throwable $e ) {
			return new \WP_REST_Response( [
				'success' => false,
				'message' => __( 'Failed to confirm group booking.', 'nozule' ),
			], 500 );
		}
	}

	/**
	 * PUT /admin/groups/{id}/check-in
	 *
	 * Bulk check-in all rooms in a group booking.
	 */
	public function checkIn( \WP_REST_Request $request ): \WP_REST_Response {
		try {
			$group = $this->service->bulkCheckIn( (int) $request->get_param( 'id' ) );

			return new \WP_REST_Response( [
				'success' => true,
				'data'    => $group->toArray(),
			], 200 );
		} catch ( \InvalidArgumentException $e ) {
			return new \WP_REST_Response( [
				'success' => false,
				'message' => $e->getMessage(),
			], 409 );
		} catch ( \Throwable $e ) {
			return new \WP_REST_Response( [
				'success' => false,
				'message' => __( 'Failed to check in group.', 'nozule' ),
			], 500 );
		}
	}

	/**
	 * PUT /admin/groups/{id}/check-out
	 *
	 * Bulk check-out all rooms in a group booking.
	 */
	public function checkOut( \WP_REST_Request $request ): \WP_REST_Response {
		try {
			$group = $this->service->bulkCheckOut( (int) $request->get_param( 'id' ) );

			return new \WP_REST_Response( [
				'success' => true,
				'data'    => $group->toArray(),
			], 200 );
		} catch ( \InvalidArgumentException $e ) {
			return new \WP_REST_Response( [
				'success' => false,
				'message' => $e->getMessage(),
			], 409 );
		} catch ( \Throwable $e ) {
			return new \WP_REST_Response( [
				'success' => false,
				'message' => __( 'Failed to check out group.', 'nozule' ),
			], 500 );
		}
	}

	/**
	 * POST /admin/groups/{id}/rooms
	 *
	 * Add a room allocation to a group booking.
	 */
	public function addRoom( \WP_REST_Request $request ): \WP_REST_Response {
		try {
			$room = $this->service->addRoom(
				(int) $request->get_param( 'id' ),
				$request->get_params()
			);

			return new \WP_REST_Response( [
				'success' => true,
				'data'    => $room->toArray(),
			], 201 );
		} catch ( \InvalidArgumentException $e ) {
			return new \WP_REST_Response( [
				'success' => false,
				'message' => $e->getMessage(),
			], 400 );
		} catch ( \RuntimeException $e ) {
			return new \WP_REST_Response( [
				'success' => false,
				'message' => $e->getMessage(),
			], 404 );
		} catch ( \Throwable $e ) {
			return new \WP_REST_Response( [
				'success' => false,
				'message' => __( 'Failed to add room to group.', 'nozule' ),
			], 500 );
		}
	}

	/**
	 * DELETE /admin/groups/rooms/{id}
	 *
	 * Remove a room allocation from a group booking.
	 */
	public function removeRoom( \WP_REST_Request $request ): \WP_REST_Response {
		try {
			$this->service->removeRoom( (int) $request->get_param( 'id' ) );

			return new \WP_REST_Response( [
				'success' => true,
				'message' => __( 'Room allocation removed.', 'nozule' ),
			], 200 );
		} catch ( \RuntimeException $e ) {
			return new \WP_REST_Response( [
				'success' => false,
				'message' => $e->getMessage(),
			], 404 );
		} catch ( \Throwable $e ) {
			return new \WP_REST_Response( [
				'success' => false,
				'message' => __( 'Failed to remove room from group.', 'nozule' ),
			], 500 );
		}
	}

	// ── Helpers ─────────────────────────────────────────────────────

	/**
	 * Argument definitions for the list endpoint.
	 */
	private function getListArgs(): array {
		return [
			'status' => [
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			],
			'date_from' => [
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			],
			'date_to' => [
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			],
			'search' => [
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			],
			'orderby' => [
				'type'              => 'string',
				'default'           => 'created_at',
				'sanitize_callback' => 'sanitize_text_field',
			],
			'order' => [
				'type'              => 'string',
				'default'           => 'DESC',
				'sanitize_callback' => 'sanitize_text_field',
			],
			'per_page' => [
				'type'              => 'integer',
				'default'           => 20,
				'sanitize_callback' => 'absint',
			],
			'page' => [
				'type'              => 'integer',
				'default'           => 1,
				'sanitize_callback' => 'absint',
			],
		];
	}
}
