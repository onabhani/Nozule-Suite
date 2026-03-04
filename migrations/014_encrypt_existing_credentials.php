<?php
/**
 * Migration 014 — Encrypt existing plaintext credentials at rest.
 *
 * STATUS: STUB — no DB changes are executed yet.
 *
 * This migration must be created and run BEFORE go-live, after real
 * credentials have been entered for the first pilot hotel.
 *
 * What it will do (when implemented):
 *  1. Read every row in nzl_whatsapp_settings where setting_key is one of
 *     'access_token', 'phone_number_id', or 'business_id'.
 *  2. For each row whose value is NOT already encrypted
 *     (CredentialVault::isEncrypted() returns false), re-write the value
 *     via CredentialVault::encrypt(['value' => $plaintext]).
 *  3. Repeat the same for equivalent Odoo credential keys in nzl_settings
 *     (odoo_url, odoo_api_key, odoo_db).
 *
 * New writes are already encrypted by WhatsAppService and OdooService,
 * so this migration only converts legacy plaintext rows that were stored
 * before the encrypt-on-write logic was deployed.
 *
 * Timing guidance:
 *  - Do NOT run on fresh installs (no plaintext rows exist).
 *  - Run once on the pilot hotel's site after connecting WhatsApp/Odoo
 *    for real, before the site is exposed to production traffic.
 *  - Safe to re-run: the isEncrypted() guard makes it idempotent.
 *
 * @see includes/Core/CredentialVault.php
 * @see includes/Modules/WhatsApp/Services/WhatsAppService.php
 * @see includes/Modules/Settings/Services/OdooService.php
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function nzl_migration_014_encrypt_existing_credentials(): void {
    // TODO: Implement before go-live. See docblock above for specification.
}
