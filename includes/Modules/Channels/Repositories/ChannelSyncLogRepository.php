<?php

namespace Nozule\Modules\Channels\Repositories;

use Nozule\Core\BaseRepository;
use Nozule\Core\Database;
use Nozule\Modules\Channels\Models\ChannelSyncLog;

/**
 * Repository for channel sync log entries.
 */
class ChannelSyncLogRepository extends BaseRepository {

	protected string $table = 'channel_sync_log';
	protected string $model = ChannelSyncLog::class;

	public function __construct( Database $db ) {
		parent::__construct( $db );
	}

	/**
	 * Find a sync log entry by ID.
	 */
	public function find( int $id ): ?ChannelSyncLog {
		$table = $this->tableName();
		$row   = $this->db->getRow(
			"SELECT * FROM {$table} WHERE id = %d",
			$id
		);

		return $row ? ChannelSyncLog::fromRow( $row ) : null;
	}

	/**
	 * Create a new sync log entry (marks start of sync).
	 *
	 * @return ChannelSyncLog|false
	 */
	public function create( array $data ): ChannelSyncLog|false {
		$now = current_time( 'mysql', true );

		$insert = [
			'channel_name'      => $data['channel_name'] ?? '',
			'direction'         => $data['direction'] ?? ChannelSyncLog::DIRECTION_PUSH,
			'sync_type'         => $data['sync_type'] ?? ChannelSyncLog::TYPE_AVAILABILITY,
			'status'            => $data['status'] ?? ChannelSyncLog::STATUS_PENDING,
			'records_processed' => (int) ( $data['records_processed'] ?? 0 ),
			'error_message'     => $data['error_message'] ?? '',
			'started_at'        => $data['started_at'] ?? $now,
			'completed_at'      => $data['completed_at'] ?? null,
			'created_at'        => $now,
		];

		$id = $this->db->insert( $this->table, $insert );

		if ( $id === false ) {
			return false;
		}

		return $this->find( $id );
	}

	/**
	 * Mark a sync log entry as completed.
	 */
	public function complete( int $id, string $status, int $recordsProcessed = 0, string $errorMessage = '' ): bool {
		$table = $this->tableName();
		$now   = current_time( 'mysql', true );

		$result = $this->db->query(
			"UPDATE {$table}
			 SET status            = %s,
			     records_processed = %d,
			     error_message     = %s,
			     completed_at      = %s
			 WHERE id = %d",
			$status,
			$recordsProcessed,
			$errorMessage,
			$now,
			$id
		);

		return $result !== false;
	}

	/**
	 * Get recent sync log entries for a channel.
	 *
	 * @return ChannelSyncLog[]
	 */
	public function getRecent( ?string $channelName = null, int $limit = 20 ): array {
		$table = $this->tableName();

		if ( $channelName ) {
			$rows = $this->db->getResults(
				"SELECT * FROM {$table}
				 WHERE channel_name = %s
				 ORDER BY created_at DESC
				 LIMIT %d",
				$channelName,
				$limit
			);
		} else {
			$rows = $this->db->getResults(
				"SELECT * FROM {$table}
				 ORDER BY created_at DESC
				 LIMIT %d",
				$limit
			);
		}

		return ChannelSyncLog::fromRows( $rows );
	}

	/**
	 * List sync log entries with pagination and filters.
	 *
	 * @param array $args {
	 *     Optional arguments.
	 *
	 *     @type string $channel    Filter by channel name.
	 *     @type string $direction  Filter by direction (push/pull).
	 *     @type string $status     Filter by status.
	 *     @type string $sync_type  Filter by sync type.
	 *     @type string $orderby    Column to order by. Default 'created_at'.
	 *     @type string $order      Sort direction. Default 'DESC'.
	 *     @type int    $per_page   Results per page. Default 20.
	 *     @type int    $page       Page number (1-based). Default 1.
	 * }
	 * @return array{ items: ChannelSyncLog[], total: int, pages: int }
	 */
	public function list( array $args = [] ): array {
		$defaults = [
			'channel'   => '',
			'direction' => '',
			'status'    => '',
			'sync_type' => '',
			'orderby'   => 'created_at',
			'order'     => 'DESC',
			'per_page'  => 20,
			'page'      => 1,
		];

		$args   = wp_parse_args( $args, $defaults );
		$table  = $this->tableName();
		$offset = ( $args['page'] - 1 ) * $args['per_page'];

		$conditions = [];
		$params     = [];

		if ( ! empty( $args['channel'] ) ) {
			$conditions[] = 'channel_name = %s';
			$params[]     = $args['channel'];
		}

		if ( ! empty( $args['direction'] ) ) {
			$conditions[] = 'direction = %s';
			$params[]     = $args['direction'];
		}

		if ( ! empty( $args['status'] ) ) {
			$conditions[] = 'status = %s';
			$params[]     = $args['status'];
		}

		if ( ! empty( $args['sync_type'] ) ) {
			$conditions[] = 'sync_type = %s';
			$params[]     = $args['sync_type'];
		}

		$where = '';
		if ( ! empty( $conditions ) ) {
			$where = 'WHERE ' . implode( ' AND ', $conditions );
		}

		// Sanitize ordering.
		$allowed_columns = [
			'id', 'channel_name', 'direction', 'sync_type',
			'status', 'records_processed', 'started_at',
			'completed_at', 'created_at',
		];
		$orderby = in_array( $args['orderby'], $allowed_columns, true ) ? $args['orderby'] : 'created_at';
		$order   = strtoupper( $args['order'] ) === 'ASC' ? 'ASC' : 'DESC';

		// Count total.
		$count_params = $params;
		$total        = (int) $this->db->getVar(
			"SELECT COUNT(*) FROM {$table} {$where}",
			...$count_params
		);

		// Fetch results.
		$params[] = $args['per_page'];
		$params[] = $offset;

		$rows = $this->db->getResults(
			"SELECT * FROM {$table} {$where} ORDER BY {$orderby} {$order} LIMIT %d OFFSET %d",
			...$params
		);

		return [
			'items' => ChannelSyncLog::fromRows( $rows ),
			'total' => $total,
			'pages' => (int) ceil( $total / max( 1, $args['per_page'] ) ),
		];
	}

	/**
	 * Delete old sync log entries.
	 *
	 * @param int $days Delete entries older than this many days.
	 * @return int Number of rows deleted.
	 */
	public function deleteOlderThan( int $days = 90 ): int {
		$table    = $this->tableName();
		$cutoff   = wp_date( 'Y-m-d H:i:s', strtotime( "-{$days} days" ) );

		$result = $this->db->query(
			"DELETE FROM {$table} WHERE created_at < %s",
			$cutoff
		);

		return $result !== false ? $result : 0;
	}
}
