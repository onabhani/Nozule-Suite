<?php

namespace Nozule\Modules\Rooms\Services;

use Nozule\Core\CacheManager;
use Nozule\Core\EventDispatcher;
use Nozule\Core\Logger;
use Nozule\Modules\Rooms\Models\RoomType;
use Nozule\Modules\Rooms\Repositories\InventoryRepository;
use Nozule\Modules\Rooms\Repositories\RoomTypeRepository;

/**
 * Availability service for checking room availability, deducting inventory,
 * and restoring inventory when bookings are modified or cancelled.
 *
 * This is the critical path for the booking flow:
 *   1. Search: checkAvailability() returns room types with availability for a date range.
 *   2. Book:   deductInventory() atomically reduces available rooms.
 *   3. Cancel: restoreInventory() restores rooms when a booking is cancelled.
 */
class AvailabilityService {

	private const CACHE_TTL = 300; // 5 minutes

	private InventoryRepository $inventoryRepository;
	private RoomTypeRepository $roomTypeRepository;
	private CacheManager $cache;
	private EventDispatcher $events;
	private Logger $logger;

	public function __construct(
		InventoryRepository $inventoryRepository,
		RoomTypeRepository $roomTypeRepository,
		CacheManager $cache,
		EventDispatcher $events,
		Logger $logger
	) {
		$this->inventoryRepository = $inventoryRepository;
		$this->roomTypeRepository  = $roomTypeRepository;
		$this->cache               = $cache;
		$this->events              = $events;
		$this->logger              = $logger;
	}

	/**
	 * Quick inventory-only availability check.
	 *
	 * Checks room count and stop-sell only. Does NOT enforce min_stay,
	 * guest eligibility, or rate plan restrictions — those are handled
	 * by PricingService::calculateStayPrice() in the full booking flow.
	 * For complete availability with pricing, use checkAvailability().
	 *
	 * @param int    $roomTypeId Room type ID.
	 * @param string $checkIn    Check-in date (Y-m-d).
	 * @param string $checkOut   Check-out date (Y-m-d).
	 * @param int    $quantity   Number of rooms needed (default 1).
	 * @return bool True if at least $quantity rooms are available on every night.
	 */
	public function isAvailable( int $roomTypeId, string $checkIn, string $checkOut, int $quantity = 1 ): bool {
		if ( $this->inventoryRepository->hasStopSell( $roomTypeId, $checkIn, $checkOut ) ) {
			return false;
		}

		$minAvailable = $this->inventoryRepository->getMinAvailability( $roomTypeId, $checkIn, $checkOut );

		return $minAvailable >= $quantity;
	}

	/**
	 * Check availability for a date range.
	 *
	 * Returns an array of room types that have availability for every night
	 * in the requested range, enriched with availability and pricing data.
	 *
	 * @param string   $checkIn    Check-in date (Y-m-d).
	 * @param string   $checkOut   Check-out date (Y-m-d).
	 * @param int      $guests     Number of guests (filters by max_occupancy).
	 * @param int|null $roomTypeId Optional: restrict to a single room type.
	 *
	 * @return array[] Each element contains 'room_type', 'available_rooms', 'nightly_rates', 'total_price', 'nights'.
	 */
	public function checkAvailability(
		string $checkIn,
		string $checkOut,
		int $guests = 1,
		?int $roomTypeId = null
	): array {
		// Validate dates.
		$checkInDate  = new \DateTimeImmutable( $checkIn );
		$checkOutDate = new \DateTimeImmutable( $checkOut );

		if ( $checkOutDate <= $checkInDate ) {
			return [];
		}

		$nights = (int) $checkInDate->diff( $checkOutDate )->days;

		// Build cache key. Include property scope to prevent cross-property cache collisions.
		$propertyId = $this->inventoryRepository->getPropertyFilter();
		$cacheKey = sprintf(
			'availability_%d_%s_%s_%d_%d',
			$propertyId ?? 0,
			$checkIn,
			$checkOut,
			$guests,
			$roomTypeId ?? 0
		);

		$cached = $this->cache->get( $cacheKey );
		if ( $cached !== false ) {
			return $cached;
		}

		// Determine which room types to check.
		if ( $roomTypeId ) {
			$roomType = $this->roomTypeRepository->find( $roomTypeId );
			$roomTypes = $roomType && $roomType->isActive() ? [ $roomType ] : [];
		} else {
			$roomTypes = $this->roomTypeRepository->getActive();
		}

		// Filter by guest occupancy first.
		$eligibleTypes = array_filter(
			$roomTypes,
			fn( $rt ) => $rt->max_occupancy >= $guests
		);

		// Batch-fetch inventory for all eligible room types in one query (P3 fix).
		$endDateForInventory = ( new \DateTimeImmutable( $checkOut ) )->modify( '-1 day' )->format( 'Y-m-d' );
		$roomTypeIds = array_map( fn( $rt ) => $rt->id, $eligibleTypes );
		$inventoryBatch = ! empty( $roomTypeIds )
			? $this->inventoryRepository->getForDateRangeBatch( $roomTypeIds, $checkIn, $endDateForInventory )
			: [];

		$results = [];

		foreach ( $eligibleTypes as $roomType ) {
			$inventory = $inventoryBatch[ $roomType->id ] ?? [];

			$availability = $this->getRoomTypeAvailabilityFromInventory(
				$roomType,
				$inventory,
				$nights,
				$guests
			);

			if ( $availability !== null ) {
				$results[] = $availability;
			}
		}

		// Sort by total price ascending.
		usort( $results, fn( $a, $b ) => $a['total_price'] <=> $b['total_price'] );

		// Allow filtering via hook.
		$results = $this->events->filter(
			'rooms/availability_results',
			$results,
			$checkIn,
			$checkOut,
			$guests
		);

		$this->cache->set( $cacheKey, $results, self::CACHE_TTL );

		return $results;
	}

	/**
	 * Deduct inventory when a booking is confirmed.
	 *
	 * This is an atomic operation: either all nights are deducted or none.
	 * Uses database-level checks to prevent overbooking.
	 *
	 * NOTE: This method does NOT manage its own transaction. The caller
	 * (e.g. BookingService::createBooking) must wrap the call in a
	 * transaction to ensure atomicity with the booking record creation.
	 *
	 * @param int    $roomTypeId Room type ID.
	 * @param string $checkIn    Check-in date (Y-m-d).
	 * @param string $checkOut   Check-out date (Y-m-d).
	 * @param int    $quantity   Number of rooms to deduct.
	 *
	 * @return bool True if inventory was successfully deducted.
	 */
	public function deductInventory(
		int $roomTypeId,
		string $checkIn,
		string $checkOut,
		int $quantity = 1
	): bool {
		$success = $this->inventoryRepository->deductRooms(
			$roomTypeId,
			$checkIn,
			$checkOut,
			$quantity
		);

		if ( ! $success ) {
			$this->logger->warning( 'Inventory deduction failed - insufficient availability', [
				'room_type_id' => $roomTypeId,
				'check_in'     => $checkIn,
				'check_out'    => $checkOut,
				'quantity'     => $quantity,
			] );
			return false;
		}

		// NOTE: Side effects (cache, events, logging) are deferred and run
		// via onInventoryDeducted() after the caller commits the transaction.
		// This prevents stale cache/events if the transaction rolls back.
		$this->pendingDeductions[] = [
			'room_type_id' => $roomTypeId,
			'check_in'     => $checkIn,
			'check_out'    => $checkOut,
			'quantity'     => $quantity,
		];

		return true;
	}

	/** @var array Pending deduction side effects to flush after commit. */
	private array $pendingDeductions = [];

	/** @var array Pending restoration side effects to flush after commit. */
	private array $pendingRestorations = [];

	/**
	 * Flush deferred side effects after the caller commits the transaction.
	 *
	 * Call this after your transaction commits to invalidate cache,
	 * dispatch events, and log the inventory changes.
	 */
	public function flushPendingSideEffects(): void {
		foreach ( $this->pendingDeductions as $d ) {
			$this->invalidateAvailabilityCache( $d['check_in'], $d['check_out'] );
			$this->events->dispatch( 'rooms/inventory_deducted', $d['room_type_id'], $d['check_in'], $d['check_out'], $d['quantity'] );
			$this->logger->info( 'Inventory deducted', $d );
		}

		foreach ( $this->pendingRestorations as $r ) {
			$this->invalidateAvailabilityCache( $r['check_in'], $r['check_out'] );
			$this->events->dispatch( 'rooms/inventory_restored', $r['room_type_id'], $r['check_in'], $r['check_out'], $r['quantity'] );
			$this->logger->info( 'Inventory restored', $r );
		}

		$this->pendingDeductions  = [];
		$this->pendingRestorations = [];
	}

	/**
	 * Restore inventory when a booking is cancelled or modified.
	 *
	 * NOTE: This method does NOT manage its own transaction. The caller
	 * (e.g. BookingService::cancelBooking) must wrap the call in a
	 * transaction to ensure atomicity with the booking status change.
	 *
	 * @param int    $roomTypeId Room type ID.
	 * @param string $checkIn    Check-in date (Y-m-d).
	 * @param string $checkOut   Check-out date (Y-m-d).
	 * @param int    $quantity   Number of rooms to restore.
	 *
	 * @return bool True if inventory was successfully restored.
	 */
	public function restoreInventory(
		int $roomTypeId,
		string $checkIn,
		string $checkOut,
		int $quantity = 1
	): bool {
		$success = $this->inventoryRepository->restoreRooms(
			$roomTypeId,
			$checkIn,
			$checkOut,
			$quantity
		);

		if ( ! $success ) {
			$this->logger->warning( 'Inventory restoration failed', [
				'room_type_id' => $roomTypeId,
				'check_in'     => $checkIn,
				'check_out'    => $checkOut,
				'quantity'     => $quantity,
			] );
			return false;
		}

		// Defer side effects until the caller commits the transaction.
		$this->pendingRestorations[] = [
			'room_type_id' => $roomTypeId,
			'check_in'     => $checkIn,
			'check_out'    => $checkOut,
			'quantity'     => $quantity,
		];

		return true;
	}

	/**
	 * Get detailed availability for a single room type across a date range.
	 *
	 * Checks inventory for every night, calculates nightly rates, and
	 * ensures minimum availability across the entire stay.
	 *
	 * @return array|null Null if the room type is not available.
	 */
	/**
	 * @deprecated Use getRoomTypeAvailabilityFromInventory() with batch-loaded inventory.
	 */
	private function getRoomTypeAvailability(
		RoomType $roomType,
		string $checkIn,
		string $checkOut,
		int $nights,
		int $guests
	): ?array {
		$inventory = $this->inventoryRepository->getForDateRange(
			$roomType->id,
			$checkIn,
			( new \DateTimeImmutable( $checkOut ) )->modify( '-1 day' )->format( 'Y-m-d' )
		);

		return $this->getRoomTypeAvailabilityFromInventory( $roomType, $inventory, $nights, $guests );
	}

	/**
	 * Evaluate availability for a single room type given pre-loaded inventory.
	 *
	 * @param RoomType          $roomType  The room type to evaluate.
	 * @param RoomInventory[]   $inventory Pre-loaded inventory records for the date range.
	 * @param int               $nights    Number of nights in the stay.
	 * @param int               $guests    Number of guests.
	 * @return array|null Null if the room type is not available.
	 */
	private function getRoomTypeAvailabilityFromInventory(
		RoomType $roomType,
		array $inventory,
		int $nights,
		int $guests
	): ?array {
		// Must have inventory records for every night.
		if ( count( $inventory ) < $nights ) {
			return null;
		}

		$minAvailable  = PHP_INT_MAX;
		$nightlyRates  = [];
		$totalPrice    = 0.0;

		foreach ( $inventory as $dayInventory ) {
			// Skip if stop_sell is active for any night.
			if ( $dayInventory->stop_sell ) {
				return null;
			}

			// Skip if no rooms available on any night.
			if ( $dayInventory->available_rooms <= 0 ) {
				return null;
			}

			// Check minimum stay requirement.
			if ( $dayInventory->min_stay > $nights ) {
				return null;
			}

			// Track the minimum availability across all nights.
			$minAvailable = min( $minAvailable, $dayInventory->available_rooms );

			// Determine the rate for this night.
			$nightRate = $dayInventory->price_override ?? $roomType->base_price;

			// Add extra guest charges if occupancy exceeds base.
			if ( $guests > $roomType->base_occupancy ) {
				$extraAdults = $guests - $roomType->base_occupancy;
				$nightRate  += $extraAdults * $roomType->extra_adult_price;
			}

			$nightlyRates[] = [
				'date' => $dayInventory->date,
				'rate' => round( $nightRate, 2 ),
			];

			$totalPrice += $nightRate;
		}

		if ( $minAvailable <= 0 ) {
			return null;
		}

		return [
			'room_type'       => $roomType->toPublicArray(),
			'available_rooms' => $minAvailable,
			'nights'          => $nights,
			'nightly_rates'   => $nightlyRates,
			'total_price'     => round( $totalPrice, 2 ),
			'avg_nightly'     => round( $totalPrice / $nights, 2 ),
		];
	}

	/**
	 * Invalidate availability cache entries for dates in a range.
	 *
	 * Since cache keys contain dates, we invalidate the tag so all
	 * availability queries are refreshed.
	 */
	private function invalidateAvailabilityCache( string $checkIn, string $checkOut ): void {
		$this->cache->invalidateTag( 'availability' );

		// Also clear specific date-based keys that may exist.
		$current = new \DateTimeImmutable( $checkIn );
		$end     = new \DateTimeImmutable( $checkOut );

		while ( $current < $end ) {
			$this->cache->delete( 'availability_' . $current->format( 'Y-m-d' ) );
			$current = $current->modify( '+1 day' );
		}
	}
}
