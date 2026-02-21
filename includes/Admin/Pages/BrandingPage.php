<?php

namespace Nozule\Admin\Pages;

/**
 * Renders the Branding admin page (NZL-041).
 */
class BrandingPage {

	public function render(): void {
		if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'nzl_admin' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'nozule' ) );
		}

		include NZL_PLUGIN_DIR . 'templates/admin/branding.php';
	}
}
