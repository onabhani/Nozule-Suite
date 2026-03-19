<?php

namespace Nozule\Core;

/**
 * Centralized permission checks for REST API routes.
 *
 * Replaces the identical checkAdminPermission() / checkStaffPermission()
 * methods that were duplicated across 37 controllers.
 */
class PermissionHelper {

    /**
     * Check if the current user has admin-level access.
     *
     * Equivalent to `manage_options || nzl_admin`.
     */
    public static function isAdmin(): bool {
        return current_user_can( 'manage_options' ) || current_user_can( 'nzl_admin' );
    }

    /**
     * Check if the current user has staff-level access.
     *
     * Equivalent to `manage_options || nzl_admin || nzl_staff`.
     */
    public static function isStaff(): bool {
        return current_user_can( 'manage_options' ) || current_user_can( 'nzl_admin' ) || current_user_can( 'nzl_staff' );
    }

    /**
     * Check if the current user is a super admin (multi-property).
     */
    public static function isSuperAdmin(): bool {
        return current_user_can( 'nzl_super_admin' );
    }
}
