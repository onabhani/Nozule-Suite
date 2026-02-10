<?php

namespace Venezia\Core;

/**
 * Plugin activation handler.
 */
class Activator {

    /**
     * Run on plugin activation.
     */
    public static function activate(): void {
        self::checkRequirements();
        self::createTables();
        self::createRoles();
        self::seedDefaultSettings();
        self::scheduleEvents();

        update_option( 'vhm_db_version', VHM_DB_VERSION );
        update_option( 'vhm_activated_at', current_time( 'mysql' ) );

        flush_rewrite_rules();
    }

    /**
     * Check minimum requirements.
     */
    private static function checkRequirements(): void {
        if ( version_compare( PHP_VERSION, '8.0', '<' ) ) {
            deactivate_plugins( VHM_PLUGIN_BASENAME );
            wp_die(
                esc_html__( 'Venezia Hotel Manager requires PHP 8.0 or later.', 'venezia-hotel' ),
                'Plugin Activation Error',
                [ 'back_link' => true ]
            );
        }
    }

    /**
     * Create database tables.
     */
    private static function createTables(): void {
        require_once VHM_PLUGIN_DIR . 'migrations/001_create_tables.php';
        vhm_migration_001_create_tables();
    }

    /**
     * Create custom user roles.
     */
    private static function createRoles(): void {
        // Hotel Manager role
        add_role( 'vhm_manager', __( 'Hotel Manager', 'venezia-hotel' ), [
            'read'                 => true,
            'vhm_admin'            => true,
            'vhm_staff'            => true,
            'vhm_manage_rooms'     => true,
            'vhm_manage_rates'     => true,
            'vhm_manage_inventory' => true,
            'vhm_manage_bookings'  => true,
            'vhm_manage_guests'    => true,
            'vhm_view_reports'     => true,
            'vhm_view_calendar'    => true,
            'vhm_manage_channels'  => true,
            'vhm_manage_settings'  => true,
        ] );

        // Reception role
        add_role( 'vhm_reception', __( 'Hotel Reception', 'venezia-hotel' ), [
            'read'                => true,
            'vhm_staff'           => true,
            'vhm_manage_bookings' => true,
            'vhm_manage_guests'   => true,
            'vhm_view_calendar'   => true,
        ] );

        // Grant all capabilities to administrators
        $admin_role = get_role( 'administrator' );
        if ( $admin_role ) {
            $caps = [
                'vhm_admin',
                'vhm_staff',
                'vhm_manage_rooms',
                'vhm_manage_rates',
                'vhm_manage_inventory',
                'vhm_manage_bookings',
                'vhm_manage_guests',
                'vhm_view_reports',
                'vhm_view_calendar',
                'vhm_manage_channels',
                'vhm_manage_settings',
            ];
            foreach ( $caps as $cap ) {
                $admin_role->add_cap( $cap );
            }
        }
    }

    /**
     * Seed default settings.
     */
    private static function seedDefaultSettings(): void {
        require_once VHM_PLUGIN_DIR . 'migrations/002_seed_data.php';
        vhm_migration_002_seed_data();
    }

    /**
     * Schedule cron events.
     */
    private static function scheduleEvents(): void {
        if ( ! wp_next_scheduled( 'vhm_daily_maintenance' ) ) {
            wp_schedule_event( time(), 'daily', 'vhm_daily_maintenance' );
        }

        if ( ! wp_next_scheduled( 'vhm_send_reminders' ) ) {
            wp_schedule_event( time(), 'hourly', 'vhm_send_reminders' );
        }
    }
}
