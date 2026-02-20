<?php

namespace Nozule\Modules\Bookings\Services;

use Nozule\Core\SettingsManager;
use Nozule\Modules\Bookings\Exceptions\InvalidStateException;
use Nozule\Modules\Bookings\Exceptions\NoAvailabilityException;
use Nozule\Modules\Bookings\Models\Booking;
use Nozule\Modules\Bookings\Models\BookingLog;
use Nozule\Modules\Bookings\Models\Payment;
use Nozule\Modules\Bookings\Repositories\BookingRepository;
use Nozule\Modules\Bookings\Repositories\PaymentRepository;
use Nozule\Modules\Bookings\Validators\BookingValidator;
use Nozule\Modules\Guests\Services\GuestService;
use Nozule\Modules\Rooms\Services\AvailabilityService;
use Nozule\Modules\Pricing\Services\PricingService;
use Nozule\Modules\Notifications\Services\NotificationService;

/**
 * Central booking business-logic service.
 *
 * Orchestrates the full booking lifecycle: creation, confirmation, check-in,
 * check-out, cancellation, no-show handling, and payment recording.
 */
class BookingService {

	private BookingRepository $bookingRepository;
	private PaymentRepository $paymentRepository;
	private BookingValidator $validator;
	private GuestService $guestService;
	private AvailabilityService $availabilityService;
	private PricingService $pricingService;
	private NotificationService $notificationService;
	private SettingsManager $settings;

	public function __construct(
		BookingRepository $bookingRepository,
		PaymentRepository $paymentRepository,
		BookingValidator $validator,
		GuestService $guestService,
		AvailabilityService $availabilityService,
		PricingService $pricingService,
		NotificationService $notificationService,
		SettingsManager $settings
	) {
		$this->bookingRepository   = $bookingRepository;
		$this->paymentRepository   = $paymentRepository;
		$this->validator           = $validator;
		$this->guestService        = $guestService;
		$this->availabilityService = $availabilityService;
		$this->pricingService      = $pricingService;
		$this->notificationService = $notificationService;
		$this->settings            = $settings;
	}

	// ── Booking Lifecycle ───────────────────────────────────────────

	/**
	 * Create a new booking.
	 *
	 * Validates input, checks availability, finds or creates the guest,
	 * calculates pricing, deducts inventory, persists the booking, logs
	 * the creation, and queues confirmation notification.
	 *
	 * @param array $data Booking data (room_type_id, check_in, check_out,
	 *                    adults, children, source, guest_id or guest_* fields,
	 *                    special_requests, internal_notes, etc.).
	 * @throws \InvalidArgumentException When validation fails.
	 * @throws NoAvailabilityException   When no rooms are available.
	 */
	public function createBooking( array $data ): Booking {
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

		// 3. Check availability.
		$roomTypeId = (int) $data['room_type_id'];

		if ( ! $this->availabilityService->isAvailable( $roomTypeId, $checkIn, $checkOut ) ) {
			throw new NoAvailabilityException(
				__( 'No rooms available for the selected room type and dates.', 'nozule' )
			);
		}

		// 4. Find or create guest.
		$guestId = $this->resolveGuest( $data );

		// 5. Calculate pricing.
		$adults   = (int) ( $data['adults'] ?? 1 );
		$children = (int) ( $data['children'] ?? 0 );
		$pricing  = $this->pricingService->calculate( $roomTypeId, $checkIn, $checkOut, $adults, $children );

		// 6. Generate booking number.
		$bookingNumber = $this->generateBookingNumber();

		// 7. Default currency from settings.
		$currency = $data['currency'] ?? $this->settings->get( 'currency.default', 'USD' );

		// 8. Persist in a transaction.
		$this->bookingRepository->beginTransaction();

		try {
			// Deduct inventory.
			$this->availabilityService->deductInventory( $roomTypeId, $checkIn, $checkOut );

			// Create the booking record.
			$booking = $this->bookingRepository->create( [
				'booking_number'      => $bookingNumber,
				'guest_id'            => $guestId,
				'room_type_id'        => $roomTypeId,
				'room_id'             => $data['room_id'] ?? null,
				'check_in'            => $checkIn,
				'check_out'           => $checkOut,
				'nights'              => $nights,
				'adults'              => $adults,
				'children'            => $children,
				'status'              => Booking::STATUS_PENDING,
				'source'              => $data['source'] ?? Booking::SOURCE_DIRECT,
				'total_amount'        => $pricing['total'],
				'paid_amount'         => 0,
				'currency'            => $currency,
				'special_requests'    => sanitize_textarea_field( $data['special_requests'] ?? '' ),
				'internal_notes'      => sanitize_textarea_field( $data['internal_notes'] ?? '' ),
				'created_by'          => get_current_user_id() ?: null,
				'ip_address'          => self::getClientIP(),
			] );

			if ( ! $booking ) {
				throw new \RuntimeException( __( 'Failed to create booking record.', 'nozule' ) );
			}

			// Increment guest booking count.
			$this->guestService->incrementBookingCount( $guestId, $pricing['total'] );

			// Audit log.
			$this->bookingRepository->createLog( [
				'booking_id' => $booking->id,
				'action'     => BookingLog::ACTION_CREATED,
				'details'    => wp_json_encode( [
					'source'       => $booking->source,
					'total_amount' => $pricing['total'],
					'nights'       => $nights,
				] ),
				'user_id'    => get_current_user_id() ?: null,
				'ip_address' => self::getClientIP(),
			] );

			$this->bookingRepository->commit();
		} catch ( \Throwable $e ) {
			$this->bookingRepository->rollback();
			throw $e;
		}

		// 9. Queue notification (outside transaction -- non-critical).
		$this->notificationService->queue( 'booking_created', [
			'booking_id'     => $booking->id,
			'booking_number' => $booking->booking_number,
			'guest_id'       => $guestId,
		] );

		/**
		 * Fires after a booking has been successfully created.
		 *
		 * @param Booking $booking The new booking.
		 * @param array   $data    Original input data.
		 */
		do_action( 'nozule/booking/created', $booking, $data );

		return $booking;
	}

	/**
	 * Confirm a pending booking.
	 *
	 * @param int      $bookingId Booking ID.
	 * @param int|null $userId    User performing the confirmation.
	 * @throws InvalidStateException When booking is not in pending status.
	 */
	public function confirmBooking( int $bookingId, ?int $userId = null ): Booking {
		$booking = $this->bookingRepository->findOrFail( $bookingId );

		if ( ! $booking->isPending() ) {
			throw new InvalidStateException(
				sprintf(
					__( 'Cannot confirm booking %s: current status is "%s".', 'nozule' ),
					$booking->booking_number,
					$booking->status
				)
			);
		}

		$userId = $userId ?? get_current_user_id();

		$this->bookingRepository->update( $bookingId, [
			'status'       => Booking::STATUS_CONFIRMED,
			'confirmed_at' => current_time( 'mysql' ),
		] );

		$this->bookingRepository->createLog( [
			'booking_id' => $bookingId,
			'action'     => BookingLog::ACTION_CONFIRMED,
			'details'    => __( 'Booking confirmed.', 'nozule' ),
			'user_id'    => $userId ?: null,
			'ip_address' => self::getClientIP(),
		] );

		$this->notificationService->queue( 'booking_confirmed', [
			'booking_id'     => $bookingId,
			'booking_number' => $booking->booking_number,
			'guest_id'       => $booking->guest_id,
		] );

		do_action( 'nozule/booking/confirmed', $bookingId );

		return $this->bookingRepository->findOrFail( $bookingId );
	}

	/**
	 * Cancel a booking.
	 *
	 * Restores inventory for the cancelled dates and logs the event.
	 *
	 * @param int      $bookingId Booking ID.
	 * @param string   $reason    Cancellation reason.
	 * @param int|null $userId    User performing the cancellation.
	 * @throws InvalidStateException When booking cannot be cancelled.
	 */
	public function cancelBooking( int $bookingId, string $reason = '', ?int $userId = null ): Booking {
		$booking = $this->bookingRepository->findOrFail( $bookingId );

		if ( ! $booking->isCancellable() ) {
			throw new InvalidStateException(
				sprintf(
					__( 'Cannot cancel booking %s: current status is "%s".', 'nozule' ),
					$booking->booking_number,
					$booking->status
				)
			);
		}

		$userId = $userId ?? get_current_user_id();

		$this->bookingRepository->beginTransaction();

		try {
			// Restore inventory.
			$this->availabilityService->restoreInventory(
				$booking->room_type_id,
				$booking->check_in,
				$booking->check_out
			);

			$this->bookingRepository->update( $bookingId, [
				'status'              => Booking::STATUS_CANCELLED,
				'cancellation_reason' => sanitize_textarea_field( $reason ),
				'cancelled_by'        => $userId ?: null,
				'cancelled_at'        => current_time( 'mysql' ),
			] );

			$this->bookingRepository->createLog( [
				'booking_id' => $bookingId,
				'action'     => BookingLog::ACTION_CANCELLED,
				'details'    => $reason ?: __( 'Booking cancelled.', 'nozule' ),
				'user_id'    => $userId ?: null,
				'ip_address' => self::getClientIP(),
			] );

			$this->bookingRepository->commit();
		} catch ( \Throwable $e ) {
			$this->bookingRepository->rollback();
			throw $e;
		}

		$this->notificationService->queue( 'booking_cancelled', [
			'booking_id'     => $bookingId,
			'booking_number' => $booking->booking_number,
			'guest_id'       => $booking->guest_id,
			'reason'         => $reason,
		] );

		do_action( 'nozule/booking/cancelled', $bookingId, $reason );

		return $this->bookingRepository->findOrFail( $bookingId );
	}

	/**
	 * Check in a guest.
	 *
	 * @param int      $bookingId Booking ID.
	 * @param int|null $roomId    Specific room to assign (optional).
	 * @throws InvalidStateException When booking is not confirmed.
	 */
	public function checkIn( int $bookingId, ?int $roomId = null ): Booking {
		$booking = $this->bookingRepository->findOrFail( $bookingId );

		if ( ! $booking->isConfirmed() ) {
			throw new InvalidStateException(
				sprintf(
					__( 'Cannot check in booking %s: current status is "%s".', 'nozule' ),
					$booking->booking_number,
					$booking->status
				)
			);
		}

		$updateData = [
			'status'        => Booking::STATUS_CHECKED_IN,
			'checked_in_at' => current_time( 'mysql' ),
		];

		if ( $roomId !== null ) {
			$updateData['room_id'] = $roomId;
		}

		$this->bookingRepository->update( $bookingId, $updateData );

		$details = __( 'Guest checked in.', 'nozule' );
		if ( $roomId !== null ) {
			$details .= ' ' . sprintf( __( 'Assigned to room ID %d.', 'nozule' ), $roomId );
		}

		$this->bookingRepository->createLog( [
			'booking_id' => $bookingId,
			'action'     => BookingLog::ACTION_CHECKED_IN,
			'details'    => $details,
			'user_id'    => get_current_user_id() ?: null,
			'ip_address' => self::getClientIP(),
		] );

		$this->notificationService->queue( 'booking_checked_in', [
			'booking_id'     => $bookingId,
			'booking_number' => $booking->booking_number,
			'guest_id'       => $booking->guest_id,
		] );

		do_action( 'nozule/booking/checked_in', $bookingId, $roomId );

		return $this->bookingRepository->findOrFail( $bookingId );
	}

	/**
	 * Check out a guest.
	 *
	 * @param int $bookingId Booking ID.
	 * @throws InvalidStateException When booking is not checked in.
	 */
	public function checkOut( int $bookingId ): Booking {
		$booking = $this->bookingRepository->findOrFail( $bookingId );

		if ( ! $booking->isCheckedIn() ) {
			throw new InvalidStateException(
				sprintf(
					__( 'Cannot check out booking %s: current status is "%s".', 'nozule' ),
					$booking->booking_number,
					$booking->status
				)
			);
		}

		$this->bookingRepository->update( $bookingId, [
			'status'         => Booking::STATUS_CHECKED_OUT,
			'checked_out_at' => current_time( 'mysql' ),
		] );

		// Update guest stats (total nights, last stay).
		$this->guestService->updateAfterCheckout(
			$booking->guest_id,
			$booking->nights,
			$booking->check_out
		);

		$this->bookingRepository->createLog( [
			'booking_id' => $bookingId,
			'action'     => BookingLog::ACTION_CHECKED_OUT,
			'details'    => __( 'Guest checked out.', 'nozule' ),
			'user_id'    => get_current_user_id() ?: null,
			'ip_address' => self::getClientIP(),
		] );

		$this->notificationService->queue( 'booking_checked_out', [
			'booking_id'     => $bookingId,
			'booking_number' => $booking->booking_number,
			'guest_id'       => $booking->guest_id,
		] );

		do_action( 'nozule/booking/checked_out', $bookingId );

		return $this->bookingRepository->findOrFail( $bookingId );
	}

	// ── Dashboard Queries ───────────────────────────────────────────

	/**
	 * Get today's expected arrivals.
	 *
	 * @return Booking[]
	 */
	public function getTodayArrivals(): array {
		return $this->bookingRepository->getTodayArrivals();
	}

	/**
	 * Get today's expected departures.
	 *
	 * @return Booking[]
	 */
	public function getTodayDepartures(): array {
		return $this->bookingRepository->getTodayDepartures();
	}

	/**
	 * Get all currently in-house guests.
	 *
	 * @return Booking[]
	 */
	public function getInHouseGuests(): array {
		return $this->bookingRepository->getInHouseGuests();
	}

	// ── No-Show Handling ────────────────────────────────────────────

	/**
	 * Mark overdue confirmed/pending arrivals as no-show.
	 *
	 * Should be called by the daily cron job.
	 *
	 * @return int Number of bookings marked as no-show.
	 */
	public function markNoShows(): int {
		$candidates = $this->bookingRepository->getNoShowCandidates();
		$count      = 0;

		foreach ( $candidates as $booking ) {
			$this->bookingRepository->beginTransaction();

			try {
				// Restore inventory.
				$this->availabilityService->restoreInventory(
					$booking->room_type_id,
					$booking->check_in,
					$booking->check_out
				);

				$this->bookingRepository->update( $booking->id, [
					'status' => Booking::STATUS_NO_SHOW,
				] );

				$this->bookingRepository->createLog( [
					'booking_id' => $booking->id,
					'action'     => BookingLog::ACTION_NO_SHOW,
					'details'    => __( 'Automatically marked as no-show by system.', 'nozule' ),
					'user_id'    => null,
					'ip_address' => null,
				] );

				$this->bookingRepository->commit();

				$this->notificationService->queue( 'booking_no_show', [
					'booking_id'     => $booking->id,
					'booking_number' => $booking->booking_number,
					'guest_id'       => $booking->guest_id,
				] );

				do_action( 'nozule/booking/no_show', $booking->id );

				$count++;
			} catch ( \Throwable $e ) {
				$this->bookingRepository->rollback();
				// Log and continue with next booking.
				do_action( 'nozule/log', 'error', 'Failed to mark no-show for booking ' . $booking->id, [
					'error' => $e->getMessage(),
				] );
			}
		}

		return $count;
	}

	// ── Payments ────────────────────────────────────────────────────

	/**
	 * Record a payment against a booking.
	 *
	 * @param int   $bookingId Booking ID.
	 * @param array $data      Payment data (amount, method, transaction_id, notes, etc.).
	 * @throws \InvalidArgumentException When validation fails.
	 */
	public function addPayment( int $bookingId, array $data ): Payment {
		$booking = $this->bookingRepository->findOrFail( $bookingId );

		// Validate payment data.
		if ( ! $this->validator->validatePayment( $data ) ) {
			throw new \InvalidArgumentException(
				implode( ' ', $this->validator->getAllErrors() )
			);
		}

		$amount = round( (float) $data['amount'], 2 );

		$payment = $this->paymentRepository->create( [
			'booking_id'     => $bookingId,
			'amount'         => $amount,
			'currency'       => $data['currency'] ?? $booking->currency,
			'method'         => $data['method'],
			'status'         => $data['status'] ?? Payment::STATUS_COMPLETED,
			'transaction_id' => sanitize_text_field( $data['transaction_id'] ?? '' ),
			'notes'          => sanitize_textarea_field( $data['notes'] ?? '' ),
			'recorded_by'    => get_current_user_id() ?: null,
			'payment_date'   => $data['payment_date'] ?? current_time( 'mysql' ),
		] );

		if ( ! $payment ) {
			throw new \RuntimeException( __( 'Failed to record payment.', 'nozule' ) );
		}

		// Recalculate total paid and update booking.
		$totalPaid = $this->paymentRepository->getTotalPaidForBooking( $bookingId );

		$this->bookingRepository->update( $bookingId, [
			'paid_amount' => $totalPaid,
		] );

		// Audit log.
		$this->bookingRepository->createLog( [
			'booking_id' => $bookingId,
			'action'     => BookingLog::ACTION_PAYMENT_ADDED,
			'details'    => wp_json_encode( [
				'payment_id' => $payment->id,
				'amount'     => $amount,
				'method'     => $payment->method,
				'total_paid' => $totalPaid,
			] ),
			'user_id'    => get_current_user_id() ?: null,
			'ip_address' => self::getClientIP(),
		] );

		do_action( 'nozule/booking/payment_added', $bookingId, $payment );

		return $payment;
	}

	// ── Booking Number Generation ───────────────────────────────────

	/**
	 * Generate a unique booking number in the format PREFIX-YYYY-NNNNN.
	 */
	public function generateBookingNumber(): string {
		$prefix   = $this->settings->get( 'bookings.number_prefix', 'NZL' );
		$year     = (int) current_time( 'Y' );
		$sequence = $this->bookingRepository->getNextSequence( $prefix, $year );

		return sprintf( '%s-%04d-%05d', $prefix, $year, $sequence );
	}

	// ── Guest Resolution ────────────────────────────────────────────

	/**
	 * Resolve or create a guest from booking input data.
	 *
	 * If guest_id is provided, use it directly. Otherwise, look up by email
	 * or create a new guest profile.
	 *
	 * @return int Guest ID.
	 */
	private function resolveGuest( array $data ): int {
		// Explicit guest ID provided.
		if ( ! empty( $data['guest_id'] ) ) {
			return (int) $data['guest_id'];
		}

		$nested = $data['guest'] ?? [];
		$email  = sanitize_email( $data['guest_email'] ?? $nested['email'] ?? '' );

		// Try to find existing guest by email.
		$guest = $this->guestService->findByEmail( $email );

		if ( $guest ) {
			return $guest->id;
		}

		// Create new guest profile.
		$newGuest = $this->guestService->createGuest( [
			'first_name'  => sanitize_text_field( $data['guest_first_name'] ?? $nested['first_name'] ?? '' ),
			'last_name'   => sanitize_text_field( $data['guest_last_name'] ?? $nested['last_name'] ?? '' ),
			'email'       => $email ?: sanitize_email( $nested['email'] ?? '' ),
			'phone'       => sanitize_text_field( $data['guest_phone'] ?? $nested['phone'] ?? '' ),
			'country'     => sanitize_text_field( $data['guest_country'] ?? $nested['country'] ?? '' ),
			'nationality' => sanitize_text_field( $data['guest_nationality'] ?? $nested['nationality'] ?? '' ),
			'language'    => sanitize_text_field( $data['guest_language'] ?? $nested['language'] ?? 'ar' ),
		] );

		return $newGuest->id;
	}

	// ── Utilities ───────────────────────────────────────────────────

	/**
	 * Determine the client IP address.
	 *
	 * Checks proxy headers in priority order: Cloudflare, X-Forwarded-For,
	 * then falls back to REMOTE_ADDR.
	 */
	public static function getClientIP(): string {
		// Cloudflare passes the real IP via this header.
		if ( ! empty( $_SERVER['HTTP_CF_CONNECTING_IP'] ) ) {
			return sanitize_text_field( wp_unslash( $_SERVER['HTTP_CF_CONNECTING_IP'] ) );
		}

		// Standard proxy header (may contain multiple IPs, take the first).
		if ( ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
			$ips = explode( ',', sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) );
			return trim( $ips[0] );
		}

		// Direct connection.
		if ( ! empty( $_SERVER['REMOTE_ADDR'] ) ) {
			return sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) );
		}

		return '';
	}
}
