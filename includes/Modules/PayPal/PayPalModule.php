<?php

namespace Nozule\Modules\PayPal;

use Nozule\Core\BaseModule;
use Nozule\Core\Container;
use Nozule\Core\EventDispatcher;
use Nozule\Core\Logger;
use Nozule\Core\SettingsManager;
use Nozule\Modules\Bookings\Services\BookingService;
use Nozule\Modules\PayPal\Controllers\PayPalController;
use Nozule\Modules\PayPal\Services\PayPalConnector;
use Nozule\Modules\PayPal\Services\PayPalService;

/**
 * PayPal payment gateway module bootstrap (NZL-009).
 *
 * Provides PayPal integration via the Orders v2 API:
 *   - Guest-facing checkout flow (create order → redirect → capture)
 *   - Webhook receiver for async payment events
 *   - Admin settings for credentials and mode (sandbox/live)
 *
 * Opt-in — controlled by integrations.paypal_enabled setting.
 */
class PayPalModule extends BaseModule {

	public function register(): void {
		$this->registerServices();
		$this->registerControllers();
		$this->registerHooks();
	}

	private function registerServices(): void {
		$this->container->singleton( PayPalConnector::class, function ( Container $c ) {
			return new PayPalConnector(
				$c->get( SettingsManager::class ),
				$c->get( Logger::class )
			);
		} );

		$this->container->singleton( PayPalService::class, function ( Container $c ) {
			return new PayPalService(
				$c->get( PayPalConnector::class ),
				$c->get( BookingService::class ),
				$c->get( SettingsManager::class ),
				$c->get( EventDispatcher::class ),
				$c->get( Logger::class )
			);
		} );
	}

	private function registerControllers(): void {
		$this->container->singleton( PayPalController::class, function ( Container $c ) {
			return new PayPalController(
				$c->get( PayPalService::class )
			);
		} );
	}

	private function registerHooks(): void {
		add_action( 'rest_api_init', function () {
			$this->container->get( PayPalController::class )->registerRoutes();
		} );
	}
}
