<?php

namespace Nozule\Modules\Forecasting\Services;

use Nozule\Core\Database;
use Nozule\Modules\Forecasting\Repositories\ForecastRepository;

/**
 * Service for generating AI demand forecasts.
 *
 * Analyses historical booking data, computes occupancy trends using
 * a weighted moving average with seasonal decomposition, and produces
 * 30-day forward-looking rate suggestions per room type.
 */
class ForecastService {

	private Database $db;
	private ForecastRepository $repo;

	/**
	 * Number of historical days to analyse.
	 */
	private const LOOKBACK_DAYS = 90;

	/**
	 * Number of future days to forecast.
	 */
	private const FORECAST_DAYS = 30;

	/**
	 * Minimum multiplier for suggested rate relative to base price.
	 */
	private const MIN_RATE_FACTOR = 0.70;

	/**
	 * Maximum multiplier for suggested rate relative to base price.
	 */
	private const MAX_RATE_FACTOR = 1.80;

	public function __construct( Database $db, ForecastRepository $repo ) {
		$this->db   = $db;
		$this->repo = $repo;
	}

	/**
	 * Generate forecasts for all active room types.
	 *
	 * This is the main entry point called by WP-Cron or manual trigger.
	 *
	 * @return array{room_types_processed: int, forecasts_generated: int}
	 */
	public function generateForecasts(): array {
		$roomTypes = $this->getActiveRoomTypes();
		$totalForecasts = 0;

		foreach ( $roomTypes as $roomType ) {
			$count = $this->generateForecastsForRoomType( $roomType );
			$totalForecasts += $count;
		}

		// Clean up old forecasts (older than 90 days).
		$this->repo->deleteOlderThan( 90 );

		return [
			'room_types_processed' => count( $roomTypes ),
			'forecasts_generated'  => $totalForecasts,
		];
	}

	/**
	 * Generate forecasts for a single room type.
	 *
	 * Algorithm: Weighted Moving Average with Seasonal Decomposition.
	 *
	 * 1. Collect daily occupancy from bookings for the last 90 days.
	 * 2. Calculate day-of-week factors (which days are busiest).
	 * 3. Calculate monthly trend factor.
	 * 4. Compute weighted moving average (recent days weighted more).
	 * 5. Project forward 30 days with confidence level.
	 * 6. Suggest rates: base_rate * (predicted_occupancy / avg_occupancy).
	 */
	private function generateForecastsForRoomType( object $roomType ): int {
		$roomTypeId = (int) $roomType->id;
		$basePrice  = (float) $roomType->base_price;
		$totalRooms = $this->getTotalRoomsForType( $roomTypeId );

		if ( $totalRooms <= 0 ) {
			$totalRooms = 1; // Prevent division by zero.
		}

		// Step 1: Collect historical daily occupancy.
		$historicalData = $this->getHistoricalOccupancy( $roomTypeId, self::LOOKBACK_DAYS );

		if ( empty( $historicalData ) ) {
			// No historical data — generate flat forecasts with low confidence.
			return $this->generateFlatForecasts( $roomTypeId, $basePrice, $totalRooms );
		}

		// Step 2: Calculate day-of-week factors.
		$dowFactors = $this->calculateDayOfWeekFactors( $historicalData );

		// Step 3: Calculate monthly trend.
		$monthlyTrend = $this->calculateMonthlyTrend( $historicalData );

		// Step 4: Compute average historical occupancy.
		$avgOccupancy = $this->calculateAverageOccupancy( $historicalData, $totalRooms );

		// Step 5: Compute weighted moving average.
		$wma = $this->calculateWeightedMovingAverage( $historicalData, $totalRooms );

		// Step 6: Project forward and generate forecasts.
		$forecasts = [];
		$today     = new \DateTimeImmutable( current_time( 'Y-m-d' ) );

		for ( $i = 1; $i <= self::FORECAST_DAYS; $i++ ) {
			$forecastDate = $today->modify( "+{$i} days" );
			$dateStr      = $forecastDate->format( 'Y-m-d' );
			$dayOfWeek    = (int) $forecastDate->format( 'w' );
			$month        = (int) $forecastDate->format( 'n' );

			// Apply seasonal factors.
			$dowFactor   = $dowFactors[ $dayOfWeek ] ?? 1.0;
			$trendFactor = $monthlyTrend[ $month ] ?? 1.0;

			// Predicted occupancy (percentage).
			$predicted = $wma * $dowFactor * $trendFactor;
			$predicted = max( 0, min( 100, $predicted ) );

			// Confidence decays with forecast horizon.
			$confidence = $this->calculateConfidence( $i, count( $historicalData ) );

			// Suggested rate based on predicted demand vs average.
			$rateFactor = $avgOccupancy > 0
				? $predicted / $avgOccupancy
				: 1.0;

			$rateFactor    = max( self::MIN_RATE_FACTOR, min( self::MAX_RATE_FACTOR, $rateFactor ) );
			$suggestedRate = round( $basePrice * $rateFactor, 2 );
			$predictedAdr  = $suggestedRate;

			// Build factors array for transparency.
			$factors = [
				'dow_factor'    => round( $dowFactor, 3 ),
				'trend_factor'  => round( $trendFactor, 3 ),
				'wma_base'      => round( $wma, 2 ),
				'avg_occupancy' => round( $avgOccupancy, 2 ),
				'rate_factor'   => round( $rateFactor, 3 ),
				'horizon_days'  => $i,
				'data_points'   => count( $historicalData ),
			];

			$forecasts[] = [
				'room_type_id'        => $roomTypeId,
				'forecast_date'       => $dateStr,
				'predicted_occupancy' => round( $predicted, 2 ),
				'predicted_adr'       => $predictedAdr,
				'confidence'          => round( $confidence, 3 ),
				'suggested_rate'      => $suggestedRate,
				'factors'             => $factors,
			];
		}

		return $this->repo->bulkSave( $forecasts );
	}

	/**
	 * Generate flat forecasts when no historical data is available.
	 */
	private function generateFlatForecasts( int $roomTypeId, float $basePrice, int $totalRooms ): int {
		$forecasts = [];
		$today     = new \DateTimeImmutable( current_time( 'Y-m-d' ) );

		for ( $i = 1; $i <= self::FORECAST_DAYS; $i++ ) {
			$forecastDate = $today->modify( "+{$i} days" );

			$forecasts[] = [
				'room_type_id'        => $roomTypeId,
				'forecast_date'       => $forecastDate->format( 'Y-m-d' ),
				'predicted_occupancy' => 50.0,
				'predicted_adr'       => $basePrice,
				'confidence'          => 0.1,
				'suggested_rate'      => $basePrice,
				'factors'             => [
					'note'        => 'No historical data available',
					'data_points' => 0,
				],
			];
		}

		return $this->repo->bulkSave( $forecasts );
	}

	/**
	 * Get historical daily booking counts for a room type.
	 *
	 * Returns an array of objects with 'date', 'booking_count', and
	 * 'day_of_week' for each day in the lookback period.
	 *
	 * @return object[]
	 */
	private function getHistoricalOccupancy( int $roomTypeId, int $days ): array {
		$bookings  = $this->db->table( 'bookings' );
		$startDate = wp_date( 'Y-m-d', strtotime( "-{$days} days" ) );
		$endDate   = wp_date( 'Y-m-d' );

		return $this->db->getResults(
			"SELECT
				dates.date,
				DAYOFWEEK( dates.date ) - 1 AS day_of_week,
				MONTH( dates.date ) AS month,
				COALESCE( bc.booking_count, 0 ) AS booking_count
			FROM (
				SELECT DATE_ADD( %s, INTERVAL seq.n DAY ) AS date
				FROM (
					SELECT ( a.N + b.N * 10 + c.N * 100 ) AS n
					FROM
						(SELECT 0 AS N UNION SELECT 1 UNION SELECT 2 UNION SELECT 3 UNION SELECT 4
						 UNION SELECT 5 UNION SELECT 6 UNION SELECT 7 UNION SELECT 8 UNION SELECT 9) a
					CROSS JOIN
						(SELECT 0 AS N UNION SELECT 1 UNION SELECT 2 UNION SELECT 3 UNION SELECT 4
						 UNION SELECT 5 UNION SELECT 6 UNION SELECT 7 UNION SELECT 8 UNION SELECT 9) b
					CROSS JOIN
						(SELECT 0 AS N UNION SELECT 1 UNION SELECT 2 UNION SELECT 3 UNION SELECT 4
						 UNION SELECT 5 UNION SELECT 6 UNION SELECT 7 UNION SELECT 8 UNION SELECT 9) c
				) seq
				WHERE DATE_ADD( %s, INTERVAL seq.n DAY ) <= %s
			) dates
			LEFT JOIN (
				SELECT
					d.date AS booking_date,
					COUNT( DISTINCT b.id ) AS booking_count
				FROM {$bookings} b
				CROSS JOIN (
					SELECT DATE_ADD( %s, INTERVAL seq.n DAY ) AS date
					FROM (
						SELECT ( a.N + b.N * 10 + c.N * 100 ) AS n
						FROM
							(SELECT 0 AS N UNION SELECT 1 UNION SELECT 2 UNION SELECT 3 UNION SELECT 4
							 UNION SELECT 5 UNION SELECT 6 UNION SELECT 7 UNION SELECT 8 UNION SELECT 9) a
						CROSS JOIN
							(SELECT 0 AS N UNION SELECT 1 UNION SELECT 2 UNION SELECT 3 UNION SELECT 4
							 UNION SELECT 5 UNION SELECT 6 UNION SELECT 7 UNION SELECT 8 UNION SELECT 9) b
						CROSS JOIN
							(SELECT 0 AS N UNION SELECT 1 UNION SELECT 2 UNION SELECT 3 UNION SELECT 4
							 UNION SELECT 5 UNION SELECT 6 UNION SELECT 7 UNION SELECT 8 UNION SELECT 9) c
					) seq
					WHERE DATE_ADD( %s, INTERVAL seq.n DAY ) <= %s
				) d
				WHERE b.room_type_id = %d
				  AND b.status IN ( 'confirmed', 'checked_in', 'checked_out', 'pending' )
				  AND b.check_in <= d.date
				  AND b.check_out > d.date
				GROUP BY d.date
			) bc ON dates.date = bc.booking_date
			ORDER BY dates.date ASC",
			$startDate,
			$startDate,
			$endDate,
			$startDate,
			$startDate,
			$endDate,
			$roomTypeId
		);
	}

	/**
	 * Calculate day-of-week factors.
	 *
	 * A factor > 1 means that day is busier than average.
	 *
	 * @param object[] $historicalData
	 * @return array<int, float> Day index (0=Sun) => factor.
	 */
	private function calculateDayOfWeekFactors( array $historicalData ): array {
		$dowTotals = array_fill( 0, 7, 0 );
		$dowCounts = array_fill( 0, 7, 0 );

		foreach ( $historicalData as $row ) {
			$dow = (int) $row->day_of_week;
			$dowTotals[ $dow ] += (int) $row->booking_count;
			$dowCounts[ $dow ]++;
		}

		// Calculate per-day averages.
		$dowAverages = [];
		for ( $i = 0; $i < 7; $i++ ) {
			$dowAverages[ $i ] = $dowCounts[ $i ] > 0
				? $dowTotals[ $i ] / $dowCounts[ $i ]
				: 0;
		}

		// Overall average.
		$overallAvg = array_sum( $dowAverages ) / 7;

		if ( $overallAvg <= 0 ) {
			return array_fill( 0, 7, 1.0 );
		}

		// Factors relative to overall average.
		$factors = [];
		for ( $i = 0; $i < 7; $i++ ) {
			$factors[ $i ] = $dowAverages[ $i ] / $overallAvg;

			// Clamp factors to prevent extreme values.
			$factors[ $i ] = max( 0.5, min( 2.0, $factors[ $i ] ) );
		}

		return $factors;
	}

	/**
	 * Calculate monthly trend factors.
	 *
	 * Compares each month's average bookings to the overall average.
	 *
	 * @param object[] $historicalData
	 * @return array<int, float> Month number (1–12) => factor.
	 */
	private function calculateMonthlyTrend( array $historicalData ): array {
		$monthTotals = [];
		$monthCounts = [];

		foreach ( $historicalData as $row ) {
			$month = (int) $row->month;
			if ( ! isset( $monthTotals[ $month ] ) ) {
				$monthTotals[ $month ] = 0;
				$monthCounts[ $month ] = 0;
			}
			$monthTotals[ $month ] += (int) $row->booking_count;
			$monthCounts[ $month ]++;
		}

		// Monthly averages.
		$monthAverages = [];
		foreach ( $monthTotals as $month => $total ) {
			$monthAverages[ $month ] = $monthCounts[ $month ] > 0
				? $total / $monthCounts[ $month ]
				: 0;
		}

		if ( empty( $monthAverages ) ) {
			$factors = [];
			for ( $m = 1; $m <= 12; $m++ ) {
				$factors[ $m ] = 1.0;
			}
			return $factors;
		}

		$overallAvg = array_sum( $monthAverages ) / count( $monthAverages );

		$factors = [];
		for ( $m = 1; $m <= 12; $m++ ) {
			if ( isset( $monthAverages[ $m ] ) && $overallAvg > 0 ) {
				$factors[ $m ] = $monthAverages[ $m ] / $overallAvg;
				$factors[ $m ] = max( 0.6, min( 1.6, $factors[ $m ] ) );
			} else {
				$factors[ $m ] = 1.0;
			}
		}

		return $factors;
	}

	/**
	 * Calculate average occupancy percentage over the historical period.
	 *
	 * @param object[] $historicalData
	 */
	private function calculateAverageOccupancy( array $historicalData, int $totalRooms ): float {
		if ( empty( $historicalData ) || $totalRooms <= 0 ) {
			return 50.0;
		}

		$totalBookings = 0;
		foreach ( $historicalData as $row ) {
			$totalBookings += (int) $row->booking_count;
		}

		$avgBookings  = $totalBookings / count( $historicalData );
		$avgOccupancy = ( $avgBookings / $totalRooms ) * 100;

		return max( 1.0, min( 100.0, $avgOccupancy ) );
	}

	/**
	 * Calculate weighted moving average of occupancy.
	 *
	 * Recent days are weighted more heavily using exponential decay.
	 *
	 * @param object[] $historicalData
	 */
	private function calculateWeightedMovingAverage( array $historicalData, int $totalRooms ): float {
		if ( empty( $historicalData ) || $totalRooms <= 0 ) {
			return 50.0;
		}

		$count       = count( $historicalData );
		$weightSum   = 0;
		$weightedSum = 0;

		// Decay factor — more recent data has higher weight.
		$decayRate = 0.97;

		for ( $i = 0; $i < $count; $i++ ) {
			$weight     = pow( $decayRate, $count - $i - 1 );
			$bookings   = (int) $historicalData[ $i ]->booking_count;
			$occupancy  = ( $bookings / $totalRooms ) * 100;

			$weightedSum += $occupancy * $weight;
			$weightSum   += $weight;
		}

		$wma = $weightSum > 0 ? $weightedSum / $weightSum : 50.0;

		return max( 0, min( 100, $wma ) );
	}

	/**
	 * Calculate confidence level for a forecast.
	 *
	 * Confidence decreases with forecast horizon and increases with data volume.
	 *
	 * @param int $horizonDays  Days into the future.
	 * @param int $dataPoints   Number of historical data points.
	 */
	private function calculateConfidence( int $horizonDays, int $dataPoints ): float {
		// Base confidence from data volume (more data = higher confidence).
		$dataConfidence = min( 1.0, $dataPoints / self::LOOKBACK_DAYS );

		// Horizon decay (further out = less confident).
		$horizonDecay = pow( 0.95, $horizonDays - 1 );

		$confidence = $dataConfidence * $horizonDecay;

		return max( 0.05, min( 0.99, $confidence ) );
	}

	/**
	 * Get summary statistics for a room type.
	 *
	 * @return array{avg_predicted_occupancy: float, suggested_adr: float, avg_confidence: float, forecast_count: int}
	 */
	public function getSummary( ?int $roomTypeId ): array {
		$dateFrom = wp_date( 'Y-m-d', strtotime( '+1 day' ) );
		$dateTo   = wp_date( 'Y-m-d', strtotime( '+' . self::FORECAST_DAYS . ' days' ) );

		$stats = $this->repo->getSummary( $roomTypeId, $dateFrom, $dateTo );

		return [
			'avg_predicted_occupancy' => $stats['avg_occupancy'],
			'suggested_adr'           => $stats['avg_suggested_rate'],
			'avg_confidence'          => $stats['avg_confidence'],
			'forecast_count'          => $stats['forecast_count'],
			'date_from'               => $dateFrom,
			'date_to'                 => $dateTo,
		];
	}

	/**
	 * Get all active room types from the database.
	 *
	 * @return object[]
	 */
	private function getActiveRoomTypes(): array {
		$table = $this->db->table( 'room_types' );

		return $this->db->getResults(
			"SELECT id, name, slug, base_price
			 FROM {$table}
			 WHERE status = 'active'
			 ORDER BY sort_order ASC, name ASC"
		);
	}

	/**
	 * Get total room count for a room type.
	 */
	private function getTotalRoomsForType( int $roomTypeId ): int {
		$rooms = $this->db->table( 'rooms' );

		$count = $this->db->getVar(
			"SELECT COUNT(*) FROM {$rooms}
			 WHERE room_type_id = %d AND status = 'active'",
			$roomTypeId
		);

		return (int) $count;
	}
}
