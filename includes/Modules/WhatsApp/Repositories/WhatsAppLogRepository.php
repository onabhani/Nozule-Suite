<?php

namespace Nozule\Modules\WhatsApp\Repositories;

use Nozule\Core\BaseRepository;
use Nozule\Core\Database;
use Nozule\Modules\WhatsApp\Models\WhatsAppLog;

/**
 * Repository for WhatsApp log database operations.
 */
class WhatsAppLogRepository extends BaseRepository {

	protected string $table = 'whatsapp_log';
	protected string $model = WhatsAppLog::class;

	public function __construct( Database $db ) {
		parent::__construct( $db );
	}

	// ── Finders ─────────────────────────────────────────────────────

	/**
	 * Get all WhatsApp log entries for a specific booking.
	 *
	 * @return WhatsAppLog[]
	 */
	public function getByBooking( int $bookingId ): array {
		$table = $this->tableName();
		$rows  = $this->db->getResults(
			"SELECT * FROM {$table} WHERE booking_id = %d ORDER BY created_at DESC",
			$bookingId
		);

		return WhatsAppLog::fromRows( $rows );
	}

	/**
	 * Get all WhatsApp log entries for a specific guest.
	 *
	 * @return WhatsAppLog[]
	 */
	public function getByGuest( int $guestId ): array {
		$table = $this->tableName();
		$rows  = $this->db->getResults(
			"SELECT * FROM {$table} WHERE guest_id = %d ORDER BY created_at DESC",
			$guestId
		);

		return WhatsAppLog::fromRows( $rows );
	}

	/**
	 * Get the most recent WhatsApp log entries.
	 *
	 * @return WhatsAppLog[]
	 */
	public function getRecent( int $limit = 50 ): array {
		$table = $this->tableName();
		$rows  = $this->db->getResults(
			"SELECT * FROM {$table} ORDER BY created_at DESC LIMIT %d",
			$limit
		);

		return WhatsAppLog::fromRows( $rows );
	}

	// ── CRUD Overrides ──────────────────────────────────────────────

	/**
	 * Create a new WhatsApp log entry.
	 *
	 * @return WhatsAppLog|false
	 */
	public function create( array $data ): WhatsAppLog|false {
		$data['created_at'] = $data['created_at'] ?? current_time( 'mysql' );

		$id = $this->db->insert( $this->table, $data );

		if ( $id === false ) {
			return false;
		}

		return $this->find( $id );
	}

	/**
	 * Update the status of a WhatsApp log entry.
	 */
	public function updateStatus( int $id, string $status, ?string $errorMessage = null, ?string $waMessageId = null ): bool {
		$data = [ 'status' => $status ];

		if ( $status === WhatsAppLog::STATUS_SENT ) {
			$data['sent_at'] = current_time( 'mysql' );
		}

		if ( $errorMessage !== null ) {
			$data['error_message'] = $errorMessage;
		}

		if ( $waMessageId !== null ) {
			$data['wa_message_id'] = $waMessageId;
		}

		return $this->db->update( $this->table, $data, [ 'id' => $id ] ) !== false;
	}

	// ── Paginated Listing ───────────────────────────────────────────

	/**
	 * List WhatsApp log entries with pagination and filtering.
	 *
	 * @param array $args {
	 *     Optional. Arguments for listing log entries.
	 *
	 *     @type string $status   Filter by status (queued|sent|delivered|read|failed).
	 *     @type string $search   Free-text search on to_phone or body.
	 *     @type string $orderby  Column to order by. Default 'created_at'.
	 *     @type string $order    Sort direction (ASC|DESC). Default 'DESC'.
	 *     @type int    $per_page Results per page. Default 20.
	 *     @type int    $page     Page number (1-based). Default 1.
	 * }
	 * @return array{ items: WhatsAppLog[], total: int, pages: int }
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
			$conditions[] = '(to_phone LIKE %s OR body LIKE %s)';
			$params[]     = $like;
			$params[]     = $like;
		}

		$where = ! empty( $conditions )
			? 'WHERE ' . implode( ' AND ', $conditions )
			: '';

		// Sanitize ordering.
		$allowed_columns = [ 'id', 'to_phone', 'status', 'sent_at', 'created_at' ];
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
			'items' => WhatsAppLog::fromRows( $rows ),
			'total' => $total,
			'pages' => (int) ceil( $total / max( 1, $args['per_page'] ) ),
		];
	}
}
