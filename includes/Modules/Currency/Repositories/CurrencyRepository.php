<?php

namespace Nozule\Modules\Currency\Repositories;

use Nozule\Core\BaseRepository;
use Nozule\Modules\Currency\Models\Currency;

/**
 * Repository for currency database operations.
 */
class CurrencyRepository extends BaseRepository {

	protected string $table = 'currencies';
	protected string $model = Currency::class;

	/**
	 * Find a currency by its ISO code.
	 *
	 * @param string $code 3-character ISO currency code.
	 */
	public function findByCode( string $code ): ?Currency {
		$table = $this->tableName();
		$row   = $this->db->getRow(
			"SELECT * FROM {$table} WHERE code = %s LIMIT 1",
			strtoupper( $code )
		);

		return $row ? Currency::fromRow( $row ) : null;
	}

	/**
	 * Get the default currency.
	 */
	public function getDefault(): ?Currency {
		$table = $this->tableName();
		$row   = $this->db->getRow(
			"SELECT * FROM {$table} WHERE is_default = 1 LIMIT 1"
		);

		return $row ? Currency::fromRow( $row ) : null;
	}

	/**
	 * Get all active currencies ordered by sort_order.
	 *
	 * @return Currency[]
	 */
	public function getActive(): array {
		$table = $this->tableName();
		$rows  = $this->db->getResults(
			"SELECT * FROM {$table} WHERE is_active = 1 ORDER BY sort_order ASC, code ASC"
		);

		return Currency::fromRows( $rows );
	}

	/**
	 * Set a currency as the default, unsetting all others.
	 *
	 * @param int $id The ID of the currency to set as default.
	 */
	public function setDefault( int $id ): bool {
		$table = $this->tableName();

		// Unset current default(s).
		$this->db->query(
			"UPDATE {$table} SET is_default = 0 WHERE is_default = 1"
		);

		// Set the new default.
		$result = $this->db->query(
			"UPDATE {$table} SET is_default = 1, updated_at = %s WHERE id = %d",
			current_time( 'mysql' ),
			$id
		);

		return $result !== false;
	}

	/**
	 * Create a new currency record.
	 *
	 * @return Currency|false
	 */
	public function create( array $data ): Currency|false {
		$data['created_at'] = $data['created_at'] ?? current_time( 'mysql' );
		$data['updated_at'] = $data['updated_at'] ?? current_time( 'mysql' );

		$id = $this->db->insert( $this->table, $data );

		if ( $id === false ) {
			return false;
		}

		return $this->find( $id );
	}

	/**
	 * Update an existing currency record.
	 */
	public function update( int $id, array $data ): bool {
		$data['updated_at'] = current_time( 'mysql' );

		return parent::update( $id, $data );
	}
}
