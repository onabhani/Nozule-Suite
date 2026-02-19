<?php
/**
 * Nozule Uninstall
 *
 * Fired when the plugin is uninstalled. Removes all database tables and options.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

global $wpdb;

// Only remove data if the option is set
$remove_data = get_option( 'nzl_remove_data_on_uninstall', false );

if ( $remove_data ) {
    // Drop custom tables
    $tables = [
        $wpdb->prefix . 'nzl_channel_mappings',
        $wpdb->prefix . 'nzl_notifications',
        $wpdb->prefix . 'nzl_payments',
        $wpdb->prefix . 'nzl_booking_logs',
        $wpdb->prefix . 'nzl_bookings',
        $wpdb->prefix . 'nzl_guests',
        $wpdb->prefix . 'nzl_seasonal_rates',
        $wpdb->prefix . 'nzl_rate_plans',
        $wpdb->prefix . 'nzl_room_inventory',
        $wpdb->prefix . 'nzl_rooms',
        $wpdb->prefix . 'nzl_room_types',
        $wpdb->prefix . 'nzl_settings',
    ];

    foreach ( $tables as $table ) {
        $wpdb->query( "DROP TABLE IF EXISTS `{$table}`" ); // phpcs:ignore WordPress.DB.PreparedSQL
    }

    // Remove options
    delete_option( 'nzl_db_version' );
    delete_option( 'nzl_remove_data_on_uninstall' );
    delete_option( 'nzl_activated_at' );

    // Remove custom roles
    remove_role( 'nzl_manager' );
    remove_role( 'nzl_reception' );

    // Remove capabilities from admin
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
            $admin_role->remove_cap( $cap );
        }
    }

    // Clear scheduled events
    wp_clear_scheduled_hook( 'nzl_daily_maintenance' );
    wp_clear_scheduled_hook( 'nzl_send_reminders' );
    wp_clear_scheduled_hook( 'nzl_sync_channels' );

    // Clear transients
    $wpdb->query(
        "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_nzl_%' OR option_name LIKE '_transient_timeout_nzl_%'"
    );
}
