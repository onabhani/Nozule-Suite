<?php

namespace Venezia\Admin\Pages;

/**
 * Renders the Hotel Manager Dashboard admin page.
 */
class DashboardPage {

    /**
     * Render the dashboard page.
     */
    public function render(): void {
        if ( ! current_user_can( 'vhm_staff' ) ) {
            wp_die( esc_html__( 'You do not have permission to access this page.', 'venezia-hotel' ) );
        }

        include VHM_PLUGIN_DIR . 'templates/admin/dashboard.php';
    }
}
