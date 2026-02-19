<?php

namespace Nozule\Modules\Rooms\Models;

use Nozule\Core\BaseModel;

/**
 * Room Type model.
 *
 * Represents a category of rooms (e.g. Standard, Deluxe, Suite) with
 * shared properties such as base occupancy, amenities, and images.
 *
 * @property int         $id
 * @property string      $name
 * @property string      $slug
 * @property string|null $description
 * @property int         $max_occupancy
 * @property int         $base_occupancy
 * @property float       $base_price
 * @property float       $extra_adult_price
 * @property float       $extra_child_price
 * @property array       $amenities
 * @property array       $images
 * @property int         $sort_order
 * @property string      $status          active|inactive
 * @property string      $created_at
 * @property string      $updated_at
 */
class RoomType extends BaseModel {

	/**
	 * Fields that are stored as JSON in the database.
	 *
	 * @var string[]
	 */
	protected static array $jsonFields = [
		'amenities',
		'images',
	];

	/**
	 * Fields that should be cast to integers.
	 *
	 * @var string[]
	 */
	protected static array $intFields = [
		'id',
		'max_occupancy',
		'base_occupancy',
		'sort_order',
	];

	/**
	 * Fields that should be cast to floats.
	 *
	 * @var string[]
	 */
	protected static array $floatFields = [
		'base_price',
		'extra_adult_price',
		'extra_child_price',
	];

	/**
	 * Create from a database row, decoding JSON fields automatically.
	 */
	public static function fromRow( object $row ): static {
		$data = (array) $row;

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
	 * Check whether this room type is active.
	 */
	public function isActive(): bool {
		return $this->status === 'active';
	}

	/**
	 * Get the featured image URL (first image or null).
	 */
	public function getFeaturedImage(): ?string {
		$images = $this->amenities; // use the actual images attribute
		$imgs   = $this->images;

		return ! empty( $imgs ) ? $imgs[0] : null;
	}
}
