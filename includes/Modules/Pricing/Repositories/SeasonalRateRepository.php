<?php

namespace Venezia\Modules\Pricing\Repositories;

use Venezia\Core\BaseRepository;
use Venezia\Core\Database;
use Venezia\Modules\Pricing\Models\SeasonalRate;

/**
 * Repository for seasonal rate CRUD and querying.
 */
class SeasonalRateRepository extends BaseRepository {

	protected string $table = 'seasonal_rates';
	protected string $model = SeasonalRate::class;

	public function __construct( Database $db ) {
		parent::__construct( $db );
	}

	/**
	 * Find a seasonal rate by ID.
	 */
	public function find( int $id ): ?SeasonalRate {
		$table = $this->tableName();
		$row   = $this->db->getRow(
			"SELECT * FROM {$table} WHERE id = %d",
			$id
		);

		return $row ? SeasonalRate::fromRow( $row ) : null;
	}

	/**
	 * Get active seasonal rates applicable to a specific date.
	 *
	 * Filters by room type, rate plan, date range, and day of week.
	 * Results are ordered by priority (highest first) so the caller
	 * can pick the most relevant one.
	 *
	 * @param int|null $roomTypeId Filter by room type (null = no filter).
	 * @param int|null $ratePlanId Filter by rate plan (null = no filter).
	 * @param string   $date       The target date (Y-m-d).
	 * @return SeasonalRate[]
	 */
	public function getActiveForDate( ?int $roomTypeId, ?int $ratePlanId, string $date ): array {
		$table      = $this->tableName();
		$conditions = [ "status = 'active'", "start_date <= %s", "end_date >= %s" ];
		$params     = [ $date, $date ];

		if ( $roomTypeId !== null ) {
			$conditions[] = '(room_type_id = %d OR room_type_id IS NULL)';
			$params[]     = $roomTypeId;
		}

		if ( $ratePlanId !== null ) {
			$conditions[] = '(rate_plan_id = %d OR rate_plan_id IS NULL)';
			$params[]     = $ratePlanId;
		}

		$where = implode( ' AND ', $conditions );
		$rows  = $this->db->getResults(
			"SELECT * FROM {$table} WHERE {$where} ORDER BY priority DESC",
			...$params
		);

		// Post-filter by day of week (ISO 1=Mon..7=Sun).
		$dayNumber = (int) ( new \DateTimeImmutable( $date ) )->format( 'N' );
		$results   = [];

		foreach ( SeasonalRate::fromRows( $rows ) as $rate ) {
			$daysOfWeek = $rate->days_of_week;
			if ( empty( $daysOfWeek ) || in_array( $dayNumber, $daysOfWeek, true ) ) {
				$results[] = $rate;
			}
		}

		return $results;
	}

	/**
	 * Get all active seasonal rates that overlap with a date range.
	 *
	 * Useful for pre-loading seasonal rates for a multi-night stay
	 * to avoid N+1 queries.
	 *
	 * @param int|null $roomTypeId Filter by room type (null = no filter).
	 * @param int|null $ratePlanId Filter by rate plan (null = no filter).
	 * @param string   $startDate  Range start (Y-m-d).
	 * @param string   $endDate    Range end (Y-m-d).
	 * @return SeasonalRate[]
	 */
	public function getForDateRange( ?int $roomTypeId, ?int $ratePlanId, string $startDate, string $endDate ): array {
		$table      = $this->tableName();
		$conditions = [
			"status = 'active'",
			"start_date <= %s",
			"end_date >= %s",
		];
		$params = [ $endDate, $startDate ];

		if ( $roomTypeId !== null ) {
			$conditions[] = '(room_type_id = %d OR room_type_id IS NULL)';
			$params[]     = $roomTypeId;
		}

		if ( $ratePlanId !== null ) {
			$conditions[] = '(rate_plan_id = %d OR rate_plan_id IS NULL)';
			$params[]     = $ratePlanId;
		}

		$where = implode( ' AND ', $conditions );
		$rows  = $this->db->getResults(
			"SELECT * FROM {$table} WHERE {$where} ORDER BY priority DESC",
			...$params
		);

		return SeasonalRate::fromRows( $rows );
	}

	/**
	 * Create a new seasonal rate.
	 *
	 * @return SeasonalRate|false
	 */
	public function create( array $data ): SeasonalRate|false {
		$now = current_time( 'mysql', true );
		$data['created_at'] = $data['created_at'] ?? $now;
		$data['updated_at'] = $data['updated_at'] ?? $now;

		$data = $this->prepareJsonFields( $data );

		$id = $this->db->insert( $this->table, $data );

		if ( $id === false ) {
			return false;
		}

		return $this->find( $id );
	}

	/**
	 * Update a seasonal rate by ID.
	 */
	public function update( int $id, array $data ): bool {
		$data['updated_at'] = current_time( 'mysql', true );
		$data = $this->prepareJsonFields( $data );

		return $this->db->update( $this->table, $data, [ 'id' => $id ] ) !== false;
	}

	/**
	 * Delete a seasonal rate by ID.
	 */
	public function delete( int $id ): bool {
		return $this->db->delete( $this->table, [ 'id' => $id ] ) !== false;
	}

	/**
	 * Get all seasonal rates for a specific room type (admin listing).
	 *
	 * @return SeasonalRate[]
	 */
	public function getForRoomType( int $roomTypeId ): array {
		$table = $this->tableName();
		$rows  = $this->db->getResults(
			"SELECT * FROM {$table}
			WHERE room_type_id = %d
			ORDER BY start_date ASC, priority DESC",
			$roomTypeId
		);

		return SeasonalRate::fromRows( $rows );
	}

	/**
	 * Get all seasonal rates ordered for admin listing.
	 *
	 * @return SeasonalRate[]
	 */
	public function getAllOrdered(): array {
		$table = $this->tableName();
		$rows  = $this->db->getResults(
			"SELECT * FROM {$table} ORDER BY start_date ASC, priority DESC"
		);

		return SeasonalRate::fromRows( $rows );
	}

	/**
	 * Encode JSON fields for database storage.
	 */
	private function prepareJsonFields( array $data ): array {
		if ( isset( $data['days_of_week'] ) && is_array( $data['days_of_week'] ) ) {
			$data['days_of_week'] = wp_json_encode( $data['days_of_week'] );
		}

		return $data;
	}
}
