<?php

namespace Nozule\Modules\Integrations;

use Nozule\Core\BaseModule;
use Nozule\Core\Container;
use Nozule\Core\SettingsManager;
use Nozule\Modules\Integrations\Controllers\IntegrationController;
use Nozule\Modules\Integrations\Services\IntegrationService;
use Nozule\Modules\Integrations\Services\OdooConnector;
use Nozule\Modules\Integrations\Services\WebhookConnector;

/**
 * Integrations module bootstrap.
 *
 * Provides ERP/CRM integration connectors (Odoo, generic webhooks)
 * that react to booking, guest, and payment events.
 */
class IntegrationsModule extends BaseModule {

	public function register(): void {
		$this->registerServices();
		$this->registerHooks();
	}

	private function registerServices(): void {
		$this->container->singleton( OdooConnector::class, function ( Container $c ) {
			return new OdooConnector( $c->get( SettingsManager::class ) );
		} );

		$this->container->singleton( WebhookConnector::class, function ( Container $c ) {
			return new WebhookConnector( $c->get( SettingsManager::class ) );
		} );

		$this->container->singleton( IntegrationService::class, function ( Container $c ) {
			return new IntegrationService(
				$c->get( SettingsManager::class ),
				$c->get( OdooConnector::class ),
				$c->get( WebhookConnector::class )
			);
		} );

		$this->container->singleton( IntegrationController::class, function ( Container $c ) {
			return new IntegrationController(
				$c->get( IntegrationService::class ),
				$c->get( OdooConnector::class ),
				$c->get( WebhookConnector::class )
			);
		} );
	}

	private function registerHooks(): void {
		// Register REST routes.
		add_action( 'rest_api_init', function () {
			$this->container->get( IntegrationController::class )->registerRoutes();
		} );

		// Hook into plugin events after boot.
		add_action( 'nozule/booted', function () {
			$this->container->get( IntegrationService::class )->subscribeToEvents();
		} );
	}
}
