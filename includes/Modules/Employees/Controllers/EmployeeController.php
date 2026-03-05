<?php

namespace Nozule\Modules\Employees\Controllers;

use Nozule\Core\HotelRoles;
use Nozule\Modules\Employees\Models\Employee;
use Nozule\Modules\Employees\Repositories\EmployeeRepository;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * REST controller for employee (hotel staff) management.
 *
 * Routes:
 *   GET    /nozule/v1/admin/employees              List employees
 *   POST   /nozule/v1/admin/employees              Create employee (WP user + nzl_* role)
 *   PUT    /nozule/v1/admin/employees/{id}          Update employee
 *   DELETE /nozule/v1/admin/employees/{id}          Deactivate employee
 *   GET    /nozule/v1/admin/employees/capabilities  Available capabilities list
 */
class EmployeeController {

    private const NAMESPACE = 'nozule/v1';

    /** All Nozule capabilities that can be assigned. */
    private const ALL_CAPABILITIES = [
        'nzl_admin',
        'nzl_staff',
        'nzl_manage_rooms',
        'nzl_manage_rates',
        'nzl_manage_inventory',
        'nzl_manage_bookings',
        'nzl_manage_guests',
        'nzl_view_reports',
        'nzl_view_calendar',
        'nzl_manage_channels',
        'nzl_manage_settings',
        'nzl_manage_employees',
        'nzl_manage_housekeeping',
        'nzl_manage_billing',
        'nzl_manage_pos',
        'nzl_manage_messaging',
    ];

    private EmployeeRepository $repo;

    public function __construct( EmployeeRepository $repo ) {
        $this->repo = $repo;
    }

    public function registerRoutes(): void {
        register_rest_route( self::NAMESPACE, '/admin/employees', [
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [ $this, 'list' ],
                'permission_callback' => [ $this, 'canManageEmployees' ],
            ],
            [
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => [ $this, 'create' ],
                'permission_callback' => [ $this, 'canManageEmployees' ],
            ],
        ] );

        register_rest_route( self::NAMESPACE, '/admin/employees/(?P<id>\d+)', [
            [
                'methods'             => WP_REST_Server::EDITABLE,
                'callback'            => [ $this, 'update' ],
                'permission_callback' => [ $this, 'canManageEmployees' ],
            ],
            [
                'methods'             => WP_REST_Server::DELETABLE,
                'callback'            => [ $this, 'deactivate' ],
                'permission_callback' => [ $this, 'canManageEmployees' ],
            ],
        ] );

        register_rest_route( self::NAMESPACE, '/admin/employees/capabilities', [
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [ $this, 'getCapabilities' ],
                'permission_callback' => [ $this, 'canManageEmployees' ],
            ],
        ] );
    }

    /**
     * List all active employees from nzl_employees.
     */
    public function list( WP_REST_Request $request ): WP_REST_Response {
        $employees = $this->repo->findActive();

        $search = sanitize_text_field( $request->get_param( 'search' ) ?? '' );
        if ( $search ) {
            $lower     = mb_strtolower( $search );
            $employees = array_values( array_filter(
                $employees,
                fn( Employee $e ) =>
                    str_contains( mb_strtolower( $e->display_name ?? '' ), $lower )
                    || str_contains( mb_strtolower( $e->email ?? '' ), $lower )
            ) );
        }

        $result = array_map( fn( Employee $e ) => $this->formatEmployee( $e ), $employees );

        return new WP_REST_Response( [
            'success' => true,
            'data'    => $result,
        ], 200 );
    }

    /**
     * Create a new hotel staff user.
     *
     * Inserts a WP user for authentication, then stores the canonical
     * employee record in nzl_employees with the returned wp_user_id.
     */
    public function create( WP_REST_Request $request ): WP_REST_Response {
        $username     = sanitize_user( $request->get_param( 'username' ) ?? '' );
        $email        = sanitize_email( $request->get_param( 'email' ) ?? '' );
        $display_name = sanitize_text_field( $request->get_param( 'display_name' ) ?? '' );
        $password     = $request->get_param( 'password' ) ?? '';
        $role         = sanitize_text_field( $request->get_param( 'role' ) ?? 'nzl_reception' );

        if ( empty( $username ) || empty( $email ) || empty( $password ) ) {
            return new WP_REST_Response( [
                'success' => false,
                'message' => __( 'Username, email, and password are required.', 'nozule' ),
            ], 400 );
        }

        if ( ! in_array( $role, HotelRoles::getSlugs(), true ) ) {
            return new WP_REST_Response( [
                'success' => false,
                'message' => __( 'Invalid role.', 'nozule' ),
            ], 400 );
        }

        if ( username_exists( $username ) ) {
            return new WP_REST_Response( [
                'success' => false,
                'message' => __( 'Username already exists.', 'nozule' ),
            ], 400 );
        }

        if ( email_exists( $email ) ) {
            return new WP_REST_Response( [
                'success' => false,
                'message' => __( 'Email already exists.', 'nozule' ),
            ], 400 );
        }

        // Create WP user for authentication.
        $wp_user_id = wp_insert_user( [
            'user_login'   => $username,
            'user_email'   => $email,
            'user_pass'    => $password,
            'display_name' => $display_name ?: $username,
            'role'         => $role,
        ] );

        if ( is_wp_error( $wp_user_id ) ) {
            return new WP_REST_Response( [
                'success' => false,
                'message' => $wp_user_id->get_error_message(),
            ], 400 );
        }

        // Apply WP capabilities for permission checks.
        $this->applyWpCapabilities( $wp_user_id, $request->get_param( 'capabilities' ) );

        // Resolve capabilities array for the employee row.
        $caps = $this->resolveCapabilities( $request->get_param( 'capabilities' ) );

        // Insert canonical employee record.
        $employee = $this->repo->create( [
            'wp_user_id'   => $wp_user_id,
            'email'        => $email,
            'display_name' => $display_name ?: $username,
            'phone'        => sanitize_text_field( $request->get_param( 'phone' ) ?? '' ) ?: null,
            'role'         => $role,
            'capabilities' => wp_json_encode( $caps ),
            'is_active'    => 1,
        ] );

        if ( ! $employee ) {
            wp_delete_user( $wp_user_id );
            return new WP_REST_Response( [
                'success' => false,
                'message' => __( 'Failed to create employee record.', 'nozule' ),
            ], 500 );
        }

        return new WP_REST_Response( [
            'success' => true,
            'message' => __( 'Employee created successfully.', 'nozule' ),
            'data'    => $this->formatEmployee( $employee ),
        ], 201 );
    }

    /**
     * Update an existing employee.
     *
     * Updates nzl_employees as source of truth. Propagates password and
     * email changes to the WP user so authentication keeps working.
     */
    public function update( WP_REST_Request $request ): WP_REST_Response {
        $id       = (int) $request->get_param( 'id' );
        $employee = $this->repo->find( $id );

        if ( ! $employee || ! $employee->is_active ) {
            return new WP_REST_Response( [
                'success' => false,
                'message' => __( 'Employee not found.', 'nozule' ),
            ], 404 );
        }

        // Prevent users from editing their own role or capabilities
        // (only WordPress administrators may do so).
        $is_self = ( (int) $employee->wp_user_id === get_current_user_id() );
        if ( $is_self && ! current_user_can( 'manage_options' ) ) {
            $requested_caps = $request->get_param( 'capabilities' );
            $requested_role = $request->get_param( 'role' );
            if ( $requested_caps !== null || ( $requested_role !== null && $requested_role !== '' ) ) {
                return new WP_REST_Response( [
                    'success' => false,
                    'message' => __( 'You cannot change your own role or permissions.', 'nozule' ),
                ], 403 );
            }
        }

        // Build nzl_employees update payload.
        $emp_data = [];

        $display_name = $request->get_param( 'display_name' );
        if ( $display_name !== null ) {
            $emp_data['display_name'] = sanitize_text_field( $display_name );
        }

        $email = $request->get_param( 'email' );
        if ( $email !== null ) {
            $email    = sanitize_email( $email );
            $existing = email_exists( $email );
            if ( $existing && $existing !== (int) $employee->wp_user_id ) {
                return new WP_REST_Response( [
                    'success' => false,
                    'message' => __( 'Email already exists.', 'nozule' ),
                ], 400 );
            }
            $emp_data['email'] = $email;
        }

        $phone = $request->get_param( 'phone' );
        if ( $phone !== null ) {
            $emp_data['phone'] = sanitize_text_field( $phone ) ?: null;
        }

        // Role change.
        $role = sanitize_text_field( $request->get_param( 'role' ) ?? '' );
        if ( $role && ! ( $is_self && ! current_user_can( 'manage_options' ) ) ) {
            if ( ! in_array( $role, HotelRoles::getSlugs(), true ) ) {
                return new WP_REST_Response( [
                    'success' => false,
                    'message' => __( 'Invalid role.', 'nozule' ),
                ], 400 );
            }
            $emp_data['role'] = $role;
        }

        // Capabilities.
        if ( ! ( $is_self && ! current_user_can( 'manage_options' ) ) ) {
            $raw_caps = $request->get_param( 'capabilities' );
            if ( is_array( $raw_caps ) ) {
                $emp_data['capabilities'] = wp_json_encode( $this->resolveCapabilities( $raw_caps ) );
            }
        }

        // Propagate password / email / display_name changes to WP user first,
        // so nzl_employees only updates if the WP side succeeds.
        $wp_update = [ 'ID' => (int) $employee->wp_user_id ];
        $needs_wp  = false;

        if ( isset( $emp_data['email'] ) ) {
            $wp_update['user_email'] = $emp_data['email'];
            $needs_wp = true;
        }

        $password = $request->get_param( 'password' );
        if ( ! empty( $password ) ) {
            $wp_update['user_pass'] = $password;
            $needs_wp = true;
        }

        if ( isset( $emp_data['display_name'] ) ) {
            $wp_update['display_name'] = $emp_data['display_name'];
            $needs_wp = true;
        }

        if ( $needs_wp ) {
            $result = wp_update_user( $wp_update );
            if ( is_wp_error( $result ) ) {
                return new WP_REST_Response( [
                    'success' => false,
                    'message' => $result->get_error_message(),
                ], 400 );
            }
        }

        // Update nzl_employees row (after WP user update succeeded).
        if ( ! empty( $emp_data ) ) {
            $this->repo->update( $id, $emp_data );
        }

        // Sync WP role + capabilities so auth checks keep working.
        if ( $employee->wp_user_id ) {
            $wp_user = get_userdata( (int) $employee->wp_user_id );
            if ( $wp_user ) {
                if ( isset( $emp_data['role'] ) ) {
                    $wp_user->set_role( $emp_data['role'] );
                }
                if ( ! ( $is_self && ! current_user_can( 'manage_options' ) ) ) {
                    $this->applyWpCapabilities( (int) $employee->wp_user_id, $request->get_param( 'capabilities' ) );
                }
            }
        }

        $updated = $this->repo->find( $id );

        if ( ! $updated ) {
            return new WP_REST_Response( [
                'success' => false,
                'message' => __( 'Employee not found after update.', 'nozule' ),
            ], 404 );
        }

        return new WP_REST_Response( [
            'success' => true,
            'message' => __( 'Employee updated successfully.', 'nozule' ),
            'data'    => $this->formatEmployee( $updated ),
        ], 200 );
    }

    /**
     * Deactivate an employee.
     *
     * Soft-deletes the nzl_employees row (is_active = 0) and strips the
     * WP user's nzl_* role so they can no longer log in as staff.
     */
    public function deactivate( WP_REST_Request $request ): WP_REST_Response {
        $id       = (int) $request->get_param( 'id' );
        $employee = $this->repo->find( $id );

        if ( ! $employee || ! $employee->is_active ) {
            return new WP_REST_Response( [
                'success' => false,
                'message' => __( 'Employee not found.', 'nozule' ),
            ], 404 );
        }

        // Don't allow deactivating yourself.
        if ( (int) $employee->wp_user_id === get_current_user_id() ) {
            return new WP_REST_Response( [
                'success' => false,
                'message' => __( 'You cannot deactivate yourself.', 'nozule' ),
            ], 400 );
        }

        // Soft-delete in nzl_employees.
        $this->repo->update( $id, [ 'is_active' => 0 ] );

        // Strip nzl capabilities and demote WP user to subscriber.
        if ( $employee->wp_user_id ) {
            $wp_user = get_userdata( (int) $employee->wp_user_id );
            if ( $wp_user ) {
                foreach ( self::ALL_CAPABILITIES as $cap ) {
                    $wp_user->remove_cap( $cap );
                }
                $wp_user->set_role( 'subscriber' );
            }
        }

        return new WP_REST_Response( [
            'success' => true,
            'message' => __( 'Employee deactivated.', 'nozule' ),
        ], 200 );
    }

    /**
     * Return the list of assignable capabilities with labels.
     */
    public function getCapabilities( WP_REST_Request $request ): WP_REST_Response {
        $caps = [
            [ 'key' => 'nzl_admin',               'label' => 'Admin Access',          'label_ar' => 'صلاحية المدير' ],
            [ 'key' => 'nzl_staff',               'label' => 'Staff Access',          'label_ar' => 'صلاحية الموظف' ],
            [ 'key' => 'nzl_manage_rooms',        'label' => 'Manage Rooms',          'label_ar' => 'إدارة الغرف' ],
            [ 'key' => 'nzl_manage_rates',        'label' => 'Manage Rates',          'label_ar' => 'إدارة الأسعار' ],
            [ 'key' => 'nzl_manage_inventory',    'label' => 'Manage Inventory',      'label_ar' => 'إدارة المخزون' ],
            [ 'key' => 'nzl_manage_bookings',     'label' => 'Manage Bookings',       'label_ar' => 'إدارة الحجوزات' ],
            [ 'key' => 'nzl_manage_guests',       'label' => 'Manage Guests',         'label_ar' => 'إدارة الضيوف' ],
            [ 'key' => 'nzl_view_reports',        'label' => 'View Reports',          'label_ar' => 'عرض التقارير' ],
            [ 'key' => 'nzl_view_calendar',       'label' => 'View Calendar',         'label_ar' => 'عرض التقويم' ],
            [ 'key' => 'nzl_manage_channels',     'label' => 'Manage Channels',       'label_ar' => 'إدارة القنوات' ],
            [ 'key' => 'nzl_manage_settings',     'label' => 'Manage Settings',       'label_ar' => 'إدارة الإعدادات' ],
            [ 'key' => 'nzl_manage_employees',    'label' => 'Manage Employees',      'label_ar' => 'إدارة الموظفين' ],
            [ 'key' => 'nzl_manage_housekeeping', 'label' => 'Manage Housekeeping',   'label_ar' => 'إدارة التدبير المنزلي' ],
            [ 'key' => 'nzl_manage_billing',      'label' => 'Manage Billing',        'label_ar' => 'إدارة الفواتير' ],
            [ 'key' => 'nzl_manage_pos',          'label' => 'Manage POS',            'label_ar' => 'إدارة نقاط البيع' ],
            [ 'key' => 'nzl_manage_messaging',    'label' => 'Manage Messaging',      'label_ar' => 'إدارة الرسائل' ],
        ];

        return new WP_REST_Response( [
            'success' => true,
            'data'    => $caps,
        ], 200 );
    }

    /**
     * Permission callback.
     */
    public function canManageEmployees( WP_REST_Request $request ): bool {
        return current_user_can( 'manage_options' ) || current_user_can( 'nzl_manage_employees' );
    }

    /**
     * Format an Employee model for the API response.
     */
    private function formatEmployee( Employee $employee ): array {
        $caps = is_array( $employee->capabilities ) ? $employee->capabilities : [];

        return [
            'id'           => (int) $employee->id,
            'wp_user_id'   => $employee->wp_user_id ? (int) $employee->wp_user_id : null,
            'email'        => $employee->email,
            'display_name' => $employee->display_name,
            'phone'        => $employee->phone,
            'role'         => $employee->role,
            'capabilities' => $caps,
            'is_active'    => (bool) $employee->is_active,
            'created_at'   => $employee->created_at,
        ];
    }

    /**
     * Apply capabilities to the WP user so permission checks keep working.
     *
     * @param int        $wp_user_id  WordPress user ID.
     * @param array|null $capabilities Array of capability keys to grant.
     */
    private function applyWpCapabilities( int $wp_user_id, $capabilities ): void {
        if ( ! is_array( $capabilities ) ) {
            return;
        }

        $user = get_userdata( $wp_user_id );
        if ( ! $user ) {
            return;
        }

        foreach ( self::ALL_CAPABILITIES as $cap ) {
            if ( in_array( $cap, $capabilities, true ) ) {
                $user->add_cap( $cap );
            } else {
                $user->remove_cap( $cap );
            }
        }
    }

    /**
     * Resolve a raw capabilities param into a filtered array of valid keys.
     *
     * @param mixed $capabilities Raw capabilities from request.
     * @return string[]
     */
    private function resolveCapabilities( $capabilities ): array {
        if ( ! is_array( $capabilities ) ) {
            return [];
        }

        return array_values( array_intersect( $capabilities, self::ALL_CAPABILITIES ) );
    }
}
