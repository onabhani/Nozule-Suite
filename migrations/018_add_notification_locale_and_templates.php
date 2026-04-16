<?php
/**
 * Migration 018: Add locale and WhatsApp template columns to notifications.
 *
 * Supports external SMS/WhatsApp delivery (e.g., SimpleNotify) where:
 *  - locale: routes to correct localized template / sender on the gateway side
 *  - template_name / template_lang / template_params: WhatsApp Business approved
 *    templates (required to send outside the 24-hour customer-service window)
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function nzl_migration_018_add_notification_locale_and_templates(): void {
	global $wpdb;

	$table = $wpdb->prefix . 'nzl_notifications';

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery
	if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) !== $table ) {
		return;
	}

	$columns = [
		'locale'          => "ADD COLUMN locale VARCHAR(5) NOT NULL DEFAULT 'en' AFTER recipient",
		'template_name'   => 'ADD COLUMN template_name VARCHAR(100) DEFAULT NULL AFTER template_vars',
		'template_lang'   => 'ADD COLUMN template_lang VARCHAR(10) DEFAULT NULL AFTER template_name',
		'template_params' => 'ADD COLUMN template_params LONGTEXT DEFAULT NULL AFTER template_lang',
	];

	foreach ( $columns as $column => $ddl ) {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$exists = $wpdb->get_var(
			$wpdb->prepare(
				'SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND COLUMN_NAME = %s',
				DB_NAME,
				$table,
				$column
			)
		);

		if ( ! $exists ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->query( "ALTER TABLE {$table} {$ddl}" );
		}
	}
}
