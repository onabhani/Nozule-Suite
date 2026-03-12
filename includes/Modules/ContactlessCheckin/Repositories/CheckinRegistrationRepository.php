<?php

namespace Nozule\Modules\ContactlessCheckin\Repositories;

use Nozule\Core\BaseRepository;
use Nozule\Core\Database;
use Nozule\Modules\ContactlessCheckin\Models\CheckinRegistration;

/**
 * Repository for contactless check-in registration records.
 */
class CheckinRegistrationRepository extends BaseRepository {

	protected string $table = 'checkin_registrations';
	protected string $model = CheckinRegistration::class;

	public function __construct( Database $db ) {
		parent::__construct( $db );
	}

	/**
	 * Create a new registration record.
	 *
	 * @return CheckinRegistration|false
	 */
	public function create( array $data ): CheckinRegistration|false {
		$now = current_time( 'mysql', true );
		$data['created_at'] = $data['created_at'] ?? $now;
		$data['updated_at'] = $data['updated_at'] ?? $now;

		// Encode JSON fields.
		if ( isset( $data['guest_details'] ) && is_array( $data['guest_details'] ) ) {
			$data['guest_details'] = wp_json_encode( $data['guest_details'] );
		}
		if ( isset( $data['document_ids'] ) && is_array( $data['document_ids'] ) ) {
			$data['document_ids'] = wp_json_encode( $data['document_ids'] );
		}

		$id = $this->db->insert( $this->table, $data );
		if ( $id === false ) {
			return false;
		}

		return $this->find( $id );
	}

	/**
	 * Update a registration record.
	 */
	public function update( int $id, array $data ): bool {
		$data['updated_at'] = current_time( 'mysql', true );

		if ( isset( $data['guest_details'] ) && is_array( $data['guest_details'] ) ) {
			$data['guest_details'] = wp_json_encode( $data['guest_details'] );
		}
		if ( isset( $data['document_ids'] ) && is_array( $data['document_ids'] ) ) {
			$data['document_ids'] = wp_json_encode( $data['document_ids'] );
		}

		return $this->db->update( $this->table, $data, [ 'id' => $id ] ) !== false;
	}

	/**
	 * Find a registration by its public token.
	 */
	public function findByToken( string $token ): ?CheckinRegistration {
		$table = $this->tableName();
		$row   = $this->db->getRow(
			"SELECT * FROM {$table} WHERE token = %s LIMIT 1",
			$token
		);

		return $row ? CheckinRegistration::fromRow( $row ) : null;
	}

	/**
	 * Find a registration by booking ID.
	 */
	public function findByBooking( int $bookingId ): ?CheckinRegistration {
		$table = $this->tableName();
		$row   = $this->db->getRow(
			"SELECT * FROM {$table} WHERE booking_id = %d ORDER BY created_at DESC LIMIT 1",
			$bookingId
		);

		return $row ? CheckinRegistration::fromRow( $row ) : null;
	}

	/**
	 * Get registrations filtered by status with booking info.
	 *
	 * @return CheckinRegistration[]
	 */
	public function getFiltered( ?string $status = null, int $limit = 50, int $offset = 0 ): array {
		$table          = $this->tableName();
		$bookings_table = $this->db->table( 'bookings' );
		$guests_table   = $this->db->table( 'guests' );

		$sql  = "SELECT cr.*, b.booking_number, b.check_in, b.check_out, b.status AS booking_status,
				g.first_name AS guest_first_name, g.last_name AS guest_last_name, g.email AS guest_email
			FROM {$table} cr
			LEFT JOIN {$bookings_table} b ON cr.booking_id = b.id
			LEFT JOIN {$guests_table} g ON cr.guest_id = g.id
			WHERE 1=1";
		$args = [];

		if ( $status ) {
			$sql   .= ' AND cr.status = %s';
			$args[] = $status;
		}

		$sql    = $this->applyPropertyScope( $sql, $args, 'cr.property_id' );
		$sql   .= ' ORDER BY cr.created_at DESC LIMIT %d OFFSET %d';
		$args[] = $limit;
		$args[] = $offset;

		$rows = $this->db->getResults( $sql, ...$args );

		return CheckinRegistration::fromRows( $rows );
	}

	/**
	 * Count registrations with optional status filter.
	 */
	public function countFiltered( ?string $status = null ): int {
		$table = $this->tableName();
		$sql   = "SELECT COUNT(*) FROM {$table} WHERE 1=1";
		$args  = [];

		if ( $status ) {
			$sql   .= ' AND status = %s';
			$args[] = $status;
		}

		$sql = $this->applyPropertyScope( $sql, $args );

		return (int) $this->db->getVar( $sql, ...$args );
	}

	/**
	 * Get status counts for dashboard.
	 *
	 * @return array<string, int>
	 */
	public function countByStatus(): array {
		$table   = $this->tableName();
		$results = $this->db->getResults(
			"SELECT status, COUNT(*) AS total FROM {$table} GROUP BY status"
		);

		$counts = [];
		foreach ( $results as $row ) {
			$counts[ $row->status ] = (int) $row->total;
		}

		foreach ( CheckinRegistration::validStatuses() as $status ) {
			if ( ! isset( $counts[ $status ] ) ) {
				$counts[ $status ] = 0;
			}
		}

		return $counts;
	}

	/**
	 * Delete expired pending registrations older than the given date.
	 */
	public function deleteExpired( string $beforeDate ): int {
		$table = $this->tableName();

		return (int) $this->db->getVar(
			"DELETE FROM {$table} WHERE status = 'pending' AND expires_at < %s",
			$beforeDate
		);
	}
}
