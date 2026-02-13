<?php

namespace Nozule\Core;

/**
 * Settings Manager for plugin configuration.
 */
class SettingsManager {

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
            $decoded = json_decode( $value, true );
            $result  = ( json_last_error() === JSON_ERROR_NONE ) ? $decoded : $value;
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
        $store      = is_array( $value ) || is_object( $value ) ? wp_json_encode( $value ) : (string) $value;

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
            $decoded = json_decode( $row->option_value, true );
            $settings[ $row->option_key ] = ( json_last_error() === JSON_ERROR_NONE ) ? $decoded : $row->option_value;
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
            $decoded = json_decode( $row->option_value, true );
            $value   = ( json_last_error() === JSON_ERROR_NONE ) ? $decoded : $row->option_value;
            $settings[ $row->option_group ][ $row->option_key ] = $value;
        }

        return $settings;
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
            $decoded = json_decode( $row->option_value, true );
            $this->cache[ $key ] = ( json_last_error() === JSON_ERROR_NONE ) ? $decoded : $row->option_value;
        }
    }
}
