<?php

namespace Nozule\Modules\Guests;

use Nozule\Core\BaseModule;
use Nozule\Core\Database;
use Nozule\Core\EventDispatcher;
use Nozule\Modules\Guests\Controllers\GuestController;
use Nozule\Modules\Guests\Repositories\GuestRepository;
use Nozule\Modules\Guests\Services\GuestService;
use Nozule\Modules\Guests\Validators\GuestValidator;

/**
 * Guests module bootstrap.
 *
 * Registers all services, repositories, and controllers
 * for guest profile management.
 */
class GuestsModule extends BaseModule {

    /**
     * Register the module's bindings, services, and hooks.
     */
    public function register(): void {
        $this->registerBindings();
        $this->registerHooks();
    }

    /**
     * Register service container bindings.
     */
    private function registerBindings(): void {
        $this->container->singleton(
            GuestRepository::class,
            fn() => new GuestRepository(
                $this->container->get( Database::class )
            )
        );

        $this->container->singleton(
            GuestValidator::class,
            fn() => new GuestValidator(
                $this->container->get( GuestRepository::class )
            )
        );

        $this->container->singleton(
            GuestService::class,
            fn() => new GuestService(
                $this->container->get( GuestRepository::class ),
                $this->container->get( GuestValidator::class ),
                $this->container->get( EventDispatcher::class )
            )
        );

        $this->container->singleton(
            GuestController::class,
            fn() => new GuestController(
                $this->container->get( GuestService::class ),
                $this->container->get( GuestRepository::class )
            )
        );
    }

    /**
     * Register WordPress hooks for this module.
     */
    private function registerHooks(): void {
        add_action( 'rest_api_init', [ $this, 'registerRestRoutes' ] );
    }

    /**
     * Callback to register REST API routes.
     */
    public function registerRestRoutes(): void {
        $controller = $this->container->get( GuestController::class );
        $controller->registerRoutes();
    }
}
