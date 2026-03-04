<?php
/**
 * Migration 015: Create nzl_employees table.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function nzl_migration_015_create_employees_table(): void {
    global $wpdb;

    $table   = $wpdb->prefix . 'nzl_employees';
    $charset = $wpdb->get_charset_collate();

    if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) === $table ) {
        return;
    }

    $sql = "CREATE TABLE {$table} (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        wp_user_id BIGINT UNSIGNED NULL,
        email VARCHAR(100) NOT NULL UNIQUE,
        display_name VARCHAR(100) NOT NULL,
        phone VARCHAR(20) NULL,
        role VARCHAR(50) NOT NULL,
        capabilities JSON NOT NULL DEFAULT (JSON_OBJECT()),
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_role (role),
        INDEX idx_wp_user_id (wp_user_id),
        INDEX idx_email (email)
    ) ENGINE=InnoDB {$charset};";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta( $sql );
}
