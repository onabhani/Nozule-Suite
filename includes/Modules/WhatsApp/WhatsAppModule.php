<?php

namespace Nozule\Modules\WhatsApp;

use Nozule\Core\BaseModule;
use Nozule\Core\Container;
use Nozule\Core\Database;
use Nozule\Core\Logger;
use Nozule\Core\SettingsManager;
use Nozule\Modules\WhatsApp\Controllers\WhatsAppController;
use Nozule\Modules\WhatsApp\Repositories\WhatsAppLogRepository;
use Nozule\Modules\WhatsApp\Repositories\WhatsAppTemplateRepository;
use Nozule\Modules\WhatsApp\Services\WhatsAppService;

/**
 * WhatsApp messaging module bootstrap.
 *
 * Registers all services, repositories, and controllers related to
 * WhatsApp Business API messaging. Hooks into booking lifecycle events
 * to dispatch automated WhatsApp notifications.
 */
class WhatsAppModule extends BaseModule {

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
		$this->container->singleton( WhatsAppTemplateRepository::class, function ( Container $c ) {
			return new WhatsAppTemplateRepository( $c->get( Database::class ) );
		} );

		$this->container->singleton( WhatsAppLogRepository::class, function ( Container $c ) {
			return new WhatsAppLogRepository( $c->get( Database::class ) );
		} );

		// Core service.
		$this->container->singleton( WhatsAppService::class, function ( Container $c ) {
			return new WhatsAppService(
				$c->get( WhatsAppTemplateRepository::class ),
				$c->get( WhatsAppLogRepository::class ),
				$c->get( SettingsManager::class ),
				$c->get( Logger::class ),
				$c->get( Database::class )
			);
		} );

		// Controller.
		$this->container->singleton( WhatsAppController::class, function ( Container $c ) {
			return new WhatsAppController(
				$c->get( WhatsAppService::class ),
				$c->get( WhatsAppTemplateRepository::class ),
				$c->get( WhatsAppLogRepository::class )
			);
		} );
	}

	/**
	 * Register WordPress hooks.
	 */
	private function registerHooks(): void {
		// Register REST routes on rest_api_init.
		add_action( 'rest_api_init', function () {
			$this->container->get( WhatsAppController::class )->registerRoutes();
		} );

		// ── Booking lifecycle WhatsApp triggers ──────────────────

		// Booking confirmed → send booking_confirmed WhatsApp.
		add_action( 'nozule/booking/confirmed', function ( int $bookingId ) {
			$this->sendBookingWhatsApp( $bookingId, 'booking_confirmed' );
		} );

		// Guest checked in → send check_in_welcome WhatsApp.
		add_action( 'nozule/booking/checked_in', function ( int $bookingId ) {
			$this->sendBookingWhatsApp( $bookingId, 'booking_checked_in' );
		} );

		// Guest checked out → send check_out_thanks WhatsApp.
		add_action( 'nozule/booking/checked_out', function ( int $bookingId ) {
			$this->sendBookingWhatsApp( $bookingId, 'booking_checked_out' );
		} );
	}

	/**
	 * Build variables from a booking and send the trigger WhatsApp message.
	 *
	 * @param int    $bookingId    The booking ID.
	 * @param string $triggerEvent The trigger event name.
	 */
	private function sendBookingWhatsApp( int $bookingId, string $triggerEvent ): void {
		try {
			/** @var WhatsAppService $waService */
			$waService = $this->container->get( WhatsAppService::class );

			$variables = $waService->buildVariablesFromBooking( $bookingId );

			if ( empty( $variables ) || empty( $variables['guest_phone'] ) ) {
				return;
			}

			$toPhone = $variables['guest_phone'];

			// Look up the guest ID from the booking for logging.
			$db       = $this->container->get( Database::class );
			$table    = $db->table( 'bookings' );
			$guestId  = (int) $db->getVar(
				"SELECT guest_id FROM {$table} WHERE id = %d",
				$bookingId
			);

			$waService->sendByTrigger(
				$triggerEvent,
				$variables,
				$toPhone,
				$bookingId,
				$guestId ?: null
			);
		} catch ( \Throwable $e ) {
			/** @var Logger $logger */
			$logger = $this->container->get( Logger::class );
			$logger->error( "Failed to send {$triggerEvent} WhatsApp for booking {$bookingId}", [
				'error' => $e->getMessage(),
			] );
		}
	}
}
