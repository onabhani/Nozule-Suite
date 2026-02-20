<?php

namespace Nozule\Modules\Reviews\Repositories;

use Nozule\Core\BaseRepository;
use Nozule\Core\Database;
use Nozule\Modules\Reviews\Models\ReviewRequest;

/**
 * Repository for review request database operations.
 */
class ReviewRepository extends BaseRepository {

	protected string $table = 'review_requests';
	protected string $model = ReviewRequest::class;

	public function __construct( Database $db ) {
		parent::__construct( $db );
	}

	// ── Finders ─────────────────────────────────────────────────────

	/**
	 * Get all review requests for a specific booking.
	 *
	 * @return ReviewRequest[]
	 */
	public function getByBooking( int $bookingId ): array {
		$table = $this->tableName();
		$rows  = $this->db->getResults(
			"SELECT * FROM {$table} WHERE booking_id = %d ORDER BY created_at DESC",
			$bookingId
		);

		return ReviewRequest::fromRows( $rows );
	}

	/**
	 * Get all review requests for a specific guest.
	 *
	 * @return ReviewRequest[]
	 */
	public function getByGuest( int $guestId ): array {
		$table = $this->tableName();
		$rows  = $this->db->getResults(
			"SELECT * FROM {$table} WHERE guest_id = %d ORDER BY created_at DESC",
			$guestId
		);

		return ReviewRequest::fromRows( $rows );
	}

	/**
	 * Get queued requests that are ready to be sent (past their delay).
	 *
	 * @return ReviewRequest[]
	 */
	public function getPending(): array {
		$table = $this->tableName();
		$now   = current_time( 'mysql' );

		$rows = $this->db->getResults(
			"SELECT * FROM {$table} WHERE status = %s AND (send_after IS NULL OR send_after <= %s) ORDER BY created_at ASC",
			ReviewRequest::STATUS_QUEUED,
			$now
		);

		return ReviewRequest::fromRows( $rows );
	}

	// ── Stats ───────────────────────────────────────────────────────

	/**
	 * Get aggregated stats by status.
	 *
	 * @return array{ total: int, queued: int, sent: int, failed: int, clicked: int }
	 */
	public function getStats(): array {
		$table = $this->tableName();

		$rows = $this->db->getResults(
			"SELECT status, COUNT(*) AS cnt FROM {$table} GROUP BY status"
		);

		$stats = [
			'total'   => 0,
			'queued'  => 0,
			'sent'    => 0,
			'failed'  => 0,
			'clicked' => 0,
		];

		foreach ( $rows as $row ) {
			$status = $row->status;
			$count  = (int) $row->cnt;
			if ( isset( $stats[ $status ] ) ) {
				$stats[ $status ] = $count;
			}
			$stats['total'] += $count;
		}

		return $stats;
	}

	// ── Status Updates ──────────────────────────────────────────────

	/**
	 * Mark a review request as sent.
	 */
	public function markSent( int $id ): bool {
		return $this->db->update( $this->table, [
			'status'  => ReviewRequest::STATUS_SENT,
			'sent_at' => current_time( 'mysql' ),
		], [ 'id' => $id ] ) !== false;
	}

	/**
	 * Mark a review request as clicked.
	 */
	public function markClicked( int $id ): bool {
		return $this->db->update( $this->table, [
			'status'     => ReviewRequest::STATUS_CLICKED,
			'clicked_at' => current_time( 'mysql' ),
		], [ 'id' => $id ] ) !== false;
	}

	/**
	 * Mark a review request as failed.
	 */
	public function markFailed( int $id ): bool {
		return $this->db->update( $this->table, [
			'status' => ReviewRequest::STATUS_FAILED,
		], [ 'id' => $id ] ) !== false;
	}

	// ── CRUD Overrides ──────────────────────────────────────────────

	/**
	 * Create a new review request.
	 *
	 * @return ReviewRequest|false
	 */
	public function create( array $data ): ReviewRequest|false {
		$data['created_at'] = $data['created_at'] ?? current_time( 'mysql' );

		$id = $this->db->insert( $this->table, $data );

		if ( $id === false ) {
			return false;
		}

		return $this->find( $id );
	}

	// ── Paginated Listing ───────────────────────────────────────────

	/**
	 * List review requests with pagination and filtering.
	 *
	 * @param array $args {
	 *     Optional. Arguments for listing review requests.
	 *
	 *     @type string $status   Filter by status (queued|sent|failed|clicked).
	 *     @type string $search   Free-text search on to_email.
	 *     @type string $orderby  Column to order by. Default 'created_at'.
	 *     @type string $order    Sort direction (ASC|DESC). Default 'DESC'.
	 *     @type int    $per_page Results per page. Default 20.
	 *     @type int    $page     Page number (1-based). Default 1.
	 * }
	 * @return array{ items: ReviewRequest[], total: int, pages: int }
	 */
	public function list( array $args = [] ): array {
		$defaults = [
			'status'   => '',
			'search'   => '',
			'orderby'  => 'created_at',
			'order'    => 'DESC',
			'per_page' => 20,
			'page'     => 1,
		];

		$args       = wp_parse_args( $args, $defaults );
		$table      = $this->tableName();
		$offset     = ( $args['page'] - 1 ) * $args['per_page'];
		$conditions = [];
		$params     = [];

		// Status filter.
		if ( ! empty( $args['status'] ) ) {
			$conditions[] = 'status = %s';
			$params[]     = $args['status'];
		}

		// Free-text search.
		if ( ! empty( $args['search'] ) ) {
			$like         = '%' . $this->db->wpdb()->esc_like( $args['search'] ) . '%';
			$conditions[] = 'to_email LIKE %s';
			$params[]     = $like;
		}

		$where = ! empty( $conditions )
			? 'WHERE ' . implode( ' AND ', $conditions )
			: '';

		// Sanitize ordering.
		$allowed_columns = [ 'id', 'to_email', 'status', 'review_platform', 'sent_at', 'clicked_at', 'created_at' ];
		$orderby         = in_array( $args['orderby'], $allowed_columns, true ) ? $args['orderby'] : 'created_at';
		$order           = strtoupper( $args['order'] ) === 'ASC' ? 'ASC' : 'DESC';

		// Count total matching rows.
		$count_params = $params;
		$total        = (int) $this->db->getVar(
			"SELECT COUNT(*) FROM {$table} {$where}",
			...$count_params
		);

		// Fetch paginated results.
		$params[] = $args['per_page'];
		$params[] = $offset;

		$rows = $this->db->getResults(
			"SELECT * FROM {$table} {$where} ORDER BY {$orderby} {$order} LIMIT %d OFFSET %d",
			...$params
		);

		return [
			'items' => ReviewRequest::fromRows( $rows ),
			'total' => $total,
			'pages' => (int) ceil( $total / max( 1, $args['per_page'] ) ),
		];
	}

	// ── Settings Helpers ────────────────────────────────────────────

	/**
	 * Get a review setting value by key.
	 *
	 * @param string      $key     The setting key.
	 * @param string|null $default Default value if not found.
	 */
	public function getSetting( string $key, ?string $default = null ): ?string {
		$table = $this->db->table( 'review_settings' );
		$value = $this->db->getVar(
			"SELECT setting_value FROM {$table} WHERE setting_key = %s",
			$key
		);

		return $value !== null ? $value : $default;
	}

	/**
	 * Update or insert a review setting.
	 */
	public function setSetting( string $key, string $value ): bool {
		$table    = $this->db->table( 'review_settings' );
		$existing = $this->db->getVar(
			"SELECT id FROM {$table} WHERE setting_key = %s",
			$key
		);

		if ( $existing ) {
			return $this->db->wpdb()->update(
				$table,
				[
					'setting_value' => $value,
					'updated_at'    => current_time( 'mysql' ),
				],
				[ 'setting_key' => $key ]
			) !== false;
		}

		return $this->db->wpdb()->insert( $table, [
			'setting_key'   => $key,
			'setting_value' => $value,
		] ) !== false;
	}

	/**
	 * Get all review settings as a key-value array.
	 *
	 * @return array<string, string>
	 */
	public function getAllSettings(): array {
		$table = $this->db->table( 'review_settings' );
		$rows  = $this->db->getResults( "SELECT setting_key, setting_value FROM {$table}" );

		$settings = [];
		foreach ( $rows as $row ) {
			$settings[ $row->setting_key ] = $row->setting_value;
		}

		return $settings;
	}
}
