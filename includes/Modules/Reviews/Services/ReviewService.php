<?php

namespace Nozule\Modules\Reviews\Services;

use Nozule\Core\Database;
use Nozule\Core\Logger;
use Nozule\Core\SettingsManager;
use Nozule\Modules\Messaging\Services\EmailService;
use Nozule\Modules\Reviews\Models\ReviewRequest;
use Nozule\Modules\Reviews\Repositories\ReviewRepository;

/**
 * Review solicitation service.
 *
 * Handles queuing, processing, and tracking of post-checkout review
 * request emails sent to guests.
 */
class ReviewService {

	private ReviewRepository $repo;
	private EmailService $emailService;
	private SettingsManager $settings;
	private Logger $logger;
	private Database $db;

	public function __construct(
		ReviewRepository $repo,
		EmailService $emailService,
		SettingsManager $settings,
		Logger $logger,
		Database $db
	) {
		$this->repo         = $repo;
		$this->emailService = $emailService;
		$this->settings     = $settings;
		$this->logger       = $logger;
		$this->db           = $db;
	}

	// ── Queue a Review Request ─────────────────────────────────────

	/**
	 * Queue a review request email for a checked-out booking.
	 *
	 * Called on the `nozule/booking/checked_out` action.
	 *
	 * @param int $bookingId The booking ID.
	 * @return ReviewRequest|false The created request, or false on failure.
	 */
	public function queueReviewRequest( int $bookingId ) {
		// Check if review solicitation is enabled.
		$enabled = $this->repo->getSetting( 'enabled', '1' );
		if ( $enabled !== '1' ) {
			return false;
		}

		// Prevent duplicates — skip if a request already exists for this booking.
		$existing = $this->repo->getByBooking( $bookingId );
		if ( ! empty( $existing ) ) {
			$this->logger->debug( "Review request already exists for booking {$bookingId}, skipping." );
			return false;
		}

		// Fetch booking and guest data.
		$bookings_table = $this->db->table( 'bookings' );
		$guests_table   = $this->db->table( 'guests' );

		$booking = $this->db->getRow(
			"SELECT * FROM {$bookings_table} WHERE id = %d",
			$bookingId
		);

		if ( ! $booking || empty( $booking->guest_id ) ) {
			$this->logger->warning( "Cannot queue review request: booking {$bookingId} not found or has no guest." );
			return false;
		}

		$guest = $this->db->getRow(
			"SELECT * FROM {$guests_table} WHERE id = %d",
			$booking->guest_id
		);

		if ( ! $guest || empty( $guest->email ) ) {
			$this->logger->debug( "Cannot queue review request: guest has no email for booking {$bookingId}." );
			return false;
		}

		// Calculate send_after based on configured delay.
		$delayHours = (int) $this->repo->getSetting( 'delay_hours', '2' );
		$sendAfter  = gmdate( 'Y-m-d H:i:s', strtotime( current_time( 'mysql' ) . " +{$delayHours} hours" ) );

		$request = $this->repo->create( [
			'booking_id'      => $bookingId,
			'guest_id'        => (int) $booking->guest_id,
			'to_email'        => sanitize_email( $guest->email ),
			'status'          => ReviewRequest::STATUS_QUEUED,
			'review_platform' => ReviewRequest::PLATFORM_GOOGLE,
			'send_after'      => $sendAfter,
		] );

		if ( $request ) {
			$this->logger->info( "Review request queued for booking {$bookingId}, send after {$sendAfter}." );
		}

		return $request;
	}

	// ── Process Pending Requests ───────────────────────────────────

	/**
	 * Process all pending review requests that are past their delay.
	 *
	 * Called by WP-Cron every 15 minutes.
	 *
	 * @return int Number of emails sent.
	 */
	public function processPendingRequests(): int {
		$pending = $this->repo->getPending();
		$sent    = 0;

		foreach ( $pending as $request ) {
			try {
				$success = $this->sendReviewEmail( $request );

				if ( $success ) {
					$this->repo->markSent( $request->id );
					$sent++;
				} else {
					$this->repo->markFailed( $request->id );
				}
			} catch ( \Throwable $e ) {
				$this->repo->markFailed( $request->id );
				$this->logger->error( "Failed to send review email for request {$request->id}", [
					'error' => $e->getMessage(),
				] );
			}
		}

		if ( $sent > 0 ) {
			$this->logger->info( "Processed {$sent} review request emails." );
		}

		return $sent;
	}

	// ── Track Click ────────────────────────────────────────────────

	/**
	 * Record that a guest clicked a review link.
	 *
	 * @param int    $requestId The review request ID.
	 * @param string $platform  The platform clicked (google|tripadvisor).
	 */
	public function trackClick( int $requestId, string $platform ): bool {
		$request = $this->repo->find( $requestId );

		if ( ! $request ) {
			return false;
		}

		// Update the platform if provided.
		if ( in_array( $platform, ReviewRequest::validPlatforms(), true ) ) {
			$this->db->update( 'review_requests', [
				'review_platform' => $platform,
			], [ 'id' => $requestId ] );
		}

		return $this->repo->markClicked( $requestId );
	}

	// ── Review URLs ────────────────────────────────────────────────

	/**
	 * Get configured review URLs.
	 *
	 * @return array{ google_review_url: string, tripadvisor_url: string }
	 */
	public function getReviewUrls(): array {
		return [
			'google_review_url' => $this->repo->getSetting( 'google_review_url', '' ),
			'tripadvisor_url'   => $this->repo->getSetting( 'tripadvisor_url', '' ),
		];
	}

	// ── Stats ──────────────────────────────────────────────────────

	/**
	 * Get reputation dashboard statistics.
	 *
	 * @return array{ total: int, queued: int, sent: int, failed: int, clicked: int, click_rate: float }
	 */
	public function getStats(): array {
		$stats = $this->repo->getStats();

		// Calculate click rate: clicked / (sent + clicked) to avoid division by zero.
		$deliveredTotal = $stats['sent'] + $stats['clicked'];
		$stats['click_rate'] = $deliveredTotal > 0
			? round( ( $stats['clicked'] / $deliveredTotal ) * 100, 1 )
			: 0.0;

		return $stats;
	}

	// ── Settings ───────────────────────────────────────────────────

	/**
	 * Get all review settings.
	 *
	 * @return array<string, string>
	 */
	public function getSettings(): array {
		return $this->repo->getAllSettings();
	}

	/**
	 * Update review settings.
	 *
	 * @param array<string, string> $data Key-value pairs of settings to update.
	 */
	public function updateSettings( array $data ): bool {
		$allowedKeys = [
			'google_review_url',
			'tripadvisor_url',
			'delay_hours',
			'enabled',
			'email_subject',
			'email_subject_ar',
			'email_body',
			'email_body_ar',
		];

		$updated = false;

		foreach ( $data as $key => $value ) {
			if ( in_array( $key, $allowedKeys, true ) ) {
				$this->repo->setSetting( $key, $value );
				$updated = true;
			}
		}

		return $updated;
	}

	// ── Private Helpers ────────────────────────────────────────────

	/**
	 * Send the review solicitation email for a given request.
	 *
	 * @param ReviewRequest $request The review request model.
	 */
	private function sendReviewEmail( ReviewRequest $request ): bool {
		// Load all settings.
		$allSettings = $this->repo->getAllSettings();

		// Determine guest locale.
		$guests_table = $this->db->table( 'guests' );
		$guest        = $this->db->getRow(
			"SELECT * FROM {$guests_table} WHERE id = %d",
			$request->guest_id
		);

		$locale = ( $guest && isset( $guest->language ) && $guest->language === 'ar' ) ? 'ar' : 'en';

		// Choose subject/body based on locale.
		if ( $locale === 'ar' && ! empty( $allSettings['email_subject_ar'] ) && ! empty( $allSettings['email_body_ar'] ) ) {
			$subject = $allSettings['email_subject_ar'];
			$body    = $allSettings['email_body_ar'];
		} else {
			$subject = $allSettings['email_subject'] ?? 'How was your stay?';
			$body    = $allSettings['email_body'] ?? '';
		}

		// Build template variables.
		$variables = $this->emailService->buildVariablesFromBooking( $request->booking_id );

		// Add review-specific variables with tracking URLs.
		$trackBase = rest_url( 'nozule/v1/reviews/track/' . $request->id );

		$googleUrl      = $allSettings['google_review_url'] ?? '';
		$tripadvisorUrl = $allSettings['tripadvisor_url'] ?? '';

		$variables['google_review_url'] = ! empty( $googleUrl )
			? add_query_arg( [ 'platform' => 'google', 'redirect' => urlencode( $googleUrl ) ], $trackBase )
			: '';

		$variables['tripadvisor_url'] = ! empty( $tripadvisorUrl )
			? add_query_arg( [ 'platform' => 'tripadvisor', 'redirect' => urlencode( $tripadvisorUrl ) ], $trackBase )
			: '';

		// Render placeholders.
		$renderedSubject = $this->emailService->renderTemplate( $subject, $variables );
		$renderedBody    = $this->emailService->renderTemplate( $body, $variables );

		return $this->emailService->sendRawEmail(
			$request->to_email,
			$renderedSubject,
			$renderedBody,
			$request->booking_id,
			$request->guest_id
		);
	}
}
