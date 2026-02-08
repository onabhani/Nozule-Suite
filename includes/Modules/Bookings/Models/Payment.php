<?php

namespace Venezia\Modules\Bookings\Models;

use Venezia\Core\BaseModel;

/**
 * Payment model.
 *
 * Represents a single payment transaction associated with a booking.
 *
 * @property int         $id
 * @property int         $booking_id
 * @property float       $amount
 * @property string      $currency
 * @property string      $method          cash|credit_card|bank_transfer|online|other
 * @property string      $status          completed|pending|refunded|failed
 * @property string|null $transaction_id  External payment gateway reference.
 * @property string|null $notes
 * @property int|null    $recorded_by     WordPress user who recorded the payment.
 * @property string      $payment_date    Y-m-d H:i:s
 * @property string      $created_at
 * @property string      $updated_at
 */
class Payment extends BaseModel {

	// ── Method Constants ────────────────────────────────────────────

	public const METHOD_CASH          = 'cash';
	public const METHOD_CREDIT_CARD   = 'credit_card';
	public const METHOD_BANK_TRANSFER = 'bank_transfer';
	public const METHOD_ONLINE        = 'online';
	public const METHOD_OTHER         = 'other';

	// ── Status Constants ────────────────────────────────────────────

	public const STATUS_COMPLETED = 'completed';
	public const STATUS_PENDING   = 'pending';
	public const STATUS_REFUNDED  = 'refunded';
	public const STATUS_FAILED    = 'failed';

	/** @var array<string, string> */
	protected static array $casts = [
		'id'          => 'int',
		'booking_id'  => 'int',
		'amount'      => 'float',
		'recorded_by' => 'int',
	];

	/**
	 * @return string[]
	 */
	public static function validMethods(): array {
		return [
			self::METHOD_CASH,
			self::METHOD_CREDIT_CARD,
			self::METHOD_BANK_TRANSFER,
			self::METHOD_ONLINE,
			self::METHOD_OTHER,
		];
	}

	/**
	 * @return string[]
	 */
	public static function validStatuses(): array {
		return [
			self::STATUS_COMPLETED,
			self::STATUS_PENDING,
			self::STATUS_REFUNDED,
			self::STATUS_FAILED,
		];
	}

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
	 * Whether this payment has been successfully completed.
	 */
	public function isCompleted(): bool {
		return $this->status === self::STATUS_COMPLETED;
	}

	/**
	 * Whether this payment has been refunded.
	 */
	public function isRefunded(): bool {
		return $this->status === self::STATUS_REFUNDED;
	}
}
