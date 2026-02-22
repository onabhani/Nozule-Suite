<?php

namespace Nozule\Modules\Branding\Controllers;

use Nozule\Modules\Branding\Models\Brand;
use Nozule\Modules\Branding\Services\BrandService;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * REST controller for brand / white-label administration.
 *
 * Routes:
 *   GET    /nozule/v1/admin/branding/brands              List all brands
 *   GET    /nozule/v1/admin/branding/brands/{id}         Get single brand
 *   POST   /nozule/v1/admin/branding/brands              Create brand
 *   PUT    /nozule/v1/admin/branding/brands/{id}         Update brand
 *   DELETE /nozule/v1/admin/branding/brands/{id}         Delete brand
 *   POST   /nozule/v1/admin/branding/brands/{id}/set-default  Set as default brand
 *   GET    /nozule/v1/admin/branding/preview              Get CSS preview
 */
class BrandController {

	private const NAMESPACE = 'nozule/v1';

	private BrandService $service;

	public function __construct( BrandService $service ) {
		$this->service = $service;
	}

	/**
	 * Register REST API routes.
	 */
	public function registerRoutes(): void {
		// List and create brands.
		register_rest_route( self::NAMESPACE, '/admin/branding/brands', [
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

		// Single brand operations.
		register_rest_route( self::NAMESPACE, '/admin/branding/brands/(?P<id>\d+)', [
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

		// Set default brand.
		register_rest_route( self::NAMESPACE, '/admin/branding/brands/(?P<id>\d+)/set-default', [
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'setDefault' ],
				'permission_callback' => [ $this, 'checkAdminPermission' ],
				'args'                => $this->getIdArgs(),
			],
		] );

		// CSS preview (without saving).
		register_rest_route( self::NAMESPACE, '/admin/branding/preview', [
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'preview' ],
				'permission_callback' => [ $this, 'checkAdminPermission' ],
			],
		] );
	}

	/**
	 * List all brands.
	 */
	public function index( WP_REST_Request $request ): WP_REST_Response {
		$brands = $this->service->getBrands();

		$items = array_map(
			function ( Brand $brand ) {
				return $brand->toArray();
			},
			$brands
		);

		return new WP_REST_Response( [
			'success' => true,
			'data'    => $items,
		], 200 );
	}

	/**
	 * Get a single brand.
	 */
	public function show( WP_REST_Request $request ): WP_REST_Response {
		$id    = (int) $request->get_param( 'id' );
		$brand = $this->service->getBrand( $id );

		if ( ! $brand ) {
			return new WP_REST_Response( [
				'success' => false,
				'message' => __( 'Brand not found.', 'nozule' ),
			], 404 );
		}

		return new WP_REST_Response( [
			'success' => true,
			'data'    => $brand->toArray(),
		], 200 );
	}

	/**
	 * Create a new brand.
	 */
	public function store( WP_REST_Request $request ): WP_REST_Response {
		$data = $this->extractBrandData( $request );

		$result = $this->service->createBrand( $data );

		if ( $result instanceof Brand ) {
			return new WP_REST_Response( [
				'success' => true,
				'message' => __( 'Brand created successfully.', 'nozule' ),
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
	 * Update an existing brand.
	 */
	public function update( WP_REST_Request $request ): WP_REST_Response {
		$id   = (int) $request->get_param( 'id' );
		$data = $this->extractBrandData( $request );

		$result = $this->service->updateBrand( $id, $data );

		if ( $result instanceof Brand ) {
			return new WP_REST_Response( [
				'success' => true,
				'message' => __( 'Brand updated successfully.', 'nozule' ),
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
	 * Delete a brand.
	 */
	public function destroy( WP_REST_Request $request ): WP_REST_Response {
		$id     = (int) $request->get_param( 'id' );
		$result = $this->service->deleteBrand( $id );

		if ( $result === true ) {
			return new WP_REST_Response( [
				'success' => true,
				'message' => __( 'Brand deleted successfully.', 'nozule' ),
			], 200 );
		}

		$statusCode = isset( $result['id'] ) ? 404 : 422;

		return new WP_REST_Response( [
			'success' => false,
			'message' => __( 'Failed to delete brand.', 'nozule' ),
			'errors'  => $result,
		], $statusCode );
	}

	/**
	 * Set a brand as the default.
	 */
	public function setDefault( WP_REST_Request $request ): WP_REST_Response {
		$id     = (int) $request->get_param( 'id' );
		$result = $this->service->setDefault( $id );

		if ( $result instanceof Brand ) {
			return new WP_REST_Response( [
				'success' => true,
				'message' => __( 'Brand set as default successfully.', 'nozule' ),
				'data'    => $result->toArray(),
			], 200 );
		}

		$statusCode = isset( $result['id'] ) ? 404 : 422;

		return new WP_REST_Response( [
			'success' => false,
			'message' => __( 'Failed to set default brand.', 'nozule' ),
			'errors'  => $result,
		], $statusCode );
	}

	/**
	 * Preview CSS variables without saving.
	 *
	 * Accepts color query params and returns the generated CSS string.
	 */
	public function preview( WP_REST_Request $request ): WP_REST_Response {
		$brandConfig = [
			'primary_color'   => $request->get_param( 'primary_color' ) ?: Brand::DEFAULT_PRIMARY_COLOR,
			'secondary_color' => $request->get_param( 'secondary_color' ) ?: Brand::DEFAULT_SECONDARY_COLOR,
			'accent_color'    => $request->get_param( 'accent_color' ) ?: Brand::DEFAULT_ACCENT_COLOR,
			'text_color'      => $request->get_param( 'text_color' ) ?: Brand::DEFAULT_TEXT_COLOR,
		];

		$css = $this->service->generateCSSVariables( $brandConfig );

		return new WP_REST_Response( [
			'success' => true,
			'data'    => [
				'css' => ':root { ' . $css . ' }',
			],
		], 200 );
	}

	/**
	 * Permission callback: require manage_options capability.
	 */
	public function checkAdminPermission( WP_REST_Request $request ): bool {
		return current_user_can( 'manage_options' ) || current_user_can( 'nzl_admin' );
	}

	/**
	 * Extract brand data from the request body.
	 */
	private function extractBrandData( WP_REST_Request $request ): array {
		$fields = [
			'name', 'name_ar', 'logo_url', 'favicon_url',
			'primary_color', 'secondary_color', 'accent_color', 'text_color',
			'custom_css', 'email_header_html', 'email_footer_html',
			'is_active',
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
	 * Common ID argument definition.
	 */
	private function getIdArgs(): array {
		return [
			'id' => [
				'required'          => true,
				'validate_callback' => function ( $value ) {
					return is_numeric( $value ) && $value > 0;
				},
				'sanitize_callback' => 'absint',
			],
		];
	}
}
