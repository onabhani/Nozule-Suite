<?php

namespace Nozule\Modules\Currency\Validators;

use Nozule\Core\BaseValidator;
use Nozule\Modules\Currency\Repositories\CurrencyRepository;

/**
 * Validator for currency data.
 */
class CurrencyValidator extends BaseValidator {

	private CurrencyRepository $repository;

	public function __construct( CurrencyRepository $repository ) {
		$this->repository = $repository;
	}

	/**
	 * Validate data for creating a new currency.
	 *
	 * Code must be a 3-character uppercase string, name and symbol are required,
	 * and exchange_rate must be greater than zero.
	 */
	public function validateCreate( array $data ): bool {
		$rules = $this->getBaseRules();

		// Code must be unique on creation.
		$rules['code'][] = 'uniqueCode';

		return $this->validate( $data, $rules );
	}

	/**
	 * Validate data for updating an existing currency.
	 *
	 * @param int   $id   The ID of the currency being updated.
	 * @param array $data The fields to update.
	 */
	public function validateUpdate( int $id, array $data ): bool {
		$this->errors = [];

		$rules = $this->getUpdateRules( $data );
		$valid = $this->validate( $data, $rules );

		// Check code uniqueness if code is being changed.
		if ( isset( $data['code'] ) ) {
			$existing = $this->repository->findByCode( $data['code'] );
			if ( $existing && $existing->id !== $id ) {
				$this->errors['code'][] = __( 'A currency with this code already exists.', 'nozule' );
				$valid = false;
			}
		}

		return $valid;
	}

	/**
	 * Get the base validation rules for currency creation.
	 *
	 * @return array<string, array>
	 */
	private function getBaseRules(): array {
		return [
			'code'          => [ 'required', 'uppercaseCode' ],
			'name'          => [ 'required', 'min' => 1, 'max' => 100 ],
			'symbol'        => [ 'required', 'max' => 10 ],
			'exchange_rate' => [ 'required', 'numeric', 'positiveNumber' ],
		];
	}

	/**
	 * Get validation rules for partial updates (only validate provided fields).
	 *
	 * @return array<string, array>
	 */
	private function getUpdateRules( array $data ): array {
		$rules     = [];
		$all_rules = [
			'code'           => [ 'uppercaseCode' ],
			'name'           => [ 'min' => 1, 'max' => 100 ],
			'name_ar'        => [ 'max' => 100 ],
			'symbol'         => [ 'max' => 10 ],
			'symbol_ar'      => [ 'max' => 10 ],
			'decimal_places' => [ 'integer', 'min' => 0, 'max' => 6 ],
			'exchange_rate'  => [ 'numeric', 'positiveNumber' ],
			'sort_order'     => [ 'integer', 'min' => 0 ],
		];

		foreach ( $all_rules as $field => $field_rules ) {
			if ( array_key_exists( $field, $data ) ) {
				$rules[ $field ] = $field_rules;
			}
		}

		// If code, name, or symbol are provided, they must not be empty.
		if ( isset( $data['code'] ) ) {
			array_unshift( $rules['code'], 'required' );
		}
		if ( isset( $data['name'] ) ) {
			array_unshift( $rules['name'], 'required' );
		}
		if ( isset( $data['symbol'] ) ) {
			array_unshift( $rules['symbol'], 'required' );
		}
		if ( isset( $data['exchange_rate'] ) ) {
			array_unshift( $rules['exchange_rate'], 'required' );
		}

		return $rules;
	}

	/**
	 * Custom validation: ensure currency code is 3 uppercase letters.
	 *
	 * @param mixed $value
	 * @param mixed $param
	 */
	protected function validateUppercaseCode( string $field, $value, $param = null, array $data = [] ): ?string {
		if ( empty( $value ) ) {
			return null;
		}

		if ( ! preg_match( '/^[A-Z]{3}$/', $value ) ) {
			return __( 'Currency code must be exactly 3 uppercase letters (e.g., USD, SYP).', 'nozule' );
		}

		return null;
	}

	/**
	 * Custom validation: ensure code is unique in the currencies table.
	 *
	 * @param mixed $value
	 * @param mixed $param
	 */
	protected function validateUniqueCode( string $field, $value, $param = null, array $data = [] ): ?string {
		if ( empty( $value ) ) {
			return null;
		}

		$existing = $this->repository->findByCode( $value );

		if ( $existing ) {
			return __( 'A currency with this code already exists.', 'nozule' );
		}

		return null;
	}

	/**
	 * Custom validation: ensure value is a positive number (greater than 0).
	 *
	 * @param mixed $value
	 * @param mixed $param
	 */
	protected function validatePositiveNumber( string $field, $value, $param = null, array $data = [] ): ?string {
		if ( $value === null || $value === '' ) {
			return null;
		}

		if ( ! is_numeric( $value ) || (float) $value <= 0 ) {
			return sprintf( __( '%s must be greater than zero.', 'nozule' ), $field );
		}

		return null;
	}
}
