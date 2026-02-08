<?php

namespace Venezia\Core;

/**
 * Plugin deactivation handler.
 */
class Deactivator {

    /**
     * Run on plugin deactivation.
     */
    public static function deactivate(): void {
        // Clear scheduled events
        wp_clear_scheduled_hook( 'vhm_daily_maintenance' );
        wp_clear_scheduled_hook( 'vhm_send_reminders' );
        wp_clear_scheduled_hook( 'vhm_sync_channels' );

        // Flush rewrite rules
        flush_rewrite_rules();
    }
}
