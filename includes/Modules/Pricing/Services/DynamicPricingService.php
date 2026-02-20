<?php

namespace Nozule\Modules\Pricing\Services;

use Nozule\Modules\Pricing\Models\DowRule;
use Nozule\Modules\Pricing\Models\EventOverride;
use Nozule\Modules\Pricing\Models\OccupancyRule;
use Nozule\Modules\Pricing\Repositories\DynamicPricingRepository;

/**
 * Dynamic Pricing Service.
 *
 * Combines occupancy-based, day-of-week, and event-based pricing
 * modifiers to produce a single additive modifier for a given
 * room type and date.
 */
class DynamicPricingService {

	private DynamicPricingRepository $repository;

	public function __construct( DynamicPricingRepository $repository ) {
		$this->repository = $repository;
	}

	/**
	 * Calculate the combined dynamic pricing modifier for a room type and date.
	 *
	 * The result is the sum of all applicable modifiers (additive):
	 *   - Highest-threshold matching occupancy rule modifier
	 *   - All matching day-of-week rule modifiers
	 *   - All matching event override modifiers
	 *
	 * For percentage modifiers, the returned value represents percentage points
	 * (e.g. +10 means +10%). For fixed modifiers, it represents an absolute amount.
	 *
	 * Since modifiers can be of mixed types (percentage and fixed), the return
	 * is an associative array with separate sums for each type.
	 *
	 * @param int    $roomTypeId The room type to price.
	 * @param string $date       The target date (Y-m-d).
	 * @return array{percentage: float, fixed: float} Combined modifiers by type.
	 */
	public function calculateDynamicModifier( int $roomTypeId, string $date ): array {
		$percentageModifier = 0.0;
		$fixedModifier      = 0.0;

		// ── 1. Occupancy-based pricing ────────────────────────────
		$occupancyModifier = $this->getOccupancyModifier( $roomTypeId, $date );
		if ( $occupancyModifier !== null ) {
			if ( $occupancyModifier['type'] === 'percentage' ) {
				$percentageModifier += $occupancyModifier['value'];
			} else {
				$fixedModifier += $occupancyModifier['value'];
			}
		}

		// ── 2. Day-of-week pricing ────────────────────────────────
		$dowModifiers = $this->getDowModifiers( $roomTypeId, $date );
		foreach ( $dowModifiers as $mod ) {
			if ( $mod['type'] === 'percentage' ) {
				$percentageModifier += $mod['value'];
			} else {
				$fixedModifier += $mod['value'];
			}
		}

		// ── 3. Event-based overrides ──────────────────────────────
		$eventModifiers = $this->getEventModifiers( $roomTypeId, $date );
		foreach ( $eventModifiers as $mod ) {
			if ( $mod['type'] === 'percentage' ) {
				$percentageModifier += $mod['value'];
			} else {
				$fixedModifier += $mod['value'];
			}
		}

		return [
			'percentage' => round( $percentageModifier, 2 ),
			'fixed'      => round( $fixedModifier, 2 ),
		];
	}

	/**
	 * Apply the dynamic pricing modifier to a base price.
	 *
	 * This is a convenience method that takes a price and returns
	 * the adjusted price after applying dynamic modifiers.
	 *
	 * @param float  $price      The current price before dynamic adjustment.
	 * @param int    $roomTypeId The room type.
	 * @param string $date       The date (Y-m-d).
	 * @return float The adjusted price (never negative).
	 */
	public function applyDynamicPricing( float $price, int $roomTypeId, string $date ): float {
		$modifiers = $this->calculateDynamicModifier( $roomTypeId, $date );

		// Apply percentage modifier first, then fixed.
		if ( $modifiers['percentage'] !== 0.0 ) {
			$price = $price * ( 1 + ( $modifiers['percentage'] / 100 ) );
		}

		if ( $modifiers['fixed'] !== 0.0 ) {
			$price = $price + $modifiers['fixed'];
		}

		return max( 0.0, round( $price, 2 ) );
	}

	/**
	 * Get the occupancy-based modifier for a room type on a date.
	 *
	 * Returns the modifier from the highest-threshold rule whose
	 * threshold is met by the current occupancy, or null if none apply.
	 *
	 * @return array{type: string, value: float}|null
	 */
	private function getOccupancyModifier( int $roomTypeId, string $date ): ?array {
		$occupancyPercent = $this->repository->getOccupancyPercent( $roomTypeId, $date );
		$rules            = $this->repository->getActiveOccupancyRules( $roomTypeId );

		if ( empty( $rules ) ) {
			return null;
		}

		// Rules are ordered by threshold ascending. Walk backwards to find
		// the highest threshold that is met.
		$matchingRule = null;

		foreach ( $rules as $rule ) {
			if ( $occupancyPercent >= $rule->threshold_percent ) {
				$matchingRule = $rule;
			}
		}

		if ( $matchingRule === null ) {
			return null;
		}

		return [
			'type'  => $matchingRule->modifier_type,
			'value' => $matchingRule->modifier_value,
		];
	}

	/**
	 * Get day-of-week modifiers for a room type on a date.
	 *
	 * @return array<int, array{type: string, value: float}>
	 */
	private function getDowModifiers( int $roomTypeId, string $date ): array {
		// PHP 'w' format: 0 = Sunday, 6 = Saturday.
		$dayOfWeek = (int) ( new \DateTimeImmutable( $date ) )->format( 'w' );
		$rules     = $this->repository->getActiveDowRules( $dayOfWeek, $roomTypeId );
		$modifiers = [];

		foreach ( $rules as $rule ) {
			$modifiers[] = [
				'type'  => $rule->modifier_type,
				'value' => $rule->modifier_value,
			];
		}

		return $modifiers;
	}

	/**
	 * Get event-based modifiers for a room type on a date.
	 *
	 * @return array<int, array{type: string, value: float}>
	 */
	private function getEventModifiers( int $roomTypeId, string $date ): array {
		$events    = $this->repository->getActiveEventOverrides( $date, $roomTypeId );
		$modifiers = [];

		foreach ( $events as $event ) {
			$modifiers[] = [
				'type'  => $event->modifier_type,
				'value' => $event->modifier_value,
			];
		}

		return $modifiers;
	}
}
