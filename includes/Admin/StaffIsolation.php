<?php

namespace Nozule\Admin;

/**
 * Isolates hotel-staff users from core WordPress admin.
 *
 * - Translates custom role display names (editable_roles filter).
 * - Hides all WP admin menus except the Nozule menu for nzl_* roles.
 * - Simplifies the admin bar for hotel staff.
 * - Redirects hotel staff to the plugin dashboard on login.
 */
class StaffIsolation {

    /**
     * Register all hooks.
     */
    public function register(): void {
        // Translate role names at display time.
        add_filter( 'editable_roles', [ $this, 'translateRoles' ] );

        // Only apply isolation for hotel-staff users (not administrators).
        if ( ! $this->isHotelStaff() ) {
            return;
        }

        // Strip all WP menus.
        add_action( 'admin_menu', [ $this, 'removeWordPressMenus' ], 999 );

        // Clean up the admin bar.
        add_action( 'admin_bar_menu', [ $this, 'cleanAdminBar' ], 999 );

        // Redirect to plugin dashboard on login.
        add_filter( 'login_redirect', [ $this, 'loginRedirect' ], 10, 3 );

        // Redirect away from disallowed WP pages.
        add_action( 'admin_init', [ $this, 'blockDisallowedPages' ] );

        // Hide the "Screen Options" and "Help" tabs.
        add_filter( 'screen_options_show_screen', '__return_false' );
        add_action( 'admin_head', [ $this, 'hideAdminClutter' ] );
    }

    /**
     * Translate plugin role display names in the dropdown.
     *
     * @param array $roles Editable roles array.
     * @return array
     */
    public function translateRoles( array $roles ): array {
        $translations = [
            'nzl_manager'   => __( 'Hotel Manager', 'nozule' ),
            'nzl_reception' => __( 'Hotel Reception', 'nozule' ),
        ];

        foreach ( $translations as $slug => $name ) {
            if ( isset( $roles[ $slug ] ) ) {
                $roles[ $slug ]['name'] = $name;
            }
        }

        return $roles;
    }

    /**
     * Remove all WordPress admin menus except the Nozule menu.
     */
    public function removeWordPressMenus(): void {
        global $menu;

        if ( empty( $menu ) || ! is_array( $menu ) ) {
            return;
        }

        $allowed_slugs = [
            'nzl-dashboard',     // Our top-level menu
            'profile.php',       // Allow users to edit their own profile
        ];

        foreach ( $menu as $position => $item ) {
            $slug = $item[2] ?? '';
            if ( ! in_array( $slug, $allowed_slugs, true ) ) {
                remove_menu_page( $slug );
            }
        }
    }

    /**
     * Clean up the admin bar for hotel staff.
     *
     * Remove WP logo, comments, new-content, and other non-essential items.
     *
     * @param \WP_Admin_Bar $bar Admin bar instance.
     */
    public function cleanAdminBar( $bar ): void {
        $bar->remove_node( 'wp-logo' );
        $bar->remove_node( 'comments' );
        $bar->remove_node( 'new-content' );
        $bar->remove_node( 'updates' );
        $bar->remove_node( 'search' );
        $bar->remove_node( 'customize' );
        $bar->remove_node( 'edit' );
        $bar->remove_node( 'site-name' );
    }

    /**
     * Redirect hotel staff to the Nozule dashboard after login.
     *
     * @param string   $redirect_to Default redirect URL.
     * @param string   $requested   Requested redirect URL.
     * @param \WP_User $user        Logged-in user.
     * @return string
     */
    public function loginRedirect( $redirect_to, $requested, $user ): string {
        if ( $user instanceof \WP_User && $this->userIsHotelStaff( $user ) ) {
            return admin_url( 'admin.php?page=nzl-dashboard' );
        }

        return $redirect_to;
    }

    /**
     * Redirect hotel staff away from disallowed WP admin pages.
     */
    public function blockDisallowedPages(): void {
        global $pagenow;

        // Allow AJAX and REST calls.
        if ( wp_doing_ajax() || defined( 'REST_REQUEST' ) ) {
            return;
        }

        // Allow plugin pages (admin.php?page=nzl-*).
        if ( $pagenow === 'admin.php' ) {
            $page = sanitize_text_field( $_GET['page'] ?? '' );
            if ( str_starts_with( $page, 'nzl-' ) ) {
                return;
            }
        }

        // Allow profile page.
        if ( $pagenow === 'profile.php' ) {
            return;
        }

        // Allow upload.php (media library, needed for branding uploads).
        if ( $pagenow === 'upload.php' || $pagenow === 'media-upload.php' || $pagenow === 'async-upload.php' ) {
            return;
        }

        // Block everything else — redirect to dashboard.
        wp_safe_redirect( admin_url( 'admin.php?page=nzl-dashboard' ) );
        exit;
    }

    /**
     * Hide WP admin clutter via CSS for hotel staff.
     */
    public function hideAdminClutter(): void {
        echo '<style>
            #wpfooter,
            #contextual-help-link-wrap,
            .update-nag,
            .notice:not(.nzl-notice) { display: none !important; }
        </style>';
    }

    /**
     * Check if the current user has an nzl_* role (but NOT administrator).
     */
    private function isHotelStaff(): bool {
        $user = wp_get_current_user();
        return $this->userIsHotelStaff( $user );
    }

    /**
     * Check if a specific user has an nzl_* role (but NOT administrator).
     *
     * @param \WP_User $user WordPress user.
     */
    private function userIsHotelStaff( \WP_User $user ): bool {
        if ( ! $user->exists() ) {
            return false;
        }

        $nzl_roles = [ 'nzl_manager', 'nzl_reception' ];
        return ! empty( array_intersect( $nzl_roles, $user->roles ) );
    }
}
