<?php

namespace Nozule\Modules\Pricing\Validators;

use Nozule\Core\BaseValidator;

/**
 * Validator for seasonal rate create and update operations.
 *
 * Validates input for creating and updating seasonal rate overrides,
 * ensuring date ranges are logical, modifier values are sensible,
 * and days_of_week contains valid ISO day numbers.
 */
class SeasonalRateValidator extends BaseValidator {

	/**
	 * Validate data for creating a new seasonal rate.
	 */
	public function validateCreate( array $data ): bool {
		$valid = $this->validate( $data, $this->createRules() );

		if ( $valid ) {
			$this->validateDateRange( $data );
			$this->validateDaysOfWeek( $data );
		}

		return empty( $this->errors );
	}

	/**
	 * Validate data for updating an existing seasonal rate.
	 */
	public function validateUpdate( array $data ): bool {
		$valid = $this->validate( $data, $this->updateRules( $data ) );

		if ( $valid ) {
			$this->validateDateRange( $data );
			$this->validateDaysOfWeek( $data );
		}

		return empty( $this->errors );
	}

	/**
	 * Validation rules for creating a seasonal rate.
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
			'room_type_id' => [
				'required',
				'integer',
				'min' => 1,
			],
			'start_date' => [
				'required',
				'date',
			],
			'end_date' => [
				'required',
				'date',
			],
			'modifier_type' => [
				'required',
				'in' => [ 'percentage', 'fixed', 'absolute' ],
			],
			'modifier_value' => [
				'required',
				'numeric',
			],
			'priority' => [
				'integer',
				'min' => 0,
			],
			'min_stay' => [
				'integer',
				'min' => 1,
			],
			'status' => [
				'in' => [ 'active', 'inactive' ],
			],
		];
	}

	/**
	 * Validation rules for updating a seasonal rate.
	 *
	 * Only validates fields present in the data.
	 *
	 * @return array<string, array>
	 */
	private function updateRules( array $data ): array {
		$allRules = [
			'name' => [
				'min' => 1,
				'max' => 200,
			],
			'room_type_id' => [
				'integer',
				'min' => 1,
			],
			'rate_plan_id' => [
				'integer',
				'min' => 1,
			],
			'start_date' => [
				'date',
			],
			'end_date' => [
				'date',
			],
			'modifier_type' => [
				'in' => [ 'percentage', 'fixed', 'absolute' ],
			],
			'modifier_value' => [
				'numeric',
			],
			'priority' => [
				'integer',
				'min' => 0,
			],
			'min_stay' => [
				'integer',
				'min' => 1,
			],
			'status' => [
				'in' => [ 'active', 'inactive' ],
			],
		];

		$rules = [];

		foreach ( $allRules as $field => $fieldRules ) {
			if ( array_key_exists( $field, $data ) ) {
				$rules[ $field ] = $fieldRules;
			}
		}

		// If name is present in an update, it must not be empty.
		if ( isset( $data['name'] ) ) {
			array_unshift( $rules['name'], 'required' );
		}

		return $rules;
	}

	/**
	 * Validate that start_date is before or equal to end_date.
	 */
	private function validateDateRange( array $data ): void {
		$startDate = $data['start_date'] ?? null;
		$endDate   = $data['end_date'] ?? null;

		if ( $startDate && $endDate && $startDate > $endDate ) {
			$this->errors['end_date'][] = __(
				'The end date must be on or after the start date.',
				'nozule'
			);
		}
	}

	/**
	 * Validate days_of_week contains valid ISO day numbers (1-7).
	 *
	 * An empty array or null means "all days" and is accepted.
	 * Each element must be an integer from 1 (Monday) to 7 (Sunday).
	 */
	private function validateDaysOfWeek( array $data ): void {
		if ( ! isset( $data['days_of_week'] ) ) {
			return;
		}

		$daysOfWeek = $data['days_of_week'];

		// Allow null or empty array (means all days apply).
		if ( $daysOfWeek === null || $daysOfWeek === [] ) {
			return;
		}

		if ( ! is_array( $daysOfWeek ) ) {
			$this->errors['days_of_week'][] = __(
				'Days of week must be an array of day numbers.',
				'nozule'
			);
			return;
		}

		foreach ( $daysOfWeek as $day ) {
			if ( ! is_int( $day ) && ! ctype_digit( (string) $day ) ) {
				$this->errors['days_of_week'][] = __(
					'Each day must be an integer.',
					'nozule'
				);
				return;
			}

			$dayInt = (int) $day;
			if ( $dayInt < 1 || $dayInt > 7 ) {
				$this->errors['days_of_week'][] = sprintf(
					/* translators: %d: invalid day number */
					__( 'Invalid day number %d. Must be between 1 (Monday) and 7 (Sunday).', 'nozule' ),
					$dayInt
				);
				return;
			}
		}
	}
}
