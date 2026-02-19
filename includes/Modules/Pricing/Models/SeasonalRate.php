<?php

namespace Nozule\Modules\Pricing\Models;

use Nozule\Core\BaseModel;

/**
 * Seasonal Rate model.
 *
 * Overrides or adjusts pricing for a room type / rate plan combination
 * during a specific date range. Supports day-of-week restrictions so
 * that, for example, a weekend surcharge applies only on Fri/Sat.
 *
 * @property int         $id
 * @property string      $name              Human-readable label (e.g. "Summer Peak").
 * @property int         $room_type_id
 * @property int|null    $rate_plan_id      Null means applies to all rate plans.
 * @property string      $start_date        Y-m-d
 * @property string      $end_date          Y-m-d
 * @property string      $modifier_type     'percentage' | 'fixed' | 'absolute'
 * @property float       $modifier_value    The adjustment amount.
 * @property array       $days_of_week      JSON array of ISO day numbers (1=Mon..7=Sun). Empty = all days.
 * @property int         $priority          Higher priority overrides lower when ranges overlap.
 * @property int         $min_stay          Minimum nights for this seasonal rate to apply.
 * @property string      $status            'active' | 'inactive'
 * @property string      $created_at
 * @property string      $updated_at
 */
class SeasonalRate extends BaseModel {

	/**
	 * Fields stored as JSON in the database.
	 *
	 * @var string[]
	 */
	protected static array $jsonFields = [
		'days_of_week',
	];

	/**
	 * Fields that should be cast to integers.
	 *
	 * @var string[]
	 */
	protected static array $intFields = [
		'id',
		'room_type_id',
		'rate_plan_id',
		'priority',
	];

	/**
	 * Fields that should be cast to floats.
	 *
	 * @var string[]
	 */
	protected static array $floatFields = [
		'price_modifier',
	];

	/**
	 * Create from a database row, decoding JSON fields automatically.
	 */
	public static function fromRow( object $row ): static {
		$data = (array) $row;

		foreach ( static::$jsonFields as $field ) {
			if ( isset( $data[ $field ] ) && is_string( $data[ $field ] ) ) {
				$decoded = json_decode( $data[ $field ], true );
				$data[ $field ] = ( json_last_error() === JSON_ERROR_NONE ) ? $decoded : [];
			} elseif ( ! isset( $data[ $field ] ) ) {
				$data[ $field ] = [];
			}
		}

		foreach ( static::$intFields as $field ) {
			if ( isset( $data[ $field ] ) ) {
				$data[ $field ] = (int) $data[ $field ];
			}
		}

		foreach ( static::$floatFields as $field ) {
			if ( isset( $data[ $field ] ) ) {
				$data[ $field ] = (float) $data[ $field ];
			}
		}

		return new static( $data );
	}

	/**
	 * Convert to array, encoding JSON fields for database storage.
	 */
	public function toArray(): array {
		$data = parent::toArray();

		foreach ( static::$jsonFields as $field ) {
			if ( isset( $data[ $field ] ) && is_array( $data[ $field ] ) ) {
				$data[ $field ] = wp_json_encode( $data[ $field ] );
			}
		}

		return $data;
	}

	/**
	 * Convert to a public-facing array (JSON fields remain decoded).
	 */
	public function toPublicArray(): array {
		$data = parent::toArray();

		// Map DB column name → API field name for frontend compatibility.
		if ( array_key_exists( 'price_modifier', $data ) ) {
			$data['modifier_value'] = $data['price_modifier'];
			unset( $data['price_modifier'] );
		}

		return $data;
	}

	/**
	 * Check whether this seasonal rate is active.
	 */
	public function isActive(): bool {
		return $this->status === 'active';
	}

	/**
	 * Check whether this seasonal rate applies to a specific date.
	 *
	 * Checks that the date falls within the start/end range and,
	 * if days_of_week is set, that the day of week matches.
	 */
	public function appliesToDate( string $date ): bool {
		if ( ! $this->isActive() ) {
			return false;
		}

		if ( $date < $this->start_date || $date > $this->end_date ) {
			return false;
		}

		$daysOfWeek = $this->days_of_week;
		if ( ! empty( $daysOfWeek ) ) {
			$dayNumber = (int) ( new \DateTimeImmutable( $date ) )->format( 'N' );
			if ( ! in_array( $dayNumber, $daysOfWeek, true ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Check whether this seasonal rate applies to a given rate plan.
	 *
	 * A null rate_plan_id means the seasonal rate applies to all plans.
	 */
	public function appliesToRatePlan( ?int $ratePlanId ): bool {
		return $this->rate_plan_id === null || $this->rate_plan_id === $ratePlanId;
	}
}
