<?php

namespace Nozule\Modules\Promotions\Controllers;

use Nozule\Modules\Promotions\Models\PromoCode;
use Nozule\Modules\Promotions\Services\PromoCodeService;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * REST controller for promo code administration and public validation.
 *
 * Routes:
 *   GET    /nozule/v1/admin/promo-codes              List promo codes (staff)
 *   POST   /nozule/v1/admin/promo-codes              Create promo code (admin)
 *   GET    /nozule/v1/admin/promo-codes/{id}         Get single promo code (staff)
 *   PUT    /nozule/v1/admin/promo-codes/{id}         Update promo code (admin)
 *   DELETE /nozule/v1/admin/promo-codes/{id}         Delete promo code (admin)
 *   POST   /nozule/v1/promo-codes/validate           Validate a code (public)
 */
class PromoCodeController {

	private const NAMESPACE = 'nozule/v1';

	private PromoCodeService $service;

	public function __construct( PromoCodeService $service ) {
		$this->service = $service;
	}

	/**
	 * Register REST API routes.
	 */
	public function registerRoutes(): void {
		// Admin: list and create promo codes.
		register_rest_route( self::NAMESPACE, '/admin/promo-codes', [
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'index' ],
				'permission_callback' => [ $this, 'checkStaffPermission' ],
				'args'                => $this->getListArgs(),
			],
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'store' ],
				'permission_callback' => [ $this, 'checkAdminPermission' ],
			],
		] );

		// Admin: single promo code operations.
		register_rest_route( self::NAMESPACE, '/admin/promo-codes/(?P<id>\d+)', [
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
				'callback'            => [ $this, 'destroy' ],
				'permission_callback' => [ $this, 'checkAdminPermission' ],
				'args'                => $this->getIdArgs(),
			],
		] );

		// Public: validate a promo code.
		register_rest_route( self::NAMESPACE, '/promo-codes/validate', [
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'validateCode' ],
				'permission_callback' => '__return_true',
				'args'                => $this->getValidateArgs(),
			],
		] );
	}

	/**
	 * List promo codes with filters and pagination.
	 */
	public function index( WP_REST_Request $request ): WP_REST_Response {
		$result = $this->service->getPromoCodes( [
			'status'   => $request->get_param( 'status' ) ?? '',
			'search'   => $request->get_param( 'search' ) ?? '',
			'orderby'  => $request->get_param( 'orderby' ) ?? 'created_at',
			'order'    => $request->get_param( 'order' ) ?? 'DESC',
			'per_page' => $request->get_param( 'per_page' ) ?? 20,
			'page'     => $request->get_param( 'page' ) ?? 1,
		] );

		$items = array_map(
			fn( PromoCode $promo ) => $promo->toArray(),
			$result['items']
		);

		return new WP_REST_Response( [
			'success' => true,
			'data'    => [
				'items'      => $items,
				'pagination' => [
					'page'        => (int) ( $request->get_param( 'page' ) ?? 1 ),
					'per_page'    => (int) ( $request->get_param( 'per_page' ) ?? 20 ),
					'total'       => $result['total'],
					'total_pages' => $result['pages'],
				],
			],
		], 200 );
	}

	/**
	 * Get a single promo code by ID.
	 */
	public function show( WP_REST_Request $request ): WP_REST_Response {
		$id    = (int) $request->get_param( 'id' );
		$promo = $this->service->getPromoCode( $id );

		if ( ! $promo ) {
			return new WP_REST_Response( [
				'success' => false,
				'message' => __( 'Promo code not found.', 'nozule' ),
			], 404 );
		}

		return new WP_REST_Response( [
			'success' => true,
			'data'    => $promo->toArray(),
		], 200 );
	}

	/**
	 * Create a new promo code.
	 */
	public function store( WP_REST_Request $request ): WP_REST_Response {
		$data = $this->extractPromoData( $request );

		// Set the creator to the current user.
		$data['created_by'] = get_current_user_id();

		$result = $this->service->createPromoCode( $data );

		if ( $result instanceof PromoCode ) {
			return new WP_REST_Response( [
				'success' => true,
				'message' => __( 'Promo code created successfully.', 'nozule' ),
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
	 * Update an existing promo code.
	 */
	public function update( WP_REST_Request $request ): WP_REST_Response {
		$id   = (int) $request->get_param( 'id' );
		$data = $this->extractPromoData( $request );

		$result = $this->service->updatePromoCode( $id, $data );

		if ( $result instanceof PromoCode ) {
			return new WP_REST_Response( [
				'success' => true,
				'message' => __( 'Promo code updated successfully.', 'nozule' ),
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
	 * Delete a promo code.
	 */
	public function destroy( WP_REST_Request $request ): WP_REST_Response {
		$id     = (int) $request->get_param( 'id' );
		$result = $this->service->deletePromoCode( $id );

		if ( $result === true ) {
			return new WP_REST_Response( [
				'success' => true,
				'message' => __( 'Promo code deleted successfully.', 'nozule' ),
			], 200 );
		}

		$statusCode = isset( $result['id'] ) ? 404 : 422;

		return new WP_REST_Response( [
			'success' => false,
			'message' => __( 'Failed to delete promo code.', 'nozule' ),
			'errors'  => $result,
		], $statusCode );
	}

	/**
	 * Validate a promo code for a booking (public endpoint).
	 */
	public function validateCode( WP_REST_Request $request ): WP_REST_Response {
		$code     = sanitize_text_field( $request->get_param( 'code' ) ?? '' );
		$subtotal = (float) ( $request->get_param( 'subtotal' ) ?? 0 );
		$nights   = (int) ( $request->get_param( 'nights' ) ?? 1 );
		$guestId  = $request->get_param( 'guest_id' ) ? (int) $request->get_param( 'guest_id' ) : null;

		if ( empty( $code ) ) {
			return new WP_REST_Response( [
				'success' => false,
				'message' => __( 'Promo code is required.', 'nozule' ),
			], 400 );
		}

		$result = $this->service->validateCode( $code, $subtotal, $nights, $guestId );

		if ( $result instanceof PromoCode ) {
			$discount = $this->service->applyDiscount( $result, $subtotal );

			return new WP_REST_Response( [
				'success' => true,
				'data'    => [
					'code'           => $result->code,
					'name'           => $result->name,
					'name_ar'        => $result->name_ar,
					'discount_type'  => $result->discount_type,
					'discount_value' => $result->discount_value,
					'discount_amount' => $discount,
					'currency_code'  => $result->currency_code,
				],
			], 200 );
		}

		return new WP_REST_Response( [
			'success' => false,
			'message' => __( 'Invalid promo code.', 'nozule' ),
			'errors'  => $result,
		], 422 );
	}

	/**
	 * Permission callback: require admin capability.
	 */
	public function checkAdminPermission( WP_REST_Request $request ): bool {
		return current_user_can( 'manage_options' ) || current_user_can( 'nzl_admin' );
	}

	/**
	 * Permission callback: require staff capability.
	 */
	public function checkStaffPermission( WP_REST_Request $request ): bool {
		return current_user_can( 'manage_options' ) || current_user_can( 'nzl_admin' ) || current_user_can( 'nzl_staff' );
	}

	/**
	 * Extract promo code data from the request body.
	 */
	private function extractPromoData( WP_REST_Request $request ): array {
		$fields = [
			'code', 'name', 'name_ar', 'description', 'description_ar',
			'discount_type', 'discount_value', 'currency_code',
			'min_nights', 'min_amount', 'max_discount', 'max_uses',
			'per_guest_limit', 'valid_from', 'valid_to',
			'applicable_room_types', 'applicable_sources', 'is_active',
		];

		$data = [];
		foreach ( $fields as $field ) {
			$value = $request->get_param( $field );
			if ( $value !== null ) {
				$data[ $field ] = $value;
			}
		}

		return $data;
	}

	/**
	 * Argument definitions for the list endpoint.
	 */
	private function getListArgs(): array {
		return [
			'status'   => [
				'type'              => 'string',
				'default'           => '',
				'enum'              => [ '', 'active', 'inactive', 'expired' ],
				'sanitize_callback' => 'sanitize_text_field',
			],
			'search'   => [
				'type'              => 'string',
				'default'           => '',
				'sanitize_callback' => 'sanitize_text_field',
			],
			'orderby'  => [
				'type'              => 'string',
				'default'           => 'created_at',
				'enum'              => [
					'id', 'code', 'name', 'discount_type', 'discount_value',
					'max_uses', 'used_count', 'valid_from', 'valid_to',
					'is_active', 'created_at',
				],
				'sanitize_callback' => 'sanitize_text_field',
			],
			'order'    => [
				'type'    => 'string',
				'default' => 'DESC',
				'enum'    => [ 'ASC', 'DESC' ],
			],
			'per_page' => [
				'type'              => 'integer',
				'default'           => 20,
				'minimum'           => 1,
				'maximum'           => 100,
				'sanitize_callback' => 'absint',
			],
			'page'     => [
				'type'              => 'integer',
				'default'           => 1,
				'minimum'           => 1,
				'sanitize_callback' => 'absint',
			],
		];
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
	 * Argument definitions for the validate endpoint.
	 */
	private function getValidateArgs(): array {
		return [
			'code'     => [
				'type'              => 'string',
				'required'          => true,
				'sanitize_callback' => 'sanitize_text_field',
			],
			'subtotal' => [
				'type'    => 'number',
				'default' => 0,
			],
			'nights'   => [
				'type'              => 'integer',
				'default'           => 1,
				'minimum'           => 1,
				'sanitize_callback' => 'absint',
			],
			'guest_id' => [
				'type'              => 'integer',
				'sanitize_callback' => 'absint',
			],
		];
	}
}
