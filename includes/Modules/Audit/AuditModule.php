<?php

namespace Nozule\Modules\Audit;

use Nozule\Core\BaseModule;
use Nozule\Core\Container;
use Nozule\Core\Database;
use Nozule\Core\Logger;
use Nozule\Modules\Audit\Controllers\NightAuditController;
use Nozule\Modules\Audit\Repositories\NightAuditRepository;
use Nozule\Modules\Audit\Services\NightAuditService;

/**
 * Audit module bootstrap.
 *
 * Registers the repository, service, and controller for the
 * night audit feature and hooks them into the WordPress REST API.
 */
class AuditModule extends BaseModule {

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
		// Repository.
		$this->container->singleton( NightAuditRepository::class, function ( Container $c ) {
			return new NightAuditRepository( $c->get( Database::class ) );
		} );

		// Service.
		$this->container->singleton( NightAuditService::class, function ( Container $c ) {
			return new NightAuditService(
				$c->get( NightAuditRepository::class ),
				$c->get( Database::class ),
				$c->get( Logger::class )
			);
		} );

		// Controller.
		$this->container->singleton( NightAuditController::class, function ( Container $c ) {
			return new NightAuditController(
				$c->get( NightAuditService::class )
			);
		} );
	}

	/**
	 * Register WordPress hooks.
	 */
	private function registerHooks(): void {
		// Register REST routes on rest_api_init.
		add_action( 'rest_api_init', function () {
			$this->container->get( NightAuditController::class )->registerRoutes();
		} );
	}
}
