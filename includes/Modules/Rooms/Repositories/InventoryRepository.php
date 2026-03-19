<?php

namespace Nozule\Modules\Rooms\Repositories;

use Nozule\Core\BaseRepository;
use Nozule\Core\Database;
use Nozule\Modules\Rooms\Models\RoomInventory;

/**
 * Repository for daily room inventory management.
 */
class InventoryRepository extends BaseRepository {

	protected string $table = 'room_inventory';
	protected string $model = RoomInventory::class;

	public function __construct( Database $db ) {
		parent::__construct( $db );
	}

	/**
	 * Get inventory for a specific room type on a specific date.
	 */
	public function getForDate( int $roomTypeId, string $date ): ?RoomInventory {
		$table = $this->tableName();
		$row   = $this->db->getRow(
			"SELECT * FROM {$table} WHERE room_type_id = %d AND date = %s",
			$roomTypeId,
			$date
		);

		return $row ? RoomInventory::fromRow( $row ) : null;
	}

	/**
	 * Get inventory for a room type across a date range.
	 *
	 * @return RoomInventory[]
	 */
	public function getForDateRange( int $roomTypeId, string $startDate, string $endDate ): array {
		$table = $this->tableName();
		$rows  = $this->db->getResults(
			"SELECT * FROM {$table}
			WHERE room_type_id = %d
			AND date >= %s
			AND date <= %s
			ORDER BY date ASC",
			$roomTypeId,
			$startDate,
			$endDate
		);

		return RoomInventory::fromRows( $rows );
	}

	/**
	 * Get inventory for all room types on a specific date.
	 *
	 * @return RoomInventory[]
	 */
	public function getAllForDate( string $date ): array {
		$table = $this->tableName();
		$rows  = $this->db->getResults(
			"SELECT * FROM {$table} WHERE date = %s ORDER BY room_type_id ASC",
			$date
		);

		return RoomInventory::fromRows( $rows );
	}

	/**
	 * Deduct rooms from inventory for a date range (atomically).
	 *
	 * Uses a SQL UPDATE with available_rooms >= quantity check
	 * to prevent overbooking.
	 *
	 * @return bool True if all dates were successfully deducted.
	 */
	public function deductRooms( int $roomTypeId, string $startDate, string $endDate, int $quantity = 1 ): bool {
		$table = $this->tableName();
		$now   = current_time( 'mysql', true );
		$expectedNights = $this->countNightsBetween( $startDate, $endDate );

		// Lock the inventory rows to prevent concurrent bookings from reading
		// stale availability (TOCTOU race condition). The caller MUST have an
		// active transaction for FOR UPDATE to be effective.
		$locked = $this->db->getResults(
			"SELECT id, available_rooms FROM {$table}
			 WHERE room_type_id = %d
			   AND date >= %s
			   AND date < %s
			 FOR UPDATE",
			$roomTypeId,
			$startDate,
			$endDate
		);

		// Verify we have inventory records for every night and all have enough rooms.
		if ( count( $locked ) < $expectedNights ) {
			return false;
		}

		foreach ( $locked as $row ) {
			if ( (int) $row->available_rooms < $quantity ) {
				return false;
			}
		}

		$result = $this->db->query(
			"UPDATE {$table}
			SET available_rooms = available_rooms - %d,
				booked_rooms = booked_rooms + %d,
				updated_at = %s
			WHERE room_type_id = %d
			AND date >= %s
			AND date < %s
			AND available_rooms >= %d
			AND stop_sell = 0",
			$quantity,
			$quantity,
			$now,
			$roomTypeId,
			$startDate,
			$endDate,
			$quantity
		);

		return $result !== false && (int) $result === $expectedNights;
	}

	/**
	 * Restore rooms to inventory for a date range.
	 *
	 * Used when a booking is cancelled or modified.
	 */
	public function restoreRooms( int $roomTypeId, string $startDate, string $endDate, int $quantity = 1 ): bool {
		$table = $this->tableName();
		$now   = current_time( 'mysql', true );

		$result = $this->db->query(
			"UPDATE {$table}
			SET available_rooms = LEAST(available_rooms + %d, total_rooms),
				booked_rooms = GREATEST(booked_rooms - %d, 0),
				updated_at = %s
			WHERE room_type_id = %d
			AND date >= %s
			AND date < %s",
			$quantity,
			$quantity,
			$now,
			$roomTypeId,
			$startDate,
			$endDate
		);

		return $result !== false;
	}

	/**
	 * Bulk update inventory for a room type across a date range.
	 *
	 * Supports updating price_override, stop_sell, min_stay, and total_rooms.
	 */
	public function bulkUpdate( int $roomTypeId, string $startDate, string $endDate, array $data ): bool {
		$table        = $this->tableName();
		$setClauses   = [];
		$values       = [];
		$now          = current_time( 'mysql', true );

		$allowedFields = [ 'total_rooms', 'available_rooms', 'price_override', 'stop_sell', 'min_stay' ];

		foreach ( $allowedFields as $field ) {
			if ( array_key_exists( $field, $data ) ) {
				$setClauses[] = "{$field} = %s";
				$values[]     = $data[ $field ];
			}
		}

		if ( empty( $setClauses ) ) {
			return false;
		}

		// If total_rooms changed, recalculate available_rooms.
		if ( isset( $data['total_rooms'] ) ) {
			$setClauses[] = 'available_rooms = GREATEST(%d - booked_rooms, 0)';
			$values[]     = (int) $data['total_rooms'];
		}

		$setClauses[] = 'updated_at = %s';
		$values[]     = $now;

		$setString = implode( ', ', $setClauses );

		$values[] = $roomTypeId;
		$values[] = $startDate;
		$values[] = $endDate;

		$result = $this->db->query(
			"UPDATE {$table} SET {$setString}
			WHERE room_type_id = %d AND date >= %s AND date <= %s",
			...$values
		);

		return $result !== false;
	}

	/**
	 * Initialize inventory rows for a room type over a date range.
	 *
	 * Creates rows for dates that do not already have inventory records.
	 */
	public function initializeInventory( int $roomTypeId, int $totalRooms, string $startDate, string $endDate ): int {
		$table   = $this->tableName();
		$now     = current_time( 'mysql', true );
		$created = 0;

		// Batch-fetch all existing dates in one query instead of N queries.
		$existingRecords = $this->getForDateRange( $roomTypeId, $startDate, $endDate );
		$existingDates   = [];
		foreach ( $existingRecords as $record ) {
			$existingDates[ $record->date ] = true;
		}

		// Collect missing dates for batch insert.
		$current = new \DateTimeImmutable( $startDate );
		$end     = new \DateTimeImmutable( $endDate );
		$missing = [];

		while ( $current <= $end ) {
			$dateStr = $current->format( 'Y-m-d' );
			if ( ! isset( $existingDates[ $dateStr ] ) ) {
				$missing[] = $dateStr;
			}
			$current = $current->modify( '+1 day' );
		}

		if ( empty( $missing ) ) {
			return 0;
		}

		// Batch insert in chunks to avoid overly long queries.
		$chunks = array_chunk( $missing, 50 );

		foreach ( $chunks as $chunk ) {
			$values     = [];
			$placeholders = [];

			foreach ( $chunk as $dateStr ) {
				$placeholders[] = '(%d, %s, %d, %d, 0, NULL, 0, 1, %s, %s)';
				$values[]       = $roomTypeId;
				$values[]       = $dateStr;
				$values[]       = $totalRooms;
				$values[]       = $totalRooms;
				$values[]       = $now;
				$values[]       = $now;
			}

			$sql = "INSERT INTO {$table}
				(room_type_id, date, total_rooms, available_rooms, booked_rooms, price_override, stop_sell, min_stay, created_at, updated_at)
				VALUES " . implode( ', ', $placeholders );

			$result = $this->db->query( $sql, ...$values );

			if ( $result !== false ) {
				$created += (int) $result;
			}
		}

		return $created;
	}

	/**
	 * Get inventory for multiple room types across a date range in one query.
	 *
	 * Returns results grouped by room_type_id for efficient batch processing.
	 *
	 * @param int[]  $roomTypeIds Room type IDs.
	 * @param string $startDate   Start date (Y-m-d).
	 * @param string $endDate     End date (Y-m-d).
	 * @return array<int, RoomInventory[]> Keyed by room_type_id.
	 */
	public function getForDateRangeBatch( array $roomTypeIds, string $startDate, string $endDate ): array {
		if ( empty( $roomTypeIds ) ) {
			return [];
		}

		$table        = $this->tableName();
		$placeholders = implode( ',', array_fill( 0, count( $roomTypeIds ), '%d' ) );
		$params       = array_merge( $roomTypeIds, [ $startDate, $endDate ] );

		$rows = $this->db->getResults(
			"SELECT * FROM {$table}
			 WHERE room_type_id IN ({$placeholders})
			   AND date >= %s
			   AND date <= %s
			 ORDER BY room_type_id ASC, date ASC",
			...$params
		);

		$grouped = [];
		foreach ( RoomInventory::fromRows( $rows ) as $inv ) {
			$grouped[ $inv->room_type_id ][] = $inv;
		}

		return $grouped;
	}

	/**
	 * Get the minimum available rooms for a room type across a date range.
	 *
	 * This is the bottleneck availability: the lowest number of rooms
	 * available on any single night in the range.
	 */
	public function getMinAvailability( int $roomTypeId, string $startDate, string $endDate ): int {
		$table = $this->tableName();

		$result = $this->db->getVar(
			"SELECT MIN(available_rooms) FROM {$table}
			WHERE room_type_id = %d
			AND date >= %s
			AND date < %s
			AND stop_sell = 0",
			$roomTypeId,
			$startDate,
			$endDate
		);

		return $result !== null ? (int) $result : 0;
	}

	/**
	 * Check whether any date in the range has stop_sell enabled.
	 */
	public function hasStopSell( int $roomTypeId, string $startDate, string $endDate ): bool {
		$table = $this->tableName();

		$count = $this->db->getVar(
			"SELECT COUNT(*) FROM {$table}
			WHERE room_type_id = %d
			AND date >= %s
			AND date < %s
			AND stop_sell = 1",
			$roomTypeId,
			$startDate,
			$endDate
		);

		return (int) $count > 0;
	}

	/**
	 * Count the number of nights between two dates.
	 */
	private function countNightsBetween( string $startDate, string $endDate ): int {
		$start = new \DateTimeImmutable( $startDate );
		$end   = new \DateTimeImmutable( $endDate );

		return (int) $start->diff( $end )->days;
	}
}
