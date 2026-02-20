<?php
/**
 * Migration 005: Create dynamic pricing tables (occupancy rules, DOW rules, event overrides).
 */
function nzl_migration_005_create_dynamic_pricing(): void {
	global $wpdb;

	$charset = $wpdb->get_charset_collate();
	$prefix  = $wpdb->prefix . 'nzl_';

	require_once ABSPATH . 'wp-admin/includes/upgrade.php';

	// ── 1. Occupancy Rules ────────────────────────────────────────
	$sql = "CREATE TABLE {$prefix}occupancy_rules (
		id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
		room_type_id BIGINT UNSIGNED DEFAULT NULL,
		threshold_percent INT NOT NULL DEFAULT 70,
		modifier_type VARCHAR(20) NOT NULL DEFAULT 'percentage',
		modifier_value DECIMAL(10,2) NOT NULL DEFAULT 0.00,
		priority INT NOT NULL DEFAULT 0,
		status VARCHAR(20) NOT NULL DEFAULT 'active',
		created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
		updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
		PRIMARY KEY (id),
		KEY idx_room_type (room_type_id),
		KEY idx_status_priority (status, priority)
	) $charset;";
	dbDelta( $sql );

	// ── 2. Day-of-Week Rules ──────────────────────────────────────
	$sql = "CREATE TABLE {$prefix}dow_rules (
		id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
		room_type_id BIGINT UNSIGNED DEFAULT NULL,
		day_of_week TINYINT UNSIGNED NOT NULL DEFAULT 0,
		modifier_type VARCHAR(20) NOT NULL DEFAULT 'percentage',
		modifier_value DECIMAL(10,2) NOT NULL DEFAULT 0.00,
		status VARCHAR(20) NOT NULL DEFAULT 'active',
		created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
		updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
		PRIMARY KEY (id),
		KEY idx_room_type (room_type_id),
		KEY idx_day_status (day_of_week, status)
	) $charset;";
	dbDelta( $sql );

	// ── 3. Event Overrides ────────────────────────────────────────
	$sql = "CREATE TABLE {$prefix}event_overrides (
		id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
		name VARCHAR(200) NOT NULL,
		name_ar VARCHAR(200) DEFAULT NULL,
		room_type_id BIGINT UNSIGNED DEFAULT NULL,
		start_date DATE NOT NULL,
		end_date DATE NOT NULL,
		modifier_type VARCHAR(20) NOT NULL DEFAULT 'percentage',
		modifier_value DECIMAL(10,2) NOT NULL DEFAULT 0.00,
		priority INT NOT NULL DEFAULT 0,
		status VARCHAR(20) NOT NULL DEFAULT 'active',
		created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
		updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
		PRIMARY KEY (id),
		KEY idx_room_type (room_type_id),
		KEY idx_dates_status (start_date, end_date, status),
		KEY idx_status_priority (status, priority)
	) $charset;";
	dbDelta( $sql );
}
