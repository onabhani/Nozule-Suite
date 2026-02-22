<?php

namespace Nozule\Modules\Forecasting\Repositories;

use Nozule\Core\BaseRepository;
use Nozule\Core\Database;
use Nozule\Modules\Forecasting\Models\DemandForecast;

/**
 * Repository for demand forecast database operations.
 */
class ForecastRepository extends BaseRepository {

	protected string $table = 'demand_forecasts';
	protected string $model = DemandForecast::class;

	public function __construct( Database $db ) {
		parent::__construct( $db );
	}

	/**
	 * Get forecasts for a room type within a date range.
	 *
	 * @return DemandForecast[]
	 */
	public function getForecasts( ?int $roomTypeId, string $dateFrom, string $dateTo ): array {
		$table = $this->tableName();

		if ( $roomTypeId ) {
			$rows = $this->db->getResults(
				"SELECT * FROM {$table}
				 WHERE room_type_id = %d
				   AND forecast_date >= %s
				   AND forecast_date <= %s
				 ORDER BY forecast_date ASC",
				$roomTypeId,
				$dateFrom,
				$dateTo
			);
		} else {
			$rows = $this->db->getResults(
				"SELECT * FROM {$table}
				 WHERE forecast_date >= %s
				   AND forecast_date <= %s
				 ORDER BY forecast_date ASC, room_type_id ASC",
				$dateFrom,
				$dateTo
			);
		}

		return DemandForecast::fromRows( $rows );
	}

	/**
	 * Save a forecast record (insert or update).
	 *
	 * If a forecast already exists for the same room_type_id + forecast_date,
	 * it is updated. Otherwise a new row is inserted.
	 *
	 * @return DemandForecast|false
	 */
	public function save( array $data ): DemandForecast|false {
		$table = $this->tableName();

		// Encode factors to JSON if provided as array.
		if ( isset( $data['factors'] ) && is_array( $data['factors'] ) ) {
			$data['factors'] = wp_json_encode( $data['factors'] );
		}

		// Check for existing record.
		$existing = $this->db->getRow(
			"SELECT id FROM {$table}
			 WHERE room_type_id = %d AND forecast_date = %s
			 LIMIT 1",
			$data['room_type_id'],
			$data['forecast_date']
		);

		if ( $existing ) {
			unset( $data['created_at'] );
			$this->db->update( $this->table, $data, [ 'id' => (int) $existing->id ] );
			return $this->find( (int) $existing->id );
		}

		$data['created_at'] = $data['created_at'] ?? current_time( 'mysql' );
		$id = $this->db->insert( $this->table, $data );

		if ( $id === false ) {
			return false;
		}

		return $this->find( $id );
	}

	/**
	 * Delete forecasts older than the given number of days.
	 */
	public function deleteOlderThan( int $days ): int {
		$table    = $this->tableName();
		$cutoff   = wp_date( 'Y-m-d', strtotime( "-{$days} days" ) );

		return (int) $this->db->query(
			"DELETE FROM {$table} WHERE forecast_date < %s",
			$cutoff
		);
	}

	/**
	 * Get the latest forecast set for a room type.
	 *
	 * Returns all forecasts for the room type whose created_at matches
	 * the most recent batch.
	 *
	 * @return DemandForecast[]
	 */
	public function getLatestByRoomType( int $roomTypeId ): array {
		$table = $this->tableName();

		// Find the latest created_at for this room type.
		$latestCreatedAt = $this->db->getVar(
			"SELECT MAX( created_at ) FROM {$table} WHERE room_type_id = %d",
			$roomTypeId
		);

		if ( ! $latestCreatedAt ) {
			return [];
		}

		$rows = $this->db->getResults(
			"SELECT * FROM {$table}
			 WHERE room_type_id = %d
			   AND created_at = %s
			 ORDER BY forecast_date ASC",
			$roomTypeId,
			$latestCreatedAt
		);

		return DemandForecast::fromRows( $rows );
	}

	/**
	 * Bulk insert forecasts efficiently.
	 *
	 * @param array[] $records Array of forecast data arrays.
	 */
	public function bulkSave( array $records ): int {
		$saved = 0;

		foreach ( $records as $record ) {
			$result = $this->save( $record );
			if ( $result !== false ) {
				$saved++;
			}
		}

		return $saved;
	}

	/**
	 * Get aggregate summary for a room type within a date range.
	 *
	 * @return array{avg_occupancy: float, avg_adr: float, avg_confidence: float, forecast_count: int}
	 */
	public function getSummary( ?int $roomTypeId, string $dateFrom, string $dateTo ): array {
		$table = $this->tableName();

		if ( $roomTypeId ) {
			$row = $this->db->getRow(
				"SELECT
					AVG( predicted_occupancy ) AS avg_occupancy,
					AVG( predicted_adr ) AS avg_adr,
					AVG( suggested_rate ) AS avg_suggested_rate,
					AVG( confidence ) AS avg_confidence,
					COUNT( * ) AS forecast_count
				FROM {$table}
				WHERE room_type_id = %d
				  AND forecast_date >= %s
				  AND forecast_date <= %s",
				$roomTypeId,
				$dateFrom,
				$dateTo
			);
		} else {
			$row = $this->db->getRow(
				"SELECT
					AVG( predicted_occupancy ) AS avg_occupancy,
					AVG( predicted_adr ) AS avg_adr,
					AVG( suggested_rate ) AS avg_suggested_rate,
					AVG( confidence ) AS avg_confidence,
					COUNT( * ) AS forecast_count
				FROM {$table}
				WHERE forecast_date >= %s
				  AND forecast_date <= %s",
				$dateFrom,
				$dateTo
			);
		}

		if ( ! $row || (int) $row->forecast_count === 0 ) {
			return [
				'avg_occupancy'      => 0.0,
				'avg_adr'            => 0.0,
				'avg_suggested_rate' => 0.0,
				'avg_confidence'     => 0.0,
				'forecast_count'     => 0,
			];
		}

		return [
			'avg_occupancy'      => round( (float) $row->avg_occupancy, 2 ),
			'avg_adr'            => round( (float) $row->avg_adr, 2 ),
			'avg_suggested_rate' => round( (float) $row->avg_suggested_rate, 2 ),
			'avg_confidence'     => round( (float) $row->avg_confidence, 3 ),
			'forecast_count'     => (int) $row->forecast_count,
		];
	}
}
