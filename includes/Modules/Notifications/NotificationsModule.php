<?php

namespace Venezia\Modules\Notifications;

use Venezia\Core\BaseModule;
use Venezia\Core\Container;
use Venezia\Core\Database;
use Venezia\Core\Logger;
use Venezia\Core\SettingsManager;
use Venezia\Modules\Notifications\Repositories\NotificationRepository;
use Venezia\Modules\Notifications\Services\NotificationService;
use Venezia\Modules\Notifications\Services\TemplateService;

/**
 * Notifications module bootstrap.
 *
 * Registers all notification-related services, repositories, and hooks.
 */
class NotificationsModule extends BaseModule {

	/**
	 * Register the module's services and hooks.
	 */
	public function register(): void {
		$this->registerServices();
		$this->registerHooks();
	}

	/**
	 * Register notification services in the DI container.
	 */
	private function registerServices(): void {
		$this->container->singleton(
			NotificationRepository::class,
			function ( Container $c ): NotificationRepository {
				return new NotificationRepository(
					$c->get( Database::class )
				);
			}
		);

		$this->container->singleton(
			TemplateService::class,
			function ( Container $c ): TemplateService {
				return new TemplateService(
					$c->get( SettingsManager::class )
				);
			}
		);

		$this->container->singleton(
			NotificationService::class,
			function ( Container $c ): NotificationService {
				return new NotificationService(
					$c->get( NotificationRepository::class ),
					$c->get( TemplateService::class ),
					$c->get( SettingsManager::class ),
					$c->get( Database::class ),
					$c->get( Logger::class )
				);
			}
		);
	}

	/**
	 * Register WordPress hooks for the notifications module.
	 */
	private function registerHooks(): void {
		// Listen for booking events to trigger notifications.
		add_action( 'venezia/bookings/created', [ $this, 'onBookingCreated' ], 10, 1 );
		add_action( 'venezia/bookings/confirmed', [ $this, 'onBookingConfirmed' ], 10, 1 );
		add_action( 'venezia/bookings/cancelled', [ $this, 'onBookingCancelled' ], 10, 1 );
		add_action( 'venezia/bookings/checked_out', [ $this, 'onBookingCheckedOut' ], 10, 1 );
		add_action( 'venezia/payments/received', [ $this, 'onPaymentReceived' ], 10, 2 );

		// Process the notification queue via cron.
		add_action( 'venezia/cron/process_notifications', [ $this, 'processQueue' ] );

		// Schedule the queue processor if not already scheduled.
		add_action( 'init', [ $this, 'scheduleQueueProcessor' ] );

		// Clean up old notifications during daily maintenance.
		add_action( 'vhm_daily_maintenance', [ $this, 'cleanOldNotifications' ], 20 );
	}

	/**
	 * Handle booking created event.
	 *
	 * @param object $booking The newly created booking.
	 */
	public function onBookingCreated( object $booking ): void {
		$service = $this->container->get( NotificationService::class );
		$service->queue( $booking, 'booking_confirmation' );
	}

	/**
	 * Handle booking confirmed event.
	 *
	 * @param object $booking The confirmed booking.
	 */
	public function onBookingConfirmed( object $booking ): void {
		$service = $this->container->get( NotificationService::class );
		$service->queue( $booking, 'booking_confirmed' );
	}

	/**
	 * Handle booking cancelled event.
	 *
	 * @param object $booking The cancelled booking.
	 */
	public function onBookingCancelled( object $booking ): void {
		$service = $this->container->get( NotificationService::class );

		// Cancel any pending notifications for this booking.
		$service->cancelForBooking( (int) $booking->id );

		// Send the cancellation notification.
		$service->queue( $booking, 'booking_cancelled' );
	}

	/**
	 * Handle booking checked-out event.
	 *
	 * Queues a review request notification after checkout.
	 *
	 * @param object $booking The checked-out booking.
	 */
	public function onBookingCheckedOut( object $booking ): void {
		$settings = $this->container->get( SettingsManager::class );

		// Only send review requests if enabled.
		$send_review = $settings->get( 'notifications.review_request_enabled', true );
		if ( $send_review ) {
			$service = $this->container->get( NotificationService::class );
			$service->queue( $booking, 'review_request' );
		}
	}

	/**
	 * Handle payment received event.
	 *
	 * @param object $booking The booking associated with the payment.
	 * @param object $payment The payment record.
	 */
	public function onPaymentReceived( object $booking, object $payment ): void {
		$service = $this->container->get( NotificationService::class );
		$service->queue( $booking, 'payment_receipt' );
	}

	/**
	 * Process the notification queue.
	 *
	 * Called by the cron event to send queued notifications.
	 */
	public function processQueue(): void {
		$service = $this->container->get( NotificationService::class );
		$service->processQueue();
	}

	/**
	 * Schedule the queue processor cron event.
	 */
	public function scheduleQueueProcessor(): void {
		// Register the custom cron interval.
		add_filter( 'cron_schedules', [ $this, 'addCronInterval' ] );

		if ( ! wp_next_scheduled( 'venezia/cron/process_notifications' ) ) {
			wp_schedule_event( time(), 'five_minutes', 'venezia/cron/process_notifications' );
		}
	}

	/**
	 * Add a five-minute cron interval for notification processing.
	 *
	 * @param array $schedules Existing cron schedules.
	 * @return array Modified cron schedules.
	 */
	public function addCronInterval( array $schedules ): array {
		if ( ! isset( $schedules['five_minutes'] ) ) {
			$schedules['five_minutes'] = [
				'interval' => 300,
				'display'  => __( 'Every Five Minutes', 'venezia-hotel' ),
			];
		}

		return $schedules;
	}

	/**
	 * Clean up old notification records.
	 *
	 * Called during daily maintenance.
	 */
	public function cleanOldNotifications(): void {
		$settings = $this->container->get( SettingsManager::class );
		$days     = (int) $settings->get( 'notifications.retention_days', 180 );
		$repo     = $this->container->get( NotificationRepository::class );
		$deleted  = $repo->deleteOlderThan( $days );

		if ( $deleted > 0 ) {
			$logger = $this->container->get( Logger::class );
			$logger->info( 'Old notification records cleaned.', [
				'deleted' => $deleted,
				'days'    => $days,
			] );
		}
	}
}
