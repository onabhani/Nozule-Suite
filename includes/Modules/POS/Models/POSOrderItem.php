<?php

namespace Nozule\Modules\POS\Models;

use Nozule\Core\BaseModel;

/**
 * POS Order Item model.
 *
 * Represents a single line item within a POS order.
 *
 * @property int    $id
 * @property int    $order_id
 * @property int    $item_id
 * @property string $item_name
 * @property int    $quantity
 * @property float  $unit_price
 * @property float  $subtotal
 * @property string $created_at
 */
class POSOrderItem extends BaseModel {

	/** @var string[] */
	protected static array $intFields = [
		'id',
		'order_id',
		'item_id',
		'quantity',
	];

	/** @var string[] */
	protected static array $floatFields = [
		'unit_price',
		'subtotal',
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
			if ( isset( $data[ $field ] ) ) {
				$data[ $field ] = (float) $data[ $field ];
			}
		}

		return new static( $data );
	}
}
