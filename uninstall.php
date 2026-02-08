<?php
/**
 * Venezia Hotel Manager Uninstall
 *
 * Fired when the plugin is uninstalled. Removes all database tables and options.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

global $wpdb;

// Only remove data if the option is set
$remove_data = get_option( 'vhm_remove_data_on_uninstall', false );

if ( $remove_data ) {
    // Drop custom tables
    $tables = [
        $wpdb->prefix . 'vhm_channel_mappings',
        $wpdb->prefix . 'vhm_notifications',
        $wpdb->prefix . 'vhm_payments',
        $wpdb->prefix . 'vhm_booking_logs',
        $wpdb->prefix . 'vhm_bookings',
        $wpdb->prefix . 'vhm_guests',
        $wpdb->prefix . 'vhm_seasonal_rates',
        $wpdb->prefix . 'vhm_rate_plans',
        $wpdb->prefix . 'vhm_room_inventory',
        $wpdb->prefix . 'vhm_rooms',
        $wpdb->prefix . 'vhm_room_types',
        $wpdb->prefix . 'vhm_settings',
    ];

    foreach ( $tables as $table ) {
        $wpdb->query( "DROP TABLE IF EXISTS `{$table}`" ); // phpcs:ignore WordPress.DB.PreparedSQL
    }

    // Remove options
    delete_option( 'vhm_db_version' );
    delete_option( 'vhm_remove_data_on_uninstall' );
    delete_option( 'vhm_activated_at' );

    // Remove custom roles
    remove_role( 'vhm_manager' );
    remove_role( 'vhm_reception' );

    // Remove capabilities from admin
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
            $admin_role->remove_cap( $cap );
        }
    }

    // Clear scheduled events
    wp_clear_scheduled_hook( 'vhm_daily_maintenance' );
    wp_clear_scheduled_hook( 'vhm_send_reminders' );
    wp_clear_scheduled_hook( 'vhm_sync_channels' );

    // Clear transients
    $wpdb->query(
        "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_vhm_%' OR option_name LIKE '_transient_timeout_vhm_%'"
    );
}
