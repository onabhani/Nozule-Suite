<?php

namespace Nozule\Modules\Forecasting;

use Nozule\Core\BaseModule;
use Nozule\Core\Container;
use Nozule\Core\Database;
use Nozule\Modules\Forecasting\Controllers\ForecastController;
use Nozule\Modules\Forecasting\Repositories\ForecastRepository;
use Nozule\Modules\Forecasting\Services\ForecastService;

/**
 * Forecasting module bootstrap.
 *
 * Registers all services, repositories, and controllers for the
 * AI demand forecasting feature. Schedules a daily WP-Cron event
 * to regenerate forecasts automatically.
 */
class ForecastingModule extends BaseModule {

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
		$this->container->singleton( ForecastRepository::class, function ( Container $c ) {
			return new ForecastRepository( $c->get( Database::class ) );
		} );

		// Service.
		$this->container->singleton( ForecastService::class, function ( Container $c ) {
			return new ForecastService(
				$c->get( Database::class ),
				$c->get( ForecastRepository::class )
			);
		} );

		// Controller.
		$this->container->singleton( ForecastController::class, function ( Container $c ) {
			return new ForecastController(
				$c->get( ForecastService::class ),
				$c->get( ForecastRepository::class ),
				$c->get( Database::class )
			);
		} );
	}

	/**
	 * Register WordPress hooks.
	 */
	private function registerHooks(): void {
		// Register REST routes on rest_api_init.
		add_action( 'rest_api_init', function () {
			$this->container->get( ForecastController::class )->registerRoutes();
		} );

		// Schedule daily forecast generation.
		add_action( 'init', function () {
			if ( ! wp_next_scheduled( 'nzl_generate_forecasts' ) ) {
				wp_schedule_event( time(), 'daily', 'nzl_generate_forecasts' );
			}
		} );

		// Handle the cron event.
		add_action( 'nzl_generate_forecasts', function () {
			try {
				$service = $this->container->get( ForecastService::class );
				$service->generateForecasts();
			} catch ( \Throwable $e ) {
				error_log( 'Nozule: Scheduled forecast generation failed — ' . $e->getMessage() );
			}
		} );
	}
}
