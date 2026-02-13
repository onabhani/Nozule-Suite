<?php
/**
 * Migration 002: Seed default data.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function nzl_migration_002_seed_data(): void {
    global $wpdb;

    $table = $wpdb->prefix . 'nzl_settings';

    // Check if settings already exist
    $existing = $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
    if ( (int) $existing > 0 ) {
        return;
    }

    $settings = [
        // General
        [ 'general', 'hotel_name', 'Nozule Hotel' ],
        [ 'general', 'hotel_name_ar', 'نوزول' ],
        [ 'general', 'hotel_email', get_option( 'admin_email', '' ) ],
        [ 'general', 'hotel_phone', '' ],
        [ 'general', 'hotel_address', '' ],
        [ 'general', 'hotel_stars', '4' ],
        [ 'general', 'timezone', wp_timezone_string() ],

        // Currency
        [ 'currency', 'default', 'USD' ],
        [ 'currency', 'symbol', '$' ],
        [ 'currency', 'position', 'before' ],
        [ 'currency', 'decimals', '2' ],
        [ 'currency', 'thousand_sep', ',' ],
        [ 'currency', 'decimal_sep', '.' ],

        // Booking
        [ 'bookings', 'require_approval', '0' ],
        [ 'bookings', 'number_prefix', 'NZL' ],
        [ 'bookings', 'min_advance_days', '0' ],
        [ 'bookings', 'max_advance_days', '365' ],
        [ 'bookings', 'default_check_in_time', '14:00' ],
        [ 'bookings', 'default_check_out_time', '12:00' ],
        [ 'bookings', 'terms_url', '' ],

        // Pricing
        [ 'pricing', 'tax_rate', '0' ],
        [ 'pricing', 'tax_name', 'Tax' ],
        [ 'pricing', 'tax_name_ar', 'ضريبة' ],
        [ 'pricing', 'extra_adult_charge', '0' ],
        [ 'pricing', 'extra_child_charge', '0' ],
        [ 'pricing', 'child_max_age', '12' ],
        [ 'pricing', 'infant_max_age', '2' ],

        // Notifications
        [ 'notifications', 'email_enabled', '1' ],
        [ 'notifications', 'sms_enabled', '0' ],
        [ 'notifications', 'whatsapp_enabled', '0' ],
        [ 'notifications', 'admin_email_on_booking', '1' ],
        [ 'notifications', 'reminder_days_before', '1' ],

        // Display
        [ 'display', 'primary_color', '#1e40af' ],
        [ 'display', 'secondary_color', '#f59e0b' ],
        [ 'display', 'date_format', 'Y-m-d' ],
        [ 'display', 'language', 'ar' ],
    ];

    foreach ( $settings as $setting ) {
        $wpdb->insert( $table, [
            'option_group' => $setting[0],
            'option_key'   => $setting[1],
            'option_value' => $setting[2],
            'autoload'     => 1,
        ] );
    }
}
