<?php

namespace Nozule\Modules\Loyalty\Models;

use Nozule\Core\BaseModel;

/**
 * Loyalty reward model.
 *
 * @property int    $id
 * @property string $name
 * @property string $name_ar
 * @property int    $points_cost
 * @property string $type          discount|free_night|upgrade|amenity
 * @property string $value
 * @property string $description
 * @property string $description_ar
 * @property bool   $is_active
 * @property string $created_at
 * @property string $updated_at
 */
class LoyaltyReward extends BaseModel {

	public const TYPE_DISCOUNT   = 'discount';
	public const TYPE_FREE_NIGHT = 'free_night';
	public const TYPE_UPGRADE    = 'upgrade';
	public const TYPE_AMENITY    = 'amenity';

	protected static array $intFields = [
		'id',
		'points_cost',
	];

	protected static array $boolFields = [
		'is_active',
	];

	/**
	 * Valid reward type values.
	 *
	 * @return string[]
	 */
	public static function validTypes(): array {
		return [
			self::TYPE_DISCOUNT,
			self::TYPE_FREE_NIGHT,
			self::TYPE_UPGRADE,
			self::TYPE_AMENITY,
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

		foreach ( static::$boolFields as $field ) {
			if ( isset( $data[ $field ] ) ) {
				$data[ $field ] = (bool) (int) $data[ $field ];
			}
		}

		return new static( $data );
	}
}
