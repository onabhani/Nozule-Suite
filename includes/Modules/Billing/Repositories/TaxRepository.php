<?php

namespace Nozule\Modules\Billing\Repositories;

use Nozule\Core\BaseRepository;
use Nozule\Core\Database;
use Nozule\Modules\Billing\Models\Tax;

/**
 * Repository for tax CRUD and querying.
 */
class TaxRepository extends BaseRepository {

	protected string $table = 'taxes';
	protected string $model = Tax::class;

	public function __construct( Database $db ) {
		parent::__construct( $db );
	}

	/**
	 * Get all active taxes ordered by sort_order.
	 *
	 * @return Tax[]
	 */
	public function getActive(): array {
		$table = $this->tableName();
		$rows  = $this->db->getResults(
			"SELECT * FROM {$table} WHERE is_active = 1 ORDER BY sort_order ASC, name ASC"
		);

		return Tax::fromRows( $rows );
	}

	/**
	 * Get all taxes ordered by sort_order.
	 *
	 * @return Tax[]
	 */
	public function getAll(): array {
		$table = $this->tableName();
		$rows  = $this->db->getResults(
			"SELECT * FROM {$table} ORDER BY sort_order ASC, name ASC"
		);

		return Tax::fromRows( $rows );
	}

	/**
	 * Get taxes filtered by their applies_to category.
	 *
	 * @return Tax[]
	 */
	public function getByAppliesTo( string $appliesTo ): array {
		$table = $this->tableName();
		$rows  = $this->db->getResults(
			"SELECT * FROM {$table} WHERE is_active = 1 AND (applies_to = %s OR applies_to = 'all') ORDER BY sort_order ASC",
			$appliesTo
		);

		return Tax::fromRows( $rows );
	}

	/**
	 * Create a new tax.
	 *
	 * @return Tax|false
	 */
	public function create( array $data ): Tax|false {
		$now = current_time( 'mysql', true );
		$data['created_at'] = $data['created_at'] ?? $now;
		$data['updated_at'] = $data['updated_at'] ?? $now;

		if ( ! isset( $data['sort_order'] ) ) {
			$data['sort_order'] = $this->getNextSortOrder();
		}

		$id = $this->db->insert( $this->table, $data );
		if ( $id === false ) {
			return false;
		}

		return $this->find( $id );
	}

	/**
	 * Update a tax.
	 */
	public function update( int $id, array $data ): bool {
		$data['updated_at'] = current_time( 'mysql', true );

		return $this->db->update( $this->table, $data, [ 'id' => $id ] ) !== false;
	}

	/**
	 * Get the next sort order value.
	 */
	private function getNextSortOrder(): int {
		$table   = $this->tableName();
		$maxSort = $this->db->getVar( "SELECT MAX(sort_order) FROM {$table}" );

		return ( (int) $maxSort ) + 1;
	}
}
