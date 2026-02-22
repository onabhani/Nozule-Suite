<?php

namespace Nozule\Modules\Branding\Repositories;

use Nozule\Core\BaseRepository;
use Nozule\Modules\Branding\Models\Brand;

/**
 * Repository for brand database operations.
 */
class BrandRepository extends BaseRepository {

	protected string $table = 'brands';
	protected string $model = Brand::class;

	/**
	 * Get all brands, ordered by default status then name.
	 *
	 * @return Brand[]
	 */
	public function getBrands(): array {
		$table = $this->tableName();
		$rows  = $this->db->getResults(
			"SELECT * FROM {$table} ORDER BY is_default DESC, name ASC"
		);

		return Brand::fromRows( $rows );
	}

	/**
	 * Get a single brand by ID.
	 */
	public function getBrand( int $id ): ?Brand {
		return $this->find( $id );
	}

	/**
	 * Get the currently active default brand.
	 */
	public function getActiveBrand(): ?Brand {
		$table = $this->tableName();
		$row   = $this->db->getRow(
			"SELECT * FROM {$table} WHERE is_default = 1 AND is_active = 1 LIMIT 1"
		);

		return $row ? Brand::fromRow( $row ) : null;
	}

	/**
	 * Save (create or update) a brand record.
	 *
	 * @param array $data Brand data.
	 * @return Brand|false Brand on success, false on failure.
	 */
	public function saveBrand( array $data ) {
		if ( ! empty( $data['id'] ) ) {
			$id = (int) $data['id'];
			unset( $data['id'] );
			$data['updated_at'] = current_time( 'mysql' );

			$success = $this->update( $id, $data );
			if ( ! $success ) {
				return false;
			}

			return $this->find( $id );
		}

		$data['created_at'] = $data['created_at'] ?? current_time( 'mysql' );
		$data['updated_at'] = $data['updated_at'] ?? current_time( 'mysql' );

		$id = $this->db->insert( $this->table, $data );

		if ( $id === false ) {
			return false;
		}

		return $this->find( $id );
	}

	/**
	 * Delete a brand by ID.
	 */
	public function deleteBrand( int $id ): bool {
		return $this->delete( $id );
	}

	/**
	 * Set a brand as the default, unsetting all others.
	 *
	 * @param int $id The brand ID to set as default.
	 */
	public function setDefault( int $id ): bool {
		$table = $this->tableName();
		$now   = current_time( 'mysql' );

		// Unset all current defaults.
		$this->db->query(
			"UPDATE {$table} SET is_default = 0, updated_at = %s WHERE is_default = 1",
			$now
		);

		// Set the specified brand as default and ensure it is active.
		$result = $this->db->query(
			"UPDATE {$table} SET is_default = 1, is_active = 1, updated_at = %s WHERE id = %d",
			$now,
			$id
		);

		return $result !== false;
	}

	/**
	 * Get all active brands.
	 *
	 * @return Brand[]
	 */
	public function getActiveBrands(): array {
		$table = $this->tableName();
		$rows  = $this->db->getResults(
			"SELECT * FROM {$table} WHERE is_active = 1 ORDER BY is_default DESC, name ASC"
		);

		return Brand::fromRows( $rows );
	}
}
