<?php
/**
 * Migration 019: Add promo_code_id to bookings so per-guest promo usage can
 * be tracked without a separate usage table.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function nzl_migration_019_add_promo_code_id_to_bookings(): void {
	global $wpdb;

	$table = $wpdb->prefix . 'nzl_bookings';

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery
	if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) !== $table ) {
		return;
	}

	// Column: promo_code_id
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery
	$exists = $wpdb->get_var(
		$wpdb->prepare(
			'SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND COLUMN_NAME = %s',
			DB_NAME,
			$table,
			'promo_code_id'
		)
	);

	if ( ! $exists ) {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->query( "ALTER TABLE {$table} ADD COLUMN promo_code_id BIGINT UNSIGNED DEFAULT NULL AFTER discount" );
	}

	// Index for guest usage lookups.
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery
	$indexExists = $wpdb->get_var(
		$wpdb->prepare(
			'SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND INDEX_NAME = %s',
			DB_NAME,
			$table,
			'idx_promo_guest'
		)
	);

	if ( ! $indexExists ) {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->query( "ALTER TABLE {$table} ADD INDEX idx_promo_guest (promo_code_id, guest_id, status)" );
	}
}
