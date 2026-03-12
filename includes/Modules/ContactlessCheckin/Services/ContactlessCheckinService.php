<?php

namespace Nozule\Modules\ContactlessCheckin\Services;

use Nozule\Core\EventDispatcher;
use Nozule\Core\Logger;
use Nozule\Core\SettingsManager;
use Nozule\Modules\Bookings\Repositories\BookingRepository;
use Nozule\Modules\ContactlessCheckin\Models\CheckinRegistration;
use Nozule\Modules\ContactlessCheckin\Repositories\CheckinRegistrationRepository;
use Nozule\Modules\ContactlessCheckin\Validators\CheckinRegistrationValidator;
use Nozule\Modules\Guests\Repositories\GuestRepository;

/**
 * Service layer for contactless check-in operations.
 */
class ContactlessCheckinService {

	private CheckinRegistrationRepository $repository;
	private CheckinRegistrationValidator $validator;
	private BookingRepository $bookingRepository;
	private GuestRepository $guestRepository;
	private SettingsManager $settings;
	private EventDispatcher $events;
	private Logger $logger;

	public function __construct(
		CheckinRegistrationRepository $repository,
		CheckinRegistrationValidator $validator,
		BookingRepository $bookingRepository,
		GuestRepository $guestRepository,
		SettingsManager $settings,
		EventDispatcher $events,
		Logger $logger
	) {
		$this->repository        = $repository;
		$this->validator         = $validator;
		$this->bookingRepository = $bookingRepository;
		$this->guestRepository   = $guestRepository;
		$this->settings          = $settings;
		$this->events            = $events;
		$this->logger            = $logger;
	}

	// =========================================================================
	// Feature toggle
	// =========================================================================

	/**
	 * Check if contactless check-in is enabled.
	 */
	public function isEnabled(): bool {
		return (bool) $this->settings->get( 'contactless_checkin.enabled', false );
	}

	/**
	 * Get all contactless check-in settings.
	 */
	public function getSettings(): array {
		return [
			'enabled'            => (bool) $this->settings->get( 'contactless_checkin.enabled', false ),
			'require_document'   => (bool) $this->settings->get( 'contactless_checkin.require_document', true ),
			'require_signature'  => (bool) $this->settings->get( 'contactless_checkin.require_signature', true ),
			'token_expiry_hours' => (int) $this->settings->get( 'contactless_checkin.token_expiry_hours', 72 ),
			'auto_approve'       => (bool) $this->settings->get( 'contactless_checkin.auto_approve', false ),
		];
	}

	/**
	 * Update contactless check-in settings.
	 */
	public function updateSettings( array $data ): array {
		$keys = [ 'enabled', 'require_document', 'require_signature', 'token_expiry_hours', 'auto_approve' ];

		foreach ( $keys as $key ) {
			if ( array_key_exists( $key, $data ) ) {
				$value = $key === 'token_expiry_hours' ? absint( $data[ $key ] ) : (bool) $data[ $key ];
				$this->settings->set( "contactless_checkin.{$key}", $value );
			}
		}

		$this->logger->info( 'Contactless check-in settings updated' );

		return $this->getSettings();
	}

	// =========================================================================
	// Token & registration creation
	// =========================================================================

	/**
	 * Send a contactless check-in link for a booking.
	 *
	 * Creates a registration record with a unique token and fires an event
	 * so the notification system can email/WhatsApp the link to the guest.
	 *
	 * @return CheckinRegistration|array Registration on success, errors on failure.
	 */
	public function sendCheckinLink( int $bookingId ): CheckinRegistration|array {
		if ( ! $this->isEnabled() ) {
			return [ 'general' => [ __( 'Contactless check-in is disabled.', 'nozule' ) ] ];
		}

		$booking = $this->bookingRepository->find( $bookingId );
		if ( ! $booking ) {
			return [ 'booking_id' => [ __( 'Booking not found.', 'nozule' ) ] ];
		}

		if ( ! in_array( $booking->status, [ 'confirmed', 'pending' ], true ) ) {
			return [ 'booking_id' => [ __( 'Booking must be in confirmed or pending status.', 'nozule' ) ] ];
		}

		// Invalidate any existing pending registration for this booking.
		$existing = $this->repository->findByBooking( $bookingId );
		if ( $existing && $existing->isPending() ) {
			$this->repository->update( $existing->id, [ 'status' => 'rejected' ] );
		}

		$expiryHours = (int) $this->settings->get( 'contactless_checkin.token_expiry_hours', 72 );
		$token       = bin2hex( random_bytes( 32 ) );

		$registration = $this->repository->create( [
			'booking_id'  => $bookingId,
			'guest_id'    => (int) $booking->guest_id,
			'property_id' => $booking->property_id ?? 1,
			'token'       => $token,
			'status'      => CheckinRegistration::STATUS_PENDING,
			'expires_at'  => gmdate( 'Y-m-d H:i:s', time() + ( $expiryHours * 3600 ) ),
		] );

		if ( ! $registration ) {
			$this->logger->error( 'Failed to create contactless check-in registration', [
				'booking_id' => $bookingId,
			] );
			return [ 'general' => [ __( 'Failed to create check-in registration.', 'nozule' ) ] ];
		}

		$this->events->dispatch( 'contactless_checkin/link_sent', $registration, $booking );
		$this->logger->info( 'Contactless check-in link created', [
			'booking_id'      => $bookingId,
			'registration_id' => $registration->id,
		] );

		return $registration;
	}

	// =========================================================================
	// Public guest-facing operations (token-authenticated)
	// =========================================================================

	/**
	 * Verify a token and return the registration with booking details.
	 *
	 * @return array|null Registration data with booking info, or null if invalid.
	 */
	public function verifyToken( string $token ): ?array {
		$registration = $this->repository->findByToken( $token );
		if ( ! $registration ) {
			return null;
		}

		if ( $registration->isExpired() ) {
			return null;
		}

		if ( ! in_array( $registration->status, [ CheckinRegistration::STATUS_PENDING, CheckinRegistration::STATUS_SUBMITTED ], true ) ) {
			return null;
		}

		$booking = $this->bookingRepository->find( $registration->booking_id );
		if ( ! $booking ) {
			return null;
		}

		$guest = $this->guestRepository->find( $registration->guest_id );

		$settings = $this->getSettings();

		return [
			'registration' => $registration->toArray(),
			'booking'      => [
				'id'             => $booking->id,
				'booking_number' => $booking->booking_number,
				'check_in'       => $booking->check_in,
				'check_out'      => $booking->check_out,
				'status'         => $booking->status,
				'room_type'      => $booking->room_type_name ?? null,
				'adults'         => $booking->adults ?? null,
				'children'       => $booking->children ?? null,
			],
			'guest'        => $guest ? [
				'first_name'  => $guest->first_name,
				'last_name'   => $guest->last_name,
				'email'       => $guest->email,
				'phone'       => $guest->phone,
				'nationality' => $guest->nationality,
			] : null,
			'settings'     => [
				'require_document'  => $settings['require_document'],
				'require_signature' => $settings['require_signature'],
			],
		];
	}

	/**
	 * Submit the guest's check-in registration form.
	 *
	 * @return CheckinRegistration|array Registration on success, errors on failure.
	 */
	public function submitRegistration( string $token, array $data ): CheckinRegistration|array {
		$registration = $this->repository->findByToken( $token );
		if ( ! $registration ) {
			return [ 'token' => [ __( 'Invalid or expired check-in link.', 'nozule' ) ] ];
		}

		if ( $registration->isExpired() ) {
			return [ 'token' => [ __( 'This check-in link has expired.', 'nozule' ) ] ];
		}

		if ( ! $registration->isPending() ) {
			return [ 'token' => [ __( 'This registration has already been submitted.', 'nozule' ) ] ];
		}

		$guestDetails = $data['guest_details'] ?? [];
		if ( ! $this->validator->validateSubmission( $guestDetails ) ) {
			return $this->validator->getErrors();
		}

		$settings = $this->getSettings();

		if ( $settings['require_signature'] && empty( $data['signature_path'] ) ) {
			return [ 'signature' => [ __( 'Digital signature is required.', 'nozule' ) ] ];
		}

		if ( $settings['require_document'] && empty( $data['document_ids'] ) ) {
			return [ 'documents' => [ __( 'At least one ID document is required.', 'nozule' ) ] ];
		}

		$updateData = [
			'status'           => CheckinRegistration::STATUS_SUBMITTED,
			'guest_details'    => $guestDetails,
			'room_preference'  => $data['room_preference'] ?? null,
			'special_requests' => $data['special_requests'] ?? null,
			'document_ids'     => $data['document_ids'] ?? [],
			'signature_path'   => $data['signature_path'] ?? null,
			'submitted_at'     => current_time( 'mysql', true ),
		];

		$success = $this->repository->update( $registration->id, $updateData );
		if ( ! $success ) {
			return [ 'general' => [ __( 'Failed to submit registration.', 'nozule' ) ] ];
		}

		// Update guest record with confirmed details.
		$this->guestRepository->update( $registration->guest_id, [
			'phone'       => sanitize_text_field( $guestDetails['phone'] ?? '' ),
			'nationality' => sanitize_text_field( $guestDetails['nationality'] ?? '' ),
		] );

		$updated = $this->repository->find( $registration->id );

		// Auto-approve if configured.
		if ( $settings['auto_approve'] ) {
			return $this->approve( $registration->id );
		}

		$this->events->dispatch( 'contactless_checkin/submitted', $updated );
		$this->logger->info( 'Contactless check-in submitted', [
			'registration_id' => $registration->id,
			'booking_id'      => $registration->booking_id,
		] );

		return $updated;
	}

	// =========================================================================
	// Admin operations
	// =========================================================================

	/**
	 * List registrations with optional status filter and pagination.
	 *
	 * @return array{ items: CheckinRegistration[], total: int }
	 */
	public function list( ?string $status = null, int $page = 1, int $perPage = 50 ): array {
		$offset = ( max( 1, $page ) - 1 ) * $perPage;
		$items  = $this->repository->getFiltered( $status, $perPage, $offset );
		$total  = $this->repository->countFiltered( $status );

		return [ 'items' => $items, 'total' => $total ];
	}

	/**
	 * Get status counts for dashboard.
	 */
	public function getStatusCounts(): array {
		return $this->repository->countByStatus();
	}

	/**
	 * Approve a submitted registration and optionally trigger check-in.
	 *
	 * @return CheckinRegistration|array
	 */
	public function approve( int $id ): CheckinRegistration|array {
		$registration = $this->repository->find( $id );
		if ( ! $registration ) {
			return [ 'id' => [ __( 'Registration not found.', 'nozule' ) ] ];
		}

		if ( $registration->status !== CheckinRegistration::STATUS_SUBMITTED ) {
			return [ 'status' => [ __( 'Only submitted registrations can be approved.', 'nozule' ) ] ];
		}

		$this->repository->update( $id, [
			'status'      => CheckinRegistration::STATUS_APPROVED,
			'reviewed_by' => get_current_user_id() ?: null,
			'reviewed_at' => current_time( 'mysql', true ),
		] );

		$updated = $this->repository->find( $id );

		$this->events->dispatch( 'contactless_checkin/approved', $updated );
		$this->logger->info( 'Contactless check-in approved', [
			'registration_id' => $id,
			'booking_id'      => $registration->booking_id,
		] );

		return $updated;
	}

	/**
	 * Reject a submitted registration.
	 *
	 * @return CheckinRegistration|array
	 */
	public function reject( int $id ): CheckinRegistration|array {
		$registration = $this->repository->find( $id );
		if ( ! $registration ) {
			return [ 'id' => [ __( 'Registration not found.', 'nozule' ) ] ];
		}

		if ( ! in_array( $registration->status, [ CheckinRegistration::STATUS_PENDING, CheckinRegistration::STATUS_SUBMITTED ], true ) ) {
			return [ 'status' => [ __( 'This registration cannot be rejected.', 'nozule' ) ] ];
		}

		$this->repository->update( $id, [
			'status'      => CheckinRegistration::STATUS_REJECTED,
			'reviewed_by' => get_current_user_id() ?: null,
			'reviewed_at' => current_time( 'mysql', true ),
		] );

		$updated = $this->repository->find( $id );

		$this->events->dispatch( 'contactless_checkin/rejected', $updated );
		$this->logger->info( 'Contactless check-in rejected', [
			'registration_id' => $id,
			'booking_id'      => $registration->booking_id,
		] );

		return $updated;
	}

	// =========================================================================
	// Signature upload
	// =========================================================================

	/**
	 * Save a base64-encoded signature image to the filesystem.
	 *
	 * @return string|array File path on success, errors on failure.
	 */
	public function saveSignature( string $base64Data, int $bookingId ): string|array {
		$uploadDir = wp_upload_dir();
		$dir       = $uploadDir['basedir'] . '/nozule/signatures/' . $bookingId;

		if ( ! wp_mkdir_p( $dir ) ) {
			return [ 'signature' => [ __( 'Failed to create signature directory.', 'nozule' ) ] ];
		}

		// Strip data URI prefix if present.
		$base64Data = preg_replace( '#^data:image/\w+;base64,#i', '', $base64Data );
		$decoded    = base64_decode( $base64Data, true );

		if ( ! $decoded ) {
			return [ 'signature' => [ __( 'Invalid signature data.', 'nozule' ) ] ];
		}

		$filename = 'signature_' . time() . '.png';
		$filepath = $dir . '/' . $filename;

		if ( file_put_contents( $filepath, $decoded ) === false ) {
			return [ 'signature' => [ __( 'Failed to save signature.', 'nozule' ) ] ];
		}

		// Return relative path from uploads directory.
		return 'nozule/signatures/' . $bookingId . '/' . $filename;
	}
}
