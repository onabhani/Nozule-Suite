<?php

namespace Nozule\Modules\Bookings\Validators;

use Nozule\Core\BaseValidator;
use Nozule\Modules\Bookings\Models\Booking;
use Nozule\Modules\Bookings\Models\Payment;

/**
 * Validator for booking and payment data.
 */
class BookingValidator extends BaseValidator {

	/**
	 * Validate data for creating a new booking.
	 */
	public function validateCreate( array $data ): bool {
		$rules = [
			'room_type_id' => [ 'required', 'integer' ],
			'check_in'     => [ 'required', 'date', 'futureDate' ],
			'check_out'    => [ 'required', 'date' ],
			'adults'       => [ 'required', 'integer', 'min' => 1 ],
			'source'       => [ 'in' => Booking::validSources() ],
		];

		$valid = $this->validate( $data, $rules );

		// Cross-field: check_out must be after check_in.
		if ( ! empty( $data['check_in'] ) && ! empty( $data['check_out'] ) ) {
			if ( strtotime( $data['check_out'] ) <= strtotime( $data['check_in'] ) ) {
				$this->errors['check_out'][] = __( 'Check-out date must be after check-in date.', 'nozule' );
				$valid = false;
			}
		}

		// Guest info: either guest_id or at least email + name.
		// Support both flat (guest_email) and nested (guest.email) formats.
		if ( empty( $data['guest_id'] ) ) {
			$nested = $data['guest'] ?? [];
			if ( empty( $data['guest_email'] ) && empty( $nested['email'] ) ) {
				$this->errors['guest_email'][] = __( 'Guest email is required when no guest ID is provided.', 'nozule' );
				$valid = false;
			}
			if ( empty( $data['guest_first_name'] ) && empty( $nested['first_name'] ) ) {
				$this->errors['guest_first_name'][] = __( 'Guest first name is required when no guest ID is provided.', 'nozule' );
				$valid = false;
			}
			if ( empty( $data['guest_last_name'] ) && empty( $nested['last_name'] ) ) {
				$this->errors['guest_last_name'][] = __( 'Guest last name is required when no guest ID is provided.', 'nozule' );
				$valid = false;
			}
		}

		return $valid;
	}

	/**
	 * Validate data for updating an existing booking.
	 */
	public function validateUpdate( array $data ): bool {
		$rules = [];

		if ( isset( $data['check_in'] ) ) {
			$rules['check_in'] = [ 'date' ];
		}
		if ( isset( $data['check_out'] ) ) {
			$rules['check_out'] = [ 'date' ];
		}
		if ( isset( $data['adults'] ) ) {
			$rules['adults'] = [ 'integer', 'min' => 1 ];
		}
		if ( isset( $data['children'] ) ) {
			$rules['children'] = [ 'integer', 'min' => 0 ];
		}
		if ( isset( $data['status'] ) ) {
			$rules['status'] = [ 'in' => Booking::validStatuses() ];
		}
		if ( isset( $data['source'] ) ) {
			$rules['source'] = [ 'in' => Booking::validSources() ];
		}

		$valid = $this->validate( $data, $rules );

		// Cross-field date validation if both are present.
		$check_in  = $data['check_in'] ?? null;
		$check_out = $data['check_out'] ?? null;

		if ( $check_in && $check_out ) {
			if ( strtotime( $check_out ) <= strtotime( $check_in ) ) {
				$this->errors['check_out'][] = __( 'Check-out date must be after check-in date.', 'nozule' );
				$valid = false;
			}
		}

		return $valid;
	}

	/**
	 * Validate payment data.
	 */
	public function validatePayment( array $data ): bool {
		$rules = [
			'amount' => [ 'required', 'numeric', 'min' => 0.01 ],
			'method' => [ 'required', 'in' => Payment::validMethods() ],
		];

		return $this->validate( $data, $rules );
	}

	/**
	 * Validate cancellation data.
	 */
	public function validateCancellation( array $data ): bool {
		$rules = [
			'reason' => [ 'max' => 1000 ],
		];

		return $this->validate( $data, $rules );
	}
}
