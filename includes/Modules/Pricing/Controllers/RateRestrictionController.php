<?php

namespace Nozule\Modules\Pricing\Controllers;

use Nozule\Core\EventDispatcher;
use Nozule\Modules\Pricing\Models\RateRestriction;
use Nozule\Modules\Pricing\Repositories\RateRestrictionRepository;

/**
 * REST API controller for rate restriction CRUD operations (NZL-017).
 *
 * All endpoints require the 'manage_options' capability.
 * Registered under the nozule/v1/rate-restrictions namespace.
 */
class RateRestrictionController {

	private RateRestrictionRepository $repository;
	private EventDispatcher $events;

	/**
	 * Valid restriction type values.
	 *
	 * @var string[]
	 */
	private static array $validTypes = [
		'min_stay',
		'max_stay',
		'cta',
		'ctd',
		'stop_sell',
	];

	public function __construct(
		RateRestrictionRepository $repository,
		EventDispatcher           $events
	) {
		$this->repository = $repository;
		$this->events     = $events;
	}

	/**
	 * Register REST API routes.
	 */
	public function registerRoutes(): void {
		$namespace = 'nozule/v1';

		register_rest_route( $namespace, '/rate-restrictions', [
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

		register_rest_route( $namespace, '/rate-restrictions/(?P<id>\d+)', [
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
	 * List rate restrictions.
	 *
	 * GET /nozule/v1/rate-restrictions
	 *
	 * Supports optional filters: ?room_type_id= and ?restriction_type=
	 */
	public function index( \WP_REST_Request $request ): \WP_REST_Response {
		$roomTypeId      = $request->get_param( 'room_type_id' );
		$restrictionType = $request->get_param( 'restriction_type' );

		$restrictions = $this->repository->getAll();

		if ( $roomTypeId ) {
			$roomTypeId   = (int) $roomTypeId;
			$restrictions = array_filter(
				$restrictions,
				function ( RateRestriction $r ) use ( $roomTypeId ) {
					return $r->room_type_id === $roomTypeId;
				}
			);
		}

		if ( $restrictionType ) {
			$restrictionType = sanitize_text_field( $restrictionType );
			$restrictions    = array_filter(
				$restrictions,
				function ( RateRestriction $r ) use ( $restrictionType ) {
					return $r->restriction_type === $restrictionType;
				}
			);
		}

		return new \WP_REST_Response( [
			'success' => true,
			'data'    => array_values( array_map(
				function ( RateRestriction $r ) {
					return $r->toArray();
				},
				$restrictions
			) ),
		] );
	}

	/**
	 * Show a single rate restriction.
	 *
	 * GET /nozule/v1/rate-restrictions/{id}
	 */
	public function show( \WP_REST_Request $request ): \WP_REST_Response {
		$restriction = $this->repository->find( (int) $request->get_param( 'id' ) );

		if ( ! $restriction ) {
			return new \WP_REST_Response( [
				'success' => false,
				'error'   => [
					'code'    => 'NOT_FOUND',
					'message' => __( 'Rate restriction not found.', 'nozule' ),
				],
			], 404 );
		}

		return new \WP_REST_Response( [
			'success' => true,
			'data'    => $restriction->toArray(),
		] );
	}

	/**
	 * Create a new rate restriction.
	 *
	 * POST /nozule/v1/rate-restrictions
	 */
	public function store( \WP_REST_Request $request ): \WP_REST_Response {
		$data = $request->get_json_params();

		$errors = $this->validate( $data, false );

		if ( ! empty( $errors ) ) {
			return new \WP_REST_Response( [
				'success' => false,
				'error'   => [
					'code'    => 'VALIDATION_ERROR',
					'message' => implode( ' ', $errors ),
					'fields'  => $errors,
				],
			], 422 );
		}

		$sanitized   = $this->sanitizeData( $data );
		$restriction = $this->repository->create( $sanitized );

		if ( ! $restriction ) {
			return new \WP_REST_Response( [
				'success' => false,
				'error'   => [
					'code'    => 'CREATE_FAILED',
					'message' => __( 'Failed to create rate restriction.', 'nozule' ),
				],
			], 500 );
		}

		$this->events->dispatch( 'pricing/restriction_created', $restriction );

		return new \WP_REST_Response( [
			'success' => true,
			'data'    => $restriction->toArray(),
		], 201 );
	}

	/**
	 * Update an existing rate restriction.
	 *
	 * PUT/PATCH /nozule/v1/rate-restrictions/{id}
	 */
	public function update( \WP_REST_Request $request ): \WP_REST_Response {
		$id          = (int) $request->get_param( 'id' );
		$restriction = $this->repository->find( $id );

		if ( ! $restriction ) {
			return new \WP_REST_Response( [
				'success' => false,
				'error'   => [
					'code'    => 'NOT_FOUND',
					'message' => __( 'Rate restriction not found.', 'nozule' ),
				],
			], 404 );
		}

		$data   = $request->get_json_params();
		$errors = $this->validate( $data, true );

		if ( ! empty( $errors ) ) {
			return new \WP_REST_Response( [
				'success' => false,
				'error'   => [
					'code'    => 'VALIDATION_ERROR',
					'message' => implode( ' ', $errors ),
					'fields'  => $errors,
				],
			], 422 );
		}

		$sanitized  = $this->sanitizeData( $data );
		$updateData = array_filter( $sanitized, function ( $v ) {
			return $v !== null;
		} );

		$updated = $this->repository->update( $id, $updateData );

		if ( ! $updated ) {
			return new \WP_REST_Response( [
				'success' => false,
				'error'   => [
					'code'    => 'UPDATE_FAILED',
					'message' => __( 'Failed to update rate restriction.', 'nozule' ),
				],
			], 500 );
		}

		$restriction = $this->repository->find( $id );
		$this->events->dispatch( 'pricing/restriction_updated', $restriction );

		return new \WP_REST_Response( [
			'success' => true,
			'data'    => $restriction->toArray(),
		] );
	}

	/**
	 * Delete a rate restriction.
	 *
	 * DELETE /nozule/v1/rate-restrictions/{id}
	 */
	public function destroy( \WP_REST_Request $request ): \WP_REST_Response {
		$id          = (int) $request->get_param( 'id' );
		$restriction = $this->repository->find( $id );

		if ( ! $restriction ) {
			return new \WP_REST_Response( [
				'success' => false,
				'error'   => [
					'code'    => 'NOT_FOUND',
					'message' => __( 'Rate restriction not found.', 'nozule' ),
				],
			], 404 );
		}

		$deleted = $this->repository->delete( $id );

		if ( ! $deleted ) {
			return new \WP_REST_Response( [
				'success' => false,
				'error'   => [
					'code'    => 'DELETE_FAILED',
					'message' => __( 'Failed to delete rate restriction.', 'nozule' ),
				],
			], 500 );
		}

		$this->events->dispatch( 'pricing/restriction_deleted', $id, $restriction );

		return new \WP_REST_Response( [
			'success' => true,
			'message' => __( 'Rate restriction deleted successfully.', 'nozule' ),
		] );
	}

	/**
	 * Validate restriction data.
	 *
	 * @param array $data      Raw input data.
	 * @param bool  $isUpdate  Whether this is an update (fields are optional).
	 * @return array Associative array of field => error message. Empty if valid.
	 */
	private function validate( array $data, bool $isUpdate ): array {
		$errors = [];

		if ( ! $isUpdate ) {
			if ( empty( $data['room_type_id'] ) ) {
				$errors['room_type_id'] = __( 'Room type ID is required.', 'nozule' );
			}

			if ( empty( $data['restriction_type'] ) ) {
				$errors['restriction_type'] = __( 'Restriction type is required.', 'nozule' );
			}

			if ( empty( $data['date_from'] ) ) {
				$errors['date_from'] = __( 'Start date is required.', 'nozule' );
			}

			if ( empty( $data['date_to'] ) ) {
				$errors['date_to'] = __( 'End date is required.', 'nozule' );
			}
		}

		if ( ! empty( $data['restriction_type'] ) && ! in_array( $data['restriction_type'], self::$validTypes, true ) ) {
			$errors['restriction_type'] = sprintf(
				__( 'Invalid restriction type. Must be one of: %s', 'nozule' ),
				implode( ', ', self::$validTypes )
			);
		}

		if ( ! empty( $data['date_from'] ) && ! empty( $data['date_to'] ) ) {
			if ( $data['date_from'] > $data['date_to'] ) {
				$errors['date_from'] = __( 'Start date must be before or equal to end date.', 'nozule' );
			}
		}

		if ( ! empty( $data['days_of_week'] ) ) {
			$validDays = [ 'mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun' ];
			$days      = array_map( 'trim', explode( ',', strtolower( $data['days_of_week'] ) ) );

			foreach ( $days as $day ) {
				if ( ! in_array( $day, $validDays, true ) ) {
					$errors['days_of_week'] = sprintf(
						__( 'Invalid day of week: %s. Allowed values: %s', 'nozule' ),
						$day,
						implode( ', ', $validDays )
					);
					break;
				}
			}
		}

		return $errors;
	}

	/**
	 * Sanitize restriction data before storage.
	 *
	 * @param array $data Raw input data.
	 * @return array Sanitized data.
	 */
	private function sanitizeData( array $data ): array {
		$sanitized = [];

		// Integer field (required): room_type_id.
		if ( array_key_exists( 'room_type_id', $data ) ) {
			$sanitized['room_type_id'] = (int) $data['room_type_id'];
		}

		// Integer field (nullable): rate_plan_id.
		if ( array_key_exists( 'rate_plan_id', $data ) ) {
			$sanitized['rate_plan_id'] = $data['rate_plan_id'] !== null && $data['rate_plan_id'] !== ''
				? (int) $data['rate_plan_id']
				: null;
		}

		// Text field: restriction_type.
		if ( array_key_exists( 'restriction_type', $data ) ) {
			$sanitized['restriction_type'] = sanitize_text_field( $data['restriction_type'] );
		}

		// Integer field (nullable): value.
		if ( array_key_exists( 'value', $data ) ) {
			$sanitized['value'] = $data['value'] !== null && $data['value'] !== ''
				? (int) $data['value']
				: null;
		}

		// Text field (nullable): channel.
		if ( array_key_exists( 'channel', $data ) ) {
			$sanitized['channel'] = $data['channel'] !== null && $data['channel'] !== ''
				? sanitize_text_field( $data['channel'] )
				: null;
		}

		// Date fields.
		if ( array_key_exists( 'date_from', $data ) ) {
			$sanitized['date_from'] = sanitize_text_field( $data['date_from'] );
		}

		if ( array_key_exists( 'date_to', $data ) ) {
			$sanitized['date_to'] = sanitize_text_field( $data['date_to'] );
		}

		// Text field (nullable): days_of_week (comma-separated).
		if ( array_key_exists( 'days_of_week', $data ) ) {
			$sanitized['days_of_week'] = $data['days_of_week'] !== null && $data['days_of_week'] !== ''
				? sanitize_text_field( strtolower( $data['days_of_week'] ) )
				: null;
		}

		// Boolean / tinyint field: is_active.
		if ( array_key_exists( 'is_active', $data ) ) {
			$sanitized['is_active'] = (int) (bool) $data['is_active'];
		}

		return $sanitized;
	}
}
