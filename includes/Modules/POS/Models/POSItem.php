<?php

namespace Nozule\Modules\POS\Models;

use Nozule\Core\BaseModel;

/**
 * POS Item model.
 *
 * Represents a purchasable item within a POS outlet.
 *
 * @property int         $id
 * @property int         $outlet_id
 * @property string      $name
 * @property string|null $name_ar
 * @property string|null $category
 * @property float       $price
 * @property string      $status       active|inactive
 * @property int         $sort_order
 * @property string      $created_at
 * @property string      $updated_at
 */
class POSItem extends BaseModel {

	public const STATUS_ACTIVE   = 'active';
	public const STATUS_INACTIVE = 'inactive';

	/** @var string[] */
	protected static array $intFields = [
		'id',
		'outlet_id',
		'sort_order',
	];

	/** @var string[] */
	protected static array $floatFields = [
		'price',
	];

	/**
	 * All valid statuses.
	 *
	 * @return string[]
	 */
	public static function validStatuses(): array {
		return [
			self::STATUS_ACTIVE,
			self::STATUS_INACTIVE,
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
	 * Check whether this item is active.
	 */
	public function isActive(): bool {
		return $this->status === self::STATUS_ACTIVE;
	}
}
