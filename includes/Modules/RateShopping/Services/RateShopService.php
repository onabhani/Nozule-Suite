<?php

namespace Nozule\Modules\RateShopping\Services;

use Nozule\Core\Logger;
use Nozule\Core\SettingsManager;
use Nozule\Modules\RateShopping\Models\Competitor;
use Nozule\Modules\RateShopping\Models\ParityAlert;
use Nozule\Modules\RateShopping\Models\RateResult;
use Nozule\Modules\RateShopping\Repositories\RateShopRepository;
use Nozule\Modules\Pricing\Services\PricingService;

/**
 * Service layer for competitive rate shopping business logic.
 *
 * Provides competitor management, rate recording, parity checking,
 * and reporting capabilities.
 */
class RateShopService {

	private RateShopRepository $repository;
	private PricingService $pricingService;
	private SettingsManager $settings;
	private Logger $logger;

	/**
	 * Default parity threshold percentage.
	 * If competitor rate differs by more than this %, an alert is generated.
	 */
	private const DEFAULT_PARITY_THRESHOLD = 5.0;

	public function __construct(
		RateShopRepository $repository,
		PricingService $pricingService,
		SettingsManager $settings,
		Logger $logger
	) {
		$this->repository     = $repository;
		$this->pricingService = $pricingService;
		$this->settings       = $settings;
		$this->logger         = $logger;
	}

	// ══════════════════════════════════════════════════════════════════
	// Competitor Management
	// ══════════════════════════════════════════════════════════════════

	/**
	 * Add or update a competitor.
	 *
	 * @param string      $name          English name.
	 * @param string      $nameAr        Arabic name.
	 * @param string      $source        OTA source identifier.
	 * @param int|null    $roomTypeMatch Room type ID to match against.
	 * @param string      $notes         Free-text notes.
	 * @param int|null    $id            If provided, updates existing record.
	 * @return Competitor|array Competitor on success, error array on failure.
	 */
	public function addCompetitor(
		string $name,
		string $nameAr,
		string $source,
		?int $roomTypeMatch = null,
		string $notes = '',
		?int $id = null
	) {
		// Validate source.
		if ( ! in_array( $source, Competitor::validSources(), true ) ) {
			return [ 'source' => [ __( 'Invalid OTA source.', 'nozule' ) ] ];
		}

		if ( empty( $name ) ) {
			return [ 'name' => [ __( 'Competitor name is required.', 'nozule' ) ] ];
		}

		$data = [
			'name'            => sanitize_text_field( $name ),
			'name_ar'         => sanitize_text_field( $nameAr ),
			'source'          => sanitize_text_field( $source ),
			'room_type_match' => $roomTypeMatch ? absint( $roomTypeMatch ) : null,
			'notes'           => sanitize_textarea_field( $notes ),
			'is_active'       => 1,
		];

		if ( $id ) {
			$data['id'] = $id;
		}

		$competitor = $this->repository->saveCompetitor( $data );

		if ( ! $competitor ) {
			$this->logger->error( 'Failed to save competitor', [ 'data' => $data ] );
			return [ 'general' => [ __( 'Failed to save competitor.', 'nozule' ) ] ];
		}

		$action = $id ? 'updated' : 'created';
		$this->logger->info( "Competitor {$action}", [
			'id'   => $competitor->id,
			'name' => $competitor->name,
		] );

		return $competitor;
	}

	/**
	 * Update an existing competitor.
	 *
	 * @return Competitor|array
	 */
	public function updateCompetitor( int $id, array $data ) {
		$existing = $this->repository->getCompetitor( $id );
		if ( ! $existing ) {
			return [ 'id' => [ __( 'Competitor not found.', 'nozule' ) ] ];
		}

		$updateData = [ 'id' => $id ];

		if ( isset( $data['name'] ) ) {
			$updateData['name'] = sanitize_text_field( $data['name'] );
		}
		if ( isset( $data['name_ar'] ) ) {
			$updateData['name_ar'] = sanitize_text_field( $data['name_ar'] );
		}
		if ( isset( $data['source'] ) ) {
			if ( ! in_array( $data['source'], Competitor::validSources(), true ) ) {
				return [ 'source' => [ __( 'Invalid OTA source.', 'nozule' ) ] ];
			}
			$updateData['source'] = sanitize_text_field( $data['source'] );
		}
		if ( array_key_exists( 'room_type_match', $data ) ) {
			$updateData['room_type_match'] = $data['room_type_match'] ? absint( $data['room_type_match'] ) : null;
		}
		if ( isset( $data['notes'] ) ) {
			$updateData['notes'] = sanitize_textarea_field( $data['notes'] );
		}
		if ( isset( $data['is_active'] ) ) {
			$updateData['is_active'] = (int) (bool) $data['is_active'];
		}

		$competitor = $this->repository->saveCompetitor( $updateData );

		if ( ! $competitor ) {
			return [ 'general' => [ __( 'Failed to update competitor.', 'nozule' ) ] ];
		}

		$this->logger->info( 'Competitor updated', [
			'id'   => $competitor->id,
			'name' => $competitor->name,
		] );

		return $competitor;
	}

	/**
	 * Delete a competitor.
	 *
	 * @return bool|array True on success, error array on failure.
	 */
	public function deleteCompetitor( int $id ) {
		$existing = $this->repository->getCompetitor( $id );
		if ( ! $existing ) {
			return [ 'id' => [ __( 'Competitor not found.', 'nozule' ) ] ];
		}

		$success = $this->repository->deleteCompetitor( $id );
		if ( ! $success ) {
			return [ 'general' => [ __( 'Failed to delete competitor.', 'nozule' ) ] ];
		}

		$this->logger->info( 'Competitor deleted', [
			'id'   => $id,
			'name' => $existing->name,
		] );

		return true;
	}

	/**
	 * Get all competitors.
	 *
	 * @return Competitor[]
	 */
	public function getCompetitors(): array {
		return $this->repository->getCompetitors();
	}

	/**
	 * Get a single competitor.
	 */
	public function getCompetitor( int $id ): ?Competitor {
		return $this->repository->getCompetitor( $id );
	}

	// ══════════════════════════════════════════════════════════════════
	// Rate Recording
	// ══════════════════════════════════════════════════════════════════

	/**
	 * Record a competitor rate and trigger parity check.
	 *
	 * @param int    $competitorId The competitor ID.
	 * @param string $checkDate    The stay date (Y-m-d).
	 * @param float  $rate         The competitor's rate.
	 * @param string $currency     ISO currency code.
	 * @param string $source       How the rate was captured (manual, api, scrape).
	 * @return RateResult|array RateResult on success, error array on failure.
	 */
	public function recordRate(
		int $competitorId,
		string $checkDate,
		float $rate,
		string $currency = 'SAR',
		string $source = RateResult::SOURCE_MANUAL
	) {
		// Validate competitor exists.
		$competitor = $this->repository->getCompetitor( $competitorId );
		if ( ! $competitor ) {
			return [ 'competitor_id' => [ __( 'Competitor not found.', 'nozule' ) ] ];
		}

		if ( $rate <= 0 ) {
			return [ 'rate' => [ __( 'Rate must be greater than zero.', 'nozule' ) ] ];
		}

		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $checkDate ) ) {
			return [ 'check_date' => [ __( 'Invalid date format. Use YYYY-MM-DD.', 'nozule' ) ] ];
		}

		$result = $this->repository->saveResult( [
			'competitor_id' => $competitorId,
			'check_date'    => $checkDate,
			'rate'          => $rate,
			'currency'      => sanitize_text_field( $currency ),
			'source'        => sanitize_text_field( $source ),
		] );

		if ( ! $result ) {
			$this->logger->error( 'Failed to save rate result', [
				'competitor_id' => $competitorId,
				'check_date'    => $checkDate,
			] );
			return [ 'general' => [ __( 'Failed to record rate.', 'nozule' ) ] ];
		}

		$this->logger->info( 'Rate recorded', [
			'competitor_id' => $competitorId,
			'check_date'    => $checkDate,
			'rate'          => $rate,
		] );

		// Trigger parity check.
		$this->checkParity( $competitorId, $checkDate );

		return $result;
	}

	// ══════════════════════════════════════════════════════════════════
	// Parity Checking
	// ══════════════════════════════════════════════════════════════════

	/**
	 * Check rate parity for a competitor and check date.
	 *
	 * Compares the competitor's latest rate against our rate for the same
	 * room type and date. Generates an alert if the difference exceeds
	 * the configured threshold.
	 *
	 * @param int    $competitorId The competitor ID.
	 * @param string $checkDate    The stay date (Y-m-d).
	 * @return ParityAlert|null Alert if violation detected, null otherwise.
	 */
	public function checkParity( int $competitorId, string $checkDate ): ?ParityAlert {
		$competitor = $this->repository->getCompetitor( $competitorId );
		if ( ! $competitor || ! $competitor->room_type_match ) {
			return null;
		}

		// Get the latest competitor rate for this date.
		$results = $this->repository->getResults( $competitorId, $checkDate, $checkDate );
		if ( empty( $results ) ) {
			return null;
		}

		$latestResult = end( $results );
		$theirRate    = $latestResult->rate;

		// Get our rate for this room type and date.
		$ourRate = $this->getOurRate( $competitor->room_type_match, $checkDate );
		if ( $ourRate === null || $ourRate <= 0 ) {
			return null;
		}

		// Calculate difference.
		$difference    = round( $theirRate - $ourRate, 2 );
		$pctDifference = $ourRate > 0 ? round( ( $difference / $ourRate ) * 100, 2 ) : 0;

		// Get threshold from settings.
		$threshold = (float) $this->settings->get(
			'rate_shopping.parity_threshold',
			self::DEFAULT_PARITY_THRESHOLD
		);

		// Check if difference exceeds threshold.
		if ( abs( $pctDifference ) <= $threshold ) {
			return null; // Within parity — no alert needed.
		}

		// Determine alert type.
		$alertType = $difference < 0
			? ParityAlert::TYPE_UNDERCUT   // Competitor is cheaper.
			: ParityAlert::TYPE_OVERPRICED; // Competitor is more expensive (we're underpricing).

		$alert = $this->repository->saveAlert( [
			'competitor_id'  => $competitorId,
			'check_date'     => $checkDate,
			'our_rate'       => $ourRate,
			'their_rate'     => $theirRate,
			'difference'     => $difference,
			'pct_difference' => $pctDifference,
			'alert_type'     => $alertType,
		] );

		if ( $alert ) {
			$this->logger->warning( 'Parity violation detected', [
				'competitor'   => $competitor->name,
				'check_date'   => $checkDate,
				'our_rate'     => $ourRate,
				'their_rate'   => $theirRate,
				'difference'   => $difference,
				'pct_diff'     => $pctDifference,
				'type'         => $alertType,
			] );
		}

		return $alert ?: null;
	}

	/**
	 * Get our nightly rate for a given room type and date.
	 *
	 * @param int    $roomTypeId The room type ID.
	 * @param string $date       The stay date (Y-m-d).
	 * @return float|null Our rate, or null if unavailable.
	 */
	private function getOurRate( int $roomTypeId, string $date ): ?float {
		try {
			$nextDay = gmdate( 'Y-m-d', strtotime( $date . ' +1 day' ) );
			$result  = $this->pricingService->calculateStayPrice(
				$roomTypeId,
				$date,
				$nextDay,
				2  // Default 2 adults.
			);

			return $result->subtotal ?? null;
		} catch ( \Throwable $e ) {
			$this->logger->error( 'Failed to get our rate for parity check', [
				'room_type_id' => $roomTypeId,
				'date'         => $date,
				'error'        => $e->getMessage(),
			] );
			return null;
		}
	}

	// ══════════════════════════════════════════════════════════════════
	// Reporting
	// ══════════════════════════════════════════════════════════════════

	/**
	 * Get parity report for a date range.
	 *
	 * Returns comparison data: our rate vs each competitor for each check date.
	 *
	 * @return array[] Array of comparison objects.
	 */
	public function getParityReport( string $dateFrom, string $dateTo ): array {
		$rawData = $this->repository->getParityReportData( $dateFrom, $dateTo );
		$report  = [];

		foreach ( $rawData as $row ) {
			$roomTypeId = (int) ( $row->room_type_match ?? 0 );
			$checkDate  = $row->check_date;
			$theirRate  = (float) $row->rate;

			// Get our rate for this room type and date.
			$ourRate = $roomTypeId > 0 ? $this->getOurRate( $roomTypeId, $checkDate ) : null;

			$difference    = $ourRate !== null ? round( $theirRate - $ourRate, 2 ) : null;
			$pctDifference = ( $ourRate !== null && $ourRate > 0 )
				? round( ( $difference / $ourRate ) * 100, 2 )
				: null;

			// Determine status.
			$threshold = (float) $this->settings->get(
				'rate_shopping.parity_threshold',
				self::DEFAULT_PARITY_THRESHOLD
			);

			$status = 'unknown';
			if ( $pctDifference !== null ) {
				if ( abs( $pctDifference ) <= $threshold ) {
					$status = 'parity';
				} elseif ( $pctDifference < 0 ) {
					$status = 'undercut';
				} else {
					$status = 'overpriced';
				}
			}

			$report[] = [
				'check_date'         => $checkDate,
				'competitor_id'      => (int) $row->competitor_id,
				'competitor_name'    => $row->competitor_name,
				'competitor_name_ar' => $row->competitor_name_ar,
				'competitor_source'  => $row->competitor_source,
				'our_rate'           => $ourRate,
				'their_rate'         => $theirRate,
				'difference'         => $difference,
				'pct_difference'     => $pctDifference,
				'currency'           => $row->currency,
				'status'             => $status,
			];
		}

		return $report;
	}

	/**
	 * Get competitor rate trend data for charting.
	 *
	 * @param int $competitorId The competitor ID.
	 * @param int $days         Number of days to look back. Default 30.
	 * @return array[] Array of { check_date, rate, currency } items.
	 */
	public function getCompetitorTrends( int $competitorId, int $days = 30 ): array {
		$dateFrom = gmdate( 'Y-m-d', strtotime( "-{$days} days" ) );
		$dateTo   = gmdate( 'Y-m-d' );

		$results = $this->repository->getResults( $competitorId, $dateFrom, $dateTo );
		$trends  = [];

		foreach ( $results as $result ) {
			$trends[] = [
				'check_date' => $result->check_date,
				'rate'       => $result->rate,
				'currency'   => $result->currency,
			];
		}

		return $trends;
	}

	// ══════════════════════════════════════════════════════════════════
	// Alerts
	// ══════════════════════════════════════════════════════════════════

	/**
	 * Get alerts with filters and pagination.
	 *
	 * @return array{ items: array, total: int, pages: int }
	 */
	public function getAlerts( array $filters = [], int $page = 1 ): array {
		$result = $this->repository->getAlerts( $filters, $page );

		$items = array_map( function ( ParityAlert $alert ) {
			return $alert->toArray();
		}, $result['items'] );

		return [
			'items' => $items,
			'total' => $result['total'],
			'pages' => $result['pages'],
		];
	}

	/**
	 * Resolve a parity alert.
	 *
	 * @return bool|array True on success, error array on failure.
	 */
	public function resolveAlert( int $id ) {
		$alert = $this->repository->getAlert( $id );
		if ( ! $alert ) {
			return [ 'id' => [ __( 'Alert not found.', 'nozule' ) ] ];
		}

		if ( $alert->isResolved() ) {
			return [ 'status' => [ __( 'Alert is already resolved.', 'nozule' ) ] ];
		}

		$success = $this->repository->resolveAlert( $id );
		if ( ! $success ) {
			return [ 'general' => [ __( 'Failed to resolve alert.', 'nozule' ) ] ];
		}

		$this->logger->info( 'Parity alert resolved', [ 'id' => $id ] );

		return true;
	}

	// ══════════════════════════════════════════════════════════════════
	// Stats
	// ══════════════════════════════════════════════════════════════════

	/**
	 * Get summary statistics for the rate shopping dashboard.
	 *
	 * @return array{
	 *     total_competitors: int,
	 *     active_competitors: int,
	 *     unresolved_alerts: int,
	 *     avg_rate_diff: float,
	 *     last_shop_date: string|null
	 * }
	 */
	public function getStats(): array {
		$competitors       = $this->repository->getCompetitors();
		$activeCompetitors = $this->repository->getActiveCompetitors();

		return [
			'total_competitors'  => count( $competitors ),
			'active_competitors' => count( $activeCompetitors ),
			'unresolved_alerts'  => $this->repository->getUnresolvedCount(),
			'avg_rate_diff'      => $this->repository->getAverageRateDiff(),
			'last_shop_date'     => $this->repository->getLastShopDate(),
		];
	}

	/**
	 * Get rate results for a competitor within a date range.
	 *
	 * @return RateResult[]
	 */
	public function getResults( int $competitorId, string $dateFrom, string $dateTo ): array {
		return $this->repository->getResults( $competitorId, $dateFrom, $dateTo );
	}

	/**
	 * Get recent rate results across all competitors.
	 *
	 * @return RateResult[]
	 */
	public function getRecentResults( int $limit = 50 ): array {
		return $this->repository->getRecentResults( $limit );
	}
}
