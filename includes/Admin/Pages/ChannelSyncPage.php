<?php

namespace Nozule\Admin\Pages;

/**
 * Renders the Channel Sync admin page.
 */
class ChannelSyncPage {

	public function render(): void {
		if ( ! current_user_can( 'nzl_admin' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'nozule' ) );
		}

		include NZL_PLUGIN_DIR . 'templates/admin/channel-sync.php';
	}
}
