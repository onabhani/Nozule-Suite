<?php

namespace Nozule\Modules\Audit\Controllers;

use Nozule\Modules\Audit\Models\NightAudit;
use Nozule\Modules\Audit\Services\NightAuditService;

/**
 * REST API controller for night audit operations.
 *
 * Running an audit requires 'manage_options' (admin).
 * Viewing audit data requires 'nzl_admin' (staff).
 * Route namespace: nozule/v1
 */
class NightAuditController {

	private NightAuditService $service;

	private const NAMESPACE = 'nozule/v1';

	public function __construct( NightAuditService $service ) {
		$this->service = $service;
	}

	/**
	 * Register all night audit REST routes.
	 */
	public function registerRoutes(): void {
		$admin_perm = [ $this, 'checkAdminPermission' ];
		$staff_perm = [ $this, 'checkStaffPermission' ];

		// POST /admin/night-audit/run — run the night audit.
		register_rest_route( self::NAMESPACE, '/admin/night-audit/run', [
			'methods'             => \WP_REST_Server::CREATABLE,
			'callback'            => [ $this, 'run' ],
			'permission_callback' => $admin_perm,
			'args'                => [
				'date' => [
					'required'          => false,
					'type'              => 'string',
					'sanitize_callback' => 'sanitize_text_field',
					'validate_callback' => [ $this, 'validateDate' ],
					'description'       => __( 'Audit date in Y-m-d format. Defaults to yesterday.', 'nozule' ),
				],
			],
		] );

		// GET /admin/night-audit — list recent audits.
		register_rest_route( self::NAMESPACE, '/admin/night-audit', [
			'methods'             => \WP_REST_Server::READABLE,
			'callback'            => [ $this, 'index' ],
			'permission_callback' => $staff_perm,
			'args'                => [
				'limit' => [
					'required'          => false,
					'type'              => 'integer',
					'default'           => 30,
					'sanitize_callback' => 'absint',
					'description'       => __( 'Number of recent audits to return.', 'nozule' ),
				],
			],
		] );

		// GET /admin/night-audit/(?P<id>\d+) — show single audit.
		register_rest_route( self::NAMESPACE, '/admin/night-audit/(?P<id>\d+)', [
			'methods'             => \WP_REST_Server::READABLE,
			'callback'            => [ $this, 'show' ],
			'permission_callback' => $staff_perm,
		] );

		// GET /admin/night-audit/date/(?P<date>[0-9-]+) — get audit by date.
		register_rest_route( self::NAMESPACE, '/admin/night-audit/date/(?P<date>[0-9-]+)', [
			'methods'             => \WP_REST_Server::READABLE,
			'callback'            => [ $this, 'showByDate' ],
			'permission_callback' => $staff_perm,
			'args'                => [
				'date' => [
					'required'          => true,
					'type'              => 'string',
					'validate_callback' => [ $this, 'validateDate' ],
					'description'       => __( 'Audit date in Y-m-d format.', 'nozule' ),
				],
			],
		] );

		// GET /admin/night-audit/summary — get summary for date range.
		register_rest_route( self::NAMESPACE, '/admin/night-audit/summary', [
			'methods'             => \WP_REST_Server::READABLE,
			'callback'            => [ $this, 'summary' ],
			'permission_callback' => $staff_perm,
			'args'                => [
				'from' => [
					'required'          => true,
					'type'              => 'string',
					'sanitize_callback' => 'sanitize_text_field',
					'validate_callback' => [ $this, 'validateDate' ],
					'description'       => __( 'Start date in Y-m-d format.', 'nozule' ),
				],
				'to' => [
					'required'          => true,
					'type'              => 'string',
					'sanitize_callback' => 'sanitize_text_field',
					'validate_callback' => [ $this, 'validateDate' ],
					'description'       => __( 'End date in Y-m-d format.', 'nozule' ),
				],
			],
		] );
	}

	// ── Permission Checks ──────────────────────────────────────────

	/**
	 * Permission check: current user must have 'manage_options' capability.
	 */
	public function checkAdminPermission(): bool {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Permission check: current user must have 'nzl_admin' capability (staff level).
	 */
	public function checkStaffPermission(): bool {
		return current_user_can( 'nzl_admin' );
	}

	// ── Endpoints ──────────────────────────────────────────────────

	/**
	 * POST /admin/night-audit/run
	 *
	 * Execute the night audit for a given date (defaults to yesterday).
	 */
	public function run( \WP_REST_Request $request ): \WP_REST_Response {
		$date = $request->get_param( 'date' ) ?: null;

		$result = $this->service->runAudit( $date );

		// If an error array was returned.
		if ( is_array( $result ) && ! empty( $result['error'] ) ) {
			return new \WP_REST_Response( [
				'success' => false,
				'message' => $result['message'],
			], 409 );
		}

		return new \WP_REST_Response( [
			'success' => true,
			'message' => __( 'Night audit completed successfully.', 'nozule' ),
			'data'    => $result->toArray(),
		], 201 );
	}

	/**
	 * GET /admin/night-audit
	 *
	 * List recent audit records.
	 */
	public function index( \WP_REST_Request $request ): \WP_REST_Response {
		$limit  = (int) ( $request->get_param( 'limit' ) ?? 30 );
		$audits = $this->service->getRecentAudits( $limit );

		return new \WP_REST_Response( [
			'success' => true,
			'data'    => array_map( fn( NightAudit $a ) => $a->toArray(), $audits ),
		], 200 );
	}

	/**
	 * GET /admin/night-audit/{id}
	 *
	 * Get a single audit by ID.
	 */
	public function show( \WP_REST_Request $request ): \WP_REST_Response {
		$audit = $this->service->getAudit( (int) $request->get_param( 'id' ) );

		if ( ! $audit ) {
			return new \WP_REST_Response( [
				'success' => false,
				'message' => __( 'Night audit not found.', 'nozule' ),
			], 404 );
		}

		return new \WP_REST_Response( [
			'success' => true,
			'data'    => $audit->toArray(),
		], 200 );
	}

	/**
	 * GET /admin/night-audit/date/{date}
	 *
	 * Get an audit by date.
	 */
	public function showByDate( \WP_REST_Request $request ): \WP_REST_Response {
		$date  = $request->get_param( 'date' );
		$audit = $this->service->getAuditByDate( $date );

		if ( ! $audit ) {
			return new \WP_REST_Response( [
				'success' => false,
				'message' => sprintf(
					/* translators: %s: audit date */
					__( 'No night audit found for %s.', 'nozule' ),
					$date
				),
			], 404 );
		}

		return new \WP_REST_Response( [
			'success' => true,
			'data'    => $audit->toArray(),
		], 200 );
	}

	/**
	 * GET /admin/night-audit/summary
	 *
	 * Get aggregated summary statistics for a date range.
	 */
	public function summary( \WP_REST_Request $request ): \WP_REST_Response {
		$from = $request->get_param( 'from' );
		$to   = $request->get_param( 'to' );

		$data = $this->service->getAuditSummary( $from, $to );

		return new \WP_REST_Response( [
			'success' => true,
			'data'    => array_map( fn( NightAudit $a ) => $a->toArray(), $data['audits'] ),
			'summary' => $data['summary'],
		], 200 );
	}

	// ── Validation ─────────────────────────────────────────────────

	/**
	 * Validate a date string is in Y-m-d format.
	 *
	 * @param string          $value   The parameter value.
	 * @param \WP_REST_Request $request The request object.
	 * @param string          $param   The parameter name.
	 * @return true|\WP_Error
	 */
	public function validateDate( string $value, \WP_REST_Request $request, string $param ): true|\WP_Error {
		$date = \DateTimeImmutable::createFromFormat( 'Y-m-d', $value );

		if ( ! $date || $date->format( 'Y-m-d' ) !== $value ) {
			return new \WP_Error(
				'rest_invalid_param',
				sprintf(
					/* translators: %s: parameter name */
					__( '%s must be a valid date in Y-m-d format.', 'nozule' ),
					$param
				),
				[ 'status' => 400 ]
			);
		}

		return true;
	}
}
