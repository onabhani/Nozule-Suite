<?php

namespace Nozule\Modules\Groups\Repositories;

use Nozule\Core\BaseRepository;
use Nozule\Core\Database;
use Nozule\Modules\Groups\Models\GroupBookingRoom;

/**
 * Repository for group booking room allocation database operations.
 */
class GroupBookingRoomRepository extends BaseRepository {

	protected string $table = 'group_booking_rooms';
	protected string $model = GroupBookingRoom::class;

	public function __construct( Database $db ) {
		parent::__construct( $db );
	}

	// ── CRUD ────────────────────────────────────────────────────────

	/**
	 * Create a new group booking room allocation.
	 *
	 * @return GroupBookingRoom|false
	 */
	public function create( array $data ): GroupBookingRoom|false {
		$now                = current_time( 'mysql' );
		$data['created_at'] = $data['created_at'] ?? $now;
		$data['updated_at'] = $data['updated_at'] ?? $now;

		$id = $this->db->insert( $this->table, $data );

		if ( $id === false ) {
			return false;
		}

		return $this->find( $id );
	}

	/**
	 * Update a group booking room allocation by ID.
	 */
	public function update( int $id, array $data ): bool {
		$data['updated_at'] = current_time( 'mysql' );

		return $this->db->update( $this->table, $data, [ 'id' => $id ] ) !== false;
	}

	// ── Finders ─────────────────────────────────────────────────────

	/**
	 * Get all room allocations for a group booking.
	 *
	 * @return GroupBookingRoom[]
	 */
	public function getByGroupBooking( int $groupBookingId ): array {
		$table = $this->tableName();
		$rows  = $this->db->getResults(
			"SELECT * FROM {$table} WHERE group_booking_id = %d ORDER BY id ASC",
			$groupBookingId
		);

		return GroupBookingRoom::fromRows( $rows );
	}

	/**
	 * Get all room allocations for a group booking with room and room type details.
	 *
	 * Joins rooms and room_types tables for room_number and type name.
	 *
	 * @return array Raw associative arrays with joined data.
	 */
	public function getByGroupWithDetails( int $groupBookingId ): array {
		$table      = $this->tableName();
		$rooms      = $this->db->table( 'rooms' );
		$roomTypes  = $this->db->table( 'room_types' );

		$rows = $this->db->getResults(
			"SELECT gbr.*,
			        r.room_number,
			        rt.name AS room_type_name,
			        rt.name_ar AS room_type_name_ar
			 FROM {$table} gbr
			 LEFT JOIN {$rooms} r ON gbr.room_id = r.id
			 LEFT JOIN {$roomTypes} rt ON gbr.room_type_id = rt.id
			 WHERE gbr.group_booking_id = %d
			 ORDER BY gbr.id ASC",
			$groupBookingId
		);

		return array_map( fn( $row ) => (array) $row, $rows );
	}

	// ── Counting ────────────────────────────────────────────────────

	/**
	 * Count room allocations for a group booking, optionally filtered by status.
	 */
	public function countByGroupAndStatus( int $groupBookingId, ?string $status = null ): int {
		$table  = $this->tableName();
		$params = [ $groupBookingId ];

		$statusClause = '';
		if ( $status !== null ) {
			$statusClause = ' AND status = %s';
			$params[]     = $status;
		}

		return (int) $this->db->getVar(
			"SELECT COUNT(*) FROM {$table} WHERE group_booking_id = %d{$statusClause}",
			...$params
		);
	}

	// ── Status Updates ──────────────────────────────────────────────

	/**
	 * Update the status of a room allocation.
	 */
	public function updateStatus( int $id, string $status ): bool {
		return $this->update( $id, [ 'status' => $status ] );
	}

	/**
	 * Bulk update status for all rooms in a group booking.
	 *
	 * @param int    $groupBookingId Group booking ID.
	 * @param string $newStatus      New status to set.
	 * @param string|null $fromStatus Only update rooms with this current status (optional).
	 */
	public function bulkUpdateStatus( int $groupBookingId, string $newStatus, ?string $fromStatus = null ): int {
		$table  = $this->tableName();
		$now    = current_time( 'mysql' );
		$params = [ $newStatus, $now, $groupBookingId ];

		$fromClause = '';
		if ( $fromStatus !== null ) {
			$fromClause = ' AND status = %s';
			$params[]   = $fromStatus;
		}

		return (int) $this->db->query(
			"UPDATE {$table} SET status = %s, updated_at = %s WHERE group_booking_id = %d{$fromClause}",
			...$params
		);
	}

	// ── Deletion ────────────────────────────────────────────────────

	/**
	 * Delete all room allocations for a group booking.
	 */
	public function deleteByGroup( int $groupBookingId ): bool {
		$table = $this->tableName();

		return $this->db->query(
			"DELETE FROM {$table} WHERE group_booking_id = %d",
			$groupBookingId
		) !== false;
	}
}
