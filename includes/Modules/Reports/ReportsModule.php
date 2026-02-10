<?php

namespace Venezia\Modules\Reports;

use Venezia\Core\BaseModule;
use Venezia\Core\CacheManager;
use Venezia\Core\Container;
use Venezia\Core\Database;
use Venezia\Modules\Reports\Controllers\ReportController;
use Venezia\Modules\Reports\Services\ExportService;
use Venezia\Modules\Reports\Services\ReportService;

/**
 * Reports module bootstrap.
 *
 * Registers report services and REST API endpoints for
 * occupancy, revenue, source, guest, forecast, cancellation
 * reports and data export.
 */
class ReportsModule extends BaseModule {

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
        $this->container->singleton( ReportService::class, function ( Container $c ) {
            return new ReportService(
                $c->get( Database::class ),
                $c->get( CacheManager::class )
            );
        } );

        $this->container->singleton( ExportService::class, function () {
            return new ExportService();
        } );

        $this->container->singleton( ReportController::class, function ( Container $c ) {
            return new ReportController(
                $c->get( ReportService::class ),
                $c->get( ExportService::class )
            );
        } );
    }

    /**
     * Register WordPress hooks.
     */
    private function registerHooks(): void {
        add_action( 'rest_api_init', function () {
            $this->container->get( ReportController::class )->registerRoutes();
        } );
    }
}
