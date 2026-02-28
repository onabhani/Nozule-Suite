<?php
/**
 * Migration 011: Add property_id column to core tables (NZL-019).
 *
 * Prepares existing tables for multi-property support by adding a nullable
 * property_id column.  When multi-property mode is off the column stays
 * NULL; when enabled, the system populates it from the active property.
 *
 * Tables affected:
 *   - nzl_room_types
 *   - nzl_rooms
 *   - nzl_room_inventory
 *   - nzl_rate_plans
 *   - nzl_seasonal_rates
 *   - nzl_bookings
 *   - nzl_guests
 *   - nzl_payments
 *   - nzl_notifications
 *   - nzl_channel_mappings
 *   - nzl_housekeeping_tasks
 *   - nzl_folios
 *   - nzl_folio_items
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function nzl_migration_011_add_property_id_columns(): void {
    global $wpdb;

    $prefix = $wpdb->prefix;

    $tables = [
        'nzl_room_types',
        'nzl_rooms',
        'nzl_room_inventory',
        'nzl_rate_plans',
        'nzl_seasonal_rates',
        'nzl_bookings',
        'nzl_guests',
        'nzl_payments',
        'nzl_notifications',
        'nzl_channel_mappings',
        'nzl_housekeeping_tasks',
        'nzl_folios',
        'nzl_folio_items',
    ];

    foreach ( $tables as $table ) {
        $full_table = $prefix . $table;

        // Skip tables that don't exist yet.
        if ( $wpdb->get_var( "SHOW TABLES LIKE '{$full_table}'" ) !== $full_table ) {
            continue;
        }

        // Skip if column already exists.
        $column_exists = $wpdb->get_var(
            "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
             AND TABLE_NAME = '{$full_table}'
             AND COLUMN_NAME = 'property_id'"
        );

        if ( (int) $column_exists > 0 ) {
            continue;
        }

        // Add the property_id column after the id column.
        $wpdb->query( "ALTER TABLE {$full_table} ADD COLUMN property_id VARCHAR(36) DEFAULT NULL AFTER id" );

        // Add an index for property-scoped queries.
        $wpdb->query( "ALTER TABLE {$full_table} ADD INDEX idx_property_id (property_id)" );
    }

    // Back-fill existing rows with the first property's property_id (if one exists).
    $properties_table = $prefix . 'nzl_properties';
    if ( $wpdb->get_var( "SHOW TABLES LIKE '{$properties_table}'" ) === $properties_table ) {
        $first_property_id = $wpdb->get_var(
            "SELECT property_id FROM {$properties_table} WHERE status = 'active' ORDER BY id ASC LIMIT 1"
        );

        if ( $first_property_id ) {
            foreach ( $tables as $table ) {
                $full_table = $prefix . $table;
                if ( $wpdb->get_var( "SHOW TABLES LIKE '{$full_table}'" ) !== $full_table ) {
                    continue;
                }
                $wpdb->query(
                    $wpdb->prepare(
                        "UPDATE {$full_table} SET property_id = %s WHERE property_id IS NULL",
                        $first_property_id
                    )
                );
            }
        }
    }
}
