<?php

namespace Venezia\Modules\Rooms\Repositories;

use Venezia\Core\BaseRepository;
use Venezia\Core\Database;
use Venezia\Modules\Rooms\Models\RoomInventory;

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

		// Verify that every night in the range was updated.
		$expectedNights = $this->countNightsBetween( $startDate, $endDate );

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

		$current = new \DateTimeImmutable( $startDate );
		$end     = new \DateTimeImmutable( $endDate );

		while ( $current <= $end ) {
			$dateStr  = $current->format( 'Y-m-d' );
			$existing = $this->getForDate( $roomTypeId, $dateStr );

			if ( ! $existing ) {
				$id = $this->db->insert( $this->table, [
					'room_type_id'    => $roomTypeId,
					'date'            => $dateStr,
					'total_rooms'     => $totalRooms,
					'available_rooms' => $totalRooms,
					'booked_rooms'    => 0,
					'price_override'  => null,
					'stop_sell'       => 0,
					'min_stay'        => 1,
					'created_at'      => $now,
					'updated_at'      => $now,
				] );

				if ( $id !== false ) {
					$created++;
				}
			}

			$current = $current->modify( '+1 day' );
		}

		return $created;
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
