<?php

namespace Nozule\Modules\Property\Repositories;

use Nozule\Core\BaseRepository;
use Nozule\Core\Database;
use Nozule\Modules\Property\Models\Property;

/**
 * Repository for property database operations.
 */
class PropertyRepository extends BaseRepository {

	protected string $table = 'properties';
	protected string $model = Property::class;

	public function __construct( Database $db ) {
		parent::__construct( $db );
	}

	/**
	 * Get the current property (single-property mode).
	 *
	 * Returns the first active property. When multi-property (NZL-019)
	 * is implemented this will accept a property_id parameter.
	 */
	public function getCurrent(): ?Property {
		$table = $this->tableName();
		$row   = $this->db->getRow(
			"SELECT * FROM {$table} WHERE status = 'active' ORDER BY id ASC LIMIT 1"
		);

		return $row ? Property::fromRow( $row ) : null;
	}

	/**
	 * Find a property by its public property_id (UUID).
	 */
	public function findByPropertyId( string $propertyId ): ?Property {
		$table = $this->tableName();
		$row   = $this->db->getRow(
			"SELECT * FROM {$table} WHERE property_id = %s",
			$propertyId
		);

		return $row ? Property::fromRow( $row ) : null;
	}

	/**
	 * Find a property by slug.
	 */
	public function findBySlug( string $slug ): ?Property {
		$table = $this->tableName();
		$row   = $this->db->getRow(
			"SELECT * FROM {$table} WHERE slug = %s",
			$slug
		);

		return $row ? Property::fromRow( $row ) : null;
	}

	/**
	 * Get all properties.
	 *
	 * @return Property[]
	 */
	public function getAll(): array {
		$table = $this->tableName();
		$rows  = $this->db->getResults(
			"SELECT * FROM {$table} ORDER BY id ASC"
		);

		return Property::fromRows( $rows );
	}

	/**
	 * Create a new property.
	 *
	 * @return Property|false
	 */
	public function create( array $data ): Property|false {
		$now = current_time( 'mysql', true );
		$data['created_at'] = $data['created_at'] ?? $now;
		$data['updated_at'] = $data['updated_at'] ?? $now;

		// Generate property_id if not provided.
		if ( empty( $data['property_id'] ) ) {
			$data['property_id'] = wp_generate_uuid4();
		}

		// Encode JSON fields.
		foreach ( [ 'photos', 'facilities', 'policies', 'social_links' ] as $field ) {
			if ( isset( $data[ $field ] ) && is_array( $data[ $field ] ) ) {
				$data[ $field ] = wp_json_encode( $data[ $field ] );
			}
		}

		$id = $this->db->insert( $this->table, $data );
		if ( $id === false ) {
			return false;
		}

		return $this->find( $id );
	}

	/**
	 * Update a property.
	 */
	public function update( int $id, array $data ): bool {
		$data['updated_at'] = current_time( 'mysql', true );

		// Encode JSON fields.
		foreach ( [ 'photos', 'facilities', 'policies', 'social_links' ] as $field ) {
			if ( isset( $data[ $field ] ) && is_array( $data[ $field ] ) ) {
				$data[ $field ] = wp_json_encode( $data[ $field ] );
			}
		}

		return $this->db->update( $this->table, $data, [ 'id' => $id ] ) !== false;
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
}
