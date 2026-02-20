<?php

namespace Nozule\Modules\Channels;

use Nozule\Core\BaseModule;
use Nozule\Core\Container;
use Nozule\Core\Database;
use Nozule\Core\EventDispatcher;
use Nozule\Core\Logger;
use Nozule\Modules\Channels\Controllers\ChannelSyncController;
use Nozule\Modules\Channels\Repositories\ChannelConnectionRepository;
use Nozule\Modules\Channels\Repositories\ChannelRateMappingRepository;
use Nozule\Modules\Channels\Repositories\ChannelSyncLogRepository;
use Nozule\Modules\Channels\Services\BookingComApiClient;
use Nozule\Modules\Channels\Services\ChannelSyncService;

/**
 * Channel Sync module bootstrap.
 *
 * Registers all OTA channel sync services, repositories, controllers,
 * WP-Cron schedules, and booking lifecycle hooks.
 */
class ChannelSyncModule extends BaseModule {

	/** @var string Cron hook for periodic reservation pull (every 15 min). */
	const CRON_PULL_RESERVATIONS = 'nzl_channel_pull_reservations';

	/** @var string Cron hook for periodic availability/rates push (every hour). */
	const CRON_PUSH_INVENTORY = 'nzl_channel_push_inventory';

	/** @var string Custom cron schedule name for 15-minute intervals. */
	const SCHEDULE_FIFTEEN_MIN = 'nzl_every_fifteen_minutes';

	/**
	 * Register the module's services, repositories, and hooks.
	 */
	public function register(): void {
		$this->registerServices();
		$this->registerHooks();
	}

	/**
	 * Bind channel sync services into the DI container.
	 */
	private function registerServices(): void {
		// Repositories.
		$this->container->singleton(
			ChannelConnectionRepository::class,
			function ( Container $c ) {
				return new ChannelConnectionRepository(
					$c->get( Database::class )
				);
			}
		);

		$this->container->singleton(
			ChannelSyncLogRepository::class,
			function ( Container $c ) {
				return new ChannelSyncLogRepository(
					$c->get( Database::class )
				);
			}
		);

		$this->container->singleton(
			ChannelRateMappingRepository::class,
			function ( Container $c ) {
				return new ChannelRateMappingRepository(
					$c->get( Database::class )
				);
			}
		);

		// API Client.
		$this->container->singleton(
			BookingComApiClient::class,
			function ( Container $c ) {
				return new BookingComApiClient(
					$c->get( Logger::class )
				);
			}
		);

		// Sync Service (orchestrator).
		$this->container->singleton(
			ChannelSyncService::class,
			function ( Container $c ) {
				$service = new ChannelSyncService(
					$c->get( ChannelConnectionRepository::class ),
					$c->get( ChannelRateMappingRepository::class ),
					$c->get( ChannelSyncLogRepository::class ),
					$c->get( Database::class ),
					$c->get( EventDispatcher::class ),
					$c->get( Logger::class )
				);

				// Register the Booking.com client factory.
				$service->registerClient( 'booking_com', function () use ( $c ) {
					return new BookingComApiClient(
						$c->get( Logger::class )
					);
				} );

				/**
				 * Allow third-party plugins to register additional channel clients.
				 *
				 * @param ChannelSyncService $service The sync service instance.
				 */
				do_action( 'nozule/channel_sync/register_clients', $service );

				return $service;
			}
		);

		// Controller.
		$this->container->singleton(
			ChannelSyncController::class,
			function ( Container $c ) {
				return new ChannelSyncController(
					$c->get( ChannelSyncService::class ),
					$c->get( ChannelConnectionRepository::class ),
					$c->get( ChannelRateMappingRepository::class ),
					$c->get( ChannelSyncLogRepository::class )
				);
			}
		);
	}

	/**
	 * Register WordPress hooks for the channel sync module.
	 */
	private function registerHooks(): void {
		// Register REST routes.
		add_action( 'rest_api_init', function () {
			$controller = $this->container->get( ChannelSyncController::class );
			$controller->registerRoutes();
		} );

		// Register custom cron schedules.
		add_filter( 'cron_schedules', [ $this, 'addCronSchedules' ] );

		// Schedule cron events.
		add_action( 'init', [ $this, 'scheduleCronEvents' ] );

		// Cron action handlers.
		add_action( self::CRON_PULL_RESERVATIONS, [ $this, 'runReservationPull' ] );
		add_action( self::CRON_PUSH_INVENTORY, [ $this, 'runInventoryPush' ] );

		// Hook into booking lifecycle for real-time inventory updates.
		$events = $this->container->get( EventDispatcher::class );

		$events->listen( 'bookings/confirmed', function ( ...$args ) {
			$this->onBookingConfirmed( ...$args );
		} );

		$events->listen( 'bookings/cancelled', function ( ...$args ) {
			$this->onBookingCancelled( ...$args );
		} );

		$events->listen( 'bookings/created', function ( ...$args ) {
			$this->onBookingCreated( ...$args );
		} );
	}

	/**
	 * Add custom cron schedules.
	 *
	 * @param array $schedules Existing schedules.
	 * @return array Modified schedules.
	 */
	public function addCronSchedules( array $schedules ): array {
		$schedules[ self::SCHEDULE_FIFTEEN_MIN ] = [
			'interval' => 15 * MINUTE_IN_SECONDS,
			'display'  => __( 'Every 15 Minutes', 'nozule' ),
		];

		return $schedules;
	}

	/**
	 * Schedule the recurring cron events.
	 */
	public function scheduleCronEvents(): void {
		// Reservation pull every 15 minutes.
		if ( ! wp_next_scheduled( self::CRON_PULL_RESERVATIONS ) ) {
			wp_schedule_event( time(), self::SCHEDULE_FIFTEEN_MIN, self::CRON_PULL_RESERVATIONS );
		}

		// Inventory push every hour.
		if ( ! wp_next_scheduled( self::CRON_PUSH_INVENTORY ) ) {
			wp_schedule_event( time(), 'hourly', self::CRON_PUSH_INVENTORY );
		}
	}

	/**
	 * Cron handler: pull reservations from all active channels.
	 */
	public function runReservationPull(): void {
		$service     = $this->container->get( ChannelSyncService::class );
		$connections = $this->container->get( ChannelConnectionRepository::class )->getActive();

		foreach ( $connections as $connection ) {
			$service->pullReservations( $connection->channel_name );
		}
	}

	/**
	 * Cron handler: push availability and rates to all active channels.
	 */
	public function runInventoryPush(): void {
		$service     = $this->container->get( ChannelSyncService::class );
		$connections = $this->container->get( ChannelConnectionRepository::class )->getActive();

		foreach ( $connections as $connection ) {
			$service->pushAvailability( $connection->channel_name );
			$service->pushRates( $connection->channel_name );
		}
	}

	/**
	 * React to booking confirmation by pushing updated availability.
	 *
	 * @param mixed ...$args Event arguments (booking data).
	 */
	private function onBookingConfirmed( ...$args ): void {
		$this->pushAvailabilityForBooking( $args, 'confirmed' );
	}

	/**
	 * React to booking creation by pushing updated availability.
	 *
	 * @param mixed ...$args Event arguments (booking data).
	 */
	private function onBookingCreated( ...$args ): void {
		$this->pushAvailabilityForBooking( $args, 'created' );
	}

	/**
	 * React to booking cancellation by pushing updated availability (restore).
	 *
	 * @param mixed ...$args Event arguments (booking data).
	 */
	private function onBookingCancelled( ...$args ): void {
		$this->pushAvailabilityForBooking( $args, 'cancelled' );
	}

	/**
	 * Push availability updates to all active channels for a booking event.
	 *
	 * @param array  $args      Event arguments.
	 * @param string $eventType Type of event (created, confirmed, cancelled).
	 */
	private function pushAvailabilityForBooking( array $args, string $eventType ): void {
		$logger = $this->container->get( Logger::class );

		$logger->debug( 'Channel sync: Booking event received.', [
			'event' => $eventType,
			'args'  => $args,
		] );

		// Extract room type and dates from the booking data.
		$booking = $args[0] ?? null;
		if ( ! $booking ) {
			return;
		}

		// Handle both object and array booking data.
		if ( is_object( $booking ) ) {
			$roomTypeId = $booking->room_type_id ?? null;
			$checkIn    = $booking->check_in ?? null;
			$checkOut   = $booking->check_out ?? null;
		} elseif ( is_array( $booking ) ) {
			$roomTypeId = $booking['room_type_id'] ?? null;
			$checkIn    = $booking['check_in'] ?? null;
			$checkOut   = $booking['check_out'] ?? null;
		} else {
			return;
		}

		if ( ! $roomTypeId || ! $checkIn || ! $checkOut ) {
			return;
		}

		// Push availability updates to all active channels.
		$service     = $this->container->get( ChannelSyncService::class );
		$connections = $this->container->get( ChannelConnectionRepository::class )->getActive();

		foreach ( $connections as $connection ) {
			try {
				$service->pushAvailability(
					$connection->channel_name,
					(int) $roomTypeId,
					$checkIn,
					$checkOut
				);
			} catch ( \Throwable $e ) {
				$logger->error( 'Channel sync: Failed to push availability on booking event.', [
					'channel' => $connection->channel_name,
					'event'   => $eventType,
					'error'   => $e->getMessage(),
				] );
			}
		}
	}
}
