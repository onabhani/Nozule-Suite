<?php

namespace Nozule\Modules\Loyalty\Models;

use Nozule\Core\BaseModel;

/**
 * Loyalty tier model.
 *
 * @property int    $id
 * @property string $name
 * @property string $name_ar
 * @property int    $min_points
 * @property float  $discount_percent
 * @property string $benefits
 * @property string $benefits_ar
 * @property string $color
 * @property int    $sort_order
 * @property string $created_at
 * @property string $updated_at
 */
class LoyaltyTier extends BaseModel {

	protected static array $intFields = [
		'id',
		'min_points',
		'sort_order',
	];

	protected static array $floatFields = [
		'discount_percent',
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
