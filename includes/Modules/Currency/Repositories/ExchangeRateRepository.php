<?php

namespace Nozule\Modules\Currency\Repositories;

use Nozule\Core\BaseRepository;
use Nozule\Modules\Currency\Models\ExchangeRate;

/**
 * Repository for exchange rate database operations.
 */
class ExchangeRateRepository extends BaseRepository {

	protected string $table = 'exchange_rates';
	protected string $model = ExchangeRate::class;

	/**
	 * Get the most recent exchange rate for a currency pair.
	 *
	 * @param string $from Source currency code.
	 * @param string $to   Target currency code.
	 */
	public function getLatest( string $from, string $to ): ?ExchangeRate {
		$table = $this->tableName();
		$row   = $this->db->getRow(
			"SELECT * FROM {$table}
			 WHERE from_currency = %s AND to_currency = %s
			 ORDER BY effective_date DESC, id DESC
			 LIMIT 1",
			strtoupper( $from ),
			strtoupper( $to )
		);

		return $row ? ExchangeRate::fromRow( $row ) : null;
	}

	/**
	 * Get the exchange rate for a specific date (on or before the given date).
	 *
	 * @param string $from Source currency code.
	 * @param string $to   Target currency code.
	 * @param string $date Date in Y-m-d format.
	 */
	public function getForDate( string $from, string $to, string $date ): ?ExchangeRate {
		$table = $this->tableName();
		$row   = $this->db->getRow(
			"SELECT * FROM {$table}
			 WHERE from_currency = %s AND to_currency = %s AND effective_date <= %s
			 ORDER BY effective_date DESC, id DESC
			 LIMIT 1",
			strtoupper( $from ),
			strtoupper( $to ),
			$date
		);

		return $row ? ExchangeRate::fromRow( $row ) : null;
	}

	/**
	 * Get the exchange rate history for a currency pair.
	 *
	 * @param string $from  Source currency code.
	 * @param string $to    Target currency code.
	 * @param int    $limit Maximum number of records to return.
	 * @return ExchangeRate[]
	 */
	public function getHistory( string $from, string $to, int $limit = 30 ): array {
		$table = $this->tableName();
		$rows  = $this->db->getResults(
			"SELECT * FROM {$table}
			 WHERE from_currency = %s AND to_currency = %s
			 ORDER BY effective_date DESC, id DESC
			 LIMIT %d",
			strtoupper( $from ),
			strtoupper( $to ),
			$limit
		);

		return ExchangeRate::fromRows( $rows );
	}

	/**
	 * Create a new exchange rate record.
	 *
	 * @return ExchangeRate|false
	 */
	public function create( array $data ): ExchangeRate|false {
		$data['created_at'] = $data['created_at'] ?? current_time( 'mysql' );

		$id = $this->db->insert( $this->table, $data );

		if ( $id === false ) {
			return false;
		}

		return $this->find( $id );
	}
}
