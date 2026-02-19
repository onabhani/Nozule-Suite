<?php

namespace Nozule\Modules\Billing\Validators;

use Nozule\Core\BaseValidator;
use Nozule\Modules\Billing\Models\FolioItem;

/**
 * Validator for folio create and add-item operations.
 */
class FolioValidator extends BaseValidator {

	/**
	 * Validate data for creating a new folio.
	 */
	public function validateCreate( array $data ): bool {
		$valid = $this->validate( $data, $this->createRules() );

		return empty( $this->errors );
	}

	/**
	 * Validate data for adding an item to a folio.
	 */
	public function validateAddItem( array $data ): bool {
		$valid = $this->validate( $data, $this->addItemRules() );

		return empty( $this->errors );
	}

	/**
	 * Validation rules for creating a folio.
	 */
	private function createRules(): array {
		return [
			'guest_id' => [
				'required',
				'integer',
				'min' => 1,
			],
			'booking_id' => [
				'integer',
				'min' => 1,
			],
			'group_booking_id' => [
				'integer',
				'min' => 1,
			],
		];
	}

	/**
	 * Validation rules for adding a line item.
	 */
	private function addItemRules(): array {
		return [
			'folio_id' => [
				'required',
				'integer',
				'min' => 1,
			],
			'description' => [
				'required',
				'minLength' => 1,
				'maxLength' => 500,
			],
			'unit_price' => [
				'required',
				'numeric',
			],
			'quantity' => [
				'required',
				'integer',
				'min' => 1,
			],
			'category' => [
				'required',
				'in' => FolioItem::validCategories(),
			],
		];
	}
}
