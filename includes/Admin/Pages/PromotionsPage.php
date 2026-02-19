<?php

namespace Nozule\Admin\Pages;

/**
 * Renders the Promotions admin page.
 */
class PromotionsPage {

	public function render(): void {
		if ( ! current_user_can( 'nzl_staff' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'nozule' ) );
		}

		include NZL_PLUGIN_DIR . 'templates/admin/promotions.php';
	}
}
