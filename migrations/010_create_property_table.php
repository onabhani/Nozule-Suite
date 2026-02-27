<?php
/**
 * Migration 010: Create property table.
 *
 * Stores hotel property details — address, description, photos, facilities,
 * star rating, policies.  Designed with a unique `property_id` column from
 * day one so that the future Multi-Property feature (NZL-019) can be
 * introduced without a schema rewrite.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function nzl_migration_010_create_property_table(): void {
    global $wpdb;

    $charset_collate = $wpdb->get_charset_collate();
    $prefix          = $wpdb->prefix;

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';

    $sql = "CREATE TABLE {$prefix}nzl_properties (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        property_id VARCHAR(36) NOT NULL,
        name VARCHAR(255) NOT NULL,
        name_ar VARCHAR(255) NOT NULL DEFAULT '',
        slug VARCHAR(255) NOT NULL,
        description TEXT,
        description_ar TEXT,
        property_type VARCHAR(50) NOT NULL DEFAULT 'hotel',
        star_rating TINYINT UNSIGNED DEFAULT NULL,
        address_line_1 VARCHAR(255) DEFAULT NULL,
        address_line_2 VARCHAR(255) DEFAULT NULL,
        city VARCHAR(100) DEFAULT NULL,
        state_province VARCHAR(100) DEFAULT NULL,
        country VARCHAR(100) DEFAULT NULL,
        postal_code VARCHAR(20) DEFAULT NULL,
        latitude DECIMAL(10,7) DEFAULT NULL,
        longitude DECIMAL(10,7) DEFAULT NULL,
        phone VARCHAR(30) DEFAULT NULL,
        phone_alt VARCHAR(30) DEFAULT NULL,
        email VARCHAR(255) DEFAULT NULL,
        website VARCHAR(500) DEFAULT NULL,
        check_in_time VARCHAR(5) NOT NULL DEFAULT '14:00',
        check_out_time VARCHAR(5) NOT NULL DEFAULT '12:00',
        timezone VARCHAR(50) NOT NULL DEFAULT 'Asia/Damascus',
        logo_url VARCHAR(500) DEFAULT NULL,
        cover_image_url VARCHAR(500) DEFAULT NULL,
        photos LONGTEXT DEFAULT NULL,
        facilities LONGTEXT DEFAULT NULL,
        policies LONGTEXT DEFAULT NULL,
        social_links LONGTEXT DEFAULT NULL,
        tax_id VARCHAR(100) DEFAULT NULL,
        license_number VARCHAR(100) DEFAULT NULL,
        total_rooms SMALLINT UNSIGNED DEFAULT NULL,
        total_floors TINYINT UNSIGNED DEFAULT NULL,
        year_built SMALLINT UNSIGNED DEFAULT NULL,
        year_renovated SMALLINT UNSIGNED DEFAULT NULL,
        currency VARCHAR(3) NOT NULL DEFAULT 'USD',
        status VARCHAR(20) NOT NULL DEFAULT 'active',
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY property_id (property_id),
        UNIQUE KEY slug (slug),
        KEY status (status)
    ) $charset_collate;";
    dbDelta( $sql );
}
