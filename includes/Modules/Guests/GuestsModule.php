<?php

namespace Nozule\Modules\Guests;

use Nozule\Core\BaseModule;
use Nozule\Core\Database;
use Nozule\Core\EventDispatcher;
use Nozule\Core\PropertyScope;
use Nozule\Modules\Bookings\Repositories\BookingRepository;
use Nozule\Modules\Bookings\Services\BookingService;
use Nozule\Modules\Guests\Controllers\GuestController;
use Nozule\Modules\Guests\Controllers\GuestPortalController;
use Nozule\Modules\Guests\Repositories\GuestRepository;
use Nozule\Modules\Guests\Services\GuestService;
use Nozule\Modules\Guests\Validators\GuestValidator;

/**
 * Guests module bootstrap.
 *
 * Registers all services, repositories, and controllers
 * for guest profile management.
 */
class GuestsModule extends BaseModule {

    /**
     * Register the module's bindings, services, and hooks.
     */
    public function register(): void {
        $this->registerBindings();
        $this->registerHooks();
    }

    /**
     * Register service container bindings.
     */
    private function registerBindings(): void {
        $this->container->singleton(
            GuestRepository::class,
            fn() => new GuestRepository(
                $this->container->get( Database::class )
            )
        );

        $this->container->singleton(
            GuestValidator::class,
            fn() => new GuestValidator(
                $this->container->get( GuestRepository::class )
            )
        );

        $this->container->singleton(
            GuestService::class,
            fn() => new GuestService(
                $this->container->get( GuestRepository::class ),
                $this->container->get( GuestValidator::class ),
                $this->container->get( EventDispatcher::class )
            )
        );

        $this->container->singleton(
            GuestController::class,
            fn() => new GuestController(
                $this->container->get( GuestService::class ),
                $this->container->get( GuestRepository::class ),
                $this->container->get( PropertyScope::class )
            )
        );

        $this->container->singleton(
            GuestPortalController::class,
            fn() => new GuestPortalController(
                $this->container->get( GuestRepository::class ),
                $this->container->get( BookingRepository::class ),
                $this->container->get( BookingService::class )
            )
        );
    }

    /**
     * Register WordPress hooks for this module.
     */
    private function registerHooks(): void {
        add_action( 'rest_api_init', [ $this, 'registerRestRoutes' ] );

        // When a WP user registers (via any path — WP default, Ultimate Member, etc.),
        // create a linked Guest record so the guest portal works out of the box.
        add_action( 'user_register', [ $this, 'onUserRegister' ], 20, 1 );
    }

    /**
     * Create or link a Guest record for a newly-registered WP user.
     */
    public function onUserRegister( int $userId ): void {
        $user = get_userdata( $userId );
        if ( ! $user || ! $user->user_email ) {
            return;
        }

        $repo     = $this->container->get( GuestRepository::class );
        $existing = $repo->findByEmail( $user->user_email );

        if ( $existing ) {
            if ( (int) $existing->wp_user_id !== $userId ) {
                $repo->update( $existing->id, [ 'wp_user_id' => $userId ] );
            }
            return;
        }

        $repo->create( [
            'first_name' => $user->first_name ?: $user->display_name,
            'last_name'  => $user->last_name ?: '',
            'email'      => $user->user_email,
            'wp_user_id' => $userId,
        ] );
    }

    /**
     * Callback to register REST API routes.
     */
    public function registerRestRoutes(): void {
        $this->container->get( GuestController::class )->registerRoutes();
        $this->registerPortalRoutes();
    }

    /**
     * Register the public guest-portal REST routes.
     */
    private function registerPortalRoutes(): void {
        $namespace  = 'nozule/v1';
        $controller = fn() => $this->container->get( GuestPortalController::class );
        $authed     = fn() => is_user_logged_in();

        register_rest_route( $namespace, '/me/register', [
            'methods'             => 'POST',
            'callback'            => fn( \WP_REST_Request $r ) => $controller()->register( $r ),
            'permission_callback' => '__return_true',
            'args'                => [
                'email'      => [ 'required' => true, 'sanitize_callback' => 'sanitize_email', 'validate_callback' => fn( $v ) => is_email( $v ) ],
                'password'   => [ 'required' => true ],
                'first_name' => [ 'required' => true, 'sanitize_callback' => 'sanitize_text_field' ],
                'last_name'  => [ 'required' => true, 'sanitize_callback' => 'sanitize_text_field' ],
                'phone'      => [ 'sanitize_callback' => 'sanitize_text_field' ],
                'locale'     => [ 'sanitize_callback' => 'sanitize_text_field' ],
            ],
        ] );

        register_rest_route( $namespace, '/me/profile', [
            [
                'methods'             => 'GET',
                'callback'            => fn( \WP_REST_Request $r ) => $controller()->profile( $r ),
                'permission_callback' => $authed,
            ],
            [
                'methods'             => 'PUT',
                'callback'            => fn( \WP_REST_Request $r ) => $controller()->updateProfile( $r ),
                'permission_callback' => $authed,
            ],
        ] );

        register_rest_route( $namespace, '/me/bookings', [
            'methods'             => 'GET',
            'callback'            => fn( \WP_REST_Request $r ) => $controller()->bookings( $r ),
            'permission_callback' => $authed,
        ] );

        register_rest_route( $namespace, '/me/bookings/(?P<id>\d+)', [
            'methods'             => 'GET',
            'callback'            => fn( \WP_REST_Request $r ) => $controller()->booking( $r ),
            'permission_callback' => $authed,
            'args'                => [
                'id' => [ 'validate_callback' => fn( $v ) => is_numeric( $v ) && (int) $v > 0, 'sanitize_callback' => 'absint' ],
            ],
        ] );

        register_rest_route( $namespace, '/me/bookings/(?P<id>\d+)/cancel', [
            'methods'             => 'POST',
            'callback'            => fn( \WP_REST_Request $r ) => $controller()->cancelBooking( $r ),
            'permission_callback' => $authed,
            'args'                => [
                'id'     => [ 'validate_callback' => fn( $v ) => is_numeric( $v ) && (int) $v > 0, 'sanitize_callback' => 'absint' ],
                'reason' => [ 'sanitize_callback' => 'sanitize_text_field' ],
            ],
        ] );
    }
}
