<?php

namespace Nozule\Modules\Groups\Models;

use Nozule\Core\BaseModel;

/**
 * Group Booking Room model.
 *
 * Represents a single room allocation within a group booking.
 *
 * @property int         $id
 * @property int         $group_booking_id   Parent group booking.
 * @property int|null    $booking_id         Linked individual booking (if created).
 * @property int         $room_type_id
 * @property int|null    $room_id            Assigned physical room.
 * @property string|null $guest_name
 * @property int|null    $guest_id
 * @property float       $rate_per_night
 * @property string      $status             reserved|checked_in|checked_out|cancelled
 * @property string|null $notes
 * @property string      $created_at
 * @property string      $updated_at
 */
class GroupBookingRoom extends BaseModel {

	// ── Status Constants ────────────────────────────────────────────

	public const STATUS_RESERVED    = 'reserved';
	public const STATUS_CHECKED_IN  = 'checked_in';
	public const STATUS_CHECKED_OUT = 'checked_out';
	public const STATUS_CANCELLED   = 'cancelled';

	// ── Type Casting ────────────────────────────────────────────────

	/** @var array<string, string> */
	protected static array $casts = [
		'id'                => 'int',
		'group_booking_id'  => 'int',
		'booking_id'        => 'int',
		'room_type_id'      => 'int',
		'room_id'           => 'int',
		'guest_id'          => 'int',
		'rate_per_night'    => 'float',
	];

	/**
	 * @return string[]
	 */
	public static function validStatuses(): array {
		return [
			self::STATUS_RESERVED,
			self::STATUS_CHECKED_IN,
			self::STATUS_CHECKED_OUT,
			self::STATUS_CANCELLED,
		];
	}

	// ── Overrides ───────────────────────────────────────────────────

	/**
	 * Fill attributes, applying type casts.
	 */
	public function fill( array $attributes ): static {
		foreach ( $attributes as $key => $value ) {
			if ( $value !== null && isset( static::$casts[ $key ] ) ) {
				$value = match ( static::$casts[ $key ] ) {
					'int'   => (int) $value,
					'float' => (float) $value,
					default => $value,
				};
			}
			$this->attributes[ $key ] = $value;
		}
		return $this;
	}

	// ── Status Helpers ──────────────────────────────────────────────

	public function isReserved(): bool {
		return $this->status === self::STATUS_RESERVED;
	}

	public function isCheckedIn(): bool {
		return $this->status === self::STATUS_CHECKED_IN;
	}

	public function isCheckedOut(): bool {
		return $this->status === self::STATUS_CHECKED_OUT;
	}

	public function isCancelled(): bool {
		return $this->status === self::STATUS_CANCELLED;
	}
}
