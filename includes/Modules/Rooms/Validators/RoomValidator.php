<?php

namespace Venezia\Modules\Rooms\Validators;

use Venezia\Core\BaseValidator;
use Venezia\Modules\Rooms\Models\Room;
use Venezia\Modules\Rooms\Repositories\RoomRepository;
use Venezia\Modules\Rooms\Repositories\RoomTypeRepository;

/**
 * Validator for individual room create and update operations.
 */
class RoomValidator extends BaseValidator {

	private RoomRepository $roomRepository;
	private RoomTypeRepository $roomTypeRepository;

	public function __construct(
		RoomRepository $roomRepository,
		RoomTypeRepository $roomTypeRepository
	) {
		$this->roomRepository     = $roomRepository;
		$this->roomTypeRepository = $roomTypeRepository;
	}

	/**
	 * Validate data for creating a new room.
	 */
	public function validateCreate( array $data ): bool {
		$valid = $this->validate( $data, $this->createRules() );

		if ( $valid ) {
			$this->validateRoomNumberUniqueness( $data['room_number'] ?? '', null );
			$this->validateRoomTypeExists( $data['room_type_id'] ?? 0 );
		}

		return empty( $this->errors );
	}

	/**
	 * Validate data for updating an existing room.
	 */
	public function validateUpdate( int $id, array $data ): bool {
		$valid = $this->validate( $data, $this->updateRules() );

		if ( $valid && isset( $data['room_number'] ) ) {
			$this->validateRoomNumberUniqueness( $data['room_number'], $id );
		}

		if ( $valid && isset( $data['room_type_id'] ) ) {
			$this->validateRoomTypeExists( (int) $data['room_type_id'] );
		}

		return empty( $this->errors );
	}

	/**
	 * Validate a status transition.
	 */
	public function validateStatusChange( int $roomId, string $newStatus ): bool {
		$this->errors = [];

		if ( ! in_array( $newStatus, Room::validStatuses(), true ) ) {
			$this->errors['status'][] = sprintf(
				__( 'Invalid status. Must be one of: %s.', 'venezia-hotel' ),
				implode( ', ', Room::validStatuses() )
			);
			return false;
		}

		$room = $this->roomRepository->find( $roomId );
		if ( ! $room ) {
			$this->errors['id'][] = __( 'Room not found.', 'venezia-hotel' );
			return false;
		}

		// Prevent transitioning an occupied room directly to maintenance/out_of_order.
		if (
			$room->status === Room::STATUS_OCCUPIED
			&& in_array( $newStatus, [ Room::STATUS_MAINTENANCE, Room::STATUS_OUT_OF_ORDER ], true )
		) {
			$this->errors['status'][] = __(
				'Cannot move an occupied room to maintenance or out-of-order. Check out the guest first.',
				'venezia-hotel'
			);
			return false;
		}

		return true;
	}

	/**
	 * Validation rules for creating a room.
	 */
	private function createRules(): array {
		return [
			'room_number' => [
				'required',
				'min' => 1,
				'max' => 20,
			],
			'room_type_id' => [
				'required',
				'integer',
				'min' => 1,
			],
			'floor' => [
				'integer',
			],
			'status' => [
				'in' => Room::validStatuses(),
			],
		];
	}

	/**
	 * Validation rules for updating a room.
	 */
	private function updateRules(): array {
		return [
			'room_number' => [
				'min' => 1,
				'max' => 20,
			],
			'room_type_id' => [
				'integer',
				'min' => 1,
			],
			'floor' => [
				'integer',
			],
			'status' => [
				'in' => Room::validStatuses(),
			],
		];
	}

	/**
	 * Validate that a room number is unique.
	 */
	private function validateRoomNumberUniqueness( string $roomNumber, ?int $excludeId ): void {
		if ( $roomNumber && ! $this->roomRepository->isRoomNumberUnique( $roomNumber, $excludeId ) ) {
			$this->errors['room_number'][] = __(
				'This room number is already in use.',
				'venezia-hotel'
			);
		}
	}

	/**
	 * Validate that the room type exists.
	 */
	private function validateRoomTypeExists( int $roomTypeId ): void {
		if ( $roomTypeId && ! $this->roomTypeRepository->find( $roomTypeId ) ) {
			$this->errors['room_type_id'][] = __(
				'The specified room type does not exist.',
				'venezia-hotel'
			);
		}
	}
}
