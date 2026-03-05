<?php
/**
 * Migration 016: Add property_id to nzl_employees and seed default property.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function nzl_migration_016_add_property_scope(): void {
    global $wpdb;

    $employees_table  = $wpdb->prefix . 'nzl_employees';
    $properties_table = $wpdb->prefix . 'nzl_properties';

    // 1. Add property_id column to nzl_employees if it doesn't exist.
    $column_exists = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND COLUMN_NAME = 'property_id'",
            DB_NAME,
            $employees_table
        )
    );

    if ( ! $column_exists ) {
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        $wpdb->query(
            "ALTER TABLE {$employees_table} ADD COLUMN property_id BIGINT UNSIGNED NULL DEFAULT 1 AFTER wp_user_id, ADD INDEX idx_property_id (property_id)"
        );
    }

    // 2. Seed the first property if nzl_properties is empty.
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery
    $wpdb->query(
        "INSERT INTO {$properties_table} (name, created_at, updated_at) SELECT 'Default Property', NOW(), NOW() FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM {$properties_table})"
    );
}
