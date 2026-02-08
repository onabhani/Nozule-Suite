<?php

namespace Venezia\Modules\Pricing;

use Venezia\Core\BaseModule;
use Venezia\Core\CacheManager;
use Venezia\Core\Container;
use Venezia\Core\Database;
use Venezia\Core\EventDispatcher;
use Venezia\Core\SettingsManager;
use Venezia\Modules\Pricing\Controllers\RatePlanController;
use Venezia\Modules\Pricing\Controllers\SeasonalRateController;
use Venezia\Modules\Pricing\Repositories\RatePlanRepository;
use Venezia\Modules\Pricing\Repositories\SeasonalRateRepository;
use Venezia\Modules\Pricing\Services\PricingService;
use Venezia\Modules\Pricing\Validators\RatePlanValidator;
use Venezia\Modules\Pricing\Validators\SeasonalRateValidator;
use Venezia\Modules\Rooms\Repositories\InventoryRepository;
use Venezia\Modules\Rooms\Repositories\RoomTypeRepository;

/**
 * Pricing Module bootstrap.
 *
 * Registers all pricing-related services, repositories, validators,
 * and REST API controllers with the dependency injection container.
 */
class PricingModule extends BaseModule {

	/**
	 * Register the module's services, repositories, and hooks.
	 */
	public function register(): void {
		$this->registerRepositories();
		$this->registerValidators();
		$this->registerServices();
		$this->registerControllers();
		$this->registerHooks();
	}

	/**
	 * Register repository singletons.
	 */
	private function registerRepositories(): void {
		$this->container->singleton( RatePlanRepository::class, function ( Container $c ) {
			return new RatePlanRepository( $c->get( Database::class ) );
		} );

		$this->container->singleton( SeasonalRateRepository::class, function ( Container $c ) {
			return new SeasonalRateRepository( $c->get( Database::class ) );
		} );
	}

	/**
	 * Register validator bindings.
	 */
	private function registerValidators(): void {
		$this->container->bind( RatePlanValidator::class, function ( Container $c ) {
			return new RatePlanValidator( $c->get( RatePlanRepository::class ) );
		} );

		$this->container->bind( SeasonalRateValidator::class, function () {
			return new SeasonalRateValidator();
		} );
	}

	/**
	 * Register service singletons.
	 */
	private function registerServices(): void {
		$this->container->singleton( PricingService::class, function ( Container $c ) {
			return new PricingService(
				$c->get( RatePlanRepository::class ),
				$c->get( SeasonalRateRepository::class ),
				$c->get( InventoryRepository::class ),
				$c->get( RoomTypeRepository::class ),
				$c->get( SettingsManager::class ),
				$c->get( CacheManager::class ),
				$c->get( EventDispatcher::class )
			);
		} );
	}

	/**
	 * Register REST API controller singletons.
	 */
	private function registerControllers(): void {
		$this->container->singleton( RatePlanController::class, function ( Container $c ) {
			return new RatePlanController(
				$c->get( RatePlanRepository::class ),
				$c->get( RatePlanValidator::class ),
				$c->get( EventDispatcher::class )
			);
		} );

		$this->container->singleton( SeasonalRateController::class, function ( Container $c ) {
			return new SeasonalRateController(
				$c->get( SeasonalRateRepository::class ),
				$c->get( SeasonalRateValidator::class ),
				$c->get( EventDispatcher::class )
			);
		} );
	}

	/**
	 * Register WordPress hooks.
	 */
	private function registerHooks(): void {
		// Register REST API routes on rest_api_init.
		add_action( 'rest_api_init', function () {
			$this->container->get( RatePlanController::class )->registerRoutes();
			$this->container->get( SeasonalRateController::class )->registerRoutes();
		} );

		// Invalidate pricing cache when rate plans or seasonal rates change.
		$events = $this->container->get( EventDispatcher::class );
		$cache  = $this->container->get( CacheManager::class );

		$invalidateCache = function () use ( $cache ) {
			$cache->invalidateTag( 'pricing' );
		};

		$events->listen( 'pricing/rate_plan_created', $invalidateCache );
		$events->listen( 'pricing/rate_plan_updated', $invalidateCache );
		$events->listen( 'pricing/rate_plan_deleted', $invalidateCache );
		$events->listen( 'pricing/seasonal_rate_created', $invalidateCache );
		$events->listen( 'pricing/seasonal_rate_updated', $invalidateCache );
		$events->listen( 'pricing/seasonal_rate_deleted', $invalidateCache );
	}
}
