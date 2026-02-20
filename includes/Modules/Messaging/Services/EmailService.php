<?php

namespace Nozule\Modules\Messaging\Services;

use Nozule\Core\Database;
use Nozule\Core\Logger;
use Nozule\Core\SettingsManager;
use Nozule\Modules\Messaging\Models\EmailLog;
use Nozule\Modules\Messaging\Models\EmailTemplate;
use Nozule\Modules\Messaging\Repositories\EmailLogRepository;
use Nozule\Modules\Messaging\Repositories\EmailTemplateRepository;

/**
 * Email sending and template rendering service.
 *
 * Handles template-based and raw email dispatch via wp_mail(),
 * with automatic logging of every send attempt.
 */
class EmailService {

	private EmailTemplateRepository $templateRepo;
	private EmailLogRepository $logRepo;
	private SettingsManager $settings;
	private Logger $logger;
	private Database $db;

	public function __construct(
		EmailTemplateRepository $templateRepo,
		EmailLogRepository $logRepo,
		SettingsManager $settings,
		Logger $logger,
		Database $db
	) {
		$this->templateRepo = $templateRepo;
		$this->logRepo      = $logRepo;
		$this->settings     = $settings;
		$this->logger       = $logger;
		$this->db           = $db;
	}

	// ── Template-based Sending ──────────────────────────────────────

	/**
	 * Send an email by looking up the template assigned to a trigger event.
	 *
	 * @param string   $triggerEvent The trigger event name (e.g. 'booking_confirmed').
	 * @param array    $variables    Key-value pairs for placeholder substitution.
	 * @param string   $toEmail      Recipient email address.
	 * @param int|null $bookingId    Associated booking ID (for logging).
	 * @param int|null $guestId      Associated guest ID (for logging).
	 */
	public function sendByTrigger( string $triggerEvent, array $variables, string $toEmail, ?int $bookingId = null, ?int $guestId = null ): bool {
		$template = $this->templateRepo->getByTriggerEvent( $triggerEvent );

		if ( ! $template || ! $template->isActive() ) {
			$this->logger->debug( "No active template for trigger: {$triggerEvent}" );
			return false;
		}

		return $this->dispatchTemplate( $template, $variables, $toEmail, $bookingId, $guestId );
	}

	/**
	 * Send an email using a specific template ID.
	 *
	 * @param int      $templateId Template ID.
	 * @param array    $variables  Key-value pairs for placeholder substitution.
	 * @param string   $toEmail    Recipient email address.
	 * @param int|null $bookingId  Associated booking ID (for logging).
	 * @param int|null $guestId    Associated guest ID (for logging).
	 */
	public function sendTemplate( int $templateId, array $variables, string $toEmail, ?int $bookingId = null, ?int $guestId = null ): bool {
		$template = $this->templateRepo->find( $templateId );

		if ( ! $template ) {
			$this->logger->warning( "Email template not found: ID {$templateId}" );
			return false;
		}

		return $this->dispatchTemplate( $template, $variables, $toEmail, $bookingId, $guestId );
	}

	// ── Raw Email Sending ───────────────────────────────────────────

	/**
	 * Send an arbitrary HTML email and log the result.
	 *
	 * @param string   $to         Recipient email address.
	 * @param string   $subject    Email subject.
	 * @param string   $body       Rendered HTML body.
	 * @param int|null $bookingId  Associated booking ID (for logging).
	 * @param int|null $guestId    Associated guest ID (for logging).
	 * @param int|null $templateId Source template ID (for logging).
	 */
	public function sendRawEmail( string $to, string $subject, string $body, ?int $bookingId = null, ?int $guestId = null, ?int $templateId = null ): bool {
		// Create a queued log entry before attempting to send.
		$logEntry = $this->logRepo->create( [
			'template_id' => $templateId,
			'booking_id'  => $bookingId,
			'guest_id'    => $guestId,
			'to_email'    => sanitize_email( $to ),
			'subject'     => sanitize_text_field( $subject ),
			'body'        => $body,
			'status'      => EmailLog::STATUS_QUEUED,
		] );

		$headers = $this->getEmailHeaders();
		$sent    = wp_mail( $to, $subject, $body, $headers );

		if ( $logEntry ) {
			if ( $sent ) {
				$this->logRepo->updateStatus( $logEntry->id, EmailLog::STATUS_SENT );
			} else {
				global $phpmailer;
				$errorMessage = '';
				if ( isset( $phpmailer ) && $phpmailer instanceof \PHPMailer\PHPMailer\PHPMailer ) {
					$errorMessage = $phpmailer->ErrorInfo;
				}
				$this->logRepo->updateStatus( $logEntry->id, EmailLog::STATUS_FAILED, $errorMessage );
			}
		}

		if ( ! $sent ) {
			$this->logger->error( "Failed to send email to {$to}", [
				'subject'     => $subject,
				'template_id' => $templateId,
				'booking_id'  => $bookingId,
			] );
		}

		return $sent;
	}

	// ── Template Rendering ──────────────────────────────────────────

	/**
	 * Replace {{variable}} placeholders in a template body.
	 *
	 * @param string $body      The template body with {{placeholder}} markers.
	 * @param array  $variables Key-value pairs (key without braces).
	 */
	public function renderTemplate( string $body, array $variables ): string {
		foreach ( $variables as $key => $value ) {
			$body = str_replace( '{{' . $key . '}}', (string) $value, $body );
		}

		return $body;
	}

	// ── Email Headers ───────────────────────────────────────────────

	/**
	 * Build email headers with HTML content type and From address.
	 *
	 * @return string[]
	 */
	public function getEmailHeaders(): array {
		$headers = [
			'Content-Type: text/html; charset=UTF-8',
		];

		$fromName  = $this->settings->get( 'hotel.name', get_bloginfo( 'name' ) );
		$fromEmail = $this->settings->get( 'hotel.email', get_option( 'admin_email' ) );

		if ( $fromEmail ) {
			$headers[] = "From: {$fromName} <{$fromEmail}>";
		}

		return $headers;
	}

	// ── Variable Building ───────────────────────────────────────────

	/**
	 * Build template variables from a booking ID.
	 *
	 * Fetches booking, guest, room, and room type data and assembles
	 * a flat key-value array suitable for template rendering.
	 *
	 * @return array<string, string>
	 */
	public function buildVariablesFromBooking( int $bookingId ): array {
		$bookings_table   = $this->db->table( 'bookings' );
		$guests_table     = $this->db->table( 'guests' );
		$rooms_table      = $this->db->table( 'rooms' );
		$room_types_table = $this->db->table( 'room_types' );

		// Fetch booking.
		$booking = $this->db->getRow(
			"SELECT * FROM {$bookings_table} WHERE id = %d",
			$bookingId
		);

		if ( ! $booking ) {
			$this->logger->warning( "Cannot build email variables: booking {$bookingId} not found." );
			return [];
		}

		// Fetch guest.
		$guest = null;
		if ( ! empty( $booking->guest_id ) ) {
			$guest = $this->db->getRow(
				"SELECT * FROM {$guests_table} WHERE id = %d",
				$booking->guest_id
			);
		}

		// Fetch room type.
		$roomType = null;
		if ( ! empty( $booking->room_type_id ) ) {
			$roomType = $this->db->getRow(
				"SELECT * FROM {$room_types_table} WHERE id = %d",
				$booking->room_type_id
			);
		}

		// Fetch room.
		$room = null;
		if ( ! empty( $booking->room_id ) ) {
			$room = $this->db->getRow(
				"SELECT * FROM {$rooms_table} WHERE id = %d",
				$booking->room_id
			);
		}

		// Build the variables array.
		$guestName = '';
		if ( $guest ) {
			$guestName = trim( ( $guest->first_name ?? '' ) . ' ' . ( $guest->last_name ?? '' ) );
		}

		return [
			'guest_name'     => $guestName,
			'guest_email'    => $guest->email ?? '',
			'booking_number' => $booking->booking_number ?? '',
			'check_in'       => $booking->check_in ?? '',
			'check_out'      => $booking->check_out ?? '',
			'room_type'      => $roomType->name ?? '',
			'room_number'    => $room->room_number ?? '',
			'total_amount'   => $booking->total_amount ?? '0.00',
			'currency'       => $booking->currency ?? $this->settings->get( 'currency.default', 'USD' ),
			'hotel_name'     => $this->settings->get( 'hotel.name', get_bloginfo( 'name' ) ),
			'hotel_phone'    => $this->settings->get( 'hotel.phone', '' ),
			'hotel_email'    => $this->settings->get( 'hotel.email', get_option( 'admin_email' ) ),
			'locale'         => $guest->language ?? 'ar',
		];
	}

	// ── Private Helpers ─────────────────────────────────────────────

	/**
	 * Dispatch an email using a resolved template model.
	 *
	 * Selects the correct language (EN/AR) based on the locale variable
	 * or site-level settings, renders placeholders, and sends.
	 */
	private function dispatchTemplate( EmailTemplate $template, array $variables, string $toEmail, ?int $bookingId, ?int $guestId ): bool {
		// Determine locale: check variables first, then settings, default to 'en'.
		$locale = $variables['locale'] ?? $this->settings->get( 'general.locale', 'en' );

		// Select the appropriate subject/body.
		if ( $locale === 'ar' && ! empty( $template->subject_ar ) && ! empty( $template->body_ar ) ) {
			$subject = $template->subject_ar;
			$body    = $template->body_ar;
		} else {
			$subject = $template->subject;
			$body    = $template->body;
		}

		// Render placeholders.
		$renderedSubject = $this->renderTemplate( $subject, $variables );
		$renderedBody    = $this->renderTemplate( $body, $variables );

		return $this->sendRawEmail( $toEmail, $renderedSubject, $renderedBody, $bookingId, $guestId, $template->id );
	}
}
