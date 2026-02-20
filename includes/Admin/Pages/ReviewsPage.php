<?php

namespace Nozule\Admin\Pages;

/**
 * Renders the Reviews & Reputation admin page.
 */
class ReviewsPage {

	public function render(): void {
		if ( ! current_user_can( 'nzl_admin' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'nozule' ) );
		}

		include NZL_PLUGIN_DIR . 'templates/admin/reviews.php';
	}
}
