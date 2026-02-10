<?php

namespace Venezia\Modules\Pricing\Controllers;

use Venezia\Core\EventDispatcher;
use Venezia\Modules\Pricing\Models\SeasonalRate;
use Venezia\Modules\Pricing\Repositories\SeasonalRateRepository;
use Venezia\Modules\Pricing\Validators\SeasonalRateValidator;

/**
 * REST API controller for seasonal rate admin CRUD operations.
 *
 * All endpoints require the 'manage_options' capability.
 * Registered under the venezia/v1/seasonal-rates namespace.
 */
class SeasonalRateController {

	private SeasonalRateRepository $repository;
	private SeasonalRateValidator $validator;
	private EventDispatcher $events;

	public function __construct(
		SeasonalRateRepository $repository,
		SeasonalRateValidator  $validator,
		EventDispatcher        $events
	) {
		$this->repository = $repository;
		$this->validator  = $validator;
		$this->events     = $events;
	}

	/**
	 * Register REST API routes.
	 */
	public function registerRoutes(): void {
		$namespace = 'venezia/v1';

		register_rest_route( $namespace, '/seasonal-rates', [
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

		register_rest_route( $namespace, '/seasonal-rates/(?P<id>\d+)', [
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
	 * List seasonal rates.
	 *
	 * GET /venezia/v1/seasonal-rates
	 *
	 * Supports optional ?room_type_id= filter.
	 */
	public function index( \WP_REST_Request $request ): \WP_REST_Response {
		$roomTypeId = $request->get_param( 'room_type_id' );

		if ( $roomTypeId ) {
			$rates = $this->repository->getForRoomType( (int) $roomTypeId );
		} else {
			$rates = $this->repository->getAllOrdered();
		}

		return new \WP_REST_Response( [
			'success' => true,
			'data'    => array_map(
				fn( SeasonalRate $rate ) => $rate->toPublicArray(),
				$rates
			),
		] );
	}

	/**
	 * Show a single seasonal rate.
	 *
	 * GET /venezia/v1/seasonal-rates/{id}
	 */
	public function show( \WP_REST_Request $request ): \WP_REST_Response {
		$rate = $this->repository->find( (int) $request->get_param( 'id' ) );

		if ( ! $rate ) {
			return new \WP_REST_Response( [
				'success' => false,
				'error'   => [
					'code'    => 'NOT_FOUND',
					'message' => __( 'Seasonal rate not found.', 'venezia-hotel' ),
				],
			], 404 );
		}

		return new \WP_REST_Response( [
			'success' => true,
			'data'    => $rate->toPublicArray(),
		] );
	}

	/**
	 * Create a new seasonal rate.
	 *
	 * POST /venezia/v1/seasonal-rates
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

		$sanitized = $this->sanitizeSeasonalRateData( $data );
		$rate      = $this->repository->create( $sanitized );

		if ( ! $rate ) {
			return new \WP_REST_Response( [
				'success' => false,
				'error'   => [
					'code'    => 'CREATE_FAILED',
					'message' => __( 'Failed to create seasonal rate.', 'venezia-hotel' ),
				],
			], 500 );
		}

		$this->events->dispatch( 'pricing/seasonal_rate_created', $rate );

		return new \WP_REST_Response( [
			'success' => true,
			'data'    => $rate->toPublicArray(),
		], 201 );
	}

	/**
	 * Update an existing seasonal rate.
	 *
	 * PUT/PATCH /venezia/v1/seasonal-rates/{id}
	 */
	public function update( \WP_REST_Request $request ): \WP_REST_Response {
		$id   = (int) $request->get_param( 'id' );
		$rate = $this->repository->find( $id );

		if ( ! $rate ) {
			return new \WP_REST_Response( [
				'success' => false,
				'error'   => [
					'code'    => 'NOT_FOUND',
					'message' => __( 'Seasonal rate not found.', 'venezia-hotel' ),
				],
			], 404 );
		}

		$data = $request->get_json_params();

		if ( ! $this->validator->validateUpdate( $data ) ) {
			return new \WP_REST_Response( [
				'success' => false,
				'error'   => [
					'code'    => 'VALIDATION_ERROR',
					'message' => implode( ' ', $this->validator->getAllErrors() ),
					'fields'  => $this->validator->getErrors(),
				],
			], 422 );
		}

		$sanitized = $this->sanitizeSeasonalRateData( $data );

		// Only include fields that were provided.
		$updateData = array_filter( $sanitized, fn( $v ) => $v !== null );

		$updated = $this->repository->update( $id, $updateData );

		if ( ! $updated ) {
			return new \WP_REST_Response( [
				'success' => false,
				'error'   => [
					'code'    => 'UPDATE_FAILED',
					'message' => __( 'Failed to update seasonal rate.', 'venezia-hotel' ),
				],
			], 500 );
		}

		$rate = $this->repository->find( $id );
		$this->events->dispatch( 'pricing/seasonal_rate_updated', $rate );

		return new \WP_REST_Response( [
			'success' => true,
			'data'    => $rate->toPublicArray(),
		] );
	}

	/**
	 * Delete a seasonal rate.
	 *
	 * DELETE /venezia/v1/seasonal-rates/{id}
	 */
	public function destroy( \WP_REST_Request $request ): \WP_REST_Response {
		$id   = (int) $request->get_param( 'id' );
		$rate = $this->repository->find( $id );

		if ( ! $rate ) {
			return new \WP_REST_Response( [
				'success' => false,
				'error'   => [
					'code'    => 'NOT_FOUND',
					'message' => __( 'Seasonal rate not found.', 'venezia-hotel' ),
				],
			], 404 );
		}

		$deleted = $this->repository->delete( $id );

		if ( ! $deleted ) {
			return new \WP_REST_Response( [
				'success' => false,
				'error'   => [
					'code'    => 'DELETE_FAILED',
					'message' => __( 'Failed to delete seasonal rate.', 'venezia-hotel' ),
				],
			], 500 );
		}

		$this->events->dispatch( 'pricing/seasonal_rate_deleted', $id, $rate );

		return new \WP_REST_Response( [
			'success' => true,
			'message' => __( 'Seasonal rate deleted successfully.', 'venezia-hotel' ),
		] );
	}

	/**
	 * Sanitize seasonal rate data before storage.
	 *
	 * @param array $data Raw input data.
	 * @return array Sanitized data.
	 */
	private function sanitizeSeasonalRateData( array $data ): array {
		$sanitized = [];

		// Text fields.
		$textFields = [ 'name', 'modifier_type', 'status' ];
		foreach ( $textFields as $field ) {
			if ( array_key_exists( $field, $data ) ) {
				$sanitized[ $field ] = sanitize_text_field( $data[ $field ] );
			}
		}

		// Date fields.
		$dateFields = [ 'start_date', 'end_date' ];
		foreach ( $dateFields as $field ) {
			if ( array_key_exists( $field, $data ) ) {
				$sanitized[ $field ] = sanitize_text_field( $data[ $field ] );
			}
		}

		// Numeric fields — frontend sends modifier_value, DB column is price_modifier.
		if ( array_key_exists( 'modifier_value', $data ) ) {
			$sanitized['price_modifier'] = (float) $data['modifier_value'];
		}

		// Integer fields (nullable).
		$intNullableFields = [ 'room_type_id', 'rate_plan_id' ];
		foreach ( $intNullableFields as $field ) {
			if ( array_key_exists( $field, $data ) ) {
				$sanitized[ $field ] = $data[ $field ] !== null ? (int) $data[ $field ] : null;
			}
		}

		// Integer fields (non-nullable).
		$intFields = [ 'priority' ];
		foreach ( $intFields as $field ) {
			if ( array_key_exists( $field, $data ) ) {
				$sanitized[ $field ] = (int) $data[ $field ];
			}
		}

		// Days of week (array of integers).
		if ( array_key_exists( 'days_of_week', $data ) ) {
			$daysOfWeek = $data['days_of_week'];

			if ( is_array( $daysOfWeek ) ) {
				$sanitized['days_of_week'] = array_map( 'intval', $daysOfWeek );
			} elseif ( $daysOfWeek === null ) {
				$sanitized['days_of_week'] = [];
			}
		}

		return $sanitized;
	}
}
