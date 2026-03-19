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
	 * Create from a database row, casting types automatically.
	 */
	/**
	 * Convert to array for database storage.
	 */

	protected static array $casts = [
		'id' => 'int',
		'room_type_id' => 'int',
		'threshold_percent' => 'int',
		'priority' => 'int',
		'modifier_value' => 'float',
	];

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
