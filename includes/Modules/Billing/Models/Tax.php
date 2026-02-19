<?php

namespace Nozule\Modules\Billing\Models;

use Nozule\Core\BaseModel;

/**
 * Tax model.
 *
 * Represents a tax rule that can be applied to folio line items.
 *
 * @property int    $id
 * @property string $name
 * @property string $name_ar
 * @property float  $rate
 * @property string $type        percentage|fixed
 * @property string $applies_to  all|room_charge|extra|service
 * @property int    $is_active
 * @property int    $sort_order
 * @property string $created_at
 * @property string $updated_at
 */
class Tax extends BaseModel {

	/**
	 * Tax type constants.
	 */
	public const TYPE_PERCENTAGE = 'percentage';
	public const TYPE_FIXED      = 'fixed';

	/**
	 * Applies-to constants.
	 */
	public const APPLIES_ALL     = 'all';
	public const APPLIES_ROOM    = 'room_charge';
	public const APPLIES_EXTRA   = 'extra';
	public const APPLIES_SERVICE = 'service';

	/**
	 * Fields that should be cast to integers.
	 *
	 * @var string[]
	 */
	protected static array $intFields = [
		'id',
		'is_active',
		'sort_order',
	];

	/**
	 * Fields that should be cast to floats.
	 *
	 * @var string[]
	 */
	protected static array $floatFields = [
		'rate',
	];

	/**
	 * All valid type values.
	 *
	 * @return string[]
	 */
	public static function validTypes(): array {
		return [
			self::TYPE_PERCENTAGE,
			self::TYPE_FIXED,
		];
	}

	/**
	 * All valid applies_to values.
	 *
	 * @return string[]
	 */
	public static function validAppliesTo(): array {
		return [
			self::APPLIES_ALL,
			self::APPLIES_ROOM,
			self::APPLIES_EXTRA,
			self::APPLIES_SERVICE,
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
	 * Check whether this tax uses a percentage rate.
	 */
	public function isPercentage(): bool {
		return $this->type === self::TYPE_PERCENTAGE;
	}

	/**
	 * Check whether this tax uses a fixed amount.
	 */
	public function isFixed(): bool {
		return $this->type === self::TYPE_FIXED;
	}

	/**
	 * Check whether this tax is currently active.
	 */
	public function isActive(): bool {
		return (int) $this->is_active === 1;
	}

	/**
	 * Calculate the tax amount for a given base amount.
	 *
	 * @param float $baseAmount The amount to apply the tax to.
	 * @return float The calculated tax amount.
	 */
	public function calculateAmount( float $baseAmount ): float {
		if ( $this->isPercentage() ) {
			return round( $baseAmount * ( $this->rate / 100 ), 2 );
		}

		// Fixed amount — the rate IS the tax amount.
		return round( (float) $this->rate, 2 );
	}
}
