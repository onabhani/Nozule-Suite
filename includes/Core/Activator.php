<?php

namespace Nozule\Core;

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

        update_option( 'nzl_db_version', NZL_DB_VERSION );
        update_option( 'nzl_activated_at', current_time( 'mysql' ) );

        flush_rewrite_rules();
    }

    /**
     * Check minimum requirements.
     */
    private static function checkRequirements(): void {
        if ( version_compare( PHP_VERSION, '8.0', '<' ) ) {
            deactivate_plugins( NZL_PLUGIN_BASENAME );
            wp_die(
                esc_html__( 'Nozule requires PHP 8.0 or later.', 'nozule' ),
                'Plugin Activation Error',
                [ 'back_link' => true ]
            );
        }
    }

    /**
     * Create database tables.
     */
    private static function createTables(): void {
        require_once NZL_PLUGIN_DIR . 'migrations/001_create_tables.php';
        nzl_migration_001_create_tables();

        require_once NZL_PLUGIN_DIR . 'migrations/003_create_housekeeping_billing_groups.php';
        nzl_migration_003_create_housekeeping_billing_groups();

        require_once NZL_PLUGIN_DIR . 'migrations/004_create_promotions_messaging_currency_documents.php';
        nzl_migration_004_create_promotions_messaging_currency_documents();

        require_once NZL_PLUGIN_DIR . 'migrations/005_create_dynamic_pricing.php';
        nzl_migration_005_create_dynamic_pricing();

        require_once NZL_PLUGIN_DIR . 'migrations/006_create_reviews_whatsapp.php';
        nzl_migration_006_create_reviews_whatsapp();

        require_once NZL_PLUGIN_DIR . 'migrations/007_create_channel_sync.php';
        nzl_migration_007_create_channel_sync();

        require_once NZL_PLUGIN_DIR . 'migrations/008_create_rate_restrictions.php';
        nzl_migration_008_up();
    }

    /**
     * Run upgrade migrations when the DB version has changed.
     *
     * Called from plugins_loaded so that new tables are created even
     * when the plugin is updated without a deactivate/reactivate cycle.
     */
    public static function maybeUpgrade(): void {
        $installed = get_option( 'nzl_db_version', '0' );

        if ( version_compare( $installed, NZL_DB_VERSION, '>=' ) ) {
            return;
        }

        self::createTables();
        self::seedDefaultSettings();

        update_option( 'nzl_db_version', NZL_DB_VERSION );
    }

    /**
     * Create custom user roles.
     */
    private static function createRoles(): void {
        // Hotel Manager role
        add_role( 'nzl_manager', __( 'Hotel Manager', 'nozule' ), [
            'read'                 => true,
            'nzl_admin'            => true,
            'nzl_staff'            => true,
            'nzl_manage_rooms'     => true,
            'nzl_manage_rates'     => true,
            'nzl_manage_inventory' => true,
            'nzl_manage_bookings'  => true,
            'nzl_manage_guests'    => true,
            'nzl_view_reports'     => true,
            'nzl_view_calendar'    => true,
            'nzl_manage_channels'  => true,
            'nzl_manage_settings'  => true,
        ] );

        // Reception role
        add_role( 'nzl_reception', __( 'Hotel Reception', 'nozule' ), [
            'read'                => true,
            'nzl_staff'           => true,
            'nzl_manage_bookings' => true,
            'nzl_manage_guests'   => true,
            'nzl_view_calendar'   => true,
        ] );

        // Grant all capabilities to administrators
        $admin_role = get_role( 'administrator' );
        if ( $admin_role ) {
            $caps = [
                'nzl_admin',
                'nzl_staff',
                'nzl_manage_rooms',
                'nzl_manage_rates',
                'nzl_manage_inventory',
                'nzl_manage_bookings',
                'nzl_manage_guests',
                'nzl_view_reports',
                'nzl_view_calendar',
                'nzl_manage_channels',
                'nzl_manage_settings',
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
        require_once NZL_PLUGIN_DIR . 'migrations/002_seed_data.php';
        nzl_migration_002_seed_data();
    }

    /**
     * Schedule cron events.
     */
    private static function scheduleEvents(): void {
        if ( ! wp_next_scheduled( 'nzl_daily_maintenance' ) ) {
            wp_schedule_event( time(), 'daily', 'nzl_daily_maintenance' );
        }

        if ( ! wp_next_scheduled( 'nzl_send_reminders' ) ) {
            wp_schedule_event( time(), 'hourly', 'nzl_send_reminders' );
        }
    }
}
