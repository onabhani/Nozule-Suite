<?php

namespace Nozule\Modules\Maintenance;

use Nozule\Core\BaseModule;
use Nozule\Core\Container;
use Nozule\Core\Database;
use Nozule\Core\EventDispatcher;
use Nozule\Core\Logger;
use Nozule\Modules\Maintenance\Controllers\WorkOrderController;
use Nozule\Modules\Maintenance\Repositories\WorkOrderRepository;
use Nozule\Modules\Maintenance\Services\MaintenanceService;
use Nozule\Modules\Maintenance\Validators\WorkOrderValidator;
use Nozule\Modules\Rooms\Repositories\RoomRepository;

/**
 * Maintenance module bootstrap.
 *
 * Registers all repositories, validators, services, and controllers
 * for the maintenance work order functionality (NZL-011).
 */
class MaintenanceModule extends BaseModule {

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

	private function registerRepositories(): void {
		$this->container->singleton( WorkOrderRepository::class, function ( Container $c ) {
			return new WorkOrderRepository(
				$c->get( Database::class )
			);
		} );
	}

	private function registerValidators(): void {
		$this->container->singleton( WorkOrderValidator::class, function ( Container $c ) {
			return new WorkOrderValidator(
				$c->get( WorkOrderRepository::class )
			);
		} );
	}

	private function registerServices(): void {
		$this->container->singleton( MaintenanceService::class, function ( Container $c ) {
			return new MaintenanceService(
				$c->get( WorkOrderRepository::class ),
				$c->get( WorkOrderValidator::class ),
				$c->get( RoomRepository::class ),
				$c->get( EventDispatcher::class ),
				$c->get( Logger::class )
			);
		} );
	}

	private function registerControllers(): void {
		$this->container->singleton( WorkOrderController::class, function ( Container $c ) {
			return new WorkOrderController(
				$c->get( MaintenanceService::class )
			);
		} );
	}

	private function registerHooks(): void {
		add_action( 'rest_api_init', function () {
			$this->container->get( WorkOrderController::class )->registerRoutes();
		} );
	}
}
