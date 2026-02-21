<?php

namespace Nozule\Modules\POS;

use Nozule\Core\BaseModule;
use Nozule\Core\Container;
use Nozule\Core\Database;
use Nozule\Core\EventDispatcher;
use Nozule\Core\Logger;
use Nozule\Modules\Billing\Repositories\FolioItemRepository;
use Nozule\Modules\Billing\Repositories\FolioRepository;
use Nozule\Modules\POS\Controllers\POSController;
use Nozule\Modules\POS\Repositories\POSRepository;
use Nozule\Modules\POS\Services\POSService;

/**
 * POS module bootstrap.
 *
 * Registers the repository, service, and controller singletons
 * for the Point-of-Sale functionality (outlets, items, orders,
 * and folio posting).
 */
class POSModule extends BaseModule {

	/**
	 * Register the module's services and hooks.
	 */
	public function register(): void {
		$this->registerRepositories();
		$this->registerServices();
		$this->registerControllers();
		$this->registerHooks();
	}

	/**
	 * Register repository singletons in the container.
	 */
	private function registerRepositories(): void {
		$this->container->singleton( POSRepository::class, function ( Container $c ) {
			return new POSRepository(
				$c->get( Database::class )
			);
		} );
	}

	/**
	 * Register service singletons in the container.
	 */
	private function registerServices(): void {
		$this->container->singleton( POSService::class, function ( Container $c ) {
			return new POSService(
				$c->get( POSRepository::class ),
				$c->get( Database::class ),
				$c->get( FolioRepository::class ),
				$c->get( FolioItemRepository::class ),
				$c->get( EventDispatcher::class ),
				$c->get( Logger::class )
			);
		} );
	}

	/**
	 * Register REST API controllers.
	 */
	private function registerControllers(): void {
		$this->container->singleton( POSController::class, function ( Container $c ) {
			return new POSController(
				$c->get( POSService::class )
			);
		} );
	}

	/**
	 * Register WordPress hooks for this module.
	 */
	private function registerHooks(): void {
		// Register REST routes on rest_api_init.
		add_action( 'rest_api_init', function () {
			$this->container->get( POSController::class )->registerRoutes();
		} );
	}
}
