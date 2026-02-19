<?php

namespace Nozule\Modules\Groups\Repositories;

use Nozule\Core\BaseRepository;
use Nozule\Core\Database;
use Nozule\Modules\Groups\Models\GroupBooking;

/**
 * Repository for group booking database operations.
 */
class GroupBookingRepository extends BaseRepository {

	protected string $table = 'group_bookings';
	protected string $model = GroupBooking::class;

	public function __construct( Database $db ) {
		parent::__construct( $db );
	}

	// ── CRUD ────────────────────────────────────────────────────────

	/**
	 * Create a new group booking.
	 *
	 * @return GroupBooking|false
	 */
	public function create( array $data ): GroupBooking|false {
		$now                = current_time( 'mysql' );
		$data['created_at'] = $data['created_at'] ?? $now;
		$data['updated_at'] = $data['updated_at'] ?? $now;

		$id = $this->db->insert( $this->table, $data );

		if ( $id === false ) {
			return false;
		}

		return $this->find( $id );
	}

	/**
	 * Update a group booking by ID.
	 */
	public function update( int $id, array $data ): bool {
		$data['updated_at'] = current_time( 'mysql' );

		return $this->db->update( $this->table, $data, [ 'id' => $id ] ) !== false;
	}

	// ── Group Number Generation ────────────────────────────────────

	/**
	 * Generate a unique group number in the format GRP-YYYY-NNNNN.
	 */
	public function generateGroupNumber(): string {
		$year  = (int) current_time( 'Y' );
		$table = $this->tableName();

		$pattern = 'GRP-' . $year . '-%';

		$count = (int) $this->db->getVar(
			"SELECT COUNT(*) FROM {$table} WHERE group_number LIKE %s",
			$pattern
		);

		$sequence = $count + 1;

		return sprintf( 'GRP-%04d-%05d', $year, $sequence );
	}

	// ── Finders ─────────────────────────────────────────────────────

	/**
	 * Get group bookings filtered by status.
	 *
	 * @return GroupBooking[]
	 */
	public function getByStatus( string $status ): array {
		$table = $this->tableName();
		$rows  = $this->db->getResults(
			"SELECT * FROM {$table} WHERE status = %s ORDER BY check_in ASC",
			$status
		);

		return GroupBooking::fromRows( $rows );
	}

	/**
	 * Get group bookings within a date range.
	 *
	 * Returns groups whose stay overlaps with the given range.
	 *
	 * @return GroupBooking[]
	 */
	public function getByDateRange( string $from, string $to ): array {
		$table = $this->tableName();
		$rows  = $this->db->getResults(
			"SELECT * FROM {$table}
			 WHERE check_in <= %s
			   AND check_out >= %s
			 ORDER BY check_in ASC",
			$to,
			$from
		);

		return GroupBooking::fromRows( $rows );
	}

	/**
	 * Get upcoming confirmed groups (check_in >= today).
	 *
	 * @return GroupBooking[]
	 */
	public function getUpcoming(): array {
		$table = $this->tableName();
		$today = current_time( 'Y-m-d' );

		$rows = $this->db->getResults(
			"SELECT * FROM {$table}
			 WHERE status = %s
			   AND check_in >= %s
			 ORDER BY check_in ASC",
			GroupBooking::STATUS_CONFIRMED,
			$today
		);

		return GroupBooking::fromRows( $rows );
	}

	/**
	 * Search group bookings by group_name, agency_name, contact_person, or group_number.
	 *
	 * @return GroupBooking[]
	 */
	public function search( string $query ): array {
		$table = $this->tableName();
		$like  = '%' . $this->db->wpdb()->esc_like( $query ) . '%';

		$rows = $this->db->getResults(
			"SELECT * FROM {$table}
			 WHERE group_name LIKE %s
			    OR agency_name LIKE %s
			    OR contact_person LIKE %s
			    OR group_number LIKE %s
			 ORDER BY created_at DESC",
			$like,
			$like,
			$like,
			$like
		);

		return GroupBooking::fromRows( $rows );
	}

	// ── Paginated Listing ───────────────────────────────────────────

	/**
	 * List group bookings with pagination, filtering, and sorting.
	 *
	 * @param array $args {
	 *     Optional. Arguments for listing group bookings.
	 *
	 *     @type string $status    Filter by status.
	 *     @type string $date_from Filter by check_in >= date (Y-m-d).
	 *     @type string $date_to   Filter by check_in <= date (Y-m-d).
	 *     @type string $search    Free-text search (group_name, agency_name, contact_person, group_number).
	 *     @type string $orderby   Column to order by. Default 'created_at'.
	 *     @type string $order     Sort direction (ASC|DESC). Default 'DESC'.
	 *     @type int    $per_page  Results per page. Default 20.
	 *     @type int    $page      Page number (1-based). Default 1.
	 * }
	 * @return array{ groups: GroupBooking[], total: int, pages: int }
	 */
	public function list( array $args = [] ): array {
		$defaults = [
			'status'    => '',
			'date_from' => '',
			'date_to'   => '',
			'search'    => '',
			'orderby'   => 'created_at',
			'order'     => 'DESC',
			'per_page'  => 20,
			'page'      => 1,
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

		// Date range filter (on check_in).
		if ( ! empty( $args['date_from'] ) ) {
			$conditions[] = 'check_in >= %s';
			$params[]     = $args['date_from'];
		}
		if ( ! empty( $args['date_to'] ) ) {
			$conditions[] = 'check_in <= %s';
			$params[]     = $args['date_to'];
		}

		// Free-text search.
		if ( ! empty( $args['search'] ) ) {
			$like         = '%' . $this->db->wpdb()->esc_like( $args['search'] ) . '%';
			$conditions[] = '(group_name LIKE %s OR agency_name LIKE %s OR contact_person LIKE %s OR group_number LIKE %s)';
			$params[]     = $like;
			$params[]     = $like;
			$params[]     = $like;
			$params[]     = $like;
		}

		$where = ! empty( $conditions )
			? 'WHERE ' . implode( ' AND ', $conditions )
			: '';

		// Sanitize ordering.
		$allowed_columns = [
			'id', 'group_number', 'group_name', 'check_in', 'check_out',
			'status', 'total_rooms', 'grand_total', 'created_at',
		];
		$orderby = in_array( $args['orderby'], $allowed_columns, true ) ? $args['orderby'] : 'created_at';
		$order   = strtoupper( $args['order'] ) === 'ASC' ? 'ASC' : 'DESC';

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
			"SELECT * FROM {$table}
			 {$where}
			 ORDER BY {$orderby} {$order}
			 LIMIT %d OFFSET %d",
			...$params
		);

		return [
			'groups' => GroupBooking::fromRows( $rows ),
			'total'  => $total,
			'pages'  => (int) ceil( $total / max( 1, $args['per_page'] ) ),
		];
	}
}
