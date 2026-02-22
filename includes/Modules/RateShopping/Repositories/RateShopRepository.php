<?php

namespace Nozule\Modules\RateShopping\Repositories;

use Nozule\Core\Database;
use Nozule\Modules\RateShopping\Models\Competitor;
use Nozule\Modules\RateShopping\Models\ParityAlert;
use Nozule\Modules\RateShopping\Models\RateResult;

/**
 * Repository for rate shopping database operations.
 *
 * Manages three tables:
 *   - rate_shop_competitors
 *   - rate_shop_results
 *   - rate_shop_alerts
 */
class RateShopRepository {

	private Database $db;

	public function __construct( Database $db ) {
		$this->db = $db;
	}

	// ══════════════════════════════════════════════════════════════════
	// Competitors
	// ══════════════════════════════════════════════════════════════════

	/**
	 * Get all competitors, ordered by name.
	 *
	 * @return Competitor[]
	 */
	public function getCompetitors(): array {
		$table = $this->db->table( 'rate_shop_competitors' );
		$rows  = $this->db->getResults( "SELECT * FROM {$table} ORDER BY name ASC" );

		return Competitor::fromRows( $rows );
	}

	/**
	 * Get a single competitor by ID.
	 */
	public function getCompetitor( int $id ): ?Competitor {
		$table = $this->db->table( 'rate_shop_competitors' );
		$row   = $this->db->getRow( "SELECT * FROM {$table} WHERE id = %d", $id );

		return $row ? Competitor::fromRow( $row ) : null;
	}

	/**
	 * Save (create or update) a competitor.
	 *
	 * @param array $data Competitor fields. Include 'id' to update.
	 * @return Competitor|false
	 */
	public function saveCompetitor( array $data ) {
		$now = current_time( 'mysql' );

		if ( ! empty( $data['id'] ) ) {
			// Update.
			$id = (int) $data['id'];
			unset( $data['id'] );
			$data['updated_at'] = $now;

			$result = $this->db->update( 'rate_shop_competitors', $data, [ 'id' => $id ] );
			if ( $result === false ) {
				return false;
			}

			return $this->getCompetitor( $id );
		}

		// Create.
		$data['created_at'] = $data['created_at'] ?? $now;
		$data['updated_at'] = $data['updated_at'] ?? $now;

		$id = $this->db->insert( 'rate_shop_competitors', $data );
		if ( $id === false ) {
			return false;
		}

		return $this->getCompetitor( $id );
	}

	/**
	 * Delete a competitor by ID.
	 */
	public function deleteCompetitor( int $id ): bool {
		return $this->db->delete( 'rate_shop_competitors', [ 'id' => $id ] ) !== false;
	}

	/**
	 * Get only active competitors.
	 *
	 * @return Competitor[]
	 */
	public function getActiveCompetitors(): array {
		$table = $this->db->table( 'rate_shop_competitors' );
		$rows  = $this->db->getResults(
			"SELECT * FROM {$table} WHERE is_active = 1 ORDER BY name ASC"
		);

		return Competitor::fromRows( $rows );
	}

	// ══════════════════════════════════════════════════════════════════
	// Results
	// ══════════════════════════════════════════════════════════════════

	/**
	 * Get rate results for a competitor within a date range.
	 *
	 * @return RateResult[]
	 */
	public function getResults( int $competitorId, string $dateFrom, string $dateTo ): array {
		$table = $this->db->table( 'rate_shop_results' );
		$rows  = $this->db->getResults(
			"SELECT * FROM {$table}
			 WHERE competitor_id = %d
			   AND check_date >= %s
			   AND check_date <= %s
			 ORDER BY check_date ASC, captured_at DESC",
			$competitorId,
			$dateFrom,
			$dateTo
		);

		return RateResult::fromRows( $rows );
	}

	/**
	 * Save a rate result.
	 *
	 * @return RateResult|false
	 */
	public function saveResult( array $data ) {
		$data['captured_at'] = $data['captured_at'] ?? current_time( 'mysql' );

		$id = $this->db->insert( 'rate_shop_results', $data );
		if ( $id === false ) {
			return false;
		}

		$table = $this->db->table( 'rate_shop_results' );
		$row   = $this->db->getRow( "SELECT * FROM {$table} WHERE id = %d", $id );

		return $row ? RateResult::fromRow( $row ) : false;
	}

	/**
	 * Get the latest rate results for a competitor (most recent capture per check_date).
	 *
	 * @return RateResult[]
	 */
	public function getLatestResults( int $competitorId, int $limit = 30 ): array {
		$table = $this->db->table( 'rate_shop_results' );
		$rows  = $this->db->getResults(
			"SELECT r.* FROM {$table} r
			 INNER JOIN (
			     SELECT check_date, MAX(id) AS max_id
			     FROM {$table}
			     WHERE competitor_id = %d
			     GROUP BY check_date
			 ) latest ON r.id = latest.max_id
			 ORDER BY r.check_date DESC
			 LIMIT %d",
			$competitorId,
			$limit
		);

		return RateResult::fromRows( $rows );
	}

	/**
	 * Get all rate results for a specific check date (across all competitors).
	 *
	 * @return RateResult[]
	 */
	public function getResultsByCheckDate( string $checkDate ): array {
		$table = $this->db->table( 'rate_shop_results' );
		$rows  = $this->db->getResults(
			"SELECT r.* FROM {$table} r
			 INNER JOIN (
			     SELECT competitor_id, MAX(id) AS max_id
			     FROM {$table}
			     WHERE check_date = %s
			     GROUP BY competitor_id
			 ) latest ON r.id = latest.max_id
			 ORDER BY r.competitor_id ASC",
			$checkDate
		);

		return RateResult::fromRows( $rows );
	}

	/**
	 * Get recent rate results across all competitors.
	 *
	 * @return RateResult[]
	 */
	public function getRecentResults( int $limit = 50 ): array {
		$table = $this->db->table( 'rate_shop_results' );
		$rows  = $this->db->getResults(
			"SELECT * FROM {$table} ORDER BY captured_at DESC LIMIT %d",
			$limit
		);

		return RateResult::fromRows( $rows );
	}

	// ══════════════════════════════════════════════════════════════════
	// Alerts
	// ══════════════════════════════════════════════════════════════════

	/**
	 * Get alerts with filters and pagination.
	 *
	 * @param array $filters {
	 *     @type string $status        Filter by status: unresolved, resolved, or '' for all.
	 *     @type int    $competitor_id Filter by competitor.
	 *     @type int    $per_page      Results per page. Default 20.
	 *     @type int    $page          Page number (1-based). Default 1.
	 * }
	 * @return array{ items: ParityAlert[], total: int, pages: int }
	 */
	public function getAlerts( array $filters = [], int $page = 1 ): array {
		$defaults = [
			'status'        => '',
			'competitor_id' => 0,
			'per_page'      => 20,
			'page'          => $page,
		];

		$args       = wp_parse_args( $filters, $defaults );
		$table      = $this->db->table( 'rate_shop_alerts' );
		$offset     = ( $args['page'] - 1 ) * $args['per_page'];
		$conditions = [];
		$params     = [];

		if ( ! empty( $args['status'] ) ) {
			$conditions[] = 'status = %s';
			$params[]     = $args['status'];
		}

		if ( ! empty( $args['competitor_id'] ) ) {
			$conditions[] = 'competitor_id = %d';
			$params[]     = (int) $args['competitor_id'];
		}

		$where = '';
		if ( ! empty( $conditions ) ) {
			$where = 'WHERE ' . implode( ' AND ', $conditions );
		}

		// Count total.
		$total = (int) $this->db->getVar(
			"SELECT COUNT(*) FROM {$table} {$where}",
			...$params
		);

		// Fetch results.
		$queryParams   = $params;
		$queryParams[] = $args['per_page'];
		$queryParams[] = $offset;

		$rows = $this->db->getResults(
			"SELECT * FROM {$table} {$where} ORDER BY created_at DESC LIMIT %d OFFSET %d",
			...$queryParams
		);

		return [
			'items' => ParityAlert::fromRows( $rows ),
			'total' => $total,
			'pages' => (int) ceil( $total / max( 1, $args['per_page'] ) ),
		];
	}

	/**
	 * Save (create) a parity alert.
	 *
	 * @return ParityAlert|false
	 */
	public function saveAlert( array $data ) {
		$data['created_at'] = $data['created_at'] ?? current_time( 'mysql' );
		$data['status']     = $data['status'] ?? ParityAlert::STATUS_UNRESOLVED;

		$id = $this->db->insert( 'rate_shop_alerts', $data );
		if ( $id === false ) {
			return false;
		}

		$table = $this->db->table( 'rate_shop_alerts' );
		$row   = $this->db->getRow( "SELECT * FROM {$table} WHERE id = %d", $id );

		return $row ? ParityAlert::fromRow( $row ) : false;
	}

	/**
	 * Resolve an alert by ID.
	 */
	public function resolveAlert( int $id ): bool {
		return $this->db->update( 'rate_shop_alerts', [
			'status'      => ParityAlert::STATUS_RESOLVED,
			'resolved_at' => current_time( 'mysql' ),
			'resolved_by' => get_current_user_id(),
		], [ 'id' => $id ] ) !== false;
	}

	/**
	 * Get count of unresolved alerts.
	 */
	public function getUnresolvedCount(): int {
		$table = $this->db->table( 'rate_shop_alerts' );

		return (int) $this->db->getVar(
			"SELECT COUNT(*) FROM {$table} WHERE status = %s",
			ParityAlert::STATUS_UNRESOLVED
		);
	}

	/**
	 * Get a single alert by ID.
	 */
	public function getAlert( int $id ): ?ParityAlert {
		$table = $this->db->table( 'rate_shop_alerts' );
		$row   = $this->db->getRow( "SELECT * FROM {$table} WHERE id = %d", $id );

		return $row ? ParityAlert::fromRow( $row ) : null;
	}

	// ══════════════════════════════════════════════════════════════════
	// Stats / Aggregates
	// ══════════════════════════════════════════════════════════════════

	/**
	 * Get the most recent captured_at timestamp across all results.
	 */
	public function getLastShopDate(): ?string {
		$table = $this->db->table( 'rate_shop_results' );

		return $this->db->getVar(
			"SELECT MAX(captured_at) FROM {$table}"
		);
	}

	/**
	 * Count total competitors.
	 */
	public function countCompetitors(): int {
		$table = $this->db->table( 'rate_shop_competitors' );

		return (int) $this->db->getVar( "SELECT COUNT(*) FROM {$table}" );
	}

	/**
	 * Get average rate difference from recent alerts (absolute value).
	 */
	public function getAverageRateDiff(): float {
		$table = $this->db->table( 'rate_shop_alerts' );

		$result = $this->db->getVar(
			"SELECT AVG(ABS(difference)) FROM {$table}
			 WHERE created_at >= %s",
			gmdate( 'Y-m-d H:i:s', strtotime( '-30 days' ) )
		);

		return round( (float) $result, 2 );
	}

	/**
	 * Get parity report data for a date range.
	 *
	 * Returns rate results with competitor info joined.
	 *
	 * @return array Raw result objects with competitor_name, competitor_source etc.
	 */
	public function getParityReportData( string $dateFrom, string $dateTo ): array {
		$resultsTable     = $this->db->table( 'rate_shop_results' );
		$competitorsTable = $this->db->table( 'rate_shop_competitors' );

		return $this->db->getResults(
			"SELECT r.*, c.name AS competitor_name, c.name_ar AS competitor_name_ar,
			        c.source AS competitor_source, c.room_type_match
			 FROM {$resultsTable} r
			 INNER JOIN {$competitorsTable} c ON r.competitor_id = c.id
			 INNER JOIN (
			     SELECT competitor_id, check_date, MAX(id) AS max_id
			     FROM {$resultsTable}
			     WHERE check_date >= %s AND check_date <= %s
			     GROUP BY competitor_id, check_date
			 ) latest ON r.id = latest.max_id
			 WHERE c.is_active = 1
			 ORDER BY r.check_date ASC, c.name ASC",
			$dateFrom,
			$dateTo
		);
	}
}
