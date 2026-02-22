<?php

namespace Nozule\Modules\Loyalty\Models;

use Nozule\Core\BaseModel;

/**
 * Loyalty transaction model.
 *
 * @property int    $id
 * @property int    $member_id
 * @property string $type         earn|redeem|adjust
 * @property int    $points
 * @property int    $balance_after
 * @property int    $booking_id
 * @property int    $reward_id
 * @property string $description
 * @property int    $created_by
 * @property string $created_at
 */
class LoyaltyTransaction extends BaseModel {

	public const TYPE_EARN   = 'earn';
	public const TYPE_REDEEM = 'redeem';
	public const TYPE_ADJUST = 'adjust';

	protected static array $intFields = [
		'id',
		'member_id',
		'points',
		'balance_after',
		'booking_id',
		'reward_id',
		'created_by',
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
