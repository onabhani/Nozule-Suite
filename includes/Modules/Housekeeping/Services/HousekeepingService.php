<?php

namespace Nozule\Modules\Housekeeping\Services;

use Nozule\Core\EventDispatcher;
use Nozule\Core\Logger;
use Nozule\Modules\Housekeeping\Models\HousekeepingTask;
use Nozule\Modules\Housekeeping\Repositories\HousekeepingRepository;
use Nozule\Modules\Housekeeping\Validators\HousekeepingValidator;
use Nozule\Modules\Rooms\Repositories\RoomRepository;

/**
 * Service layer orchestrating housekeeping task operations.
 *
 * Manages the lifecycle of housekeeping tasks: creation, assignment,
 * status transitions, and integration with the room module.
 */
class HousekeepingService {

	private HousekeepingRepository $housekeepingRepository;
	private HousekeepingValidator $validator;
	private RoomRepository $roomRepository;
	private EventDispatcher $events;
	private Logger $logger;

	public function __construct(
		HousekeepingRepository $housekeepingRepository,
		HousekeepingValidator $validator,
		RoomRepository $roomRepository,
		EventDispatcher $events,
		Logger $logger
	) {
		$this->housekeepingRepository = $housekeepingRepository;
		$this->validator              = $validator;
		$this->roomRepository         = $roomRepository;
		$this->events                 = $events;
		$this->logger                 = $logger;
	}

	// =========================================================================
	// Query operations
	// =========================================================================

	/**
	 * Get tasks with optional filters.
	 *
	 * @return HousekeepingTask[]
	 */
	public function getTasks( ?string $status = null, ?int $roomId = null, ?int $assigneeId = null ): array {
		if ( $status ) {
			return $this->housekeepingRepository->getByStatus( $status );
		}

		if ( $roomId ) {
			return $this->housekeepingRepository->getByRoom( $roomId );
		}

		if ( $assigneeId ) {
			return $this->housekeepingRepository->getByAssignee( $assigneeId );
		}

		return $this->housekeepingRepository->getTodaysTasks();
	}

	/**
	 * Get all tasks with joined room and room type data.
	 *
	 * @return HousekeepingTask[]
	 */
	public function getTasksWithRoomInfo(): array {
		return $this->housekeepingRepository->getAllWithRoomInfo();
	}

	/**
	 * Get task counts grouped by status.
	 *
	 * @return array<string, int>
	 */
	public function getStatusCounts(): array {
		return $this->housekeepingRepository->countByStatus();
	}

	/**
	 * Find a single task by ID.
	 */
	public function findTask( int $id ): ?HousekeepingTask {
		return $this->housekeepingRepository->find( $id );
	}

	// =========================================================================
	// CRUD operations
	// =========================================================================

	/**
	 * Create a new housekeeping task.
	 *
	 * @return HousekeepingTask|array Task on success, errors on failure.
	 */
	public function createTask( array $data ): HousekeepingTask|array {
		// Set defaults.
		if ( ! isset( $data['status'] ) ) {
			$data['status'] = HousekeepingTask::STATUS_DIRTY;
		}
		if ( ! isset( $data['priority'] ) ) {
			$data['priority'] = HousekeepingTask::PRIORITY_NORMAL;
		}
		if ( ! isset( $data['task_type'] ) ) {
			$data['task_type'] = HousekeepingTask::TYPE_CHECKOUT_CLEAN;
		}

		// Validate.
		if ( ! $this->validator->validateCreate( $data ) ) {
			return $this->validator->getErrors();
		}

		// Verify the room exists.
		$room = $this->roomRepository->find( (int) $data['room_id'] );
		if ( ! $room ) {
			return [ 'room_id' => [ __( 'The specified room does not exist.', 'nozule' ) ] ];
		}

		// Set created_by to current user if not provided.
		if ( ! isset( $data['created_by'] ) ) {
			$data['created_by'] = get_current_user_id() ?: null;
		}

		$task = $this->housekeepingRepository->create( $data );
		if ( ! $task ) {
			$this->logger->error( 'Failed to create housekeeping task', [ 'data' => $data ] );
			return [ 'general' => [ __( 'Failed to create housekeeping task.', 'nozule' ) ] ];
		}

		$this->events->dispatch( 'housekeeping/task_created', $task );
		$this->logger->info( 'Housekeeping task created', [
			'id'      => $task->id,
			'room_id' => $task->room_id,
			'type'    => $task->task_type,
		] );

		return $task;
	}

	/**
	 * Update an existing housekeeping task.
	 *
	 * @return HousekeepingTask|array Updated task on success, errors on failure.
	 */
	public function updateTask( int $id, array $data ): HousekeepingTask|array {
		$existing = $this->housekeepingRepository->find( $id );
		if ( ! $existing ) {
			return [ 'id' => [ __( 'Housekeeping task not found.', 'nozule' ) ] ];
		}

		if ( ! $this->validator->validateUpdate( $id, $data ) ) {
			return $this->validator->getErrors();
		}

		$success = $this->housekeepingRepository->update( $id, $data );
		if ( ! $success ) {
			$this->logger->error( 'Failed to update housekeeping task', [ 'id' => $id ] );
			return [ 'general' => [ __( 'Failed to update housekeeping task.', 'nozule' ) ] ];
		}

		$updated = $this->housekeepingRepository->find( $id );

		$this->events->dispatch( 'housekeeping/task_updated', $updated, $existing );
		$this->logger->info( 'Housekeeping task updated', [ 'id' => $id ] );

		return $updated;
	}

	// =========================================================================
	// Status transitions
	// =========================================================================

	/**
	 * Update the status of a housekeeping task.
	 *
	 * Automatically sets started_at when moving to clean and completed_at
	 * when moving to inspected.
	 *
	 * @return HousekeepingTask|array Updated task on success, errors on failure.
	 */
	public function updateTaskStatus( int $id, string $status ): HousekeepingTask|array {
		if ( ! $this->validator->validateStatusChange( $id, $status ) ) {
			return $this->validator->getErrors();
		}

		$updateData = [ 'status' => $status ];

		// Set started_at when cleaning begins (transitioning from dirty to clean).
		$task = $this->housekeepingRepository->find( $id );
		if ( $task->isDirty() && $status === HousekeepingTask::STATUS_CLEAN ) {
			$updateData['started_at'] = current_time( 'mysql', true );
		}

		// Set completed_at when task is inspected.
		if ( $status === HousekeepingTask::STATUS_INSPECTED ) {
			$updateData['completed_at'] = current_time( 'mysql', true );
		}

		$success = $this->housekeepingRepository->update( $id, $updateData );
		if ( ! $success ) {
			return [ 'general' => [ __( 'Failed to update task status.', 'nozule' ) ] ];
		}

		$updated = $this->housekeepingRepository->find( $id );

		$this->events->dispatch( 'housekeeping/task_status_changed', $updated, $task->status );
		$this->logger->info( 'Housekeeping task status changed', [
			'id'         => $id,
			'old_status' => $task->status,
			'new_status' => $status,
		] );

		return $updated;
	}

	/**
	 * Assign a task to a staff member.
	 *
	 * @return HousekeepingTask|array Updated task on success, errors on failure.
	 */
	public function assignTask( int $id, int $userId ): HousekeepingTask|array {
		$task = $this->housekeepingRepository->find( $id );
		if ( ! $task ) {
			return [ 'id' => [ __( 'Housekeeping task not found.', 'nozule' ) ] ];
		}

		$success = $this->housekeepingRepository->update( $id, [
			'assigned_to' => $userId,
		] );

		if ( ! $success ) {
			return [ 'general' => [ __( 'Failed to assign task.', 'nozule' ) ] ];
		}

		$updated = $this->housekeepingRepository->find( $id );

		$this->events->dispatch( 'housekeeping/task_assigned', $updated, $userId );
		$this->logger->info( 'Housekeeping task assigned', [
			'id'          => $id,
			'assigned_to' => $userId,
		] );

		return $updated;
	}

	// =========================================================================
	// Room-level convenience methods
	// =========================================================================

	/**
	 * Mark a room as dirty by creating a new housekeeping task.
	 *
	 * Typically called on guest checkout.
	 *
	 * @return HousekeepingTask|array Task on success, errors on failure.
	 */
	public function markRoomDirty( int $roomId ): HousekeepingTask|array {
		// Check if there is already an active (open) task for this room.
		$existingTask = $this->housekeepingRepository->getActiveTaskForRoom( $roomId );
		if ( $existingTask && $existingTask->isDirty() ) {
			// Already has a dirty task, return it.
			return $existingTask;
		}

		return $this->createTask( [
			'room_id'   => $roomId,
			'status'    => HousekeepingTask::STATUS_DIRTY,
			'priority'  => HousekeepingTask::PRIORITY_NORMAL,
			'task_type' => HousekeepingTask::TYPE_CHECKOUT_CLEAN,
		] );
	}

	/**
	 * Mark a room as clean by updating its active housekeeping task.
	 *
	 * @return HousekeepingTask|array Updated task on success, errors on failure.
	 */
	public function markRoomClean( int $roomId ): HousekeepingTask|array {
		$task = $this->housekeepingRepository->getActiveTaskForRoom( $roomId );
		if ( ! $task ) {
			return [ 'room_id' => [ __( 'No active housekeeping task found for this room.', 'nozule' ) ] ];
		}

		return $this->updateTaskStatus( $task->id, HousekeepingTask::STATUS_CLEAN );
	}

	/**
	 * Mark a room as inspected by updating its active housekeeping task.
	 *
	 * After inspection, the room is set to available via the RoomRepository.
	 *
	 * @return HousekeepingTask|array Updated task on success, errors on failure.
	 */
	public function markRoomInspected( int $roomId ): HousekeepingTask|array {
		$task = $this->housekeepingRepository->getActiveTaskForRoom( $roomId );
		if ( ! $task ) {
			return [ 'room_id' => [ __( 'No active housekeeping task found for this room.', 'nozule' ) ] ];
		}

		$result = $this->updateTaskStatus( $task->id, HousekeepingTask::STATUS_INSPECTED );

		if ( $result instanceof HousekeepingTask ) {
			// Room has passed inspection — mark it as available.
			$this->roomRepository->updateStatus( $roomId, 'available' );

			$this->events->dispatch( 'housekeeping/room_inspected', $roomId, $result );
			$this->logger->info( 'Room marked available after inspection', [
				'room_id' => $roomId,
				'task_id' => $result->id,
			] );
		}

		return $result;
	}
}
