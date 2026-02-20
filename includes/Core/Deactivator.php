<?php

namespace Nozule\Core;

/**
 * Plugin deactivation handler.
 */
class Deactivator {

    /**
     * Run on plugin deactivation.
     */
    public static function deactivate(): void {
        // Clear scheduled events
        wp_clear_scheduled_hook( 'nzl_daily_maintenance' );
        wp_clear_scheduled_hook( 'nzl_send_reminders' );
        wp_clear_scheduled_hook( 'nzl_sync_channels' );
        wp_clear_scheduled_hook( 'nzl_process_review_requests' );

        // Flush rewrite rules
        flush_rewrite_rules();
    }
}
