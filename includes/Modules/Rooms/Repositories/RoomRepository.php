<?php

namespace Venezia\Modules\Rooms\Repositories;

use Venezia\Core\BaseRepository;
use Venezia\Core\Database;
use Venezia\Modules\Rooms\Models\Room;

/**
 * Repository for individual room CRUD and querying.
 */
class RoomRepository extends BaseRepository {

	protected string $table = 'rooms';
	protected string $model = Room::class;

	public function __construct( Database $db ) {
		parent::__construct( $db );
	}

	/**
	 * Find a room by its room number.
	 */
	public function findByNumber( string $roomNumber ): ?Room {
		$table = $this->tableName();
		$row   = $this->db->getRow(
			"SELECT * FROM {$table} WHERE room_number = %s",
			$roomNumber
		);

		return $row ? Room::fromRow( $row ) : null;
	}

	/**
	 * Get all rooms for a given room type.
	 *
	 * @return Room[]
	 */
	public function getByRoomType( int $roomTypeId ): array {
		$table = $this->tableName();
		$rows  = $this->db->getResults(
			"SELECT * FROM {$table} WHERE room_type_id = %d ORDER BY room_number ASC",
			$roomTypeId
		);

		return Room::fromRows( $rows );
	}

	/**
	 * Get rooms filtered by status.
	 *
	 * @return Room[]
	 */
	public function getByStatus( string $status ): array {
		$table = $this->tableName();
		$rows  = $this->db->getResults(
			"SELECT * FROM {$table} WHERE status = %s ORDER BY room_number ASC",
			$status
		);

		return Room::fromRows( $rows );
	}

	/**
	 * Get rooms available for booking for a specific room type.
	 *
	 * Returns rooms that are in the "available" status and not currently
	 * assigned to an active booking in the given date range.
	 *
	 * @return Room[]
	 */
	public function getAvailableForBooking( int $roomTypeId, string $checkIn, string $checkOut ): array {
		$rooms_table    = $this->tableName();
		$bookings_table = $this->db->table( 'bookings' );

		$rows = $this->db->getResults(
			"SELECT r.* FROM {$rooms_table} r
			WHERE r.room_type_id = %d
			AND r.status = 'available'
			AND r.id NOT IN (
				SELECT b.room_id FROM {$bookings_table} b
				WHERE b.room_id IS NOT NULL
				AND b.room_type_id = %d
				AND b.status IN ('confirmed', 'checked_in')
				AND b.check_in < %s
				AND b.check_out > %s
			)
			ORDER BY r.room_number ASC",
			$roomTypeId,
			$roomTypeId,
			$checkOut,
			$checkIn
		);

		return Room::fromRows( $rows );
	}

	/**
	 * Create a new room.
	 *
	 * @return Room|false
	 */
	public function create( array $data ): Room|false {
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
	 * Update a room.
	 */
	public function update( int $id, array $data ): bool {
		$data['updated_at'] = current_time( 'mysql', true );

		return $this->db->update( $this->table, $data, [ 'id' => $id ] ) !== false;
	}

	/**
	 * Update only the status of a room.
	 */
	public function updateStatus( int $id, string $status ): bool {
		return $this->update( $id, [ 'status' => $status ] );
	}

	/**
	 * Check whether a room number is unique (optionally excluding a given ID).
	 */
	public function isRoomNumberUnique( string $roomNumber, ?int $excludeId = null ): bool {
		$table = $this->tableName();

		if ( $excludeId ) {
			$count = $this->db->getVar(
				"SELECT COUNT(*) FROM {$table} WHERE room_number = %s AND id != %d",
				$roomNumber,
				$excludeId
			);
		} else {
			$count = $this->db->getVar(
				"SELECT COUNT(*) FROM {$table} WHERE room_number = %s",
				$roomNumber
			);
		}

		return (int) $count === 0;
	}

	/**
	 * Count rooms by room type and status.
	 */
	public function countByTypeAndStatus( int $roomTypeId, ?string $status = null ): int {
		$table = $this->tableName();

		if ( $status ) {
			return (int) $this->db->getVar(
				"SELECT COUNT(*) FROM {$table} WHERE room_type_id = %d AND status = %s",
				$roomTypeId,
				$status
			);
		}

		return (int) $this->db->getVar(
			"SELECT COUNT(*) FROM {$table} WHERE room_type_id = %d",
			$roomTypeId
		);
	}

	/**
	 * Get all rooms with their room type information.
	 *
	 * @return Room[]
	 */
	public function getAllWithType(): array {
		$rooms_table = $this->tableName();
		$types_table = $this->db->table( 'room_types' );

		$rows = $this->db->getResults(
			"SELECT r.*, rt.name AS room_type_name, rt.slug AS room_type_slug
			FROM {$rooms_table} r
			LEFT JOIN {$types_table} rt ON r.room_type_id = rt.id
			ORDER BY r.room_number ASC"
		);

		return Room::fromRows( $rows );
	}
}
