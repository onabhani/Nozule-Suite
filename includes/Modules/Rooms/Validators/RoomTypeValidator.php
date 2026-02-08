<?php

namespace Venezia\Modules\Rooms\Validators;

use Venezia\Core\BaseValidator;
use Venezia\Modules\Rooms\Repositories\RoomTypeRepository;

/**
 * Validator for room type create and update operations.
 */
class RoomTypeValidator extends BaseValidator {

	private RoomTypeRepository $roomTypeRepository;

	public function __construct( RoomTypeRepository $roomTypeRepository ) {
		$this->roomTypeRepository = $roomTypeRepository;
	}

	/**
	 * Validate data for creating a new room type.
	 */
	public function validateCreate( array $data ): bool {
		$valid = $this->validate( $data, $this->createRules() );

		if ( $valid ) {
			$this->validateSlugUniqueness( $data['slug'] ?? '', null );
		}

		return empty( $this->errors );
	}

	/**
	 * Validate data for updating an existing room type.
	 */
	public function validateUpdate( int $id, array $data ): bool {
		$valid = $this->validate( $data, $this->updateRules() );

		if ( $valid && isset( $data['slug'] ) ) {
			$this->validateSlugUniqueness( $data['slug'], $id );
		}

		if ( $valid ) {
			$this->validateOccupancyConsistency( $data );
		}

		return empty( $this->errors );
	}

	/**
	 * Validation rules for creating a room type.
	 */
	private function createRules(): array {
		return [
			'name' => [
				'required',
				'min' => 2,
				'max' => 100,
			],
			'slug' => [
				'required',
				'slug',
				'max' => 100,
			],
			'max_occupancy' => [
				'required',
				'integer',
				'min' => 1,
				'max' => 20,
			],
			'base_occupancy' => [
				'required',
				'integer',
				'min' => 1,
				'max' => 20,
			],
			'base_price' => [
				'required',
				'numeric',
				'min' => 0,
			],
			'status' => [
				'in' => [ 'active', 'inactive' ],
			],
		];
	}

	/**
	 * Validation rules for updating a room type.
	 *
	 * All fields are optional during update.
	 */
	private function updateRules(): array {
		$rules = [];

		// Only validate fields that are present.
		return [
			'name' => [
				'min' => 2,
				'max' => 100,
			],
			'slug' => [
				'slug',
				'max' => 100,
			],
			'max_occupancy' => [
				'integer',
				'min' => 1,
				'max' => 20,
			],
			'base_occupancy' => [
				'integer',
				'min' => 1,
				'max' => 20,
			],
			'base_price' => [
				'numeric',
				'min' => 0,
			],
			'extra_adult_price' => [
				'numeric',
				'min' => 0,
			],
			'extra_child_price' => [
				'numeric',
				'min' => 0,
			],
			'status' => [
				'in' => [ 'active', 'inactive' ],
			],
		];
	}

	/**
	 * Validate that the slug is unique across room types.
	 */
	private function validateSlugUniqueness( string $slug, ?int $excludeId ): void {
		if ( $slug && ! $this->roomTypeRepository->isSlugUnique( $slug, $excludeId ) ) {
			$this->errors['slug'][] = __( 'This slug is already in use by another room type.', 'venezia-hotel' );
		}
	}

	/**
	 * Validate that base_occupancy does not exceed max_occupancy.
	 */
	private function validateOccupancyConsistency( array $data ): void {
		$base = $data['base_occupancy'] ?? null;
		$max  = $data['max_occupancy'] ?? null;

		if ( $base !== null && $max !== null && (int) $base > (int) $max ) {
			$this->errors['base_occupancy'][] = __(
				'Base occupancy cannot exceed maximum occupancy.',
				'venezia-hotel'
			);
		}
	}
}
