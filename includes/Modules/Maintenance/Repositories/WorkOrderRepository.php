<?php

namespace Nozule\Modules\Maintenance\Repositories;

use Nozule\Core\BaseRepository;
use Nozule\Core\Database;
use Nozule\Modules\Maintenance\Models\WorkOrder;

/**
 * Repository for maintenance work order CRUD and querying.
 */
class WorkOrderRepository extends BaseRepository {

	protected string $table = 'maintenance_orders';
	protected string $model = WorkOrder::class;

	public function __construct( Database $db ) {
		parent::__construct( $db );
	}

	/**
	 * Create a new work order.
	 *
	 * @return WorkOrder|false
	 */
	public function create( array $data ): WorkOrder|false {
		$now = current_time( 'mysql', true );
		$data['created_at'] = $data['created_at'] ?? $now;
		$data['updated_at'] = $data['updated_at'] ?? $now;

		$id = $this->db->insert( $this->table, $data );
		if ( $id === false ) {
			return false;
		}

		return $this->find( $id );
	}

	/**
	 * Update a work order.
	 */
	public function update( int $id, array $data ): bool {
		$data['updated_at'] = current_time( 'mysql', true );

		return $this->db->update( $this->table, $data, [ 'id' => $id ] ) !== false;
	}

	/**
	 * Get work orders filtered by status.
	 *
	 * @return WorkOrder[]
	 */
	public function getByStatus( string $status ): array {
		$table = $this->tableName();
		$sql   = "SELECT * FROM {$table} WHERE status = %s";
		$args  = [ $status ];
		$sql   = $this->applyPropertyScope( $sql, $args );
		$sql  .= ' ORDER BY FIELD(priority, \'urgent\', \'high\', \'normal\', \'low\'), created_at DESC';

		$rows = $this->db->getResults( $sql, ...$args );

		return WorkOrder::fromRows( $rows );
	}

	/**
	 * Get work orders for a specific room.
	 *
	 * @return WorkOrder[]
	 */
	public function getByRoom( int $roomId ): array {
		$table = $this->tableName();
		$sql   = "SELECT * FROM {$table} WHERE room_id = %d";
		$args  = [ $roomId ];
		$sql   = $this->applyPropertyScope( $sql, $args );
		$sql  .= ' ORDER BY created_at DESC';

		$rows = $this->db->getResults( $sql, ...$args );

		return WorkOrder::fromRows( $rows );
	}

	/**
	 * Get work orders assigned to a specific user.
	 *
	 * @return WorkOrder[]
	 */
	public function getByAssignee( int $userId ): array {
		$table = $this->tableName();
		$sql   = "SELECT * FROM {$table} WHERE assigned_to = %d";
		$args  = [ $userId ];
		$sql   = $this->applyPropertyScope( $sql, $args );
		$sql  .= ' ORDER BY FIELD(priority, \'urgent\', \'high\', \'normal\', \'low\'), created_at DESC';

		$rows = $this->db->getResults( $sql, ...$args );

		return WorkOrder::fromRows( $rows );
	}

	/**
	 * Get all open and in-progress work orders with room info.
	 *
	 * @return WorkOrder[]
	 */
	public function getActiveWithRoomInfo(): array {
		$table       = $this->tableName();
		$rooms_table = $this->db->table( 'rooms' );
		$types_table = $this->db->table( 'room_types' );

		$sql  = "SELECT wo.*, r.room_number, r.floor, rt.name AS room_type_name
			FROM {$table} wo
			LEFT JOIN {$rooms_table} r ON wo.room_id = r.id
			LEFT JOIN {$types_table} rt ON r.room_type_id = rt.id
			WHERE wo.status IN ('open', 'in_progress')";
		$args = [];
		$sql  = $this->applyPropertyScope( $sql, $args, 'wo.property_id' );
		$sql .= ' ORDER BY FIELD(wo.priority, \'urgent\', \'high\', \'normal\', \'low\'), wo.created_at DESC';

		$rows = $this->db->getResults( $sql, ...$args );

		return WorkOrder::fromRows( $rows );
	}

	/**
	 * Get all work orders with room info (paginated).
	 *
	 * @return WorkOrder[]
	 */
	public function getAllWithRoomInfo( ?string $status = null, ?int $roomId = null, ?int $assigneeId = null, int $limit = 50, int $offset = 0 ): array {
		$table       = $this->tableName();
		$rooms_table = $this->db->table( 'rooms' );
		$types_table = $this->db->table( 'room_types' );

		$sql  = "SELECT wo.*, r.room_number, r.floor, rt.name AS room_type_name
			FROM {$table} wo
			LEFT JOIN {$rooms_table} r ON wo.room_id = r.id
			LEFT JOIN {$types_table} rt ON r.room_type_id = rt.id
			WHERE 1=1";
		$args = [];

		if ( $status ) {
			$sql   .= ' AND wo.status = %s';
			$args[] = $status;
		}
		if ( $roomId ) {
			$sql   .= ' AND wo.room_id = %d';
			$args[] = $roomId;
		}
		if ( $assigneeId ) {
			$sql   .= ' AND wo.assigned_to = %d';
			$args[] = $assigneeId;
		}

		$sql  = $this->applyPropertyScope( $sql, $args, 'wo.property_id' );
		$sql .= ' ORDER BY FIELD(wo.priority, \'urgent\', \'high\', \'normal\', \'low\'), wo.created_at DESC';
		$sql .= ' LIMIT %d OFFSET %d';
		$args[] = $limit;
		$args[] = $offset;

		$rows = $this->db->getResults( $sql, ...$args );

		return WorkOrder::fromRows( $rows );
	}

	/**
	 * Count work orders with optional filters.
	 */
	public function countFiltered( ?string $status = null, ?int $roomId = null, ?int $assigneeId = null ): int {
		$table = $this->tableName();
		$sql   = "SELECT COUNT(*) FROM {$table} WHERE 1=1";
		$args  = [];

		if ( $status ) {
			$sql   .= ' AND status = %s';
			$args[] = $status;
		}
		if ( $roomId ) {
			$sql   .= ' AND room_id = %d';
			$args[] = $roomId;
		}
		if ( $assigneeId ) {
			$sql   .= ' AND assigned_to = %d';
			$args[] = $assigneeId;
		}

		$sql = $this->applyPropertyScope( $sql, $args );

		return (int) $this->db->getVar( $sql, ...$args );
	}

	/**
	 * Get counts grouped by status.
	 *
	 * @return array<string, int>
	 */
	public function countByStatus(): array {
		$table   = $this->tableName();
		$results = $this->db->getResults(
			"SELECT status, COUNT(*) AS total FROM {$table} GROUP BY status"
		);

		$counts = [];
		foreach ( $results as $row ) {
			$counts[ $row->status ] = (int) $row->total;
		}

		foreach ( WorkOrder::validStatuses() as $status ) {
			if ( ! isset( $counts[ $status ] ) ) {
				$counts[ $status ] = 0;
			}
		}

		return $counts;
	}

	/**
	 * Get open work orders for a specific room.
	 */
	public function getOpenOrderForRoom( int $roomId ): ?WorkOrder {
		$table = $this->tableName();
		$row   = $this->db->getRow(
			"SELECT * FROM {$table}
			WHERE room_id = %d AND status IN ('open', 'in_progress')
			ORDER BY created_at DESC LIMIT 1",
			$roomId
		);

		return $row ? WorkOrder::fromRow( $row ) : null;
	}
}
