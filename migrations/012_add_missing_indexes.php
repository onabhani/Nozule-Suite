<?php
/**
 * Migration 012: Add missing performance indexes and resolved_by column.
 *
 * Indexes added:
 *   - nzl_bookings.idx_status           – dashboard, calendar, report filters
 *   - nzl_bookings.idx_checkin_checkout  – availability, calendar, occupancy queries
 *   - nzl_room_inventory.idx_roomtype_date – availability lookups (hottest query)
 *   - nzl_notifications.idx_status_scheduled – notification queue processing
 *
 * Column added:
 *   - nzl_rate_shop_alerts.resolved_by   – tracks which user resolved the alert
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function nzl_migration_012_add_missing_indexes(): void {
	global $wpdb;

	$prefix = $wpdb->prefix . 'nzl_';

	// Helper: check whether an index already exists on a table.
	$index_exists = function ( string $table, string $index_name ) use ( $wpdb ): bool {
		return (bool) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
				 WHERE TABLE_SCHEMA = DATABASE()
				 AND TABLE_NAME = %s
				 AND INDEX_NAME = %s",
				$table,
				$index_name
			)
		);
	};

	// Helper: check whether a column already exists on a table.
	$column_exists = function ( string $table, string $column_name ) use ( $wpdb ): bool {
		return (bool) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
				 WHERE TABLE_SCHEMA = DATABASE()
				 AND TABLE_NAME = %s
				 AND COLUMN_NAME = %s",
				$table,
				$column_name
			)
		);
	};

	// ── 1. nzl_bookings.status ──────────────────────────────────────
	$bookings = $prefix . 'bookings';
	if ( ! $index_exists( $bookings, 'idx_status' ) ) {
		$wpdb->query( "ALTER TABLE {$bookings} ADD INDEX idx_status (status)" );
	}

	// ── 2. nzl_bookings.check_in + check_out ────────────────────────
	if ( ! $index_exists( $bookings, 'idx_checkin_checkout' ) ) {
		$wpdb->query( "ALTER TABLE {$bookings} ADD INDEX idx_checkin_checkout (check_in, check_out)" );
	}

	// ── 3. nzl_room_inventory.room_type_id + date ───────────────────
	$inventory = $prefix . 'room_inventory';
	if ( ! $index_exists( $inventory, 'idx_roomtype_date' ) ) {
		$wpdb->query( "ALTER TABLE {$inventory} ADD INDEX idx_roomtype_date (room_type_id, date)" );
	}

	// ── 4. nzl_notifications.status + scheduled_at ──────────────────
	$notifications = $prefix . 'notifications';
	if ( ! $index_exists( $notifications, 'idx_status_scheduled' ) ) {
		$wpdb->query( "ALTER TABLE {$notifications} ADD INDEX idx_status_scheduled (status, scheduled_at)" );
	}

	// ── 5. nzl_rate_shop_alerts: add resolved_by column ─────────────
	$alerts = $prefix . 'rate_shop_alerts';
	if ( ! $column_exists( $alerts, 'resolved_by' ) ) {
		$wpdb->query( "ALTER TABLE {$alerts} ADD COLUMN resolved_by BIGINT UNSIGNED DEFAULT NULL AFTER resolved_at" );
	}
}
