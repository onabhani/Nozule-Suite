<?php

namespace Nozule\Modules\Housekeeping\Validators;

use Nozule\Core\BaseValidator;
use Nozule\Modules\Housekeeping\Models\HousekeepingTask;
use Nozule\Modules\Housekeeping\Repositories\HousekeepingRepository;

/**
 * Validator for housekeeping task create and update operations.
 */
class HousekeepingValidator extends BaseValidator {

	private HousekeepingRepository $housekeepingRepository;

	public function __construct( HousekeepingRepository $housekeepingRepository ) {
		$this->housekeepingRepository = $housekeepingRepository;
	}

	/**
	 * Validate data for creating a new housekeeping task.
	 */
	public function validateCreate( array $data ): bool {
		$valid = $this->validate( $data, $this->createRules() );

		return empty( $this->errors );
	}

	/**
	 * Validate data for updating an existing housekeeping task.
	 */
	public function validateUpdate( int $id, array $data ): bool {
		$this->errors = [];

		// Verify the task exists.
		$task = $this->housekeepingRepository->find( $id );
		if ( ! $task ) {
			$this->errors['id'][] = __( 'Housekeeping task not found.', 'nozule' );
			return false;
		}

		$valid = $this->validate( $data, $this->updateRules() );

		return empty( $this->errors );
	}

	/**
	 * Validate a status transition.
	 */
	public function validateStatusChange( int $id, string $newStatus ): bool {
		$this->errors = [];

		if ( ! in_array( $newStatus, HousekeepingTask::validStatuses(), true ) ) {
			$this->errors['status'][] = sprintf(
				__( 'Invalid status. Must be one of: %s.', 'nozule' ),
				implode( ', ', HousekeepingTask::validStatuses() )
			);
			return false;
		}

		$task = $this->housekeepingRepository->find( $id );
		if ( ! $task ) {
			$this->errors['id'][] = __( 'Housekeeping task not found.', 'nozule' );
			return false;
		}

		// Prevent backward transitions: inspected tasks cannot go back to dirty.
		if (
			$task->status === HousekeepingTask::STATUS_INSPECTED
			&& $newStatus === HousekeepingTask::STATUS_DIRTY
		) {
			$this->errors['status'][] = __(
				'Cannot revert an inspected task back to dirty. Create a new task instead.',
				'nozule'
			);
			return false;
		}

		// Out-of-order tasks can only transition to dirty (re-opening).
		if (
			$task->status === HousekeepingTask::STATUS_OUT_OF_ORDER
			&& ! in_array( $newStatus, [ HousekeepingTask::STATUS_DIRTY ], true )
		) {
			$this->errors['status'][] = __(
				'An out-of-order task can only be moved back to dirty.',
				'nozule'
			);
			return false;
		}

		return true;
	}

	/**
	 * Validation rules for creating a task.
	 */
	private function createRules(): array {
		return [
			'room_id' => [
				'required',
				'integer',
				'min' => 1,
			],
			'status' => [
				'in' => HousekeepingTask::validStatuses(),
			],
			'priority' => [
				'in' => HousekeepingTask::validPriorities(),
			],
			'task_type' => [
				'in' => HousekeepingTask::validTaskTypes(),
			],
			'assigned_to' => [
				'integer',
			],
		];
	}

	/**
	 * Validation rules for updating a task.
	 */
	private function updateRules(): array {
		return [
			'room_id' => [
				'integer',
				'min' => 1,
			],
			'status' => [
				'in' => HousekeepingTask::validStatuses(),
			],
			'priority' => [
				'in' => HousekeepingTask::validPriorities(),
			],
			'task_type' => [
				'in' => HousekeepingTask::validTaskTypes(),
			],
			'assigned_to' => [
				'integer',
			],
		];
	}
}
