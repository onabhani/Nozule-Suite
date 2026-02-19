<?php

namespace Nozule\Modules\Groups\Validators;

use Nozule\Core\BaseValidator;
use Nozule\Modules\Groups\Models\GroupBooking;
use Nozule\Modules\Groups\Models\GroupBookingRoom;

/**
 * Validator for group booking and room allocation data.
 */
class GroupBookingValidator extends BaseValidator {

	/**
	 * Valid status transitions for group bookings.
	 *
	 * Maps current status to an array of allowed next statuses.
	 *
	 * @var array<string, string[]>
	 */
	private static array $statusTransitions = [
		GroupBooking::STATUS_TENTATIVE   => [
			GroupBooking::STATUS_CONFIRMED,
			GroupBooking::STATUS_CANCELLED,
		],
		GroupBooking::STATUS_CONFIRMED   => [
			GroupBooking::STATUS_CHECKED_IN,
			GroupBooking::STATUS_CANCELLED,
		],
		GroupBooking::STATUS_CHECKED_IN  => [
			GroupBooking::STATUS_CHECKED_OUT,
		],
		GroupBooking::STATUS_CHECKED_OUT => [],
		GroupBooking::STATUS_CANCELLED   => [],
	];

	/**
	 * Validate data for creating a new group booking.
	 */
	public function validateCreate( array $data ): bool {
		$rules = [
			'group_name' => [ 'required', 'max' => 255 ],
			'check_in'   => [ 'required', 'date' ],
			'check_out'  => [ 'required', 'date' ],
		];

		$valid = $this->validate( $data, $rules );

		// Cross-field: check_out must be after check_in.
		if ( ! empty( $data['check_in'] ) && ! empty( $data['check_out'] ) ) {
			if ( strtotime( $data['check_out'] ) <= strtotime( $data['check_in'] ) ) {
				$this->errors['check_out'][] = __( 'Check-out date must be after check-in date.', 'nozule' );
				$valid = false;
			}
		}

		// Optional field validations.
		if ( ! empty( $data['contact_email'] ) ) {
			$emailRules = [ 'contact_email' => [ 'email' ] ];
			if ( ! $this->validate( $data, $emailRules ) ) {
				$valid = false;
			}
		}

		if ( ! empty( $data['contact_phone'] ) ) {
			$phoneRules = [ 'contact_phone' => [ 'phone' ] ];
			if ( ! $this->validate( $data, $phoneRules ) ) {
				$valid = false;
			}
		}

		if ( ! empty( $data['status'] ) ) {
			$statusRules = [ 'status' => [ 'in' => GroupBooking::validStatuses() ] ];
			if ( ! $this->validate( $data, $statusRules ) ) {
				$valid = false;
			}
		}

		return $valid;
	}

	/**
	 * Validate data for updating an existing group booking.
	 */
	public function validateUpdate( array $data ): bool {
		$rules = [];

		if ( isset( $data['group_name'] ) ) {
			$rules['group_name'] = [ 'required', 'max' => 255 ];
		}
		if ( isset( $data['check_in'] ) ) {
			$rules['check_in'] = [ 'date' ];
		}
		if ( isset( $data['check_out'] ) ) {
			$rules['check_out'] = [ 'date' ];
		}
		if ( isset( $data['contact_email'] ) && $data['contact_email'] !== '' ) {
			$rules['contact_email'] = [ 'email' ];
		}
		if ( isset( $data['contact_phone'] ) && $data['contact_phone'] !== '' ) {
			$rules['contact_phone'] = [ 'phone' ];
		}
		if ( isset( $data['status'] ) ) {
			$rules['status'] = [ 'in' => GroupBooking::validStatuses() ];
		}
		if ( isset( $data['total_rooms'] ) ) {
			$rules['total_rooms'] = [ 'integer', 'min' => 0 ];
		}
		if ( isset( $data['total_guests'] ) ) {
			$rules['total_guests'] = [ 'integer', 'min' => 0 ];
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
	 * Validate data for adding a room to a group booking.
	 */
	public function validateAddRoom( array $data ): bool {
		$rules = [
			'room_type_id'   => [ 'required', 'integer' ],
			'rate_per_night' => [ 'required', 'numeric', 'min' => 0 ],
		];

		return $this->validate( $data, $rules );
	}

	/**
	 * Validate a group booking status change.
	 *
	 * Enforces valid status transitions:
	 * - tentative  -> confirmed, cancelled
	 * - confirmed  -> checked_in, cancelled
	 * - checked_in -> checked_out
	 * - checked_out -> (terminal)
	 * - cancelled   -> (terminal)
	 *
	 * @param string $currentStatus The current status of the group booking.
	 * @param string $newStatus     The desired new status.
	 */
	public function validateStatusChange( string $currentStatus, string $newStatus ): bool {
		$this->errors = [];

		$allowed = self::$statusTransitions[ $currentStatus ] ?? [];

		if ( ! in_array( $newStatus, $allowed, true ) ) {
			$this->errors['status'][] = sprintf(
				__( 'Cannot transition from "%s" to "%s".', 'nozule' ),
				$currentStatus,
				$newStatus
			);
			return false;
		}

		return true;
	}
}
