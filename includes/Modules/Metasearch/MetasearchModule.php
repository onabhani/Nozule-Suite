<?php

namespace Nozule\Modules\Metasearch;

use Nozule\Core\BaseModule;
use Nozule\Core\Container;
use Nozule\Core\SettingsManager;
use Nozule\Modules\Metasearch\Controllers\MetasearchController;
use Nozule\Modules\Metasearch\Services\GoogleHotelAdsService;
use Nozule\Modules\Pricing\Repositories\RatePlanRepository;
use Nozule\Modules\Pricing\Services\PricingService;
use Nozule\Modules\Rooms\Repositories\RoomTypeRepository;

/**
 * Metasearch Module bootstrap.
 *
 * Provides Google Hotel Ads price feed generation, Free Booking Links
 * structured data, and CPC campaign settings management.
 */
class MetasearchModule extends BaseModule {

	/**
	 * Register the module's services, controllers, and hooks.
	 */
	public function register(): void {
		$this->registerServices();
		$this->registerControllers();
		$this->registerHooks();
	}

	/**
	 * Register service singletons in the DI container.
	 */
	private function registerServices(): void {
		$this->container->singleton(
			GoogleHotelAdsService::class,
			function ( Container $c ) {
				return new GoogleHotelAdsService(
					$c->get( SettingsManager::class ),
					$c->get( PricingService::class ),
					$c->get( RoomTypeRepository::class ),
					$c->get( RatePlanRepository::class )
				);
			}
		);
	}

	/**
	 * Register REST API controller singletons.
	 */
	private function registerControllers(): void {
		$this->container->singleton(
			MetasearchController::class,
			function ( Container $c ) {
				return new MetasearchController(
					$c->get( GoogleHotelAdsService::class )
				);
			}
		);
	}

	/**
	 * Register WordPress hooks for the metasearch module.
	 */
	private function registerHooks(): void {
		// Register REST API routes.
		add_action( 'rest_api_init', function () {
			$this->container->get( MetasearchController::class )->registerRoutes();
		} );

		// Output Hotel JSON-LD structured data in the page head when enabled.
		add_action( 'wp_head', function () {
			$service = $this->container->get( GoogleHotelAdsService::class );

			if ( ! $service->isEnabled() ) {
				return;
			}

			$settings = $service->getSettings();

			if ( empty( $settings['free_booking_links_enabled'] ) ) {
				return;
			}

			$jsonLd = $service->generateJsonLd();

			if ( ! empty( $jsonLd ) ) {
				echo "\n" . $jsonLd . "\n";
			}
		} );
	}
}
