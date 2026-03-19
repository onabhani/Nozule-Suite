<?php

namespace Nozule\Modules\Property\Controllers;

use Nozule\Core\ResponseHelper;
use Nozule\Modules\Property\Models\Property;
use Nozule\Modules\Property\Services\PropertyService;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * REST controller for property administration.
 *
 * Routes:
 *   GET    /nozule/v1/admin/property              Get current property (or list all)
 *   GET    /nozule/v1/admin/property/{id}          Get single property
 *   POST   /nozule/v1/admin/property               Create property
 *   PUT    /nozule/v1/admin/property/{id}          Update property
 *   DELETE /nozule/v1/admin/property/{id}          Delete property
 *   GET    /nozule/v1/property                     Public property info
 */
class PropertyController {

	private const NAMESPACE = 'nozule/v1';

	private PropertyService $service;

	public function __construct( PropertyService $service ) {
		$this->service = $service;
	}

	/**
	 * Register REST API routes.
	 */
	public function registerRoutes(): void {
		// Admin: list / create.
		register_rest_route( self::NAMESPACE, '/admin/property', [
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'index' ],
				'permission_callback' => [ $this, 'checkAdminPermission' ],
			],
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'store' ],
				'permission_callback' => [ $this, 'checkAdminPermission' ],
			],
		] );

		// Admin: show / update / delete single property.
		register_rest_route( self::NAMESPACE, '/admin/property/(?P<id>\d+)', [
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'show' ],
				'permission_callback' => [ $this, 'checkAdminPermission' ],
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

		// Public: get property info for booking engine / website.
		register_rest_route( self::NAMESPACE, '/property', [
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'publicShow' ],
				'permission_callback' => '__return_true',
			],
		] );
	}

	/**
	 * List all properties (admin).
	 *
	 * In single-property mode this returns an array with one item.
	 */
	public function index( WP_REST_Request $request ): WP_REST_Response {
		$properties = $this->service->getAll();

		$data = array_map(
			fn( Property $p ) => $p->toArray(),
			$properties
		);

		return ResponseHelper::success( $data );
	}

	/**
	 * Get a single property (admin).
	 */
	public function show( WP_REST_Request $request ): WP_REST_Response {
		$id       = (int) $request->get_param( 'id' );
		$property = $this->service->find( $id );

		if ( ! $property ) {
			return ResponseHelper::notFound( __( 'Property not found.', 'nozule' ) );
		}

		return ResponseHelper::success( $property->toArray() );
	}

	/**
	 * Create a new property.
	 */
	public function store( WP_REST_Request $request ): WP_REST_Response {
		$data = $this->extractPropertyData( $request );

		$result = $this->service->createProperty( $data );

		if ( $result instanceof Property ) {
			return ResponseHelper::created( $result->toArray(), __( 'Property created successfully.', 'nozule' ) );
		}

		return ResponseHelper::error( __( 'Validation failed.', 'nozule' ), 422, $result );
	}

	/**
	 * Update an existing property.
	 */
	public function update( WP_REST_Request $request ): WP_REST_Response {
		$id   = (int) $request->get_param( 'id' );
		$data = $this->extractPropertyData( $request );

		$result = $this->service->updateProperty( $id, $data );

		if ( $result instanceof Property ) {
			return ResponseHelper::success( $result->toArray(), __( 'Property updated successfully.', 'nozule' ) );
		}

		if ( isset( $result['id'] ) ) {
			$message = is_array( $result['id'] ) && isset( $result['id'][0] ) ? $result['id'][0] : __( 'Property not found.', 'nozule' );

			return ResponseHelper::error( $message, 404, $result );
		}

		return ResponseHelper::error( __( 'Validation failed.', 'nozule' ), 422, $result );
	}

	/**
	 * Delete a property.
	 */
	public function destroy( WP_REST_Request $request ): WP_REST_Response {
		$id     = (int) $request->get_param( 'id' );
		$result = $this->service->deleteProperty( $id );

		if ( $result === true ) {
			return ResponseHelper::success( null, __( 'Property deleted successfully.', 'nozule' ) );
		}

		$statusCode = isset( $result['id'] ) ? 404 : 422;

		// Use the service-level message when available (e.g. "Property not found.");
		// fall back to a generic message only if no specific message was provided.
		$message = __( 'Failed to delete property.', 'nozule' );
		if ( $statusCode === 404 && ! empty( $result['id'][0] ) ) {
			$message = $result['id'][0];
		}

		return ResponseHelper::error( $message, $statusCode, $result );
	}

	/**
	 * Public endpoint: get property details for booking engine / website.
	 */
	public function publicShow( WP_REST_Request $request ): WP_REST_Response {
		$property = $this->service->getCurrent();

		if ( ! $property ) {
			return ResponseHelper::notFound( __( 'No property configured.', 'nozule' ) );
		}

		// Return a subset of fields suitable for public consumption.
		$data = $property->toArray();
		unset( $data['tax_id'], $data['license_number'] );

		return ResponseHelper::success( $data );
	}

	/**
	 * Extract property data from the request body.
	 */
	private function extractPropertyData( WP_REST_Request $request ): array {
		$fields = [
			'name', 'name_ar', 'slug', 'description', 'description_ar',
			'property_type', 'star_rating',
			'address_line_1', 'address_line_2', 'city', 'state_province',
			'country', 'postal_code', 'latitude', 'longitude',
			'phone', 'phone_alt', 'email', 'website',
			'check_in_time', 'check_out_time', 'timezone',
			'logo_url', 'cover_image_url',
			'photos', 'facilities', 'policies', 'social_links',
			'tax_id', 'license_number',
			'total_rooms', 'total_floors', 'year_built', 'year_renovated',
			'currency', 'status',
		];

		$urlFields = [ 'website', 'logo_url', 'cover_image_url' ];
		$emailFields = [ 'email' ];
		$intFields = [ 'star_rating', 'total_rooms', 'total_floors', 'year_built', 'year_renovated' ];
		$jsonFields = [ 'photos', 'facilities', 'policies', 'social_links' ];

		$data = [];
		foreach ( $fields as $field ) {
			if ( ! $request->has_param( $field ) ) {
				continue;
			}

			$value = $request->get_param( $field );

			if ( in_array( $field, $urlFields, true ) ) {
				$data[ $field ] = esc_url_raw( $value );
			} elseif ( in_array( $field, $emailFields, true ) ) {
				$data[ $field ] = sanitize_email( $value );
			} elseif ( in_array( $field, $intFields, true ) ) {
				$data[ $field ] = absint( $value );
			} elseif ( in_array( $field, $jsonFields, true ) ) {
				$data[ $field ] = is_array( $value ) ? map_deep( $value, 'sanitize_text_field' ) : $value;
			} elseif ( $field === 'latitude' || $field === 'longitude' ) {
				$data[ $field ] = (float) $value;
			} else {
				$data[ $field ] = sanitize_text_field( $value );
			}
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
	 * Permission callback: require nzl_admin capability.
	 */
	public function checkAdminPermission( WP_REST_Request $request ): bool {
		return current_user_can( 'manage_options' ) || current_user_can( 'nzl_admin' );
	}
}
