<?php

namespace Nozule\Modules\Rooms\Models;

use Nozule\Core\BaseModel;

/**
 * Room model.
 *
 * Represents an individual, physical room unit that belongs to a room type.
 *
 * @property int         $id
 * @property int         $room_type_id
 * @property string      $room_number
 * @property int         $floor
 * @property string      $status         available|occupied|maintenance|out_of_order
 * @property string|null $notes
 * @property string      $created_at
 * @property string      $updated_at
 */
class Room extends BaseModel {

	/**
	 * Allowed status values.
	 */
	public const STATUS_AVAILABLE    = 'available';
	public const STATUS_OCCUPIED     = 'occupied';
	public const STATUS_MAINTENANCE  = 'maintenance';
	public const STATUS_OUT_OF_ORDER = 'out_of_order';

	/**
	 * All valid status values.
	 *
	 * @return string[]
	 */

	protected static array $casts = [
		'id' => 'int',
		'room_type_id' => 'int',
		'floor' => 'int',
	];

	public static function validStatuses(): array {
		return [
			self::STATUS_AVAILABLE,
			self::STATUS_OCCUPIED,
			self::STATUS_MAINTENANCE,
			self::STATUS_OUT_OF_ORDER,
		];
	}

	/**
	 * Check whether this room is currently available for booking.
	 */
	public function isAvailable(): bool {
		return $this->status === self::STATUS_AVAILABLE;
	}

	/**
	 * Check whether this room is in a bookable status (not out-of-order or maintenance).
	 */
	public function isBookable(): bool {
		return in_array( $this->status, [ self::STATUS_AVAILABLE, self::STATUS_OCCUPIED ], true );
	}
}
