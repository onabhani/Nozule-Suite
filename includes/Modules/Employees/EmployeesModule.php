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
     * Re-register hotel staff roles if they are missing from WordPress.
     */
    public function ensureRolesExist(): void {
        $wp_roles = wp_roles();

        $required_roles = [ 'nzl_manager', 'nzl_reception', 'nzl_housekeeper', 'nzl_finance', 'nzl_concierge' ];
        $all_present = true;
        foreach ( $required_roles as $role ) {
            if ( ! isset( $wp_roles->roles[ $role ] ) ) {
                $all_present = false;
                break;
            }
        }
        if ( $all_present ) {
            return;
        }

        if ( ! isset( $wp_roles->roles['nzl_manager'] ) ) {
            add_role( 'nzl_manager', 'Hotel Manager', [
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
            ] );
        }

        if ( ! isset( $wp_roles->roles['nzl_reception'] ) ) {
            add_role( 'nzl_reception', 'Hotel Reception', [
                'read'                => true,
                'upload_files'        => true,
                'nzl_staff'           => true,
                'nzl_manage_bookings' => true,
                'nzl_manage_guests'   => true,
                'nzl_view_calendar'   => true,
                'nzl_manage_billing'  => true,
            ] );
        }

        if ( ! isset( $wp_roles->roles['nzl_housekeeper'] ) ) {
            add_role( 'nzl_housekeeper', 'Housekeeper', [
                'read'                    => true,
                'nzl_staff'               => true,
                'nzl_manage_housekeeping' => true,
                'nzl_view_calendar'       => true,
            ] );
        }

        if ( ! isset( $wp_roles->roles['nzl_finance'] ) ) {
            add_role( 'nzl_finance', 'Finance', [
                'read'               => true,
                'nzl_staff'          => true,
                'nzl_manage_billing' => true,
                'nzl_view_reports'   => true,
                'nzl_manage_rates'   => true,
                'nzl_manage_pos'     => true,
            ] );
        }

        if ( ! isset( $wp_roles->roles['nzl_concierge'] ) ) {
            add_role( 'nzl_concierge', 'Concierge', [
                'read'                 => true,
                'nzl_staff'            => true,
                'nzl_manage_guests'    => true,
                'nzl_manage_bookings'  => true,
                'nzl_view_calendar'    => true,
                'nzl_manage_messaging' => true,
            ] );
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
