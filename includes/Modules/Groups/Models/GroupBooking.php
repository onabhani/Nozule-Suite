<?php

namespace Nozule\Modules\Groups\Models;

use Nozule\Core\BaseModel;

/**
 * Group Booking model.
 *
 * Represents a group reservation containing multiple room allocations.
 *
 * @property int         $id
 * @property string      $group_number       Unique reference (e.g. GRP-2025-00001).
 * @property string      $group_name
 * @property string|null $group_name_ar
 * @property string|null $contact_person
 * @property string|null $contact_phone
 * @property string|null $contact_email
 * @property string|null $agency_name
 * @property string|null $agency_name_ar
 * @property string      $check_in           Y-m-d
 * @property string      $check_out          Y-m-d
 * @property int         $nights
 * @property int         $total_rooms
 * @property int         $total_guests
 * @property float       $subtotal
 * @property float       $tax_total
 * @property float       $grand_total
 * @property float       $paid_amount
 * @property string      $currency
 * @property string      $status             tentative|confirmed|checked_in|checked_out|cancelled
 * @property string|null $payment_terms
 * @property string|null $notes
 * @property string|null $internal_notes
 * @property string|null $confirmed_at
 * @property string|null $cancelled_at
 * @property int|null    $created_by         User ID who created the group booking.
 * @property string      $created_at
 * @property string      $updated_at
 *
 * Computed:
 * @property float       $balance            grand_total - paid_amount
 */
class GroupBooking extends BaseModel {

	// ── Status Constants ────────────────────────────────────────────

	public const STATUS_TENTATIVE   = 'tentative';
	public const STATUS_CONFIRMED   = 'confirmed';
	public const STATUS_CHECKED_IN  = 'checked_in';
	public const STATUS_CHECKED_OUT = 'checked_out';
	public const STATUS_CANCELLED   = 'cancelled';

	// ── Type Casting ────────────────────────────────────────────────

	/** @var array<string, string> */
	protected static array $casts = [
		'id'           => 'int',
		'nights'       => 'int',
		'total_rooms'  => 'int',
		'total_guests' => 'int',
		'created_by'   => 'int',
		'subtotal'     => 'float',
		'tax_total'    => 'float',
		'grand_total'  => 'float',
		'paid_amount'  => 'float',
	];

	/**
	 * @return string[]
	 */
	public static function validStatuses(): array {
		return [
			self::STATUS_TENTATIVE,
			self::STATUS_CONFIRMED,
			self::STATUS_CHECKED_IN,
			self::STATUS_CHECKED_OUT,
			self::STATUS_CANCELLED,
		];
	}

	/**
	 * Statuses that are considered "active" (holding inventory).
	 *
	 * @return string[]
	 */
	public static function activeStatuses(): array {
		return [
			self::STATUS_TENTATIVE,
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
		if ( $name === 'balance' ) {
			return $this->getBalance();
		}

		return parent::__get( $name );
	}

	/**
	 * Support isset() for computed properties.
	 */
	public function __isset( string $name ): bool {
		if ( $name === 'balance' ) {
			return isset( $this->attributes['grand_total'] );
		}

		return parent::__isset( $name );
	}

	// ── Computed Properties ─────────────────────────────────────────

	/**
	 * Calculate the outstanding balance.
	 */
	public function getBalance(): float {
		$total = (float) ( $this->attributes['grand_total'] ?? 0.0 );
		$paid  = (float) ( $this->attributes['paid_amount'] ?? 0.0 );

		return round( $total - $paid, 2 );
	}

	// ── Status Helpers ──────────────────────────────────────────────

	public function isTentative(): bool {
		return $this->status === self::STATUS_TENTATIVE;
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

	/**
	 * Whether the group booking is in an active state (holds inventory).
	 */
	public function isActive(): bool {
		return in_array( $this->status, self::activeStatuses(), true );
	}

	/**
	 * Whether the group booking may be cancelled from its current state.
	 */
	public function isCancellable(): bool {
		return in_array( $this->status, [ self::STATUS_TENTATIVE, self::STATUS_CONFIRMED ], true );
	}

	// ── Serialisation ───────────────────────────────────────────────

	/**
	 * Convert to array with computed balance included.
	 */
	public function toArray(): array {
		$data            = parent::toArray();
		$data['balance'] = $this->getBalance();

		return $data;
	}
}
