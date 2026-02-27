<?php

namespace Nozule\Modules\Pricing\Controllers;

use Nozule\Core\EventDispatcher;
use Nozule\Modules\Pricing\Models\DowRule;
use Nozule\Modules\Pricing\Models\EventOverride;
use Nozule\Modules\Pricing\Models\OccupancyRule;
use Nozule\Modules\Pricing\Repositories\DynamicPricingRepository;

/**
 * REST API controller for dynamic pricing admin CRUD operations.
 *
 * All endpoints require either the 'manage_options' or 'nzl_manage_rates' capability.
 * Registered under the nozule/v1/admin/dynamic-pricing namespace.
 */
class DynamicPricingController {

	private DynamicPricingRepository $repository;
	private EventDispatcher $events;

	public function __construct(
		DynamicPricingRepository $repository,
		EventDispatcher          $events
	) {
		$this->repository = $repository;
		$this->events     = $events;
	}

	/**
	 * Register REST API routes.
	 */
	public function registerRoutes(): void {
		$namespace = 'nozule/v1';

		// ── Occupancy Rules ───────────────────────────────────────
		register_rest_route( $namespace, '/admin/dynamic-pricing/occupancy-rules', [
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ $this, 'indexOccupancy' ],
				'permission_callback' => [ $this, 'checkAdminPermission' ],
			],
			[
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'storeOccupancy' ],
				'permission_callback' => [ $this, 'checkAdminPermission' ],
			],
		] );

		register_rest_route( $namespace, '/admin/dynamic-pricing/occupancy-rules/(?P<id>\d+)', [
			[
				'methods'             => 'PUT, PATCH',
				'callback'            => [ $this, 'updateOccupancy' ],
				'permission_callback' => [ $this, 'checkAdminPermission' ],
			],
			[
				'methods'             => \WP_REST_Server::DELETABLE,
				'callback'            => [ $this, 'destroyOccupancy' ],
				'permission_callback' => [ $this, 'checkAdminPermission' ],
			],
		] );

		// ── Day-of-Week Rules ─────────────────────────────────────
		register_rest_route( $namespace, '/admin/dynamic-pricing/dow-rules', [
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ $this, 'indexDow' ],
				'permission_callback' => [ $this, 'checkAdminPermission' ],
			],
			[
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'storeDow' ],
				'permission_callback' => [ $this, 'checkAdminPermission' ],
			],
		] );

		register_rest_route( $namespace, '/admin/dynamic-pricing/dow-rules/(?P<id>\d+)', [
			[
				'methods'             => 'PUT, PATCH',
				'callback'            => [ $this, 'updateDow' ],
				'permission_callback' => [ $this, 'checkAdminPermission' ],
			],
			[
				'methods'             => \WP_REST_Server::DELETABLE,
				'callback'            => [ $this, 'destroyDow' ],
				'permission_callback' => [ $this, 'checkAdminPermission' ],
			],
		] );

		// ── Event Overrides ───────────────────────────────────────
		register_rest_route( $namespace, '/admin/dynamic-pricing/event-overrides', [
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ $this, 'indexEvents' ],
				'permission_callback' => [ $this, 'checkAdminPermission' ],
			],
			[
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'storeEvent' ],
				'permission_callback' => [ $this, 'checkAdminPermission' ],
			],
		] );

		register_rest_route( $namespace, '/admin/dynamic-pricing/event-overrides/(?P<id>\d+)', [
			[
				'methods'             => 'PUT, PATCH',
				'callback'            => [ $this, 'updateEvent' ],
				'permission_callback' => [ $this, 'checkAdminPermission' ],
			],
			[
				'methods'             => \WP_REST_Server::DELETABLE,
				'callback'            => [ $this, 'destroyEvent' ],
				'permission_callback' => [ $this, 'checkAdminPermission' ],
			],
		] );
	}

	/**
	 * Check that the current user has admin permissions.
	 */
	public function checkAdminPermission(): bool {
		return current_user_can( 'manage_options' ) || current_user_can( 'nzl_manage_rates' );
	}

	// =================================================================
	// Occupancy Rules
	// =================================================================

	/**
	 * List occupancy rules.
	 *
	 * GET /nozule/v1/admin/dynamic-pricing/occupancy-rules
	 */
	public function indexOccupancy( \WP_REST_Request $request ): \WP_REST_Response {
		$rules = $this->repository->getAllOccupancyRules();

		return new \WP_REST_Response( [
			'success' => true,
			'data'    => array_map(
				function ( OccupancyRule $rule ) {
					return $rule->toPublicArray();
				},
				$rules
			),
		] );
	}

	/**
	 * Create an occupancy rule.
	 *
	 * POST /nozule/v1/admin/dynamic-pricing/occupancy-rules
	 */
	public function storeOccupancy( \WP_REST_Request $request ): \WP_REST_Response {
		$data      = $request->get_json_params();
		$sanitized = $this->sanitizeOccupancyData( $data );
		$rule      = $this->repository->createOccupancyRule( $sanitized );

		if ( ! $rule ) {
			return new \WP_REST_Response( [
				'success' => false,
				'error'   => [
					'code'    => 'CREATE_FAILED',
					'message' => __( 'Failed to create occupancy rule.', 'nozule' ),
				],
			], 500 );
		}

		$this->events->dispatch( 'pricing/dynamic_rule_created', $rule );

		return new \WP_REST_Response( [
			'success' => true,
			'data'    => $rule->toPublicArray(),
		], 201 );
	}

	/**
	 * Update an occupancy rule.
	 *
	 * PUT/PATCH /nozule/v1/admin/dynamic-pricing/occupancy-rules/{id}
	 */
	public function updateOccupancy( \WP_REST_Request $request ): \WP_REST_Response {
		$id   = (int) $request->get_param( 'id' );
		$rule = $this->repository->findOccupancyRule( $id );

		if ( ! $rule ) {
			return new \WP_REST_Response( [
				'success' => false,
				'error'   => [
					'code'    => 'NOT_FOUND',
					'message' => __( 'Occupancy rule not found.', 'nozule' ),
				],
			], 404 );
		}

		$data       = $request->get_json_params();
		$sanitized  = $this->sanitizeOccupancyData( $data );
		$updateData = array_filter( $sanitized, function ( $v ) {
			return $v !== null;
		} );

		$updated = $this->repository->updateOccupancyRule( $id, $updateData );

		if ( ! $updated ) {
			return new \WP_REST_Response( [
				'success' => false,
				'error'   => [
					'code'    => 'UPDATE_FAILED',
					'message' => __( 'Failed to update occupancy rule.', 'nozule' ),
				],
			], 500 );
		}

		$rule = $this->repository->findOccupancyRule( $id );
		$this->events->dispatch( 'pricing/dynamic_rule_updated', $rule );

		return new \WP_REST_Response( [
			'success' => true,
			'data'    => $rule->toPublicArray(),
		] );
	}

	/**
	 * Delete an occupancy rule.
	 *
	 * DELETE /nozule/v1/admin/dynamic-pricing/occupancy-rules/{id}
	 */
	public function destroyOccupancy( \WP_REST_Request $request ): \WP_REST_Response {
		$id   = (int) $request->get_param( 'id' );
		$rule = $this->repository->findOccupancyRule( $id );

		if ( ! $rule ) {
			return new \WP_REST_Response( [
				'success' => false,
				'error'   => [
					'code'    => 'NOT_FOUND',
					'message' => __( 'Occupancy rule not found.', 'nozule' ),
				],
			], 404 );
		}

		$deleted = $this->repository->deleteOccupancyRule( $id );

		if ( ! $deleted ) {
			return new \WP_REST_Response( [
				'success' => false,
				'error'   => [
					'code'    => 'DELETE_FAILED',
					'message' => __( 'Failed to delete occupancy rule.', 'nozule' ),
				],
			], 500 );
		}

		$this->events->dispatch( 'pricing/dynamic_rule_deleted', $id, $rule );

		return new \WP_REST_Response( [
			'success' => true,
			'message' => __( 'Occupancy rule deleted successfully.', 'nozule' ),
		] );
	}

	// =================================================================
	// Day-of-Week Rules
	// =================================================================

	/**
	 * List DOW rules.
	 *
	 * GET /nozule/v1/admin/dynamic-pricing/dow-rules
	 */
	public function indexDow( \WP_REST_Request $request ): \WP_REST_Response {
		$rules = $this->repository->getAllDowRules();

		return new \WP_REST_Response( [
			'success' => true,
			'data'    => array_map(
				function ( DowRule $rule ) {
					return $rule->toPublicArray();
				},
				$rules
			),
		] );
	}

	/**
	 * Create a DOW rule.
	 *
	 * POST /nozule/v1/admin/dynamic-pricing/dow-rules
	 */
	public function storeDow( \WP_REST_Request $request ): \WP_REST_Response {
		$data      = $request->get_json_params();
		$sanitized = $this->sanitizeDowData( $data );
		$rule      = $this->repository->createDowRule( $sanitized );

		if ( ! $rule ) {
			return new \WP_REST_Response( [
				'success' => false,
				'error'   => [
					'code'    => 'CREATE_FAILED',
					'message' => __( 'Failed to create day-of-week rule.', 'nozule' ),
				],
			], 500 );
		}

		$this->events->dispatch( 'pricing/dynamic_rule_created', $rule );

		return new \WP_REST_Response( [
			'success' => true,
			'data'    => $rule->toPublicArray(),
		], 201 );
	}

	/**
	 * Update a DOW rule.
	 *
	 * PUT/PATCH /nozule/v1/admin/dynamic-pricing/dow-rules/{id}
	 */
	public function updateDow( \WP_REST_Request $request ): \WP_REST_Response {
		$id   = (int) $request->get_param( 'id' );
		$rule = $this->repository->findDowRule( $id );

		if ( ! $rule ) {
			return new \WP_REST_Response( [
				'success' => false,
				'error'   => [
					'code'    => 'NOT_FOUND',
					'message' => __( 'Day-of-week rule not found.', 'nozule' ),
				],
			], 404 );
		}

		$data       = $request->get_json_params();
		$sanitized  = $this->sanitizeDowData( $data );
		$updateData = array_filter( $sanitized, function ( $v ) {
			return $v !== null;
		} );

		$updated = $this->repository->updateDowRule( $id, $updateData );

		if ( ! $updated ) {
			return new \WP_REST_Response( [
				'success' => false,
				'error'   => [
					'code'    => 'UPDATE_FAILED',
					'message' => __( 'Failed to update day-of-week rule.', 'nozule' ),
				],
			], 500 );
		}

		$rule = $this->repository->findDowRule( $id );
		$this->events->dispatch( 'pricing/dynamic_rule_updated', $rule );

		return new \WP_REST_Response( [
			'success' => true,
			'data'    => $rule->toPublicArray(),
		] );
	}

	/**
	 * Delete a DOW rule.
	 *
	 * DELETE /nozule/v1/admin/dynamic-pricing/dow-rules/{id}
	 */
	public function destroyDow( \WP_REST_Request $request ): \WP_REST_Response {
		$id   = (int) $request->get_param( 'id' );
		$rule = $this->repository->findDowRule( $id );

		if ( ! $rule ) {
			return new \WP_REST_Response( [
				'success' => false,
				'error'   => [
					'code'    => 'NOT_FOUND',
					'message' => __( 'Day-of-week rule not found.', 'nozule' ),
				],
			], 404 );
		}

		$deleted = $this->repository->deleteDowRule( $id );

		if ( ! $deleted ) {
			return new \WP_REST_Response( [
				'success' => false,
				'error'   => [
					'code'    => 'DELETE_FAILED',
					'message' => __( 'Failed to delete day-of-week rule.', 'nozule' ),
				],
			], 500 );
		}

		$this->events->dispatch( 'pricing/dynamic_rule_deleted', $id, $rule );

		return new \WP_REST_Response( [
			'success' => true,
			'message' => __( 'Day-of-week rule deleted successfully.', 'nozule' ),
		] );
	}

	// =================================================================
	// Event Overrides
	// =================================================================

	/**
	 * List event overrides.
	 *
	 * GET /nozule/v1/admin/dynamic-pricing/event-overrides
	 */
	public function indexEvents( \WP_REST_Request $request ): \WP_REST_Response {
		$events = $this->repository->getAllEventOverrides();

		return new \WP_REST_Response( [
			'success' => true,
			'data'    => array_map(
				function ( EventOverride $event ) {
					return $event->toPublicArray();
				},
				$events
			),
		] );
	}

	/**
	 * Create an event override.
	 *
	 * POST /nozule/v1/admin/dynamic-pricing/event-overrides
	 */
	public function storeEvent( \WP_REST_Request $request ): \WP_REST_Response {
		$data      = $request->get_json_params();
		$sanitized = $this->sanitizeEventData( $data );
		$event     = $this->repository->createEventOverride( $sanitized );

		if ( ! $event ) {
			return new \WP_REST_Response( [
				'success' => false,
				'error'   => [
					'code'    => 'CREATE_FAILED',
					'message' => __( 'Failed to create event override.', 'nozule' ),
				],
			], 500 );
		}

		$this->events->dispatch( 'pricing/dynamic_rule_created', $event );

		return new \WP_REST_Response( [
			'success' => true,
			'data'    => $event->toPublicArray(),
		], 201 );
	}

	/**
	 * Update an event override.
	 *
	 * PUT/PATCH /nozule/v1/admin/dynamic-pricing/event-overrides/{id}
	 */
	public function updateEvent( \WP_REST_Request $request ): \WP_REST_Response {
		$id    = (int) $request->get_param( 'id' );
		$event = $this->repository->findEventOverride( $id );

		if ( ! $event ) {
			return new \WP_REST_Response( [
				'success' => false,
				'error'   => [
					'code'    => 'NOT_FOUND',
					'message' => __( 'Event override not found.', 'nozule' ),
				],
			], 404 );
		}

		$data       = $request->get_json_params();
		$sanitized  = $this->sanitizeEventData( $data );
		$updateData = array_filter( $sanitized, function ( $v ) {
			return $v !== null;
		} );

		$updated = $this->repository->updateEventOverride( $id, $updateData );

		if ( ! $updated ) {
			return new \WP_REST_Response( [
				'success' => false,
				'error'   => [
					'code'    => 'UPDATE_FAILED',
					'message' => __( 'Failed to update event override.', 'nozule' ),
				],
			], 500 );
		}

		$event = $this->repository->findEventOverride( $id );
		$this->events->dispatch( 'pricing/dynamic_rule_updated', $event );

		return new \WP_REST_Response( [
			'success' => true,
			'data'    => $event->toPublicArray(),
		] );
	}

	/**
	 * Delete an event override.
	 *
	 * DELETE /nozule/v1/admin/dynamic-pricing/event-overrides/{id}
	 */
	public function destroyEvent( \WP_REST_Request $request ): \WP_REST_Response {
		$id    = (int) $request->get_param( 'id' );
		$event = $this->repository->findEventOverride( $id );

		if ( ! $event ) {
			return new \WP_REST_Response( [
				'success' => false,
				'error'   => [
					'code'    => 'NOT_FOUND',
					'message' => __( 'Event override not found.', 'nozule' ),
				],
			], 404 );
		}

		$deleted = $this->repository->deleteEventOverride( $id );

		if ( ! $deleted ) {
			return new \WP_REST_Response( [
				'success' => false,
				'error'   => [
					'code'    => 'DELETE_FAILED',
					'message' => __( 'Failed to delete event override.', 'nozule' ),
				],
			], 500 );
		}

		$this->events->dispatch( 'pricing/dynamic_rule_deleted', $id, $event );

		return new \WP_REST_Response( [
			'success' => true,
			'message' => __( 'Event override deleted successfully.', 'nozule' ),
		] );
	}

	// =================================================================
	// Sanitization helpers
	// =================================================================

	/**
	 * Sanitize occupancy rule data before storage.
	 *
	 * @param array $data Raw input data.
	 * @return array Sanitized data.
	 */
	private function sanitizeOccupancyData( array $data ): array {
		$sanitized = [];

		if ( array_key_exists( 'room_type_id', $data ) ) {
			$sanitized['room_type_id'] = $data['room_type_id'] !== null && $data['room_type_id'] !== ''
				? (int) $data['room_type_id']
				: null;
		}

		if ( array_key_exists( 'threshold_percent', $data ) ) {
			$sanitized['threshold_percent'] = max( 0, min( 100, (int) $data['threshold_percent'] ) );
		}

		if ( array_key_exists( 'modifier_type', $data ) ) {
			$sanitized['modifier_type'] = sanitize_text_field( $data['modifier_type'] );
		}

		if ( array_key_exists( 'modifier_value', $data ) ) {
			$sanitized['modifier_value'] = (float) $data['modifier_value'];
		}

		if ( array_key_exists( 'priority', $data ) ) {
			$sanitized['priority'] = (int) $data['priority'];
		}

		if ( array_key_exists( 'status', $data ) ) {
			$sanitized['status'] = sanitize_text_field( $data['status'] );
		}

		return $sanitized;
	}

	/**
	 * Sanitize DOW rule data before storage.
	 *
	 * @param array $data Raw input data.
	 * @return array Sanitized data.
	 */
	private function sanitizeDowData( array $data ): array {
		$sanitized = [];

		if ( array_key_exists( 'room_type_id', $data ) ) {
			$sanitized['room_type_id'] = $data['room_type_id'] !== null && $data['room_type_id'] !== ''
				? (int) $data['room_type_id']
				: null;
		}

		if ( array_key_exists( 'day_of_week', $data ) ) {
			$sanitized['day_of_week'] = max( 0, min( 6, (int) $data['day_of_week'] ) );
		}

		if ( array_key_exists( 'modifier_type', $data ) ) {
			$sanitized['modifier_type'] = sanitize_text_field( $data['modifier_type'] );
		}

		if ( array_key_exists( 'modifier_value', $data ) ) {
			$sanitized['modifier_value'] = (float) $data['modifier_value'];
		}

		if ( array_key_exists( 'status', $data ) ) {
			$sanitized['status'] = sanitize_text_field( $data['status'] );
		}

		return $sanitized;
	}

	/**
	 * Sanitize event override data before storage.
	 *
	 * @param array $data Raw input data.
	 * @return array Sanitized data.
	 */
	private function sanitizeEventData( array $data ): array {
		$sanitized = [];

		$textFields = [ 'name', 'name_ar', 'modifier_type', 'status' ];
		foreach ( $textFields as $field ) {
			if ( array_key_exists( $field, $data ) ) {
				$sanitized[ $field ] = sanitize_text_field( $data[ $field ] );
			}
		}

		$dateFields = [ 'start_date', 'end_date' ];
		foreach ( $dateFields as $field ) {
			if ( array_key_exists( $field, $data ) ) {
				$sanitized[ $field ] = sanitize_text_field( $data[ $field ] );
			}
		}

		if ( array_key_exists( 'room_type_id', $data ) ) {
			$sanitized['room_type_id'] = $data['room_type_id'] !== null && $data['room_type_id'] !== ''
				? (int) $data['room_type_id']
				: null;
		}

		if ( array_key_exists( 'modifier_value', $data ) ) {
			$sanitized['modifier_value'] = (float) $data['modifier_value'];
		}

		if ( array_key_exists( 'priority', $data ) ) {
			$sanitized['priority'] = (int) $data['priority'];
		}

		return $sanitized;
	}
}
