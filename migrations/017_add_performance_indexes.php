<?php
/**
 * Migration 017: Add missing performance indexes identified by code audit.
 *
 * Addresses:
 *  - bookings.cancelled_at: used in night audit and cancellation reports
 *  - notifications composite: deduplication check (booking_id, type, channel, status)
 *  - seasonal_rates composite: pricing hot path (room_type_id, rate_plan_id)
 *  - payments.payment_date: night audit payment stats (range queries)
 *  - bookings composite: channel sync lookup (channel_booking_id, channel_name)
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function nzl_migration_017_add_performance_indexes(): void {
	global $wpdb;

	$indexes = [
		// bookings.cancelled_at — used by NightAuditService and ReportService
		[
			'table'  => $wpdb->prefix . 'nzl_bookings',
			'index'  => 'idx_cancelled_at',
			'sql'    => 'ADD INDEX idx_cancelled_at (cancelled_at)',
		],
		// notifications dedup composite — used by hasBeenSent() lookups
		[
			'table'  => $wpdb->prefix . 'nzl_notifications',
			'index'  => 'idx_dedup',
			'sql'    => 'ADD INDEX idx_dedup (booking_id, type, channel, status)',
		],
		// seasonal_rates pricing composite — used by getForDateRange()
		[
			'table'  => $wpdb->prefix . 'nzl_seasonal_rates',
			'index'  => 'idx_room_rate',
			'sql'    => 'ADD INDEX idx_room_rate (room_type_id, rate_plan_id)',
		],
		// payments.payment_date — used by NightAuditService range queries
		[
			'table'  => $wpdb->prefix . 'nzl_payments',
			'index'  => 'idx_payment_date',
			'sql'    => 'ADD INDEX idx_payment_date (payment_date)',
		],
		// bookings channel lookup composite — used by ChannelSyncService
		[
			'table'  => $wpdb->prefix . 'nzl_bookings',
			'index'  => 'idx_channel_lookup',
			'sql'    => 'ADD INDEX idx_channel_lookup (channel_booking_id, channel_name)',
		],
	];

	foreach ( $indexes as $def ) {
		$table = $def['table'];
		$index = $def['index'];

		// Check if table exists.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		if ( $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $table ) ) !== $table ) {
			continue;
		}

		// Check if index already exists.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$exists = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND INDEX_NAME = %s",
				DB_NAME,
				$table,
				$index
			)
		);

		if ( ! $exists ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->query( "ALTER TABLE {$table} {$def['sql']}" );
		}
	}
}
