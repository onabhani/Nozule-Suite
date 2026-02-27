<?php

namespace Nozule\Modules\Employees\Controllers;

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

    /** Available hotel roles. */
    private const HOTEL_ROLES = [
        'nzl_manager',
        'nzl_reception',
        'nzl_housekeeper',
        'nzl_finance',
        'nzl_concierge',
    ];

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
     * List all users with nzl_* roles.
     *
     * Uses an explicit meta_query on the capabilities key to ensure
     * custom roles are found regardless of WordPress role registration state.
     */
    public function list( WP_REST_Request $request ): WP_REST_Response {
        global $wpdb;

        $cap_key = $wpdb->prefix . 'capabilities';

        $role_clauses = [];
        foreach ( self::HOTEL_ROLES as $role ) {
            $role_clauses[] = [
                'key'     => $cap_key,
                'value'   => '"' . $role . '"',
                'compare' => 'LIKE',
            ];
        }

        $args = [
            'meta_query' => array_merge( [ 'relation' => 'OR' ], $role_clauses ),
            'orderby'    => 'display_name',
            'order'      => 'ASC',
        ];

        $search = sanitize_text_field( $request->get_param( 'search' ) ?? '' );
        if ( $search ) {
            $args['search']         = '*' . $search . '*';
            $args['search_columns'] = [ 'user_login', 'user_email', 'display_name' ];
        }

        $users  = get_users( $args );
        $result = [];

        foreach ( $users as $user ) {
            $result[] = $this->formatUser( $user );
        }

        return new WP_REST_Response( [
            'success' => true,
            'data'    => $result,
        ], 200 );
    }

    /**
     * Create a new hotel staff user.
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

        if ( ! in_array( $role, self::HOTEL_ROLES, true ) ) {
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

        $user_id = wp_insert_user( [
            'user_login'   => $username,
            'user_email'   => $email,
            'user_pass'    => $password,
            'display_name' => $display_name ?: $username,
            'role'         => $role,
        ] );

        if ( is_wp_error( $user_id ) ) {
            return new WP_REST_Response( [
                'success' => false,
                'message' => $user_id->get_error_message(),
            ], 400 );
        }

        // Apply custom capabilities if provided.
        $this->applyCapabilities( $user_id, $request->get_param( 'capabilities' ) );

        $user = get_userdata( $user_id );

        return new WP_REST_Response( [
            'success' => true,
            'message' => __( 'Employee created successfully.', 'nozule' ),
            'data'    => $this->formatUser( $user ),
        ], 201 );
    }

    /**
     * Update an existing employee.
     */
    public function update( WP_REST_Request $request ): WP_REST_Response {
        $id   = (int) $request->get_param( 'id' );
        $user = get_userdata( $id );

        if ( ! $user || empty( array_intersect( self::HOTEL_ROLES, $user->roles ) ) ) {
            return new WP_REST_Response( [
                'success' => false,
                'message' => __( 'Employee not found.', 'nozule' ),
            ], 404 );
        }

        // Prevent users from editing their own role or capabilities
        // (only WordPress administrators may do so).
        $is_self = ( $id === get_current_user_id() );
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

        $update_data = [ 'ID' => $id ];

        $display_name = $request->get_param( 'display_name' );
        if ( $display_name !== null ) {
            $update_data['display_name'] = sanitize_text_field( $display_name );
        }

        $email = $request->get_param( 'email' );
        if ( $email !== null ) {
            $email = sanitize_email( $email );
            $existing = email_exists( $email );
            if ( $existing && $existing !== $id ) {
                return new WP_REST_Response( [
                    'success' => false,
                    'message' => __( 'Email already exists.', 'nozule' ),
                ], 400 );
            }
            $update_data['user_email'] = $email;
        }

        $password = $request->get_param( 'password' );
        if ( ! empty( $password ) ) {
            $update_data['user_pass'] = $password;
        }

        $result = wp_update_user( $update_data );
        if ( is_wp_error( $result ) ) {
            return new WP_REST_Response( [
                'success' => false,
                'message' => $result->get_error_message(),
            ], 400 );
        }

        // Update role if changed (skip if editing self — already blocked above).
        $role = sanitize_text_field( $request->get_param( 'role' ) ?? '' );
        if ( $role && ! $is_self ) {
            if ( ! in_array( $role, self::HOTEL_ROLES, true ) ) {
                return new WP_REST_Response( [
                    'success' => false,
                    'message' => __( 'Invalid role.', 'nozule' ),
                ], 400 );
            }
            $user->set_role( $role );
        }

        // Apply custom capabilities (skip if editing self — already blocked above).
        if ( ! $is_self ) {
            $this->applyCapabilities( $id, $request->get_param( 'capabilities' ) );
        }

        $user = get_userdata( $id );

        return new WP_REST_Response( [
            'success' => true,
            'message' => __( 'Employee updated successfully.', 'nozule' ),
            'data'    => $this->formatUser( $user ),
        ], 200 );
    }

    /**
     * Deactivate an employee (set role to subscriber — effectively locked out).
     */
    public function deactivate( WP_REST_Request $request ): WP_REST_Response {
        $id   = (int) $request->get_param( 'id' );
        $user = get_userdata( $id );

        if ( ! $user || empty( array_intersect( self::HOTEL_ROLES, $user->roles ) ) ) {
            return new WP_REST_Response( [
                'success' => false,
                'message' => __( 'Employee not found.', 'nozule' ),
            ], 404 );
        }

        // Don't allow deactivating yourself.
        if ( $id === get_current_user_id() ) {
            return new WP_REST_Response( [
                'success' => false,
                'message' => __( 'You cannot deactivate yourself.', 'nozule' ),
            ], 400 );
        }

        // Strip nzl capabilities and set to subscriber.
        foreach ( self::ALL_CAPABILITIES as $cap ) {
            $user->remove_cap( $cap );
        }
        $user->set_role( 'subscriber' );

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
     * Format a WP_User for the API response.
     */
    private function formatUser( \WP_User $user ): array {
        $nzl_caps = [];
        foreach ( self::ALL_CAPABILITIES as $cap ) {
            if ( $user->has_cap( $cap ) ) {
                $nzl_caps[] = $cap;
            }
        }

        // Determine the hotel role. WordPress $user->roles may be empty if
        // the custom role is not registered via add_role() yet, so fall back
        // to checking the raw capabilities meta for an nzl_* role key.
        $role = '';
        foreach ( $user->roles as $r ) {
            if ( in_array( $r, self::HOTEL_ROLES, true ) ) {
                $role = $r;
                break;
            }
        }
        if ( ! $role ) {
            // Fallback: inspect the raw caps array stored in user meta.
            $raw_caps = get_user_meta( $user->ID, $GLOBALS['wpdb']->prefix . 'capabilities', true );
            if ( is_array( $raw_caps ) ) {
                foreach ( self::HOTEL_ROLES as $hr ) {
                    if ( ! empty( $raw_caps[ $hr ] ) ) {
                        $role = $hr;
                        break;
                    }
                }
            }
        }

        return [
            'id'           => $user->ID,
            'username'     => $user->user_login,
            'email'        => $user->user_email,
            'display_name' => $user->display_name,
            'role'         => $role,
            'capabilities' => $nzl_caps,
            'registered'   => $user->user_registered,
        ];
    }

    /**
     * Apply a list of capabilities to a user.
     *
     * @param int        $user_id      WordPress user ID.
     * @param array|null $capabilities Array of capability keys to grant.
     */
    private function applyCapabilities( int $user_id, $capabilities ): void {
        if ( ! is_array( $capabilities ) ) {
            return;
        }

        $user = get_userdata( $user_id );
        if ( ! $user ) {
            return;
        }

        // Set each capability: grant if in the list, remove if not.
        foreach ( self::ALL_CAPABILITIES as $cap ) {
            if ( in_array( $cap, $capabilities, true ) ) {
                $user->add_cap( $cap );
            } else {
                $user->remove_cap( $cap );
            }
        }
    }
}
