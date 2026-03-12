<?php

namespace Nozule\Modules\Maintenance\Services;

use Nozule\Core\EventDispatcher;
use Nozule\Core\Logger;
use Nozule\Modules\Maintenance\Models\WorkOrder;
use Nozule\Modules\Maintenance\Repositories\WorkOrderRepository;
use Nozule\Modules\Maintenance\Validators\WorkOrderValidator;
use Nozule\Modules\Rooms\Repositories\RoomRepository;

/**
 * Service layer for maintenance work order operations.
 */
class MaintenanceService {

	private WorkOrderRepository $repository;
	private WorkOrderValidator $validator;
	private RoomRepository $roomRepository;
	private EventDispatcher $events;
	private Logger $logger;

	public function __construct(
		WorkOrderRepository $repository,
		WorkOrderValidator $validator,
		RoomRepository $roomRepository,
		EventDispatcher $events,
		Logger $logger
	) {
		$this->repository     = $repository;
		$this->validator      = $validator;
		$this->roomRepository = $roomRepository;
		$this->events         = $events;
		$this->logger         = $logger;
	}

	// =========================================================================
	// Query operations
	// =========================================================================

	/**
	 * List work orders with optional filters and pagination.
	 *
	 * @return array{ items: WorkOrder[], total: int }
	 */
	public function list( ?string $status = null, ?int $roomId = null, ?int $assigneeId = null, int $page = 1, int $perPage = 50 ): array {
		$offset = ( max( 1, $page ) - 1 ) * $perPage;
		$items  = $this->repository->getAllWithRoomInfo( $status, $roomId, $assigneeId, $perPage, $offset );
		$total  = $this->repository->countFiltered( $status, $roomId, $assigneeId );

		return [ 'items' => $items, 'total' => $total ];
	}

	/**
	 * Find a single work order by ID.
	 */
	public function find( int $id ): ?WorkOrder {
		return $this->repository->find( $id );
	}

	/**
	 * Get status counts for dashboard stats.
	 *
	 * @return array<string, int>
	 */
	public function getStatusCounts(): array {
		return $this->repository->countByStatus();
	}

	// =========================================================================
	// CRUD operations
	// =========================================================================

	/**
	 * Create a new work order.
	 *
	 * @return WorkOrder|array Work order on success, errors on failure.
	 */
	public function create( array $data ): WorkOrder|array {
		if ( ! isset( $data['status'] ) ) {
			$data['status'] = WorkOrder::STATUS_OPEN;
		}
		if ( ! isset( $data['priority'] ) ) {
			$data['priority'] = WorkOrder::PRIORITY_NORMAL;
		}
		if ( ! isset( $data['category'] ) ) {
			$data['category'] = WorkOrder::CATEGORY_GENERAL;
		}

		if ( ! $this->validator->validateCreate( $data ) ) {
			return $this->validator->getErrors();
		}

		// Verify the room exists.
		$room = $this->roomRepository->find( (int) $data['room_id'] );
		if ( ! $room ) {
			return [ 'room_id' => [ __( 'The specified room does not exist.', 'nozule' ) ] ];
		}

		if ( ! isset( $data['reported_by'] ) ) {
			$data['reported_by'] = get_current_user_id() ?: null;
		}

		$order = $this->repository->create( $data );
		if ( ! $order ) {
			$this->logger->error( 'Failed to create maintenance work order', [ 'data' => $data ] );
			return [ 'general' => [ __( 'Failed to create work order.', 'nozule' ) ] ];
		}

		$this->events->dispatch( 'maintenance/order_created', $order );
		$this->logger->info( 'Maintenance work order created', [
			'id'      => $order->id,
			'room_id' => $order->room_id,
			'title'   => $order->title,
		] );

		return $order;
	}

	/**
	 * Update an existing work order.
	 *
	 * @return WorkOrder|array Updated work order on success, errors on failure.
	 */
	public function update( int $id, array $data ): WorkOrder|array {
		$existing = $this->repository->find( $id );
		if ( ! $existing ) {
			return [ 'id' => [ __( 'Work order not found.', 'nozule' ) ] ];
		}

		if ( ! $this->validator->validateUpdate( $id, $data ) ) {
			return $this->validator->getErrors();
		}

		$success = $this->repository->update( $id, $data );
		if ( ! $success ) {
			$this->logger->error( 'Failed to update maintenance work order', [ 'id' => $id ] );
			return [ 'general' => [ __( 'Failed to update work order.', 'nozule' ) ] ];
		}

		$updated = $this->repository->find( $id );

		$this->events->dispatch( 'maintenance/order_updated', $updated, $existing );
		$this->logger->info( 'Maintenance work order updated', [ 'id' => $id ] );

		return $updated;
	}

	/**
	 * Delete a work order.
	 */
	public function delete( int $id ): WorkOrder|array {
		$order = $this->repository->find( $id );
		if ( ! $order ) {
			return [ 'id' => [ __( 'Work order not found.', 'nozule' ) ] ];
		}

		$success = $this->repository->delete( $id );
		if ( ! $success ) {
			$this->logger->error( 'Failed to delete maintenance work order', [ 'id' => $id ] );
			return [ 'general' => [ __( 'Failed to delete work order.', 'nozule' ) ] ];
		}

		$this->events->dispatch( 'maintenance/order_deleted', $order );
		$this->logger->info( 'Maintenance work order deleted', [ 'id' => $id ] );

		return $order;
	}

	// =========================================================================
	// Status transitions
	// =========================================================================

	/**
	 * Update the status of a work order.
	 *
	 * Automatically sets started_at when moving to in_progress
	 * and resolved_at when moving to resolved.
	 *
	 * @return WorkOrder|array Updated work order on success, errors on failure.
	 */
	public function updateStatus( int $id, string $status, ?string $resolutionNotes = null ): WorkOrder|array {
		if ( ! $this->validator->validateStatusChange( $id, $status ) ) {
			return $this->validator->getErrors();
		}

		$order      = $this->repository->find( $id );
		$updateData = [ 'status' => $status ];

		// Set started_at when work begins.
		if ( $order->isOpen() && $status === WorkOrder::STATUS_IN_PROGRESS ) {
			$updateData['started_at'] = current_time( 'mysql', true );
		}

		// Set resolved_at when issue is resolved.
		if ( $status === WorkOrder::STATUS_RESOLVED ) {
			$updateData['resolved_at'] = current_time( 'mysql', true );
			if ( $resolutionNotes ) {
				$updateData['resolution_notes'] = $resolutionNotes;
			}
		}

		$success = $this->repository->update( $id, $updateData );
		if ( ! $success ) {
			return [ 'general' => [ __( 'Failed to update work order status.', 'nozule' ) ] ];
		}

		$updated = $this->repository->find( $id );

		$this->events->dispatch( 'maintenance/order_status_changed', $updated, $order->status );
		$this->logger->info( 'Maintenance work order status changed', [
			'id'         => $id,
			'old_status' => $order->status,
			'new_status' => $status,
		] );

		return $updated;
	}

	/**
	 * Assign a work order to a staff member.
	 *
	 * @return WorkOrder|array Updated work order on success, errors on failure.
	 */
	public function assign( int $id, int $userId ): WorkOrder|array {
		$order = $this->repository->find( $id );
		if ( ! $order ) {
			return [ 'id' => [ __( 'Work order not found.', 'nozule' ) ] ];
		}

		$success = $this->repository->update( $id, [
			'assigned_to' => $userId,
		] );

		if ( ! $success ) {
			return [ 'general' => [ __( 'Failed to assign work order.', 'nozule' ) ] ];
		}

		$updated = $this->repository->find( $id );

		$this->events->dispatch( 'maintenance/order_assigned', $updated, $userId );
		$this->logger->info( 'Maintenance work order assigned', [
			'id'          => $id,
			'assigned_to' => $userId,
		] );

		return $updated;
	}
}
