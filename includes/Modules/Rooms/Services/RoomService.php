<?php

namespace Nozule\Modules\Rooms\Services;

use Nozule\Core\CacheManager;
use Nozule\Core\EventDispatcher;
use Nozule\Core\Logger;
use Nozule\Modules\Rooms\Models\Room;
use Nozule\Modules\Rooms\Models\RoomType;
use Nozule\Modules\Rooms\Repositories\InventoryRepository;
use Nozule\Modules\Rooms\Repositories\RoomRepository;
use Nozule\Modules\Rooms\Repositories\RoomTypeRepository;
use Nozule\Modules\Rooms\Validators\RoomTypeValidator;
use Nozule\Modules\Rooms\Validators\RoomValidator;

/**
 * Service layer orchestrating room type and room operations.
 */
class RoomService {

	private RoomTypeRepository $roomTypeRepository;
	private RoomRepository $roomRepository;
	private InventoryRepository $inventoryRepository;
	private RoomTypeValidator $roomTypeValidator;
	private RoomValidator $roomValidator;
	private CacheManager $cache;
	private EventDispatcher $events;
	private Logger $logger;

	public function __construct(
		RoomTypeRepository $roomTypeRepository,
		RoomRepository $roomRepository,
		InventoryRepository $inventoryRepository,
		RoomTypeValidator $roomTypeValidator,
		RoomValidator $roomValidator,
		CacheManager $cache,
		EventDispatcher $events,
		Logger $logger
	) {
		$this->roomTypeRepository  = $roomTypeRepository;
		$this->roomRepository      = $roomRepository;
		$this->inventoryRepository = $inventoryRepository;
		$this->roomTypeValidator   = $roomTypeValidator;
		$this->roomValidator       = $roomValidator;
		$this->cache               = $cache;
		$this->events              = $events;
		$this->logger              = $logger;
	}

	// =========================================================================
	// Room Type operations
	// =========================================================================

	/**
	 * Get all room types (ordered).
	 *
	 * @return RoomType[]
	 */
	public function getAllRoomTypes(): array {
		$cached = $this->cache->get( 'room_types_all' );
		if ( $cached !== false ) {
			return $cached;
		}

		$types = $this->roomTypeRepository->getAllOrdered();
		$this->cache->set( 'room_types_all', $types, 300 );

		return $types;
	}

	/**
	 * Get all room types without caching (for admin lists that need fresh data).
	 *
	 * @return RoomType[]
	 */
	public function getAllRoomTypesFresh(): array {
		return $this->roomTypeRepository->getAllOrdered();
	}

	/**
	 * Get active room types only.
	 *
	 * @return RoomType[]
	 */
	public function getActiveRoomTypes(): array {
		$cached = $this->cache->get( 'room_types_active' );
		if ( $cached !== false ) {
			return $cached;
		}

		$types = $this->roomTypeRepository->getActive();
		$this->cache->set( 'room_types_active', $types, 300 );

		return $types;
	}

	/**
	 * Find a room type by ID.
	 */
	public function findRoomType( int $id ): ?RoomType {
		return $this->roomTypeRepository->find( $id );
	}

	/**
	 * Find a room type by slug.
	 */
	public function findRoomTypeBySlug( string $slug ): ?RoomType {
		return $this->roomTypeRepository->findBySlug( $slug );
	}

	/**
	 * Create a new room type.
	 *
	 * @return RoomType|array RoomType on success, array of errors on failure.
	 */
	public function createRoomType( array $data ): RoomType|array {
		// Auto-generate slug if not provided.
		if ( empty( $data['slug'] ) && ! empty( $data['name'] ) ) {
			$data['slug'] = sanitize_title( $data['name'] );
		}

		if ( ! $this->roomTypeValidator->validateCreate( $data ) ) {
			return $this->roomTypeValidator->getErrors();
		}

		$roomType = $this->roomTypeRepository->create( $data );
		if ( ! $roomType ) {
			$this->logger->error( 'Failed to create room type', [ 'data' => $data ] );
			return [ 'general' => [ __( 'Failed to create room type.', 'nozule' ) ] ];
		}

		$this->invalidateRoomTypeCache();
		$this->events->dispatch( 'rooms/room_type_created', $roomType );
		$this->logger->info( 'Room type created', [ 'id' => $roomType->id, 'name' => $roomType->name ] );

		return $roomType;
	}

	/**
	 * Update an existing room type.
	 *
	 * @return RoomType|array Updated RoomType on success, errors on failure.
	 */
	public function updateRoomType( int $id, array $data ): RoomType|array {
		$existing = $this->roomTypeRepository->find( $id );
		if ( ! $existing ) {
			return [ 'id' => [ __( 'Room type not found.', 'nozule' ) ] ];
		}

		if ( ! $this->roomTypeValidator->validateUpdate( $id, $data ) ) {
			return $this->roomTypeValidator->getErrors();
		}

		$success = $this->roomTypeRepository->update( $id, $data );
		if ( ! $success ) {
			$this->logger->error( 'Failed to update room type', [ 'id' => $id ] );
			return [ 'general' => [ __( 'Failed to update room type.', 'nozule' ) ] ];
		}

		$updated = $this->roomTypeRepository->find( $id );

		$this->invalidateRoomTypeCache();
		$this->events->dispatch( 'rooms/room_type_updated', $updated, $existing );
		$this->logger->info( 'Room type updated', [ 'id' => $id ] );

		return $updated;
	}

	/**
	 * Delete a room type.
	 *
	 * Prevents deletion if rooms are still associated.
	 */
	public function deleteRoomType( int $id ): true|array {
		$existing = $this->roomTypeRepository->find( $id );
		if ( ! $existing ) {
			return [ 'id' => [ __( 'Room type not found.', 'nozule' ) ] ];
		}

		$roomCount = $this->roomTypeRepository->getRoomCount( $id );
		if ( $roomCount > 0 ) {
			return [ 'id' => [
				sprintf(
					__( 'Cannot delete room type with %d associated rooms. Remove or reassign rooms first.', 'nozule' ),
					$roomCount
				),
			] ];
		}

		$success = $this->roomTypeRepository->delete( $id );
		if ( ! $success ) {
			return [ 'general' => [ __( 'Failed to delete room type.', 'nozule' ) ] ];
		}

		$this->invalidateRoomTypeCache();
		$this->events->dispatch( 'rooms/room_type_deleted', $existing );
		$this->logger->info( 'Room type deleted', [ 'id' => $id, 'name' => $existing->name ] );

		return true;
	}

	/**
	 * Reorder room types.
	 *
	 * @param int[] $orderedIds
	 */
	public function reorderRoomTypes( array $orderedIds ): bool {
		$success = $this->roomTypeRepository->reorder( $orderedIds );

		if ( $success ) {
			$this->invalidateRoomTypeCache();
		}

		return $success;
	}

	// =========================================================================
	// Room operations
	// =========================================================================

	/**
	 * Get all rooms, optionally filtered.
	 *
	 * @return Room[]
	 */
	public function getRooms( ?int $roomTypeId = null, ?string $status = null ): array {
		if ( $roomTypeId ) {
			return $this->roomRepository->getByRoomType( $roomTypeId );
		}

		if ( $status ) {
			return $this->roomRepository->getByStatus( $status );
		}

		return $this->roomRepository->getAllWithType();
	}

	/**
	 * Find a room by ID.
	 */
	public function findRoom( int $id ): ?Room {
		return $this->roomRepository->find( $id );
	}

	/**
	 * Find a room by its number.
	 */
	public function findRoomByNumber( string $roomNumber ): ?Room {
		return $this->roomRepository->findByNumber( $roomNumber );
	}

	/**
	 * Create a new room.
	 *
	 * @return Room|array Room on success, errors on failure.
	 */
	public function createRoom( array $data ): Room|array {
		if ( ! isset( $data['status'] ) ) {
			$data['status'] = Room::STATUS_AVAILABLE;
		}

		if ( ! $this->roomValidator->validateCreate( $data ) ) {
			return $this->roomValidator->getErrors();
		}

		$room = $this->roomRepository->create( $data );
		if ( ! $room ) {
			$this->logger->error( 'Failed to create room', [ 'data' => $data ] );
			return [ 'general' => [ __( 'Failed to create room.', 'nozule' ) ] ];
		}

		$this->events->dispatch( 'rooms/room_created', $room );
		$this->logger->info( 'Room created', [ 'id' => $room->id, 'number' => $room->room_number ] );

		return $room;
	}

	/**
	 * Update an existing room.
	 *
	 * @return Room|array Updated Room on success, errors on failure.
	 */
	public function updateRoom( int $id, array $data ): Room|array {
		$existing = $this->roomRepository->find( $id );
		if ( ! $existing ) {
			return [ 'id' => [ __( 'Room not found.', 'nozule' ) ] ];
		}

		if ( ! $this->roomValidator->validateUpdate( $id, $data ) ) {
			return $this->roomValidator->getErrors();
		}

		$success = $this->roomRepository->update( $id, $data );
		if ( ! $success ) {
			$this->logger->error( 'Failed to update room', [ 'id' => $id ] );
			return [ 'general' => [ __( 'Failed to update room.', 'nozule' ) ] ];
		}

		$updated = $this->roomRepository->find( $id );

		$this->events->dispatch( 'rooms/room_updated', $updated, $existing );
		$this->logger->info( 'Room updated', [ 'id' => $id ] );

		return $updated;
	}

	/**
	 * Delete a room.
	 */
	public function deleteRoom( int $id ): true|array {
		$existing = $this->roomRepository->find( $id );
		if ( ! $existing ) {
			return [ 'id' => [ __( 'Room not found.', 'nozule' ) ] ];
		}

		if ( $existing->status === Room::STATUS_OCCUPIED ) {
			return [ 'status' => [ __( 'Cannot delete an occupied room.', 'nozule' ) ] ];
		}

		$success = $this->roomRepository->delete( $id );
		if ( ! $success ) {
			return [ 'general' => [ __( 'Failed to delete room.', 'nozule' ) ] ];
		}

		$this->events->dispatch( 'rooms/room_deleted', $existing );
		$this->logger->info( 'Room deleted', [ 'id' => $id, 'number' => $existing->room_number ] );

		return true;
	}

	/**
	 * Update a room's status with validation.
	 *
	 * @return Room|array Updated Room on success, errors on failure.
	 */
	public function updateRoomStatus( int $id, string $status ): Room|array {
		if ( ! $this->roomValidator->validateStatusChange( $id, $status ) ) {
			return $this->roomValidator->getErrors();
		}

		$success = $this->roomRepository->updateStatus( $id, $status );
		if ( ! $success ) {
			return [ 'general' => [ __( 'Failed to update room status.', 'nozule' ) ] ];
		}

		$room = $this->roomRepository->find( $id );
		$this->events->dispatch( 'rooms/room_status_changed', $room, $status );

		return $room;
	}

	// =========================================================================
	// Inventory initialization
	// =========================================================================

	/**
	 * Initialize inventory for a room type over a date range.
	 *
	 * Uses the count of rooms in "available" or "occupied" status as
	 * the total_rooms value.
	 */
	public function initializeInventory( int $roomTypeId, string $startDate, string $endDate ): int {
		$totalRooms = $this->roomRepository->countByTypeAndStatus( $roomTypeId );

		return $this->inventoryRepository->initializeInventory(
			$roomTypeId,
			$totalRooms,
			$startDate,
			$endDate
		);
	}

	/**
	 * Invalidate all room type related caches.
	 */
	private function invalidateRoomTypeCache(): void {
		$this->cache->delete( 'room_types_all' );
		$this->cache->delete( 'room_types_active' );
		$this->cache->invalidateTag( 'room_types' );
	}
}
