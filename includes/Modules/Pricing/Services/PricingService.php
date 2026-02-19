<?php

namespace Nozule\Modules\Pricing\Services;

use Nozule\Core\CacheManager;
use Nozule\Core\EventDispatcher;
use Nozule\Core\SettingsManager;
use Nozule\Modules\Pricing\Models\PricingResult;
use Nozule\Modules\Pricing\Models\RatePlan;
use Nozule\Modules\Pricing\Models\SeasonalRate;
use Nozule\Modules\Pricing\Repositories\RatePlanRepository;
use Nozule\Modules\Pricing\Repositories\SeasonalRateRepository;
use Nozule\Modules\Rooms\Repositories\InventoryRepository;
use Nozule\Modules\Rooms\Repositories\RoomTypeRepository;

/**
 * Main pricing service.
 *
 * Orchestrates rate plan selection, seasonal adjustments, extra person
 * charges, and tax calculations to produce a complete PricingResult
 * for any given stay.
 */
class PricingService {

	private RatePlanRepository $ratePlanRepo;
	private SeasonalRateRepository $seasonalRepo;
	private InventoryRepository $inventoryRepo;
	private RoomTypeRepository $roomTypeRepo;
	private SettingsManager $settings;
	private CacheManager $cache;
	private EventDispatcher $events;

	public function __construct(
		RatePlanRepository      $ratePlanRepo,
		SeasonalRateRepository  $seasonalRepo,
		InventoryRepository     $inventoryRepo,
		RoomTypeRepository      $roomTypeRepo,
		SettingsManager         $settings,
		CacheManager            $cache,
		EventDispatcher         $events
	) {
		$this->ratePlanRepo  = $ratePlanRepo;
		$this->seasonalRepo  = $seasonalRepo;
		$this->inventoryRepo = $inventoryRepo;
		$this->roomTypeRepo  = $roomTypeRepo;
		$this->settings      = $settings;
		$this->cache         = $cache;
		$this->events        = $events;
	}

	/**
	 * Calculate the total price for a stay.
	 *
	 * Builds a PricingResult including nightly rates, extra person fees,
	 * taxes, and totals. If no rate plan ID is provided, the default
	 * plan for the room type is used.
	 *
	 * When $guestType is provided ('syrian' or 'non_syrian'), the system
	 * selects rate plans matching that guest type first. This supports the
	 * common Syrian hotel practice of different pricing for locals vs foreigners.
	 *
	 * @param int         $roomTypeId  The room type being priced.
	 * @param string      $checkIn     Check-in date (Y-m-d).
	 * @param string      $checkOut    Check-out date (Y-m-d).
	 * @param int         $adults      Number of adult guests.
	 * @param int         $children    Number of child guests.
	 * @param int|null    $ratePlanId  Specific rate plan, or null for default.
	 * @param string|null $guestType   'syrian', 'non_syrian', or null for default ('all').
	 * @return PricingResult
	 * @throws \RuntimeException If no rate plan is available or the room type is not found.
	 */
	public function calculateStayPrice(
		int     $roomTypeId,
		string  $checkIn,
		string  $checkOut,
		int     $adults,
		int     $children   = 0,
		?int    $ratePlanId = null,
		?string $guestType  = null
	): PricingResult {
		// Resolve the rate plan (considers guest_type for Syrian/non-Syrian pricing).
		$ratePlan = $this->resolveRatePlan( $roomTypeId, $ratePlanId, $guestType );

		// Validate stay length against rate plan constraints.
		$dates  = $this->getDateRange( $checkIn, $checkOut );
		$nights = count( $dates );

		if ( $nights === 0 ) {
			throw new \RuntimeException(
				__( 'Check-out date must be after check-in date.', 'nozule' )
			);
		}

		if ( ! $ratePlan->isValidForStayLength( $nights ) ) {
			throw new \RuntimeException(
				sprintf(
					__( 'This rate plan requires a stay between %d and %d nights.', 'nozule' ),
					$ratePlan->min_stay,
					$ratePlan->max_stay > 0 ? $ratePlan->max_stay : PHP_INT_MAX
				)
			);
		}

		// Pre-load seasonal rates for the entire range to avoid N+1 queries.
		$seasonalRates = $this->seasonalRepo->getForDateRange(
			$roomTypeId,
			$ratePlan->id,
			$checkIn,
			$checkOut
		);

		// Calculate nightly rates.
		$nightlyRates = [];
		$subtotal     = 0.0;

		foreach ( $dates as $date ) {
			$rate = $this->calculateNightlyRate(
				$roomTypeId,
				$ratePlan,
				$date,
				$seasonalRates
			);
			$nightlyRates[ $date ] = $rate;
			$subtotal             += $rate;
		}

		// Extra person charges.
		$extraPersonFees = $this->calculateExtraPersonCharge(
			$roomTypeId,
			$adults,
			$children,
			$nights
		);

		// Service fees (if any).
		$serviceFeeRate = (float) $this->settings->get( 'pricing.service_fee_rate', 0 );
		$serviceFee     = round( $subtotal * ( $serviceFeeRate / 100 ), 2 );

		$totalFees = round( $extraPersonFees + $serviceFee, 2 );

		// Discounts (can be extended via filter).
		$discount = 0.0;

		/**
		 * Filter the discount amount before tax calculation.
		 *
		 * @param float    $discount    Current discount (default 0).
		 * @param int      $roomTypeId  Room type ID.
		 * @param RatePlan $ratePlan    The resolved rate plan.
		 * @param int      $nights      Number of nights.
		 * @param float    $subtotal    Subtotal before discount.
		 */
		$discount = (float) $this->events->filter(
			'pricing/discount',
			$discount,
			$roomTypeId,
			$ratePlan,
			$nights,
			$subtotal
		);
		$discount = max( 0.0, round( $discount, 2 ) );

		// Tax calculation on (subtotal + fees - discount).
		$taxableAmount = max( 0.0, $subtotal + $totalFees - $discount );
		$taxRate       = (float) $this->settings->get( 'pricing.tax_rate', 0 );
		$taxes         = round( $taxableAmount * ( $taxRate / 100 ), 2 );

		// Currency and exchange rate.
		$currency     = $this->settings->get( 'currency.default', 'USD' );
		$exchangeRate = (float) $this->settings->get( 'currency.exchange_rate', 1.0 );

		// Final total.
		$total = round( $subtotal + $totalFees + $taxes - $discount, 2 );

		$result = new PricingResult(
			subtotal:     round( $subtotal, 2 ),
			taxes:        $taxes,
			fees:         $totalFees,
			discount:     $discount,
			total:        $total,
			currency:     $currency,
			exchangeRate: $exchangeRate,
			nightlyRates: $nightlyRates,
		);

		/**
		 * Dispatched after a stay price has been calculated.
		 *
		 * @param PricingResult $result     The pricing result.
		 * @param int           $roomTypeId Room type ID.
		 * @param RatePlan      $ratePlan   The rate plan used.
		 * @param int           $adults     Number of adults.
		 * @param int           $children   Number of children.
		 */
		$this->events->dispatch(
			'pricing/calculated',
			$result,
			$roomTypeId,
			$ratePlan,
			$adults,
			$children
		);

		return $result;
	}

	/**
	 * Calculate the nightly rate for a specific room type, rate plan, and date.
	 *
	 * The calculation pipeline is:
	 *   1. Get base price from inventory (price_override) or room type.
	 *   2. Apply the rate plan modifier.
	 *   3. Apply applicable seasonal rate modifiers (highest priority first).
	 *
	 * @param int             $roomTypeId    Room type ID.
	 * @param RatePlan        $ratePlan      The rate plan to apply.
	 * @param string          $date          The date (Y-m-d).
	 * @param SeasonalRate[]  $seasonalRates Pre-loaded seasonal rates for the stay range.
	 * @return float The calculated nightly rate (never negative).
	 */
	public function calculateNightlyRate(
		int      $roomTypeId,
		RatePlan $ratePlan,
		string   $date,
		array    $seasonalRates = []
	): float {
		// Step 1: Resolve base price.
		$basePrice = $this->resolveBasePrice( $roomTypeId, $date );

		// Step 2: Apply rate plan modifier.
		$price = $this->applyModifier(
			$basePrice,
			$ratePlan->modifier_value,
			$ratePlan->modifier_type
		);

		// Step 3: Apply seasonal rate modifiers.
		// If no pre-loaded seasonal rates, fetch them for this date.
		if ( empty( $seasonalRates ) ) {
			$seasonalRates = $this->seasonalRepo->getActiveForDate(
				$roomTypeId,
				$ratePlan->id,
				$date
			);
		}

		// Filter to rates that apply to this specific date (day-of-week check).
		$applicableSeasonalRates = array_filter(
			$seasonalRates,
			fn( SeasonalRate $sr ) => $sr->appliesToDate( $date )
				&& $sr->appliesToRatePlan( $ratePlan->id )
		);

		// Apply the highest-priority seasonal rate only (first in the sorted list).
		if ( ! empty( $applicableSeasonalRates ) ) {
			$topSeasonal = reset( $applicableSeasonalRates );
			$price = $this->applyModifier(
				$price,
				$topSeasonal->modifier_value,
				$topSeasonal->modifier_type
			);
		}

		/**
		 * Filter the nightly rate after all modifiers have been applied.
		 *
		 * @param float    $price      The calculated price.
		 * @param int      $roomTypeId Room type ID.
		 * @param RatePlan $ratePlan   The rate plan.
		 * @param string   $date       The date.
		 */
		$price = (float) $this->events->filter(
			'pricing/nightly_rate',
			$price,
			$roomTypeId,
			$ratePlan,
			$date
		);

		return max( 0.0, round( $price, 2 ) );
	}

	/**
	 * Apply a price modifier.
	 *
	 * Supports three modifier types:
	 *   - 'percentage': adjusts by a percentage (e.g. +10 adds 10%, -15 subtracts 15%).
	 *   - 'fixed': adds or subtracts a fixed amount.
	 *   - 'absolute': replaces the price entirely with the modifier value.
	 *
	 * @param float  $price    The current price.
	 * @param float  $modifier The modifier value.
	 * @param string $type     The modifier type.
	 * @return float The modified price.
	 */
	public function applyModifier( float $price, float $modifier, string $type ): float {
		return match ( $type ) {
			'percentage' => $price * ( 1 + ( $modifier / 100 ) ),
			'fixed'      => $price + $modifier,
			'absolute'   => $modifier,
			default      => $price,
		};
	}

	/**
	 * Calculate extra person charges based on base occupancy.
	 *
	 * If the number of adults exceeds the room type's base_occupancy,
	 * extra adult charges apply per extra adult per night. Children
	 * are always charged the child rate per child per night.
	 *
	 * Room-type-level overrides (extra_adult_price, extra_child_price)
	 * take precedence over global settings if set and greater than zero.
	 *
	 * @param int $roomTypeId Room type ID.
	 * @param int $adults     Number of adults.
	 * @param int $children   Number of children.
	 * @param int $nights     Number of nights.
	 * @return float The total extra person charge.
	 */
	public function calculateExtraPersonCharge(
		int $roomTypeId,
		int $adults,
		int $children,
		int $nights
	): float {
		$roomType = $this->roomTypeRepo->find( $roomTypeId );

		if ( ! $roomType ) {
			return 0.0;
		}

		$baseOccupancy = (int) $roomType->base_occupancy;
		$extraAdults   = max( 0, $adults - $baseOccupancy );

		// Use room-type-level overrides if available; fall back to global settings.
		$adultCharge = ( $roomType->extra_adult_price && (float) $roomType->extra_adult_price > 0 )
			? (float) $roomType->extra_adult_price
			: (float) $this->settings->get( 'pricing.extra_adult_charge', 0 );

		$childCharge = ( $roomType->extra_child_price && (float) $roomType->extra_child_price > 0 )
			? (float) $roomType->extra_child_price
			: (float) $this->settings->get( 'pricing.extra_child_charge', 0 );

		$totalCharge = ( ( $extraAdults * $adultCharge ) + ( $children * $childCharge ) ) * $nights;

		return round( $totalCharge, 2 );
	}

	/**
	 * Resolve the rate plan to use for pricing.
	 *
	 * When no explicit rate plan ID is provided, uses the guest type
	 * (syrian / non_syrian) to find the best matching default plan.
	 *
	 * @param int         $roomTypeId Room type ID.
	 * @param int|null    $ratePlanId Explicit rate plan ID, or null for automatic selection.
	 * @param string|null $guestType  'syrian', 'non_syrian', or null.
	 * @return RatePlan
	 * @throws \RuntimeException If no rate plan is available.
	 */
	private function resolveRatePlan( int $roomTypeId, ?int $ratePlanId, ?string $guestType = null ): RatePlan {
		if ( $ratePlanId !== null ) {
			$ratePlan = $this->ratePlanRepo->find( $ratePlanId );

			if ( ! $ratePlan ) {
				throw new \RuntimeException(
					sprintf( __( 'Rate plan with ID %d not found.', 'nozule' ), $ratePlanId )
				);
			}

			if ( ! $ratePlan->isActive() ) {
				throw new \RuntimeException(
					__( 'The selected rate plan is not currently active.', 'nozule' )
				);
			}

			if ( ! $ratePlan->appliesToRoomType( $roomTypeId ) ) {
				throw new \RuntimeException(
					__( 'The selected rate plan does not apply to this room type.', 'nozule' )
				);
			}

			return $ratePlan;
		}

		// Use guest_type to find the best matching rate plan.
		$ratePlan = $this->ratePlanRepo->getDefaultForRoomType( $roomTypeId, $guestType );

		if ( ! $ratePlan ) {
			throw new \RuntimeException(
				__( 'No rate plan is available for this room type.', 'nozule' )
			);
		}

		return $ratePlan;
	}

	/**
	 * Resolve the base price for a room type on a specific date.
	 *
	 * Checks the inventory table first for a price_override (date-specific
	 * price). Falls back to the room type's base_price.
	 *
	 * @param int    $roomTypeId Room type ID.
	 * @param string $date       The date (Y-m-d).
	 * @return float The base price.
	 */
	private function resolveBasePrice( int $roomTypeId, string $date ): float {
		$inventory = $this->inventoryRepo->getForDate( $roomTypeId, $date );

		if ( $inventory !== null ) {
			$override = $inventory->getEffectivePrice();
			if ( $override !== null ) {
				return $override;
			}
		}

		return $this->getRoomTypeBasePrice( $roomTypeId );
	}

	/**
	 * Get the base price for a room type.
	 *
	 * Results are cached in memory during the request to avoid
	 * repeated database lookups when pricing multi-night stays.
	 *
	 * @param int $roomTypeId Room type ID.
	 * @return float The base price (0 if room type not found).
	 */
	private function getRoomTypeBasePrice( int $roomTypeId ): float {
		static $basePrices = [];

		if ( ! isset( $basePrices[ $roomTypeId ] ) ) {
			$roomType = $this->roomTypeRepo->find( $roomTypeId );
			$basePrices[ $roomTypeId ] = $roomType ? (float) $roomType->base_price : 0.0;
		}

		return $basePrices[ $roomTypeId ];
	}

	/**
	 * Generate an array of dates for each night of a stay.
	 *
	 * Returns dates from check-in up to (but not including) check-out,
	 * since a guest does not stay on the check-out night.
	 *
	 * @param string $checkIn  Check-in date (Y-m-d).
	 * @param string $checkOut Check-out date (Y-m-d).
	 * @return string[] Array of Y-m-d date strings.
	 */
	private function getDateRange( string $checkIn, string $checkOut ): array {
		$dates   = [];
		$current = new \DateTimeImmutable( $checkIn );
		$end     = new \DateTimeImmutable( $checkOut );

		while ( $current < $end ) {
			$dates[] = $current->format( 'Y-m-d' );
			$current = $current->modify( '+1 day' );
		}

		return $dates;
	}
}
