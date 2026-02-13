<?php

namespace Nozule\API;

use Nozule\Core\Container;
use Nozule\Modules\Rooms\Controllers\RoomTypeController;
use Nozule\Modules\Rooms\Controllers\RoomController;
use Nozule\Modules\Rooms\Controllers\InventoryController;
use Nozule\Modules\Bookings\Controllers\AvailabilityController;
use Nozule\Modules\Bookings\Controllers\BookingController;
use Nozule\Modules\Bookings\Controllers\AdminBookingController;
use Nozule\Modules\Bookings\Controllers\DashboardController;
use Nozule\Modules\Bookings\Controllers\CalendarController;
use Nozule\Modules\Guests\Controllers\GuestController;
use Nozule\Modules\Pricing\Controllers\RatePlanController;
use Nozule\Modules\Pricing\Controllers\SeasonalRateController;
use Nozule\Modules\Settings\Controllers\SettingsController;
use Nozule\Modules\Reports\Controllers\ReportController;
use Nozule\Modules\Channels\Controllers\ChannelController;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

/**
 * Main REST route registrar.
 *
 * Registers all module routes under the nozule/v1 namespace and delegates
 * request handling to the appropriate module controller resolved from the
 * dependency-injection container.
 */
class RestController {

    private const NAMESPACE = 'nozule/v1';

    private Container $container;

    public function __construct( Container $container ) {
        $this->container = $container;
    }

    /**
     * Register all REST API routes.
     */
    public function registerRoutes(): void {
        $this->registerPublicRoutes();
        $this->registerStaffRoutes();
        $this->registerAdminRoutes();
    }

    // ------------------------------------------------------------------
    // Public endpoints (no authentication required)
    // ------------------------------------------------------------------

    private function registerPublicRoutes(): void {

        // GET /room-types
        register_rest_route( self::NAMESPACE, '/room-types', [
            'methods'             => 'GET',
            'callback'            => fn( WP_REST_Request $r ) => $this->container->get( RoomTypeController::class )->index( $r ),
            'permission_callback' => '__return_true',
        ] );

        // GET /room-types/<id>
        register_rest_route( self::NAMESPACE, '/room-types/(?P<id>\d+)', [
            'methods'             => 'GET',
            'callback'            => fn( WP_REST_Request $r ) => $this->container->get( RoomTypeController::class )->show( $r ),
            'permission_callback' => '__return_true',
            'args'                => [
                'id' => [
                    'validate_callback' => fn( $v ) => is_numeric( $v ) && (int) $v > 0,
                    'sanitize_callback' => 'absint',
                ],
            ],
        ] );

        // GET /availability
        register_rest_route( self::NAMESPACE, '/availability', [
            'methods'             => 'GET',
            'callback'            => fn( WP_REST_Request $r ) => $this->container->get( AvailabilityController::class )->check( $r ),
            'permission_callback' => '__return_true',
        ] );

        // POST /bookings
        register_rest_route( self::NAMESPACE, '/bookings', [
            'methods'             => 'POST',
            'callback'            => fn( WP_REST_Request $r ) => $this->container->get( BookingController::class )->store( $r ),
            'permission_callback' => '__return_true',
        ] );

        // GET /bookings/<booking_number>  (public look-up, requires email param)
        register_rest_route( self::NAMESPACE, '/bookings/(?P<booking_number>[A-Z0-9-]+)', [
            'methods'             => 'GET',
            'callback'            => fn( WP_REST_Request $r ) => $this->container->get( BookingController::class )->show( $r ),
            'permission_callback' => '__return_true',
            'args'                => [
                'booking_number' => [
                    'validate_callback' => fn( $v ) => (bool) preg_match( '/^[A-Z0-9-]+$/', $v ),
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'email' => [
                    'required'          => true,
                    'validate_callback' => fn( $v ) => is_email( $v ),
                    'sanitize_callback' => 'sanitize_email',
                ],
            ],
        ] );

        // POST /bookings/<booking_number>/cancel
        register_rest_route( self::NAMESPACE, '/bookings/(?P<booking_number>[A-Z0-9-]+)/cancel', [
            'methods'             => 'POST',
            'callback'            => fn( WP_REST_Request $r ) => $this->container->get( BookingController::class )->cancel( $r ),
            'permission_callback' => '__return_true',
            'args'                => [
                'booking_number' => [
                    'validate_callback' => fn( $v ) => (bool) preg_match( '/^[A-Z0-9-]+$/', $v ),
                    'sanitize_callback' => 'sanitize_text_field',
                ],
            ],
        ] );

        // GET /settings/public
        register_rest_route( self::NAMESPACE, '/settings/public', [
            'methods'             => 'GET',
            'callback'            => fn( WP_REST_Request $r ) => $this->container->get( SettingsController::class )->publicSettings( $r ),
            'permission_callback' => '__return_true',
        ] );
    }

    // ------------------------------------------------------------------
    // Staff endpoints (require nzl_staff capability)
    // ------------------------------------------------------------------

    private function registerStaffRoutes(): void {

        $staff_permission = fn() => current_user_can( 'manage_options' ) || current_user_can( 'nzl_staff' );

        // --- Bookings management ---

        // GET  /admin/bookings  (list)
        // POST /admin/bookings  (create)
        register_rest_route( self::NAMESPACE, '/admin/bookings', [
            [
                'methods'             => 'GET',
                'callback'            => fn( WP_REST_Request $r ) => $this->container->get( AdminBookingController::class )->index( $r ),
                'permission_callback' => $staff_permission,
            ],
            [
                'methods'             => 'POST',
                'callback'            => fn( WP_REST_Request $r ) => $this->container->get( AdminBookingController::class )->store( $r ),
                'permission_callback' => $staff_permission,
            ],
        ] );

        // GET /admin/bookings/<id>
        // PUT /admin/bookings/<id>
        register_rest_route( self::NAMESPACE, '/admin/bookings/(?P<id>\d+)', [
            [
                'methods'             => 'GET',
                'callback'            => fn( WP_REST_Request $r ) => $this->container->get( AdminBookingController::class )->show( $r ),
                'permission_callback' => $staff_permission,
            ],
            [
                'methods'             => 'PUT',
                'callback'            => fn( WP_REST_Request $r ) => $this->container->get( AdminBookingController::class )->update( $r ),
                'permission_callback' => $staff_permission,
            ],
            'args' => [
                'id' => [
                    'validate_callback' => fn( $v ) => is_numeric( $v ) && (int) $v > 0,
                    'sanitize_callback' => 'absint',
                ],
            ],
        ] );

        // Booking action shortcuts
        $booking_actions = [ 'confirm', 'cancel', 'check-in', 'check-out', 'assign-room', 'payments' ];

        foreach ( $booking_actions as $action ) {
            $method = str_replace( '-', '', lcfirst( ucwords( $action, '-' ) ) );

            register_rest_route( self::NAMESPACE, '/admin/bookings/(?P<id>\d+)/' . $action, [
                'methods'             => 'POST',
                'callback'            => fn( WP_REST_Request $r ) => $this->container->get( AdminBookingController::class )->{$method}( $r ),
                'permission_callback' => $staff_permission,
                'args'                => [
                    'id' => [
                        'validate_callback' => fn( $v ) => is_numeric( $v ) && (int) $v > 0,
                        'sanitize_callback' => 'absint',
                    ],
                ],
            ] );
        }

        // GET /admin/bookings/<id>/logs
        register_rest_route( self::NAMESPACE, '/admin/bookings/(?P<id>\d+)/logs', [
            'methods'             => 'GET',
            'callback'            => fn( WP_REST_Request $r ) => $this->container->get( AdminBookingController::class )->logs( $r ),
            'permission_callback' => $staff_permission,
            'args'                => [
                'id' => [
                    'validate_callback' => fn( $v ) => is_numeric( $v ) && (int) $v > 0,
                    'sanitize_callback' => 'absint',
                ],
            ],
        ] );

        // --- Dashboard ---

        $dashboard_endpoints = [ 'stats', 'arrivals', 'departures', 'in-house' ];

        foreach ( $dashboard_endpoints as $endpoint ) {
            $method = str_replace( '-', '', lcfirst( ucwords( $endpoint, '-' ) ) );

            register_rest_route( self::NAMESPACE, '/admin/dashboard/' . $endpoint, [
                'methods'             => 'GET',
                'callback'            => fn( WP_REST_Request $r ) => $this->container->get( DashboardController::class )->{$method}( $r ),
                'permission_callback' => $staff_permission,
            ] );
        }

        // --- Calendar ---

        register_rest_route( self::NAMESPACE, '/admin/calendar', [
            'methods'             => 'GET',
            'callback'            => fn( WP_REST_Request $r ) => $this->container->get( CalendarController::class )->index( $r ),
            'permission_callback' => $staff_permission,
        ] );

        // --- Guests ---

        register_rest_route( self::NAMESPACE, '/admin/guests', [
            'methods'             => 'GET',
            'callback'            => fn( WP_REST_Request $r ) => $this->container->get( GuestController::class )->index( $r ),
            'permission_callback' => $staff_permission,
        ] );

        register_rest_route( self::NAMESPACE, '/admin/guests/(?P<id>\d+)', [
            'methods'             => 'GET',
            'callback'            => fn( WP_REST_Request $r ) => $this->container->get( GuestController::class )->show( $r ),
            'permission_callback' => $staff_permission,
            'args'                => [
                'id' => [
                    'validate_callback' => fn( $v ) => is_numeric( $v ) && (int) $v > 0,
                    'sanitize_callback' => 'absint',
                ],
            ],
        ] );
    }

    // ------------------------------------------------------------------
    // Admin endpoints (require nzl_admin capability)
    // ------------------------------------------------------------------

    private function registerAdminRoutes(): void {

        $admin_permission = fn() => current_user_can( 'manage_options' );

        // --- Room Types CRUD ---
        $this->registerCrudRoutes( 'admin/room-types', RoomTypeController::class, $admin_permission );

        // --- Rooms CRUD ---
        $this->registerCrudRoutes( 'admin/rooms', RoomController::class, $admin_permission );

        // --- Inventory ---
        // NOTE: InventoryController registers its own specialised routes
        // (with optional room_type_id, bulk-update, initialize) so we do
        // NOT register generic CRUD routes here to avoid conflicts.

        // --- Rate Plans CRUD ---
        $this->registerCrudRoutes( 'admin/rate-plans', RatePlanController::class, $admin_permission );

        // --- Seasonal Rates CRUD ---
        $this->registerCrudRoutes( 'admin/seasonal-rates', SeasonalRateController::class, $admin_permission );

        // --- Settings ---

        register_rest_route( self::NAMESPACE, '/admin/settings', [
            [
                'methods'             => 'GET',
                'callback'            => fn( WP_REST_Request $r ) => $this->container->get( SettingsController::class )->index( $r ),
                'permission_callback' => $admin_permission,
            ],
            [
                'methods'             => 'PUT',
                'callback'            => fn( WP_REST_Request $r ) => $this->container->get( SettingsController::class )->update( $r ),
                'permission_callback' => $admin_permission,
            ],
        ] );

        // --- Reports ---

        register_rest_route( self::NAMESPACE, '/admin/reports', [
            'methods'             => 'GET',
            'callback'            => fn( WP_REST_Request $r ) => $this->container->get( ReportController::class )->index( $r ),
            'permission_callback' => $admin_permission,
        ] );

        register_rest_route( self::NAMESPACE, '/admin/reports/revenue', [
            'methods'             => 'GET',
            'callback'            => fn( WP_REST_Request $r ) => $this->container->get( ReportController::class )->revenue( $r ),
            'permission_callback' => $admin_permission,
        ] );

        register_rest_route( self::NAMESPACE, '/admin/reports/occupancy', [
            'methods'             => 'GET',
            'callback'            => fn( WP_REST_Request $r ) => $this->container->get( ReportController::class )->occupancy( $r ),
            'permission_callback' => $admin_permission,
        ] );

        register_rest_route( self::NAMESPACE, '/admin/reports/sources', [
            'methods'             => 'GET',
            'callback'            => fn( WP_REST_Request $r ) => $this->container->get( ReportController::class )->sources( $r ),
            'permission_callback' => $admin_permission,
        ] );

        register_rest_route( self::NAMESPACE, '/admin/reports/export', [
            'methods'             => 'GET',
            'callback'            => fn( WP_REST_Request $r ) => $this->container->get( ReportController::class )->export( $r ),
            'permission_callback' => $admin_permission,
        ] );

        // --- Channels ---

        register_rest_route( self::NAMESPACE, '/admin/channels', [
            [
                'methods'             => 'GET',
                'callback'            => fn( WP_REST_Request $r ) => $this->container->get( ChannelController::class )->index( $r ),
                'permission_callback' => $admin_permission,
            ],
            [
                'methods'             => 'POST',
                'callback'            => fn( WP_REST_Request $r ) => $this->container->get( ChannelController::class )->store( $r ),
                'permission_callback' => $admin_permission,
            ],
        ] );

        register_rest_route( self::NAMESPACE, '/admin/channels/(?P<id>\d+)', [
            [
                'methods'             => 'GET',
                'callback'            => fn( WP_REST_Request $r ) => $this->container->get( ChannelController::class )->show( $r ),
                'permission_callback' => $admin_permission,
            ],
            [
                'methods'             => 'PUT',
                'callback'            => fn( WP_REST_Request $r ) => $this->container->get( ChannelController::class )->update( $r ),
                'permission_callback' => $admin_permission,
            ],
            [
                'methods'             => 'DELETE',
                'callback'            => fn( WP_REST_Request $r ) => $this->container->get( ChannelController::class )->destroy( $r ),
                'permission_callback' => $admin_permission,
            ],
            'args' => [
                'id' => [
                    'validate_callback' => fn( $v ) => is_numeric( $v ) && (int) $v > 0,
                    'sanitize_callback' => 'absint',
                ],
            ],
        ] );

        register_rest_route( self::NAMESPACE, '/admin/channels/(?P<id>\d+)/sync', [
            'methods'             => 'POST',
            'callback'            => fn( WP_REST_Request $r ) => $this->container->get( ChannelController::class )->sync( $r ),
            'permission_callback' => $admin_permission,
            'args'                => [
                'id' => [
                    'validate_callback' => fn( $v ) => is_numeric( $v ) && (int) $v > 0,
                    'sanitize_callback' => 'absint',
                ],
            ],
        ] );
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    /**
     * Register standard CRUD routes for a resource.
     *
     * Generates:
     *   GET    /{base}          → index
     *   POST   /{base}          → store
     *   GET    /{base}/{id}     → show
     *   PUT    /{base}/{id}     → update
     *   DELETE /{base}/{id}     → destroy
     *
     * @param string   $base             Route base without leading slash.
     * @param string   $controllerClass  Fully-qualified controller class name.
     * @param callable $permission       Permission callback.
     */
    private function registerCrudRoutes( string $base, string $controllerClass, callable $permission ): void {

        // Collection routes
        register_rest_route( self::NAMESPACE, '/' . $base, [
            [
                'methods'             => 'GET',
                'callback'            => fn( WP_REST_Request $r ) => $this->container->get( $controllerClass )->index( $r ),
                'permission_callback' => $permission,
            ],
            [
                'methods'             => 'POST',
                'callback'            => fn( WP_REST_Request $r ) => $this->container->get( $controllerClass )->store( $r ),
                'permission_callback' => $permission,
            ],
        ] );

        // Single-resource routes
        register_rest_route( self::NAMESPACE, '/' . $base . '/(?P<id>\d+)', [
            [
                'methods'             => 'GET',
                'callback'            => fn( WP_REST_Request $r ) => $this->container->get( $controllerClass )->show( $r ),
                'permission_callback' => $permission,
            ],
            [
                'methods'             => 'PUT',
                'callback'            => fn( WP_REST_Request $r ) => $this->container->get( $controllerClass )->update( $r ),
                'permission_callback' => $permission,
            ],
            [
                'methods'             => 'DELETE',
                'callback'            => fn( WP_REST_Request $r ) => $this->container->get( $controllerClass )->destroy( $r ),
                'permission_callback' => $permission,
            ],
            'args' => [
                'id' => [
                    'validate_callback' => fn( $v ) => is_numeric( $v ) && (int) $v > 0,
                    'sanitize_callback' => 'absint',
                ],
            ],
        ] );
    }
}
