<?php

namespace Nozule\Modules\Pricing\Validators;

use Nozule\Core\BaseValidator;
use Nozule\Modules\Pricing\Repositories\RatePlanRepository;

/**
 * Validator for rate plan create and update operations.
 */
class RatePlanValidator extends BaseValidator {

	private RatePlanRepository $repository;

	public function __construct( RatePlanRepository $repository ) {
		$this->repository = $repository;
	}

	/**
	 * Validate data for creating a new rate plan.
	 */
	public function validateCreate( array $data ): bool {
		$valid = $this->validate( $data, $this->createRules() );

		if ( $valid ) {
			$this->validateCodeUniqueness( $data['code'] ?? '', null );
			$this->validateDateRange( $data );
		}

		return empty( $this->errors );
	}

	/**
	 * Validate data for updating an existing rate plan.
	 */
	public function validateUpdate( int $id, array $data ): bool {
		$valid = $this->validate( $data, $this->updateRules( $data ) );

		if ( $valid && isset( $data['code'] ) ) {
			$this->validateCodeUniqueness( $data['code'], $id );
		}

		if ( $valid ) {
			$this->validateDateRange( $data );
			$this->validateStayConsistency( $data );
		}

		return empty( $this->errors );
	}

	/**
	 * Validation rules for creating a rate plan.
	 *
	 * @return array<string, array>
	 */
	private function createRules(): array {
		return [
			'name' => [
				'required',
				'min' => 1,
				'max' => 200,
			],
			'code' => [
				'required',
				'slug',
				'max' => 50,
			],
			'modifier_type' => [
				'required',
				'in' => [ 'percentage', 'fixed', 'absolute' ],
			],
			'modifier_value' => [
				'required',
				'numeric',
			],
			'min_stay' => [
				'integer',
				'min' => 1,
			],
			'max_stay' => [
				'integer',
				'min' => 0,
			],
			'priority' => [
				'integer',
				'min' => 0,
			],
			'guest_type' => [
				'in' => [ 'all', 'syrian', 'non_syrian' ],
			],
			'status' => [
				'in' => [ 'active', 'inactive' ],
			],
		];
	}

	/**
	 * Validation rules for updating a rate plan.
	 *
	 * Only validates fields that are present in the data.
	 *
	 * @return array<string, array>
	 */
	private function updateRules( array $data ): array {
		$allRules = [
			'name' => [
				'min' => 1,
				'max' => 200,
			],
			'code' => [
				'slug',
				'max' => 50,
			],
			'modifier_type' => [
				'in' => [ 'percentage', 'fixed', 'absolute' ],
			],
			'modifier_value' => [
				'numeric',
			],
			'min_stay' => [
				'integer',
				'min' => 1,
			],
			'max_stay' => [
				'integer',
				'min' => 0,
			],
			'priority' => [
				'integer',
				'min' => 0,
			],
			'guest_type' => [
				'in' => [ 'all', 'syrian', 'non_syrian' ],
			],
			'status' => [
				'in' => [ 'active', 'inactive' ],
			],
			'valid_from' => [
				'date',
			],
			'valid_until' => [
				'date',
			],
			'cancellation_policy' => [
				'max' => 1000,
			],
		];

		$rules = [];

		foreach ( $allRules as $field => $fieldRules ) {
			if ( array_key_exists( $field, $data ) ) {
				$rules[ $field ] = $fieldRules;
			}
		}

		// If name is provided in an update, it must not be empty.
		if ( isset( $data['name'] ) ) {
			array_unshift( $rules['name'], 'required' );
		}

		return $rules;
	}

	/**
	 * Validate that the code is unique across rate plans.
	 */
	private function validateCodeUniqueness( string $code, ?int $excludeId ): void {
		if ( $code && ! $this->repository->isCodeUnique( $code, $excludeId ) ) {
			$this->errors['code'][] = __(
				'This code is already in use by another rate plan.',
				'nozule'
			);
		}
	}

	/**
	 * Validate that valid_from is before valid_until when both are provided.
	 */
	private function validateDateRange( array $data ): void {
		$from  = $data['valid_from'] ?? null;
		$until = $data['valid_until'] ?? null;

		if ( $from && $until && $from > $until ) {
			$this->errors['valid_until'][] = __(
				'The end date must be on or after the start date.',
				'nozule'
			);
		}
	}

	/**
	 * Validate that min_stay does not exceed max_stay.
	 */
	private function validateStayConsistency( array $data ): void {
		$minStay = $data['min_stay'] ?? null;
		$maxStay = $data['max_stay'] ?? null;

		if ( $minStay !== null && $maxStay !== null && (int) $maxStay > 0 && (int) $minStay > (int) $maxStay ) {
			$this->errors['min_stay'][] = __(
				'Minimum stay cannot exceed maximum stay.',
				'nozule'
			);
		}
	}
}
