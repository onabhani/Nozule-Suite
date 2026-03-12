<?php

namespace Nozule\Modules\ContactlessCheckin\Validators;

use Nozule\Core\BaseValidator;

/**
 * Validator for contactless check-in submissions.
 */
class CheckinRegistrationValidator extends BaseValidator {

	/**
	 * Validate guest submission data.
	 */
	public function validateSubmission( array $data ): bool {
		$this->validate( $data, [
			'first_name' => [
				'required',
				'maxLength' => 100,
			],
			'last_name' => [
				'required',
				'maxLength' => 100,
			],
			'email' => [
				'required',
				'email',
			],
			'phone' => [
				'required',
				'phone',
			],
		] );

		return empty( $this->errors );
	}
}
