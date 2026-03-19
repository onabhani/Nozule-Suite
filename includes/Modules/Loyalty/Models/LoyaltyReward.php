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

	protected static array $boolFields = [
		'is_active',
	];

	/**
	 * Valid reward type values.
	 *
	 * @return string[]
	 */

	protected static array $casts = [
		'id' => 'int',
		'points_cost' => 'int',
	];

	public static function validTypes(): array {
		return [
			self::TYPE_DISCOUNT,
			self::TYPE_FREE_NIGHT,
			self::TYPE_UPGRADE,
			self::TYPE_AMENITY,
		];
	}
}
