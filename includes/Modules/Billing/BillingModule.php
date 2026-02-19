<?php

namespace Nozule\Modules\Billing;

use Nozule\Core\BaseModule;
use Nozule\Core\CacheManager;
use Nozule\Core\Container;
use Nozule\Core\Database;
use Nozule\Core\EventDispatcher;
use Nozule\Core\Logger;
use Nozule\Core\SettingsManager;
use Nozule\Modules\Billing\Controllers\FolioController;
use Nozule\Modules\Billing\Controllers\TaxController;
use Nozule\Modules\Billing\Repositories\FolioItemRepository;
use Nozule\Modules\Billing\Repositories\FolioRepository;
use Nozule\Modules\Billing\Repositories\TaxRepository;
use Nozule\Modules\Billing\Services\FolioService;
use Nozule\Modules\Billing\Services\TaxService;
use Nozule\Modules\Billing\Validators\FolioValidator;
use Nozule\Modules\Billing\Validators\TaxValidator;

/**
 * Billing module bootstrap.
 *
 * Registers all repositories, validators, services, and controllers
 * for the billing functionality (taxes, folios, folio items).
 */
class BillingModule extends BaseModule {

	/**
	 * Register the module's services and hooks.
	 */
	public function register(): void {
		$this->registerRepositories();
		$this->registerValidators();
		$this->registerServices();
		$this->registerControllers();
		$this->registerHooks();
	}

	/**
	 * Register repository singletons in the container.
	 */
	private function registerRepositories(): void {
		$this->container->singleton( TaxRepository::class, function ( Container $c ) {
			return new TaxRepository(
				$c->get( Database::class )
			);
		} );

		$this->container->singleton( FolioRepository::class, function ( Container $c ) {
			return new FolioRepository(
				$c->get( Database::class )
			);
		} );

		$this->container->singleton( FolioItemRepository::class, function ( Container $c ) {
			return new FolioItemRepository(
				$c->get( Database::class )
			);
		} );
	}

	/**
	 * Register validator singletons in the container.
	 */
	private function registerValidators(): void {
		$this->container->singleton( TaxValidator::class, function () {
			return new TaxValidator();
		} );

		$this->container->singleton( FolioValidator::class, function () {
			return new FolioValidator();
		} );
	}

	/**
	 * Register service singletons in the container.
	 */
	private function registerServices(): void {
		$this->container->singleton( TaxService::class, function ( Container $c ) {
			return new TaxService(
				$c->get( TaxRepository::class ),
				$c->get( TaxValidator::class ),
				$c->get( CacheManager::class ),
				$c->get( Logger::class )
			);
		} );

		$this->container->singleton( FolioService::class, function ( Container $c ) {
			return new FolioService(
				$c->get( FolioRepository::class ),
				$c->get( FolioItemRepository::class ),
				$c->get( TaxService::class ),
				$c->get( FolioValidator::class ),
				$c->get( SettingsManager::class ),
				$c->get( EventDispatcher::class ),
				$c->get( Logger::class )
			);
		} );
	}

	/**
	 * Register REST API controllers.
	 *
	 * Controllers are instantiated and their routes registered during the
	 * rest_api_init action.
	 */
	private function registerControllers(): void {
		$this->container->singleton( TaxController::class, function ( Container $c ) {
			return new TaxController(
				$c->get( TaxService::class )
			);
		} );

		$this->container->singleton( FolioController::class, function ( Container $c ) {
			return new FolioController(
				$c->get( FolioService::class )
			);
		} );
	}

	/**
	 * Register WordPress hooks for this module.
	 */
	private function registerHooks(): void {
		// Register REST routes on rest_api_init.
		add_action( 'rest_api_init', function () {
			$this->container->get( TaxController::class )->registerRoutes();
			$this->container->get( FolioController::class )->registerRoutes();
		} );

		// Auto-create a folio when a guest checks in.
		// BookingService fires do_action('nozule/booking/checked_in', $bookingId, $roomId).
		add_action( 'nozule/booking/checked_in', function ( int $bookingId ) {
			$this->onBookingCheckedIn( $bookingId );
		} );
	}

	/**
	 * Handle booking check-in event.
	 *
	 * When a guest checks in, automatically create an open folio for the
	 * booking so charges can be posted during their stay.
	 */
	private function onBookingCheckedIn( int $bookingId ): void {
		try {
			// Check if a folio already exists for this booking.
			$folioService = $this->container->get( FolioService::class );
			$existing     = $folioService->getFolioByBooking( $bookingId );

			if ( $existing ) {
				return; // Folio already exists, nothing to do.
			}

			// Look up the booking to get guest_id.
			$db      = $this->container->get( Database::class );
			$table   = $db->table( 'bookings' );
			$booking = $db->getRow(
				"SELECT guest_id FROM {$table} WHERE id = %d",
				$bookingId
			);

			if ( ! $booking || ! $booking->guest_id ) {
				return;
			}

			$folioService->createFolioForBooking( $bookingId, (int) $booking->guest_id );
		} catch ( \Throwable $e ) {
			$logger = $this->container->get( Logger::class );
			$logger->error( 'Failed to auto-create folio on check-in', [
				'booking_id' => $bookingId,
				'error'      => $e->getMessage(),
			] );
		}
	}
}
