<?php

namespace Nozule\Modules\Promotions\Models;

use Nozule\Core\BaseModel;

/**
 * PromoCode model representing a promotional discount code.
 *
 * @property int         $id
 * @property string      $code
 * @property string      $name
 * @property string      $name_ar
 * @property string      $description
 * @property string      $description_ar
 * @property string      $discount_type       percentage|fixed
 * @property float       $discount_value
 * @property string      $currency_code
 * @property int         $min_nights
 * @property float       $min_amount
 * @property float       $max_discount
 * @property int         $max_uses
 * @property int         $used_count
 * @property int         $per_guest_limit
 * @property string|null $valid_from
 * @property string|null $valid_to
 * @property array       $applicable_room_types
 * @property array       $applicable_sources
 * @property int         $is_active
 * @property int         $created_by
 * @property string      $created_at
 * @property string      $updated_at
 */
class PromoCode extends BaseModel {

	/**
	 * Discount type constants.
	 */
	public const TYPE_PERCENTAGE = 'percentage';
	public const TYPE_FIXED      = 'fixed';

	/**
	 * Fields that should be cast to integers.
	 *
	 * @var string[]
	 */
	protected static array $intFields = [
		'id',
		'max_uses',
		'used_count',
		'per_guest_limit',
		'min_nights',
		'created_by',
	];

	/**
	 * Fields that should be cast to floats.
	 *
	 * @var string[]
	 */
	protected static array $floatFields = [
		'discount_value',
		'min_amount',
		'max_discount',
	];

	/**
	 * Fields that should be cast to booleans.
	 *
	 * @var string[]
	 */
	protected static array $boolFields = [
		'is_active',
	];

	/**
	 * All valid discount type values.
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
	 * Create from a database row with type casting and JSON decoding.
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

		foreach ( static::$boolFields as $field ) {
			if ( isset( $data[ $field ] ) ) {
				$data[ $field ] = (bool) (int) $data[ $field ];
			}
		}

		// Decode JSON fields.
		if ( isset( $data['applicable_room_types'] ) && is_string( $data['applicable_room_types'] ) ) {
			$decoded = json_decode( $data['applicable_room_types'], true );
			$data['applicable_room_types'] = is_array( $decoded ) ? $decoded : [];
		}

		if ( isset( $data['applicable_sources'] ) && is_string( $data['applicable_sources'] ) ) {
			$decoded = json_decode( $data['applicable_sources'], true );
			$data['applicable_sources'] = is_array( $decoded ) ? $decoded : [];
		}

		return new static( $data );
	}

	/**
	 * Convert to array with JSON encoding for storage-compatible fields.
	 */
	public function toArray(): array {
		$data = parent::toArray();

		// Encode JSON fields for output.
		if ( isset( $data['applicable_room_types'] ) && is_array( $data['applicable_room_types'] ) ) {
			$data['applicable_room_types'] = $data['applicable_room_types'];
		}

		if ( isset( $data['applicable_sources'] ) && is_array( $data['applicable_sources'] ) ) {
			$data['applicable_sources'] = $data['applicable_sources'];
		}

		return $data;
	}

	/**
	 * Get fields suitable for database insertion/update.
	 *
	 * JSON-encodes array fields for storage.
	 */
	public function toDatabaseArray(): array {
		$data = parent::toArray();

		if ( isset( $data['applicable_room_types'] ) && is_array( $data['applicable_room_types'] ) ) {
			$data['applicable_room_types'] = wp_json_encode( $data['applicable_room_types'] );
		}

		if ( isset( $data['applicable_sources'] ) && is_array( $data['applicable_sources'] ) ) {
			$data['applicable_sources'] = wp_json_encode( $data['applicable_sources'] );
		}

		return $data;
	}

	/**
	 * Check whether this promo code is currently active.
	 */
	public function isActive(): bool {
		return (bool) $this->is_active;
	}

	/**
	 * Check whether this promo code is valid (active and within date range).
	 */
	public function isValid(): bool {
		if ( ! $this->isActive() ) {
			return false;
		}

		return ! $this->isExpired();
	}

	/**
	 * Check whether this promo code has expired or is not yet valid.
	 */
	public function isExpired(): bool {
		$today = current_time( 'Y-m-d' );

		if ( ! empty( $this->valid_from ) && $today < $this->valid_from ) {
			return true;
		}

		if ( ! empty( $this->valid_to ) && $today > $this->valid_to ) {
			return true;
		}

		return false;
	}

	/**
	 * Check whether uses remain for this promo code.
	 */
	public function hasUsesRemaining(): bool {
		if ( empty( $this->max_uses ) || $this->max_uses <= 0 ) {
			return true; // Unlimited uses.
		}

		return $this->used_count < $this->max_uses;
	}

	/**
	 * Check if this promo code applies to a given room type ID.
	 *
	 * @param int $roomTypeId The room type ID to check.
	 */
	public function appliesToRoomType( int $roomTypeId ): bool {
		$types = $this->applicable_room_types ?? [];

		if ( ! is_array( $types ) ) {
			$types = json_decode( $types, true ) ?: [];
		}

		// Empty array means applies to all room types.
		if ( empty( $types ) ) {
			return true;
		}

		return in_array( $roomTypeId, array_map( 'intval', $types ), true );
	}

	/**
	 * Check if this promo code applies to a given booking source.
	 *
	 * @param string $source The booking source to check.
	 */
	public function appliesToSource( string $source ): bool {
		$sources = $this->applicable_sources ?? [];

		if ( ! is_array( $sources ) ) {
			$sources = json_decode( $sources, true ) ?: [];
		}

		// Empty array means applies to all sources.
		if ( empty( $sources ) ) {
			return true;
		}

		return in_array( $source, $sources, true );
	}
}
