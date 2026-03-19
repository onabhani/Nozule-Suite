<?php

namespace Nozule\Modules\Audit\Services;

use Nozule\Core\Database;
use Nozule\Core\Logger;
use Nozule\Modules\Audit\Models\NightAudit;
use Nozule\Modules\Audit\Repositories\NightAuditRepository;
use Nozule\Modules\Rooms\Services\AvailabilityService;

/**
 * Night Audit business-logic service.
 *
 * Orchestrates the end-of-day audit process: gathering room status,
 * booking statistics, revenue figures, and payment totals from across
 * the system, then persisting the snapshot and performing housekeeping
 * tasks such as marking no-show bookings.
 */
class NightAuditService {

	private NightAuditRepository $repository;
	private Database $db;
	private Logger $logger;
	private AvailabilityService $availabilityService;

	public function __construct(
		NightAuditRepository $repository,
		Database $db,
		Logger $logger,
		AvailabilityService $availabilityService
	) {
		$this->repository          = $repository;
		$this->db                  = $db;
		$this->logger              = $logger;
		$this->availabilityService = $availabilityService;
	}

	// ── Main Audit Process ─────────────────────────────────────────

	/**
	 * Run the night audit for a given date.
	 *
	 * Collects room, booking, revenue, and payment data across multiple
	 * tables, calculates KPIs, inserts the audit record, marks no-show
	 * bookings, and dispatches the completion event.
	 *
	 * @param string|null $date Audit date in Y-m-d format. Defaults to yesterday.
	 * @return NightAudit|array NightAudit model on success, error array on failure.
	 */
	/** @var int|null Property ID to scope audit queries to. */
	private ?int $propertyId = null;

	/**
	 * Scope this audit run to a specific property.
	 */
	public function forProperty( int $propertyId ): static {
		$clone = clone $this;
		$clone->propertyId = $propertyId;
		return $clone;
	}

	public function runAudit( ?string $date = null ): NightAudit|array {
		$date = $date ?: wp_date( 'Y-m-d', strtotime( '-1 day' ) );

		// 1. Check if audit already exists for this date.
		if ( $this->repository->hasAuditForDate( $date ) ) {
			return [
				'error'   => true,
				'message' => sprintf(
					/* translators: %s: audit date */
					__( 'Night audit for %s has already been completed.', 'nozule' ),
					$date
				),
			];
		}

		$this->repository->beginTransaction();

		try {
			// 2. Count rooms by status from nzl_rooms.
			$roomStats = $this->getRoomStats();

			// 3. Count booking activity for the audit date.
			$bookingStats = $this->getBookingStats( $date );

			// 4. Calculate occupied rooms (bookings spanning the audit date).
			$occupiedRooms = $this->getOccupiedRooms( $date );

			// 5. Query payment collections for the audit date.
			$paymentStats = $this->getPaymentStats( $date );

			// 6. Calculate revenue from folio items for the audit date.
			$revenueStats = $this->getRevenueStats( $date );

			// 7. Derived KPIs.
			$totalRooms    = (int) $roomStats->total_rooms;
			$availableRooms = max( 0, $totalRooms - $occupiedRooms - (int) $roomStats->out_of_order_rooms );
			$occupancyRate = $totalRooms > 0
				? round( ( $occupiedRooms / $totalRooms ) * 100, 2 )
				: 0.00;

			$roomRevenue  = (float) $revenueStats->room_revenue;
			$otherRevenue = (float) $revenueStats->other_revenue;
			$totalRevenue = round( $roomRevenue + $otherRevenue, 2 );

			// 8. ADR = room_revenue / occupied_rooms (0 if no occupied rooms).
			$adr = $occupiedRooms > 0
				? round( $roomRevenue / $occupiedRooms, 2 )
				: 0.00;

			// 9. RevPAR = room_revenue / total_rooms (0 if no rooms).
			$revpar = $totalRooms > 0
				? round( $roomRevenue / $totalRooms, 2 )
				: 0.00;

			// 10. Insert the night audit record.
			$audit = $this->repository->create( [
				'audit_date'        => $date,
				'total_rooms'       => $totalRooms,
				'occupied_rooms'    => $occupiedRooms,
				'available_rooms'   => $availableRooms,
				'out_of_order_rooms'=> (int) $roomStats->out_of_order_rooms,
				'occupancy_rate'    => $occupancyRate,
				'room_revenue'      => $roomRevenue,
				'other_revenue'     => $otherRevenue,
				'total_revenue'     => $totalRevenue,
				'adr'               => $adr,
				'revpar'            => $revpar,
				'arrivals'          => (int) $bookingStats->arrivals,
				'departures'        => (int) $bookingStats->departures,
				'no_shows'          => (int) $bookingStats->no_shows,
				'walk_ins'          => (int) $bookingStats->walk_ins,
				'cancellations'     => (int) $bookingStats->cancellations,
				'total_guests'      => (int) $bookingStats->total_guests,
				'cash_collected'    => (float) $paymentStats->cash_collected,
				'card_collected'    => (float) $paymentStats->card_collected,
				'other_collected'   => (float) $paymentStats->other_collected,
				'run_by'            => get_current_user_id() ?: null,
				'run_at'            => current_time( 'mysql' ),
				'status'            => NightAudit::STATUS_COMPLETED,
			] );

			if ( ! $audit ) {
				throw new \RuntimeException(
					__( 'Failed to insert night audit record.', 'nozule' )
				);
			}

			// 11. Mark no-show bookings: update bookings where check_in = date
			//     AND status = confirmed → status = no_show.
			$this->markNoShowBookings( $date );

			$this->repository->commit();
		} catch ( \Throwable $e ) {
			$this->repository->rollback();

			$this->logger->error( 'Night audit failed', [
				'date'  => $date,
				'error' => $e->getMessage(),
			] );

			return [
				'error'   => true,
				'message' => sprintf(
					/* translators: %s: error message */
					__( 'Night audit failed: %s', 'nozule' ),
					$e->getMessage()
				),
			];
		}

		// 12. Log the successful audit.
		$this->logger->info( 'Night audit completed', [
			'audit_id'   => $audit->id,
			'audit_date' => $date,
			'occupancy'  => $occupancyRate . '%',
			'revenue'    => $totalRevenue,
		] );

		// 13. Dispatch completion event.
		/**
		 * Fires after a night audit has been successfully completed.
		 *
		 * @param NightAudit $audit The completed audit record.
		 * @param string     $date  The audit date (Y-m-d).
		 */
		do_action( 'nozule/audit/night_audit_completed', $audit, $date );

		return $audit;
	}

	// ── Query Methods ──────────────────────────────────────────────

	/**
	 * Get a single audit by ID.
	 */
	public function getAudit( int $id ): ?NightAudit {
		return $this->repository->find( $id );
	}

	/**
	 * Get an audit by date.
	 *
	 * @param string $date Y-m-d format.
	 */
	public function getAuditByDate( string $date ): ?NightAudit {
		return $this->repository->findByDate( $date );
	}

	/**
	 * Get the most recent audits.
	 *
	 * @param int $limit Number of records to return.
	 * @return NightAudit[]
	 */
	public function getRecentAudits( int $limit = 30 ): array {
		return $this->repository->getRecent( $limit );
	}

	/**
	 * Get aggregated summary statistics over a date range.
	 *
	 * @param string $from Start date (Y-m-d).
	 * @param string $to   End date (Y-m-d).
	 * @return array{
	 *     audits: NightAudit[],
	 *     summary: array{
	 *         total_audits: int,
	 *         avg_occupancy_rate: float,
	 *         total_room_revenue: float,
	 *         total_other_revenue: float,
	 *         total_revenue: float,
	 *         avg_adr: float,
	 *         avg_revpar: float,
	 *         total_arrivals: int,
	 *         total_departures: int,
	 *         total_no_shows: int,
	 *         total_walk_ins: int,
	 *         total_cancellations: int,
	 *         total_cash_collected: float,
	 *         total_card_collected: float,
	 *         total_other_collected: float,
	 *         total_collected: float,
	 *     }
	 * }
	 */
	public function getAuditSummary( string $from, string $to ): array {
		$audits = $this->repository->getByDateRange( $from, $to );

		$table = $this->db->table( 'night_audits' );
		$summary = $this->db->getRow(
			"SELECT
				COUNT(*) AS total_audits,
				ROUND( AVG( occupancy_rate ), 2 ) AS avg_occupancy_rate,
				ROUND( SUM( room_revenue ), 2 ) AS total_room_revenue,
				ROUND( SUM( other_revenue ), 2 ) AS total_other_revenue,
				ROUND( SUM( total_revenue ), 2 ) AS total_revenue,
				ROUND( AVG( adr ), 2 ) AS avg_adr,
				ROUND( AVG( revpar ), 2 ) AS avg_revpar,
				SUM( arrivals ) AS total_arrivals,
				SUM( departures ) AS total_departures,
				SUM( no_shows ) AS total_no_shows,
				SUM( walk_ins ) AS total_walk_ins,
				SUM( cancellations ) AS total_cancellations,
				ROUND( SUM( cash_collected ), 2 ) AS total_cash_collected,
				ROUND( SUM( card_collected ), 2 ) AS total_card_collected,
				ROUND( SUM( other_collected ), 2 ) AS total_other_collected,
				ROUND( SUM( cash_collected ) + SUM( card_collected ) + SUM( other_collected ), 2 ) AS total_collected
			FROM {$table}
			WHERE audit_date >= %s
			  AND audit_date <= %s
			  AND status = %s",
			$from,
			$to,
			NightAudit::STATUS_COMPLETED
		);

		return [
			'audits'  => $audits,
			'summary' => [
				'total_audits'         => $summary ? (int) $summary->total_audits : 0,
				'avg_occupancy_rate'   => $summary ? (float) $summary->avg_occupancy_rate : 0.0,
				'total_room_revenue'   => $summary ? (float) $summary->total_room_revenue : 0.0,
				'total_other_revenue'  => $summary ? (float) $summary->total_other_revenue : 0.0,
				'total_revenue'        => $summary ? (float) $summary->total_revenue : 0.0,
				'avg_adr'              => $summary ? (float) $summary->avg_adr : 0.0,
				'avg_revpar'           => $summary ? (float) $summary->avg_revpar : 0.0,
				'total_arrivals'       => $summary ? (int) $summary->total_arrivals : 0,
				'total_departures'     => $summary ? (int) $summary->total_departures : 0,
				'total_no_shows'       => $summary ? (int) $summary->total_no_shows : 0,
				'total_walk_ins'       => $summary ? (int) $summary->total_walk_ins : 0,
				'total_cancellations'  => $summary ? (int) $summary->total_cancellations : 0,
				'total_cash_collected' => $summary ? (float) $summary->total_cash_collected : 0.0,
				'total_card_collected' => $summary ? (float) $summary->total_card_collected : 0.0,
				'total_other_collected'=> $summary ? (float) $summary->total_other_collected : 0.0,
				'total_collected'      => $summary ? (float) $summary->total_collected : 0.0,
			],
		];
	}

	// ── Private Data Gathering ─────────────────────────────────────

	/**
	 * Build a property_id SQL condition fragment.
	 *
	 * @param string $alias Table alias (e.g. 'b' or empty string).
	 * @param array  $args  Bind-parameter array (modified by reference).
	 * @return string SQL fragment like " AND b.property_id = %d" or empty string.
	 */
	private function propertyCondition( string $alias, array &$args ): string {
		if ( $this->propertyId === null ) {
			return '';
		}
		$prefix = $alias ? "{$alias}." : '';
		$args[] = $this->propertyId;
		return " AND {$prefix}property_id = %d";
	}

	private function getRoomStats(): object {
		$rooms = $this->db->table( 'rooms' );
		$args  = [];
		$propCond = $this->propertyCondition( '', $args );

		$sql = "SELECT
				COUNT(*) AS total_rooms,
				SUM( CASE WHEN status IN ('out_of_order', 'maintenance') THEN 1 ELSE 0 END ) AS out_of_order_rooms
			FROM {$rooms} WHERE 1=1{$propCond}";

		$row = empty( $args )
			? $this->db->getRow( $sql )
			: $this->db->getRow( $sql, ...$args );

		return (object) [
			'total_rooms'       => $row ? (int) $row->total_rooms : 0,
			'out_of_order_rooms'=> $row ? (int) $row->out_of_order_rooms : 0,
		];
	}

	/**
	 * Get booking activity statistics for the audit date.
	 *
	 * @param string $date Y-m-d format.
	 * @return object{arrivals: int, departures: int, no_shows: int, walk_ins: int, cancellations: int, total_guests: int}
	 */
	private function getBookingStats( string $date ): object {
		$bookings = $this->db->table( 'bookings' );
		$nextDate = ( new \DateTimeImmutable( $date ) )->modify( '+1 day' )->format( 'Y-m-d' );

		// Single aggregate query instead of 6 separate queries (perf fix P8).
		// Uses range comparison instead of DATE() to preserve index use (perf fix P4).
		$args = [];
		$propCond = $this->propertyCondition( '', $args );

		$row = $this->db->getRow(
			"SELECT
				SUM( CASE WHEN check_in = %s AND status IN ('confirmed', 'checked_in') THEN 1 ELSE 0 END ) AS arrivals,
				SUM( CASE WHEN check_out = %s AND status = 'checked_out' THEN 1 ELSE 0 END ) AS departures,
				SUM( CASE WHEN check_in = %s AND status = 'confirmed' THEN 1 ELSE 0 END ) AS no_shows,
				SUM( CASE WHEN check_in = %s AND source = 'walk_in' AND status NOT IN ('cancelled', 'no_show') THEN 1 ELSE 0 END ) AS walk_ins,
				SUM( CASE WHEN cancelled_at >= %s AND cancelled_at < %s AND status = 'cancelled' THEN 1 ELSE 0 END ) AS cancellations,
				COALESCE( SUM( CASE WHEN check_in <= %s AND check_out > %s AND status IN ('confirmed', 'checked_in') THEN adults + children ELSE 0 END ), 0 ) AS total_guests
			FROM {$bookings}
			WHERE 1=1{$propCond}",
			$date, $date, $date, $date, $date, $nextDate, $date, $date, ...$args
		);

		return (object) [
			'arrivals'      => $row ? (int) $row->arrivals : 0,
			'departures'    => $row ? (int) $row->departures : 0,
			'no_shows'      => $row ? (int) $row->no_shows : 0,
			'walk_ins'      => $row ? (int) $row->walk_ins : 0,
			'cancellations' => $row ? (int) $row->cancellations : 0,
			'total_guests'  => $row ? (int) $row->total_guests : 0,
		];
	}

	/**
	 * Count occupied rooms — bookings that span the audit date.
	 *
	 * A room is considered occupied if a booking's stay includes the
	 * audit date (check_in <= date AND check_out > date) and the booking
	 * is in an active status.
	 *
	 * @param string $date Y-m-d format.
	 */
	private function getOccupiedRooms( string $date ): int {
		$bookings = $this->db->table( 'bookings' );
		$args     = [ $date, $date ];
		$propCond = $this->propertyCondition( '', $args );

		return (int) $this->db->getVar(
			"SELECT COUNT(*) FROM {$bookings}
			 WHERE check_in <= %s
			   AND check_out > %s
			   AND status IN ('confirmed', 'checked_in'){$propCond}",
			...$args
		);
	}

	/**
	 * Get payment collection totals for the audit date, grouped by method.
	 *
	 * @param string $date Y-m-d format.
	 * @return object{cash_collected: float, card_collected: float, other_collected: float}
	 */
	private function getPaymentStats( string $date ): object {
		$payments = $this->db->table( 'payments' );
		$nextDate = ( new \DateTimeImmutable( $date ) )->modify( '+1 day' )->format( 'Y-m-d' );
		$args     = [ $date, $nextDate ];
		$propCond = $this->propertyCondition( '', $args );

		$row = $this->db->getRow(
			"SELECT
				COALESCE( SUM( CASE WHEN method = 'cash' THEN amount ELSE 0 END ), 0 ) AS cash_collected,
				COALESCE( SUM( CASE WHEN method = 'credit_card' THEN amount ELSE 0 END ), 0 ) AS card_collected,
				COALESCE( SUM( CASE WHEN method NOT IN ('cash', 'credit_card') THEN amount ELSE 0 END ), 0 ) AS other_collected
			FROM {$payments}
			WHERE payment_date >= %s
			  AND payment_date < %s
			  AND status = 'completed'{$propCond}",
			...$args
		);

		return (object) [
			'cash_collected'  => $row ? (float) $row->cash_collected : 0.0,
			'card_collected'  => $row ? (float) $row->card_collected : 0.0,
			'other_collected' => $row ? (float) $row->other_collected : 0.0,
		];
	}

	/**
	 * Calculate revenue from folio items for the audit date.
	 *
	 * Room charges are categorised as room_revenue; everything else
	 * (F&B, minibar, services, etc.) is other_revenue.
	 *
	 * @param string $date Y-m-d format.
	 * @return object{room_revenue: float, other_revenue: float}
	 */
	private function getRevenueStats( string $date ): object {
		$folios     = $this->db->table( 'folios' );
		$folioItems = $this->db->table( 'folio_items' );
		$args       = [ $date ];
		$propCond   = $this->propertyCondition( 'f', $args );

		$row = $this->db->getRow(
			"SELECT
				COALESCE( SUM( CASE WHEN fi.category = 'room_charge' THEN fi.total ELSE 0 END ), 0 ) AS room_revenue,
				COALESCE( SUM( CASE WHEN fi.category NOT IN ('room_charge', 'discount', 'payment') THEN fi.total ELSE 0 END ), 0 ) AS other_revenue
			FROM {$folioItems} fi
			INNER JOIN {$folios} f ON f.id = fi.folio_id
			WHERE fi.date = %s{$propCond}",
			...$args
		);

		return (object) [
			'room_revenue'  => $row ? (float) $row->room_revenue : 0.0,
			'other_revenue' => $row ? (float) $row->other_revenue : 0.0,
		];
	}

	/**
	 * Mark no-show bookings for the audit date.
	 *
	 * Updates bookings where check_in = date AND status = confirmed
	 * to status = no_show.
	 *
	 * @param string $date Y-m-d format.
	 * @return int Number of bookings marked as no-show.
	 */
	private function markNoShowBookings( string $date ): int {
		$bookings = $this->db->table( 'bookings' );

		// Fetch no-show candidates so we can restore their inventory.
		$candidates = $this->db->getResults(
			"SELECT id, room_type_id, check_in, check_out FROM {$bookings}
			 WHERE check_in = %s
			   AND status = 'confirmed'",
			$date
		);

		if ( empty( $candidates ) ) {
			return 0;
		}

		$count = 0;

		foreach ( $candidates as $booking ) {
			// Restore inventory that was deducted when this booking was created.
			$this->availabilityService->restoreInventory(
				(int) $booking->room_type_id,
				$booking->check_in,
				$booking->check_out
			);

			$this->db->query(
				"UPDATE {$bookings}
				 SET status = 'no_show',
				     updated_at = %s
				 WHERE id = %d
				   AND status = 'confirmed'",
				current_time( 'mysql' ),
				(int) $booking->id
			);

			$count++;
		}

		if ( $count > 0 ) {
			$this->logger->info( 'Marked bookings as no-show with inventory restored', [
				'date'  => $date,
				'count' => $count,
			] );
		}

		return $count;
	}
}
