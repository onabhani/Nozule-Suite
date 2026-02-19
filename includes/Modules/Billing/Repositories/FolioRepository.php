<?php

namespace Nozule\Modules\Billing\Repositories;

use Nozule\Core\BaseRepository;
use Nozule\Core\Database;
use Nozule\Modules\Billing\Models\Folio;

/**
 * Repository for folio CRUD and querying.
 */
class FolioRepository extends BaseRepository {

	protected string $table = 'folios';
	protected string $model = Folio::class;

	public function __construct( Database $db ) {
		parent::__construct( $db );
	}

	/**
	 * Find a folio by booking ID.
	 */
	public function findByBooking( int $bookingId ): ?Folio {
		$table = $this->tableName();
		$row   = $this->db->getRow(
			"SELECT * FROM {$table} WHERE booking_id = %d ORDER BY id DESC LIMIT 1",
			$bookingId
		);

		return $row ? Folio::fromRow( $row ) : null;
	}

	/**
	 * Find a folio by group booking ID.
	 */
	public function findByGroupBooking( int $groupBookingId ): ?Folio {
		$table = $this->tableName();
		$row   = $this->db->getRow(
			"SELECT * FROM {$table} WHERE group_booking_id = %d ORDER BY id DESC LIMIT 1",
			$groupBookingId
		);

		return $row ? Folio::fromRow( $row ) : null;
	}

	/**
	 * Get all folios for a guest.
	 *
	 * @return Folio[]
	 */
	public function getByGuest( int $guestId ): array {
		$table = $this->tableName();
		$rows  = $this->db->getResults(
			"SELECT * FROM {$table} WHERE guest_id = %d ORDER BY created_at DESC",
			$guestId
		);

		return Folio::fromRows( $rows );
	}

	/**
	 * Get folios by status.
	 *
	 * @return Folio[]
	 */
	public function getByStatus( string $status ): array {
		$table = $this->tableName();
		$rows  = $this->db->getResults(
			"SELECT * FROM {$table} WHERE status = %s ORDER BY created_at DESC",
			$status
		);

		return Folio::fromRows( $rows );
	}

	/**
	 * Get all open folios.
	 *
	 * @return Folio[]
	 */
	public function getOpenFolios(): array {
		return $this->getByStatus( Folio::STATUS_OPEN );
	}

	/**
	 * Get all folios with optional filters.
	 *
	 * @return Folio[]
	 */
	public function getAllFiltered( ?string $status = null, ?int $bookingId = null, ?int $guestId = null ): array {
		$table      = $this->tableName();
		$conditions = [];
		$values     = [];

		if ( $status ) {
			$conditions[] = 'status = %s';
			$values[]     = $status;
		}

		if ( $bookingId ) {
			$conditions[] = 'booking_id = %d';
			$values[]     = $bookingId;
		}

		if ( $guestId ) {
			$conditions[] = 'guest_id = %d';
			$values[]     = $guestId;
		}

		$where = '';
		if ( ! empty( $conditions ) ) {
			$where = 'WHERE ' . implode( ' AND ', $conditions );
		}

		$sql = "SELECT * FROM {$table} {$where} ORDER BY created_at DESC";

		if ( ! empty( $values ) ) {
			$rows = $this->db->getResults( $sql, ...$values );
		} else {
			$rows = $this->db->getResults( $sql );
		}

		return Folio::fromRows( $rows );
	}

	/**
	 * Generate a unique folio number in the format INV-YYYY-NNNNN.
	 */
	public function generateFolioNumber(): string {
		$table = $this->tableName();
		$year  = gmdate( 'Y' );

		$count = (int) $this->db->getVar(
			"SELECT COUNT(*) FROM {$table} WHERE folio_number LIKE %s",
			'INV-' . $year . '-%'
		);

		$next = $count + 1;

		return sprintf( 'INV-%s-%05d', $year, $next );
	}

	/**
	 * Create a new folio.
	 *
	 * @return Folio|false
	 */
	public function create( array $data ): Folio|false {
		$now = current_time( 'mysql', true );
		$data['created_at'] = $data['created_at'] ?? $now;
		$data['updated_at'] = $data['updated_at'] ?? $now;

		if ( empty( $data['folio_number'] ) ) {
			$data['folio_number'] = $this->generateFolioNumber();
		}

		$id = $this->db->insert( $this->table, $data );
		if ( $id === false ) {
			return false;
		}

		return $this->find( $id );
	}

	/**
	 * Update a folio.
	 */
	public function update( int $id, array $data ): bool {
		$data['updated_at'] = current_time( 'mysql', true );

		return $this->db->update( $this->table, $data, [ 'id' => $id ] ) !== false;
	}

	/**
	 * Recalculate folio totals from all its items.
	 *
	 * Queries all folio items, sums subtotals, taxes, discounts, and payments,
	 * then updates the folio record.
	 */
	public function recalculateTotals( int $folioId ): bool {
		$items_table = $this->db->table( 'folio_items' );

		// Sum charges (room_charge, extra, service, tax_adjustment).
		$chargeRow = $this->db->getRow(
			"SELECT
				COALESCE(SUM(subtotal), 0) AS subtotal_sum,
				COALESCE(SUM(tax_total), 0) AS tax_total_sum
			FROM {$items_table}
			WHERE folio_id = %d
			AND category IN ('room_charge', 'extra', 'service', 'tax_adjustment')",
			$folioId
		);

		// Sum discounts.
		$discountTotal = (float) $this->db->getVar(
			"SELECT COALESCE(SUM(total), 0) FROM {$items_table} WHERE folio_id = %d AND category = 'discount'",
			$folioId
		);

		// Sum payments.
		$paidAmount = (float) $this->db->getVar(
			"SELECT COALESCE(SUM(total), 0) FROM {$items_table} WHERE folio_id = %d AND category = 'payment'",
			$folioId
		);

		$subtotal  = (float) ( $chargeRow->subtotal_sum ?? 0 );
		$taxTotal  = (float) ( $chargeRow->tax_total_sum ?? 0 );
		$grandTotal = round( $subtotal + $taxTotal - abs( $discountTotal ), 2 );

		return $this->update( $folioId, [
			'subtotal'       => $subtotal,
			'tax_total'      => $taxTotal,
			'discount_total' => abs( $discountTotal ),
			'grand_total'    => $grandTotal,
			'paid_amount'    => abs( $paidAmount ),
		] );
	}
}
