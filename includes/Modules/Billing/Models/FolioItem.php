<?php

namespace Nozule\Modules\Billing\Models;

use Nozule\Core\BaseModel;

/**
 * Folio Item model.
 *
 * Represents a single line item on a folio (room charge, extra, service,
 * tax adjustment, discount, or payment).
 *
 * @property int         $id
 * @property int         $folio_id
 * @property string      $category       room_charge|extra|service|tax_adjustment|discount|payment
 * @property string      $description
 * @property string|null $description_ar
 * @property int         $quantity
 * @property float       $unit_price
 * @property float       $subtotal
 * @property array|null  $tax_json
 * @property float       $tax_total
 * @property float       $total
 * @property string|null $date
 * @property int|null    $posted_by
 * @property string      $created_at
 */
class FolioItem extends BaseModel {

	/**
	 * Category constants.
	 */
	public const CAT_ROOM_CHARGE    = 'room_charge';
	public const CAT_EXTRA          = 'extra';
	public const CAT_SERVICE        = 'service';
	public const CAT_TAX_ADJUSTMENT = 'tax_adjustment';
	public const CAT_DISCOUNT       = 'discount';
	public const CAT_PAYMENT        = 'payment';

	/**
	 * Fields that should be cast to integers.
	 *
	 * @var string[]
	 */
	protected static array $intFields = [
		'id',
		'folio_id',
		'quantity',
		'posted_by',
	];

	/**
	 * Fields that should be cast to floats.
	 *
	 * @var string[]
	 */
	protected static array $floatFields = [
		'unit_price',
		'subtotal',
		'tax_total',
		'total',
	];

	/**
	 * Fields that are stored as JSON in the database.
	 *
	 * @var string[]
	 */
	protected static array $jsonFields = [
		'tax_json',
	];

	/**
	 * All valid category values.
	 *
	 * @return string[]
	 */
	public static function validCategories(): array {
		return [
			self::CAT_ROOM_CHARGE,
			self::CAT_EXTRA,
			self::CAT_SERVICE,
			self::CAT_TAX_ADJUSTMENT,
			self::CAT_DISCOUNT,
			self::CAT_PAYMENT,
		];
	}

	/**
	 * Create from a database row with type casting and JSON decoding.
	 */
	public static function fromRow( object $row ): static {
		$data = (array) $row;

		// Decode JSON fields.
		foreach ( static::$jsonFields as $field ) {
			if ( isset( $data[ $field ] ) && is_string( $data[ $field ] ) ) {
				$decoded = json_decode( $data[ $field ], true );
				$data[ $field ] = ( json_last_error() === JSON_ERROR_NONE ) ? $decoded : [];
			} elseif ( ! isset( $data[ $field ] ) ) {
				$data[ $field ] = [];
			}
		}

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
	 * Convert to array, encoding JSON fields for database storage.
	 */
	public function toArray(): array {
		$data = parent::toArray();

		foreach ( static::$jsonFields as $field ) {
			if ( isset( $data[ $field ] ) && is_array( $data[ $field ] ) ) {
				$data[ $field ] = wp_json_encode( $data[ $field ] );
			}
		}

		return $data;
	}

	/**
	 * Convert to a public-facing array (JSON fields remain decoded).
	 */
	public function toPublicArray(): array {
		return parent::toArray();
	}

	/**
	 * Check whether this item is a charge (positive amount on the folio).
	 */
	public function isCharge(): bool {
		return in_array( $this->category, [
			self::CAT_ROOM_CHARGE,
			self::CAT_EXTRA,
			self::CAT_SERVICE,
			self::CAT_TAX_ADJUSTMENT,
		], true );
	}

	/**
	 * Check whether this item is a payment or discount (reduces balance).
	 */
	public function isCredit(): bool {
		return in_array( $this->category, [
			self::CAT_DISCOUNT,
			self::CAT_PAYMENT,
		], true );
	}
}
