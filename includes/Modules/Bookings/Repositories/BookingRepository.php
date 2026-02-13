<?php

namespace Nozule\Modules\Bookings\Repositories;

use Nozule\Core\BaseRepository;
use Nozule\Core\Database;
use Nozule\Modules\Bookings\Models\Booking;
use Nozule\Modules\Bookings\Models\BookingLog;

/**
 * Repository for booking database operations.
 */
class BookingRepository extends BaseRepository {

	protected string $table = 'bookings';
	protected string $model = Booking::class;

	public function __construct( Database $db ) {
		parent::__construct( $db );
	}

	// ── Finders ─────────────────────────────────────────────────────

	/**
	 * Find a booking by its unique booking number.
	 */
	public function findByBookingNumber( string $bookingNumber ): ?Booking {
		$table = $this->tableName();
		$row   = $this->db->getRow(
			"SELECT * FROM {$table} WHERE booking_number = %s LIMIT 1",
			$bookingNumber
		);

		return $row ? Booking::fromRow( $row ) : null;
	}

	/**
	 * Get bookings filtered by status.
	 *
	 * @return Booking[]
	 */
	public function getByStatus( string $status ): array {
		$table = $this->tableName();
		$rows  = $this->db->getResults(
			"SELECT * FROM {$table} WHERE status = %s ORDER BY check_in ASC",
			$status
		);

		return Booking::fromRows( $rows );
	}

	/**
	 * Get bookings with a specific check-in date.
	 *
	 * @return Booking[]
	 */
	public function getByCheckInDate( string $date ): array {
		$table = $this->tableName();
		$rows  = $this->db->getResults(
			"SELECT * FROM {$table} WHERE check_in = %s ORDER BY created_at ASC",
			$date
		);

		return Booking::fromRows( $rows );
	}

	/**
	 * Get bookings with a specific check-out date.
	 *
	 * @return Booking[]
	 */
	public function getByCheckOutDate( string $date ): array {
		$table = $this->tableName();
		$rows  = $this->db->getResults(
			"SELECT * FROM {$table} WHERE check_out = %s ORDER BY created_at ASC",
			$date
		);

		return Booking::fromRows( $rows );
	}

	/**
	 * Get all bookings for a specific guest.
	 *
	 * @return Booking[]
	 */
	public function getByGuestId( int $guestId ): array {
		$table = $this->tableName();
		$rows  = $this->db->getResults(
			"SELECT * FROM {$table} WHERE guest_id = %d ORDER BY check_in DESC",
			$guestId
		);

		return Booking::fromRows( $rows );
	}

	/**
	 * Get bookings that overlap a date range (for calendar view).
	 *
	 * Returns all bookings whose stay overlaps with [startDate, endDate].
	 *
	 * @return Booking[]
	 */
	public function getForCalendar( string $startDate, string $endDate ): array {
		$table = $this->tableName();
		$rows  = $this->db->getResults(
			"SELECT * FROM {$table}
			 WHERE check_in < %s
			   AND check_out > %s
			   AND status IN ('confirmed', 'checked_in', 'pending')
			 ORDER BY check_in ASC",
			$endDate,
			$startDate
		);

		return Booking::fromRows( $rows );
	}

	/**
	 * Get the next sequence number for the current year.
	 *
	 * Counts existing bookings whose booking_number starts with the given
	 * prefix and year, then returns count + 1.
	 */
	public function getNextSequence( string $prefix, int $year ): int {
		$table   = $this->tableName();
		$pattern = $prefix . '-' . $year . '-%';

		$count = (int) $this->db->getVar(
			"SELECT COUNT(*) FROM {$table} WHERE booking_number LIKE %s",
			$pattern
		);

		return $count + 1;
	}

	// ── CRUD ────────────────────────────────────────────────────────

	/**
	 * Create a new booking.
	 *
	 * @return Booking|false
	 */
	public function create( array $data ): Booking|false {
		$now = current_time( 'mysql' );
		$data['created_at'] = $data['created_at'] ?? $now;
		$data['updated_at'] = $data['updated_at'] ?? $now;

		$id = $this->db->insert( $this->table, $data );

		if ( $id === false ) {
			return false;
		}

		return $this->find( $id );
	}

	/**
	 * Update a booking by ID.
	 */
	public function update( int $id, array $data ): bool {
		$data['updated_at'] = current_time( 'mysql' );

		return $this->db->update( $this->table, $data, [ 'id' => $id ] ) !== false;
	}

	/**
	 * Delete a booking by ID.
	 *
	 * Also removes associated logs. Use with extreme caution; prefer
	 * cancellation in most workflows.
	 */
	public function delete( int $id ): bool {
		// Remove associated logs first.
		$logs_table = $this->db->table( 'booking_logs' );
		$this->db->query(
			"DELETE FROM {$logs_table} WHERE booking_id = %d",
			$id
		);

		return $this->db->delete( $this->table, [ 'id' => $id ] ) !== false;
	}

	// ── Audit Logs ──────────────────────────────────────────────────

	/**
	 * Create an audit log entry for a booking.
	 *
	 * @return BookingLog|false
	 */
	public function createLog( array $data ): BookingLog|false {
		$data['created_at'] = $data['created_at'] ?? current_time( 'mysql' );

		$id = $this->db->insert( 'booking_logs', $data );

		if ( $id === false ) {
			return false;
		}

		$table = $this->db->table( 'booking_logs' );
		$row   = $this->db->getRow(
			"SELECT * FROM {$table} WHERE id = %d",
			$id
		);

		return $row ? BookingLog::fromRow( $row ) : false;
	}

	/**
	 * Get all log entries for a booking, newest first.
	 *
	 * @return BookingLog[]
	 */
	public function getLogsForBooking( int $bookingId ): array {
		$table = $this->db->table( 'booking_logs' );
		$rows  = $this->db->getResults(
			"SELECT * FROM {$table} WHERE booking_id = %d ORDER BY created_at DESC",
			$bookingId
		);

		return BookingLog::fromRows( $rows );
	}

	// ── Paginated Listing ───────────────────────────────────────────

	/**
	 * List bookings with pagination, filtering, and sorting.
	 *
	 * @param array $args {
	 *     Optional. Arguments for listing bookings.
	 *
	 *     @type string $status    Filter by status.
	 *     @type string $source    Filter by source.
	 *     @type string $date_from Filter by check_in >= date (Y-m-d).
	 *     @type string $date_to   Filter by check_in <= date (Y-m-d).
	 *     @type string $search    Free-text search (booking number, guest name).
	 *     @type string $orderby   Column to order by. Default 'created_at'.
	 *     @type string $order     Sort direction (ASC|DESC). Default 'DESC'.
	 *     @type int    $per_page  Results per page. Default 20.
	 *     @type int    $page      Page number (1-based). Default 1.
	 * }
	 * @return array{ bookings: Booking[], total: int, pages: int }
	 */
	public function list( array $args = [] ): array {
		$defaults = [
			'status'    => '',
			'source'    => '',
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
		$guests     = $this->db->table( 'guests' );
		$offset     = ( $args['page'] - 1 ) * $args['per_page'];
		$conditions = [];
		$params     = [];

		// Status filter.
		if ( ! empty( $args['status'] ) ) {
			$conditions[] = 'b.status = %s';
			$params[]     = $args['status'];
		}

		// Source filter.
		if ( ! empty( $args['source'] ) ) {
			$conditions[] = 'b.source = %s';
			$params[]     = $args['source'];
		}

		// Date range filter (on check_in).
		if ( ! empty( $args['date_from'] ) ) {
			$conditions[] = 'b.check_in >= %s';
			$params[]     = $args['date_from'];
		}
		if ( ! empty( $args['date_to'] ) ) {
			$conditions[] = 'b.check_in <= %s';
			$params[]     = $args['date_to'];
		}

		// Free-text search.
		if ( ! empty( $args['search'] ) ) {
			$like         = '%' . $this->db->wpdb()->esc_like( $args['search'] ) . '%';
			$conditions[] = '(b.booking_number LIKE %s OR g.first_name LIKE %s OR g.last_name LIKE %s OR g.email LIKE %s)';
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
			'id', 'booking_number', 'check_in', 'check_out', 'status',
			'source', 'total_amount', 'paid_amount', 'nights', 'created_at',
		];
		$orderby = in_array( $args['orderby'], $allowed_columns, true ) ? $args['orderby'] : 'created_at';
		$order   = strtoupper( $args['order'] ) === 'ASC' ? 'ASC' : 'DESC';

		// Count total matching rows.
		$count_params = $params;
		$total        = (int) $this->db->getVar(
			"SELECT COUNT(*) FROM {$table} b LEFT JOIN {$guests} g ON b.guest_id = g.id {$where}",
			...$count_params
		);

		// Fetch paginated results.
		$params[] = $args['per_page'];
		$params[] = $offset;

		$rows = $this->db->getResults(
			"SELECT b.* FROM {$table} b
			 LEFT JOIN {$guests} g ON b.guest_id = g.id
			 {$where}
			 ORDER BY b.{$orderby} {$order}
			 LIMIT %d OFFSET %d",
			...$params
		);

		return [
			'bookings' => Booking::fromRows( $rows ),
			'total'    => $total,
			'pages'    => (int) ceil( $total / max( 1, $args['per_page'] ) ),
		];
	}

	// ── Dashboard Queries ───────────────────────────────────────────

	/**
	 * Get today's arrivals (bookings checking in today).
	 *
	 * @return Booking[]
	 */
	public function getTodayArrivals(): array {
		$table = $this->tableName();
		$today = current_time( 'Y-m-d' );

		$rows = $this->db->getResults(
			"SELECT * FROM {$table}
			 WHERE check_in = %s
			   AND status IN ('confirmed', 'pending')
			 ORDER BY created_at ASC",
			$today
		);

		return Booking::fromRows( $rows );
	}

	/**
	 * Get today's departures (bookings checking out today).
	 *
	 * @return Booking[]
	 */
	public function getTodayDepartures(): array {
		$table = $this->tableName();
		$today = current_time( 'Y-m-d' );

		$rows = $this->db->getResults(
			"SELECT * FROM {$table}
			 WHERE check_out = %s
			   AND status = 'checked_in'
			 ORDER BY created_at ASC",
			$today
		);

		return Booking::fromRows( $rows );
	}

	/**
	 * Get currently in-house guests (checked in, not yet checked out).
	 *
	 * @return Booking[]
	 */
	public function getInHouseGuests(): array {
		$table = $this->tableName();

		$rows = $this->db->getResults(
			"SELECT * FROM {$table}
			 WHERE status = 'checked_in'
			 ORDER BY check_out ASC"
		);

		return Booking::fromRows( $rows );
	}

	/**
	 * Get confirmed/pending bookings whose check-in date has passed (no-show candidates).
	 *
	 * @return Booking[]
	 */
	public function getNoShowCandidates(): array {
		$table = $this->tableName();
		$today = current_time( 'Y-m-d' );

		$rows = $this->db->getResults(
			"SELECT * FROM {$table}
			 WHERE check_in < %s
			   AND status IN ('confirmed', 'pending')
			 ORDER BY check_in ASC",
			$today
		);

		return Booking::fromRows( $rows );
	}

	/**
	 * Count active bookings for a room type on a given date range.
	 *
	 * Used to verify availability before creating a booking.
	 */
	public function countOverlapping( int $roomTypeId, string $checkIn, string $checkOut, ?int $excludeBookingId = null ): int {
		$table = $this->tableName();

		$exclude_clause = '';
		$params         = [ $roomTypeId, $checkOut, $checkIn ];

		if ( $excludeBookingId ) {
			$exclude_clause = 'AND id != %d';
			$params[]       = $excludeBookingId;
		}

		return (int) $this->db->getVar(
			"SELECT COUNT(*) FROM {$table}
			 WHERE room_type_id = %d
			   AND status IN ('confirmed', 'checked_in', 'pending')
			   AND check_in < %s
			   AND check_out > %s
			   {$exclude_clause}",
			...$params
		);
	}

	/**
	 * Get total number of rooms occupied (status = checked_in) right now.
	 */
	public function countInHouse(): int {
		$table = $this->tableName();

		return (int) $this->db->getVar(
			"SELECT COUNT(*) FROM {$table} WHERE status = 'checked_in'"
		);
	}
}
