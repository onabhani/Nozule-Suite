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
	protected static array $boolFields = [
		'stop_sell',
	];

	/**
	 * Check whether rooms are available on this date.
	 */

	protected static array $casts = [
		'id' => 'int',
		'room_type_id' => 'int',
		'total_rooms' => 'int',
		'available_rooms' => 'int',
		'booked_rooms' => 'int',
		'min_stay' => 'int',
		'price_override' => 'float',
	];

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
