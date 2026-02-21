<?php

namespace Nozule\Modules\RateShopping;

use Nozule\Core\BaseModule;
use Nozule\Core\Container;
use Nozule\Core\Database;
use Nozule\Core\Logger;
use Nozule\Core\SettingsManager;
use Nozule\Modules\Pricing\Services\PricingService;
use Nozule\Modules\RateShopping\Controllers\RateShopController;
use Nozule\Modules\RateShopping\Repositories\RateShopRepository;
use Nozule\Modules\RateShopping\Services\RateShopService;

/**
 * Rate Shopping module bootstrap (NZL-039).
 *
 * Competitive rate shopping: monitor competitor pricing on OTAs,
 * detect parity violations, and generate alerts.
 *
 * Registers repository, service, and controller bindings.
 * Hooks into rest_api_init for routes and schedules a WP-Cron
 * event for automated parity checks.
 */
class RateShoppingModule extends BaseModule {

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
		$this->container->singleton( RateShopRepository::class, function ( Container $c ) {
			return new RateShopRepository( $c->get( Database::class ) );
		} );

		// Core service.
		$this->container->singleton( RateShopService::class, function ( Container $c ) {
			return new RateShopService(
				$c->get( RateShopRepository::class ),
				$c->get( PricingService::class ),
				$c->get( SettingsManager::class ),
				$c->get( Logger::class )
			);
		} );

		// Controller.
		$this->container->singleton( RateShopController::class, function ( Container $c ) {
			return new RateShopController(
				$c->get( RateShopService::class )
			);
		} );
	}

	/**
	 * Register WordPress hooks.
	 */
	private function registerHooks(): void {
		// Register REST API routes on rest_api_init.
		add_action( 'rest_api_init', function () {
			$this->container->get( RateShopController::class )->registerRoutes();
		} );

		// ── WP-Cron: automated parity check twice daily ──────────────
		add_action( 'nzl_rate_shop_check', function () {
			$this->runScheduledParityCheck();
		} );

		// Schedule the cron event if not already scheduled.
		add_action( 'init', function () {
			if ( ! wp_next_scheduled( 'nzl_rate_shop_check' ) ) {
				wp_schedule_event( time(), 'twicedaily', 'nzl_rate_shop_check' );
			}
		} );
	}

	/**
	 * Scheduled parity check callback.
	 *
	 * Iterates through active competitors and re-checks parity
	 * for all results captured within the last 24 hours.
	 */
	private function runScheduledParityCheck(): void {
		try {
			/** @var RateShopService $service */
			$service = $this->container->get( RateShopService::class );

			/** @var RateShopRepository $repository */
			$repository = $this->container->get( RateShopRepository::class );

			$competitors = $repository->getActiveCompetitors();

			foreach ( $competitors as $competitor ) {
				// Get latest results for this competitor.
				$results = $repository->getLatestResults( $competitor->id, 14 );

				foreach ( $results as $result ) {
					$service->checkParity( $competitor->id, $result->check_date );
				}
			}
		} catch ( \Throwable $e ) {
			/** @var Logger $logger */
			$logger = $this->container->get( Logger::class );
			$logger->error( 'Scheduled rate shop parity check failed', [
				'error' => $e->getMessage(),
			] );
		}
	}
}
