<?php

namespace Nozule\Modules\Property\Models;

use Nozule\Core\BaseModel;

/**
 * Property model representing a hotel property.
 *
 * @property int         $id
 * @property string      $property_id
 * @property string      $name
 * @property string      $name_ar
 * @property string      $slug
 * @property string|null $description
 * @property string|null $description_ar
 * @property string      $property_type
 * @property int|null    $star_rating
 * @property string|null $address_line_1
 * @property string|null $address_line_2
 * @property string|null $city
 * @property string|null $state_province
 * @property string|null $country
 * @property string|null $postal_code
 * @property float|null  $latitude
 * @property float|null  $longitude
 * @property string|null $phone
 * @property string|null $phone_alt
 * @property string|null $email
 * @property string|null $website
 * @property string      $check_in_time
 * @property string      $check_out_time
 * @property string      $timezone
 * @property string|null $logo_url
 * @property string|null $cover_image_url
 * @property array|null  $photos
 * @property array|null  $facilities
 * @property array|null  $policies
 * @property array|null  $social_links
 * @property string|null $tax_id
 * @property string|null $license_number
 * @property int|null    $total_rooms
 * @property int|null    $total_floors
 * @property int|null    $year_built
 * @property int|null    $year_renovated
 * @property string      $currency
 * @property string      $status
 * @property string      $created_at
 * @property string      $updated_at
 */
class Property extends BaseModel {

	/** Status constants. */
	public const STATUS_ACTIVE   = 'active';
	public const STATUS_INACTIVE = 'inactive';

	/** Property type constants. */
	public const TYPE_HOTEL         = 'hotel';
	public const TYPE_RESORT        = 'resort';
	public const TYPE_BOUTIQUE      = 'boutique';
	public const TYPE_HOSTEL        = 'hostel';
	public const TYPE_APARTMENT     = 'apartment';
	public const TYPE_GUESTHOUSE    = 'guesthouse';
	public const TYPE_VILLA         = 'villa';
	public const TYPE_MOTEL         = 'motel';

	/**
	 * Fields cast to integer.
	 *
	 * @var string[]
	 */
	protected static array $intFields = [
		'id',
		'star_rating',
		'total_rooms',
		'total_floors',
		'year_built',
		'year_renovated',
	];

	/**
	 * Fields cast to float.
	 *
	 * @var string[]
	 */
	protected static array $floatFields = [
		'latitude',
		'longitude',
	];

	/**
	 * Fields decoded from JSON.
	 *
	 * @var string[]
	 */
	protected static array $jsonFields = [
		'photos',
		'facilities',
		'policies',
		'social_links',
	];

	/**
	 * Valid property types.
	 *
	 * @return string[]
	 */
	public static function validTypes(): array {
		return [
			self::TYPE_HOTEL,
			self::TYPE_RESORT,
			self::TYPE_BOUTIQUE,
			self::TYPE_HOSTEL,
			self::TYPE_APARTMENT,
			self::TYPE_GUESTHOUSE,
			self::TYPE_VILLA,
			self::TYPE_MOTEL,
		];
	}

	/**
	 * Valid statuses.
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

		foreach ( static::$intFields as $field ) {
			if ( isset( $data[ $field ] ) ) {
				$data[ $field ] = (int) $data[ $field ];
			}
		}

		foreach ( static::$floatFields as $field ) {
			if ( isset( $data[ $field ] ) && $data[ $field ] !== null ) {
				$data[ $field ] = (float) $data[ $field ];
			}
		}

		foreach ( static::$jsonFields as $field ) {
			if ( isset( $data[ $field ] ) && is_string( $data[ $field ] ) ) {
				$decoded = json_decode( $data[ $field ], true );
				$data[ $field ] = is_array( $decoded ) ? $decoded : [];
			}
		}

		return new static( $data );
	}

	/**
	 * Check whether this property is active.
	 */
	public function isActive(): bool {
		return $this->status === self::STATUS_ACTIVE;
	}

	/**
	 * Get the full formatted address.
	 */
	public function getFormattedAddress(): string {
		$parts = array_filter( [
			$this->address_line_1,
			$this->address_line_2,
			$this->city,
			$this->state_province,
			$this->postal_code,
			$this->country,
		] );

		return implode( ', ', $parts );
	}

	/**
	 * Convert to array, encoding JSON fields.
	 */
	public function toArray(): array {
		$data = parent::toArray();

		// Ensure JSON fields are arrays, not strings.
		foreach ( static::$jsonFields as $field ) {
			if ( isset( $data[ $field ] ) && is_string( $data[ $field ] ) ) {
				$decoded = json_decode( $data[ $field ], true );
				$data[ $field ] = is_array( $decoded ) ? $decoded : [];
			}
		}

		return $data;
	}
}
