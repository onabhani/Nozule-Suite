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

        // Ensure hotel roles exist. They are normally created during plugin
        // activation, but may be missing if the plugin was updated via file
        // upload without the activation hook firing.
        add_action( 'admin_init', [ $this, 'ensureRolesExist' ] );
    }

    /**
     * Re-register hotel staff roles if they are missing from WordPress,
     * and reconcile capabilities for existing roles so that upgrades
     * without re-activation still get the correct capability set.
     */
    public function ensureRolesExist(): void {
        $canonical_caps_by_role = [
            'nzl_manager' => [
                'label' => 'Hotel Manager',
                'caps'  => [
                    'read'                    => true,
                    'upload_files'            => true,
                    'nzl_admin'               => true,
                    'nzl_staff'               => true,
                    'nzl_manage_rooms'        => true,
                    'nzl_manage_rates'        => true,
                    'nzl_manage_inventory'    => true,
                    'nzl_manage_bookings'     => true,
                    'nzl_manage_guests'       => true,
                    'nzl_view_reports'        => true,
                    'nzl_view_calendar'       => true,
                    'nzl_manage_channels'     => true,
                    'nzl_manage_settings'     => true,
                    'nzl_manage_employees'    => true,
                    'nzl_manage_housekeeping' => true,
                    'nzl_manage_billing'      => true,
                    'nzl_manage_pos'          => true,
                    'nzl_manage_messaging'    => true,
                ],
            ],
            'nzl_reception' => [
                'label' => 'Hotel Reception',
                'caps'  => [
                    'read'                => true,
                    'upload_files'        => true,
                    'nzl_staff'           => true,
                    'nzl_manage_bookings' => true,
                    'nzl_manage_guests'   => true,
                    'nzl_view_calendar'   => true,
                    'nzl_manage_billing'  => true,
                ],
            ],
            'nzl_housekeeper' => [
                'label' => 'Housekeeper',
                'caps'  => [
                    'read'                    => true,
                    'nzl_staff'               => true,
                    'nzl_manage_housekeeping' => true,
                    'nzl_view_calendar'       => true,
                ],
            ],
            'nzl_finance' => [
                'label' => 'Finance',
                'caps'  => [
                    'read'               => true,
                    'nzl_staff'          => true,
                    'nzl_manage_billing' => true,
                    'nzl_view_reports'   => true,
                    'nzl_manage_rates'   => true,
                    'nzl_manage_pos'     => true,
                ],
            ],
            'nzl_concierge' => [
                'label' => 'Concierge',
                'caps'  => [
                    'read'                 => true,
                    'nzl_staff'            => true,
                    'nzl_manage_guests'    => true,
                    'nzl_manage_bookings'  => true,
                    'nzl_view_calendar'    => true,
                    'nzl_manage_messaging' => true,
                ],
            ],
        ];

        foreach ( $canonical_caps_by_role as $role_slug => $definition ) {
            $role_obj = get_role( $role_slug );

            if ( ! $role_obj ) {
                // Role does not exist — create it.
                add_role( $role_slug, $definition['label'], $definition['caps'] );
                continue;
            }

            // Role exists — reconcile capabilities.
            // Add missing caps.
            foreach ( $definition['caps'] as $cap => $grant ) {
                if ( ! $role_obj->has_cap( $cap ) ) {
                    $role_obj->add_cap( $cap, $grant );
                }
            }
            // Remove caps not in the canonical set.
            foreach ( $role_obj->capabilities as $cap => $grant ) {
                if ( ! isset( $definition['caps'][ $cap ] ) ) {
                    $role_obj->remove_cap( $cap );
                }
            }
        }

        // Ensure admin has all nzl capabilities.
        $admin_role = get_role( 'administrator' );
        if ( $admin_role && ! $admin_role->has_cap( 'nzl_manage_housekeeping' ) ) {
            $caps = [
                'nzl_admin', 'nzl_staff', 'nzl_manage_rooms', 'nzl_manage_rates',
                'nzl_manage_inventory', 'nzl_manage_bookings', 'nzl_manage_guests',
                'nzl_view_reports', 'nzl_view_calendar', 'nzl_manage_channels',
                'nzl_manage_settings', 'nzl_manage_employees',
                'nzl_manage_housekeeping', 'nzl_manage_billing',
                'nzl_manage_pos', 'nzl_manage_messaging',
            ];
            foreach ( $caps as $cap ) {
                $admin_role->add_cap( $cap );
            }
        }
    }

    public function registerRestRoutes(): void {
        $this->container->get( EmployeeController::class )->registerRoutes();
    }
}
