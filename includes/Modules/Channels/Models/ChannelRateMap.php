<?php

namespace Nozule\Modules\Channels\Models;

use Nozule\Core\BaseModel;

/**
 * Channel Rate Map model.
 *
 * Maps a local room type and rate plan to their corresponding
 * identifiers on an OTA channel.
 *
 * @property int    $id
 * @property string $channel_name       OTA identifier (e.g. 'booking_com').
 * @property int    $local_room_type_id Local room type ID.
 * @property int    $local_rate_plan_id Local rate plan ID (0 = base rate).
 * @property string $channel_room_id    Room/property ID on the OTA side.
 * @property string $channel_rate_id    Rate plan ID on the OTA side.
 * @property int    $is_active          Whether the mapping is active (0/1).
 * @property string $created_at
 * @property string $updated_at
 */
class ChannelRateMap extends BaseModel {

	/**
	 * @var string[]
	 */
	protected static array $intFields = [
		'id',
		'local_room_type_id',
		'local_rate_plan_id',
		'is_active',
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

	/**
	 * Check whether this mapping is active.
	 */
	public function isActive(): bool {
		return (bool) $this->is_active;
	}
}
