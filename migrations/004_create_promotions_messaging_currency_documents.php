<?php
/**
 * Migration 004: Create promo codes, email templates, currencies, guest documents tables.
 * Alter rate_plans to add guest_type for Syrian/non-Syrian pricing.
 */
function nzl_migration_004_create_promotions_messaging_currency_documents(): void {
	global $wpdb;

	$charset = $wpdb->get_charset_collate();
	$prefix  = $wpdb->prefix . 'nzl_';

	require_once ABSPATH . 'wp-admin/includes/upgrade.php';

	// ── 1. Promo Codes ─────────────────────────────────────────────
	$sql = "CREATE TABLE {$prefix}promo_codes (
		id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
		code VARCHAR(50) NOT NULL,
		name VARCHAR(200) NOT NULL,
		name_ar VARCHAR(200) DEFAULT NULL,
		description TEXT DEFAULT NULL,
		description_ar TEXT DEFAULT NULL,
		discount_type VARCHAR(20) NOT NULL DEFAULT 'percentage',
		discount_value DECIMAL(10,2) NOT NULL DEFAULT 0.00,
		currency_code VARCHAR(3) DEFAULT 'SYP',
		min_nights INT DEFAULT NULL,
		min_amount DECIMAL(10,2) DEFAULT NULL,
		max_discount DECIMAL(10,2) DEFAULT NULL,
		max_uses INT DEFAULT NULL,
		used_count INT NOT NULL DEFAULT 0,
		per_guest_limit INT DEFAULT NULL,
		valid_from DATE DEFAULT NULL,
		valid_to DATE DEFAULT NULL,
		applicable_room_types TEXT DEFAULT NULL,
		applicable_sources TEXT DEFAULT NULL,
		is_active TINYINT(1) NOT NULL DEFAULT 1,
		created_by BIGINT UNSIGNED DEFAULT NULL,
		created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
		updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
		PRIMARY KEY (id),
		UNIQUE KEY code (code),
		KEY idx_active_dates (is_active, valid_from, valid_to)
	) $charset;";
	dbDelta( $sql );

	// ── 2. Email Templates ─────────────────────────────────────────
	$sql = "CREATE TABLE {$prefix}email_templates (
		id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
		name VARCHAR(100) NOT NULL,
		slug VARCHAR(100) NOT NULL,
		trigger_event VARCHAR(100) DEFAULT NULL,
		subject VARCHAR(255) NOT NULL,
		subject_ar VARCHAR(255) DEFAULT NULL,
		body LONGTEXT NOT NULL,
		body_ar LONGTEXT DEFAULT NULL,
		variables TEXT DEFAULT NULL,
		is_active TINYINT(1) NOT NULL DEFAULT 1,
		created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
		updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
		PRIMARY KEY (id),
		UNIQUE KEY slug (slug),
		KEY idx_trigger (trigger_event)
	) $charset;";
	dbDelta( $sql );

	// ── 3. Email Log ───────────────────────────────────────────────
	$sql = "CREATE TABLE {$prefix}email_log (
		id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
		template_id BIGINT UNSIGNED DEFAULT NULL,
		booking_id BIGINT UNSIGNED DEFAULT NULL,
		guest_id BIGINT UNSIGNED DEFAULT NULL,
		to_email VARCHAR(255) NOT NULL,
		subject VARCHAR(255) NOT NULL,
		body LONGTEXT DEFAULT NULL,
		status VARCHAR(20) NOT NULL DEFAULT 'queued',
		error_message TEXT DEFAULT NULL,
		sent_at DATETIME DEFAULT NULL,
		created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
		PRIMARY KEY (id),
		KEY idx_status (status),
		KEY idx_booking (booking_id),
		KEY idx_guest (guest_id)
	) $charset;";
	dbDelta( $sql );

	// ── 4. Currencies ──────────────────────────────────────────────
	$sql = "CREATE TABLE {$prefix}currencies (
		id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
		code VARCHAR(3) NOT NULL,
		name VARCHAR(100) NOT NULL,
		name_ar VARCHAR(100) DEFAULT NULL,
		symbol VARCHAR(10) NOT NULL,
		symbol_ar VARCHAR(10) DEFAULT NULL,
		decimal_places INT NOT NULL DEFAULT 2,
		exchange_rate DECIMAL(15,6) NOT NULL DEFAULT 1.000000,
		is_default TINYINT(1) NOT NULL DEFAULT 0,
		is_active TINYINT(1) NOT NULL DEFAULT 1,
		sort_order INT NOT NULL DEFAULT 0,
		created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
		updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
		PRIMARY KEY (id),
		UNIQUE KEY code (code)
	) $charset;";
	dbDelta( $sql );

	// ── 5. Exchange Rates ──────────────────────────────────────────
	$sql = "CREATE TABLE {$prefix}exchange_rates (
		id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
		from_currency VARCHAR(3) NOT NULL,
		to_currency VARCHAR(3) NOT NULL,
		rate DECIMAL(15,6) NOT NULL,
		source VARCHAR(50) NOT NULL DEFAULT 'manual',
		effective_date DATE NOT NULL,
		created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
		PRIMARY KEY (id),
		KEY idx_currencies_date (from_currency, to_currency, effective_date)
	) $charset;";
	dbDelta( $sql );

	// ── 6. Guest Documents ─────────────────────────────────────────
	$sql = "CREATE TABLE {$prefix}guest_documents (
		id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
		guest_id BIGINT UNSIGNED NOT NULL,
		document_type VARCHAR(30) NOT NULL DEFAULT 'passport',
		document_number VARCHAR(100) DEFAULT NULL,
		first_name VARCHAR(100) DEFAULT NULL,
		first_name_ar VARCHAR(100) DEFAULT NULL,
		last_name VARCHAR(100) DEFAULT NULL,
		last_name_ar VARCHAR(100) DEFAULT NULL,
		nationality VARCHAR(100) DEFAULT NULL,
		issuing_country VARCHAR(100) DEFAULT NULL,
		issue_date DATE DEFAULT NULL,
		expiry_date DATE DEFAULT NULL,
		date_of_birth DATE DEFAULT NULL,
		gender VARCHAR(10) DEFAULT NULL,
		mrz_line1 VARCHAR(50) DEFAULT NULL,
		mrz_line2 VARCHAR(50) DEFAULT NULL,
		file_path VARCHAR(500) DEFAULT NULL,
		file_type VARCHAR(50) DEFAULT NULL,
		thumbnail_path VARCHAR(500) DEFAULT NULL,
		ocr_data TEXT DEFAULT NULL,
		ocr_status VARCHAR(20) NOT NULL DEFAULT 'none',
		verified TINYINT(1) NOT NULL DEFAULT 0,
		verified_by BIGINT UNSIGNED DEFAULT NULL,
		verified_at DATETIME DEFAULT NULL,
		notes TEXT DEFAULT NULL,
		created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
		updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
		PRIMARY KEY (id),
		KEY idx_guest (guest_id),
		KEY idx_doc_number (document_number),
		KEY idx_expiry (expiry_date)
	) $charset;";
	dbDelta( $sql );

	// ── 7. Add guest_type to rate_plans for Syrian/non-Syrian pricing ─
	$rate_plans_table = $prefix . 'rate_plans';
	$col = $wpdb->get_results(
		$wpdb->prepare(
			"SHOW COLUMNS FROM {$rate_plans_table} LIKE %s",
			'guest_type'
		)
	);
	if ( empty( $col ) ) {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->query(
			"ALTER TABLE {$rate_plans_table} ADD COLUMN guest_type VARCHAR(20) NOT NULL DEFAULT 'all' AFTER is_default"
		);
	}

	// ── 8. Seed default currencies ─────────────────────────────────
	$currencies_table = $prefix . 'currencies';
	$count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$currencies_table}" );
	if ( $count === 0 ) {
		$wpdb->insert( $currencies_table, [
			'code'           => 'SYP',
			'name'           => 'Syrian Pound',
			'name_ar'        => 'ليرة سورية',
			'symbol'         => 'ل.س',
			'symbol_ar'      => 'ل.س',
			'decimal_places' => 0,
			'exchange_rate'  => 1.000000,
			'is_default'     => 1,
			'is_active'      => 1,
			'sort_order'     => 1,
		] );
		$wpdb->insert( $currencies_table, [
			'code'           => 'USD',
			'name'           => 'US Dollar',
			'name_ar'        => 'دولار أمريكي',
			'symbol'         => '$',
			'symbol_ar'      => '$',
			'decimal_places' => 2,
			'exchange_rate'  => 0.000073,
			'is_default'     => 0,
			'is_active'      => 1,
			'sort_order'     => 2,
		] );
		$wpdb->insert( $currencies_table, [
			'code'           => 'EUR',
			'name'           => 'Euro',
			'name_ar'        => 'يورو',
			'symbol'         => '€',
			'symbol_ar'      => '€',
			'decimal_places' => 2,
			'exchange_rate'  => 0.000067,
			'is_default'     => 0,
			'is_active'      => 1,
			'sort_order'     => 3,
		] );
		$wpdb->insert( $currencies_table, [
			'code'           => 'SAR',
			'name'           => 'Saudi Riyal',
			'name_ar'        => 'ريال سعودي',
			'symbol'         => 'ر.س',
			'symbol_ar'      => 'ر.س',
			'decimal_places' => 2,
			'exchange_rate'  => 0.000274,
			'is_default'     => 0,
			'is_active'      => 1,
			'sort_order'     => 4,
		] );
		$wpdb->insert( $currencies_table, [
			'code'           => 'AED',
			'name'           => 'UAE Dirham',
			'name_ar'        => 'درهم إماراتي',
			'symbol'         => 'د.إ',
			'symbol_ar'      => 'د.إ',
			'decimal_places' => 2,
			'exchange_rate'  => 0.000268,
			'is_default'     => 0,
			'is_active'      => 1,
			'sort_order'     => 5,
		] );
	}

	// ── 9. Seed default email templates ────────────────────────────
	$templates_table = $prefix . 'email_templates';
	$tcount = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$templates_table}" );
	if ( $tcount === 0 ) {
		$wpdb->insert( $templates_table, [
			'name'          => 'Booking Confirmation',
			'slug'          => 'booking_confirmation',
			'trigger_event' => 'booking_confirmed',
			'subject'       => 'Booking Confirmation - {{booking_number}}',
			'subject_ar'    => 'تأكيد الحجز - {{booking_number}}',
			'body'          => '<h2>Booking Confirmed</h2><p>Dear {{guest_name}},</p><p>Your booking <strong>{{booking_number}}</strong> has been confirmed.</p><p><strong>Check-in:</strong> {{check_in}}<br><strong>Check-out:</strong> {{check_out}}<br><strong>Room Type:</strong> {{room_type}}<br><strong>Total:</strong> {{total_amount}} {{currency}}</p><p>We look forward to welcoming you!</p><p>{{hotel_name}}</p>',
			'body_ar'       => '<h2>تم تأكيد الحجز</h2><p>عزيزي {{guest_name}}،</p><p>تم تأكيد حجزك <strong>{{booking_number}}</strong>.</p><p><strong>تسجيل الدخول:</strong> {{check_in}}<br><strong>تسجيل الخروج:</strong> {{check_out}}<br><strong>نوع الغرفة:</strong> {{room_type}}<br><strong>المجموع:</strong> {{total_amount}} {{currency}}</p><p>نتطلع لاستقبالكم!</p><p>{{hotel_name}}</p>',
			'variables'     => '["guest_name","booking_number","check_in","check_out","room_type","total_amount","currency","hotel_name","hotel_phone","hotel_email"]',
			'is_active'     => 1,
		] );
		$wpdb->insert( $templates_table, [
			'name'          => 'Pre-Arrival',
			'slug'          => 'pre_arrival',
			'trigger_event' => 'pre_arrival',
			'subject'       => 'Your Stay is Coming Up - {{booking_number}}',
			'subject_ar'    => 'موعد إقامتك يقترب - {{booking_number}}',
			'body'          => '<h2>Your Stay is Almost Here!</h2><p>Dear {{guest_name}},</p><p>We are excited to welcome you on <strong>{{check_in}}</strong>.</p><p><strong>Booking:</strong> {{booking_number}}<br><strong>Room Type:</strong> {{room_type}}</p><p>If you have any special requests, please let us know.</p><p>{{hotel_name}}<br>{{hotel_phone}}</p>',
			'body_ar'       => '<h2>موعد إقامتك يقترب!</h2><p>عزيزي {{guest_name}}،</p><p>يسعدنا استقبالكم في <strong>{{check_in}}</strong>.</p><p><strong>رقم الحجز:</strong> {{booking_number}}<br><strong>نوع الغرفة:</strong> {{room_type}}</p><p>إذا كان لديكم أي طلبات خاصة، يرجى إعلامنا.</p><p>{{hotel_name}}<br>{{hotel_phone}}</p>',
			'variables'     => '["guest_name","booking_number","check_in","check_out","room_type","hotel_name","hotel_phone","hotel_email"]',
			'is_active'     => 1,
		] );
		$wpdb->insert( $templates_table, [
			'name'          => 'Check-in Welcome',
			'slug'          => 'check_in_welcome',
			'trigger_event' => 'booking_checked_in',
			'subject'       => 'Welcome! - Room {{room_number}}',
			'subject_ar'    => 'أهلاً وسهلاً! - غرفة {{room_number}}',
			'body'          => '<h2>Welcome!</h2><p>Dear {{guest_name}},</p><p>Welcome to {{hotel_name}}! You have been checked into room <strong>{{room_number}}</strong>.</p><p><strong>Check-out:</strong> {{check_out}}</p><p>If you need anything during your stay, please contact the front desk.</p><p>Enjoy your stay!</p>',
			'body_ar'       => '<h2>أهلاً وسهلاً!</h2><p>عزيزي {{guest_name}}،</p><p>مرحباً بك في {{hotel_name}}! تم تسجيل دخولك في الغرفة <strong>{{room_number}}</strong>.</p><p><strong>تسجيل الخروج:</strong> {{check_out}}</p><p>إذا احتجت أي شيء خلال إقامتك، يرجى التواصل مع مكتب الاستقبال.</p><p>نتمنى لك إقامة ممتعة!</p>',
			'variables'     => '["guest_name","booking_number","room_number","check_out","hotel_name","hotel_phone"]',
			'is_active'     => 1,
		] );
		$wpdb->insert( $templates_table, [
			'name'          => 'Check-out Thank You',
			'slug'          => 'check_out_thanks',
			'trigger_event' => 'booking_checked_out',
			'subject'       => 'Thank You for Your Stay - {{booking_number}}',
			'subject_ar'    => 'شكراً لإقامتك - {{booking_number}}',
			'body'          => '<h2>Thank You!</h2><p>Dear {{guest_name}},</p><p>Thank you for staying with us at {{hotel_name}}.</p><p><strong>Booking:</strong> {{booking_number}}<br><strong>Stay:</strong> {{check_in}} — {{check_out}}<br><strong>Total:</strong> {{total_amount}} {{currency}}</p><p>We hope you enjoyed your stay and look forward to welcoming you again!</p>',
			'body_ar'       => '<h2>شكراً لك!</h2><p>عزيزي {{guest_name}}،</p><p>شكراً لإقامتك في {{hotel_name}}.</p><p><strong>رقم الحجز:</strong> {{booking_number}}<br><strong>الإقامة:</strong> {{check_in}} — {{check_out}}<br><strong>المجموع:</strong> {{total_amount}} {{currency}}</p><p>نأمل أن تكون قد استمتعت بإقامتك ونتطلع لاستقبالك مرة أخرى!</p>',
			'variables'     => '["guest_name","booking_number","check_in","check_out","total_amount","currency","hotel_name","hotel_phone"]',
			'is_active'     => 1,
		] );
	}
}
