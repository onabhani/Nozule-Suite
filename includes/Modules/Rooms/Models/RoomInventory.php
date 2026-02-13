<?php

namespace Nozule\Modules\Rooms\Models;

use Nozule\Core\BaseModel;

/**
 * Room Inventory model.
 *
 * Tracks the daily availability of each room type. One row per room type
 * per date, recording total rooms, rooms available, and override flags.
 *
 * @property int    $id
 * @property int    $room_type_id
 * @property string $date           Y-m-d
 * @property int    $total_rooms
 * @property int    $available_rooms
 * @property int    $booked_rooms
 * @property float  $price_override  Nullable; overrides the room type's base price for this date.
 * @property bool   $stop_sell       If true, this room type cannot be booked on this date.
 * @property int    $min_stay
 * @property string $created_at
 * @property string $updated_at
 */
class RoomInventory extends BaseModel {

	/**
	 * @var string[]
	 */
	protected static array $intFields = [
		'id',
		'room_type_id',
		'total_rooms',
		'available_rooms',
		'booked_rooms',
		'min_stay',
	];

	/**
	 * @var string[]
	 */
	protected static array $floatFields = [
		'price_override',
	];

	/**
	 * @var string[]
	 */
	protected static array $boolFields = [
		'stop_sell',
	];

	/**
	 * Create from a database row with type casting.
	 */
	public static function fromRow( object $row ): static {
		$data = (array) $row;

		foreach ( static::$intFields as $field ) {
			if ( isset( $data[ $field ] ) ) {
				$data[ $field ] = (int) $data[ $field ];
			}
		}

		foreach ( static::$floatFields as $field ) {
			if ( isset( $data[ $field ] ) && $data[ $field ] !== null ) {
				$data[ $field ] = (float) $data[ $field ];
			}
		}

		foreach ( static::$boolFields as $field ) {
			if ( isset( $data[ $field ] ) ) {
				$data[ $field ] = (bool) $data[ $field ];
			}
		}

		return new static( $data );
	}

	/**
	 * Check whether rooms are available on this date.
	 */
	public function hasAvailability(): bool {
		return $this->available_rooms > 0 && ! $this->stop_sell;
	}

	/**
	 * Get the effective price for this date (override or null to use room type base price).
	 */
	public function getEffectivePrice(): ?float {
		return $this->price_override;
	}
}
