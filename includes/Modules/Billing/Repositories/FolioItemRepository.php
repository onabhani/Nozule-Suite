<?php

namespace Nozule\Modules\Billing\Repositories;

use Nozule\Core\BaseRepository;
use Nozule\Core\Database;
use Nozule\Modules\Billing\Models\FolioItem;

/**
 * Repository for folio item CRUD and querying.
 */
class FolioItemRepository extends BaseRepository {

	protected string $table = 'folio_items';
	protected string $model = FolioItem::class;

	public function __construct( Database $db ) {
		parent::__construct( $db );
	}

	/**
	 * Get all items for a folio, ordered by date and created_at.
	 *
	 * @return FolioItem[]
	 */
	public function getByFolio( int $folioId ): array {
		$table = $this->tableName();
		$rows  = $this->db->getResults(
			"SELECT * FROM {$table} WHERE folio_id = %d ORDER BY date ASC, created_at ASC",
			$folioId
		);

		return FolioItem::fromRows( $rows );
	}

	/**
	 * Create a new folio item.
	 *
	 * Handles JSON encoding of tax_json if provided as array, and sets timestamps.
	 *
	 * @return FolioItem|false
	 */
	public function create( array $data ): FolioItem|false {
		$now = current_time( 'mysql', true );
		$data['created_at'] = $data['created_at'] ?? $now;

		// JSON-encode tax_json if it is an array.
		if ( isset( $data['tax_json'] ) && is_array( $data['tax_json'] ) ) {
			$data['tax_json'] = wp_json_encode( $data['tax_json'] );
		}

		$id = $this->db->insert( $this->table, $data );
		if ( $id === false ) {
			return false;
		}

		return $this->find( $id );
	}

	/**
	 * Delete all items for a folio.
	 */
	public function deleteByFolio( int $folioId ): bool {
		$table = $this->tableName();

		return $this->db->query(
			"DELETE FROM {$table} WHERE folio_id = %d",
			$folioId
		) !== false;
	}

	/**
	 * Get items for a folio filtered by category.
	 *
	 * @return FolioItem[]
	 */
	public function getByFolioAndCategory( int $folioId, string $category ): array {
		$table = $this->tableName();
		$rows  = $this->db->getResults(
			"SELECT * FROM {$table} WHERE folio_id = %d AND category = %s ORDER BY date ASC, created_at ASC",
			$folioId,
			$category
		);

		return FolioItem::fromRows( $rows );
	}
}
