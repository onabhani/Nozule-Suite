<?php

namespace Nozule\Modules\Pricing\Models;

use Nozule\Core\BaseModel;

/**
 * Rate Restriction model.
 *
 * Represents a booking restriction (min_stay, max_stay, CTA, CTD, stop_sell)
 * applied to a room type / rate plan combination over a date range, with
 * optional day-of-week and channel filtering.
 *
 * @property int         $id
 * @property int         $room_type_id
 * @property int|null    $rate_plan_id      Null means applies to all rate plans.
 * @property string      $restriction_type  One of: min_stay, max_stay, cta, ctd, stop_sell.
 * @property int|null    $value             Number of nights for min_stay/max_stay; null for CTA/CTD/stop_sell.
 * @property string|null $channel           Channel name for stop_sell (e.g. 'booking_com'); null means all channels.
 * @property string      $date_from         Y-m-d
 * @property string      $date_to           Y-m-d
 * @property string|null $days_of_week      Comma-separated days: 'mon,tue,wed,thu,fri,sat,sun' or null for all.
 * @property int         $is_active         1 = active, 0 = inactive.
 * @property string      $created_at
 * @property string      $updated_at
 */
class RateRestriction extends BaseModel {

	/**
	 * Fields that should be cast to integers.
	 *
	 * @var string[]
	 */
	protected static array $intFields = [
		'id',
		'room_type_id',
		'rate_plan_id',
		'value',
		'is_active',
	];

	/**
	 * Map of short day names to PHP date('N') ISO day numbers.
	 *
	 * @var array<string, int>
	 */
	private static array $dayMap = [
		'mon' => 1,
		'tue' => 2,
		'wed' => 3,
		'thu' => 4,
		'fri' => 5,
		'sat' => 6,
		'sun' => 7,
	];

	/**
	 * Create from a database row, casting fields automatically.
	 */
	public static function fromRow( object $row ): static {
		$data = (array) $row;

		foreach ( static::$intFields as $field ) {
			if ( isset( $data[ $field ] ) ) {
				$data[ $field ] = (int) $data[ $field ];
			}
		}

		return new static( $data );
	}

	/**
	 * Check whether this restriction is active.
	 */
	public function isActive(): bool {
		return (int) $this->is_active === 1;
	}

	/**
	 * Check whether this restriction applies to a specific date.
	 *
	 * Verifies the date falls within date_from / date_to AND,
	 * if days_of_week is set, that the date's day of week matches.
	 *
	 * @param string $date A date string in Y-m-d format.
	 */
	public function appliesToDate( string $date ): bool {
		if ( $date < $this->date_from || $date > $this->date_to ) {
			return false;
		}

		$daysOfWeek = $this->days_of_week;

		if ( $daysOfWeek === null || $daysOfWeek === '' ) {
			return true;
		}

		$allowedDays = array_map( 'trim', explode( ',', strtolower( $daysOfWeek ) ) );
		$dayNumber   = (int) ( new \DateTimeImmutable( $date ) )->format( 'N' );

		foreach ( $allowedDays as $dayName ) {
			if ( isset( self::$dayMap[ $dayName ] ) && self::$dayMap[ $dayName ] === $dayNumber ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Check whether this restriction applies to a given rate plan.
	 *
	 * A null rate_plan_id on the restriction means it applies to all rate plans.
	 *
	 * @param int|null $ratePlanId The rate plan ID to check against.
	 */
	public function appliesToRatePlan( ?int $ratePlanId ): bool {
		if ( $this->rate_plan_id === null ) {
			return true;
		}

		return $this->rate_plan_id === $ratePlanId;
	}

	/**
	 * Check whether this restriction applies to a given channel.
	 *
	 * A null channel on the restriction means it applies to all channels.
	 *
	 * @param string|null $channelName The channel name to check against.
	 */
	public function appliesToChannel( ?string $channelName ): bool {
		if ( $this->channel === null || $this->channel === '' ) {
			return true;
		}

		return $this->channel === $channelName;
	}

	/**
	 * Convert to array representation.
	 */
	public function toArray(): array {
		return parent::toArray();
	}
}
