<?php

namespace Nozule\Admin;

/**
 * Enqueues CSS and JavaScript assets for the Nozule admin pages.
 *
 * Assets are loaded only when the current screen belongs to the plugin
 * (screen ID starts with "nozule" or "toplevel_page_nzl").
 */
class AdminAssets {

    /**
     * Hook into WordPress to register asset enqueueing.
     */
    public function register(): void {
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue' ] );
    }

    /**
     * Enqueue admin styles and scripts on plugin pages only.
     *
     * @param string $hook_suffix The current admin page hook suffix.
     */
    public function enqueue( string $hook_suffix ): void {

        if ( ! $this->isPluginPage( $hook_suffix ) ) {
            return;
        }

        // -----------------------------------------------------------------
        // Styles
        // -----------------------------------------------------------------

        // Tailwind CSS (CDN)
        wp_enqueue_style(
            'tailwindcss',
            'https://cdn.jsdelivr.net/npm/tailwindcss@3/dist/tailwind.min.css',
            [],
            '3.4.0'
        );

        // Plugin admin styles
        wp_enqueue_style(
            'nozule-admin',
            NZL_PLUGIN_URL . 'assets/css/admin.css',
            [ 'tailwindcss' ],
            NZL_VERSION
        );

        // RTL support
        if ( is_rtl() ) {
            wp_enqueue_style(
                'nozule-admin-rtl',
                NZL_PLUGIN_URL . 'assets/css/admin-rtl.css',
                [ 'nozule-admin' ],
                NZL_VERSION
            );
        }

        // -----------------------------------------------------------------
        // Scripts — loaded in footer, Alpine.js LAST so components register
        // via alpine:init before Alpine.start() auto-runs.
        // -----------------------------------------------------------------

        // Core utilities (no Alpine dependency — just plain JS)
        wp_enqueue_script(
            'nozule-api',
            NZL_PLUGIN_URL . 'assets/js/core/api.js',
            [],
            NZL_VERSION,
            true
        );

        wp_enqueue_script(
            'nozule-utils',
            NZL_PLUGIN_URL . 'assets/js/core/utils.js',
            [],
            NZL_VERSION,
            true
        );

        wp_enqueue_script(
            'nozule-i18n',
            NZL_PLUGIN_URL . 'assets/js/core/i18n.js',
            [],
            NZL_VERSION,
            true
        );

        // Alpine store — registers alpine:init listener (runs before Alpine)
        wp_enqueue_script(
            'nozule-store',
            NZL_PLUGIN_URL . 'assets/js/core/store.js',
            [ 'nozule-utils' ],
            NZL_VERSION,
            true
        );

        // Admin component scripts — each registers an alpine:init listener
        $admin_scripts = [
            'nozule-admin-dashboard'       => 'dashboard.js',
            'nozule-admin-booking-manager'  => 'booking-manager.js',
            'nozule-admin-calendar-view'    => 'calendar-view.js',
            'nozule-admin-rooms'            => 'rooms.js',
            'nozule-admin-inventory'        => 'inventory.js',
            'nozule-admin-guests'           => 'guests.js',
            'nozule-admin-rates'            => 'rates.js',
            'nozule-admin-channels'         => 'channels.js',
            'nozule-admin-settings'         => 'settings.js',
            'nozule-admin-reports'          => 'reports.js',
            'nozule-admin-housekeeping'     => 'housekeeping.js',
            'nozule-admin-billing'          => 'billing.js',
            'nozule-admin-groups'           => 'groups.js',
        ];

        $component_handles = [];
        foreach ( $admin_scripts as $handle => $file ) {
            wp_enqueue_script(
                $handle,
                NZL_PLUGIN_URL . 'assets/js/admin/' . $file,
                [ 'nozule-api', 'nozule-utils', 'nozule-store' ],
                NZL_VERSION,
                true
            );
            $component_handles[] = $handle;
        }

        // Alpine.js CDN — loaded AFTER all component scripts so that
        // when Alpine auto-starts it fires alpine:init and all listeners
        // are already registered.
        wp_enqueue_script(
            'alpinejs',
            'https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js',
            array_merge( [ 'nozule-store' ], $component_handles ),
            '3.14.0',
            true
        );

        // -----------------------------------------------------------------
        // Localized configuration object
        // -----------------------------------------------------------------

        $current_user = wp_get_current_user();

        wp_localize_script( 'nozule-api', 'NozuleAdmin', [
            'nonce'        => wp_create_nonce( 'wp_rest' ),
            'apiBase'      => rest_url( 'nozule/v1' ),
            'adminUrl'     => admin_url(),
            'pluginUrl'    => NZL_PLUGIN_URL,
            'version'      => NZL_VERSION,
            'locale'       => get_locale(),
            'isRtl'        => is_rtl(),
            'user'         => [
                'id'           => $current_user->ID,
                'display_name' => $current_user->display_name,
                'email'        => $current_user->user_email,
            ],
            'i18n'         => [
                'revenue_report'  => __( 'Revenue Report', 'nozule' ),
                'occupancy_report' => __( 'Occupancy Report', 'nozule' ),
                'booking_sources' => __( 'Booking Sources', 'nozule' ),
                'report'          => __( 'Report', 'nozule' ),
            ],
            'capabilities' => [
                'admin'            => current_user_can( 'nzl_admin' ),
                'staff'            => current_user_can( 'nzl_staff' ),
                'manage_rooms'     => current_user_can( 'nzl_manage_rooms' ),
                'manage_rates'     => current_user_can( 'nzl_manage_rates' ),
                'manage_inventory' => current_user_can( 'nzl_manage_inventory' ),
                'manage_bookings'  => current_user_can( 'nzl_manage_bookings' ),
                'manage_guests'    => current_user_can( 'nzl_manage_guests' ),
                'view_reports'     => current_user_can( 'nzl_view_reports' ),
                'view_calendar'    => current_user_can( 'nzl_view_calendar' ),
                'manage_channels'  => current_user_can( 'nzl_manage_channels' ),
                'manage_settings'  => current_user_can( 'nzl_manage_settings' ),
            ],
        ] );
    }

    /**
     * Determine whether the current screen is a plugin page.
     *
     * Plugin pages have a hook suffix that contains the slug prefix
     * used in AdminMenu (e.g. "toplevel_page_nzl-dashboard",
     * "nozule_page_nzl-bookings", etc.).
     *
     * @param string $hook_suffix The admin page hook suffix.
     */
    private function isPluginPage( string $hook_suffix ): bool {
        // The top-level page uses "toplevel_page_nzl-dashboard".
        // Sub-pages use "nozule_page_nzl-*".
        if ( str_starts_with( $hook_suffix, 'toplevel_page_nzl' ) ) {
            return true;
        }

        if ( str_contains( $hook_suffix, 'nozule' ) ) {
            return true;
        }

        // Fallback: check for any "nzl-" occurrence (covers renamed parent slugs).
        if ( str_contains( $hook_suffix, 'nzl-' ) ) {
            return true;
        }

        return false;
    }
}
