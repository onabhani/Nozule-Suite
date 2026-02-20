<?php
/**
 * Migration 008: Create rate restrictions table for NZL-017.
 *
 * Stores restriction rules (min_stay, max_stay, CTA, CTD, stop_sell)
 * applied to room types and rate plans over date ranges.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Create the nzl_rate_restrictions table.
 */
function nzl_migration_008_up(): void {
	global $wpdb;

	$charset = $wpdb->get_charset_collate();
	$prefix  = $wpdb->prefix . 'nzl_';

	require_once ABSPATH . 'wp-admin/includes/upgrade.php';

	$sql = "CREATE TABLE {$prefix}rate_restrictions (
		id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
		room_type_id BIGINT UNSIGNED NOT NULL,
		rate_plan_id BIGINT UNSIGNED DEFAULT NULL,
		restriction_type VARCHAR(30) NOT NULL,
		value INT DEFAULT NULL,
		channel VARCHAR(50) DEFAULT NULL,
		date_from DATE NOT NULL,
		date_to DATE NOT NULL,
		days_of_week VARCHAR(50) DEFAULT NULL,
		is_active TINYINT(1) NOT NULL DEFAULT 1,
		created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
		updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
		PRIMARY KEY (id),
		KEY idx_room_type (room_type_id),
		KEY idx_dates (date_from, date_to),
		KEY idx_type_active (restriction_type, is_active)
	) $charset;";

	dbDelta( $sql );
}

/**
 * Drop the nzl_rate_restrictions table.
 */
function nzl_migration_008_down(): void {
	global $wpdb;

	$table = $wpdb->prefix . 'nzl_rate_restrictions';

	$wpdb->query( "DROP TABLE IF EXISTS {$table}" );
}
