<?php

namespace Nozule\Admin\Pages;

/**
 * Renders the Google Hotel Ads / Metasearch admin page.
 */
class MetasearchPage {

	/**
	 * Render the metasearch settings page.
	 */
	public function render(): void {
		if ( ! current_user_can( 'nzl_admin' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'nozule' ) );
		}

		include NZL_PLUGIN_DIR . 'templates/admin/metasearch.php';
	}
}
