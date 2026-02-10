<?php

namespace Venezia\Modules\Pricing\Repositories;

use Venezia\Core\BaseRepository;
use Venezia\Core\Database;
use Venezia\Modules\Pricing\Models\RatePlan;

/**
 * Repository for rate plan CRUD and querying.
 */
class RatePlanRepository extends BaseRepository {

	protected string $table = 'rate_plans';
	protected string $model = RatePlan::class;

	public function __construct( Database $db ) {
		parent::__construct( $db );
	}

	/**
	 * Find a rate plan by ID.
	 */
	public function find( int $id ): ?RatePlan {
		$table = $this->tableName();
		$row   = $this->db->getRow(
			"SELECT * FROM {$table} WHERE id = %d",
			$id
		);

		return $row ? RatePlan::fromRow( $row ) : null;
	}

	/**
	 * Find a rate plan by its unique code.
	 */
	public function findByCode( string $code ): ?RatePlan {
		$table = $this->tableName();
		$row   = $this->db->getRow(
			"SELECT * FROM {$table} WHERE code = %s",
			$code
		);

		return $row ? RatePlan::fromRow( $row ) : null;
	}

	/**
	 * Get all active rate plans, ordered by default flag then name.
	 *
	 * @return RatePlan[]
	 */
	public function getActive(): array {
		$table = $this->tableName();
		$rows  = $this->db->getResults(
			"SELECT * FROM {$table}
			WHERE status = 'active'
			ORDER BY is_default DESC, name ASC"
		);

		return RatePlan::fromRows( $rows );
	}

	/**
	 * Get the default rate plan for a specific room type.
	 *
	 * Looks for rate plans that are marked as default and apply to the
	 * given room type (either specifically or globally via NULL room_type_id).
	 * Prefers a room-type-specific plan over a global one.
	 * Falls back to the first active plan for the room type.
	 */
	public function getDefaultForRoomType( int $roomTypeId ): ?RatePlan {
		$table = $this->tableName();

		// First try to find a plan explicitly marked as default for this room type.
		$row = $this->db->getRow(
			"SELECT * FROM {$table}
			WHERE status = 'active'
			AND is_default = 1
			AND (room_type_id = %d OR room_type_id IS NULL)
			ORDER BY
				CASE WHEN room_type_id = %d THEN 0 ELSE 1 END ASC,
				name ASC
			LIMIT 1",
			$roomTypeId,
			$roomTypeId
		);

		if ( $row ) {
			return RatePlan::fromRow( $row );
		}

		// Fallback: first active plan applicable to this room type.
		$row = $this->db->getRow(
			"SELECT * FROM {$table}
			WHERE status = 'active'
			AND (room_type_id = %d OR room_type_id IS NULL)
			ORDER BY
				CASE WHEN room_type_id = %d THEN 0 ELSE 1 END ASC,
				name ASC
			LIMIT 1",
			$roomTypeId,
			$roomTypeId
		);

		return $row ? RatePlan::fromRow( $row ) : null;
	}

	/**
	 * Get all rate plans applicable to a specific room type.
	 *
	 * Includes plans specifically assigned to this room type and
	 * global plans (room_type_id IS NULL).
	 *
	 * @return RatePlan[]
	 */
	public function getForRoomType( int $roomTypeId ): array {
		$table = $this->tableName();
		$rows  = $this->db->getResults(
			"SELECT * FROM {$table}
			WHERE status = 'active'
			AND (room_type_id = %d OR room_type_id IS NULL)
			ORDER BY is_default DESC, name ASC",
			$roomTypeId
		);

		return RatePlan::fromRows( $rows );
	}

	/**
	 * Create a new rate plan.
	 *
	 * @return RatePlan|false
	 */
	public function create( array $data ): RatePlan|false {
		$now = current_time( 'mysql', true );
		$data['created_at'] = $data['created_at'] ?? $now;
		$data['updated_at'] = $data['updated_at'] ?? $now;

		$data = $this->prepareBoolFields( $data );

		$id = $this->db->insert( $this->table, $data );

		if ( $id === false ) {
			return false;
		}

		return $this->find( $id );
	}

	/**
	 * Update a rate plan by ID.
	 */
	public function update( int $id, array $data ): bool {
		$data['updated_at'] = current_time( 'mysql', true );
		$data = $this->prepareBoolFields( $data );

		return $this->db->update( $this->table, $data, [ 'id' => $id ] ) !== false;
	}

	/**
	 * Delete a rate plan by ID.
	 */
	public function delete( int $id ): bool {
		return $this->db->delete( $this->table, [ 'id' => $id ] ) !== false;
	}

	/**
	 * Check whether a code is unique (optionally excluding a given ID).
	 */
	public function isCodeUnique( string $code, ?int $excludeId = null ): bool {
		$table = $this->tableName();

		if ( $excludeId ) {
			$count = $this->db->getVar(
				"SELECT COUNT(*) FROM {$table} WHERE code = %s AND id != %d",
				$code,
				$excludeId
			);
		} else {
			$count = $this->db->getVar(
				"SELECT COUNT(*) FROM {$table} WHERE code = %s",
				$code
			);
		}

		return (int) $count === 0;
	}

	/**
	 * Get all rate plans (including inactive) ordered for admin listing.
	 *
	 * @return RatePlan[]
	 */
	public function getAllOrdered(): array {
		$table = $this->tableName();
		$rows  = $this->db->getResults(
			"SELECT * FROM {$table} ORDER BY is_default DESC, name ASC"
		);

		return RatePlan::fromRows( $rows );
	}

	/**
	 * Ensure boolean fields are stored as integer 1/0 for the database.
	 */
	private function prepareBoolFields( array $data ): array {
		$boolFields = [ 'is_refundable', 'is_default' ];

		foreach ( $boolFields as $field ) {
			if ( array_key_exists( $field, $data ) ) {
				$data[ $field ] = $data[ $field ] ? 1 : 0;
			}
		}

		return $data;
	}
}
