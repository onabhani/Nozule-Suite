<?php

namespace Nozule\Admin\Pages;

/**
 * Admin page: Employees (NZL-042).
 */
class EmployeesPage {

    public function render(): void {
        if ( ! current_user_can( 'nzl_manage_employees' ) && ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You do not have permission to access this page.', 'nozule' ) );
        }

        include NZL_PLUGIN_DIR . 'templates/admin/employees.php';
    }
}
