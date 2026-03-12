<?php

namespace Nozule\Modules\Maintenance\Validators;

use Nozule\Core\BaseValidator;
use Nozule\Modules\Maintenance\Models\WorkOrder;
use Nozule\Modules\Maintenance\Repositories\WorkOrderRepository;

/**
 * Validator for maintenance work order create and update operations.
 */
class WorkOrderValidator extends BaseValidator {

	private WorkOrderRepository $repository;

	public function __construct( WorkOrderRepository $repository ) {
		$this->repository = $repository;
	}

	/**
	 * Validate data for creating a new work order.
	 */
	public function validateCreate( array $data ): bool {
		$this->validate( $data, $this->createRules() );

		return empty( $this->errors );
	}

	/**
	 * Validate data for updating an existing work order.
	 */
	public function validateUpdate( int $id, array $data ): bool {
		$this->errors = [];

		$order = $this->repository->find( $id );
		if ( ! $order ) {
			$this->errors['id'][] = __( 'Work order not found.', 'nozule' );
			return false;
		}

		$this->validate( $data, $this->updateRules() );

		return empty( $this->errors );
	}

	/**
	 * Validate a status transition.
	 */
	public function validateStatusChange( int $id, string $newStatus ): bool {
		$this->errors = [];

		if ( ! in_array( $newStatus, WorkOrder::validStatuses(), true ) ) {
			$this->errors['status'][] = sprintf(
				__( 'Invalid status. Must be one of: %s.', 'nozule' ),
				implode( ', ', WorkOrder::validStatuses() )
			);
			return false;
		}

		$order = $this->repository->find( $id );
		if ( ! $order ) {
			$this->errors['id'][] = __( 'Work order not found.', 'nozule' );
			return false;
		}

		// Resolved orders cannot be reopened directly — create a new order.
		if ( $order->status === WorkOrder::STATUS_RESOLVED && $newStatus === WorkOrder::STATUS_OPEN ) {
			$this->errors['status'][] = __(
				'Cannot reopen a resolved work order. Create a new one instead.',
				'nozule'
			);
			return false;
		}

		return true;
	}

	/**
	 * Validation rules for creating a work order.
	 */
	private function createRules(): array {
		return [
			'room_id' => [
				'required',
				'integer',
				'min' => 1,
			],
			'title' => [
				'required',
				'maxLength' => 255,
			],
			'category' => [
				'in' => WorkOrder::validCategories(),
			],
			'status' => [
				'in' => WorkOrder::validStatuses(),
			],
			'priority' => [
				'in' => WorkOrder::validPriorities(),
			],
			'assigned_to' => [
				'integer',
			],
		];
	}

	/**
	 * Validation rules for updating a work order.
	 */
	private function updateRules(): array {
		return [
			'room_id' => [
				'integer',
				'min' => 1,
			],
			'title' => [
				'maxLength' => 255,
			],
			'category' => [
				'in' => WorkOrder::validCategories(),
			],
			'status' => [
				'in' => WorkOrder::validStatuses(),
			],
			'priority' => [
				'in' => WorkOrder::validPriorities(),
			],
			'assigned_to' => [
				'integer',
			],
		];
	}
}
