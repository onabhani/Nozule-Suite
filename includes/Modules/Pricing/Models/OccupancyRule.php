<?php

namespace Nozule\Modules\Pricing\Models;

use Nozule\Core\BaseModel;

/**
 * Occupancy Rule model.
 *
 * Defines a pricing modifier that kicks in when occupancy for a
 * room type exceeds a configurable threshold percentage.
 *
 * @property int      $id
 * @property int|null $room_type_id       Null means applies to all room types.
 * @property int      $threshold_percent  The occupancy % that triggers this rule.
 * @property string   $modifier_type      'percentage' | 'fixed'
 * @property float    $modifier_value     The adjustment amount.
 * @property int      $priority           Higher priority takes precedence.
 * @property string   $status             'active' | 'inactive'
 * @property string   $created_at
 * @property string   $updated_at
 */
class OccupancyRule extends BaseModel {

	/**
	 * Fields that should be cast to integers.
	 *
	 * @var string[]
	 */
	protected static array $intFields = [
		'id',
		'room_type_id',
		'threshold_percent',
		'priority',
	];

	/**
	 * Fields that should be cast to floats.
	 *
	 * @var string[]
	 */
	protected static array $floatFields = [
		'modifier_value',
	];

	/**
	 * Create from a database row, casting types automatically.
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
	 * Convert to array for database storage.
	 */
	public function toArray(): array {
		return parent::toArray();
	}

	/**
	 * Convert to a public-facing array for API responses.
	 */
	public function toPublicArray(): array {
		return parent::toArray();
	}

	/**
	 * Check whether this rule is active.
	 */
	public function isActive(): bool {
		return $this->status === 'active';
	}
}
