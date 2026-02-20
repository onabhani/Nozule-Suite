<?php

namespace Nozule\Modules\Pricing;

use Nozule\Core\BaseModule;
use Nozule\Core\CacheManager;
use Nozule\Core\Container;
use Nozule\Core\Database;
use Nozule\Core\EventDispatcher;
use Nozule\Modules\Pricing\Controllers\DynamicPricingController;
use Nozule\Modules\Pricing\Repositories\DynamicPricingRepository;
use Nozule\Modules\Pricing\Services\DynamicPricingService;

/**
 * Dynamic Pricing Module bootstrap.
 *
 * Registers the dynamic pricing repository, service, and controller,
 * and hooks into the existing PricingService via the pricing/nightly_rate
 * filter to apply dynamic adjustments automatically.
 */
class DynamicPricingModule extends BaseModule {

	/**
	 * Register the module's services, repositories, and hooks.
	 */
	public function register(): void {
		$this->registerRepositories();
		$this->registerServices();
		$this->registerControllers();
		$this->registerHooks();
	}

	/**
	 * Register repository singletons.
	 */
	private function registerRepositories(): void {
		$this->container->singleton( DynamicPricingRepository::class, function ( Container $c ) {
			return new DynamicPricingRepository( $c->get( Database::class ) );
		} );
	}

	/**
	 * Register service singletons.
	 */
	private function registerServices(): void {
		$this->container->singleton( DynamicPricingService::class, function ( Container $c ) {
			return new DynamicPricingService(
				$c->get( DynamicPricingRepository::class )
			);
		} );
	}

	/**
	 * Register REST API controller singletons.
	 */
	private function registerControllers(): void {
		$this->container->singleton( DynamicPricingController::class, function ( Container $c ) {
			return new DynamicPricingController(
				$c->get( DynamicPricingRepository::class ),
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
			$this->container->get( DynamicPricingController::class )->registerRoutes();
		} );

		// Hook into the existing pricing pipeline.
		// The PricingService fires 'pricing/nightly_rate' after calculating a
		// nightly rate. We apply dynamic modifiers on top of that.
		$events = $this->container->get( EventDispatcher::class );

		$events->addFilter( 'pricing/nightly_rate', function ( $price, $roomTypeId, $ratePlan, $date ) {
			$service = $this->container->get( DynamicPricingService::class );
			return $service->applyDynamicPricing( (float) $price, (int) $roomTypeId, (string) $date );
		}, 20 );

		// Invalidate pricing cache when dynamic rules change.
		$cache = $this->container->get( CacheManager::class );

		$invalidateCache = function () use ( $cache ) {
			$cache->invalidateTag( 'pricing' );
		};

		$events->listen( 'pricing/dynamic_rule_created', $invalidateCache );
		$events->listen( 'pricing/dynamic_rule_updated', $invalidateCache );
		$events->listen( 'pricing/dynamic_rule_deleted', $invalidateCache );
	}
}
