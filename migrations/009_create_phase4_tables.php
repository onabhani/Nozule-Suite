<?php
/**
 * Migration 009: Create Phase 4 tables.
 *
 * NZL-033 Demand Forecasting
 * NZL-036 Loyalty Program
 * NZL-037 POS Integration
 * NZL-039 Competitive Rate Shopping
 * NZL-041 White-Label / Multi-Brand
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function nzl_migration_009_create_phase4_tables(): void {
	global $wpdb;

	$charset = $wpdb->get_charset_collate();
	$prefix  = $wpdb->prefix . 'nzl_';

	require_once ABSPATH . 'wp-admin/includes/upgrade.php';

	// ══════════════════════════════════════════════════════════════
	// NZL-033: Demand Forecasting
	// ══════════════════════════════════════════════════════════════

	$sql = "CREATE TABLE {$prefix}demand_forecasts (
		id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
		room_type_id BIGINT UNSIGNED DEFAULT NULL,
		forecast_date DATE NOT NULL,
		predicted_occupancy DECIMAL(5,2) NOT NULL DEFAULT 0.00,
		predicted_adr DECIMAL(10,2) NOT NULL DEFAULT 0.00,
		confidence DECIMAL(5,2) NOT NULL DEFAULT 0.00,
		suggested_rate DECIMAL(10,2) NOT NULL DEFAULT 0.00,
		factors TEXT DEFAULT NULL,
		created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
		PRIMARY KEY (id),
		KEY idx_room_date (room_type_id, forecast_date),
		KEY idx_date (forecast_date)
	) $charset;";
	dbDelta( $sql );

	// ══════════════════════════════════════════════════════════════
	// NZL-036: Loyalty Program
	// ══════════════════════════════════════════════════════════════

	$sql = "CREATE TABLE {$prefix}loyalty_tiers (
		id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
		name VARCHAR(100) NOT NULL,
		name_ar VARCHAR(100) DEFAULT NULL,
		min_points INT NOT NULL DEFAULT 0,
		discount_percent DECIMAL(5,2) NOT NULL DEFAULT 0.00,
		benefits TEXT DEFAULT NULL,
		color VARCHAR(20) DEFAULT '#CD7F32',
		sort_order INT NOT NULL DEFAULT 0,
		created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
		updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
		PRIMARY KEY (id),
		KEY idx_min_points (min_points)
	) $charset;";
	dbDelta( $sql );

	$sql = "CREATE TABLE {$prefix}loyalty_members (
		id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
		guest_id BIGINT UNSIGNED NOT NULL,
		tier_id BIGINT UNSIGNED DEFAULT NULL,
		points_balance INT NOT NULL DEFAULT 0,
		lifetime_points INT NOT NULL DEFAULT 0,
		joined_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
		updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
		PRIMARY KEY (id),
		UNIQUE KEY uk_guest (guest_id),
		KEY idx_tier (tier_id),
		KEY idx_points (lifetime_points)
	) $charset;";
	dbDelta( $sql );

	$sql = "CREATE TABLE {$prefix}loyalty_transactions (
		id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
		member_id BIGINT UNSIGNED NOT NULL,
		booking_id BIGINT UNSIGNED DEFAULT NULL,
		reward_id BIGINT UNSIGNED DEFAULT NULL,
		type VARCHAR(20) NOT NULL DEFAULT 'earn',
		points INT NOT NULL DEFAULT 0,
		balance_after INT NOT NULL DEFAULT 0,
		description VARCHAR(255) DEFAULT NULL,
		created_by BIGINT UNSIGNED DEFAULT NULL,
		created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
		PRIMARY KEY (id),
		KEY idx_member (member_id),
		KEY idx_booking (booking_id),
		KEY idx_reward (reward_id),
		KEY idx_type (type),
		KEY idx_created (created_at)
	) $charset;";
	dbDelta( $sql );

	$sql = "CREATE TABLE {$prefix}loyalty_rewards (
		id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
		name VARCHAR(200) NOT NULL,
		name_ar VARCHAR(200) DEFAULT NULL,
		points_cost INT NOT NULL DEFAULT 0,
		reward_type VARCHAR(30) NOT NULL DEFAULT 'discount',
		reward_value DECIMAL(10,2) NOT NULL DEFAULT 0.00,
		is_active TINYINT(1) NOT NULL DEFAULT 1,
		created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
		updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
		PRIMARY KEY (id),
		KEY idx_active_cost (is_active, points_cost)
	) $charset;";
	dbDelta( $sql );

	// Seed default loyalty tiers.
	$tiers_table = $prefix . 'loyalty_tiers';
	$tier_count  = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$tiers_table}" );

	if ( $tier_count === 0 ) {
		$tiers = [
			[ 'Bronze',   'برونزي',   0,     2.00, '#CD7F32', 1 ],
			[ 'Silver',   'فضي',      500,   5.00, '#C0C0C0', 2 ],
			[ 'Gold',     'ذهبي',     2000, 10.00, '#FFD700', 3 ],
			[ 'Platinum', 'بلاتيني',  5000, 15.00, '#E5E4E2', 4 ],
		];
		foreach ( $tiers as $t ) {
			$wpdb->insert( $tiers_table, [
				'name'             => $t[0],
				'name_ar'          => $t[1],
				'min_points'       => $t[2],
				'discount_percent' => $t[3],
				'color'            => $t[4],
				'sort_order'       => $t[5],
			] );
		}
	}

	// ══════════════════════════════════════════════════════════════
	// NZL-037: POS Integration
	// ══════════════════════════════════════════════════════════════

	$sql = "CREATE TABLE {$prefix}pos_outlets (
		id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
		name VARCHAR(200) NOT NULL,
		name_ar VARCHAR(200) DEFAULT NULL,
		type VARCHAR(30) NOT NULL DEFAULT 'restaurant',
		description TEXT DEFAULT NULL,
		status VARCHAR(20) NOT NULL DEFAULT 'active',
		sort_order INT NOT NULL DEFAULT 0,
		is_active TINYINT(1) NOT NULL DEFAULT 1,
		created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
		updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
		PRIMARY KEY (id),
		KEY idx_type (type),
		KEY idx_active (is_active)
	) $charset;";
	dbDelta( $sql );

	$sql = "CREATE TABLE {$prefix}pos_items (
		id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
		outlet_id BIGINT UNSIGNED NOT NULL,
		name VARCHAR(200) NOT NULL,
		name_ar VARCHAR(200) DEFAULT NULL,
		category VARCHAR(100) DEFAULT NULL,
		price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
		status VARCHAR(20) NOT NULL DEFAULT 'active',
		sort_order INT NOT NULL DEFAULT 0,
		is_active TINYINT(1) NOT NULL DEFAULT 1,
		created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
		updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
		PRIMARY KEY (id),
		KEY idx_outlet (outlet_id),
		KEY idx_category (category),
		KEY idx_active (is_active)
	) $charset;";
	dbDelta( $sql );

	$sql = "CREATE TABLE {$prefix}pos_orders (
		id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
		outlet_id BIGINT UNSIGNED NOT NULL,
		room_number VARCHAR(20) DEFAULT NULL,
		booking_id BIGINT UNSIGNED DEFAULT NULL,
		guest_id BIGINT UNSIGNED DEFAULT NULL,
		subtotal DECIMAL(10,2) NOT NULL DEFAULT 0.00,
		tax DECIMAL(10,2) NOT NULL DEFAULT 0.00,
		total DECIMAL(10,2) NOT NULL DEFAULT 0.00,
		status VARCHAR(20) NOT NULL DEFAULT 'open',
		notes TEXT DEFAULT NULL,
		created_by BIGINT UNSIGNED DEFAULT NULL,
		created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
		updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
		PRIMARY KEY (id),
		KEY idx_outlet (outlet_id),
		KEY idx_booking (booking_id),
		KEY idx_room (room_number),
		KEY idx_status (status),
		KEY idx_created (created_at)
	) $charset;";
	dbDelta( $sql );

	$sql = "CREATE TABLE {$prefix}pos_order_items (
		id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
		order_id BIGINT UNSIGNED NOT NULL,
		item_id BIGINT UNSIGNED DEFAULT NULL,
		item_name VARCHAR(200) NOT NULL,
		quantity INT NOT NULL DEFAULT 1,
		unit_price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
		total DECIMAL(10,2) NOT NULL DEFAULT 0.00,
		PRIMARY KEY (id),
		KEY idx_order (order_id),
		KEY idx_item (item_id)
	) $charset;";
	dbDelta( $sql );

	// ══════════════════════════════════════════════════════════════
	// NZL-039: Competitive Rate Shopping
	// ══════════════════════════════════════════════════════════════

	$sql = "CREATE TABLE {$prefix}rate_shop_competitors (
		id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
		name VARCHAR(200) NOT NULL,
		name_ar VARCHAR(200) DEFAULT NULL,
		source VARCHAR(50) NOT NULL DEFAULT 'booking_com',
		room_type_match BIGINT UNSIGNED DEFAULT NULL,
		notes TEXT DEFAULT NULL,
		is_active TINYINT(1) NOT NULL DEFAULT 1,
		created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
		updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
		PRIMARY KEY (id),
		KEY idx_active (is_active),
		KEY idx_source (source)
	) $charset;";
	dbDelta( $sql );

	$sql = "CREATE TABLE {$prefix}rate_shop_results (
		id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
		competitor_id BIGINT UNSIGNED NOT NULL,
		check_date DATE NOT NULL,
		rate_found DECIMAL(10,2) NOT NULL DEFAULT 0.00,
		currency VARCHAR(10) NOT NULL DEFAULT 'USD',
		source VARCHAR(50) DEFAULT NULL,
		captured_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
		PRIMARY KEY (id),
		KEY idx_competitor_date (competitor_id, check_date),
		KEY idx_check_date (check_date),
		KEY idx_captured (captured_at)
	) $charset;";
	dbDelta( $sql );

	$sql = "CREATE TABLE {$prefix}rate_shop_alerts (
		id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
		competitor_id BIGINT UNSIGNED NOT NULL,
		check_date DATE NOT NULL,
		our_rate DECIMAL(10,2) NOT NULL DEFAULT 0.00,
		their_rate DECIMAL(10,2) NOT NULL DEFAULT 0.00,
		difference DECIMAL(10,2) NOT NULL DEFAULT 0.00,
		diff_percent DECIMAL(5,2) NOT NULL DEFAULT 0.00,
		alert_type VARCHAR(20) NOT NULL DEFAULT 'undercut',
		status VARCHAR(20) NOT NULL DEFAULT 'unresolved',
		resolved_at DATETIME DEFAULT NULL,
		created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
		PRIMARY KEY (id),
		KEY idx_competitor (competitor_id),
		KEY idx_status (status),
		KEY idx_date (check_date),
		KEY idx_type_status (alert_type, status)
	) $charset;";
	dbDelta( $sql );

	// ══════════════════════════════════════════════════════════════
	// NZL-041: White-Label / Multi-Brand
	// ══════════════════════════════════════════════════════════════

	$sql = "CREATE TABLE {$prefix}brands (
		id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
		name VARCHAR(200) NOT NULL,
		name_ar VARCHAR(200) DEFAULT NULL,
		logo_url VARCHAR(500) DEFAULT NULL,
		favicon_url VARCHAR(500) DEFAULT NULL,
		primary_color VARCHAR(20) DEFAULT '#1e40af',
		secondary_color VARCHAR(20) DEFAULT '#3b82f6',
		accent_color VARCHAR(20) DEFAULT '#f59e0b',
		text_color VARCHAR(20) DEFAULT '#1e293b',
		custom_css TEXT DEFAULT NULL,
		email_header_html TEXT DEFAULT NULL,
		email_footer_html TEXT DEFAULT NULL,
		is_default TINYINT(1) NOT NULL DEFAULT 0,
		is_active TINYINT(1) NOT NULL DEFAULT 1,
		created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
		updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
		PRIMARY KEY (id),
		KEY idx_default (is_default),
		KEY idx_active (is_active)
	) $charset;";
	dbDelta( $sql );
}
