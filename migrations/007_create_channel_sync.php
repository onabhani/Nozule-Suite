<?php
/**
 * Migration 007: Create channel sync tables (connections, sync log, rate mappings).
 */
function nzl_migration_007_create_channel_sync(): void {
	global $wpdb;

	$charset = $wpdb->get_charset_collate();
	$prefix  = $wpdb->prefix . 'nzl_';

	require_once ABSPATH . 'wp-admin/includes/upgrade.php';

	// ── 1. Channel Connections ───────────────────────────────────
	$sql = "CREATE TABLE {$prefix}channel_connections (
		id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
		channel_name VARCHAR(50) NOT NULL,
		hotel_id VARCHAR(100) NOT NULL DEFAULT '',
		credentials TEXT,
		is_active TINYINT NOT NULL DEFAULT 0,
		last_sync_at DATETIME DEFAULT NULL,
		created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
		updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
		PRIMARY KEY (id),
		UNIQUE KEY uk_channel_name (channel_name)
	) $charset;";
	dbDelta( $sql );

	// ── 2. Channel Sync Log ─────────────────────────────────────
	$sql = "CREATE TABLE {$prefix}channel_sync_log (
		id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
		channel_name VARCHAR(50) NOT NULL,
		direction VARCHAR(10) NOT NULL DEFAULT 'push',
		sync_type VARCHAR(30) NOT NULL DEFAULT 'availability',
		status VARCHAR(20) NOT NULL DEFAULT 'pending',
		records_processed INT NOT NULL DEFAULT 0,
		error_message TEXT,
		started_at DATETIME DEFAULT NULL,
		completed_at DATETIME DEFAULT NULL,
		created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
		PRIMARY KEY (id),
		KEY idx_channel_status (channel_name, status),
		KEY idx_direction (direction),
		KEY idx_created (created_at)
	) $charset;";
	dbDelta( $sql );

	// ── 3. Channel Rate Map ─────────────────────────────────────
	$sql = "CREATE TABLE {$prefix}channel_rate_map (
		id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
		channel_name VARCHAR(50) NOT NULL,
		local_room_type_id BIGINT UNSIGNED NOT NULL,
		local_rate_plan_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
		channel_room_id VARCHAR(100) NOT NULL DEFAULT '',
		channel_rate_id VARCHAR(100) NOT NULL DEFAULT '',
		is_active TINYINT NOT NULL DEFAULT 1,
		created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
		updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
		PRIMARY KEY (id),
		UNIQUE KEY uk_channel_room_rate (channel_name, local_room_type_id, local_rate_plan_id),
		KEY idx_channel (channel_name),
		KEY idx_room_type (local_room_type_id)
	) $charset;";
	dbDelta( $sql );
}
