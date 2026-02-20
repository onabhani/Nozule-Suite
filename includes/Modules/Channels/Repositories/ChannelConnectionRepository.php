<?php

namespace Nozule\Modules\Channels\Repositories;

use Nozule\Core\BaseRepository;
use Nozule\Core\Database;
use Nozule\Modules\Channels\Models\ChannelConnection;

/**
 * Repository for channel connection CRUD and querying.
 */
class ChannelConnectionRepository extends BaseRepository {

	protected string $table = 'channel_connections';
	protected string $model = ChannelConnection::class;

	public function __construct( Database $db ) {
		parent::__construct( $db );
	}

	/**
	 * Find a channel connection by ID.
	 */
	public function find( int $id ): ?ChannelConnection {
		$table = $this->tableName();
		$row   = $this->db->getRow(
			"SELECT * FROM {$table} WHERE id = %d",
			$id
		);

		return $row ? ChannelConnection::fromRow( $row ) : null;
	}

	/**
	 * Get a connection by channel name.
	 */
	public function getByChannelName( string $channelName ): ?ChannelConnection {
		$table = $this->tableName();
		$row   = $this->db->getRow(
			"SELECT * FROM {$table} WHERE channel_name = %s",
			$channelName
		);

		return $row ? ChannelConnection::fromRow( $row ) : null;
	}

	/**
	 * Get all active connections.
	 *
	 * @return ChannelConnection[]
	 */
	public function getActive(): array {
		$table = $this->tableName();
		$rows  = $this->db->getResults(
			"SELECT * FROM {$table} WHERE is_active = 1 ORDER BY channel_name ASC"
		);

		return ChannelConnection::fromRows( $rows );
	}

	/**
	 * Get all connections.
	 *
	 * @return ChannelConnection[]
	 */
	public function getAll(): array {
		$table = $this->tableName();
		$rows  = $this->db->getResults(
			"SELECT * FROM {$table} ORDER BY channel_name ASC"
		);

		return ChannelConnection::fromRows( $rows );
	}

	/**
	 * Create a new channel connection.
	 *
	 * @return ChannelConnection|false
	 */
	public function create( array $data ): ChannelConnection|false {
		$now                = current_time( 'mysql', true );
		$data['created_at'] = $data['created_at'] ?? $now;
		$data['updated_at'] = $data['updated_at'] ?? $now;

		// Default active state.
		if ( ! isset( $data['is_active'] ) ) {
			$data['is_active'] = 0;
		} else {
			$data['is_active'] = (int) (bool) $data['is_active'];
		}

		$id = $this->db->insert( $this->table, $data );

		if ( $id === false ) {
			return false;
		}

		return $this->find( $id );
	}

	/**
	 * Update a channel connection.
	 */
	public function update( int $id, array $data ): bool {
		if ( isset( $data['is_active'] ) ) {
			$data['is_active'] = (int) (bool) $data['is_active'];
		}

		$data['updated_at'] = current_time( 'mysql', true );

		return $this->db->update( $this->table, $data, [ 'id' => $id ] ) !== false;
	}

	/**
	 * Delete a channel connection.
	 */
	public function delete( int $id ): bool {
		return $this->db->delete( $this->table, [ 'id' => $id ] ) !== false;
	}

	/**
	 * Update the last_sync_at timestamp.
	 */
	public function updateLastSync( int $id ): bool {
		$table = $this->tableName();
		$now   = current_time( 'mysql', true );

		$result = $this->db->query(
			"UPDATE {$table} SET last_sync_at = %s, updated_at = %s WHERE id = %d",
			$now,
			$now,
			$id
		);

		return $result !== false;
	}
}
