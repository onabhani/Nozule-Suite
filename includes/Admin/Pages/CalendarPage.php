<?php

namespace Nozule\Admin\Pages;

/**
 * Renders the Calendar view admin page.
 */
class CalendarPage {

    /**
     * Render the calendar page.
     */
    public function render(): void {
        if ( ! current_user_can( 'nzl_staff' ) ) {
            wp_die( esc_html__( 'You do not have permission to access this page.', 'nozule' ) );
        }

        include NZL_PLUGIN_DIR . 'templates/admin/calendar.php';
    }
}
