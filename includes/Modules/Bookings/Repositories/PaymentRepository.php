<?php

namespace Venezia\Modules\Bookings\Repositories;

use Venezia\Core\BaseRepository;
use Venezia\Core\Database;
use Venezia\Modules\Bookings\Models\Payment;

/**
 * Repository for payment database operations.
 */
class PaymentRepository extends BaseRepository {

	protected string $table = 'payments';
	protected string $model = Payment::class;

	public function __construct( Database $db ) {
		parent::__construct( $db );
	}

	/**
	 * Get all payments for a booking.
	 *
	 * @return Payment[]
	 */
	public function getByBookingId( int $bookingId ): array {
		$table = $this->tableName();
		$rows  = $this->db->getResults(
			"SELECT * FROM {$table} WHERE booking_id = %d ORDER BY payment_date DESC",
			$bookingId
		);

		return Payment::fromRows( $rows );
	}

	/**
	 * Create a new payment record.
	 *
	 * @return Payment|false
	 */
	public function create( array $data ): Payment|false {
		$now = current_time( 'mysql' );
		$data['created_at']   = $data['created_at'] ?? $now;
		$data['updated_at']   = $data['updated_at'] ?? $now;
		$data['payment_date'] = $data['payment_date'] ?? $now;

		$id = $this->db->insert( $this->table, $data );

		if ( $id === false ) {
			return false;
		}

		return $this->find( $id );
	}

	/**
	 * Update a payment record.
	 */
	public function update( int $id, array $data ): bool {
		$data['updated_at'] = current_time( 'mysql' );

		return $this->db->update( $this->table, $data, [ 'id' => $id ] ) !== false;
	}

	/**
	 * Get the total amount paid (completed payments) for a booking.
	 */
	public function getTotalPaidForBooking( int $bookingId ): float {
		$table = $this->tableName();

		$total = $this->db->getVar(
			"SELECT COALESCE(SUM(amount), 0) FROM {$table}
			 WHERE booking_id = %d AND status = 'completed'",
			$bookingId
		);

		return round( (float) $total, 2 );
	}

	/**
	 * Get the total amount refunded for a booking.
	 */
	public function getTotalRefundedForBooking( int $bookingId ): float {
		$table = $this->tableName();

		$total = $this->db->getVar(
			"SELECT COALESCE(SUM(amount), 0) FROM {$table}
			 WHERE booking_id = %d AND status = 'refunded'",
			$bookingId
		);

		return round( (float) $total, 2 );
	}
}
