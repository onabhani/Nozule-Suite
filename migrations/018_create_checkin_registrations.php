<?php
/**
 * Migration 018: Create nzl_checkin_registrations table.
 *
 * Contactless check-in registration records with token-based guest access (NZL-024).
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function nzl_migration_018_create_checkin_registrations(): void {
    global $wpdb;

    $table   = $wpdb->prefix . 'nzl_checkin_registrations';
    $charset = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE IF NOT EXISTS {$table} (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        booking_id BIGINT UNSIGNED NOT NULL,
        guest_id BIGINT UNSIGNED NOT NULL,
        property_id BIGINT UNSIGNED NULL DEFAULT 1,
        token VARCHAR(64) NOT NULL,
        status VARCHAR(20) NOT NULL DEFAULT 'pending',
        guest_details JSON NULL,
        room_preference TEXT NULL,
        special_requests TEXT NULL,
        document_ids JSON NULL,
        signature_path VARCHAR(500) NULL,
        expires_at DATETIME NOT NULL,
        submitted_at DATETIME NULL,
        reviewed_by BIGINT UNSIGNED NULL,
        reviewed_at DATETIME NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY idx_token (token),
        INDEX idx_booking_id (booking_id),
        INDEX idx_guest_id (guest_id),
        INDEX idx_property_id (property_id),
        INDEX idx_status (status),
        INDEX idx_expires_at (expires_at)
    ) {$charset};";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta( $sql );
}
