<?php

namespace Nozule\Modules\Channels;

use Nozule\Core\BaseModule;
use Nozule\Core\Container;
use Nozule\Core\Database;
use Nozule\Core\EventDispatcher;
use Nozule\Core\Logger;
use Nozule\Modules\Channels\Controllers\ChannelController;
use Nozule\Modules\Channels\Repositories\ChannelMappingRepository;
use Nozule\Modules\Channels\Services\ChannelService;
use Nozule\Modules\Channels\Validators\ChannelMappingValidator;

/**
 * Channels module bootstrap.
 *
 * Registers all channel-related services, repositories, and hooks
 * into the application container and WordPress lifecycle.
 */
class ChannelsModule extends BaseModule {

    /**
     * Register the module's services, repositories, and hooks.
     */
    public function register(): void {
        $this->registerServices();
        $this->registerHooks();
    }

    /**
     * Bind channel services into the DI container.
     */
    private function registerServices(): void {
        // Repository.
        $this->container->singleton(
            ChannelMappingRepository::class,
            function ( Container $c ) {
                return new ChannelMappingRepository(
                    $c->get( Database::class )
                );
            }
        );

        // Service (main orchestrator).
        $this->container->singleton(
            ChannelService::class,
            function ( Container $c ) {
                return new ChannelService(
                    $c->get( ChannelMappingRepository::class ),
                    $c->get( Database::class ),
                    $c->get( EventDispatcher::class ),
                    $c->get( Logger::class )
                );
            }
        );

        // Validator.
        $this->container->singleton(
            ChannelMappingValidator::class,
            function ( Container $c ) {
                return new ChannelMappingValidator(
                    $c->get( ChannelMappingRepository::class ),
                    $c->get( ChannelService::class )
                );
            }
        );

        // Controller.
        $this->container->singleton(
            ChannelController::class,
            function ( Container $c ) {
                return new ChannelController(
                    $c->get( ChannelService::class ),
                    $c->get( ChannelMappingRepository::class ),
                    $c->get( ChannelMappingValidator::class )
                );
            }
        );
    }

    /**
     * Register WordPress hooks for the channels module.
     */
    private function registerHooks(): void {
        // Register REST routes.
        add_action( 'rest_api_init', function () {
            $controller = $this->container->get( ChannelController::class );
            $controller->registerRoutes();
        } );

        // Schedule periodic channel sync via WP-Cron.
        add_action( 'init', [ $this, 'scheduleCronEvents' ] );
        add_action( 'nzl_channel_sync', [ $this, 'runScheduledSync' ] );

        // Listen for inventory changes to trigger real-time channel updates.
        $events = $this->container->get( EventDispatcher::class );

        $events->listen( 'rooms/inventory_updated', function ( ...$args ) {
            $this->onInventoryUpdated( ...$args );
        } );

        $events->listen( 'bookings/created', function ( ...$args ) {
            $this->onBookingCreated( ...$args );
        } );

        $events->listen( 'bookings/cancelled', function ( ...$args ) {
            $this->onBookingCancelled( ...$args );
        } );
    }

    /**
     * Schedule the recurring channel sync cron event.
     */
    public function scheduleCronEvents(): void {
        if ( ! wp_next_scheduled( 'nzl_channel_sync' ) ) {
            wp_schedule_event( time(), 'hourly', 'nzl_channel_sync' );
        }
    }

    /**
     * Run the scheduled sync: push availability and rates, pull reservations.
     */
    public function runScheduledSync(): void {
        $service = $this->container->get( ChannelService::class );

        $service->syncAvailability();
        $service->syncRates();
        $service->pullReservations();
    }

    /**
     * React to inventory changes by pushing updated availability.
     *
     * @param mixed ...$args Event arguments (room type ID, date range, etc.).
     */
    private function onInventoryUpdated( ...$args ): void {
        // Availability changes should be pushed to all channels
        // that map the affected room type. A full sync is triggered
        // on the next cron run; real-time push can be added here.

        $logger = $this->container->get( Logger::class );
        $logger->debug( 'Inventory updated event received by channels module.', [
            'args' => $args,
        ] );
    }

    /**
     * React to new bookings by updating channel availability.
     *
     * @param mixed ...$args Event arguments.
     */
    private function onBookingCreated( ...$args ): void {
        $logger = $this->container->get( Logger::class );
        $logger->debug( 'Booking created event received by channels module.', [
            'args' => $args,
        ] );
    }

    /**
     * React to booking cancellations by restoring channel availability.
     *
     * @param mixed ...$args Event arguments.
     */
    private function onBookingCancelled( ...$args ): void {
        $logger = $this->container->get( Logger::class );
        $logger->debug( 'Booking cancelled event received by channels module.', [
            'args' => $args,
        ] );
    }
}
