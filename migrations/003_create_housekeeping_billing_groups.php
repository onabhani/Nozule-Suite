<?php
/**
 * Migration 003: Create housekeeping, billing (taxes, folios), night-audit, and group-booking tables.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function nzl_migration_003_create_housekeeping_billing_groups(): void {
	global $wpdb;

	$charset_collate = $wpdb->get_charset_collate();
	$prefix          = $wpdb->prefix;

	require_once ABSPATH . 'wp-admin/includes/upgrade.php';

	// ── Housekeeping Tasks ────────────────────────────────────────
	$sql = "CREATE TABLE {$prefix}nzl_housekeeping_tasks (
		id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
		room_id BIGINT UNSIGNED NOT NULL,
		assigned_to BIGINT UNSIGNED DEFAULT NULL,
		status VARCHAR(20) NOT NULL DEFAULT 'dirty',
		priority VARCHAR(20) NOT NULL DEFAULT 'normal',
		task_type VARCHAR(30) NOT NULL DEFAULT 'checkout_clean',
		notes TEXT,
		started_at DATETIME DEFAULT NULL,
		completed_at DATETIME DEFAULT NULL,
		created_by BIGINT UNSIGNED DEFAULT NULL,
		created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
		updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
		PRIMARY KEY (id),
		KEY room_id (room_id),
		KEY assigned_to (assigned_to),
		KEY status (status),
		KEY priority (priority),
		KEY created_at (created_at)
	) $charset_collate;";
	dbDelta( $sql );

	// ── Taxes ─────────────────────────────────────────────────────
	$sql = "CREATE TABLE {$prefix}nzl_taxes (
		id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
		name VARCHAR(255) NOT NULL,
		name_ar VARCHAR(255) NOT NULL,
		rate DECIMAL(8,4) NOT NULL DEFAULT 0.0000,
		type VARCHAR(20) NOT NULL DEFAULT 'percentage',
		applies_to VARCHAR(30) NOT NULL DEFAULT 'all',
		is_active TINYINT(1) NOT NULL DEFAULT 1,
		sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 0,
		created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
		updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
		PRIMARY KEY (id),
		KEY is_active (is_active),
		KEY sort_order (sort_order)
	) $charset_collate;";
	dbDelta( $sql );

	// ── Folios ────────────────────────────────────────────────────
	$sql = "CREATE TABLE {$prefix}nzl_folios (
		id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
		folio_number VARCHAR(30) NOT NULL,
		booking_id BIGINT UNSIGNED DEFAULT NULL,
		group_booking_id BIGINT UNSIGNED DEFAULT NULL,
		guest_id BIGINT UNSIGNED NOT NULL,
		subtotal DECIMAL(12,2) NOT NULL DEFAULT 0.00,
		tax_total DECIMAL(12,2) NOT NULL DEFAULT 0.00,
		discount_total DECIMAL(12,2) NOT NULL DEFAULT 0.00,
		grand_total DECIMAL(12,2) NOT NULL DEFAULT 0.00,
		paid_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
		currency VARCHAR(3) NOT NULL DEFAULT 'SYP',
		status VARCHAR(20) NOT NULL DEFAULT 'open',
		notes TEXT,
		closed_at DATETIME DEFAULT NULL,
		closed_by BIGINT UNSIGNED DEFAULT NULL,
		created_by BIGINT UNSIGNED DEFAULT NULL,
		created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
		updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
		PRIMARY KEY (id),
		UNIQUE KEY folio_number (folio_number),
		KEY booking_id (booking_id),
		KEY group_booking_id (group_booking_id),
		KEY guest_id (guest_id),
		KEY status (status)
	) $charset_collate;";
	dbDelta( $sql );

	// ── Folio Items ───────────────────────────────────────────────
	$sql = "CREATE TABLE {$prefix}nzl_folio_items (
		id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
		folio_id BIGINT UNSIGNED NOT NULL,
		category VARCHAR(30) NOT NULL DEFAULT 'room_charge',
		description VARCHAR(500) NOT NULL,
		description_ar VARCHAR(500) DEFAULT NULL,
		quantity SMALLINT UNSIGNED NOT NULL DEFAULT 1,
		unit_price DECIMAL(12,2) NOT NULL DEFAULT 0.00,
		subtotal DECIMAL(12,2) NOT NULL DEFAULT 0.00,
		tax_json LONGTEXT DEFAULT NULL,
		tax_total DECIMAL(12,2) NOT NULL DEFAULT 0.00,
		total DECIMAL(12,2) NOT NULL DEFAULT 0.00,
		date DATE DEFAULT NULL,
		posted_by BIGINT UNSIGNED DEFAULT NULL,
		created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
		PRIMARY KEY (id),
		KEY folio_id (folio_id),
		KEY category (category),
		KEY date_idx (date)
	) $charset_collate;";
	dbDelta( $sql );

	// ── Night Audits ──────────────────────────────────────────────
	$sql = "CREATE TABLE {$prefix}nzl_night_audits (
		id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
		audit_date DATE NOT NULL,
		total_rooms INT UNSIGNED NOT NULL DEFAULT 0,
		occupied_rooms INT UNSIGNED NOT NULL DEFAULT 0,
		available_rooms INT UNSIGNED NOT NULL DEFAULT 0,
		out_of_order_rooms INT UNSIGNED NOT NULL DEFAULT 0,
		occupancy_rate DECIMAL(5,2) NOT NULL DEFAULT 0.00,
		room_revenue DECIMAL(12,2) NOT NULL DEFAULT 0.00,
		other_revenue DECIMAL(12,2) NOT NULL DEFAULT 0.00,
		total_revenue DECIMAL(12,2) NOT NULL DEFAULT 0.00,
		adr DECIMAL(10,2) NOT NULL DEFAULT 0.00,
		revpar DECIMAL(10,2) NOT NULL DEFAULT 0.00,
		arrivals INT UNSIGNED NOT NULL DEFAULT 0,
		departures INT UNSIGNED NOT NULL DEFAULT 0,
		no_shows INT UNSIGNED NOT NULL DEFAULT 0,
		walk_ins INT UNSIGNED NOT NULL DEFAULT 0,
		cancellations INT UNSIGNED NOT NULL DEFAULT 0,
		total_guests INT UNSIGNED NOT NULL DEFAULT 0,
		cash_collected DECIMAL(12,2) NOT NULL DEFAULT 0.00,
		card_collected DECIMAL(12,2) NOT NULL DEFAULT 0.00,
		other_collected DECIMAL(12,2) NOT NULL DEFAULT 0.00,
		notes TEXT,
		run_by BIGINT UNSIGNED DEFAULT NULL,
		run_at DATETIME DEFAULT NULL,
		status VARCHAR(20) NOT NULL DEFAULT 'completed',
		created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
		PRIMARY KEY (id),
		UNIQUE KEY audit_date (audit_date),
		KEY status (status)
	) $charset_collate;";
	dbDelta( $sql );

	// ── Group Bookings ────────────────────────────────────────────
	$sql = "CREATE TABLE {$prefix}nzl_group_bookings (
		id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
		group_number VARCHAR(30) NOT NULL,
		group_name VARCHAR(255) NOT NULL,
		group_name_ar VARCHAR(255) DEFAULT NULL,
		contact_person VARCHAR(255) DEFAULT NULL,
		contact_phone VARCHAR(30) DEFAULT NULL,
		contact_email VARCHAR(255) DEFAULT NULL,
		agency_name VARCHAR(255) DEFAULT NULL,
		agency_name_ar VARCHAR(255) DEFAULT NULL,
		check_in DATE NOT NULL,
		check_out DATE NOT NULL,
		nights TINYINT UNSIGNED NOT NULL DEFAULT 1,
		total_rooms INT UNSIGNED NOT NULL DEFAULT 0,
		total_guests INT UNSIGNED NOT NULL DEFAULT 0,
		subtotal DECIMAL(12,2) NOT NULL DEFAULT 0.00,
		tax_total DECIMAL(12,2) NOT NULL DEFAULT 0.00,
		grand_total DECIMAL(12,2) NOT NULL DEFAULT 0.00,
		paid_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
		currency VARCHAR(3) NOT NULL DEFAULT 'SYP',
		status VARCHAR(20) NOT NULL DEFAULT 'tentative',
		payment_terms TEXT,
		notes TEXT,
		internal_notes TEXT,
		confirmed_at DATETIME DEFAULT NULL,
		cancelled_at DATETIME DEFAULT NULL,
		created_by BIGINT UNSIGNED DEFAULT NULL,
		created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
		updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
		PRIMARY KEY (id),
		UNIQUE KEY group_number (group_number),
		KEY status (status),
		KEY check_in (check_in),
		KEY agency_name (agency_name)
	) $charset_collate;";
	dbDelta( $sql );

	// ── Group Booking Rooms ───────────────────────────────────────
	$sql = "CREATE TABLE {$prefix}nzl_group_booking_rooms (
		id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
		group_booking_id BIGINT UNSIGNED NOT NULL,
		booking_id BIGINT UNSIGNED DEFAULT NULL,
		room_type_id BIGINT UNSIGNED NOT NULL,
		room_id BIGINT UNSIGNED DEFAULT NULL,
		guest_name VARCHAR(255) DEFAULT NULL,
		guest_id BIGINT UNSIGNED DEFAULT NULL,
		rate_per_night DECIMAL(10,2) NOT NULL DEFAULT 0.00,
		status VARCHAR(20) NOT NULL DEFAULT 'reserved',
		notes TEXT,
		created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
		updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
		PRIMARY KEY (id),
		KEY group_booking_id (group_booking_id),
		KEY booking_id (booking_id),
		KEY room_type_id (room_type_id),
		KEY room_id (room_id),
		KEY status (status)
	) $charset_collate;";
	dbDelta( $sql );
}
