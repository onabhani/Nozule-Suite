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

        require_once NZL_PLUGIN_DIR . 'migrations/009_create_phase4_tables.php';
        nzl_migration_009_create_phase4_tables();

        require_once NZL_PLUGIN_DIR . 'migrations/010_create_property_table.php';
        nzl_migration_010_create_property_table();

        require_once NZL_PLUGIN_DIR . 'migrations/011_add_property_id_columns.php';
        nzl_migration_011_add_property_id_columns();
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
        self::createRoles();
        self::seedDefaultSettings();
        self::scheduleEvents();

        update_option( 'nzl_db_version', NZL_DB_VERSION );
    }

    /**
     * Create custom user roles.
     *
     * Role display names are stored as plain English in the DB.
     * Translation happens at display-time via the `editable_roles` filter
     * registered in StaffIsolation.
     */
    private static function createRoles(): void {
        // Remove first to ensure clean display names (fixes locale mismatch).
        remove_role( 'nzl_manager' );
        remove_role( 'nzl_reception' );
        remove_role( 'nzl_housekeeper' );
        remove_role( 'nzl_finance' );
        remove_role( 'nzl_concierge' );

        // Hotel Manager role — full admin access.
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

        // Reception / Front Desk — bookings, guests, calendar, billing.
        add_role( 'nzl_reception', 'Hotel Reception', [
            'read'                 => true,
            'upload_files'         => true,
            'nzl_staff'            => true,
            'nzl_manage_bookings'  => true,
            'nzl_manage_guests'    => true,
            'nzl_view_calendar'    => true,
            'nzl_manage_billing'   => true,
        ] );

        // Housekeeper — housekeeping tasks and room status.
        add_role( 'nzl_housekeeper', 'Housekeeper', [
            'read'                    => true,
            'nzl_staff'               => true,
            'nzl_manage_housekeeping' => true,
            'nzl_view_calendar'       => true,
        ] );

        // Finance / Accountant — billing, reports, rates, POS.
        add_role( 'nzl_finance', 'Finance', [
            'read'                 => true,
            'nzl_staff'            => true,
            'nzl_manage_billing'   => true,
            'nzl_view_reports'     => true,
            'nzl_manage_rates'     => true,
            'nzl_manage_pos'       => true,
        ] );

        // Concierge / Customer Service — guests, messaging, reviews.
        add_role( 'nzl_concierge', 'Concierge', [
            'read'                  => true,
            'nzl_staff'             => true,
            'nzl_manage_guests'     => true,
            'nzl_manage_bookings'   => true,
            'nzl_view_calendar'     => true,
            'nzl_manage_messaging'  => true,
        ] );

        // Grant all capabilities to administrators.
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
                'nzl_manage_employees',
                'nzl_manage_housekeeping',
                'nzl_manage_billing',
                'nzl_manage_pos',
                'nzl_manage_messaging',
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
