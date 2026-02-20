<?php

namespace Nozule\Modules\Settings\Controllers;

use Nozule\Core\CacheManager;
use Nozule\Core\CountryProfiles;
use Nozule\Core\SettingsManager;

/**
 * REST API controller for plugin settings.
 *
 * Admin endpoints require the 'nzl_admin' capability.
 * The public endpoint exposes a safe subset of settings
 * without authentication for front-end consumption.
 */
class SettingsController {

    private const NAMESPACE = 'nozule/v1';

    private SettingsManager $settings;
    private CacheManager $cache;

    /**
     * Setting groups that may be managed through the admin API.
     *
     * @var string[]
     */
    private const SETTING_GROUPS = [
        'general',
        'currency',
        'bookings',
        'pricing',
        'notifications',
        'display',
        'integrations',
    ];

    /**
     * Keys exposed on the public (unauthenticated) endpoint.
     *
     * Each entry maps a friendly output key to a SettingsManager key.
     *
     * @var array<string, string>
     */
    private const PUBLIC_KEYS = [
        'hotel_name'     => 'general.hotel_name',
        'hotel_name_ar'  => 'general.hotel_name_ar',
        'hotel_stars'    => 'general.hotel_stars',
        'currency'       => 'currency.default',
        'currency_symbol' => 'currency.symbol',
        'currency_position' => 'currency.position',
        'check_in_time'  => 'bookings.default_check_in_time',
        'check_out_time' => 'bookings.default_check_out_time',
        'primary_color'  => 'display.primary_color',
        'secondary_color' => 'display.secondary_color',
        'date_format'    => 'display.date_format',
        'language'       => 'display.language',
        'operating_country' => 'general.operating_country',
    ];

    public function __construct( SettingsManager $settings, CacheManager $cache ) {
        $this->settings = $settings;
        $this->cache    = $cache;
    }

    /**
     * Register all settings REST routes.
     */
    public function registerRoutes(): void {
        // Admin: get all settings.
        register_rest_route( self::NAMESPACE, '/admin/settings', [
            [
                'methods'             => \WP_REST_Server::READABLE,
                'callback'            => [ $this, 'getAllSettings' ],
                'permission_callback' => [ $this, 'checkAdminPermission' ],
            ],
            [
                'methods'             => \WP_REST_Server::EDITABLE,
                'callback'            => [ $this, 'updateSettings' ],
                'permission_callback' => [ $this, 'checkAdminPermission' ],
                'args'                => $this->getUpdateArgs(),
            ],
        ] );

        // Admin: apply country profile (seeds taxes, sets defaults).
        register_rest_route( self::NAMESPACE, '/admin/settings/apply-country-profile', [
            'methods'             => \WP_REST_Server::CREATABLE,
            'callback'            => [ $this, 'applyCountryProfile' ],
            'permission_callback' => [ $this, 'checkAdminPermission' ],
        ] );

        // Public: get public-facing settings (no auth).
        register_rest_route( self::NAMESPACE, '/settings/public', [
            'methods'             => \WP_REST_Server::READABLE,
            'callback'            => [ $this, 'getPublicSettings' ],
            'permission_callback' => '__return_true',
        ] );
    }

    /**
     * Permission check: current user must have 'nzl_admin' capability.
     */
    public function checkAdminPermission(): bool {
        return current_user_can( 'nzl_admin' );
    }

    /**
     * GET /admin/settings
     *
     * Returns all settings organized by group.
     */
    public function getAllSettings( \WP_REST_Request $request ): \WP_REST_Response {
        $group = $request->get_param( 'group' );

        if ( $group && in_array( $group, self::SETTING_GROUPS, true ) ) {
            $data = [
                $group => $this->settings->getGroup( $group ),
            ];
        } else {
            $data = $this->settings->getAll();
        }

        return new \WP_REST_Response( $data, 200 );
    }

    /**
     * PUT /admin/settings
     *
     * Accepts a JSON body with setting groups and key-value pairs.
     * Example: { "general": { "hotel_name": "My Hotel" }, "currency": { "default": "EUR" } }
     */
    public function updateSettings( \WP_REST_Request $request ): \WP_REST_Response {
        $body    = $request->get_json_params();
        $updated = [];
        $errors  = [];

        if ( empty( $body ) || ! is_array( $body ) ) {
            return new \WP_REST_Response(
                [ 'message' => __( 'Request body must be a JSON object with setting groups.', 'nozule' ) ],
                400
            );
        }

        foreach ( $body as $group => $values ) {
            // Only allow known groups.
            if ( ! in_array( $group, self::SETTING_GROUPS, true ) ) {
                $errors[] = sprintf(
                    /* translators: %s: settings group name */
                    __( 'Unknown settings group: %s', 'nozule' ),
                    $group
                );
                continue;
            }

            if ( ! is_array( $values ) ) {
                $errors[] = sprintf(
                    /* translators: %s: settings group name */
                    __( 'Settings group "%s" must be an object.', 'nozule' ),
                    $group
                );
                continue;
            }

            foreach ( $values as $key => $value ) {
                $settingKey   = $group . '.' . sanitize_key( $key );
                $sanitized    = $this->sanitizeSettingValue( $group, $key, $value );
                $result       = $this->settings->set( $settingKey, $sanitized );

                if ( $result ) {
                    $updated[] = $settingKey;
                } else {
                    $errors[] = sprintf(
                        /* translators: %s: setting key */
                        __( 'Failed to update setting: %s', 'nozule' ),
                        $settingKey
                    );
                }
            }
        }

        // Invalidate settings cache.
        $this->cache->invalidateTag( 'settings' );

        $response = [
            'message'  => sprintf(
                /* translators: %d: number of updated settings */
                __( '%d setting(s) updated successfully.', 'nozule' ),
                count( $updated )
            ),
            'updated'  => $updated,
            'settings' => $this->settings->getAll(),
        ];

        if ( ! empty( $errors ) ) {
            $response['errors'] = $errors;
        }

        return new \WP_REST_Response( $response, 200 );
    }

    /**
     * GET /settings/public
     *
     * Returns a limited set of public-facing settings.
     * No authentication required.
     */
    public function getPublicSettings( \WP_REST_Request $request ): \WP_REST_Response {
        $cacheKey = 'settings_public';
        $cached   = $this->cache->get( $cacheKey );

        if ( $cached !== false ) {
            return new \WP_REST_Response( $cached, 200 );
        }

        $data = [];
        foreach ( self::PUBLIC_KEYS as $outputKey => $settingKey ) {
            $data[ $outputKey ] = $this->settings->get( $settingKey );
        }

        // Cache public settings for 10 minutes.
        $this->cache->set( $cacheKey, $data, 600 );

        return new \WP_REST_Response( $data, 200 );
    }

    /**
     * POST /admin/settings/apply-country-profile
     *
     * Applies a country profile: sets currency, timezone, and seeds taxes.
     */
    public function applyCountryProfile( \WP_REST_Request $request ): \WP_REST_Response {
        $body    = $request->get_json_params();
        $country = sanitize_text_field( $body['country'] ?? '' );

        if ( empty( $country ) ) {
            return new \WP_REST_Response(
                [ 'message' => __( 'Country code is required.', 'nozule' ) ],
                400
            );
        }

        $profile = CountryProfiles::get( $country );
        if ( ! $profile ) {
            return new \WP_REST_Response(
                [ 'message' => __( 'Unsupported country code.', 'nozule' ) ],
                400
            );
        }

        // Apply currency defaults.
        $this->settings->set( 'general.operating_country', $country );
        $this->settings->set( 'currency.default', $profile['currency']['code'] );
        $this->settings->set( 'currency.symbol', $profile['currency']['symbol'] );
        $this->settings->set( 'currency.position', $profile['currency']['position'] );
        $this->settings->set( 'general.timezone', $profile['timezone'] );

        // Seed taxes via direct DB insert (billing module's nzl_taxes table).
        $taxesSeeded = $this->seedTaxes( $profile['taxes'] ?? [] );

        $this->cache->invalidateTag( 'settings' );

        return new \WP_REST_Response( [
            'message'      => sprintf(
                /* translators: %s: country name */
                __( 'Country profile "%s" applied. Currency, timezone, and %d tax(es) configured.', 'nozule' ),
                $profile['label'],
                $taxesSeeded
            ),
            'country'      => $country,
            'taxes_seeded' => $taxesSeeded,
        ], 200 );
    }

    /**
     * Seed default taxes from a country profile.
     * Skips taxes that already exist (matched by name).
     *
     * @param array $taxes Tax definitions from CountryProfiles.
     * @return int Number of taxes seeded.
     */
    private function seedTaxes( array $taxes ): int {
        global $wpdb;

        $table = $wpdb->prefix . 'nzl_taxes';

        // Check table exists.
        if ( $wpdb->get_var( "SHOW TABLES LIKE '{$table}'" ) !== $table ) {
            return 0;
        }

        $seeded = 0;
        foreach ( $taxes as $tax ) {
            // Skip if a tax with this name already exists.
            $exists = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(*) FROM {$table} WHERE name = %s",
                    $tax['name']
                )
            );

            if ( (int) $exists > 0 ) {
                continue;
            }

            $wpdb->insert( $table, [
                'name'       => $tax['name'],
                'name_ar'    => $tax['name_ar'] ?? '',
                'rate'       => $tax['rate'],
                'type'       => $tax['type'] ?? 'percentage',
                'applies_to' => $tax['applies_to'] ?? 'all',
                'is_active'  => 1,
                'sort_order' => $tax['sort_order'] ?? 0,
            ] );

            if ( $wpdb->insert_id ) {
                $seeded++;
            }
        }

        return $seeded;
    }

    /**
     * Sanitize a setting value based on its group and key.
     *
     * @param string $group The settings group.
     * @param string $key   The setting key within the group.
     * @param mixed  $value The raw value to sanitize.
     * @return mixed The sanitized value.
     */
    private function sanitizeSettingValue( string $group, string $key, mixed $value ): mixed {
        // Boolean fields.
        $booleanKeys = [
            'bookings.require_approval',
            'notifications.email_enabled',
            'notifications.sms_enabled',
            'notifications.whatsapp_enabled',
            'notifications.admin_email_on_booking',
            'integrations.enabled',
            'integrations.sync_bookings',
            'integrations.sync_contacts',
            'integrations.sync_invoices',
        ];

        $fullKey = $group . '.' . $key;

        if ( in_array( $fullKey, $booleanKeys, true ) ) {
            return $value ? '1' : '0';
        }

        // Numeric fields.
        $numericKeys = [
            'general.hotel_stars',
            'currency.decimals',
            'bookings.min_advance_days',
            'bookings.max_advance_days',
            'pricing.tax_rate',
            'pricing.extra_adult_charge',
            'pricing.extra_child_charge',
            'pricing.child_max_age',
            'pricing.infant_max_age',
            'notifications.reminder_days_before',
        ];

        if ( in_array( $fullKey, $numericKeys, true ) ) {
            return is_numeric( $value ) ? (string) $value : '0';
        }

        // Email fields.
        if ( $key === 'hotel_email' || str_contains( $key, 'email' ) ) {
            return sanitize_email( $value );
        }

        // Color fields.
        if ( str_contains( $key, 'color' ) ) {
            return sanitize_hex_color( $value ) ?: $value;
        }

        // URL fields.
        if ( str_contains( $key, 'url' ) ) {
            return esc_url_raw( $value );
        }

        // Time fields.
        if ( str_contains( $key, 'time' ) ) {
            if ( preg_match( '/^\d{2}:\d{2}$/', $value ) ) {
                return $value;
            }
            return sanitize_text_field( $value );
        }

        // Arrays and objects stay as-is (will be JSON-encoded by SettingsManager).
        if ( is_array( $value ) ) {
            return $value;
        }

        // Default: sanitize as text.
        return sanitize_text_field( $value );
    }

    /**
     * Build the argument definitions for the update endpoint.
     */
    private function getUpdateArgs(): array {
        return [
            'group' => [
                'required'          => false,
                'type'              => 'string',
                'sanitize_callback' => 'sanitize_text_field',
                'description'       => __( 'Filter by settings group.', 'nozule' ),
            ],
        ];
    }
}
