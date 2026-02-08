<?php

namespace Venezia\Modules\Bookings;

use Venezia\Core\BaseModule;
use Venezia\Core\Container;
use Venezia\Core\Database;
use Venezia\Core\SettingsManager;
use Venezia\Modules\Bookings\Controllers\AdminBookingController;
use Venezia\Modules\Bookings\Controllers\BookingController;
use Venezia\Modules\Bookings\Controllers\CalendarController;
use Venezia\Modules\Bookings\Controllers\DashboardController;
use Venezia\Modules\Bookings\Repositories\BookingRepository;
use Venezia\Modules\Bookings\Repositories\PaymentRepository;
use Venezia\Modules\Bookings\Services\BookingService;
use Venezia\Modules\Bookings\Validators\BookingValidator;
use Venezia\Modules\Guests\Services\GuestService;
use Venezia\Modules\Notifications\Services\NotificationService;
use Venezia\Modules\Pricing\Services\PricingService;
use Venezia\Modules\Rooms\Repositories\RoomRepository;
use Venezia\Modules\Rooms\Services\AvailabilityService;

/**
 * Bookings module bootstrap.
 *
 * Registers all services, repositories, validators, and controllers
 * related to the booking lifecycle.
 */
class BookingsModule extends BaseModule {

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
		$this->container->singleton( BookingRepository::class, function ( Container $c ) {
			return new BookingRepository( $c->get( Database::class ) );
		} );

		$this->container->singleton( PaymentRepository::class, function ( Container $c ) {
			return new PaymentRepository( $c->get( Database::class ) );
		} );

		// Validator.
		$this->container->singleton( BookingValidator::class, function () {
			return new BookingValidator();
		} );

		// Core service.
		$this->container->singleton( BookingService::class, function ( Container $c ) {
			return new BookingService(
				$c->get( BookingRepository::class ),
				$c->get( PaymentRepository::class ),
				$c->get( BookingValidator::class ),
				$c->get( GuestService::class ),
				$c->get( AvailabilityService::class ),
				$c->get( PricingService::class ),
				$c->get( NotificationService::class ),
				$c->get( SettingsManager::class )
			);
		} );

		// Controllers.
		$this->container->singleton( BookingController::class, function ( Container $c ) {
			return new BookingController(
				$c->get( BookingService::class ),
				$c->get( BookingRepository::class )
			);
		} );

		$this->container->singleton( AdminBookingController::class, function ( Container $c ) {
			return new AdminBookingController(
				$c->get( BookingService::class ),
				$c->get( BookingRepository::class ),
				$c->get( PaymentRepository::class ),
				$c->get( BookingValidator::class )
			);
		} );

		$this->container->singleton( DashboardController::class, function ( Container $c ) {
			return new DashboardController(
				$c->get( BookingService::class ),
				$c->get( BookingRepository::class ),
				$c->get( RoomRepository::class )
			);
		} );

		$this->container->singleton( CalendarController::class, function ( Container $c ) {
			return new CalendarController(
				$c->get( BookingRepository::class )
			);
		} );
	}

	/**
	 * Register WordPress hooks.
	 */
	private function registerHooks(): void {
		// Register REST routes on rest_api_init.
		add_action( 'rest_api_init', function () {
			$this->container->get( BookingController::class )->registerRoutes();
			$this->container->get( AdminBookingController::class )->registerRoutes();
			$this->container->get( DashboardController::class )->registerRoutes();
			$this->container->get( CalendarController::class )->registerRoutes();
		} );

		// Expose container via filter for cross-module resolution.
		add_filter( 'venezia/container/get', function ( $default, string $abstract ) {
			if ( $this->container->has( $abstract ) ) {
				return $this->container->get( $abstract );
			}
			return $default;
		}, 10, 2 );
	}
}
