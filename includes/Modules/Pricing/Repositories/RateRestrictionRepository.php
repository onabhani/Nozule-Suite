<?php

namespace Nozule\Modules\Pricing\Repositories;

use Nozule\Core\BaseRepository;
use Nozule\Core\Database;
use Nozule\Modules\Pricing\Models\RateRestriction;

/**
 * Repository for rate restriction CRUD and querying.
 */
class RateRestrictionRepository extends BaseRepository {

	protected string $table = 'rate_restrictions';
	protected string $model = RateRestriction::class;

	public function __construct( Database $db ) {
		parent::__construct( $db );
	}

	/**
	 * Find a rate restriction by ID.
	 *
	 * @param int $id The restriction ID.
	 * @return RateRestriction|null
	 */
	public function find( int $id ): ?RateRestriction {
		$table = $this->tableName();
		$row   = $this->db->getRow(
			"SELECT * FROM {$table} WHERE id = %d",
			$id
		);

		return $row ? RateRestriction::fromRow( $row ) : null;
	}

	/**
	 * Get all restrictions ordered by date_from DESC.
	 *
	 * @return RateRestriction[]
	 */
	public function getAll(): array {
		$table = $this->tableName();
		$rows  = $this->db->getResults(
			"SELECT * FROM {$table} ORDER BY date_from DESC"
		);

		return RateRestriction::fromRows( $rows );
	}

	/**
	 * Get active restrictions for a room type that overlap a given date range.
	 *
	 * A restriction overlaps when its date_from <= $dateTo AND date_to >= $dateFrom.
	 *
	 * @param int    $roomTypeId The room type ID.
	 * @param string $dateFrom   Range start (Y-m-d).
	 * @param string $dateTo     Range end (Y-m-d).
	 * @return RateRestriction[]
	 */
	public function getActiveForRoomType( int $roomTypeId, string $dateFrom, string $dateTo ): array {
		$table = $this->tableName();
		$rows  = $this->db->getResults(
			"SELECT * FROM {$table}
			WHERE room_type_id = %d
			  AND is_active = 1
			  AND date_from <= %s
			  AND date_to >= %s
			ORDER BY date_from DESC",
			$roomTypeId,
			$dateTo,
			$dateFrom
		);

		return RateRestriction::fromRows( $rows );
	}

	/**
	 * Get active restrictions of a specific type for a room type on a specific date.
	 *
	 * @param string $type       Restriction type (min_stay, max_stay, cta, ctd, stop_sell).
	 * @param int    $roomTypeId The room type ID.
	 * @param string $date       The target date (Y-m-d).
	 * @return RateRestriction[]
	 */
	public function getActiveByType( string $type, int $roomTypeId, string $date ): array {
		$table = $this->tableName();
		$rows  = $this->db->getResults(
			"SELECT * FROM {$table}
			WHERE restriction_type = %s
			  AND room_type_id = %d
			  AND is_active = 1
			  AND date_from <= %s
			  AND date_to >= %s
			ORDER BY date_from DESC",
			$type,
			$roomTypeId,
			$date,
			$date
		);

		return RateRestriction::fromRows( $rows );
	}

	/**
	 * Create a new rate restriction.
	 *
	 * @param array $data The restriction data.
	 * @return RateRestriction|false
	 */
	public function create( array $data ): RateRestriction|false {
		$now = current_time( 'mysql', true );
		$data['created_at'] = $data['created_at'] ?? $now;
		$data['updated_at'] = $data['updated_at'] ?? $now;

		$id = $this->db->insert( $this->table, $data );

		if ( $id === false ) {
			return false;
		}

		return $this->find( $id );
	}

	/**
	 * Update a rate restriction by ID.
	 *
	 * @param int   $id   The restriction ID.
	 * @param array $data The fields to update.
	 */
	public function update( int $id, array $data ): bool {
		$data['updated_at'] = current_time( 'mysql', true );

		return $this->db->update( $this->table, $data, [ 'id' => $id ] ) !== false;
	}

	/**
	 * Delete a rate restriction by ID.
	 *
	 * @param int $id The restriction ID.
	 */
	public function delete( int $id ): bool {
		return $this->db->delete( $this->table, [ 'id' => $id ] ) !== false;
	}
}
