<?php

namespace Nozule\Modules\Loyalty;

use Nozule\Core\BaseModule;
use Nozule\Core\Database;
use Nozule\Core\EventDispatcher;
use Nozule\Modules\Loyalty\Controllers\LoyaltyController;
use Nozule\Modules\Loyalty\Repositories\LoyaltyRepository;
use Nozule\Modules\Loyalty\Services\LoyaltyService;

/**
 * Loyalty Program module bootstrap (NZL-036).
 *
 * Registers repositories, services, and controllers for
 * tiers, members, transactions, and rewards.
 *
 * Hooks into the booking lifecycle to auto-award points
 * when a guest checks out.
 */
class LoyaltyModule extends BaseModule {

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
			LoyaltyRepository::class,
			fn() => new LoyaltyRepository(
				$this->container->get( Database::class )
			)
		);

		$this->container->singleton(
			LoyaltyService::class,
			fn() => new LoyaltyService(
				$this->container->get( LoyaltyRepository::class ),
				$this->container->get( EventDispatcher::class )
			)
		);

		$this->container->singleton(
			LoyaltyController::class,
			fn() => new LoyaltyController(
				$this->container->get( LoyaltyService::class ),
				$this->container->get( LoyaltyRepository::class )
			)
		);
	}

	/**
	 * Register WordPress hooks for this module.
	 */
	private function registerHooks(): void {
		add_action( 'rest_api_init', [ $this, 'registerRestRoutes' ] );

		// Listen for booking checkout events to auto-award points.
		$events = $this->container->get( EventDispatcher::class );
		$events->listen( 'bookings/checked_out', [ $this, 'onBookingCheckedOut' ], 20 );
	}

	/**
	 * Callback to register REST API routes.
	 */
	public function registerRestRoutes(): void {
		$controller = $this->container->get( LoyaltyController::class );
		$controller->registerRoutes();
	}

	/**
	 * Event listener: award loyalty points when a guest checks out.
	 *
	 * The bookings/checked_out event is expected to provide a booking
	 * object or array with at least: id, guest_id, total_price.
	 *
	 * @param mixed $booking Booking data (object or array).
	 */
	public function onBookingCheckedOut( $booking ): void {
		$bookingData = is_object( $booking ) ? (array) $booking : $booking;

		// If the booking is a model with toArray, convert.
		if ( is_object( $booking ) && method_exists( $booking, 'toArray' ) ) {
			$bookingData = $booking->toArray();
		}

		$guestId    = (int) ( $bookingData['guest_id'] ?? 0 );
		$bookingId  = (int) ( $bookingData['id'] ?? 0 );
		$totalPrice = (float) ( $bookingData['total_price'] ?? 0 );

		if ( ! $guestId || ! $bookingId || $totalPrice <= 0 ) {
			return;
		}

		$repository = $this->container->get( LoyaltyRepository::class );
		$service    = $this->container->get( LoyaltyService::class );

		// Only award if the guest is enrolled.
		$member = $repository->getMemberByGuest( $guestId );
		if ( ! $member ) {
			return;
		}

		try {
			$service->awardPoints( $member->id, $bookingId, $totalPrice );
		} catch ( \RuntimeException $e ) {
			// Log the failure but don't break the checkout flow.
			error_log( sprintf(
				'[Nozule Loyalty] Failed to award points for booking #%d: %s',
				$bookingId,
				$e->getMessage()
			) );
		}
	}
}
