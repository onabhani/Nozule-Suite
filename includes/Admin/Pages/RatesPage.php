<?php

namespace Nozule\Admin\Pages;

/**
 * Renders the Rates & Pricing admin page.
 */
class RatesPage {

    /**
     * Render the rates page.
     */
    public function render(): void {
        if ( ! current_user_can( 'nzl_admin' ) ) {
            wp_die( esc_html__( 'You do not have permission to access this page.', 'nozule' ) );
        }

        include NZL_PLUGIN_DIR . 'templates/admin/rates.php';
    }
}
