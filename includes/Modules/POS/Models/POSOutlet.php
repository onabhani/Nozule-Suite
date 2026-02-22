<?php

namespace Nozule\Modules\POS\Models;

use Nozule\Core\BaseModel;

/**
 * POS Outlet model.
 *
 * Represents a point-of-sale outlet such as a restaurant, minibar, spa, or laundry.
 *
 * @property int         $id
 * @property string      $name
 * @property string|null $name_ar
 * @property string      $type         restaurant|minibar|spa|laundry|other
 * @property string|null $description
 * @property string      $status       active|inactive
 * @property int         $sort_order
 * @property string      $created_at
 * @property string      $updated_at
 */
class POSOutlet extends BaseModel {

	// -- Type Constants --

	public const TYPE_RESTAURANT = 'restaurant';
	public const TYPE_MINIBAR    = 'minibar';
	public const TYPE_SPA        = 'spa';
	public const TYPE_LAUNDRY    = 'laundry';
	public const TYPE_OTHER      = 'other';

	// -- Status Constants --

	public const STATUS_ACTIVE   = 'active';
	public const STATUS_INACTIVE = 'inactive';

	/** @var string[] */
	protected static array $intFields = [
		'id',
		'sort_order',
	];

	/**
	 * All valid outlet types.
	 *
	 * @return string[]
	 */
	public static function validTypes(): array {
		return [
			self::TYPE_RESTAURANT,
			self::TYPE_MINIBAR,
			self::TYPE_SPA,
			self::TYPE_LAUNDRY,
			self::TYPE_OTHER,
		];
	}

	/**
	 * All valid statuses.
	 *
	 * @return string[]
	 */
	public static function validStatuses(): array {
		return [
			self::STATUS_ACTIVE,
			self::STATUS_INACTIVE,
		];
	}

	/**
	 * Create from a database row with type casting.
	 */
	public static function fromRow( object $row ): static {
		$data = (array) $row;

		// Remap legacy is_active boolean to status string.
		if ( isset( $data['is_active'] ) && ! isset( $data['status'] ) ) {
			$data['status'] = $data['is_active'] ? self::STATUS_ACTIVE : self::STATUS_INACTIVE;
		}
		unset( $data['is_active'] );

		foreach ( static::$intFields as $field ) {
			if ( isset( $data[ $field ] ) ) {
				$data[ $field ] = (int) $data[ $field ];
			}
		}

		return new static( $data );
	}

	/**
	 * Check whether this outlet is active.
	 */
	public function isActive(): bool {
		return $this->status === self::STATUS_ACTIVE;
	}
}
