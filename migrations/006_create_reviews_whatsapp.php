<?php
/**
 * Migration 006: Create review and WhatsApp messaging tables.
 *
 * Part of NZL-020 Review Solicitation and NZL-023 WhatsApp Messaging features.
 */
function nzl_migration_006_create_reviews_whatsapp(): void {
	global $wpdb;

	$charset = $wpdb->get_charset_collate();
	$prefix  = $wpdb->prefix . 'nzl_';

	require_once ABSPATH . 'wp-admin/includes/upgrade.php';

	// ── 1. Review Requests ────────────────────────────────────────
	$sql = "CREATE TABLE {$prefix}review_requests (
		id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
		booking_id BIGINT UNSIGNED NOT NULL,
		guest_id BIGINT UNSIGNED NOT NULL,
		to_email VARCHAR(255) NOT NULL,
		status VARCHAR(20) NOT NULL DEFAULT 'queued',
		review_platform VARCHAR(30) NOT NULL DEFAULT 'google',
		send_after DATETIME DEFAULT NULL,
		sent_at DATETIME DEFAULT NULL,
		clicked_at DATETIME DEFAULT NULL,
		created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
		PRIMARY KEY (id),
		KEY idx_status (status),
		KEY idx_booking (booking_id),
		KEY idx_guest (guest_id),
		KEY idx_send_after (status, send_after)
	) $charset;";
	dbDelta( $sql );

	// ── 2. Review Settings ────────────────────────────────────────
	$sql = "CREATE TABLE {$prefix}review_settings (
		id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
		setting_key VARCHAR(50) NOT NULL,
		setting_value TEXT DEFAULT NULL,
		updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
		PRIMARY KEY (id),
		UNIQUE KEY setting_key (setting_key)
	) $charset;";
	dbDelta( $sql );

	// ── 3. Seed default review settings ───────────────────────────
	$settings_table = $prefix . 'review_settings';
	$count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$settings_table}" );

	if ( $count === 0 ) {
		$defaults = [
			'google_review_url'  => '',
			'tripadvisor_url'    => '',
			'delay_hours'        => '2',
			'enabled'            => '1',
			'email_subject'      => 'How was your stay? - {{hotel_name}}',
			'email_subject_ar'   => 'كيف كانت إقامتك؟ - {{hotel_name}}',
			'email_body'         => '<h2>We hope you enjoyed your stay!</h2><p>Dear {{guest_name}},</p><p>Thank you for choosing {{hotel_name}}. We would love to hear about your experience.</p><p>Please take a moment to leave us a review:</p><p><a href="{{google_review_url}}" style="display:inline-block;padding:10px 20px;background:#4285f4;color:#fff;text-decoration:none;border-radius:4px;">Review on Google</a></p><p><a href="{{tripadvisor_url}}" style="display:inline-block;padding:10px 20px;background:#00af87;color:#fff;text-decoration:none;border-radius:4px;">Review on TripAdvisor</a></p><p>Your feedback helps us improve!</p><p>Best regards,<br>{{hotel_name}}</p>',
			'email_body_ar'      => '<h2>نأمل أنك استمتعت بإقامتك!</h2><p>عزيزي {{guest_name}}،</p><p>شكراً لاختيارك {{hotel_name}}. نود أن نسمع عن تجربتك.</p><p>يرجى تخصيص لحظة لترك تقييم:</p><p><a href="{{google_review_url}}" style="display:inline-block;padding:10px 20px;background:#4285f4;color:#fff;text-decoration:none;border-radius:4px;">تقييم على Google</a></p><p><a href="{{tripadvisor_url}}" style="display:inline-block;padding:10px 20px;background:#00af87;color:#fff;text-decoration:none;border-radius:4px;">تقييم على TripAdvisor</a></p><p>ملاحظاتكم تساعدنا على التحسين!</p><p>مع أطيب التحيات،<br>{{hotel_name}}</p>',
		];

		foreach ( $defaults as $key => $value ) {
			$wpdb->insert( $settings_table, [
				'setting_key'   => $key,
				'setting_value' => $value,
			] );
		}
	}

	// ══════════════════════════════════════════════════════════════
	// NZL-023: WhatsApp Messaging tables
	// ══════════════════════════════════════════════════════════════

	// ── 4. WhatsApp Templates ─────────────────────────────────────
	$sql = "CREATE TABLE {$prefix}whatsapp_templates (
		id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
		name VARCHAR(100) NOT NULL,
		slug VARCHAR(100) NOT NULL,
		trigger_event VARCHAR(100) DEFAULT NULL,
		body TEXT NOT NULL,
		body_ar TEXT DEFAULT NULL,
		variables TEXT DEFAULT NULL,
		is_active TINYINT(1) NOT NULL DEFAULT 1,
		created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
		updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
		PRIMARY KEY (id),
		UNIQUE KEY slug (slug),
		KEY idx_trigger (trigger_event)
	) $charset;";
	dbDelta( $sql );

	// ── 5. WhatsApp Log ───────────────────────────────────────────
	$sql = "CREATE TABLE {$prefix}whatsapp_log (
		id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
		template_id BIGINT UNSIGNED DEFAULT NULL,
		booking_id BIGINT UNSIGNED DEFAULT NULL,
		guest_id BIGINT UNSIGNED DEFAULT NULL,
		to_phone VARCHAR(30) NOT NULL,
		body TEXT DEFAULT NULL,
		status VARCHAR(20) NOT NULL DEFAULT 'queued',
		wa_message_id VARCHAR(255) DEFAULT NULL,
		error_message TEXT DEFAULT NULL,
		sent_at DATETIME DEFAULT NULL,
		created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
		PRIMARY KEY (id),
		KEY idx_status (status),
		KEY idx_booking (booking_id),
		KEY idx_guest (guest_id),
		KEY idx_wa_message (wa_message_id)
	) $charset;";
	dbDelta( $sql );

	// ── 6. WhatsApp Settings ──────────────────────────────────────
	$sql = "CREATE TABLE {$prefix}whatsapp_settings (
		id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
		setting_key VARCHAR(50) NOT NULL,
		setting_value TEXT DEFAULT NULL,
		updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
		PRIMARY KEY (id),
		UNIQUE KEY setting_key (setting_key)
	) $charset;";
	dbDelta( $sql );

	// ── 7. Seed default WhatsApp settings ─────────────────────────
	$wa_settings_table = $prefix . 'whatsapp_settings';
	$wa_count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wa_settings_table}" );
	if ( $wa_count === 0 ) {
		$wa_defaults = [
			'phone_number_id' => '',
			'access_token'    => '',
			'business_id'     => '',
			'enabled'         => '0',
			'api_version'     => 'v21.0',
		];
		foreach ( $wa_defaults as $key => $value ) {
			$wpdb->insert( $wa_settings_table, [
				'setting_key'   => $key,
				'setting_value' => $value,
			] );
		}
	}

	// ── 8. Seed default WhatsApp templates ────────────────────────
	$wa_templates_table = $prefix . 'whatsapp_templates';
	$wa_tcount = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wa_templates_table}" );
	if ( $wa_tcount === 0 ) {
		$wpdb->insert( $wa_templates_table, [
			'name'          => 'Booking Confirmed',
			'slug'          => 'booking_confirmed',
			'trigger_event' => 'booking_confirmed',
			'body'          => "Hello {{guest_name}},\n\nYour booking *{{booking_number}}* has been confirmed.\n\nCheck-in: {{check_in}}\nCheck-out: {{check_out}}\nRoom: {{room_type}}\nTotal: {{total_amount}} {{currency}}\n\nWe look forward to welcoming you!\n{{hotel_name}} | {{hotel_phone}}",
			'body_ar'       => "مرحباً {{guest_name}}،\n\nتم تأكيد حجزك *{{booking_number}}*.\n\nتسجيل الدخول: {{check_in}}\nتسجيل الخروج: {{check_out}}\nنوع الغرفة: {{room_type}}\nالمجموع: {{total_amount}} {{currency}}\n\nنتطلع لاستقبالكم!\n{{hotel_name}} | {{hotel_phone}}",
			'variables'     => '["guest_name","booking_number","check_in","check_out","room_type","total_amount","currency","hotel_name","hotel_phone"]',
			'is_active'     => 1,
		] );
		$wpdb->insert( $wa_templates_table, [
			'name'          => 'Pre-Arrival',
			'slug'          => 'pre_arrival',
			'trigger_event' => 'pre_arrival',
			'body'          => "Hello {{guest_name}},\n\nYour stay at *{{hotel_name}}* is coming up on *{{check_in}}*.\n\nBooking: {{booking_number}}\nRoom: {{room_type}}\n\nIf you have any special requests, please let us know.\n{{hotel_name}} | {{hotel_phone}}",
			'body_ar'       => "مرحباً {{guest_name}}،\n\nموعد إقامتك في *{{hotel_name}}* يقترب في *{{check_in}}*.\n\nرقم الحجز: {{booking_number}}\nنوع الغرفة: {{room_type}}\n\nإذا كان لديكم أي طلبات خاصة، يرجى إعلامنا.\n{{hotel_name}} | {{hotel_phone}}",
			'variables'     => '["guest_name","booking_number","check_in","check_out","room_type","hotel_name","hotel_phone"]',
			'is_active'     => 1,
		] );
		$wpdb->insert( $wa_templates_table, [
			'name'          => 'Check-in Welcome',
			'slug'          => 'check_in_welcome',
			'trigger_event' => 'booking_checked_in',
			'body'          => "Welcome, {{guest_name}}!\n\nYou have been checked into room *{{room_number}}* at {{hotel_name}}.\n\nCheck-out: {{check_out}}\n\nIf you need anything during your stay, please contact us.\nEnjoy your stay!",
			'body_ar'       => "أهلاً وسهلاً {{guest_name}}!\n\nتم تسجيل دخولك في الغرفة *{{room_number}}* في {{hotel_name}}.\n\nتسجيل الخروج: {{check_out}}\n\nإذا احتجت أي شيء خلال إقامتك، يرجى التواصل معنا.\nنتمنى لك إقامة ممتعة!",
			'variables'     => '["guest_name","booking_number","room_number","check_out","hotel_name","hotel_phone"]',
			'is_active'     => 1,
		] );
		$wpdb->insert( $wa_templates_table, [
			'name'          => 'Check-out Thank You',
			'slug'          => 'check_out_thanks',
			'trigger_event' => 'booking_checked_out',
			'body'          => "Thank you, {{guest_name}}!\n\nWe hope you enjoyed your stay at *{{hotel_name}}*.\n\nBooking: {{booking_number}}\nStay: {{check_in}} — {{check_out}}\nTotal: {{total_amount}} {{currency}}\n\nWe look forward to welcoming you again!",
			'body_ar'       => "شكراً لك {{guest_name}}!\n\nنأمل أن تكون قد استمتعت بإقامتك في *{{hotel_name}}*.\n\nرقم الحجز: {{booking_number}}\nالإقامة: {{check_in}} — {{check_out}}\nالمجموع: {{total_amount}} {{currency}}\n\nنتطلع لاستقبالك مرة أخرى!",
			'variables'     => '["guest_name","booking_number","check_in","check_out","total_amount","currency","hotel_name","hotel_phone"]',
			'is_active'     => 1,
		] );
	}
}
