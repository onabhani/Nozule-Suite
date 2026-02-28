<?php

namespace Nozule\Modules\Property\Validators;

use Nozule\Core\BaseValidator;
use Nozule\Modules\Property\Models\Property;
use Nozule\Modules\Property\Repositories\PropertyRepository;

/**
 * Validator for property data.
 */
class PropertyValidator extends BaseValidator {

	private PropertyRepository $repository;

	public function __construct( PropertyRepository $repository ) {
		$this->repository = $repository;
	}

	/**
	 * Validate data for creating a property.
	 */
	public function validateCreate( array $data ): bool {
		$valid = $this->validate( $data, $this->createRules() );

		if ( $valid ) {
			$this->validateSlugUniqueness( $data );
			$this->validateStarRating( $data );
			$this->validatePropertyType( $data );
		}

		return empty( $this->errors );
	}

	/**
	 * Validate data for updating a property.
	 */
	public function validateUpdate( int $id, array $data ): bool {
		$valid = $this->validate( $data, $this->updateRules() );

		if ( $valid ) {
			$this->validateSlugUniqueness( $data, $id );
			$this->validateStarRating( $data );
			$this->validatePropertyType( $data );
		}

		return empty( $this->errors );
	}

	/**
	 * Rules for creating a property.
	 */
	private function createRules(): array {
		return [
			'name' => [ 'required', 'maxLength' => 255 ],
			'slug' => [ 'required', 'slug', 'maxLength' => 255 ],
		];
	}

	/**
	 * Rules for updating a property.
	 */
	private function updateRules(): array {
		return [
			'name' => [ 'maxLength' => 255 ],
			'slug' => [ 'slug', 'maxLength' => 255 ],
		];
	}

	/**
	 * Validate slug uniqueness.
	 */
	private function validateSlugUniqueness( array $data, ?int $excludeId = null ): void {
		if ( ! empty( $data['slug'] ) ) {
			if ( ! $this->repository->isSlugUnique( $data['slug'], $excludeId ) ) {
				$this->errors['slug'][] = __( 'This slug is already in use.', 'nozule' );
			}
		}
	}

	/**
	 * Validate star rating if provided.
	 */
	private function validateStarRating( array $data ): void {
		if ( isset( $data['star_rating'] ) && $data['star_rating'] !== null && $data['star_rating'] !== '' ) {
			$rating = (int) $data['star_rating'];
			if ( $rating < 1 || $rating > 5 ) {
				$this->errors['star_rating'][] = __( 'Star rating must be between 1 and 5.', 'nozule' );
			}
		}
	}

	/**
	 * Validate property type if provided.
	 */
	private function validatePropertyType( array $data ): void {
		if ( ! empty( $data['property_type'] ) ) {
			if ( ! in_array( $data['property_type'], Property::validTypes(), true ) ) {
				$this->errors['property_type'][] = sprintf(
					__( 'Property type must be one of: %s.', 'nozule' ),
					implode( ', ', Property::validTypes() )
				);
			}
		}
	}
}
