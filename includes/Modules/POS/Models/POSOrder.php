<?php

namespace Nozule\Modules\POS\Models;

use Nozule\Core\BaseModel;

/**
 * POS Order model.
 *
 * Represents an order placed at a POS outlet, optionally linked to a room/booking.
 *
 * @property int         $id
 * @property string      $order_number
 * @property int         $outlet_id
 * @property string|null $room_number
 * @property int|null    $booking_id
 * @property int|null    $guest_id
 * @property int         $items_count
 * @property float       $subtotal
 * @property float       $tax_total
 * @property float       $total
 * @property string      $status          open|posted|cancelled
 * @property int|null    $folio_item_id   Reference to folio_items.id when posted
 * @property string|null $notes
 * @property int|null    $created_by
 * @property string      $created_at
 * @property string      $updated_at
 */
class POSOrder extends BaseModel {

	// -- Status Constants --

	public const STATUS_OPEN      = 'open';
	public const STATUS_POSTED    = 'posted';
	public const STATUS_CANCELLED = 'cancelled';

	/** @var string[] */
	/** @var string[] */
	/**
	 * All valid status values.
	 *
	 * @return string[]
	 */

	protected static array $casts = [
		'id' => 'int',
		'outlet_id' => 'int',
		'booking_id' => 'int',
		'guest_id' => 'int',
		'items_count' => 'int',
		'folio_item_id' => 'int',
		'created_by' => 'int',
		'subtotal' => 'float',
		'tax_total' => 'float',
		'total' => 'float',
	];

	public static function validStatuses(): array {
		return [
			self::STATUS_OPEN,
			self::STATUS_POSTED,
			self::STATUS_CANCELLED,
		];
	}

	/**
	 * Check whether this order is open.
	 */
	public function isOpen(): bool {
		return $this->status === self::STATUS_OPEN;
	}

	/**
	 * Check whether this order has been posted to folio.
	 */
	public function isPosted(): bool {
		return $this->status === self::STATUS_POSTED;
	}

	/**
	 * Check whether this order is cancelled.
	 */
	public function isCancelled(): bool {
		return $this->status === self::STATUS_CANCELLED;
	}
}
