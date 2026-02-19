<?php

namespace Nozule\Modules\Promotions\Validators;

use Nozule\Core\BaseValidator;
use Nozule\Modules\Promotions\Models\PromoCode;
use Nozule\Modules\Promotions\Repositories\PromoCodeRepository;

/**
 * Validator for promo code data.
 */
class PromoCodeValidator extends BaseValidator {

	private PromoCodeRepository $repository;

	public function __construct( PromoCodeRepository $repository ) {
		$this->repository = $repository;
	}

	/**
	 * Validate data for creating a new promo code.
	 */
	public function validateCreate( array $data ): bool {
		$this->errors = [];

		// Run standard field rules.
		$valid = $this->validate( $data, $this->getCreateRules() );

		// Code must be unique.
		if ( ! empty( $data['code'] ) ) {
			$existing = $this->repository->findByCode( $data['code'] );
			if ( $existing ) {
				$this->errors['code'][] = __( 'A promo code with this code already exists.', 'nozule' );
				$valid = false;
			}
		}

		// Discount value must be greater than zero.
		if ( isset( $data['discount_value'] ) && (float) $data['discount_value'] <= 0 ) {
			$this->errors['discount_value'][] = __( 'Discount value must be greater than zero.', 'nozule' );
			$valid = false;
		}

		// Percentage must not exceed 100.
		if (
			isset( $data['discount_type'] )
			&& $data['discount_type'] === PromoCode::TYPE_PERCENTAGE
			&& isset( $data['discount_value'] )
			&& (float) $data['discount_value'] > 100
		) {
			$this->errors['discount_value'][] = __( 'Percentage discount cannot exceed 100%.', 'nozule' );
			$valid = false;
		}

		// valid_from must be <= valid_to if both are set.
		if ( ! empty( $data['valid_from'] ) && ! empty( $data['valid_to'] ) ) {
			if ( $data['valid_from'] > $data['valid_to'] ) {
				$this->errors['valid_from'][] = __( 'Valid from date must be before or equal to valid to date.', 'nozule' );
				$valid = false;
			}
		}

		return $valid;
	}

	/**
	 * Validate data for updating an existing promo code.
	 *
	 * @param int   $id   The promo code ID being updated.
	 * @param array $data The update data.
	 */
	public function validateUpdate( int $id, array $data ): bool {
		$this->errors = [];

		// Run standard field rules (partial update: only validate provided fields).
		$valid = $this->validate( $data, $this->getUpdateRules( $data ) );

		// Code must be unique, excluding self.
		if ( isset( $data['code'] ) && ! empty( $data['code'] ) ) {
			$existing = $this->repository->findByCode( $data['code'] );
			if ( $existing && $existing->id !== $id ) {
				$this->errors['code'][] = __( 'A promo code with this code already exists.', 'nozule' );
				$valid = false;
			}
		}

		// Discount value must be greater than zero if provided.
		if ( isset( $data['discount_value'] ) && (float) $data['discount_value'] <= 0 ) {
			$this->errors['discount_value'][] = __( 'Discount value must be greater than zero.', 'nozule' );
			$valid = false;
		}

		// Percentage must not exceed 100.
		if (
			isset( $data['discount_type'] )
			&& $data['discount_type'] === PromoCode::TYPE_PERCENTAGE
			&& isset( $data['discount_value'] )
			&& (float) $data['discount_value'] > 100
		) {
			$this->errors['discount_value'][] = __( 'Percentage discount cannot exceed 100%.', 'nozule' );
			$valid = false;
		}

		// valid_from must be <= valid_to if both are set.
		if ( ! empty( $data['valid_from'] ) && ! empty( $data['valid_to'] ) ) {
			if ( $data['valid_from'] > $data['valid_to'] ) {
				$this->errors['valid_from'][] = __( 'Valid from date must be before or equal to valid to date.', 'nozule' );
				$valid = false;
			}
		}

		return $valid;
	}

	/**
	 * Validate that a promo code can be applied to a booking.
	 *
	 * @param PromoCode $promo    The promo code to validate.
	 * @param float     $subtotal The booking subtotal.
	 * @param int       $nights   The number of nights in the booking.
	 * @param int|null  $guestId  The guest ID, if available.
	 */
	public function validateApplication( PromoCode $promo, float $subtotal, int $nights, ?int $guestId = null ): bool {
		$this->errors = [];
		$valid        = true;

		// Check active status and date validity.
		if ( ! $promo->isValid() ) {
			$this->errors['code'][] = __( 'This promo code is not currently valid.', 'nozule' );
			$valid = false;
		}

		// Check global usage limit.
		if ( ! $promo->hasUsesRemaining() ) {
			$this->errors['code'][] = __( 'This promo code has reached its maximum usage limit.', 'nozule' );
			$valid = false;
		}

		// Check minimum nights requirement.
		if ( ! empty( $promo->min_nights ) && $promo->min_nights > 0 && $nights < $promo->min_nights ) {
			$this->errors['nights'][] = sprintf(
				/* translators: %d: minimum number of nights required */
				__( 'This promo code requires a minimum of %d nights.', 'nozule' ),
				$promo->min_nights
			);
			$valid = false;
		}

		// Check minimum amount requirement.
		if ( ! empty( $promo->min_amount ) && $promo->min_amount > 0 && $subtotal < $promo->min_amount ) {
			$this->errors['subtotal'][] = sprintf(
				/* translators: %s: minimum amount required */
				__( 'This promo code requires a minimum subtotal of %s.', 'nozule' ),
				number_format( $promo->min_amount, 2 )
			);
			$valid = false;
		}

		// Check per-guest usage limit.
		if ( $guestId && ! empty( $promo->per_guest_limit ) && $promo->per_guest_limit > 0 ) {
			$guestUsage = $this->repository->getGuestUsageCount( $promo->id, $guestId );
			if ( $guestUsage >= $promo->per_guest_limit ) {
				$this->errors['code'][] = __( 'You have already used this promo code the maximum number of times.', 'nozule' );
				$valid = false;
			}
		}

		return $valid;
	}

	/**
	 * Get validation rules for creating a promo code.
	 *
	 * @return array<string, array>
	 */
	private function getCreateRules(): array {
		return [
			'code'          => [ 'required', 'max' => 50 ],
			'name'          => [ 'required', 'max' => 255 ],
			'discount_type' => [ 'required', 'in' => [ PromoCode::TYPE_PERCENTAGE, PromoCode::TYPE_FIXED ] ],
			'discount_value' => [ 'required', 'numeric' ],
			'valid_from'    => [ 'date' ],
			'valid_to'      => [ 'date' ],
		];
	}

	/**
	 * Get validation rules for updating a promo code (partial update).
	 *
	 * Only validates fields that are present in the data.
	 *
	 * @return array<string, array>
	 */
	private function getUpdateRules( array $data ): array {
		$rules     = [];
		$all_rules = [
			'code'           => [ 'max' => 50 ],
			'name'           => [ 'max' => 255 ],
			'name_ar'        => [ 'max' => 255 ],
			'discount_type'  => [ 'in' => [ PromoCode::TYPE_PERCENTAGE, PromoCode::TYPE_FIXED ] ],
			'discount_value' => [ 'numeric' ],
			'min_amount'     => [ 'numeric' ],
			'max_discount'   => [ 'numeric' ],
			'min_nights'     => [ 'integer' ],
			'max_uses'       => [ 'integer' ],
			'per_guest_limit' => [ 'integer' ],
			'valid_from'     => [ 'date' ],
			'valid_to'       => [ 'date' ],
			'currency_code'  => [ 'max' => 3 ],
		];

		foreach ( $all_rules as $field => $field_rules ) {
			if ( array_key_exists( $field, $data ) ) {
				$rules[ $field ] = $field_rules;
			}
		}

		// If code is provided in an update, it must not be empty.
		if ( isset( $data['code'] ) ) {
			array_unshift( $rules['code'], 'required' );
		}

		// If name is provided in an update, it must not be empty.
		if ( isset( $data['name'] ) ) {
			array_unshift( $rules['name'], 'required' );
		}

		// If discount_type is provided in an update, it must not be empty.
		if ( isset( $data['discount_type'] ) ) {
			array_unshift( $rules['discount_type'], 'required' );
		}

		return $rules;
	}
}
