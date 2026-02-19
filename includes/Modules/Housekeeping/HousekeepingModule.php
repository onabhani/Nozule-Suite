<?php

namespace Nozule\Modules\Housekeeping;

use Nozule\Core\BaseModule;
use Nozule\Core\Container;
use Nozule\Core\Database;
use Nozule\Core\EventDispatcher;
use Nozule\Core\Logger;
use Nozule\Modules\Housekeeping\Controllers\HousekeepingController;
use Nozule\Modules\Housekeeping\Models\HousekeepingTask;
use Nozule\Modules\Housekeeping\Repositories\HousekeepingRepository;
use Nozule\Modules\Housekeeping\Services\HousekeepingService;
use Nozule\Modules\Housekeeping\Validators\HousekeepingValidator;
use Nozule\Modules\Rooms\Repositories\RoomRepository;

/**
 * Housekeeping module bootstrap.
 *
 * Registers all repositories, validators, services, and controllers
 * for the housekeeping management functionality.
 */
class HousekeepingModule extends BaseModule {

	/**
	 * Register the module's services and hooks.
	 */
	public function register(): void {
		$this->registerRepositories();
		$this->registerValidators();
		$this->registerServices();
		$this->registerControllers();
		$this->registerHooks();
	}

	/**
	 * Register repository singletons in the container.
	 */
	private function registerRepositories(): void {
		$this->container->singleton( HousekeepingRepository::class, function ( Container $c ) {
			return new HousekeepingRepository(
				$c->get( Database::class )
			);
		} );
	}

	/**
	 * Register validator singletons in the container.
	 */
	private function registerValidators(): void {
		$this->container->singleton( HousekeepingValidator::class, function ( Container $c ) {
			return new HousekeepingValidator(
				$c->get( HousekeepingRepository::class )
			);
		} );
	}

	/**
	 * Register service singletons in the container.
	 */
	private function registerServices(): void {
		$this->container->singleton( HousekeepingService::class, function ( Container $c ) {
			return new HousekeepingService(
				$c->get( HousekeepingRepository::class ),
				$c->get( HousekeepingValidator::class ),
				$c->get( RoomRepository::class ),
				$c->get( EventDispatcher::class ),
				$c->get( Logger::class )
			);
		} );
	}

	/**
	 * Register REST API controllers.
	 */
	private function registerControllers(): void {
		$this->container->singleton( HousekeepingController::class, function ( Container $c ) {
			return new HousekeepingController(
				$c->get( HousekeepingService::class )
			);
		} );
	}

	/**
	 * Register WordPress hooks for this module.
	 */
	private function registerHooks(): void {
		// Register REST routes on rest_api_init.
		add_action( 'rest_api_init', function () {
			$this->container->get( HousekeepingController::class )->registerRoutes();
		} );

		// Listen for booking checkout events to automatically create dirty tasks.
		// BookingService fires do_action('nozule/booking/checked_out', $bookingId).
		add_action( 'nozule/booking/checked_out', function ( int $bookingId ) {
			$this->onBookingCheckedOut( $bookingId );
		} );
	}

	/**
	 * Handle booking checkout event.
	 *
	 * When a guest checks out, the room should be marked dirty
	 * so housekeeping knows it needs cleaning.
	 */
	private function onBookingCheckedOut( int $bookingId ): void {
		try {
			$db      = $this->container->get( Database::class );
			$table   = $db->table( 'bookings' );
			$booking = $db->getRow(
				"SELECT room_id FROM {$table} WHERE id = %d",
				$bookingId
			);

			if ( $booking && $booking->room_id ) {
				$service = $this->container->get( HousekeepingService::class );
				$service->markRoomDirty( (int) $booking->room_id );
			}
		} catch ( \Throwable $e ) {
			$logger = $this->container->get( Logger::class );
			$logger->error( 'Failed to create housekeeping task on checkout', [
				'booking_id' => $bookingId,
				'error'      => $e->getMessage(),
			] );
		}
	}

	/**
	 * Handle room status change events.
	 *
	 * When a room is moved to maintenance or out_of_order, create an
	 * out-of-order housekeeping task if one does not already exist.
	 */
	private function onRoomStatusChanged( object $room, string $newStatus ): void {
		if ( $newStatus !== 'out_of_order' ) {
			return;
		}

		try {
			$service = $this->container->get( HousekeepingService::class );
			$repo    = $this->container->get( HousekeepingRepository::class );

			// Only create a task if there is no active one already.
			$activeTask = $repo->getActiveTaskForRoom( (int) $room->id );
			if ( ! $activeTask ) {
				$service->createTask( [
					'room_id'   => (int) $room->id,
					'status'    => HousekeepingTask::STATUS_OUT_OF_ORDER,
					'priority'  => HousekeepingTask::PRIORITY_HIGH,
					'task_type' => HousekeepingTask::TYPE_DEEP_CLEAN,
					'notes'     => __( 'Room marked out of order.', 'nozule' ),
				] );
			}
		} catch ( \Throwable $e ) {
			$logger = $this->container->get( Logger::class );
			$logger->error( 'Failed to create housekeeping task on room status change', [
				'room_id' => $room->id,
				'status'  => $newStatus,
				'error'   => $e->getMessage(),
			] );
		}
	}
}
