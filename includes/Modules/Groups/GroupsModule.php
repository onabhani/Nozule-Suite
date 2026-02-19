<?php

namespace Nozule\Modules\Groups;

use Nozule\Core\BaseModule;
use Nozule\Core\Container;
use Nozule\Core\Database;
use Nozule\Core\EventDispatcher;
use Nozule\Core\Logger;
use Nozule\Core\SettingsManager;
use Nozule\Modules\Bookings\Services\BookingService;
use Nozule\Modules\Groups\Controllers\GroupBookingController;
use Nozule\Modules\Groups\Repositories\GroupBookingRepository;
use Nozule\Modules\Groups\Repositories\GroupBookingRoomRepository;
use Nozule\Modules\Groups\Services\GroupBookingService;
use Nozule\Modules\Groups\Validators\GroupBookingValidator;

/**
 * Groups module bootstrap.
 *
 * Registers all services, repositories, validators, and controllers
 * related to group bookings.
 */
class GroupsModule extends BaseModule {

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
		$this->container->singleton( GroupBookingRepository::class, function ( Container $c ) {
			return new GroupBookingRepository( $c->get( Database::class ) );
		} );

		$this->container->singleton( GroupBookingRoomRepository::class, function ( Container $c ) {
			return new GroupBookingRoomRepository( $c->get( Database::class ) );
		} );

		// Validator.
		$this->container->singleton( GroupBookingValidator::class, function () {
			return new GroupBookingValidator();
		} );

		// Core service.
		$this->container->singleton( GroupBookingService::class, function ( Container $c ) {
			return new GroupBookingService(
				$c->get( GroupBookingRepository::class ),
				$c->get( GroupBookingRoomRepository::class ),
				$c->get( GroupBookingValidator::class ),
				$c->get( BookingService::class ),
				$c->get( EventDispatcher::class ),
				$c->get( Logger::class ),
				$c->get( SettingsManager::class )
			);
		} );

		// Controller.
		$this->container->singleton( GroupBookingController::class, function ( Container $c ) {
			return new GroupBookingController(
				$c->get( GroupBookingService::class )
			);
		} );
	}

	/**
	 * Register WordPress hooks.
	 */
	private function registerHooks(): void {
		// Register REST routes on rest_api_init.
		add_action( 'rest_api_init', function () {
			$this->container->get( GroupBookingController::class )->registerRoutes();
		} );
	}
}
