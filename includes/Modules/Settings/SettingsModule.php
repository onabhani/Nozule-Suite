<?php

namespace Venezia\Modules\Settings;

use Venezia\Core\BaseModule;
use Venezia\Core\CacheManager;
use Venezia\Core\Container;
use Venezia\Core\SettingsManager;
use Venezia\Modules\Settings\Controllers\SettingsController;

/**
 * Settings module bootstrap.
 *
 * Registers the settings controller and its REST API endpoints
 * for both admin management and public consumption.
 */
class SettingsModule extends BaseModule {

    /**
     * Register the module's services and hooks.
     */
    public function register(): void {
        $this->registerServices();
        $this->registerHooks();
    }

    /**
     * Register services into the DI container.
     */
    private function registerServices(): void {
        $this->container->singleton( SettingsController::class, function ( Container $c ) {
            return new SettingsController(
                $c->get( SettingsManager::class ),
                $c->get( CacheManager::class )
            );
        } );
    }

    /**
     * Register WordPress hooks.
     */
    private function registerHooks(): void {
        add_action( 'rest_api_init', function () {
            $this->container->get( SettingsController::class )->registerRoutes();
        } );
    }
}
