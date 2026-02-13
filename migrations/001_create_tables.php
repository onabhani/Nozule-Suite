<?php
/**
 * Migration 001: Create all database tables.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function nzl_migration_001_create_tables(): void {
    global $wpdb;

    $charset_collate = $wpdb->get_charset_collate();
    $prefix          = $wpdb->prefix;

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';

    // Room Types
    $sql = "CREATE TABLE {$prefix}nzl_room_types (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        name VARCHAR(255) NOT NULL,
        name_ar VARCHAR(255) NOT NULL,
        slug VARCHAR(255) NOT NULL,
        description TEXT,
        description_ar TEXT,
        base_occupancy TINYINT UNSIGNED NOT NULL DEFAULT 2,
        max_occupancy TINYINT UNSIGNED NOT NULL DEFAULT 4,
        base_price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        size_sqm SMALLINT UNSIGNED DEFAULT NULL,
        bed_type VARCHAR(100) DEFAULT NULL,
        amenities LONGTEXT DEFAULT NULL,
        images LONGTEXT DEFAULT NULL,
        sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 0,
        status VARCHAR(20) NOT NULL DEFAULT 'active',
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY slug (slug),
        KEY status (status)
    ) $charset_collate;";
    dbDelta( $sql );

    // Rooms
    $sql = "CREATE TABLE {$prefix}nzl_rooms (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        room_type_id BIGINT UNSIGNED NOT NULL,
        room_number VARCHAR(20) NOT NULL,
        floor TINYINT UNSIGNED DEFAULT NULL,
        status VARCHAR(20) NOT NULL DEFAULT 'available',
        amenities_override LONGTEXT DEFAULT NULL,
        notes TEXT,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY room_number (room_number),
        KEY room_type_id (room_type_id),
        KEY status (status)
    ) $charset_collate;";
    dbDelta( $sql );

    // Room Inventory
    $sql = "CREATE TABLE {$prefix}nzl_room_inventory (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        room_type_id BIGINT UNSIGNED NOT NULL,
        date DATE NOT NULL,
        total_rooms SMALLINT UNSIGNED NOT NULL DEFAULT 0,
        booked_rooms SMALLINT UNSIGNED NOT NULL DEFAULT 0,
        blocked_rooms SMALLINT UNSIGNED NOT NULL DEFAULT 0,
        base_price DECIMAL(10,2) DEFAULT NULL,
        min_stay TINYINT UNSIGNED DEFAULT NULL,
        max_stay TINYINT UNSIGNED DEFAULT NULL,
        closed_to_arrival TINYINT(1) NOT NULL DEFAULT 0,
        closed_to_departure TINYINT(1) NOT NULL DEFAULT 0,
        stop_sell TINYINT(1) NOT NULL DEFAULT 0,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY room_type_date (room_type_id, date),
        KEY date_idx (date)
    ) $charset_collate;";
    dbDelta( $sql );

    // Rate Plans
    $sql = "CREATE TABLE {$prefix}nzl_rate_plans (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        room_type_id BIGINT UNSIGNED DEFAULT NULL,
        name VARCHAR(255) NOT NULL,
        name_ar VARCHAR(255) NOT NULL,
        code VARCHAR(50) NOT NULL,
        description TEXT,
        meal_plan VARCHAR(30) NOT NULL DEFAULT 'room_only',
        price_modifier DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        modifier_type VARCHAR(20) NOT NULL DEFAULT 'fixed',
        is_default TINYINT(1) NOT NULL DEFAULT 0,
        is_refundable TINYINT(1) NOT NULL DEFAULT 1,
        cancellation_hours SMALLINT UNSIGNED DEFAULT 24,
        min_stay TINYINT UNSIGNED DEFAULT NULL,
        max_stay TINYINT UNSIGNED DEFAULT NULL,
        valid_from DATE DEFAULT NULL,
        valid_to DATE DEFAULT NULL,
        status VARCHAR(20) NOT NULL DEFAULT 'active',
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY code (code),
        KEY room_type_id (room_type_id),
        KEY status (status)
    ) $charset_collate;";
    dbDelta( $sql );

    // Seasonal Rates
    $sql = "CREATE TABLE {$prefix}nzl_seasonal_rates (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        rate_plan_id BIGINT UNSIGNED DEFAULT NULL,
        room_type_id BIGINT UNSIGNED DEFAULT NULL,
        name VARCHAR(255) NOT NULL,
        name_ar VARCHAR(255) DEFAULT NULL,
        start_date DATE NOT NULL,
        end_date DATE NOT NULL,
        price_modifier DECIMAL(10,2) NOT NULL,
        modifier_type VARCHAR(20) NOT NULL DEFAULT 'percentage',
        days_of_week LONGTEXT DEFAULT NULL,
        priority TINYINT UNSIGNED NOT NULL DEFAULT 0,
        status VARCHAR(20) NOT NULL DEFAULT 'active',
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY date_range (start_date, end_date),
        KEY status_priority (status, priority)
    ) $charset_collate;";
    dbDelta( $sql );

    // Guests
    $sql = "CREATE TABLE {$prefix}nzl_guests (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        wp_user_id BIGINT UNSIGNED DEFAULT NULL,
        first_name VARCHAR(100) NOT NULL,
        last_name VARCHAR(100) NOT NULL,
        email VARCHAR(255) NOT NULL,
        phone VARCHAR(30) NOT NULL,
        phone_alt VARCHAR(30) DEFAULT NULL,
        nationality VARCHAR(100) DEFAULT NULL,
        id_type VARCHAR(30) DEFAULT NULL,
        id_number VARCHAR(100) DEFAULT NULL,
        date_of_birth DATE DEFAULT NULL,
        gender VARCHAR(10) DEFAULT NULL,
        address TEXT,
        city VARCHAR(100) DEFAULT NULL,
        country VARCHAR(100) DEFAULT NULL,
        company VARCHAR(255) DEFAULT NULL,
        language VARCHAR(10) DEFAULT 'ar',
        notes TEXT,
        tags LONGTEXT DEFAULT NULL,
        total_bookings INT UNSIGNED NOT NULL DEFAULT 0,
        total_spent DECIMAL(12,2) NOT NULL DEFAULT 0.00,
        total_nights INT UNSIGNED NOT NULL DEFAULT 0,
        last_stay DATE DEFAULT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY email (email),
        KEY phone (phone),
        KEY last_name (last_name)
    ) $charset_collate;";
    dbDelta( $sql );

    // Bookings
    $sql = "CREATE TABLE {$prefix}nzl_bookings (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        booking_number VARCHAR(20) NOT NULL,
        guest_id BIGINT UNSIGNED NOT NULL,
        room_type_id BIGINT UNSIGNED NOT NULL,
        room_id BIGINT UNSIGNED DEFAULT NULL,
        rate_plan_id BIGINT UNSIGNED NOT NULL,
        check_in DATE NOT NULL,
        check_out DATE NOT NULL,
        nights TINYINT UNSIGNED NOT NULL DEFAULT 1,
        adults TINYINT UNSIGNED NOT NULL DEFAULT 1,
        children TINYINT UNSIGNED NOT NULL DEFAULT 0,
        infants TINYINT UNSIGNED NOT NULL DEFAULT 0,
        subtotal DECIMAL(10,2) NOT NULL,
        taxes DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        fees DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        discount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        total_price DECIMAL(10,2) NOT NULL,
        currency VARCHAR(3) NOT NULL DEFAULT 'USD',
        exchange_rate DECIMAL(10,4) NOT NULL DEFAULT 1.0000,
        amount_paid DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        status VARCHAR(20) NOT NULL DEFAULT 'pending',
        payment_status VARCHAR(20) NOT NULL DEFAULT 'unpaid',
        source VARCHAR(30) NOT NULL DEFAULT 'direct',
        source_booking_id VARCHAR(100) DEFAULT NULL,
        arrival_time TIME DEFAULT NULL,
        special_requests TEXT,
        internal_notes TEXT,
        confirmed_at DATETIME DEFAULT NULL,
        confirmed_by BIGINT UNSIGNED DEFAULT NULL,
        checked_in_at DATETIME DEFAULT NULL,
        checked_in_by BIGINT UNSIGNED DEFAULT NULL,
        checked_out_at DATETIME DEFAULT NULL,
        checked_out_by BIGINT UNSIGNED DEFAULT NULL,
        cancelled_at DATETIME DEFAULT NULL,
        cancelled_by BIGINT UNSIGNED DEFAULT NULL,
        cancel_reason TEXT,
        ip_address VARCHAR(45) DEFAULT NULL,
        user_agent VARCHAR(500) DEFAULT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY booking_number (booking_number),
        KEY guest_id (guest_id),
        KEY room_type_id (room_type_id),
        KEY check_in (check_in),
        KEY check_out (check_out),
        KEY status (status),
        KEY source (source),
        KEY created_at (created_at),
        KEY status_checkin (status, check_in)
    ) $charset_collate;";
    dbDelta( $sql );

    // Booking Logs
    $sql = "CREATE TABLE {$prefix}nzl_booking_logs (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        booking_id BIGINT UNSIGNED NOT NULL,
        action VARCHAR(30) NOT NULL,
        field_changed VARCHAR(100) DEFAULT NULL,
        old_value TEXT,
        new_value TEXT,
        user_id BIGINT UNSIGNED DEFAULT NULL,
        user_type VARCHAR(20) NOT NULL DEFAULT 'system',
        ip_address VARCHAR(45) DEFAULT NULL,
        user_agent VARCHAR(500) DEFAULT NULL,
        notes TEXT,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY booking_id (booking_id),
        KEY action (action),
        KEY created_at (created_at)
    ) $charset_collate;";
    dbDelta( $sql );

    // Payments
    $sql = "CREATE TABLE {$prefix}nzl_payments (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        booking_id BIGINT UNSIGNED NOT NULL,
        amount DECIMAL(10,2) NOT NULL,
        currency VARCHAR(3) NOT NULL DEFAULT 'USD',
        method VARCHAR(30) NOT NULL DEFAULT 'cash',
        gateway VARCHAR(50) DEFAULT NULL,
        transaction_id VARCHAR(255) DEFAULT NULL,
        status VARCHAR(20) NOT NULL DEFAULT 'pending',
        gateway_response LONGTEXT DEFAULT NULL,
        notes TEXT,
        received_by BIGINT UNSIGNED DEFAULT NULL,
        paid_at DATETIME DEFAULT NULL,
        refunded_at DATETIME DEFAULT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY booking_id (booking_id),
        KEY status (status),
        KEY paid_at (paid_at)
    ) $charset_collate;";
    dbDelta( $sql );

    // Notifications
    $sql = "CREATE TABLE {$prefix}nzl_notifications (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        booking_id BIGINT UNSIGNED DEFAULT NULL,
        guest_id BIGINT UNSIGNED DEFAULT NULL,
        type VARCHAR(50) NOT NULL,
        channel VARCHAR(20) NOT NULL,
        recipient VARCHAR(255) NOT NULL,
        subject VARCHAR(500) DEFAULT NULL,
        content TEXT NOT NULL,
        content_html TEXT,
        template_id VARCHAR(100) DEFAULT NULL,
        template_vars LONGTEXT DEFAULT NULL,
        status VARCHAR(20) NOT NULL DEFAULT 'queued',
        attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
        sent_at DATETIME DEFAULT NULL,
        delivered_at DATETIME DEFAULT NULL,
        error_message TEXT,
        external_id VARCHAR(255) DEFAULT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY booking_id (booking_id),
        KEY type_idx (type),
        KEY channel_idx (channel),
        KEY status_idx (status),
        KEY created_at (created_at)
    ) $charset_collate;";
    dbDelta( $sql );

    // Channel Mappings
    $sql = "CREATE TABLE {$prefix}nzl_channel_mappings (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        channel VARCHAR(30) NOT NULL,
        room_type_id BIGINT UNSIGNED NOT NULL,
        rate_plan_id BIGINT UNSIGNED DEFAULT NULL,
        external_room_id VARCHAR(255) NOT NULL,
        external_rate_id VARCHAR(255) DEFAULT NULL,
        sync_availability TINYINT(1) NOT NULL DEFAULT 1,
        sync_rates TINYINT(1) NOT NULL DEFAULT 1,
        sync_restrictions TINYINT(1) NOT NULL DEFAULT 1,
        last_sync_at DATETIME DEFAULT NULL,
        last_sync_status VARCHAR(20) DEFAULT NULL,
        last_sync_message TEXT,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY channel_room_rate (channel, room_type_id, rate_plan_id),
        KEY room_type_id (room_type_id),
        KEY channel_idx (channel)
    ) $charset_collate;";
    dbDelta( $sql );

    // Settings
    $sql = "CREATE TABLE {$prefix}nzl_settings (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        option_group VARCHAR(100) NOT NULL DEFAULT 'general',
        option_key VARCHAR(191) NOT NULL,
        option_value LONGTEXT,
        autoload TINYINT(1) NOT NULL DEFAULT 1,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY group_key (option_group, option_key),
        KEY autoload (autoload)
    ) $charset_collate;";
    dbDelta( $sql );
}
