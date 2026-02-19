<?php

namespace Nozule\Modules\Pricing\Controllers;

use Nozule\Core\EventDispatcher;
use Nozule\Modules\Pricing\Models\RatePlan;
use Nozule\Modules\Pricing\Repositories\RatePlanRepository;
use Nozule\Modules\Pricing\Validators\RatePlanValidator;

/**
 * REST API controller for rate plan admin CRUD operations.
 *
 * All endpoints require the 'manage_options' capability.
 * Registered under the nozule/v1/rate-plans namespace.
 */
class RatePlanController {

	private RatePlanRepository $repository;
	private RatePlanValidator $validator;
	private EventDispatcher $events;

	public function __construct(
		RatePlanRepository $repository,
		RatePlanValidator  $validator,
		EventDispatcher    $events
	) {
		$this->repository = $repository;
		$this->validator  = $validator;
		$this->events     = $events;
	}

	/**
	 * Register REST API routes.
	 */
	public function registerRoutes(): void {
		$namespace = 'nozule/v1';

		register_rest_route( $namespace, '/rate-plans', [
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ $this, 'index' ],
				'permission_callback' => [ $this, 'checkAdminPermission' ],
			],
			[
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'store' ],
				'permission_callback' => [ $this, 'checkAdminPermission' ],
			],
		] );

		register_rest_route( $namespace, '/rate-plans/(?P<id>\d+)', [
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ $this, 'show' ],
				'permission_callback' => [ $this, 'checkAdminPermission' ],
			],
			[
				'methods'             => 'PUT, PATCH',
				'callback'            => [ $this, 'update' ],
				'permission_callback' => [ $this, 'checkAdminPermission' ],
			],
			[
				'methods'             => \WP_REST_Server::DELETABLE,
				'callback'            => [ $this, 'destroy' ],
				'permission_callback' => [ $this, 'checkAdminPermission' ],
			],
		] );
	}

	/**
	 * Check that the current user has admin permissions.
	 */
	public function checkAdminPermission(): bool {
		return current_user_can( 'manage_options' );
	}

	/**
	 * List all rate plans.
	 *
	 * GET /nozule/v1/rate-plans
	 */
	public function index( \WP_REST_Request $request ): \WP_REST_Response {
		$status = $request->get_param( 'status' );

		if ( $status === 'active' ) {
			$plans = $this->repository->getActive();
		} else {
			$plans = $this->repository->getAllOrdered();
		}

		return new \WP_REST_Response( [
			'success' => true,
			'data'    => array_map(
				fn( RatePlan $plan ) => $plan->toPublicArray(),
				$plans
			),
		] );
	}

	/**
	 * Show a single rate plan.
	 *
	 * GET /nozule/v1/rate-plans/{id}
	 */
	public function show( \WP_REST_Request $request ): \WP_REST_Response {
		$plan = $this->repository->find( (int) $request->get_param( 'id' ) );

		if ( ! $plan ) {
			return new \WP_REST_Response( [
				'success' => false,
				'error'   => [
					'code'    => 'NOT_FOUND',
					'message' => __( 'Rate plan not found.', 'nozule' ),
				],
			], 404 );
		}

		return new \WP_REST_Response( [
			'success' => true,
			'data'    => $plan->toPublicArray(),
		] );
	}

	/**
	 * Create a new rate plan.
	 *
	 * POST /nozule/v1/rate-plans
	 */
	public function store( \WP_REST_Request $request ): \WP_REST_Response {
		$data = $request->get_json_params();

		if ( ! $this->validator->validateCreate( $data ) ) {
			return new \WP_REST_Response( [
				'success' => false,
				'error'   => [
					'code'    => 'VALIDATION_ERROR',
					'message' => implode( ' ', $this->validator->getAllErrors() ),
					'fields'  => $this->validator->getErrors(),
				],
			], 422 );
		}

		$sanitized = $this->sanitizeRatePlanData( $data );
		$plan      = $this->repository->create( $sanitized );

		if ( ! $plan ) {
			return new \WP_REST_Response( [
				'success' => false,
				'error'   => [
					'code'    => 'CREATE_FAILED',
					'message' => __( 'Failed to create rate plan.', 'nozule' ),
				],
			], 500 );
		}

		$this->events->dispatch( 'pricing/rate_plan_created', $plan );

		return new \WP_REST_Response( [
			'success' => true,
			'data'    => $plan->toPublicArray(),
		], 201 );
	}

	/**
	 * Update an existing rate plan.
	 *
	 * PUT/PATCH /nozule/v1/rate-plans/{id}
	 */
	public function update( \WP_REST_Request $request ): \WP_REST_Response {
		$id   = (int) $request->get_param( 'id' );
		$plan = $this->repository->find( $id );

		if ( ! $plan ) {
			return new \WP_REST_Response( [
				'success' => false,
				'error'   => [
					'code'    => 'NOT_FOUND',
					'message' => __( 'Rate plan not found.', 'nozule' ),
				],
			], 404 );
		}

		$data = $request->get_json_params();

		if ( ! $this->validator->validateUpdate( $id, $data ) ) {
			return new \WP_REST_Response( [
				'success' => false,
				'error'   => [
					'code'    => 'VALIDATION_ERROR',
					'message' => implode( ' ', $this->validator->getAllErrors() ),
					'fields'  => $this->validator->getErrors(),
				],
			], 422 );
		}

		$sanitized = $this->sanitizeRatePlanData( $data );

		// Only include fields that were provided.
		$updateData = array_filter( $sanitized, fn( $v ) => $v !== null );

		$updated = $this->repository->update( $id, $updateData );

		if ( ! $updated ) {
			return new \WP_REST_Response( [
				'success' => false,
				'error'   => [
					'code'    => 'UPDATE_FAILED',
					'message' => __( 'Failed to update rate plan.', 'nozule' ),
				],
			], 500 );
		}

		$plan = $this->repository->find( $id );
		$this->events->dispatch( 'pricing/rate_plan_updated', $plan );

		return new \WP_REST_Response( [
			'success' => true,
			'data'    => $plan->toPublicArray(),
		] );
	}

	/**
	 * Delete a rate plan.
	 *
	 * DELETE /nozule/v1/rate-plans/{id}
	 */
	public function destroy( \WP_REST_Request $request ): \WP_REST_Response {
		$id   = (int) $request->get_param( 'id' );
		$plan = $this->repository->find( $id );

		if ( ! $plan ) {
			return new \WP_REST_Response( [
				'success' => false,
				'error'   => [
					'code'    => 'NOT_FOUND',
					'message' => __( 'Rate plan not found.', 'nozule' ),
				],
			], 404 );
		}

		$deleted = $this->repository->delete( $id );

		if ( ! $deleted ) {
			return new \WP_REST_Response( [
				'success' => false,
				'error'   => [
					'code'    => 'DELETE_FAILED',
					'message' => __( 'Failed to delete rate plan.', 'nozule' ),
				],
			], 500 );
		}

		$this->events->dispatch( 'pricing/rate_plan_deleted', $id, $plan );

		return new \WP_REST_Response( [
			'success' => true,
			'message' => __( 'Rate plan deleted successfully.', 'nozule' ),
		] );
	}

	/**
	 * Sanitize rate plan data before storage.
	 *
	 * @param array $data Raw input data.
	 * @return array Sanitized data.
	 */
	private function sanitizeRatePlanData( array $data ): array {
		$sanitized = [];

		// Text fields.
		$textFields = [ 'name', 'code', 'modifier_type', 'status' ];
		foreach ( $textFields as $field ) {
			if ( array_key_exists( $field, $data ) ) {
				$sanitized[ $field ] = sanitize_text_field( $data[ $field ] );
			}
		}

		// Code should be lowercased.
		if ( isset( $sanitized['code'] ) ) {
			$sanitized['code'] = sanitize_title( $sanitized['code'] );
		}

		// Textarea fields.
		if ( array_key_exists( 'description', $data ) ) {
			$sanitized['description'] = sanitize_textarea_field( $data['description'] ?? '' );
		}

		if ( array_key_exists( 'cancellation_policy', $data ) ) {
			$sanitized['cancellation_hours'] = (int) ( $data['cancellation_policy'] ?? 24 );
		}

		// Numeric fields — frontend sends modifier_value, DB column is price_modifier.
		if ( array_key_exists( 'modifier_value', $data ) ) {
			$sanitized['price_modifier'] = (float) $data['modifier_value'];
		}

		// Integer fields.
		$intFields = [ 'room_type_id', 'min_stay', 'max_stay' ];
		foreach ( $intFields as $field ) {
			if ( array_key_exists( $field, $data ) ) {
				$sanitized[ $field ] = $data[ $field ] !== null ? (int) $data[ $field ] : null;
			}
		}

		// Boolean fields.
		$boolFields = [ 'is_refundable', 'is_default' ];
		foreach ( $boolFields as $field ) {
			if ( array_key_exists( $field, $data ) ) {
				$sanitized[ $field ] = (bool) $data[ $field ];
			}
		}

		// Date fields — frontend sends valid_until, DB column is valid_to.
		$dateFields = [ 'valid_from' ];
		foreach ( $dateFields as $field ) {
			if ( array_key_exists( $field, $data ) ) {
				$sanitized[ $field ] = $data[ $field ] ? sanitize_text_field( $data[ $field ] ) : null;
			}
		}

		if ( array_key_exists( 'valid_until', $data ) ) {
			$sanitized['valid_to'] = $data['valid_until'] ? sanitize_text_field( $data['valid_until'] ) : null;
		}

		return $sanitized;
	}
}
