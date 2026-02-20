<?php

namespace Nozule\Admin\Pages;

/**
 * Renders the Dynamic Pricing admin page.
 */
class DynamicPricingPage {

	/**
	 * Render the dynamic pricing page.
	 */
	public function render(): void {
		if ( ! current_user_can( 'nzl_admin' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'nozule' ) );
		}

		include NZL_PLUGIN_DIR . 'templates/admin/dynamic-pricing.php';
	}
}
