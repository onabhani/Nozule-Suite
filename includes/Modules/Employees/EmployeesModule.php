<?php

namespace Nozule\Modules\Employees;

use Nozule\Core\BaseModule;
use Nozule\Modules\Employees\Controllers\EmployeeController;

/**
 * Employee management module (NZL-042).
 *
 * Allows hotel managers to create, edit, deactivate staff users
 * and assign capabilities — all without leaving the Nozule admin.
 */
class EmployeesModule extends BaseModule {

    public function register(): void {
        $this->container->singleton(
            EmployeeController::class,
            fn() => new EmployeeController()
        );

        add_action( 'rest_api_init', [ $this, 'registerRestRoutes' ] );
    }

    public function registerRestRoutes(): void {
        $this->container->get( EmployeeController::class )->registerRoutes();
    }
}
