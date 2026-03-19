<?php

namespace Nozule\Modules\Loyalty\Models;

use Nozule\Core\BaseModel;

/**
 * Loyalty member model.
 *
 * @property int    $id
 * @property int    $guest_id
 * @property int    $tier_id
 * @property int    $points_balance
 * @property int    $lifetime_points
 * @property string $enrolled_at
 * @property string $updated_at
 */
class LoyaltyMember extends BaseModel {

	/**
	 * Create from a database row with type casting.
	 */

	protected static array $casts = [
		'id' => 'int',
		'guest_id' => 'int',
		'tier_id' => 'int',
		'points_balance' => 'int',
		'lifetime_points' => 'int',
	];

	public static function fromRow( object $row ): static {
		$data = (array) $row;

		// Remap DB column to model attribute.
		if ( isset( $data['joined_at'] ) && ! isset( $data['enrolled_at'] ) ) {
			$data['enrolled_at'] = $data['joined_at'];
			unset( $data['joined_at'] );
		}

		// Type casting handled by BaseModel::fill() via static::$casts.
		return new static( $data );
	}
}
