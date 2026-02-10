<?php

namespace Venezia\Modules\Rooms;

use Venezia\Core\BaseModule;
use Venezia\Core\CacheManager;
use Venezia\Core\Container;
use Venezia\Core\Database;
use Venezia\Core\EventDispatcher;
use Venezia\Core\Logger;
use Venezia\Modules\Rooms\Controllers\AvailabilityController;
use Venezia\Modules\Rooms\Controllers\InventoryController;
use Venezia\Modules\Rooms\Controllers\RoomController;
use Venezia\Modules\Rooms\Controllers\RoomTypeController;
use Venezia\Modules\Rooms\Repositories\InventoryRepository;
use Venezia\Modules\Rooms\Repositories\RoomRepository;
use Venezia\Modules\Rooms\Repositories\RoomTypeRepository;
use Venezia\Modules\Rooms\Services\AvailabilityService;
use Venezia\Modules\Rooms\Services\RoomService;
use Venezia\Modules\Rooms\Validators\RoomTypeValidator;
use Venezia\Modules\Rooms\Validators\RoomValidator;

/**
 * Rooms module bootstrap.
 *
 * Registers all repositories, validators, services, and controllers
 * for the room management functionality.
 */
class RoomsModule extends BaseModule {

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
		$this->container->singleton( RoomTypeRepository::class, function ( Container $c ) {
			return new RoomTypeRepository(
				$c->get( Database::class )
			);
		} );

		$this->container->singleton( RoomRepository::class, function ( Container $c ) {
			return new RoomRepository(
				$c->get( Database::class )
			);
		} );

		$this->container->singleton( InventoryRepository::class, function ( Container $c ) {
			return new InventoryRepository(
				$c->get( Database::class )
			);
		} );
	}

	/**
	 * Register validator singletons in the container.
	 */
	private function registerValidators(): void {
		$this->container->singleton( RoomTypeValidator::class, function ( Container $c ) {
			return new RoomTypeValidator(
				$c->get( RoomTypeRepository::class )
			);
		} );

		$this->container->singleton( RoomValidator::class, function ( Container $c ) {
			return new RoomValidator(
				$c->get( RoomRepository::class ),
				$c->get( RoomTypeRepository::class )
			);
		} );
	}

	/**
	 * Register service singletons in the container.
	 */
	private function registerServices(): void {
		$this->container->singleton( RoomService::class, function ( Container $c ) {
			return new RoomService(
				$c->get( RoomTypeRepository::class ),
				$c->get( RoomRepository::class ),
				$c->get( InventoryRepository::class ),
				$c->get( RoomTypeValidator::class ),
				$c->get( RoomValidator::class ),
				$c->get( CacheManager::class ),
				$c->get( EventDispatcher::class ),
				$c->get( Logger::class )
			);
		} );

		$this->container->singleton( AvailabilityService::class, function ( Container $c ) {
			return new AvailabilityService(
				$c->get( InventoryRepository::class ),
				$c->get( RoomTypeRepository::class ),
				$c->get( CacheManager::class ),
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
		$this->container->singleton( RoomTypeController::class, function ( Container $c ) {
			return new RoomTypeController(
				$c->get( RoomService::class )
			);
		} );

		$this->container->singleton( RoomController::class, function ( Container $c ) {
			return new RoomController(
				$c->get( RoomService::class )
			);
		} );

		$this->container->singleton( InventoryController::class, function ( Container $c ) {
			return new InventoryController(
				$c->get( InventoryRepository::class ),
				$c->get( RoomService::class )
			);
		} );

		$this->container->singleton( AvailabilityController::class, function ( Container $c ) {
			return new AvailabilityController(
				$c->get( AvailabilityService::class )
			);
		} );
	}

	/**
	 * Register WordPress hooks for this module.
	 */
	private function registerHooks(): void {
		// Register REST routes on rest_api_init.
		add_action( 'rest_api_init', function () {
			$this->container->get( RoomTypeController::class )->registerRoutes();
			$this->container->get( RoomController::class )->registerRoutes();
			$this->container->get( InventoryController::class )->registerRoutes();
			$this->container->get( AvailabilityController::class )->registerRoutes();
		} );

		// Listen for booking events to manage inventory.
		$events = $this->container->get( EventDispatcher::class );

		$events->listen( 'bookings/booking_confirmed', function ( $booking ) {
			$availabilityService = $this->container->get( AvailabilityService::class );
			$availabilityService->deductInventory(
				$booking->room_type_id,
				$booking->check_in,
				$booking->check_out
			);
		} );

		$events->listen( 'bookings/booking_cancelled', function ( $booking ) {
			$availabilityService = $this->container->get( AvailabilityService::class );
			$availabilityService->restoreInventory(
				$booking->room_type_id,
				$booking->check_in,
				$booking->check_out
			);
		} );
	}
}
