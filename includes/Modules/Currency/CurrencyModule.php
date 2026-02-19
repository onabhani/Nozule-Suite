<?php

namespace Nozule\Modules\Currency;

use Nozule\Core\BaseModule;
use Nozule\Core\Database;
use Nozule\Core\EventDispatcher;
use Nozule\Core\Logger;
use Nozule\Modules\Currency\Controllers\CurrencyController;
use Nozule\Modules\Currency\Repositories\CurrencyRepository;
use Nozule\Modules\Currency\Repositories\ExchangeRateRepository;
use Nozule\Modules\Currency\Services\CurrencyService;
use Nozule\Modules\Currency\Validators\CurrencyValidator;

/**
 * Currency module bootstrap.
 *
 * Registers all services, repositories, and controllers
 * for multi-currency support and Syrian/non-Syrian pricing.
 */
class CurrencyModule extends BaseModule {

	/**
	 * Register the module's bindings, services, and hooks.
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
			CurrencyRepository::class,
			fn() => new CurrencyRepository(
				$this->container->get( Database::class )
			)
		);

		$this->container->singleton(
			ExchangeRateRepository::class,
			fn() => new ExchangeRateRepository(
				$this->container->get( Database::class )
			)
		);

		$this->container->singleton(
			CurrencyValidator::class,
			fn() => new CurrencyValidator(
				$this->container->get( CurrencyRepository::class )
			)
		);

		$this->container->singleton(
			CurrencyService::class,
			fn() => new CurrencyService(
				$this->container->get( CurrencyRepository::class ),
				$this->container->get( ExchangeRateRepository::class ),
				$this->container->get( CurrencyValidator::class ),
				$this->container->get( EventDispatcher::class ),
				$this->container->get( Logger::class )
			)
		);

		$this->container->singleton(
			CurrencyController::class,
			fn() => new CurrencyController(
				$this->container->get( CurrencyService::class )
			)
		);
	}

	/**
	 * Register WordPress hooks for this module.
	 */
	private function registerHooks(): void {
		add_action( 'rest_api_init', [ $this, 'registerRestRoutes' ] );
	}

	/**
	 * Callback to register REST API routes.
	 */
	public function registerRestRoutes(): void {
		$controller = $this->container->get( CurrencyController::class );
		$controller->registerRoutes();
	}
}
