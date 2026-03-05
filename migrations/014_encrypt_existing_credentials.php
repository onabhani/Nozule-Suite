<?php
/**
 * Migration 014 — Encrypt existing plaintext credentials at rest.
 *
 * Converts legacy plaintext credential rows to encrypted format using
 * CredentialVault. Already-encrypted rows are skipped (idempotent).
 *
 * Tables / keys handled:
 *   nzl_whatsapp_settings: access_token, phone_number_id, business_id
 *   nzl_settings:          integrations.odoo_api_key, integrations.odoo_url, integrations.odoo_db
 *
 * Safe to re-run: isEncrypted() guard makes this idempotent.
 *
 * @see includes/Core/CredentialVault.php
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function nzl_migration_014_encrypt_existing_credentials(): void {
	global $wpdb;

	$prefix = $wpdb->prefix . 'nzl_';

	// ── 1. WhatsApp settings ───────────────────────────────────────
	$wa_table      = $prefix . 'whatsapp_settings';
	$wa_table_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wa_table ) ) === $wa_table;

	if ( $wa_table_exists ) {
		$wa_keys = [ 'access_token', 'phone_number_id', 'business_id' ];

		foreach ( $wa_keys as $key ) {
			$row = $wpdb->get_row( $wpdb->prepare(
				"SELECT id, setting_value FROM {$wa_table} WHERE setting_key = %s",
				$key
			) );

			if ( ! $row || $row->setting_value === '' || $row->setting_value === null ) {
				continue;
			}

			if ( \Nozule\Core\CredentialVault::isEncrypted( $row->setting_value ) ) {
				continue;
			}

			try {
				$encrypted = \Nozule\Core\CredentialVault::encrypt( [ 'value' => $row->setting_value ] );
			} catch ( \RuntimeException $e ) {
				error_log( 'Migration 014: Failed to encrypt whatsapp_settings.' . $key . ': ' . $e->getMessage() );
				continue;
			}

			$wpdb->update(
				$wa_table,
				[ 'setting_value' => $encrypted, 'updated_at' => current_time( 'mysql' ) ],
				[ 'id' => $row->id ],
				[ '%s', '%s' ],
				[ '%d' ]
			);
		}
	}

	// ── 2. General settings (Odoo credentials) ─────────────────────
	$settings_table = $prefix . 'settings';
	$settings_table_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $settings_table ) ) === $settings_table;

	if ( $settings_table_exists ) {
		$odoo_keys = [
			[ 'group' => 'integrations', 'key' => 'odoo_api_key' ],
			[ 'group' => 'integrations', 'key' => 'odoo_url' ],
			[ 'group' => 'integrations', 'key' => 'odoo_db' ],
		];

		foreach ( $odoo_keys as $entry ) {
			$row = $wpdb->get_row( $wpdb->prepare(
				"SELECT id, option_value FROM {$settings_table} WHERE option_group = %s AND option_key = %s",
				$entry['group'],
				$entry['key']
			) );

			if ( ! $row || $row->option_value === '' || $row->option_value === null ) {
				continue;
			}

			if ( \Nozule\Core\CredentialVault::isEncrypted( $row->option_value ) ) {
				continue;
			}

			try {
				$encrypted = \Nozule\Core\CredentialVault::encrypt( [ 'value' => $row->option_value ] );
			} catch ( \RuntimeException $e ) {
				error_log( 'Migration 014: Failed to encrypt settings.' . $entry['group'] . '.' . $entry['key'] . ': ' . $e->getMessage() );
				continue;
			}

			$wpdb->update(
				$settings_table,
				[ 'option_value' => $encrypted ],
				[ 'id' => $row->id ],
				[ '%s' ],
				[ '%d' ]
			);
		}
	}
}
