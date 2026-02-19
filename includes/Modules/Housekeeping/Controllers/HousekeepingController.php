<?php

namespace Nozule\Modules\Housekeeping\Controllers;

use Nozule\Modules\Housekeeping\Models\HousekeepingTask;
use Nozule\Modules\Housekeeping\Services\HousekeepingService;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * REST controller for housekeeping task administration.
 *
 * Routes (all staff-level):
 *   GET    /nozule/v1/admin/housekeeping               List tasks (filterable)
 *   POST   /nozule/v1/admin/housekeeping               Create task
 *   GET    /nozule/v1/admin/housekeeping/{id}           Get single task
 *   PUT    /nozule/v1/admin/housekeeping/{id}           Update task
 *   PUT    /nozule/v1/admin/housekeeping/{id}/status    Update task status
 *   PUT    /nozule/v1/admin/housekeeping/{id}/assign    Assign task to user
 *   GET    /nozule/v1/admin/housekeeping/stats          Status counts
 */
class HousekeepingController {

	private HousekeepingService $housekeepingService;

	public function __construct( HousekeepingService $housekeepingService ) {
		$this->housekeepingService = $housekeepingService;
	}

	/**
	 * Register REST routes.
	 */
	public function registerRoutes(): void {
		$namespace = 'nozule/v1';

		// Stats endpoint (must be registered before the (?P<id>\d+) route).
		register_rest_route( $namespace, '/admin/housekeeping/stats', [
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => [ $this, 'stats' ],
			'permission_callback' => [ $this, 'checkStaffPermission' ],
		] );

		// List and create tasks.
		register_rest_route( $namespace, '/admin/housekeeping', [
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'index' ],
				'permission_callback' => [ $this, 'checkStaffPermission' ],
				'args'                => [
					'status' => [
						'required'          => false,
						'validate_callback' => fn( $v ) => in_array( $v, HousekeepingTask::validStatuses(), true ),
						'sanitize_callback' => 'sanitize_text_field',
					],
					'room_id' => [
						'required'          => false,
						'validate_callback' => fn( $v ) => is_numeric( $v ),
						'sanitize_callback' => 'absint',
					],
					'assigned_to' => [
						'required'          => false,
						'validate_callback' => fn( $v ) => is_numeric( $v ),
						'sanitize_callback' => 'absint',
					],
				],
			],
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'create' ],
				'permission_callback' => [ $this, 'checkStaffPermission' ],
			],
		] );

		// Single task operations.
		register_rest_route( $namespace, '/admin/housekeeping/(?P<id>\d+)', [
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'show' ],
				'permission_callback' => [ $this, 'checkStaffPermission' ],
				'args'                => $this->getIdArgs(),
			],
			[
				'methods'             => WP_REST_Server::EDITABLE,
				'callback'            => [ $this, 'update' ],
				'permission_callback' => [ $this, 'checkStaffPermission' ],
				'args'                => $this->getIdArgs(),
			],
		] );

		// Status update endpoint.
		register_rest_route( $namespace, '/admin/housekeeping/(?P<id>\d+)/status', [
			'methods'             => WP_REST_Server::EDITABLE,
			'callback'            => [ $this, 'updateStatus' ],
			'permission_callback' => [ $this, 'checkStaffPermission' ],
			'args'                => $this->getIdArgs(),
		] );

		// Assign task endpoint.
		register_rest_route( $namespace, '/admin/housekeeping/(?P<id>\d+)/assign', [
			'methods'             => WP_REST_Server::EDITABLE,
			'callback'            => [ $this, 'assign' ],
			'permission_callback' => [ $this, 'checkStaffPermission' ],
			'args'                => $this->getIdArgs(),
		] );
	}

	/**
	 * List housekeeping tasks with optional filters.
	 */
	public function index( WP_REST_Request $request ): WP_REST_Response {
		$status     = $request->get_param( 'status' );
		$roomId     = $request->get_param( 'room_id' ) ? absint( $request->get_param( 'room_id' ) ) : null;
		$assignedTo = $request->get_param( 'assigned_to' ) ? absint( $request->get_param( 'assigned_to' ) ) : null;

		// If no filters, return all tasks with room info.
		if ( ! $status && ! $roomId && ! $assignedTo ) {
			$tasks = $this->housekeepingService->getTasksWithRoomInfo();
		} else {
			$tasks = $this->housekeepingService->getTasks( $status, $roomId, $assignedTo );
		}

		$data = array_map(
			fn( HousekeepingTask $task ) => $task->toArray(),
			$tasks
		);

		return new WP_REST_Response( [
			'success' => true,
			'data'    => $data,
			'total'   => count( $data ),
		], 200 );
	}

	/**
	 * Get a single housekeeping task.
	 */
	public function show( WP_REST_Request $request ): WP_REST_Response {
		$id   = (int) $request->get_param( 'id' );
		$task = $this->housekeepingService->findTask( $id );

		if ( ! $task ) {
			return new WP_REST_Response( [
				'success' => false,
				'message' => __( 'Housekeeping task not found.', 'nozule' ),
			], 404 );
		}

		return new WP_REST_Response( [
			'success' => true,
			'data'    => $task->toArray(),
		], 200 );
	}

	/**
	 * Create a new housekeeping task.
	 */
	public function create( WP_REST_Request $request ): WP_REST_Response {
		$data = $this->extractTaskData( $request );

		$result = $this->housekeepingService->createTask( $data );

		if ( $result instanceof HousekeepingTask ) {
			return new WP_REST_Response( [
				'success' => true,
				'message' => __( 'Housekeeping task created successfully.', 'nozule' ),
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
	 * Update an existing housekeeping task.
	 */
	public function update( WP_REST_Request $request ): WP_REST_Response {
		$id   = (int) $request->get_param( 'id' );
		$data = $this->extractTaskData( $request );

		$result = $this->housekeepingService->updateTask( $id, $data );

		if ( $result instanceof HousekeepingTask ) {
			return new WP_REST_Response( [
				'success' => true,
				'message' => __( 'Housekeeping task updated successfully.', 'nozule' ),
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
			'message' => __( 'Validation failed.', 'nozule' ),
			'errors'  => $result,
		], 422 );
	}

	/**
	 * Update housekeeping task status.
	 */
	public function updateStatus( WP_REST_Request $request ): WP_REST_Response {
		$id     = (int) $request->get_param( 'id' );
		$status = sanitize_text_field( $request->get_param( 'status' ) ?? '' );

		if ( ! $status ) {
			return new WP_REST_Response( [
				'success' => false,
				'message' => __( 'Status is required.', 'nozule' ),
			], 422 );
		}

		$result = $this->housekeepingService->updateTaskStatus( $id, $status );

		if ( $result instanceof HousekeepingTask ) {
			return new WP_REST_Response( [
				'success' => true,
				'message' => __( 'Task status updated.', 'nozule' ),
				'data'    => $result->toArray(),
			], 200 );
		}

		return new WP_REST_Response( [
			'success' => false,
			'message' => __( 'Failed to update task status.', 'nozule' ),
			'errors'  => $result,
		], 422 );
	}

	/**
	 * Assign a task to a staff member.
	 */
	public function assign( WP_REST_Request $request ): WP_REST_Response {
		$id     = (int) $request->get_param( 'id' );
		$userId = absint( $request->get_param( 'assigned_to' ) ?? 0 );

		if ( ! $userId ) {
			return new WP_REST_Response( [
				'success' => false,
				'message' => __( 'assigned_to (user ID) is required.', 'nozule' ),
			], 422 );
		}

		$result = $this->housekeepingService->assignTask( $id, $userId );

		if ( $result instanceof HousekeepingTask ) {
			return new WP_REST_Response( [
				'success' => true,
				'message' => __( 'Task assigned successfully.', 'nozule' ),
				'data'    => $result->toArray(),
			], 200 );
		}

		return new WP_REST_Response( [
			'success' => false,
			'message' => __( 'Failed to assign task.', 'nozule' ),
			'errors'  => $result,
		], 422 );
	}

	/**
	 * Get housekeeping status counts.
	 */
	public function stats( WP_REST_Request $request ): WP_REST_Response {
		$counts = $this->housekeepingService->getStatusCounts();

		return new WP_REST_Response( [
			'success' => true,
			'data'    => $counts,
		], 200 );
	}

	/**
	 * Extract task data from the request.
	 */
	private function extractTaskData( WP_REST_Request $request ): array {
		$fields = [
			'room_id',
			'assigned_to',
			'status',
			'priority',
			'task_type',
			'notes',
		];
		$data = [];

		foreach ( $fields as $field ) {
			$value = $request->get_param( $field );
			if ( $value !== null ) {
				$data[ $field ] = $value;
			}
		}

		// Sanitize.
		if ( isset( $data['room_id'] ) ) {
			$data['room_id'] = absint( $data['room_id'] );
		}
		if ( isset( $data['assigned_to'] ) ) {
			$data['assigned_to'] = absint( $data['assigned_to'] );
		}
		if ( isset( $data['status'] ) ) {
			$data['status'] = sanitize_text_field( $data['status'] );
		}
		if ( isset( $data['priority'] ) ) {
			$data['priority'] = sanitize_text_field( $data['priority'] );
		}
		if ( isset( $data['task_type'] ) ) {
			$data['task_type'] = sanitize_text_field( $data['task_type'] );
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
	 * Permission callback: require nzl_staff or nzl_admin capability.
	 */
	public function checkStaffPermission( WP_REST_Request $request ): bool {
		return current_user_can( 'manage_options' ) || current_user_can( 'nzl_admin' ) || current_user_can( 'nzl_staff' );
	}
}
