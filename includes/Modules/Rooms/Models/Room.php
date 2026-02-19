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
	public static function validStatuses(): array {
		return [
			self::STATUS_AVAILABLE,
			self::STATUS_OCCUPIED,
			self::STATUS_MAINTENANCE,
			self::STATUS_OUT_OF_ORDER,
		];
	}

	/**
	 * @var string[]
	 */
	protected static array $intFields = [
		'id',
		'room_type_id',
		'floor',
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

		return new static( $data );
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
