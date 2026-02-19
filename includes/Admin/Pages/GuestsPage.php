<?php

namespace Nozule\Admin\Pages;

/**
 * Renders the Guests management admin page.
 */
class GuestsPage {

    /**
     * Render the guests page.
     */
    public function render(): void {
        if ( ! current_user_can( 'nzl_staff' ) ) {
            wp_die( esc_html__( 'You do not have permission to access this page.', 'nozule' ) );
        }

        include NZL_PLUGIN_DIR . 'templates/admin/guests.php';
    }
}
