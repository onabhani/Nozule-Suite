<?php

namespace Venezia\Modules\Pricing;

use Venezia\Core\BaseModule;
use Venezia\Core\Container;
use Venezia\Core\Database;
use Venezia\Core\SettingsManager;
use Venezia\Modules\Pricing\Repositories\RatePlanRepository;
use Venezia\Modules\Pricing\Repositories\SeasonalRateRepository;
use Venezia\Modules\Pricing\Services\PricingService;
use Venezia\Modules\Pricing\Validators\RatePlanValidator;
use Venezia\Modules\Pricing\Controllers\RatePlanController;
use Venezia\Modules\Pricing\Controllers\SeasonalRateController;
use Venezia\Modules\Rooms\Repositories\InventoryRepository;
use Venezia\Modules\Rooms\Repositories\RoomTypeRepository;

class PricingModule extends BaseModule {

    public function register(): void {
        $this->container->singleton( RatePlanRepository::class, function ( Container $c ) {
            return new RatePlanRepository( $c->get( Database::class ) );
        } );

        $this->container->singleton( SeasonalRateRepository::class, function ( Container $c ) {
            return new SeasonalRateRepository( $c->get( Database::class ) );
        } );

        $this->container->singleton( PricingService::class, function ( Container $c ) {
            return new PricingService(
                $c->get( RatePlanRepository::class ),
                $c->get( SeasonalRateRepository::class ),
                $c->get( InventoryRepository::class ),
                $c->get( RoomTypeRepository::class ),
                $c->get( SettingsManager::class )
            );
        } );

        $this->container->bind( RatePlanValidator::class );

        $this->container->singleton( RatePlanController::class, function ( Container $c ) {
            return new RatePlanController(
                $c->get( RatePlanRepository::class ),
                $c->get( RatePlanValidator::class )
            );
        } );

        $this->container->singleton( SeasonalRateController::class, function ( Container $c ) {
            return new SeasonalRateController(
                $c->get( SeasonalRateRepository::class )
            );
        } );
    }
}
