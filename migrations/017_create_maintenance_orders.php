<?php
/**
 * Migration 017: Create nzl_maintenance_orders table.
 *
 * Ticketing system for room repairs and maintenance work orders (NZL-011).
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function nzl_migration_017_create_maintenance_orders(): void {
    global $wpdb;

    $table   = $wpdb->prefix . 'nzl_maintenance_orders';
    $charset = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE IF NOT EXISTS {$table} (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        property_id BIGINT UNSIGNED NULL DEFAULT 1,
        room_id BIGINT UNSIGNED NOT NULL,
        title VARCHAR(255) NOT NULL,
        description TEXT NULL,
        category VARCHAR(50) NOT NULL DEFAULT 'general',
        status VARCHAR(30) NOT NULL DEFAULT 'open',
        priority VARCHAR(20) NOT NULL DEFAULT 'normal',
        assigned_to BIGINT UNSIGNED NULL,
        reported_by BIGINT UNSIGNED NULL,
        started_at DATETIME NULL,
        resolved_at DATETIME NULL,
        resolution_notes TEXT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        INDEX idx_property_id (property_id),
        INDEX idx_room_id (room_id),
        INDEX idx_status (status),
        INDEX idx_assigned_to (assigned_to),
        INDEX idx_priority (priority),
        INDEX idx_created_at (created_at)
    ) {$charset};";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta( $sql );
}
