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

	protected static array $intFields = [
		'id',
		'guest_id',
		'tier_id',
		'points_balance',
		'lifetime_points',
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

		return new static( $data );
	}
}
