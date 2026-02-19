<?php

namespace Nozule\Modules\Groups\Services;

use Nozule\Core\EventDispatcher;
use Nozule\Core\Logger;
use Nozule\Core\SettingsManager;
use Nozule\Modules\Bookings\Services\BookingService;
use Nozule\Modules\Groups\Models\GroupBooking;
use Nozule\Modules\Groups\Models\GroupBookingRoom;
use Nozule\Modules\Groups\Repositories\GroupBookingRepository;
use Nozule\Modules\Groups\Repositories\GroupBookingRoomRepository;
use Nozule\Modules\Groups\Validators\GroupBookingValidator;

/**
 * Central group booking business-logic service.
 *
 * Orchestrates the full group booking lifecycle: creation, confirmation,
 * bulk check-in/check-out, cancellation, room allocation management,
 * and financial recalculation.
 */
class GroupBookingService {

	private GroupBookingRepository $groupRepo;
	private GroupBookingRoomRepository $roomRepo;
	private GroupBookingValidator $validator;
	private BookingService $bookingService;
	private EventDispatcher $events;
	private Logger $logger;
	private SettingsManager $settings;

	public function __construct(
		GroupBookingRepository $groupRepo,
		GroupBookingRoomRepository $roomRepo,
		GroupBookingValidator $validator,
		BookingService $bookingService,
		EventDispatcher $events,
		Logger $logger,
		SettingsManager $settings
	) {
		$this->groupRepo      = $groupRepo;
		$this->roomRepo       = $roomRepo;
		$this->validator      = $validator;
		$this->bookingService = $bookingService;
		$this->events         = $events;
		$this->logger         = $logger;
		$this->settings       = $settings;
	}

	// ── Listing / Retrieval ─────────────────────────────────────────

	/**
	 * List group bookings with optional filters.
	 *
	 * @param array $filters {
	 *     @type string $status    Filter by status.
	 *     @type string $date_from Filter by check_in >= date.
	 *     @type string $date_to   Filter by check_in <= date.
	 *     @type string $search    Free-text search.
	 *     @type string $orderby   Column to order by.
	 *     @type string $order     Sort direction.
	 *     @type int    $per_page  Results per page.
	 *     @type int    $page      Page number.
	 * }
	 * @return array{ groups: GroupBooking[], total: int, pages: int }
	 */
	public function getGroups( array $filters = [] ): array {
		return $this->groupRepo->list( $filters );
	}

	/**
	 * Get a single group booking with room count.
	 */
	public function getGroup( int $id ): ?array {
		$group = $this->groupRepo->find( $id );

		if ( ! $group ) {
			return null;
		}

		$roomCount = $this->roomRepo->countByGroupAndStatus( $id );

		$data               = $group->toArray();
		$data['room_count'] = $roomCount;

		return $data;
	}

	/**
	 * Get a group booking with all room allocations and details.
	 */
	public function getGroupWithRooms( int $id ): ?array {
		$group = $this->groupRepo->find( $id );

		if ( ! $group ) {
			return null;
		}

		$rooms = $this->roomRepo->getByGroupWithDetails( $id );

		$data          = $group->toArray();
		$data['rooms'] = $rooms;

		return $data;
	}

	// ── Group Lifecycle ─────────────────────────────────────────────

	/**
	 * Create a new group booking.
	 *
	 * Validates input, generates a group number, calculates nights,
	 * and persists the group booking.
	 *
	 * @param array $data Group booking data.
	 * @throws \InvalidArgumentException When validation fails.
	 */
	public function createGroup( array $data ): GroupBooking {
		// 1. Validate input.
		if ( ! $this->validator->validateCreate( $data ) ) {
			throw new \InvalidArgumentException(
				implode( ' ', $this->validator->getAllErrors() )
			);
		}

		// 2. Calculate nights.
		$checkIn  = $data['check_in'];
		$checkOut = $data['check_out'];
		$nights   = (int) ( ( strtotime( $checkOut ) - strtotime( $checkIn ) ) / DAY_IN_SECONDS );

		// 3. Generate group number.
		$groupNumber = $this->groupRepo->generateGroupNumber();

		// 4. Default currency from settings.
		$currency = $data['currency'] ?? $this->settings->get( 'currency.default', 'USD' );

		// 5. Persist.
		$group = $this->groupRepo->create( [
			'group_number'   => $groupNumber,
			'group_name'     => sanitize_text_field( $data['group_name'] ),
			'group_name_ar'  => sanitize_text_field( $data['group_name_ar'] ?? '' ),
			'contact_person' => sanitize_text_field( $data['contact_person'] ?? '' ),
			'contact_phone'  => sanitize_text_field( $data['contact_phone'] ?? '' ),
			'contact_email'  => sanitize_email( $data['contact_email'] ?? '' ),
			'agency_name'    => sanitize_text_field( $data['agency_name'] ?? '' ),
			'agency_name_ar' => sanitize_text_field( $data['agency_name_ar'] ?? '' ),
			'check_in'       => $checkIn,
			'check_out'      => $checkOut,
			'nights'         => $nights,
			'total_rooms'    => 0,
			'total_guests'   => (int) ( $data['total_guests'] ?? 0 ),
			'subtotal'       => 0,
			'tax_total'      => 0,
			'grand_total'    => 0,
			'paid_amount'    => 0,
			'currency'       => $currency,
			'status'         => $data['status'] ?? GroupBooking::STATUS_TENTATIVE,
			'payment_terms'  => sanitize_textarea_field( $data['payment_terms'] ?? '' ),
			'notes'          => sanitize_textarea_field( $data['notes'] ?? '' ),
			'internal_notes' => sanitize_textarea_field( $data['internal_notes'] ?? '' ),
			'created_by'     => get_current_user_id() ?: null,
		] );

		if ( ! $group ) {
			throw new \RuntimeException( __( 'Failed to create group booking record.', 'nozule' ) );
		}

		$this->logger->info( 'Group booking created', [
			'group_id'     => $group->id,
			'group_number' => $group->group_number,
		] );

		$this->events->dispatch( 'group_booking/created', $group );

		return $group;
	}

	/**
	 * Update an existing group booking.
	 *
	 * @param int   $id   Group booking ID.
	 * @param array $data Fields to update.
	 * @throws \InvalidArgumentException When validation fails.
	 * @throws \RuntimeException When the group is not found or update fails.
	 */
	public function updateGroup( int $id, array $data ): GroupBooking {
		$group = $this->groupRepo->findOrFail( $id );

		// Remove non-updatable fields.
		unset(
			$data['id'],
			$data['group_number'],
			$data['created_at'],
			$data['created_by']
		);

		if ( ! $this->validator->validateUpdate( $data ) ) {
			throw new \InvalidArgumentException(
				implode( ' ', $this->validator->getAllErrors() )
			);
		}

		// Sanitize text fields.
		$textFields = [
			'group_name', 'group_name_ar', 'contact_person',
			'contact_phone', 'agency_name', 'agency_name_ar',
		];
		foreach ( $textFields as $field ) {
			if ( isset( $data[ $field ] ) ) {
				$data[ $field ] = sanitize_text_field( $data[ $field ] );
			}
		}

		if ( isset( $data['contact_email'] ) ) {
			$data['contact_email'] = sanitize_email( $data['contact_email'] );
		}

		$textareaFields = [ 'payment_terms', 'notes', 'internal_notes' ];
		foreach ( $textareaFields as $field ) {
			if ( isset( $data[ $field ] ) ) {
				$data[ $field ] = sanitize_textarea_field( $data[ $field ] );
			}
		}

		// Recalculate nights if dates changed.
		$checkIn  = $data['check_in'] ?? $group->check_in;
		$checkOut = $data['check_out'] ?? $group->check_out;
		if ( isset( $data['check_in'] ) || isset( $data['check_out'] ) ) {
			$data['nights'] = (int) ( ( strtotime( $checkOut ) - strtotime( $checkIn ) ) / DAY_IN_SECONDS );
		}

		$success = $this->groupRepo->update( $id, $data );

		if ( ! $success ) {
			throw new \RuntimeException( __( 'Failed to update group booking.', 'nozule' ) );
		}

		$this->logger->info( 'Group booking updated', [
			'group_id' => $id,
			'fields'   => array_keys( $data ),
		] );

		$this->events->dispatch( 'group_booking/updated', $id, $data );

		return $this->groupRepo->findOrFail( $id );
	}

	// ── Status Transitions ──────────────────────────────────────────

	/**
	 * Confirm a tentative group booking.
	 *
	 * @throws \InvalidArgumentException When the status transition is invalid.
	 * @throws \RuntimeException When update fails.
	 */
	public function confirmGroup( int $id ): GroupBooking {
		$group = $this->groupRepo->findOrFail( $id );

		if ( ! $this->validator->validateStatusChange( $group->status, GroupBooking::STATUS_CONFIRMED ) ) {
			throw new \InvalidArgumentException(
				implode( ' ', $this->validator->getAllErrors() )
			);
		}

		$this->groupRepo->update( $id, [
			'status'       => GroupBooking::STATUS_CONFIRMED,
			'confirmed_at' => current_time( 'mysql' ),
		] );

		$this->logger->info( 'Group booking confirmed', [ 'group_id' => $id ] );
		$this->events->dispatch( 'group_booking/confirmed', $id );

		return $this->groupRepo->findOrFail( $id );
	}

	/**
	 * Cancel a group booking and all its room allocations.
	 *
	 * @param int    $id     Group booking ID.
	 * @param string $reason Cancellation reason.
	 * @throws \InvalidArgumentException When the status transition is invalid.
	 * @throws \RuntimeException When update fails.
	 */
	public function cancelGroup( int $id, string $reason = '' ): GroupBooking {
		$group = $this->groupRepo->findOrFail( $id );

		if ( ! $this->validator->validateStatusChange( $group->status, GroupBooking::STATUS_CANCELLED ) ) {
			throw new \InvalidArgumentException(
				implode( ' ', $this->validator->getAllErrors() )
			);
		}

		$this->groupRepo->beginTransaction();

		try {
			// Cancel all active room allocations.
			$this->roomRepo->bulkUpdateStatus(
				$id,
				GroupBookingRoom::STATUS_CANCELLED,
				GroupBookingRoom::STATUS_RESERVED
			);

			$updateData = [
				'status'       => GroupBooking::STATUS_CANCELLED,
				'cancelled_at' => current_time( 'mysql' ),
			];

			if ( ! empty( $reason ) ) {
				$updateData['internal_notes'] = trim(
					( $group->internal_notes ?? '' ) . "\n" .
					sprintf( __( 'Cancelled: %s', 'nozule' ), sanitize_textarea_field( $reason ) )
				);
			}

			$this->groupRepo->update( $id, $updateData );

			$this->groupRepo->commit();
		} catch ( \Throwable $e ) {
			$this->groupRepo->rollback();
			throw $e;
		}

		$this->logger->info( 'Group booking cancelled', [
			'group_id' => $id,
			'reason'   => $reason,
		] );

		$this->events->dispatch( 'group_booking/cancelled', $id, $reason );

		return $this->groupRepo->findOrFail( $id );
	}

	/**
	 * Bulk check-in: set group status and all reserved rooms to checked_in.
	 *
	 * @throws \InvalidArgumentException When the status transition is invalid.
	 */
	public function bulkCheckIn( int $id ): GroupBooking {
		$group = $this->groupRepo->findOrFail( $id );

		if ( ! $this->validator->validateStatusChange( $group->status, GroupBooking::STATUS_CHECKED_IN ) ) {
			throw new \InvalidArgumentException(
				implode( ' ', $this->validator->getAllErrors() )
			);
		}

		$this->groupRepo->beginTransaction();

		try {
			// Update all reserved rooms to checked_in.
			$this->roomRepo->bulkUpdateStatus(
				$id,
				GroupBookingRoom::STATUS_CHECKED_IN,
				GroupBookingRoom::STATUS_RESERVED
			);

			$this->groupRepo->update( $id, [
				'status' => GroupBooking::STATUS_CHECKED_IN,
			] );

			$this->groupRepo->commit();
		} catch ( \Throwable $e ) {
			$this->groupRepo->rollback();
			throw $e;
		}

		$this->logger->info( 'Group booking bulk checked in', [ 'group_id' => $id ] );
		$this->events->dispatch( 'group_booking/checked_in', $id );

		return $this->groupRepo->findOrFail( $id );
	}

	/**
	 * Bulk check-out: set group status and all checked-in rooms to checked_out.
	 *
	 * @throws \InvalidArgumentException When the status transition is invalid.
	 */
	public function bulkCheckOut( int $id ): GroupBooking {
		$group = $this->groupRepo->findOrFail( $id );

		if ( ! $this->validator->validateStatusChange( $group->status, GroupBooking::STATUS_CHECKED_OUT ) ) {
			throw new \InvalidArgumentException(
				implode( ' ', $this->validator->getAllErrors() )
			);
		}

		$this->groupRepo->beginTransaction();

		try {
			// Update all checked-in rooms to checked_out.
			$this->roomRepo->bulkUpdateStatus(
				$id,
				GroupBookingRoom::STATUS_CHECKED_OUT,
				GroupBookingRoom::STATUS_CHECKED_IN
			);

			$this->groupRepo->update( $id, [
				'status' => GroupBooking::STATUS_CHECKED_OUT,
			] );

			$this->groupRepo->commit();
		} catch ( \Throwable $e ) {
			$this->groupRepo->rollback();
			throw $e;
		}

		$this->logger->info( 'Group booking bulk checked out', [ 'group_id' => $id ] );
		$this->events->dispatch( 'group_booking/checked_out', $id );

		return $this->groupRepo->findOrFail( $id );
	}

	// ── Room Allocation Management ──────────────────────────────────

	/**
	 * Add a room allocation to a group booking.
	 *
	 * @param int   $groupId Group booking ID.
	 * @param array $data    Room allocation data.
	 * @throws \InvalidArgumentException When validation fails.
	 * @throws \RuntimeException When insert fails.
	 */
	public function addRoom( int $groupId, array $data ): GroupBookingRoom {
		$group = $this->groupRepo->findOrFail( $groupId );

		if ( ! $this->validator->validateAddRoom( $data ) ) {
			throw new \InvalidArgumentException(
				implode( ' ', $this->validator->getAllErrors() )
			);
		}

		$room = $this->roomRepo->create( [
			'group_booking_id' => $groupId,
			'booking_id'       => $data['booking_id'] ?? null,
			'room_type_id'     => (int) $data['room_type_id'],
			'room_id'          => isset( $data['room_id'] ) ? (int) $data['room_id'] : null,
			'guest_name'       => sanitize_text_field( $data['guest_name'] ?? '' ),
			'guest_id'         => isset( $data['guest_id'] ) ? (int) $data['guest_id'] : null,
			'rate_per_night'   => round( (float) $data['rate_per_night'], 2 ),
			'status'           => GroupBookingRoom::STATUS_RESERVED,
			'notes'            => sanitize_textarea_field( $data['notes'] ?? '' ),
		] );

		if ( ! $room ) {
			throw new \RuntimeException( __( 'Failed to add room to group booking.', 'nozule' ) );
		}

		// Increment total_rooms on the group booking.
		$newTotalRooms = $this->roomRepo->countByGroupAndStatus( $groupId );
		$this->groupRepo->update( $groupId, [ 'total_rooms' => $newTotalRooms ] );

		// Recalculate totals.
		$this->recalculateTotals( $groupId );

		$this->logger->info( 'Room added to group booking', [
			'group_id'     => $groupId,
			'room_alloc_id' => $room->id,
		] );

		$this->events->dispatch( 'group_booking/room_added', $groupId, $room );

		return $room;
	}

	/**
	 * Remove a room allocation from a group booking.
	 *
	 * @param int $roomAllocationId Room allocation ID.
	 * @throws \RuntimeException When the allocation is not found.
	 */
	public function removeRoom( int $roomAllocationId ): void {
		$allocation = $this->roomRepo->findOrFail( $roomAllocationId );
		$groupId    = $allocation->group_booking_id;

		$this->roomRepo->delete( $roomAllocationId );

		// Decrement total_rooms on the group booking.
		$newTotalRooms = $this->roomRepo->countByGroupAndStatus( $groupId );
		$this->groupRepo->update( $groupId, [ 'total_rooms' => $newTotalRooms ] );

		// Recalculate totals.
		$this->recalculateTotals( $groupId );

		$this->logger->info( 'Room removed from group booking', [
			'group_id'       => $groupId,
			'room_alloc_id'  => $roomAllocationId,
		] );

		$this->events->dispatch( 'group_booking/room_removed', $groupId, $roomAllocationId );
	}

	/**
	 * Update the status of a single room allocation.
	 *
	 * @param int    $roomAllocationId Room allocation ID.
	 * @param string $status           New status.
	 * @throws \InvalidArgumentException When the status is not valid.
	 */
	public function updateRoomStatus( int $roomAllocationId, string $status ): GroupBookingRoom {
		$allocation = $this->roomRepo->findOrFail( $roomAllocationId );

		if ( ! in_array( $status, GroupBookingRoom::validStatuses(), true ) ) {
			throw new \InvalidArgumentException(
				sprintf( __( 'Invalid room allocation status: %s', 'nozule' ), $status )
			);
		}

		$this->roomRepo->updateStatus( $roomAllocationId, $status );

		$this->logger->info( 'Group room allocation status updated', [
			'room_alloc_id' => $roomAllocationId,
			'group_id'      => $allocation->group_booking_id,
			'new_status'    => $status,
		] );

		return $this->roomRepo->findOrFail( $roomAllocationId );
	}

	// ── Financial Recalculation ─────────────────────────────────────

	/**
	 * Recalculate subtotal, tax_total, and grand_total for a group booking.
	 *
	 * Subtotal = sum of (rate_per_night * nights) for all non-cancelled rooms.
	 * Tax is calculated based on settings.
	 *
	 * @param int $groupId Group booking ID.
	 */
	public function recalculateTotals( int $groupId ): void {
		$group = $this->groupRepo->findOrFail( $groupId );
		$rooms = $this->roomRepo->getByGroupBooking( $groupId );

		$subtotal = 0.0;

		foreach ( $rooms as $room ) {
			if ( $room->status !== GroupBookingRoom::STATUS_CANCELLED ) {
				$subtotal += $room->rate_per_night * $group->nights;
			}
		}

		$subtotal = round( $subtotal, 2 );

		// Calculate tax based on settings (default 0%).
		$taxRate  = (float) $this->settings->get( 'tax.rate', 0 );
		$taxTotal = round( $subtotal * ( $taxRate / 100 ), 2 );

		$grandTotal = round( $subtotal + $taxTotal, 2 );

		$this->groupRepo->update( $groupId, [
			'subtotal'    => $subtotal,
			'tax_total'   => $taxTotal,
			'grand_total' => $grandTotal,
		] );
	}
}
