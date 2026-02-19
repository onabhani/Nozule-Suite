<?php

namespace Nozule\Modules\Billing\Validators;

use Nozule\Core\BaseValidator;
use Nozule\Modules\Billing\Models\Tax;

/**
 * Validator for tax create and update operations.
 */
class TaxValidator extends BaseValidator {

	/**
	 * Validate data for creating a new tax.
	 */
	public function validateCreate( array $data ): bool {
		$valid = $this->validate( $data, $this->createRules() );

		return empty( $this->errors );
	}

	/**
	 * Validate data for updating an existing tax.
	 */
	public function validateUpdate( array $data ): bool {
		$valid = $this->validate( $data, $this->updateRules() );

		return empty( $this->errors );
	}

	/**
	 * Validation rules for creating a tax.
	 */
	private function createRules(): array {
		return [
			'name' => [
				'required',
				'minLength' => 1,
				'maxLength' => 100,
			],
			'name_ar' => [
				'required',
				'minLength' => 1,
				'maxLength' => 100,
			],
			'rate' => [
				'required',
				'numeric',
				'min' => 0,
			],
			'type' => [
				'required',
				'in' => Tax::validTypes(),
			],
			'applies_to' => [
				'in' => Tax::validAppliesTo(),
			],
		];
	}

	/**
	 * Validation rules for updating a tax.
	 *
	 * All fields are optional during update.
	 */
	private function updateRules(): array {
		return [
			'name' => [
				'minLength' => 1,
				'maxLength' => 100,
			],
			'name_ar' => [
				'minLength' => 1,
				'maxLength' => 100,
			],
			'rate' => [
				'numeric',
				'min' => 0,
			],
			'type' => [
				'in' => Tax::validTypes(),
			],
			'applies_to' => [
				'in' => Tax::validAppliesTo(),
			],
		];
	}
}
