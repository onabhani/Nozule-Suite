<?php

namespace Venezia\Modules\Rooms\Repositories;

use Venezia\Core\BaseRepository;
use Venezia\Core\Database;
use Venezia\Modules\Rooms\Models\RoomType;

/**
 * Repository for room type CRUD and querying.
 */
class RoomTypeRepository extends BaseRepository {

	protected string $table = 'room_types';
	protected string $model = RoomType::class;

	public function __construct( Database $db ) {
		parent::__construct( $db );
	}

	/**
	 * Find a room type by its slug.
	 */
	public function findBySlug( string $slug ): ?RoomType {
		$table = $this->tableName();
		$row   = $this->db->getRow(
			"SELECT * FROM {$table} WHERE slug = %s",
			$slug
		);

		return $row ? RoomType::fromRow( $row ) : null;
	}

	/**
	 * Get all active room types ordered by sort_order.
	 *
	 * @return RoomType[]
	 */
	public function getActive(): array {
		$table = $this->tableName();
		$rows  = $this->db->getResults(
			"SELECT * FROM {$table} WHERE status = 'active' ORDER BY sort_order ASC, name ASC"
		);

		return RoomType::fromRows( $rows );
	}

	/**
	 * Get all room types (active and inactive) ordered by sort_order.
	 *
	 * @return RoomType[]
	 */
	public function getAllOrdered(): array {
		$table = $this->tableName();
		$rows  = $this->db->getResults(
			"SELECT * FROM {$table} ORDER BY sort_order ASC, name ASC"
		);

		return RoomType::fromRows( $rows );
	}

	/**
	 * Create a new room type.
	 *
	 * Encodes JSON fields before inserting.
	 *
	 * @return RoomType|false
	 */
	public function create( array $data ): RoomType|false {
		$data = $this->prepareJsonFields( $data );

		if ( ! isset( $data['sort_order'] ) ) {
			$data['sort_order'] = $this->getNextSortOrder();
		}

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
	 * Update a room type.
	 *
	 * Encodes JSON fields before updating.
	 */
	public function update( int $id, array $data ): bool {
		$data = $this->prepareJsonFields( $data );
		$data['updated_at'] = current_time( 'mysql', true );

		return $this->db->update( $this->table, $data, [ 'id' => $id ] ) !== false;
	}

	/**
	 * Soft-delete a room type by setting its status to inactive.
	 *
	 * Hard deletion is prevented if rooms are still associated.
	 */
	public function delete( int $id ): bool {
		return $this->db->delete( $this->table, [ 'id' => $id ] ) !== false;
	}

	/**
	 * Reorder room types.
	 *
	 * @param int[] $orderedIds List of room type IDs in their new sort order.
	 */
	public function reorder( array $orderedIds ): bool {
		$this->beginTransaction();

		try {
			$table = $this->tableName();
			$now   = current_time( 'mysql', true );

			foreach ( $orderedIds as $position => $id ) {
				$result = $this->db->query(
					"UPDATE {$table} SET sort_order = %d, updated_at = %s WHERE id = %d",
					$position,
					$now,
					(int) $id
				);

				if ( $result === false ) {
					$this->rollback();
					return false;
				}
			}

			$this->commit();
			return true;
		} catch ( \Throwable $e ) {
			$this->rollback();
			return false;
		}
	}

	/**
	 * Check whether a slug is unique (optionally excluding a given ID).
	 */
	public function isSlugUnique( string $slug, ?int $excludeId = null ): bool {
		$table = $this->tableName();

		if ( $excludeId ) {
			$count = $this->db->getVar(
				"SELECT COUNT(*) FROM {$table} WHERE slug = %s AND id != %d",
				$slug,
				$excludeId
			);
		} else {
			$count = $this->db->getVar(
				"SELECT COUNT(*) FROM {$table} WHERE slug = %s",
				$slug
			);
		}

		return (int) $count === 0;
	}

	/**
	 * Get the number of rooms associated with a room type.
	 */
	public function getRoomCount( int $roomTypeId ): int {
		$rooms_table = $this->db->table( 'rooms' );

		return (int) $this->db->getVar(
			"SELECT COUNT(*) FROM {$rooms_table} WHERE room_type_id = %d",
			$roomTypeId
		);
	}

	/**
	 * Get the next sort order value.
	 */
	private function getNextSortOrder(): int {
		$table   = $this->tableName();
		$maxSort = $this->db->getVar( "SELECT MAX(sort_order) FROM {$table}" );

		return ( (int) $maxSort ) + 1;
	}

	/**
	 * Encode array fields to JSON strings for database storage.
	 */
	private function prepareJsonFields( array $data ): array {
		foreach ( [ 'amenities', 'images' ] as $field ) {
			if ( isset( $data[ $field ] ) && is_array( $data[ $field ] ) ) {
				$data[ $field ] = wp_json_encode( $data[ $field ] );
			}
		}

		return $data;
	}
}
