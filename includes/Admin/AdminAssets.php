<?php

namespace Venezia\Admin;

/**
 * Enqueues CSS and JavaScript assets for the Hotel Manager admin pages.
 *
 * Assets are loaded only when the current screen belongs to the plugin
 * (screen ID starts with "hotel-manager" or "toplevel_page_vhm").
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
            'venezia-admin',
            VHM_PLUGIN_URL . 'assets/css/admin.css',
            [ 'tailwindcss' ],
            VHM_VERSION
        );

        // RTL support
        if ( is_rtl() ) {
            wp_enqueue_style(
                'venezia-admin-rtl',
                VHM_PLUGIN_URL . 'assets/css/admin-rtl.css',
                [ 'venezia-admin' ],
                VHM_VERSION
            );
        }

        // -----------------------------------------------------------------
        // Scripts — loaded in footer, Alpine.js LAST so components register
        // via alpine:init before Alpine.start() auto-runs.
        // -----------------------------------------------------------------

        // Core utilities (no Alpine dependency — just plain JS)
        wp_enqueue_script(
            'venezia-api',
            VHM_PLUGIN_URL . 'assets/js/core/api.js',
            [],
            VHM_VERSION,
            true
        );

        wp_enqueue_script(
            'venezia-utils',
            VHM_PLUGIN_URL . 'assets/js/core/utils.js',
            [],
            VHM_VERSION,
            true
        );

        wp_enqueue_script(
            'venezia-i18n',
            VHM_PLUGIN_URL . 'assets/js/core/i18n.js',
            [],
            VHM_VERSION,
            true
        );

        // Alpine store — registers alpine:init listener (runs before Alpine)
        wp_enqueue_script(
            'venezia-store',
            VHM_PLUGIN_URL . 'assets/js/core/store.js',
            [ 'venezia-utils' ],
            VHM_VERSION,
            true
        );

        // Admin component scripts — each registers an alpine:init listener
        $admin_scripts = [
            'venezia-admin-dashboard'       => 'dashboard.js',
            'venezia-admin-booking-manager'  => 'booking-manager.js',
            'venezia-admin-calendar-view'    => 'calendar-view.js',
            'venezia-admin-rooms'            => 'rooms.js',
            'venezia-admin-inventory'        => 'inventory.js',
            'venezia-admin-guests'           => 'guests.js',
            'venezia-admin-rates'            => 'rates.js',
            'venezia-admin-channels'         => 'channels.js',
            'venezia-admin-settings'         => 'settings.js',
            'venezia-admin-reports'          => 'reports.js',
        ];

        $component_handles = [];
        foreach ( $admin_scripts as $handle => $file ) {
            wp_enqueue_script(
                $handle,
                VHM_PLUGIN_URL . 'assets/js/admin/' . $file,
                [ 'venezia-api', 'venezia-utils', 'venezia-store' ],
                VHM_VERSION,
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
            array_merge( [ 'venezia-store' ], $component_handles ),
            '3.14.0',
            true
        );

        // -----------------------------------------------------------------
        // Localized configuration object
        // -----------------------------------------------------------------

        $current_user = wp_get_current_user();

        wp_localize_script( 'venezia-api', 'VeneziaAdmin', [
            'nonce'        => wp_create_nonce( 'wp_rest' ),
            'apiBase'      => rest_url( 'venezia/v1' ),
            'adminUrl'     => admin_url(),
            'pluginUrl'    => VHM_PLUGIN_URL,
            'version'      => VHM_VERSION,
            'locale'       => get_locale(),
            'isRtl'        => is_rtl(),
            'user'         => [
                'id'           => $current_user->ID,
                'display_name' => $current_user->display_name,
                'email'        => $current_user->user_email,
            ],
            'capabilities' => [
                'admin'            => current_user_can( 'vhm_admin' ),
                'staff'            => current_user_can( 'vhm_staff' ),
                'manage_rooms'     => current_user_can( 'vhm_manage_rooms' ),
                'manage_rates'     => current_user_can( 'vhm_manage_rates' ),
                'manage_inventory' => current_user_can( 'vhm_manage_inventory' ),
                'manage_bookings'  => current_user_can( 'vhm_manage_bookings' ),
                'manage_guests'    => current_user_can( 'vhm_manage_guests' ),
                'view_reports'     => current_user_can( 'vhm_view_reports' ),
                'view_calendar'    => current_user_can( 'vhm_view_calendar' ),
                'manage_channels'  => current_user_can( 'vhm_manage_channels' ),
                'manage_settings'  => current_user_can( 'vhm_manage_settings' ),
            ],
        ] );
    }

    /**
     * Determine whether the current screen is a plugin page.
     *
     * Plugin pages have a hook suffix that contains the slug prefix
     * used in AdminMenu (e.g. "toplevel_page_vhm-dashboard",
     * "hotel-manager_page_vhm-bookings", etc.).
     *
     * @param string $hook_suffix The admin page hook suffix.
     */
    private function isPluginPage( string $hook_suffix ): bool {
        // The top-level page uses "toplevel_page_vhm-dashboard".
        // Sub-pages use "hotel-manager_page_vhm-*".
        if ( str_starts_with( $hook_suffix, 'toplevel_page_vhm' ) ) {
            return true;
        }

        if ( str_contains( $hook_suffix, 'hotel-manager' ) ) {
            return true;
        }

        // Fallback: check for any "vhm-" occurrence (covers renamed parent slugs).
        if ( str_contains( $hook_suffix, 'vhm-' ) ) {
            return true;
        }

        return false;
    }
}
