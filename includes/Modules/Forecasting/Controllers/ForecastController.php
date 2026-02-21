<?php

namespace Nozule\Modules\Forecasting\Controllers;

use Nozule\Core\Database;
use Nozule\Modules\Forecasting\Repositories\ForecastRepository;
use Nozule\Modules\Forecasting\Services\ForecastService;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * REST controller for AI demand forecasting.
 *
 * Routes:
 *   GET  /nozule/v1/admin/forecasting/data       Get forecast data.
 *   GET  /nozule/v1/admin/forecasting/summary     Get summary stats.
 *   POST /nozule/v1/admin/forecasting/generate    Trigger forecast generation.
 *   GET  /nozule/v1/admin/forecasting/room-types  Get room types for dropdown.
 */
class ForecastController {

	private ForecastService $service;
	private ForecastRepository $repo;
	private Database $db;

	public function __construct(
		ForecastService $service,
		ForecastRepository $repo,
		Database $db
	) {
		$this->service = $service;
		$this->repo    = $repo;
		$this->db      = $db;
	}

	/**
	 * Register REST routes.
	 */
	public function registerRoutes(): void {
		$namespace = 'nozule/v1';

		// GET forecast data.
		register_rest_route( $namespace, '/admin/forecasting/data', [
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => [ $this, 'getData' ],
			'permission_callback' => [ $this, 'checkAdminPermission' ],
			'args'                => [
				'room_type_id' => [
					'required'          => false,
					'sanitize_callback' => 'absint',
				],
				'date_from' => [
					'required'          => false,
					'sanitize_callback' => 'sanitize_text_field',
				],
				'date_to' => [
					'required'          => false,
					'sanitize_callback' => 'sanitize_text_field',
				],
			],
		] );

		// GET summary stats.
		register_rest_route( $namespace, '/admin/forecasting/summary', [
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => [ $this, 'getSummary' ],
			'permission_callback' => [ $this, 'checkAdminPermission' ],
			'args'                => [
				'room_type_id' => [
					'required'          => false,
					'sanitize_callback' => 'absint',
				],
			],
		] );

		// POST trigger generation.
		register_rest_route( $namespace, '/admin/forecasting/generate', [
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => [ $this, 'generate' ],
			'permission_callback' => [ $this, 'checkAdminPermission' ],
		] );

		// GET room types for dropdown.
		register_rest_route( $namespace, '/admin/forecasting/room-types', [
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => [ $this, 'getRoomTypes' ],
			'permission_callback' => [ $this, 'checkAdminPermission' ],
		] );
	}

	/**
	 * GET /admin/forecasting/data
	 *
	 * Returns forecast data filtered by room type and date range.
	 */
	public function getData( WP_REST_Request $request ): WP_REST_Response {
		$roomTypeId = $request->get_param( 'room_type_id' );
		$dateFrom   = $request->get_param( 'date_from' );
		$dateTo     = $request->get_param( 'date_to' );

		// Defaults: next 30 days.
		if ( empty( $dateFrom ) ) {
			$dateFrom = wp_date( 'Y-m-d', strtotime( '+1 day' ) );
		}
		if ( empty( $dateTo ) ) {
			$dateTo = wp_date( 'Y-m-d', strtotime( '+30 days' ) );
		}

		$roomTypeId = $roomTypeId ? (int) $roomTypeId : null;

		$forecasts = $this->repo->getForecasts( $roomTypeId, $dateFrom, $dateTo );

		// Get current rates for comparison.
		$currentRates = $this->getCurrentRates( $roomTypeId );

		$data = array_map( function ( $forecast ) use ( $currentRates ) {
			$item                 = $forecast->toArray();
			$item['current_rate'] = $currentRates[ $forecast->room_type_id ] ?? 0.0;
			return $item;
		}, $forecasts );

		return new WP_REST_Response( [
			'success' => true,
			'data'    => $data,
		], 200 );
	}

	/**
	 * GET /admin/forecasting/summary
	 *
	 * Returns aggregate forecast statistics.
	 */
	public function getSummary( WP_REST_Request $request ): WP_REST_Response {
		$roomTypeId = $request->get_param( 'room_type_id' );
		$roomTypeId = $roomTypeId ? (int) $roomTypeId : null;

		$summary = $this->service->getSummary( $roomTypeId );

		return new WP_REST_Response( [
			'success' => true,
			'data'    => $summary,
		], 200 );
	}

	/**
	 * POST /admin/forecasting/generate
	 *
	 * Triggers forecast generation manually.
	 */
	public function generate( WP_REST_Request $request ): WP_REST_Response {
		try {
			$result = $this->service->generateForecasts();

			return new WP_REST_Response( [
				'success' => true,
				'message' => sprintf(
					/* translators: 1: number of room types, 2: number of forecasts */
					__( 'Forecasts generated: %1$d room types, %2$d forecast records.', 'nozule' ),
					$result['room_types_processed'],
					$result['forecasts_generated']
				),
				'data' => $result,
			], 200 );
		} catch ( \Throwable $e ) {
			return new WP_REST_Response( [
				'success' => false,
				'message' => __( 'Failed to generate forecasts.', 'nozule' ),
				'error'   => $e->getMessage(),
			], 500 );
		}
	}

	/**
	 * GET /admin/forecasting/room-types
	 *
	 * Returns list of room types for the filter dropdown.
	 */
	public function getRoomTypes( WP_REST_Request $request ): WP_REST_Response {
		$table = $this->db->table( 'room_types' );

		$rows = $this->db->getResults(
			"SELECT id, name, slug, base_price, status
			 FROM {$table}
			 WHERE status = 'active'
			 ORDER BY sort_order ASC, name ASC"
		);

		$data = array_map( function ( object $row ) {
			return [
				'id'         => (int) $row->id,
				'name'       => $row->name,
				'slug'       => $row->slug,
				'base_price' => (float) $row->base_price,
				'status'     => $row->status,
			];
		}, $rows );

		return new WP_REST_Response( [
			'success' => true,
			'data'    => $data,
		], 200 );
	}

	/**
	 * Get current base prices keyed by room type ID.
	 *
	 * @return array<int, float>
	 */
	private function getCurrentRates( ?int $roomTypeId ): array {
		$table = $this->db->table( 'room_types' );

		if ( $roomTypeId ) {
			$rows = $this->db->getResults(
				"SELECT id, base_price FROM {$table} WHERE id = %d",
				$roomTypeId
			);
		} else {
			$rows = $this->db->getResults(
				"SELECT id, base_price FROM {$table} WHERE status = 'active'"
			);
		}

		$rates = [];
		foreach ( $rows as $row ) {
			$rates[ (int) $row->id ] = (float) $row->base_price;
		}

		return $rates;
	}

	/**
	 * Permission callback: require manage_options or nzl_admin capability.
	 */
	public function checkAdminPermission( WP_REST_Request $request ): bool {
		return current_user_can( 'manage_options' ) || current_user_can( 'nzl_admin' );
	}
}
