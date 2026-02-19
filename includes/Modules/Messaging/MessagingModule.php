<?php

namespace Nozule\Modules\Messaging;

use Nozule\Core\BaseModule;
use Nozule\Core\Container;
use Nozule\Core\Database;
use Nozule\Core\Logger;
use Nozule\Core\SettingsManager;
use Nozule\Modules\Messaging\Controllers\EmailTemplateController;
use Nozule\Modules\Messaging\Repositories\EmailLogRepository;
use Nozule\Modules\Messaging\Repositories\EmailTemplateRepository;
use Nozule\Modules\Messaging\Services\EmailService;

/**
 * Messaging module bootstrap.
 *
 * Registers all services, repositories, and controllers related to
 * guest email messaging. Hooks into booking lifecycle events to
 * dispatch automated emails.
 */
class MessagingModule extends BaseModule {

	/**
	 * Register the module's services and hooks.
	 */
	public function register(): void {
		$this->registerServices();
		$this->registerHooks();
	}

	/**
	 * Bind module services into the DI container.
	 */
	private function registerServices(): void {
		// Repositories.
		$this->container->singleton( EmailTemplateRepository::class, function ( Container $c ) {
			return new EmailTemplateRepository( $c->get( Database::class ) );
		} );

		$this->container->singleton( EmailLogRepository::class, function ( Container $c ) {
			return new EmailLogRepository( $c->get( Database::class ) );
		} );

		// Core service.
		$this->container->singleton( EmailService::class, function ( Container $c ) {
			return new EmailService(
				$c->get( EmailTemplateRepository::class ),
				$c->get( EmailLogRepository::class ),
				$c->get( SettingsManager::class ),
				$c->get( Logger::class ),
				$c->get( Database::class )
			);
		} );

		// Controller.
		$this->container->singleton( EmailTemplateController::class, function ( Container $c ) {
			return new EmailTemplateController(
				$c->get( EmailService::class ),
				$c->get( EmailTemplateRepository::class ),
				$c->get( EmailLogRepository::class )
			);
		} );
	}

	/**
	 * Register WordPress hooks.
	 */
	private function registerHooks(): void {
		// Register REST routes on rest_api_init.
		add_action( 'rest_api_init', function () {
			$this->container->get( EmailTemplateController::class )->registerRoutes();
		} );

		// ── Booking lifecycle email triggers ────────────────────────

		// Booking confirmed → send confirmation email.
		add_action( 'nozule/booking/confirmed', function ( int $bookingId ) {
			$this->sendBookingEmail( $bookingId, 'booking_confirmed' );
		} );

		// Guest checked in → send check-in email.
		add_action( 'nozule/booking/checked_in', function ( int $bookingId ) {
			$this->sendBookingEmail( $bookingId, 'booking_checked_in' );
		} );

		// Guest checked out → send check-out email.
		add_action( 'nozule/booking/checked_out', function ( int $bookingId ) {
			$this->sendBookingEmail( $bookingId, 'booking_checked_out' );
		} );
	}

	/**
	 * Build variables from a booking and send the trigger email.
	 *
	 * @param int    $bookingId    The booking ID.
	 * @param string $triggerEvent The trigger event name.
	 */
	private function sendBookingEmail( int $bookingId, string $triggerEvent ): void {
		try {
			/** @var EmailService $emailService */
			$emailService = $this->container->get( EmailService::class );

			$variables = $emailService->buildVariablesFromBooking( $bookingId );

			if ( empty( $variables ) || empty( $variables['guest_email'] ) ) {
				return;
			}

			$toEmail = $variables['guest_email'];

			// Look up the guest ID from the booking for logging.
			$db       = $this->container->get( Database::class );
			$table    = $db->table( 'bookings' );
			$guestId  = (int) $db->getVar(
				"SELECT guest_id FROM {$table} WHERE id = %d",
				$bookingId
			);

			$emailService->sendByTrigger(
				$triggerEvent,
				$variables,
				$toEmail,
				$bookingId,
				$guestId ?: null
			);
		} catch ( \Throwable $e ) {
			/** @var Logger $logger */
			$logger = $this->container->get( Logger::class );
			$logger->error( "Failed to send {$triggerEvent} email for booking {$bookingId}", [
				'error' => $e->getMessage(),
			] );
		}
	}
}
