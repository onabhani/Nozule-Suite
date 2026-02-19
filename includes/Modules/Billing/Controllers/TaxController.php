<?php

namespace Nozule\Modules\Billing\Controllers;

use Nozule\Modules\Billing\Models\Tax;
use Nozule\Modules\Billing\Services\TaxService;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * REST controller for tax administration.
 *
 * Routes (all admin-only):
 *   GET    /nozule/v1/admin/taxes           List all taxes
 *   GET    /nozule/v1/admin/taxes/{id}      Get single tax
 *   POST   /nozule/v1/admin/taxes           Create tax
 *   PUT    /nozule/v1/admin/taxes/{id}      Update tax
 *   DELETE /nozule/v1/admin/taxes/{id}      Delete tax
 */
class TaxController {

	private TaxService $taxService;

	public function __construct( TaxService $taxService ) {
		$this->taxService = $taxService;
	}

	/**
	 * Register REST routes.
	 */
	public function registerRoutes(): void {
		$namespace = 'nozule/v1';

		// List and create taxes.
		register_rest_route( $namespace, '/admin/taxes', [
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'index' ],
				'permission_callback' => [ $this, 'checkAdminPermission' ],
			],
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'create' ],
				'permission_callback' => [ $this, 'checkAdminPermission' ],
			],
		] );

		// Single tax operations.
		register_rest_route( $namespace, '/admin/taxes/(?P<id>\d+)', [
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
				'callback'            => [ $this, 'delete' ],
				'permission_callback' => [ $this, 'checkAdminPermission' ],
				'args'                => $this->getIdArgs(),
			],
		] );
	}

	/**
	 * List all taxes.
	 */
	public function index( WP_REST_Request $request ): WP_REST_Response {
		$taxes = $this->taxService->getAllTaxes();

		$data = array_map(
			fn( Tax $tax ) => $tax->toArray(),
			$taxes
		);

		return new WP_REST_Response( [
			'success' => true,
			'data'    => $data,
			'total'   => count( $data ),
		], 200 );
	}

	/**
	 * Get a single tax.
	 */
	public function show( WP_REST_Request $request ): WP_REST_Response {
		$id  = (int) $request->get_param( 'id' );
		$tax = $this->taxService->findTax( $id );

		if ( ! $tax ) {
			return new WP_REST_Response( [
				'success' => false,
				'message' => __( 'Tax not found.', 'nozule' ),
			], 404 );
		}

		return new WP_REST_Response( [
			'success' => true,
			'data'    => $tax->toArray(),
		], 200 );
	}

	/**
	 * Create a new tax.
	 */
	public function create( WP_REST_Request $request ): WP_REST_Response {
		$data = $this->extractTaxData( $request );

		$result = $this->taxService->createTax( $data );

		if ( $result instanceof Tax ) {
			return new WP_REST_Response( [
				'success' => true,
				'message' => __( 'Tax created successfully.', 'nozule' ),
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
	 * Update an existing tax.
	 */
	public function update( WP_REST_Request $request ): WP_REST_Response {
		$id   = (int) $request->get_param( 'id' );
		$data = $this->extractTaxData( $request );

		$result = $this->taxService->updateTax( $id, $data );

		if ( $result instanceof Tax ) {
			return new WP_REST_Response( [
				'success' => true,
				'message' => __( 'Tax updated successfully.', 'nozule' ),
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
	 * Delete a tax.
	 */
	public function delete( WP_REST_Request $request ): WP_REST_Response {
		$id     = (int) $request->get_param( 'id' );
		$result = $this->taxService->deleteTax( $id );

		if ( $result === true ) {
			return new WP_REST_Response( [
				'success' => true,
				'message' => __( 'Tax deleted successfully.', 'nozule' ),
			], 200 );
		}

		$statusCode = isset( $result['id'] ) ? 404 : 422;

		return new WP_REST_Response( [
			'success' => false,
			'message' => __( 'Failed to delete tax.', 'nozule' ),
			'errors'  => $result,
		], $statusCode );
	}

	/**
	 * Extract tax data from the request.
	 */
	private function extractTaxData( WP_REST_Request $request ): array {
		$fields = [ 'name', 'name_ar', 'rate', 'type', 'applies_to', 'is_active', 'sort_order' ];
		$data   = [];

		foreach ( $fields as $field ) {
			$value = $request->get_param( $field );
			if ( $value !== null ) {
				$data[ $field ] = $value;
			}
		}

		// Sanitize.
		if ( isset( $data['name'] ) ) {
			$data['name'] = sanitize_text_field( $data['name'] );
		}
		if ( isset( $data['name_ar'] ) ) {
			$data['name_ar'] = sanitize_text_field( $data['name_ar'] );
		}
		if ( isset( $data['rate'] ) ) {
			$data['rate'] = (float) $data['rate'];
		}
		if ( isset( $data['type'] ) ) {
			$data['type'] = sanitize_text_field( $data['type'] );
		}
		if ( isset( $data['applies_to'] ) ) {
			$data['applies_to'] = sanitize_text_field( $data['applies_to'] );
		}
		if ( isset( $data['is_active'] ) ) {
			$data['is_active'] = (int) $data['is_active'];
		}
		if ( isset( $data['sort_order'] ) ) {
			$data['sort_order'] = absint( $data['sort_order'] );
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

	// Standard CRUD aliases (used by RestController admin routes).

	public function store( WP_REST_Request $request ): WP_REST_Response {
		return $this->create( $request );
	}

	public function destroy( WP_REST_Request $request ): WP_REST_Response {
		return $this->delete( $request );
	}
}
