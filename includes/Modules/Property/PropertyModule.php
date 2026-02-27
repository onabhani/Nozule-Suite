<?php

namespace Nozule\Modules\Property;

use Nozule\Core\BaseModule;
use Nozule\Core\CacheManager;
use Nozule\Core\Container;
use Nozule\Core\Database;
use Nozule\Core\EventDispatcher;
use Nozule\Core\Logger;
use Nozule\Modules\Property\Controllers\PropertyController;
use Nozule\Modules\Property\Repositories\PropertyRepository;
use Nozule\Modules\Property\Services\PropertyService;
use Nozule\Modules\Property\Validators\PropertyValidator;

/**
 * Property module bootstrap.
 *
 * Manages hotel property details — address, description, photos, facilities,
 * star rating, policies.  Designed with property_id from day one for future
 * Multi-Property support (NZL-019).
 */
class PropertyModule extends BaseModule {

	/**
	 * Register the module's services and hooks.
	 */
	public function register(): void {
		$this->registerBindings();
		$this->registerHooks();
	}

	/**
	 * Register service container bindings.
	 */
	private function registerBindings(): void {
		$this->container->singleton(
			PropertyRepository::class,
			fn() => new PropertyRepository(
				$this->container->get( Database::class )
			)
		);

		$this->container->singleton(
			PropertyValidator::class,
			fn() => new PropertyValidator(
				$this->container->get( PropertyRepository::class )
			)
		);

		$this->container->singleton(
			PropertyService::class,
			fn() => new PropertyService(
				$this->container->get( PropertyRepository::class ),
				$this->container->get( PropertyValidator::class ),
				$this->container->get( CacheManager::class ),
				$this->container->get( EventDispatcher::class ),
				$this->container->get( Logger::class )
			)
		);

		$this->container->singleton(
			PropertyController::class,
			fn() => new PropertyController(
				$this->container->get( PropertyService::class )
			)
		);
	}

	/**
	 * Register WordPress hooks for this module.
	 */
	private function registerHooks(): void {
		// Register REST routes.
		add_action( 'rest_api_init', function () {
			$this->container->get( PropertyController::class )->registerRoutes();
		} );
	}
}
