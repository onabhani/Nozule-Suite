<?php

namespace Nozule\Modules\Pricing\Models;

use Nozule\Core\BaseModel;

/**
 * Event Override model.
 *
 * Defines a named event (e.g. "Eid Holiday") with a date range and
 * pricing modifier that overrides normal rates during the event period.
 *
 * @property int      $id
 * @property string   $name             Event name (English).
 * @property string   $name_ar          Event name (Arabic).
 * @property int|null $room_type_id     Null means applies to all room types.
 * @property string   $start_date       Y-m-d
 * @property string   $end_date         Y-m-d
 * @property string   $modifier_type    'percentage' | 'fixed'
 * @property float    $modifier_value   The adjustment amount.
 * @property int      $priority         Higher priority takes precedence.
 * @property string   $status           'active' | 'inactive'
 * @property string   $created_at
 * @property string   $updated_at
 */
class EventOverride extends BaseModel {

	/**
	 * Fields that should be cast to integers.
	 *
	 * @var string[]
	 */
	protected static array $intFields = [
		'id',
		'room_type_id',
		'priority',
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
		return parent::toArray();
	}

	/**
	 * Check whether this event override is active.
	 */
	public function isActive(): bool {
		return $this->status === 'active';
	}

	/**
	 * Check whether this event applies to a specific date.
	 */
	public function appliesToDate( string $date ): bool {
		if ( ! $this->isActive() ) {
			return false;
		}

		return $date >= $this->start_date && $date <= $this->end_date;
	}

	/**
	 * Check whether this event applies to a given room type.
	 *
	 * A null room_type_id means it applies to all types.
	 */
	public function appliesToRoomType( ?int $roomTypeId ): bool {
		return $this->room_type_id === null || $this->room_type_id === $roomTypeId;
	}
}
