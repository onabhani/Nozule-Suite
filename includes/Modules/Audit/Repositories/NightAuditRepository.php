<?php

namespace Nozule\Modules\Audit\Repositories;

use Nozule\Core\BaseRepository;
use Nozule\Core\Database;
use Nozule\Modules\Audit\Models\NightAudit;

/**
 * Repository for night audit database operations.
 */
class NightAuditRepository extends BaseRepository {

	protected string $table = 'night_audits';
	protected string $model = NightAudit::class;

	public function __construct( Database $db ) {
		parent::__construct( $db );
	}

	// ── Finders ─────────────────────────────────────────────────────

	/**
	 * Find an audit record by its date.
	 *
	 * @param string $date Audit date in Y-m-d format.
	 */
	public function findByDate( string $date ): ?NightAudit {
		$table = $this->tableName();
		$row   = $this->db->getRow(
			"SELECT * FROM {$table} WHERE audit_date = %s LIMIT 1",
			$date
		);

		return $row ? NightAudit::fromRow( $row ) : null;
	}

	/**
	 * Get the most recent audit records.
	 *
	 * @param int $limit Number of records to return.
	 * @return NightAudit[]
	 */
	public function getRecent( int $limit = 30 ): array {
		$table = $this->tableName();
		$rows  = $this->db->getResults(
			"SELECT * FROM {$table} ORDER BY audit_date DESC LIMIT %d",
			$limit
		);

		return NightAudit::fromRows( $rows );
	}

	/**
	 * Get audit records within a date range.
	 *
	 * @param string $from Start date (Y-m-d).
	 * @param string $to   End date (Y-m-d).
	 * @return NightAudit[]
	 */
	public function getByDateRange( string $from, string $to ): array {
		$table = $this->tableName();
		$rows  = $this->db->getResults(
			"SELECT * FROM {$table}
			 WHERE audit_date >= %s
			   AND audit_date <= %s
			 ORDER BY audit_date ASC",
			$from,
			$to
		);

		return NightAudit::fromRows( $rows );
	}

	/**
	 * Check whether an audit already exists for a given date.
	 *
	 * @param string $date Audit date in Y-m-d format.
	 */
	public function hasAuditForDate( string $date ): bool {
		$table = $this->tableName();
		$count = (int) $this->db->getVar(
			"SELECT COUNT(*) FROM {$table} WHERE audit_date = %s",
			$date
		);

		return $count > 0;
	}

	// ── CRUD ────────────────────────────────────────────────────────

	/**
	 * Create a new night audit record with automatic timestamps.
	 *
	 * @return NightAudit|false
	 */
	public function create( array $data ): NightAudit|false {
		$now = current_time( 'mysql' );
		$data['created_at'] = $data['created_at'] ?? $now;

		$id = $this->db->insert( $this->table, $data );

		if ( $id === false ) {
			return false;
		}

		return $this->find( $id );
	}
}
