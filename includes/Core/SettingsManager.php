<?php

namespace Nozule\Core;

/**
 * Settings Manager for plugin configuration.
 */
class SettingsManager {

    /**
     * Keys whose values are encrypted at rest via CredentialVault.
     *
     * @var string[]
     */
    private const SENSITIVE_KEYS = [
        'integrations.odoo_api_key',
        'integrations.webhook_secret',
        'metasearch.partner_key',
    ];

    private Database $db;
    private array $cache = [];
    private bool $loaded = false;

    public function __construct( Database $db ) {
        $this->db = $db;
    }

    /**
     * Get a setting value.
     *
     * @param mixed $default
     * @return mixed
     */
    public function get( string $key, $default = null ) {
        $this->loadAutoloadSettings();

        if ( array_key_exists( $key, $this->cache ) ) {
            return $this->cache[ $key ];
        }

        // Try loading from database
        $parts = explode( '.', $key, 2 );
        $group = $parts[0] ?? 'general';
        $option_key = $parts[1] ?? $parts[0];

        $table = $this->db->table( 'settings' );
        $value = $this->db->getVar(
            "SELECT option_value FROM {$table} WHERE option_group = %s AND option_key = %s",
            $group,
            $option_key
        );

        if ( $value !== null ) {
            $result = $this->maybeDecrypt( $key, $value );
            $this->cache[ $key ] = $result;
            return $result;
        }

        return $default;
    }

    /**
     * Set a setting value.
     *
     * @param mixed $value
     */
    public function set( string $key, $value, bool $autoload = true ): bool {
        $parts      = explode( '.', $key, 2 );
        $group      = $parts[0] ?? 'general';
        $option_key = $parts[1] ?? $parts[0];
        $store = $this->maybeEncrypt( $key, $value );

        $table    = $this->db->table( 'settings' );
        $existing = $this->db->getVar(
            "SELECT id FROM {$table} WHERE option_group = %s AND option_key = %s",
            $group,
            $option_key
        );

        if ( $existing ) {
            $result = $this->db->update( 'settings', [
                'option_value' => $store,
                'autoload'     => $autoload ? 1 : 0,
            ], [
                'option_group' => $group,
                'option_key'   => $option_key,
            ] );
        } else {
            $result = $this->db->insert( 'settings', [
                'option_group' => $group,
                'option_key'   => $option_key,
                'option_value' => $store,
                'autoload'     => $autoload ? 1 : 0,
            ] );
        }

        $this->cache[ $key ] = $value;

        return $result !== false;
    }

    /**
     * Delete a setting.
     */
    public function delete( string $key ): bool {
        $parts      = explode( '.', $key, 2 );
        $group      = $parts[0] ?? 'general';
        $option_key = $parts[1] ?? $parts[0];

        unset( $this->cache[ $key ] );

        return $this->db->delete( 'settings', [
            'option_group' => $group,
            'option_key'   => $option_key,
        ] ) !== false;
    }

    /**
     * Get all settings for a group.
     */
    public function getGroup( string $group ): array {
        $table   = $this->db->table( 'settings' );
        $results = $this->db->getResults(
            "SELECT option_key, option_value FROM {$table} WHERE option_group = %s",
            $group
        );

        $settings = [];
        foreach ( $results as $row ) {
            $full_key = $group . '.' . $row->option_key;
            $settings[ $row->option_key ] = $this->maybeDecrypt( $full_key, $row->option_value );
        }

        return $settings;
    }

    /**
     * Get all settings.
     */
    public function getAll(): array {
        $table   = $this->db->table( 'settings' );
        $results = $this->db->getResults( "SELECT option_group, option_key, option_value FROM {$table}" );

        $settings = [];
        foreach ( $results as $row ) {
            $full_key = $row->option_group . '.' . $row->option_key;
            $settings[ $row->option_group ][ $row->option_key ] = $this->maybeDecrypt( $full_key, $row->option_value );
        }

        return $settings;
    }

    /**
     * Encrypt a value before storage if the key is sensitive.
     *
     * @param mixed $value
     * @return string Value ready for the database.
     */
    private function maybeEncrypt( string $key, $value ): string {
        if ( in_array( $key, self::SENSITIVE_KEYS, true ) ) {
            return CredentialVault::encrypt( [ 'value' => $value ] );
        }

        return is_array( $value ) || is_object( $value ) ? wp_json_encode( $value ) : (string) $value;
    }

    /**
     * Decrypt a raw database value if the key is sensitive.
     *
     * Handles legacy plaintext gracefully: if the stored value is not
     * a valid CredentialVault ciphertext it is returned as-is, so
     * existing unencrypted rows keep working until the next write
     * re-encrypts them.
     *
     * @return mixed Decrypted scalar or decoded value.
     */
    private function maybeDecrypt( string $key, string $raw ) {
        if ( in_array( $key, self::SENSITIVE_KEYS, true ) && CredentialVault::isEncrypted( $raw ) ) {
            $payload = CredentialVault::decrypt( $raw );
            return $payload['value'] ?? '';
        }

        // Standard JSON-decode path for non-sensitive keys (and legacy plaintext sensitive keys).
        $decoded = json_decode( $raw, true );
        return ( json_last_error() === JSON_ERROR_NONE ) ? $decoded : $raw;
    }

    /**
     * Load autoloaded settings into cache.
     */
    private function loadAutoloadSettings(): void {
        if ( $this->loaded ) {
            return;
        }
        $this->loaded = true;

        $table   = $this->db->table( 'settings' );
        $results = $this->db->getResults(
            "SELECT option_group, option_key, option_value FROM {$table} WHERE autoload = 1"
        );

        foreach ( $results as $row ) {
            $key     = $row->option_group . '.' . $row->option_key;
            $this->cache[ $key ] = $this->maybeDecrypt( $key, $row->option_value );
        }
    }
}
