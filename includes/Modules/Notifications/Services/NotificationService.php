<?php

namespace Venezia\Modules\Notifications\Services;

use Venezia\Core\Database;
use Venezia\Core\Logger;
use Venezia\Core\SettingsManager;
use Venezia\Modules\Notifications\Models\Notification;
use Venezia\Modules\Notifications\Repositories\NotificationRepository;

/**
 * Main notification service handling queuing, processing, and sending.
 */
class NotificationService {

	private NotificationRepository $repository;
	private TemplateService $templates;
	private SettingsManager $settings;
	private Database $db;
	private Logger $logger;

	public function __construct(
		NotificationRepository $repository,
		TemplateService $templates,
		SettingsManager $settings,
		Database $db,
		Logger $logger
	) {
		$this->repository = $repository;
		$this->templates  = $templates;
		$this->settings   = $settings;
		$this->db         = $db;
		$this->logger     = $logger;
	}

	/**
	 * Queue a notification for a booking event.
	 *
	 * Creates a notification record in 'queued' status, ready to be processed.
	 * Skips duplicates if the same notification type has already been queued/sent
	 * for the booking on the same channel.
	 *
	 * @param object $booking Booking model instance (or stdClass with booking fields).
	 * @param string $type    Notification type (e.g., 'booking_confirmation').
	 * @param string $channel Notification channel. Default 'email'.
	 * @return Notification|null The created notification, or null if skipped/failed.
	 */
	public function queue( object $booking, string $type, string $channel = 'email' ): ?Notification {
		// Validate notification type.
		if ( ! in_array( $type, Notification::TYPES, true ) ) {
			$this->logger->warning( 'Invalid notification type attempted.', [
				'type'       => $type,
				'booking_id' => $booking->id ?? 0,
			] );
			return null;
		}

		// Validate channel.
		if ( ! in_array( $channel, Notification::CHANNELS, true ) ) {
			$this->logger->warning( 'Invalid notification channel attempted.', [
				'channel'    => $channel,
				'booking_id' => $booking->id ?? 0,
			] );
			return null;
		}

		// Check if notifications are enabled for this type and channel.
		if ( ! $this->isEnabled( $type, $channel ) ) {
			$this->logger->debug( 'Notification skipped: disabled by settings.', [
				'type'    => $type,
				'channel' => $channel,
			] );
			return null;
		}

		$booking_id = (int) ( $booking->id ?? 0 );
		$guest_id   = (int) ( $booking->guest_id ?? 0 );

		// Prevent duplicate notifications for the same booking/type/channel.
		if ( $booking_id > 0 && $this->repository->hasBeenSent( $booking_id, $type, $channel ) ) {
			$this->logger->debug( 'Notification skipped: already queued or sent.', [
				'type'       => $type,
				'channel'    => $channel,
				'booking_id' => $booking_id,
			] );
			return null;
		}

		// Resolve the guest information for building template content.
		$guest = $this->resolveGuest( $guest_id );

		// Build the template content.
		$template_vars = $this->buildTemplateVars( $booking, $guest );
		$rendered      = $this->getTemplateContent( $type, $booking, $guest );

		// Determine the recipient.
		$recipient = $this->resolveRecipient( $channel, $guest, $booking );

		if ( empty( $recipient ) ) {
			$this->logger->warning( 'Notification skipped: no recipient found.', [
				'type'       => $type,
				'channel'    => $channel,
				'booking_id' => $booking_id,
			] );
			return null;
		}

		$notification = $this->repository->create( [
			'booking_id'    => $booking_id ?: null,
			'guest_id'      => $guest_id ?: null,
			'type'          => $type,
			'channel'       => $channel,
			'recipient'     => $recipient,
			'subject'       => $rendered['subject'],
			'content'       => $rendered['body'],
			'content_html'  => $rendered['body_html'],
			'template_id'   => $type,
			'template_vars' => $template_vars,
			'status'        => 'queued',
		] );

		if ( ! $notification ) {
			$this->logger->error( 'Failed to create notification record.', [
				'type'       => $type,
				'channel'    => $channel,
				'booking_id' => $booking_id,
			] );
			return null;
		}

		$this->logger->info( 'Notification queued.', [
			'notification_id' => $notification->id,
			'type'            => $type,
			'channel'         => $channel,
			'booking_id'      => $booking_id,
		] );

		/**
		 * Fires after a notification has been queued.
		 *
		 * @param Notification $notification The queued notification.
		 * @param object       $booking      The associated booking.
		 */
		do_action( 'venezia/notifications/queued', $notification, $booking );

		return $notification;
	}

	/**
	 * Process queued notifications.
	 *
	 * Retrieves up to $limit queued notifications and attempts to send each one.
	 *
	 * @param int $limit Maximum number of notifications to process per batch.
	 * @return array{ sent: int, failed: int, skipped: int }
	 */
	public function processQueue( int $limit = 50 ): array {
		$queued = $this->repository->getQueued( $limit );
		$result = [
			'sent'    => 0,
			'failed'  => 0,
			'skipped' => 0,
		];

		if ( empty( $queued ) ) {
			return $result;
		}

		$this->logger->info( 'Processing notification queue.', [
			'count' => count( $queued ),
		] );

		foreach ( $queued as $notification ) {
			// Mark as sending to prevent duplicate processing.
			$this->repository->updateStatus( $notification->id, 'sending' );

			$sent = $this->send( $notification );

			if ( $sent ) {
				++$result['sent'];
			} else {
				++$result['failed'];
			}
		}

		$this->logger->info( 'Notification queue processing complete.', $result );

		/**
		 * Fires after the notification queue has been processed.
		 *
		 * @param array $result Processing results.
		 */
		do_action( 'venezia/notifications/queue_processed', $result );

		return $result;
	}

	/**
	 * Send a single notification via the appropriate channel.
	 *
	 * @param Notification $notification The notification to send.
	 * @return bool True if sent successfully.
	 */
	public function send( Notification $notification ): bool {
		if ( ! $notification->canRetry() && ! $notification->isSending() ) {
			$this->logger->debug( 'Notification cannot be sent.', [
				'notification_id' => $notification->id,
				'status'          => $notification->status,
				'attempts'        => $notification->attempts,
			] );
			return false;
		}

		try {
			$sent = match ( $notification->channel ) {
				'email'    => $this->sendEmail( $notification ),
				'sms'      => $this->sendSMS( $notification ),
				'whatsapp' => $this->sendWhatsApp( $notification ),
				'push'     => $this->sendPush( $notification ),
				default    => false,
			};

			if ( $sent ) {
				$this->repository->markAsSent( $notification->id );

				$this->logger->info( 'Notification sent successfully.', [
					'notification_id' => $notification->id,
					'type'            => $notification->type,
					'channel'         => $notification->channel,
					'recipient'       => $notification->recipient,
				] );

				/**
				 * Fires after a notification has been sent successfully.
				 *
				 * @param Notification $notification The sent notification.
				 */
				do_action( 'venezia/notifications/sent', $notification );

				return true;
			}

			// Sending returned false without an exception.
			$this->repository->incrementAttemptAndRequeue(
				$notification->id,
				__( 'Send method returned false.', 'venezia-hotel' )
			);

			return false;
		} catch ( \Throwable $e ) {
			$this->logger->error( 'Notification send failed.', [
				'notification_id' => $notification->id,
				'channel'         => $notification->channel,
				'error'           => $e->getMessage(),
			] );

			$this->repository->incrementAttemptAndRequeue(
				$notification->id,
				$e->getMessage()
			);

			/**
			 * Fires when a notification fails to send.
			 *
			 * @param Notification $notification The failed notification.
			 * @param \Throwable   $e            The exception that caused the failure.
			 */
			do_action( 'venezia/notifications/failed', $notification, $e );

			return false;
		}
	}

	/**
	 * Send a notification via email using wp_mail.
	 *
	 * @param Notification $notification The notification to send.
	 * @return bool True if wp_mail returns success.
	 */
	public function sendEmail( Notification $notification ): bool {
		$to      = $notification->recipient;
		$subject = $notification->subject ?? '';
		$headers = [];

		// Determine the sender.
		$from_name  = $this->settings->get( 'hotel.name', get_bloginfo( 'name' ) );
		$from_email = $this->settings->get( 'notifications.from_email', get_option( 'admin_email' ) );

		$headers[] = sprintf( 'From: %s <%s>', $from_name, $from_email );

		// Use HTML content if available.
		if ( ! empty( $notification->content_html ) ) {
			$headers[] = 'Content-Type: text/html; charset=UTF-8';
			$body      = $notification->content_html;
		} else {
			$headers[] = 'Content-Type: text/plain; charset=UTF-8';
			$body      = $notification->content;
		}

		// Optional Reply-To header.
		$reply_to = $this->settings->get( 'notifications.reply_to_email' );
		if ( $reply_to ) {
			$headers[] = sprintf( 'Reply-To: %s', $reply_to );
		}

		// Optional BCC for admin copy.
		$bcc_admin = $this->settings->get( 'notifications.bcc_admin', false );
		if ( $bcc_admin ) {
			$admin_email = $this->settings->get( 'hotel.email', get_option( 'admin_email' ) );
			if ( $admin_email !== $to ) {
				$headers[] = sprintf( 'Bcc: %s', $admin_email );
			}
		}

		/**
		 * Filter the email arguments before sending.
		 *
		 * @param array        $args         The email arguments.
		 * @param Notification $notification  The notification being sent.
		 */
		$args = apply_filters( 'venezia/notifications/email_args', [
			'to'      => $to,
			'subject' => $subject,
			'body'    => $body,
			'headers' => $headers,
		], $notification );

		return wp_mail(
			$args['to'],
			$args['subject'],
			$args['body'],
			$args['headers']
		);
	}

	/**
	 * Send a notification via SMS.
	 *
	 * This is a placeholder for SMS gateway integration. To implement, either:
	 * - Hook into 'venezia/notifications/send_sms' action.
	 * - Extend this class and override this method.
	 * - Use the 'venezia/notifications/sms_handler' filter to provide a callback.
	 *
	 * @param Notification $notification The notification to send.
	 * @return bool True if sent successfully.
	 */
	public function sendSMS( Notification $notification ): bool {
		/**
		 * Filter to provide a custom SMS sending handler.
		 *
		 * Return a callable that accepts a Notification and returns bool.
		 *
		 * @param callable|null $handler      The SMS handler callback.
		 * @param Notification  $notification The notification to send.
		 */
		$handler = apply_filters( 'venezia/notifications/sms_handler', null, $notification );

		if ( is_callable( $handler ) ) {
			return (bool) call_user_func( $handler, $notification );
		}

		// Log that SMS is not configured.
		$this->logger->warning( 'SMS notification skipped: no SMS gateway configured.', [
			'notification_id' => $notification->id,
			'recipient'       => $notification->recipient,
		] );

		/**
		 * Fires when an SMS notification is attempted but no handler is configured.
		 *
		 * Allows third-party plugins to handle SMS sending.
		 *
		 * @param Notification $notification The notification to send.
		 */
		do_action( 'venezia/notifications/send_sms', $notification );

		return false;
	}

	/**
	 * Send a notification via WhatsApp.
	 *
	 * This is a placeholder for WhatsApp Business API integration. To implement, either:
	 * - Hook into 'venezia/notifications/send_whatsapp' action.
	 * - Extend this class and override this method.
	 * - Use the 'venezia/notifications/whatsapp_handler' filter to provide a callback.
	 *
	 * @param Notification $notification The notification to send.
	 * @return bool True if sent successfully.
	 */
	public function sendWhatsApp( Notification $notification ): bool {
		/**
		 * Filter to provide a custom WhatsApp sending handler.
		 *
		 * Return a callable that accepts a Notification and returns bool.
		 *
		 * @param callable|null $handler      The WhatsApp handler callback.
		 * @param Notification  $notification The notification to send.
		 */
		$handler = apply_filters( 'venezia/notifications/whatsapp_handler', null, $notification );

		if ( is_callable( $handler ) ) {
			return (bool) call_user_func( $handler, $notification );
		}

		// Log that WhatsApp is not configured.
		$this->logger->warning( 'WhatsApp notification skipped: no WhatsApp API configured.', [
			'notification_id' => $notification->id,
			'recipient'       => $notification->recipient,
		] );

		/**
		 * Fires when a WhatsApp notification is attempted but no handler is configured.
		 *
		 * Allows third-party plugins to handle WhatsApp sending.
		 *
		 * @param Notification $notification The notification to send.
		 */
		do_action( 'venezia/notifications/send_whatsapp', $notification );

		return false;
	}

	/**
	 * Send a push notification.
	 *
	 * This is a placeholder for push notification integration.
	 *
	 * @param Notification $notification The notification to send.
	 * @return bool True if sent successfully.
	 */
	public function sendPush( Notification $notification ): bool {
		/**
		 * Filter to provide a custom push notification handler.
		 *
		 * @param callable|null $handler      The push handler callback.
		 * @param Notification  $notification The notification to send.
		 */
		$handler = apply_filters( 'venezia/notifications/push_handler', null, $notification );

		if ( is_callable( $handler ) ) {
			return (bool) call_user_func( $handler, $notification );
		}

		$this->logger->warning( 'Push notification skipped: no push service configured.', [
			'notification_id' => $notification->id,
		] );

		/**
		 * Fires when a push notification is attempted but no handler is configured.
		 *
		 * @param Notification $notification The notification to send.
		 */
		do_action( 'venezia/notifications/send_push', $notification );

		return false;
	}

	/**
	 * Send scheduled reminders for bookings arriving or departing tomorrow.
	 *
	 * This method is typically called by the WordPress cron event 'vhm_send_reminders'.
	 *
	 * @return array{ check_in: int, check_out: int }
	 */
	public function sendScheduledReminders(): array {
		$result = [
			'check_in'  => 0,
			'check_out' => 0,
		];

		$tomorrow       = wp_date( 'Y-m-d', strtotime( '+1 day' ) );
		$bookings_table = $this->db->table( 'bookings' );
		$guests_table   = $this->db->table( 'guests' );

		// Find bookings with check-in tomorrow.
		$check_in_bookings = $this->db->getResults(
			"SELECT b.*, g.first_name, g.last_name, g.email, g.phone
			FROM {$bookings_table} b
			LEFT JOIN {$guests_table} g ON b.guest_id = g.id
			WHERE b.check_in = %s
			AND b.status IN ('confirmed', 'pending')
			ORDER BY b.id ASC",
			$tomorrow
		);

		foreach ( $check_in_bookings as $booking ) {
			$notification = $this->queue( $booking, 'check_in_reminder' );
			if ( $notification ) {
				++$result['check_in'];
			}
		}

		// Find bookings with check-out tomorrow.
		$check_out_bookings = $this->db->getResults(
			"SELECT b.*, g.first_name, g.last_name, g.email, g.phone
			FROM {$bookings_table} b
			LEFT JOIN {$guests_table} g ON b.guest_id = g.id
			WHERE b.check_out = %s
			AND b.status = 'checked_in'
			ORDER BY b.id ASC",
			$tomorrow
		);

		foreach ( $check_out_bookings as $booking ) {
			$notification = $this->queue( $booking, 'check_out_reminder' );
			if ( $notification ) {
				++$result['check_out'];
			}
		}

		$this->logger->info( 'Scheduled reminders processed.', $result );

		/**
		 * Fires after scheduled reminders have been processed.
		 *
		 * @param array $result Summary of reminders sent.
		 */
		do_action( 'venezia/notifications/reminders_sent', $result );

		return $result;
	}

	/**
	 * Build notification content from templates.
	 *
	 * @param string      $type    Notification type.
	 * @param object      $booking Booking data object.
	 * @param object|null $guest   Guest data object.
	 * @return array{ subject: string, body: string, body_html: string }
	 */
	public function getTemplateContent( string $type, object $booking, ?object $guest = null ): array {
		$vars = $this->buildTemplateVars( $booking, $guest );

		return $this->templates->render( $type, $vars );
	}

	/**
	 * Cancel all pending notifications for a booking.
	 *
	 * @param int $booking_id Booking ID.
	 * @return int Number of notifications cancelled.
	 */
	public function cancelForBooking( int $booking_id ): int {
		$count = $this->repository->cancelForBooking( $booking_id );

		if ( $count > 0 ) {
			$this->logger->info( 'Notifications cancelled for booking.', [
				'booking_id' => $booking_id,
				'count'      => $count,
			] );
		}

		return $count;
	}

	/**
	 * Resend a previously sent or failed notification.
	 *
	 * Creates a new notification record based on the original.
	 *
	 * @param int $notification_id The original notification ID.
	 * @return Notification|null The new notification, or null on failure.
	 */
	public function resend( int $notification_id ): ?Notification {
		$original = $this->repository->find( $notification_id );

		if ( ! $original ) {
			return null;
		}

		$new = $this->repository->create( [
			'booking_id'    => $original->booking_id,
			'guest_id'      => $original->guest_id,
			'type'          => $original->type,
			'channel'       => $original->channel,
			'recipient'     => $original->recipient,
			'subject'       => $original->subject,
			'content'       => $original->content,
			'content_html'  => $original->content_html,
			'template_id'   => $original->template_id,
			'template_vars' => $original->getTemplateVars(),
			'status'        => 'queued',
		] );

		if ( $new ) {
			$this->logger->info( 'Notification resend queued.', [
				'original_id' => $notification_id,
				'new_id'      => $new->id,
			] );
		}

		return $new;
	}

	/**
	 * Check whether notifications are enabled for a given type and channel.
	 *
	 * @param string $type    Notification type.
	 * @param string $channel Notification channel.
	 */
	private function isEnabled( string $type, string $channel ): bool {
		// Global notifications toggle.
		$global_enabled = $this->settings->get( 'notifications.enabled', true );
		if ( ! $global_enabled ) {
			return false;
		}

		// Channel-level toggle.
		$channel_enabled = $this->settings->get( "notifications.{$channel}_enabled", $channel === 'email' );
		if ( ! $channel_enabled ) {
			return false;
		}

		// Type-level toggle.
		$type_enabled = $this->settings->get( "notifications.{$type}_enabled", true );
		if ( ! $type_enabled ) {
			return false;
		}

		/**
		 * Filter whether a notification type/channel combination is enabled.
		 *
		 * @param bool   $enabled Whether the notification is enabled.
		 * @param string $type    The notification type.
		 * @param string $channel The notification channel.
		 */
		return (bool) apply_filters( 'venezia/notifications/is_enabled', true, $type, $channel );
	}

	/**
	 * Build the template variables array from booking and guest data.
	 *
	 * @param object      $booking Booking data object.
	 * @param object|null $guest   Guest data object.
	 * @return array<string, mixed>
	 */
	private function buildTemplateVars( object $booking, ?object $guest = null ): array {
		$first_name = $guest->first_name ?? $booking->first_name ?? '';
		$last_name  = $guest->last_name ?? $booking->last_name ?? '';
		$email      = $guest->email ?? $booking->email ?? '';
		$phone      = $guest->phone ?? $booking->phone ?? '';

		$check_in  = $booking->check_in ?? '';
		$check_out = $booking->check_out ?? '';

		// Format dates for display.
		$date_format    = get_option( 'date_format', 'Y-m-d' );
		$check_in_fmt   = $check_in ? wp_date( $date_format, strtotime( $check_in ) ) : '';
		$check_out_fmt  = $check_out ? wp_date( $date_format, strtotime( $check_out ) ) : '';

		$total_price = $booking->total_price ?? '0.00';
		$amount_paid = $booking->amount_paid ?? '0.00';
		$balance_due = number_format( (float) $total_price - (float) $amount_paid, 2, '.', '' );

		// Look up room type name if room_type_id is available.
		$room_type_name = $booking->room_type_name ?? '';
		if ( empty( $room_type_name ) && ! empty( $booking->room_type_id ) ) {
			$room_types_table = $this->db->table( 'room_types' );
			$room_type_name   = $this->db->getVar(
				"SELECT name FROM {$room_types_table} WHERE id = %d",
				(int) $booking->room_type_id
			) ?? '';
		}

		// Look up room number if room_id is available.
		$room_number = $booking->room_number ?? '';
		if ( empty( $room_number ) && ! empty( $booking->room_id ) ) {
			$rooms_table = $this->db->table( 'rooms' );
			$room_number = $this->db->getVar(
				"SELECT room_number FROM {$rooms_table} WHERE id = %d",
				(int) $booking->room_id
			) ?? '';
		}

		// Look up rate plan name if rate_plan_id is available.
		$rate_plan_name = $booking->rate_plan_name ?? '';
		if ( empty( $rate_plan_name ) && ! empty( $booking->rate_plan_id ) ) {
			$rate_plans_table = $this->db->table( 'rate_plans' );
			$rate_plan_name   = $this->db->getVar(
				"SELECT name FROM {$rate_plans_table} WHERE id = %d",
				(int) $booking->rate_plan_id
			) ?? '';
		}

		// Build confirmation and cancellation URLs.
		$site_url         = home_url();
		$booking_number   = $booking->booking_number ?? '';
		$confirmation_url = ! empty( $booking_number )
			? add_query_arg( [ 'booking' => $booking_number ], $site_url . '/booking-confirmation/' )
			: '';
		$cancellation_url = ! empty( $booking_number )
			? add_query_arg( [ 'booking' => $booking_number ], $site_url . '/booking-cancellation/' )
			: '';

		$vars = [
			'guest_name'        => trim( $first_name . ' ' . $last_name ),
			'guest_first_name'  => $first_name,
			'guest_last_name'   => $last_name,
			'guest_email'       => $email,
			'guest_phone'       => $phone,
			'booking_number'    => $booking_number,
			'check_in'          => $check_in_fmt,
			'check_out'         => $check_out_fmt,
			'nights'            => $booking->nights ?? '',
			'adults'            => $booking->adults ?? '',
			'children'          => $booking->children ?? '0',
			'room_type'         => $room_type_name,
			'room_number'       => $room_number,
			'rate_plan'         => $rate_plan_name,
			'total_price'       => $total_price,
			'currency'          => $booking->currency ?? $this->settings->get( 'currency.default', 'USD' ),
			'amount_paid'       => $amount_paid,
			'balance_due'       => $balance_due,
			'booking_status'    => $booking->status ?? '',
			'payment_status'    => $booking->payment_status ?? '',
			'special_requests'  => $booking->special_requests ?? '',
			'confirmation_url'  => $confirmation_url,
			'cancellation_url'  => $cancellation_url,
		];

		/**
		 * Filter the template variables before rendering.
		 *
		 * @param array  $vars    The template variables.
		 * @param object $booking The booking data.
		 * @param object|null $guest The guest data.
		 */
		return apply_filters( 'venezia/notifications/template_vars', $vars, $booking, $guest );
	}

	/**
	 * Resolve the recipient address based on the channel.
	 *
	 * @param string      $channel Notification channel.
	 * @param object|null $guest   Guest data object.
	 * @param object      $booking Booking data object.
	 * @return string The recipient address/number.
	 */
	private function resolveRecipient( string $channel, ?object $guest, object $booking ): string {
		return match ( $channel ) {
			'email'    => $guest->email ?? $booking->email ?? '',
			'sms'      => $guest->phone ?? $booking->phone ?? '',
			'whatsapp' => $guest->phone ?? $booking->phone ?? '',
			'push'     => (string) ( $guest->wp_user_id ?? $booking->wp_user_id ?? '' ),
			default    => '',
		};
	}

	/**
	 * Resolve guest information from the database.
	 *
	 * @param int $guest_id Guest ID.
	 * @return object|null Guest row as stdClass, or null if not found.
	 */
	private function resolveGuest( int $guest_id ): ?object {
		if ( $guest_id <= 0 ) {
			return null;
		}

		$table = $this->db->table( 'guests' );

		return $this->db->getRow(
			"SELECT * FROM {$table} WHERE id = %d",
			$guest_id
		);
	}
}
