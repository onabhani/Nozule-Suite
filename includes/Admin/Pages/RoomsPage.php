<?php

namespace Venezia\Admin\Pages;

/**
 * Renders the Rooms management admin page.
 */
class RoomsPage {

    /**
     * Render the rooms page.
     */
    public function render(): void {
        if ( ! current_user_can( 'vhm_admin' ) ) {
            wp_die( esc_html__( 'You do not have permission to access this page.', 'venezia-hotel' ) );
        }

        include VHM_PLUGIN_DIR . 'templates/admin/rooms.php';
    }
}
