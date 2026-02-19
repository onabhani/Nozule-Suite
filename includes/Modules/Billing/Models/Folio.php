<?php

namespace Nozule\Modules\Billing\Models;

use Nozule\Core\BaseModel;

/**
 * Folio model.
 *
 * Represents an invoice / billing folio attached to a booking or group booking.
 *
 * @property int         $id
 * @property string      $folio_number
 * @property int|null    $booking_id
 * @property int|null    $group_booking_id
 * @property int         $guest_id
 * @property float       $subtotal
 * @property float       $tax_total
 * @property float       $discount_total
 * @property float       $grand_total
 * @property float       $paid_amount
 * @property string      $currency
 * @property string      $status          open|closed|void
 * @property string|null $notes
 * @property string|null $closed_at
 * @property int|null    $closed_by
 * @property int|null    $created_by
 * @property string      $created_at
 * @property string      $updated_at
 */
class Folio extends BaseModel {

	/**
	 * Status constants.
	 */
	public const STATUS_OPEN   = 'open';
	public const STATUS_CLOSED = 'closed';
	public const STATUS_VOID   = 'void';

	/**
	 * Fields that should be cast to integers.
	 *
	 * @var string[]
	 */
	protected static array $intFields = [
		'id',
		'booking_id',
		'group_booking_id',
		'guest_id',
		'closed_by',
		'created_by',
	];

	/**
	 * Fields that should be cast to floats.
	 *
	 * @var string[]
	 */
	protected static array $floatFields = [
		'subtotal',
		'tax_total',
		'discount_total',
		'grand_total',
		'paid_amount',
	];

	/**
	 * All valid status values.
	 *
	 * @return string[]
	 */
	public static function validStatuses(): array {
		return [
			self::STATUS_OPEN,
			self::STATUS_CLOSED,
			self::STATUS_VOID,
		];
	}

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
			if ( isset( $data[ $field ] ) ) {
				$data[ $field ] = (float) $data[ $field ];
			}
		}

		return new static( $data );
	}

	/**
	 * Check whether this folio is open.
	 */
	public function isOpen(): bool {
		return $this->status === self::STATUS_OPEN;
	}

	/**
	 * Check whether this folio is closed.
	 */
	public function isClosed(): bool {
		return $this->status === self::STATUS_CLOSED;
	}

	/**
	 * Check whether this folio is voided.
	 */
	public function isVoid(): bool {
		return $this->status === self::STATUS_VOID;
	}

	/**
	 * Get the outstanding balance (grand_total - paid_amount).
	 */
	public function getBalance(): float {
		return round( (float) $this->grand_total - (float) $this->paid_amount, 2 );
	}
}
