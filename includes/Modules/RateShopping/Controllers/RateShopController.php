<?php

namespace Nozule\Modules\RateShopping\Controllers;

use Nozule\Core\ResponseHelper;
use Nozule\Modules\RateShopping\Models\Competitor;
use Nozule\Modules\RateShopping\Models\RateResult;
use Nozule\Modules\RateShopping\Services\RateShopService;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * REST controller for competitive rate shopping administration.
 *
 * Routes:
 *   GET    /nozule/v1/admin/rate-shopping/competitors          List competitors
 *   POST   /nozule/v1/admin/rate-shopping/competitors          Create/update competitor
 *   DELETE /nozule/v1/admin/rate-shopping/competitors/{id}     Delete competitor
 *   GET    /nozule/v1/admin/rate-shopping/results              Get rate results
 *   POST   /nozule/v1/admin/rate-shopping/results              Record a rate
 *   GET    /nozule/v1/admin/rate-shopping/parity               Get parity report
 *   GET    /nozule/v1/admin/rate-shopping/alerts               List alerts
 *   PUT    /nozule/v1/admin/rate-shopping/alerts/{id}/resolve  Resolve alert
 *   GET    /nozule/v1/admin/rate-shopping/stats                Summary stats
 */
class RateShopController {

	private const NAMESPACE = 'nozule/v1';

	private RateShopService $service;

	public function __construct( RateShopService $service ) {
		$this->service = $service;
	}

	/**
	 * Register REST API routes.
	 */
	public function registerRoutes(): void {
		// Competitors: list and create/update.
		register_rest_route( self::NAMESPACE, '/admin/rate-shopping/competitors', [
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'listCompetitors' ],
				'permission_callback' => [ $this, 'checkAdminPermission' ],
			],
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'saveCompetitor' ],
				'permission_callback' => [ $this, 'checkAdminPermission' ],
			],
		] );

		// Competitors: delete single.
		register_rest_route( self::NAMESPACE, '/admin/rate-shopping/competitors/(?P<id>\d+)', [
			[
				'methods'             => WP_REST_Server::DELETABLE,
				'callback'            => [ $this, 'deleteCompetitor' ],
				'permission_callback' => [ $this, 'checkAdminPermission' ],
				'args'                => $this->getIdArgs(),
			],
		] );

		// Results: list and record.
		register_rest_route( self::NAMESPACE, '/admin/rate-shopping/results', [
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'listResults' ],
				'permission_callback' => [ $this, 'checkAdminPermission' ],
				'args'                => $this->getResultsListArgs(),
			],
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'recordRate' ],
				'permission_callback' => [ $this, 'checkAdminPermission' ],
			],
		] );

		// Parity report.
		register_rest_route( self::NAMESPACE, '/admin/rate-shopping/parity', [
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'parityReport' ],
				'permission_callback' => [ $this, 'checkAdminPermission' ],
				'args'                => $this->getDateRangeArgs(),
			],
		] );

		// Alerts: list.
		register_rest_route( self::NAMESPACE, '/admin/rate-shopping/alerts', [
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'listAlerts' ],
				'permission_callback' => [ $this, 'checkAdminPermission' ],
				'args'                => $this->getAlertsListArgs(),
			],
		] );

		// Alerts: resolve.
		register_rest_route( self::NAMESPACE, '/admin/rate-shopping/alerts/(?P<id>\d+)/resolve', [
			[
				'methods'             => WP_REST_Server::EDITABLE,
				'callback'            => [ $this, 'resolveAlert' ],
				'permission_callback' => [ $this, 'checkAdminPermission' ],
				'args'                => $this->getIdArgs(),
			],
		] );

		// Stats.
		register_rest_route( self::NAMESPACE, '/admin/rate-shopping/stats', [
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'stats' ],
				'permission_callback' => [ $this, 'checkAdminPermission' ],
			],
		] );
	}

	// ══════════════════════════════════════════════════════════════════
	// Route Handlers
	// ══════════════════════════════════════════════════════════════════

	/**
	 * GET /admin/rate-shopping/competitors
	 * List all competitors.
	 */
	public function listCompetitors( WP_REST_Request $request ): WP_REST_Response {
		$competitors = $this->service->getCompetitors();

		$items = array_map( function ( Competitor $c ) {
			return $c->toArray();
		}, $competitors );

		return ResponseHelper::success( $items );
	}

	/**
	 * POST /admin/rate-shopping/competitors
	 * Create or update a competitor.
	 */
	public function saveCompetitor( WP_REST_Request $request ): WP_REST_Response {
		$id = $request->get_param( 'id' ) ? (int) $request->get_param( 'id' ) : null;

		if ( $id ) {
			// Update existing.
			$data = [];
			$fields = [ 'name', 'name_ar', 'source', 'room_type_match', 'notes', 'is_active' ];
			foreach ( $fields as $field ) {
				$value = $request->get_param( $field );
				if ( $value !== null ) {
					$data[ $field ] = $value;
				}
			}

			$result = $this->service->updateCompetitor( $id, $data );
		} else {
			// Create new.
			$result = $this->service->addCompetitor(
				$request->get_param( 'name' ) ?? '',
				$request->get_param( 'name_ar' ) ?? '',
				$request->get_param( 'source' ) ?? '',
				$request->get_param( 'room_type_match' ) ? (int) $request->get_param( 'room_type_match' ) : null,
				$request->get_param( 'notes' ) ?? ''
			);
		}

		if ( $result instanceof Competitor ) {
			$message = $id
				? __( 'Competitor updated successfully.', 'nozule' )
				: __( 'Competitor created successfully.', 'nozule' );
			return $id
				? ResponseHelper::success( $result->toArray(), $message )
				: ResponseHelper::created( $result->toArray(), $message );
		}

		return ResponseHelper::error( __( 'Validation failed.', 'nozule' ), 422, $result );
	}

	/**
	 * DELETE /admin/rate-shopping/competitors/{id}
	 * Delete a competitor.
	 */
	public function deleteCompetitor( WP_REST_Request $request ): WP_REST_Response {
		$id     = (int) $request->get_param( 'id' );
		$result = $this->service->deleteCompetitor( $id );

		if ( $result === true ) {
			return ResponseHelper::success( null, __( 'Competitor deleted successfully.', 'nozule' ) );
		}

		$statusCode = isset( $result['id'] ) ? 404 : 422;

		return ResponseHelper::error( __( 'Failed to delete competitor.', 'nozule' ), $statusCode, $result );
	}

	/**
	 * GET /admin/rate-shopping/results
	 * List rate results for a competitor.
	 */
	public function listResults( WP_REST_Request $request ): WP_REST_Response {
		$competitorId = (int) ( $request->get_param( 'competitor_id' ) ?? 0 );
		$dateFrom     = $request->get_param( 'date_from' ) ?? '';
		$dateTo       = $request->get_param( 'date_to' ) ?? '';

		if ( $competitorId > 0 && $dateFrom && $dateTo ) {
			$results = $this->service->getResults( $competitorId, $dateFrom, $dateTo );
		} else {
			// Return recent results across all competitors.
			$results = $this->service->getRecentResults( 50 );
		}

		$items = array_map( function ( RateResult $r ) {
			return $r->toArray();
		}, $results );

		return ResponseHelper::success( $items );
	}

	/**
	 * POST /admin/rate-shopping/results
	 * Record one or more rates manually.
	 */
	public function recordRate( WP_REST_Request $request ): WP_REST_Response {
		// Support bulk entries: if 'entries' param is an array, process each.
		$entries = $request->get_param( 'entries' );

		if ( is_array( $entries ) && ! empty( $entries ) ) {
			return $this->recordBulkRates( $entries );
		}

		// Single entry.
		$result = $this->service->recordRate(
			(int) ( $request->get_param( 'competitor_id' ) ?? 0 ),
			$request->get_param( 'check_date' ) ?? '',
			(float) ( $request->get_param( 'rate' ) ?? 0 ),
			$request->get_param( 'currency' ) ?? 'SAR',
			$request->get_param( 'source' ) ?? RateResult::SOURCE_MANUAL
		);

		if ( $result instanceof RateResult ) {
			return ResponseHelper::created( $result->toArray(), __( 'Rate recorded successfully.', 'nozule' ) );
		}

		return ResponseHelper::error( __( 'Failed to record rate.', 'nozule' ), 422, $result );
	}

	/**
	 * Process bulk rate entries.
	 */
	private function recordBulkRates( array $entries ): WP_REST_Response {
		$results = [];
		$errors  = [];

		foreach ( $entries as $index => $entry ) {
			$result = $this->service->recordRate(
				(int) ( $entry['competitor_id'] ?? 0 ),
				$entry['check_date'] ?? '',
				(float) ( $entry['rate'] ?? 0 ),
				$entry['currency'] ?? 'SAR',
				$entry['source'] ?? RateResult::SOURCE_MANUAL
			);

			if ( $result instanceof RateResult ) {
				$results[] = $result->toArray();
			} else {
				$errors[] = [
					'index'  => $index,
					'errors' => $result,
				];
			}
		}

		$allSucceeded = empty( $errors );

		if ( $allSucceeded ) {
			return ResponseHelper::created( $results, __( 'All rates recorded successfully.', 'nozule' ) );
		}

		return new WP_REST_Response( [
			'success' => false,
			'data'    => [
				'successes' => $results,
				'errors'    => $errors,
			],
			'message' => sprintf(
				/* translators: 1: number of successful entries, 2: total entries */
				__( '%1$d of %2$d rates recorded. Some entries had errors.', 'nozule' ),
				count( $results ),
				count( $entries )
			),
			'meta'    => [],
		], 207 );
	}

	/**
	 * GET /admin/rate-shopping/parity
	 * Get parity report for a date range.
	 */
	public function parityReport( WP_REST_Request $request ): WP_REST_Response {
		$dateFrom = $request->get_param( 'date_from' ) ?? gmdate( 'Y-m-d' );
		$dateTo   = $request->get_param( 'date_to' ) ?? gmdate( 'Y-m-d', strtotime( '+7 days' ) );

		$report = $this->service->getParityReport( $dateFrom, $dateTo );

		return ResponseHelper::success( $report );
	}

	/**
	 * GET /admin/rate-shopping/alerts
	 * List parity alerts with filters.
	 */
	public function listAlerts( WP_REST_Request $request ): WP_REST_Response {
		$filters = [
			'status'        => $request->get_param( 'status' ) ?? '',
			'competitor_id' => $request->get_param( 'competitor_id' ) ?? 0,
			'per_page'      => $request->get_param( 'per_page' ) ?? 20,
		];

		$page   = (int) ( $request->get_param( 'page' ) ?? 1 );
		$result = $this->service->getAlerts( $filters, $page );

		return ResponseHelper::success( [
			'items'      => $result['items'],
			'pagination' => [
				'page'        => $page,
				'per_page'    => (int) ( $filters['per_page'] ),
				'total'       => $result['total'],
				'total_pages' => $result['pages'],
			],
		] );
	}

	/**
	 * PUT /admin/rate-shopping/alerts/{id}/resolve
	 * Resolve a parity alert.
	 */
	public function resolveAlert( WP_REST_Request $request ): WP_REST_Response {
		$id     = (int) $request->get_param( 'id' );
		$result = $this->service->resolveAlert( $id );

		if ( $result === true ) {
			return ResponseHelper::success( null, __( 'Alert resolved successfully.', 'nozule' ) );
		}

		$statusCode = isset( $result['id'] ) ? 404 : 422;

		return ResponseHelper::error( __( 'Failed to resolve alert.', 'nozule' ), $statusCode, $result );
	}

	/**
	 * GET /admin/rate-shopping/stats
	 * Get summary statistics.
	 */
	public function stats( WP_REST_Request $request ): WP_REST_Response {
		$stats = $this->service->getStats();

		return ResponseHelper::success( $stats );
	}

	// ══════════════════════════════════════════════════════════════════
	// Permissions
	// ══════════════════════════════════════════════════════════════════

	/**
	 * Permission callback: require admin capability.
	 */
	public function checkAdminPermission( WP_REST_Request $request ): bool {
		return current_user_can( 'manage_options' ) || current_user_can( 'nzl_admin' );
	}

	// ══════════════════════════════════════════════════════════════════
	// Argument Definitions
	// ══════════════════════════════════════════════════════════════════

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
	 * Arguments for results listing.
	 */
	private function getResultsListArgs(): array {
		return [
			'competitor_id' => [
				'type'              => 'integer',
				'default'           => 0,
				'sanitize_callback' => 'absint',
			],
			'date_from' => [
				'type'              => 'string',
				'default'           => '',
				'sanitize_callback' => 'sanitize_text_field',
			],
			'date_to' => [
				'type'              => 'string',
				'default'           => '',
				'sanitize_callback' => 'sanitize_text_field',
			],
		];
	}

	/**
	 * Date range arguments.
	 */
	private function getDateRangeArgs(): array {
		return [
			'date_from' => [
				'type'              => 'string',
				'default'           => '',
				'sanitize_callback' => 'sanitize_text_field',
			],
			'date_to' => [
				'type'              => 'string',
				'default'           => '',
				'sanitize_callback' => 'sanitize_text_field',
			],
		];
	}

	/**
	 * Arguments for alerts listing.
	 */
	private function getAlertsListArgs(): array {
		return [
			'status' => [
				'type'              => 'string',
				'default'           => '',
				'enum'              => [ '', 'unresolved', 'resolved' ],
				'sanitize_callback' => 'sanitize_text_field',
			],
			'competitor_id' => [
				'type'              => 'integer',
				'default'           => 0,
				'sanitize_callback' => 'absint',
			],
			'per_page' => [
				'type'              => 'integer',
				'default'           => 20,
				'minimum'           => 1,
				'maximum'           => 100,
				'sanitize_callback' => 'absint',
			],
			'page' => [
				'type'              => 'integer',
				'default'           => 1,
				'minimum'           => 1,
				'sanitize_callback' => 'absint',
			],
		];
	}
}
