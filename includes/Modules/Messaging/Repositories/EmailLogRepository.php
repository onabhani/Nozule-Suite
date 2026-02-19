<?php

namespace Nozule\Modules\Messaging\Repositories;

use Nozule\Core\BaseRepository;
use Nozule\Core\Database;
use Nozule\Modules\Messaging\Models\EmailLog;

/**
 * Repository for email log database operations.
 */
class EmailLogRepository extends BaseRepository {

	protected string $table = 'email_log';
	protected string $model = EmailLog::class;

	public function __construct( Database $db ) {
		parent::__construct( $db );
	}

	// ── Finders ─────────────────────────────────────────────────────

	/**
	 * Get all email log entries for a specific booking.
	 *
	 * @return EmailLog[]
	 */
	public function getByBooking( int $bookingId ): array {
		$table = $this->tableName();
		$rows  = $this->db->getResults(
			"SELECT * FROM {$table} WHERE booking_id = %d ORDER BY created_at DESC",
			$bookingId
		);

		return EmailLog::fromRows( $rows );
	}

	/**
	 * Get all email log entries for a specific guest.
	 *
	 * @return EmailLog[]
	 */
	public function getByGuest( int $guestId ): array {
		$table = $this->tableName();
		$rows  = $this->db->getResults(
			"SELECT * FROM {$table} WHERE guest_id = %d ORDER BY created_at DESC",
			$guestId
		);

		return EmailLog::fromRows( $rows );
	}

	/**
	 * Get the most recent email log entries.
	 *
	 * @return EmailLog[]
	 */
	public function getRecent( int $limit = 50 ): array {
		$table = $this->tableName();
		$rows  = $this->db->getResults(
			"SELECT * FROM {$table} ORDER BY created_at DESC LIMIT %d",
			$limit
		);

		return EmailLog::fromRows( $rows );
	}

	// ── CRUD Overrides ──────────────────────────────────────────────

	/**
	 * Create a new email log entry.
	 *
	 * @return EmailLog|false
	 */
	public function create( array $data ): EmailLog|false {
		$data['created_at'] = $data['created_at'] ?? current_time( 'mysql' );

		$id = $this->db->insert( $this->table, $data );

		if ( $id === false ) {
			return false;
		}

		return $this->find( $id );
	}

	/**
	 * Update the status of an email log entry.
	 */
	public function updateStatus( int $id, string $status, ?string $errorMessage = null ): bool {
		$data = [ 'status' => $status ];

		if ( $status === EmailLog::STATUS_SENT ) {
			$data['sent_at'] = current_time( 'mysql' );
		}

		if ( $errorMessage !== null ) {
			$data['error_message'] = $errorMessage;
		}

		return $this->db->update( $this->table, $data, [ 'id' => $id ] ) !== false;
	}

	// ── Paginated Listing ───────────────────────────────────────────

	/**
	 * List email log entries with pagination and filtering.
	 *
	 * @param array $args {
	 *     Optional. Arguments for listing log entries.
	 *
	 *     @type string $status   Filter by status (queued|sent|failed).
	 *     @type string $search   Free-text search on to_email or subject.
	 *     @type string $orderby  Column to order by. Default 'created_at'.
	 *     @type string $order    Sort direction (ASC|DESC). Default 'DESC'.
	 *     @type int    $per_page Results per page. Default 20.
	 *     @type int    $page     Page number (1-based). Default 1.
	 * }
	 * @return array{ items: EmailLog[], total: int, pages: int }
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
			$conditions[] = '(to_email LIKE %s OR subject LIKE %s)';
			$params[]     = $like;
			$params[]     = $like;
		}

		$where = ! empty( $conditions )
			? 'WHERE ' . implode( ' AND ', $conditions )
			: '';

		// Sanitize ordering.
		$allowed_columns = [ 'id', 'to_email', 'subject', 'status', 'sent_at', 'created_at' ];
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
			'items' => EmailLog::fromRows( $rows ),
			'total' => $total,
			'pages' => (int) ceil( $total / max( 1, $args['per_page'] ) ),
		];
	}
}
