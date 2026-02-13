<?php

namespace Nozule\Modules\Bookings\Models;

use Nozule\Core\BaseModel;

/**
 * Booking model.
 *
 * Represents a single hotel reservation with computed balance tracking.
 *
 * @property int         $id
 * @property string      $booking_number    Unique reference (e.g. NZL-2025-00001).
 * @property int         $guest_id
 * @property int         $room_type_id
 * @property int|null    $room_id           Assigned physical room (may be null until check-in).
 * @property string      $check_in          Y-m-d
 * @property string      $check_out         Y-m-d
 * @property int         $nights
 * @property int         $adults
 * @property int         $children
 * @property string      $status            pending|confirmed|checked_in|checked_out|cancelled|no_show
 * @property string      $source            direct|website|booking_com|expedia|airbnb|phone|walk_in
 * @property float       $total_amount
 * @property float       $paid_amount
 * @property string      $currency
 * @property string|null $special_requests
 * @property string|null $internal_notes
 * @property string|null $cancellation_reason
 * @property int|null    $cancelled_by      User ID who cancelled.
 * @property string|null $cancelled_at
 * @property string|null $confirmed_at
 * @property string|null $checked_in_at
 * @property string|null $checked_out_at
 * @property string      $created_at
 * @property string      $updated_at
 * @property int|null    $created_by        User ID who created the booking.
 * @property string|null $ip_address        Client IP at booking time.
 *
 * Computed:
 * @property float       $balance_due       total_amount - paid_amount
 */
class Booking extends BaseModel {

	// ── Status Constants ────────────────────────────────────────────

	public const STATUS_PENDING     = 'pending';
	public const STATUS_CONFIRMED   = 'confirmed';
	public const STATUS_CHECKED_IN  = 'checked_in';
	public const STATUS_CHECKED_OUT = 'checked_out';
	public const STATUS_CANCELLED   = 'cancelled';
	public const STATUS_NO_SHOW     = 'no_show';

	// ── Source Constants ────────────────────────────────────────────

	public const SOURCE_DIRECT      = 'direct';
	public const SOURCE_WEBSITE     = 'website';
	public const SOURCE_BOOKING_COM = 'booking_com';
	public const SOURCE_EXPEDIA     = 'expedia';
	public const SOURCE_AIRBNB      = 'airbnb';
	public const SOURCE_PHONE       = 'phone';
	public const SOURCE_WALK_IN     = 'walk_in';

	// ── Type Casting ────────────────────────────────────────────────

	/** @var array<string, string> */
	protected static array $casts = [
		'id'            => 'int',
		'guest_id'      => 'int',
		'room_type_id'  => 'int',
		'room_id'       => 'int',
		'nights'        => 'int',
		'adults'        => 'int',
		'children'      => 'int',
		'total_amount'  => 'float',
		'paid_amount'   => 'float',
		'cancelled_by'  => 'int',
		'created_by'    => 'int',
	];

	/**
	 * @return string[]
	 */
	public static function validStatuses(): array {
		return [
			self::STATUS_PENDING,
			self::STATUS_CONFIRMED,
			self::STATUS_CHECKED_IN,
			self::STATUS_CHECKED_OUT,
			self::STATUS_CANCELLED,
			self::STATUS_NO_SHOW,
		];
	}

	/**
	 * @return string[]
	 */
	public static function validSources(): array {
		return [
			self::SOURCE_DIRECT,
			self::SOURCE_WEBSITE,
			self::SOURCE_BOOKING_COM,
			self::SOURCE_EXPEDIA,
			self::SOURCE_AIRBNB,
			self::SOURCE_PHONE,
			self::SOURCE_WALK_IN,
		];
	}

	/**
	 * Statuses that are considered "active" (occupying inventory).
	 *
	 * @return string[]
	 */
	public static function activeStatuses(): array {
		return [
			self::STATUS_CONFIRMED,
			self::STATUS_CHECKED_IN,
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

	/**
	 * Access computed properties.
	 *
	 * @return mixed
	 */
	public function __get( string $name ) {
		if ( $name === 'balance_due' ) {
			return $this->getBalanceDue();
		}

		return parent::__get( $name );
	}

	/**
	 * Support isset() for computed properties.
	 */
	public function __isset( string $name ): bool {
		if ( $name === 'balance_due' ) {
			return isset( $this->attributes['total_amount'] );
		}

		return parent::__isset( $name );
	}

	// ── Computed Properties ─────────────────────────────────────────

	/**
	 * Calculate the outstanding balance.
	 */
	public function getBalanceDue(): float {
		$total = (float) ( $this->attributes['total_amount'] ?? 0.0 );
		$paid  = (float) ( $this->attributes['paid_amount'] ?? 0.0 );

		return round( $total - $paid, 2 );
	}

	// ── Status Helpers ──────────────────────────────────────────────

	public function isPending(): bool {
		return $this->status === self::STATUS_PENDING;
	}

	public function isConfirmed(): bool {
		return $this->status === self::STATUS_CONFIRMED;
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

	public function isNoShow(): bool {
		return $this->status === self::STATUS_NO_SHOW;
	}

	/**
	 * Whether the booking is in an active state (holds inventory).
	 */
	public function isActive(): bool {
		return in_array( $this->status, self::activeStatuses(), true );
	}

	/**
	 * Whether the booking may be cancelled from its current state.
	 */
	public function isCancellable(): bool {
		return in_array( $this->status, [ self::STATUS_PENDING, self::STATUS_CONFIRMED ], true );
	}

	// ── Serialisation ───────────────────────────────────────────────

	/**
	 * Convert to array with computed balance_due included.
	 */
	public function toArray(): array {
		$data                = parent::toArray();
		$data['balance_due'] = $this->getBalanceDue();

		return $data;
	}
}
