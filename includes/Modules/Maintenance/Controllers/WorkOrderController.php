<?php

namespace Nozule\Modules\Maintenance\Controllers;

use Nozule\Core\ResponseHelper;
use Nozule\Modules\Maintenance\Models\WorkOrder;
use Nozule\Modules\Maintenance\Services\MaintenanceService;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * REST controller for maintenance work orders.
 *
 * Routes (all staff-level):
 *   GET    /nozule/v1/admin/maintenance               List work orders (filterable, paginated)
 *   POST   /nozule/v1/admin/maintenance               Create work order
 *   GET    /nozule/v1/admin/maintenance/stats          Status counts
 *   GET    /nozule/v1/admin/maintenance/{id}           Get single work order
 *   PUT    /nozule/v1/admin/maintenance/{id}           Update work order
 *   DELETE /nozule/v1/admin/maintenance/{id}           Delete work order
 *   PUT    /nozule/v1/admin/maintenance/{id}/status    Update status
 *   PUT    /nozule/v1/admin/maintenance/{id}/assign    Assign to staff
 */
class WorkOrderController {

	private MaintenanceService $service;

	public function __construct( MaintenanceService $service ) {
		$this->service = $service;
	}

	/**
	 * Register REST routes.
	 */
	public function registerRoutes(): void {
		$namespace = 'nozule/v1';

		// Stats endpoint (before parameterised route).
		register_rest_route( $namespace, '/admin/maintenance/stats', [
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => [ $this, 'stats' ],
			'permission_callback' => [ $this, 'checkStaffPermission' ],
		] );

		// List and create.
		register_rest_route( $namespace, '/admin/maintenance', [
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'index' ],
				'permission_callback' => [ $this, 'checkStaffPermission' ],
				'args'                => [
					'status' => [
						'required'          => false,
						'validate_callback' => fn( $v ) => in_array( $v, WorkOrder::validStatuses(), true ),
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
					'page' => [
						'required'          => false,
						'default'           => 1,
						'sanitize_callback' => 'absint',
					],
					'per_page' => [
						'required'          => false,
						'default'           => 50,
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

		// Single work order operations.
		register_rest_route( $namespace, '/admin/maintenance/(?P<id>\d+)', [
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
			[
				'methods'             => WP_REST_Server::DELETABLE,
				'callback'            => [ $this, 'destroy' ],
				'permission_callback' => [ $this, 'checkAdminPermission' ],
				'args'                => $this->getIdArgs(),
			],
		] );

		// Status update.
		register_rest_route( $namespace, '/admin/maintenance/(?P<id>\d+)/status', [
			'methods'             => WP_REST_Server::EDITABLE,
			'callback'            => [ $this, 'updateStatus' ],
			'permission_callback' => [ $this, 'checkStaffPermission' ],
			'args'                => $this->getIdArgs(),
		] );

		// Assign to staff.
		register_rest_route( $namespace, '/admin/maintenance/(?P<id>\d+)/assign', [
			'methods'             => WP_REST_Server::EDITABLE,
			'callback'            => [ $this, 'assign' ],
			'permission_callback' => [ $this, 'checkStaffPermission' ],
			'args'                => $this->getIdArgs(),
		] );
	}

	/**
	 * List work orders with optional filters.
	 */
	public function index( WP_REST_Request $request ): WP_REST_Response {
		$status     = $request->get_param( 'status' );
		$roomId     = $request->get_param( 'room_id' ) ? absint( $request->get_param( 'room_id' ) ) : null;
		$assignedTo = $request->get_param( 'assigned_to' ) ? absint( $request->get_param( 'assigned_to' ) ) : null;
		$page       = absint( $request->get_param( 'page' ) ) ?: 1;
		$perPage    = absint( $request->get_param( 'per_page' ) ) ?: 50;

		$result = $this->service->list( $status, $roomId, $assignedTo, $page, $perPage );

		$data = array_map(
			fn( WorkOrder $order ) => $order->toArray(),
			$result['items']
		);

		return ResponseHelper::paginated( $data, $result['total'], $page, $perPage );
	}

	/**
	 * Get a single work order.
	 */
	public function show( WP_REST_Request $request ): WP_REST_Response {
		$id    = (int) $request->get_param( 'id' );
		$order = $this->service->find( $id );

		if ( ! $order ) {
			return ResponseHelper::notFound( __( 'Work order not found.', 'nozule' ) );
		}

		return ResponseHelper::success( $order->toArray() );
	}

	/**
	 * Create a new work order.
	 */
	public function create( WP_REST_Request $request ): WP_REST_Response {
		$data = $this->extractData( $request );

		$result = $this->service->create( $data );

		if ( $result instanceof WorkOrder ) {
			return ResponseHelper::created( $result->toArray(), __( 'Work order created successfully.', 'nozule' ) );
		}

		return ResponseHelper::error( __( 'Validation failed.', 'nozule' ), 422, $result );
	}

	/**
	 * Update an existing work order.
	 */
	public function update( WP_REST_Request $request ): WP_REST_Response {
		$id   = (int) $request->get_param( 'id' );
		$data = $this->extractData( $request );

		$result = $this->service->update( $id, $data );

		if ( $result instanceof WorkOrder ) {
			return ResponseHelper::success( $result->toArray(), __( 'Work order updated successfully.', 'nozule' ) );
		}

		if ( isset( $result['id'] ) ) {
			return ResponseHelper::error( $result['id'][0], 404, $result );
		}

		return ResponseHelper::error( __( 'Validation failed.', 'nozule' ), 422, $result );
	}

	/**
	 * Delete a work order.
	 */
	public function destroy( WP_REST_Request $request ): WP_REST_Response {
		$id     = (int) $request->get_param( 'id' );
		$result = $this->service->delete( $id );

		if ( $result instanceof WorkOrder ) {
			return ResponseHelper::success( null, __( 'Work order deleted successfully.', 'nozule' ) );
		}

		if ( isset( $result['id'] ) ) {
			return ResponseHelper::error( $result['id'][0], 404, $result );
		}

		return ResponseHelper::error( __( 'Failed to delete work order.', 'nozule' ), 500, $result );
	}

	/**
	 * Update work order status.
	 */
	public function updateStatus( WP_REST_Request $request ): WP_REST_Response {
		$id              = (int) $request->get_param( 'id' );
		$status          = sanitize_text_field( $request->get_param( 'status' ) ?? '' );
		$resolutionNotes = sanitize_textarea_field( $request->get_param( 'resolution_notes' ) ?? '' );

		if ( ! $status ) {
			return ResponseHelper::error( __( 'Status is required.', 'nozule' ), 422 );
		}

		$result = $this->service->updateStatus( $id, $status, $resolutionNotes ?: null );

		if ( $result instanceof WorkOrder ) {
			return ResponseHelper::success( $result->toArray(), __( 'Work order status updated.', 'nozule' ) );
		}

		return ResponseHelper::error( __( 'Failed to update work order status.', 'nozule' ), 422, $result );
	}

	/**
	 * Assign a work order to a staff member.
	 */
	public function assign( WP_REST_Request $request ): WP_REST_Response {
		$id     = (int) $request->get_param( 'id' );
		$userId = absint( $request->get_param( 'assigned_to' ) ?? 0 );

		if ( ! $userId ) {
			return ResponseHelper::error( __( 'assigned_to (user ID) is required.', 'nozule' ), 422 );
		}

		$result = $this->service->assign( $id, $userId );

		if ( $result instanceof WorkOrder ) {
			return ResponseHelper::success( $result->toArray(), __( 'Work order assigned successfully.', 'nozule' ) );
		}

		return ResponseHelper::error( __( 'Failed to assign work order.', 'nozule' ), 422, $result );
	}

	/**
	 * Get work order status counts.
	 */
	public function stats( WP_REST_Request $request ): WP_REST_Response {
		$counts = $this->service->getStatusCounts();

		return ResponseHelper::success( $counts );
	}

	/**
	 * Extract work order data from the request.
	 */
	private function extractData( WP_REST_Request $request ): array {
		$fields = [
			'room_id',
			'title',
			'description',
			'category',
			'status',
			'priority',
			'assigned_to',
			'resolution_notes',
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
		if ( isset( $data['title'] ) ) {
			$data['title'] = sanitize_text_field( $data['title'] );
		}
		if ( isset( $data['description'] ) ) {
			$data['description'] = sanitize_textarea_field( $data['description'] );
		}
		if ( isset( $data['category'] ) ) {
			$data['category'] = sanitize_text_field( $data['category'] );
		}
		if ( isset( $data['status'] ) ) {
			$data['status'] = sanitize_text_field( $data['status'] );
		}
		if ( isset( $data['priority'] ) ) {
			$data['priority'] = sanitize_text_field( $data['priority'] );
		}
		if ( isset( $data['assigned_to'] ) ) {
			$data['assigned_to'] = absint( $data['assigned_to'] );
		}
		if ( isset( $data['resolution_notes'] ) ) {
			$data['resolution_notes'] = sanitize_textarea_field( $data['resolution_notes'] );
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

	/**
	 * Permission callback: require nzl_admin capability (for destructive actions).
	 */
	public function checkAdminPermission( WP_REST_Request $request ): bool {
		return current_user_can( 'manage_options' ) || current_user_can( 'nzl_admin' );
	}
}
