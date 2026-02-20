<?php

namespace Nozule\Modules\Pricing\Models;

use Nozule\Core\BaseModel;

/**
 * Day-of-Week Rule model.
 *
 * Defines a pricing modifier for a specific day of the week.
 * Day numbering follows PHP's 'w' format: 0 = Sunday, 6 = Saturday.
 *
 * @property int      $id
 * @property int|null $room_type_id   Null means applies to all room types.
 * @property int      $day_of_week    0 = Sunday, 1 = Monday, ... 6 = Saturday.
 * @property string   $modifier_type  'percentage' | 'fixed'
 * @property float    $modifier_value The adjustment amount.
 * @property string   $status         'active' | 'inactive'
 * @property string   $created_at
 * @property string   $updated_at
 */
class DowRule extends BaseModel {

	/**
	 * Day name mapping (English).
	 *
	 * @var string[]
	 */
	private static array $dayNames = [
		0 => 'Sunday',
		1 => 'Monday',
		2 => 'Tuesday',
		3 => 'Wednesday',
		4 => 'Thursday',
		5 => 'Friday',
		6 => 'Saturday',
	];

	/**
	 * Day name mapping (Arabic).
	 *
	 * @var string[]
	 */
	private static array $dayNamesAr = [
		0 => "\u{0627}\u{0644}\u{0623}\u{062D}\u{062F}",
		1 => "\u{0627}\u{0644}\u{0627}\u{062B}\u{0646}\u{064A}\u{0646}",
		2 => "\u{0627}\u{0644}\u{062B}\u{0644}\u{0627}\u{062B}\u{0627}\u{0621}",
		3 => "\u{0627}\u{0644}\u{0623}\u{0631}\u{0628}\u{0639}\u{0627}\u{0621}",
		4 => "\u{0627}\u{0644}\u{062E}\u{0645}\u{064A}\u{0633}",
		5 => "\u{0627}\u{0644}\u{062C}\u{0645}\u{0639}\u{0629}",
		6 => "\u{0627}\u{0644}\u{0633}\u{0628}\u{062A}",
	];

	/**
	 * Fields that should be cast to integers.
	 *
	 * @var string[]
	 */
	protected static array $intFields = [
		'id',
		'room_type_id',
		'day_of_week',
	];

	/**
	 * Fields that should be cast to floats.
	 *
	 * @var string[]
	 */
	protected static array $floatFields = [
		'modifier_value',
	];

	/**
	 * Create from a database row, casting types automatically.
	 */
	public static function fromRow( object $row ): static {
		$data = (array) $row;

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
	 * Convert to array for database storage.
	 */
	public function toArray(): array {
		return parent::toArray();
	}

	/**
	 * Convert to a public-facing array for API responses.
	 */
	public function toPublicArray(): array {
		$data = parent::toArray();
		$data['day_name']    = $this->getDayName();
		$data['day_name_ar'] = $this->getDayName( 'ar' );
		return $data;
	}

	/**
	 * Get the human-readable day name.
	 *
	 * @param string $locale 'en' or 'ar'.
	 */
	public function getDayName( string $locale = 'en' ): string {
		$day = $this->day_of_week;

		if ( $locale === 'ar' ) {
			return self::$dayNamesAr[ $day ] ?? '';
		}

		return self::$dayNames[ $day ] ?? '';
	}

	/**
	 * Check whether this rule is active.
	 */
	public function isActive(): bool {
		return $this->status === 'active';
	}
}
