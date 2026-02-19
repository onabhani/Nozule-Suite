<?php

namespace Nozule\Modules\Housekeeping\Repositories;

use Nozule\Core\BaseRepository;
use Nozule\Core\Database;
use Nozule\Modules\Housekeeping\Models\HousekeepingTask;

/**
 * Repository for housekeeping task CRUD and querying.
 */
class HousekeepingRepository extends BaseRepository {

	protected string $table = 'housekeeping_tasks';
	protected string $model = HousekeepingTask::class;

	public function __construct( Database $db ) {
		parent::__construct( $db );
	}

	/**
	 * Create a new housekeeping task.
	 *
	 * @return HousekeepingTask|false
	 */
	public function create( array $data ): HousekeepingTask|false {
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
	 * Update a housekeeping task.
	 */
	public function update( int $id, array $data ): bool {
		$data['updated_at'] = current_time( 'mysql', true );

		return $this->db->update( $this->table, $data, [ 'id' => $id ] ) !== false;
	}

	/**
	 * Get all tasks for a specific room.
	 *
	 * @return HousekeepingTask[]
	 */
	public function getByRoom( int $roomId ): array {
		$table = $this->tableName();
		$rows  = $this->db->getResults(
			"SELECT * FROM {$table} WHERE room_id = %d ORDER BY created_at DESC",
			$roomId
		);

		return HousekeepingTask::fromRows( $rows );
	}

	/**
	 * Get all tasks filtered by status.
	 *
	 * @return HousekeepingTask[]
	 */
	public function getByStatus( string $status ): array {
		$table = $this->tableName();
		$rows  = $this->db->getResults(
			"SELECT * FROM {$table} WHERE status = %s ORDER BY created_at DESC",
			$status
		);

		return HousekeepingTask::fromRows( $rows );
	}

	/**
	 * Get all tasks assigned to a specific user.
	 *
	 * @return HousekeepingTask[]
	 */
	public function getByAssignee( int $userId ): array {
		$table = $this->tableName();
		$rows  = $this->db->getResults(
			"SELECT * FROM {$table} WHERE assigned_to = %d ORDER BY created_at DESC",
			$userId
		);

		return HousekeepingTask::fromRows( $rows );
	}

	/**
	 * Get today's tasks: created today or still open (dirty/clean).
	 *
	 * @return HousekeepingTask[]
	 */
	public function getTodaysTasks(): array {
		$table = $this->tableName();
		$today = current_time( 'Y-m-d' );
		$rows  = $this->db->getResults(
			"SELECT * FROM {$table}
			WHERE DATE(created_at) = %s
			   OR status IN ('dirty', 'clean')
			ORDER BY
				FIELD(priority, 'urgent', 'high', 'normal', 'low'),
				created_at DESC",
			$today
		);

		return HousekeepingTask::fromRows( $rows );
	}

	/**
	 * Get the latest open (dirty or clean) task for a room.
	 */
	public function getActiveTaskForRoom( int $roomId ): ?HousekeepingTask {
		$table = $this->tableName();
		$row   = $this->db->getRow(
			"SELECT * FROM {$table}
			WHERE room_id = %d
			  AND status IN ('dirty', 'clean')
			ORDER BY created_at DESC
			LIMIT 1",
			$roomId
		);

		return $row ? HousekeepingTask::fromRow( $row ) : null;
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

		// Ensure all statuses are represented.
		foreach ( HousekeepingTask::validStatuses() as $status ) {
			if ( ! isset( $counts[ $status ] ) ) {
				$counts[ $status ] = 0;
			}
		}

		return $counts;
	}

	/**
	 * Get all tasks with joined room and room type information.
	 *
	 * @return HousekeepingTask[]
	 */
	public function getAllWithRoomInfo(): array {
		$tasks_table = $this->tableName();
		$rooms_table = $this->db->table( 'rooms' );
		$types_table = $this->db->table( 'room_types' );

		$rows = $this->db->getResults(
			"SELECT t.*, r.room_number, r.floor, r.status AS room_status, rt.name AS room_type_name
			FROM {$tasks_table} t
			LEFT JOIN {$rooms_table} r ON t.room_id = r.id
			LEFT JOIN {$types_table} rt ON r.room_type_id = rt.id
			ORDER BY
				FIELD(t.priority, 'urgent', 'high', 'normal', 'low'),
				t.created_at DESC"
		);

		return HousekeepingTask::fromRows( $rows );
	}
}
