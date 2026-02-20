<?php

namespace Nozule\Modules\WhatsApp\Services;

use Nozule\Core\Database;
use Nozule\Core\Logger;
use Nozule\Core\SettingsManager;
use Nozule\Modules\WhatsApp\Models\WhatsAppLog;
use Nozule\Modules\WhatsApp\Models\WhatsAppTemplate;
use Nozule\Modules\WhatsApp\Repositories\WhatsAppLogRepository;
use Nozule\Modules\WhatsApp\Repositories\WhatsAppTemplateRepository;

/**
 * WhatsApp Business API messaging service.
 *
 * Handles template-based and raw WhatsApp message dispatch via the
 * Meta Graph API, with automatic logging of every send attempt.
 */
class WhatsAppService {

	private WhatsAppTemplateRepository $templateRepo;
	private WhatsAppLogRepository $logRepo;
	private SettingsManager $settings;
	private Logger $logger;
	private Database $db;

	public function __construct(
		WhatsAppTemplateRepository $templateRepo,
		WhatsAppLogRepository $logRepo,
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
	 * Send a WhatsApp message by looking up the template assigned to a trigger event.
	 *
	 * @param string   $triggerEvent The trigger event name (e.g. 'booking_confirmed').
	 * @param array    $variables    Key-value pairs for placeholder substitution.
	 * @param string   $toPhone      Recipient phone number (international format).
	 * @param int|null $bookingId    Associated booking ID (for logging).
	 * @param int|null $guestId      Associated guest ID (for logging).
	 */
	public function sendByTrigger( string $triggerEvent, array $variables, string $toPhone, ?int $bookingId = null, ?int $guestId = null ): bool {
		// Check if WhatsApp is enabled.
		$waSettings = $this->getSettings();
		if ( empty( $waSettings['enabled'] ) || $waSettings['enabled'] === '0' ) {
			$this->logger->debug( 'WhatsApp is disabled, skipping trigger: ' . $triggerEvent );
			return false;
		}

		$template = $this->templateRepo->getByTriggerEvent( $triggerEvent );

		if ( ! $template || ! $template->isActive() ) {
			$this->logger->debug( "No active WhatsApp template for trigger: {$triggerEvent}" );
			return false;
		}

		return $this->dispatchTemplate( $template, $variables, $toPhone, $bookingId, $guestId );
	}

	/**
	 * Send a WhatsApp message using a specific template ID.
	 *
	 * @param int      $templateId Template ID.
	 * @param array    $variables  Key-value pairs for placeholder substitution.
	 * @param string   $toPhone    Recipient phone number (international format).
	 * @param int|null $bookingId  Associated booking ID (for logging).
	 * @param int|null $guestId    Associated guest ID (for logging).
	 */
	public function sendTemplate( int $templateId, array $variables, string $toPhone, ?int $bookingId = null, ?int $guestId = null ): bool {
		$template = $this->templateRepo->find( $templateId );

		if ( ! $template ) {
			$this->logger->warning( "WhatsApp template not found: ID {$templateId}" );
			return false;
		}

		return $this->dispatchTemplate( $template, $variables, $toPhone, $bookingId, $guestId );
	}

	// ── Raw Message Sending ─────────────────────────────────────────

	/**
	 * Send an arbitrary WhatsApp text message and log the result.
	 *
	 * @param string   $to         Recipient phone number (international format).
	 * @param string   $body       Message body text.
	 * @param int|null $bookingId  Associated booking ID (for logging).
	 * @param int|null $guestId    Associated guest ID (for logging).
	 * @param int|null $templateId Source template ID (for logging).
	 */
	public function sendMessage( string $to, string $body, ?int $bookingId = null, ?int $guestId = null, ?int $templateId = null ): bool {
		// Create a queued log entry before attempting to send.
		$logEntry = $this->logRepo->create( [
			'template_id' => $templateId,
			'booking_id'  => $bookingId,
			'guest_id'    => $guestId,
			'to_phone'    => sanitize_text_field( $to ),
			'body'        => $body,
			'status'      => WhatsAppLog::STATUS_QUEUED,
		] );

		$result = $this->callWhatsAppApi( $to, $body );

		if ( $logEntry ) {
			if ( $result['success'] ) {
				$this->logRepo->updateStatus(
					$logEntry->id,
					WhatsAppLog::STATUS_SENT,
					null,
					$result['message_id'] ?? null
				);
			} else {
				$this->logRepo->updateStatus(
					$logEntry->id,
					WhatsAppLog::STATUS_FAILED,
					$result['error'] ?? __( 'Unknown error', 'nozule' )
				);
			}
		}

		if ( ! $result['success'] ) {
			$this->logger->error( "Failed to send WhatsApp message to {$to}", [
				'template_id' => $templateId,
				'booking_id'  => $bookingId,
				'error'       => $result['error'] ?? '',
			] );
		}

		return $result['success'];
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
			$this->logger->warning( "Cannot build WhatsApp variables: booking {$bookingId} not found." );
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
			'guest_phone'    => $guest->phone ?? '',
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

	// ── Settings Management ─────────────────────────────────────────

	/**
	 * Get all WhatsApp settings as a key-value array.
	 *
	 * @return array<string, string>
	 */
	public function getSettings(): array {
		$table = $this->db->table( 'whatsapp_settings' );
		$rows  = $this->db->getResults( "SELECT setting_key, setting_value FROM {$table}" );

		$settings = [];
		if ( is_array( $rows ) ) {
			foreach ( $rows as $row ) {
				$settings[ $row->setting_key ] = $row->setting_value;
			}
		}

		return $settings;
	}

	/**
	 * Update WhatsApp settings from a key-value array.
	 *
	 * @param array<string, string> $data Key-value pairs to update.
	 */
	public function updateSettings( array $data ): bool {
		$table       = $this->db->table( 'whatsapp_settings' );
		$allowedKeys = [ 'phone_number_id', 'access_token', 'business_id', 'enabled', 'api_version' ];

		foreach ( $data as $key => $value ) {
			if ( ! in_array( $key, $allowedKeys, true ) ) {
				continue;
			}

			$existing = $this->db->getRow(
				"SELECT id FROM {$table} WHERE setting_key = %s",
				$key
			);

			if ( $existing ) {
				$this->db->update(
					'whatsapp_settings',
					[
						'setting_value' => sanitize_text_field( $value ),
						'updated_at'    => current_time( 'mysql' ),
					],
					[ 'id' => $existing->id ]
				);
			} else {
				$this->db->insert( 'whatsapp_settings', [
					'setting_key'   => $key,
					'setting_value' => sanitize_text_field( $value ),
				] );
			}
		}

		return true;
	}

	// ── Private Helpers ─────────────────────────────────────────────

	/**
	 * Dispatch a WhatsApp message using a resolved template model.
	 *
	 * Selects the correct language (EN/AR) based on the locale variable
	 * or site-level settings, renders placeholders, and sends.
	 */
	private function dispatchTemplate( WhatsAppTemplate $template, array $variables, string $toPhone, ?int $bookingId, ?int $guestId ): bool {
		// Determine locale: check variables first, then settings, default to 'en'.
		$locale = $variables['locale'] ?? $this->settings->get( 'general.locale', 'en' );

		// Select the appropriate body.
		if ( $locale === 'ar' && ! empty( $template->body_ar ) ) {
			$body = $template->body_ar;
		} else {
			$body = $template->body;
		}

		// Render placeholders.
		$renderedBody = $this->renderTemplate( $body, $variables );

		return $this->sendMessage( $toPhone, $renderedBody, $bookingId, $guestId, $template->id );
	}

	/**
	 * Call the WhatsApp Business API to send a text message.
	 *
	 * Uses the Meta Graph API endpoint:
	 * https://graph.facebook.com/{api_version}/{phone_number_id}/messages
	 *
	 * @param string $to   Recipient phone number (international format, e.g. +966501234567).
	 * @param string $body The message text.
	 * @return array{ success: bool, message_id: ?string, error: ?string }
	 */
	private function callWhatsAppApi( string $to, string $body ): array {
		$waSettings = $this->getSettings();

		$phoneNumberId = $waSettings['phone_number_id'] ?? '';
		$accessToken   = $waSettings['access_token'] ?? '';
		$apiVersion    = $waSettings['api_version'] ?? 'v21.0';

		// Gracefully handle missing credentials.
		if ( empty( $phoneNumberId ) || empty( $accessToken ) ) {
			$this->logger->warning( 'WhatsApp API credentials not configured. Cannot send message.' );
			return [
				'success'    => false,
				'message_id' => null,
				'error'      => __( 'WhatsApp API credentials not configured.', 'nozule' ),
			];
		}

		$url = sprintf(
			'https://graph.facebook.com/%s/%s/messages',
			$apiVersion,
			$phoneNumberId
		);

		// Clean phone number: remove spaces, dashes; keep + prefix.
		$cleanPhone = preg_replace( '/[^0-9+]/', '', $to );

		$payload = [
			'messaging_product' => 'whatsapp',
			'to'                => $cleanPhone,
			'type'              => 'text',
			'text'              => [
				'body' => $body,
			],
		];

		$response = wp_remote_post( $url, [
			'headers' => [
				'Authorization' => 'Bearer ' . $accessToken,
				'Content-Type'  => 'application/json',
			],
			'body'    => wp_json_encode( $payload ),
			'timeout' => 30,
		] );

		// Handle WP_Error (network failure, timeout, etc.).
		if ( is_wp_error( $response ) ) {
			return [
				'success'    => false,
				'message_id' => null,
				'error'      => $response->get_error_message(),
			];
		}

		$httpCode     = wp_remote_retrieve_response_code( $response );
		$responseBody = wp_remote_retrieve_body( $response );
		$decoded      = json_decode( $responseBody, true );

		if ( $httpCode >= 200 && $httpCode < 300 ) {
			$messageId = null;
			if ( ! empty( $decoded['messages'][0]['id'] ) ) {
				$messageId = $decoded['messages'][0]['id'];
			}
			return [
				'success'    => true,
				'message_id' => $messageId,
				'error'      => null,
			];
		}

		// Extract error message from API response.
		$errorMsg = __( 'WhatsApp API error', 'nozule' );
		if ( ! empty( $decoded['error']['message'] ) ) {
			$errorMsg = $decoded['error']['message'];
		} elseif ( ! empty( $decoded['error']['error_data']['details'] ) ) {
			$errorMsg = $decoded['error']['error_data']['details'];
		}

		$this->logger->error( 'WhatsApp API call failed', [
			'http_code'    => $httpCode,
			'error'        => $errorMsg,
			'phone_number' => $cleanPhone,
		] );

		return [
			'success'    => false,
			'message_id' => null,
			'error'      => sprintf( '%s (HTTP %d)', $errorMsg, $httpCode ),
		];
	}
}
