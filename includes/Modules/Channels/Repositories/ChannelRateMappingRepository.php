<?php

namespace Nozule\Modules\Channels\Repositories;

use Nozule\Core\BaseRepository;
use Nozule\Core\Database;
use Nozule\Modules\Channels\Models\ChannelRateMap;

/**
 * Repository for channel rate mapping CRUD and querying.
 */
class ChannelRateMappingRepository extends BaseRepository {

	protected string $table = 'channel_rate_map';
	protected string $model = ChannelRateMap::class;

	public function __construct( Database $db ) {
		parent::__construct( $db );
	}

	/**
	 * Find a rate mapping by ID.
	 */
	public function find( int $id ): ?ChannelRateMap {
		$table = $this->tableName();
		$row   = $this->db->getRow(
			"SELECT * FROM {$table} WHERE id = %d",
			$id
		);

		return $row ? ChannelRateMap::fromRow( $row ) : null;
	}

	/**
	 * Get all rate mappings for a specific channel.
	 *
	 * @return ChannelRateMap[]
	 */
	public function getByChannel( string $channelName ): array {
		$table = $this->tableName();
		$rows  = $this->db->getResults(
			"SELECT * FROM {$table}
			 WHERE channel_name = %s
			 ORDER BY local_room_type_id ASC, local_rate_plan_id ASC",
			$channelName
		);

		return ChannelRateMap::fromRows( $rows );
	}

	/**
	 * Get active rate mappings for a channel.
	 *
	 * @return ChannelRateMap[]
	 */
	public function getActiveByChannel( string $channelName ): array {
		$table = $this->tableName();
		$rows  = $this->db->getResults(
			"SELECT * FROM {$table}
			 WHERE channel_name = %s AND is_active = 1
			 ORDER BY local_room_type_id ASC, local_rate_plan_id ASC",
			$channelName
		);

		return ChannelRateMap::fromRows( $rows );
	}

	/**
	 * Get the mapping for a specific room type on a channel.
	 *
	 * @return ChannelRateMap[]
	 */
	public function getMappingForRoomType( string $channelName, int $roomTypeId ): array {
		$table = $this->tableName();
		$rows  = $this->db->getResults(
			"SELECT * FROM {$table}
			 WHERE channel_name = %s AND local_room_type_id = %d
			 ORDER BY local_rate_plan_id ASC",
			$channelName,
			$roomTypeId
		);

		return ChannelRateMap::fromRows( $rows );
	}

	/**
	 * Create a new rate mapping.
	 *
	 * @return ChannelRateMap|false
	 */
	public function create( array $data ): ChannelRateMap|false {
		$now = current_time( 'mysql', true );

		$insert = [
			'channel_name'       => $data['channel_name'] ?? '',
			'local_room_type_id' => (int) ( $data['local_room_type_id'] ?? 0 ),
			'local_rate_plan_id' => (int) ( $data['local_rate_plan_id'] ?? 0 ),
			'channel_room_id'    => $data['channel_room_id'] ?? '',
			'channel_rate_id'    => $data['channel_rate_id'] ?? '',
			'is_active'          => isset( $data['is_active'] ) ? (int) (bool) $data['is_active'] : 1,
			'created_at'         => $now,
			'updated_at'         => $now,
		];

		$id = $this->db->insert( $this->table, $insert );

		if ( $id === false ) {
			return false;
		}

		return $this->find( $id );
	}

	/**
	 * Update a rate mapping.
	 */
	public function update( int $id, array $data ): bool {
		$update = [];

		if ( isset( $data['channel_room_id'] ) ) {
			$update['channel_room_id'] = sanitize_text_field( $data['channel_room_id'] );
		}

		if ( isset( $data['channel_rate_id'] ) ) {
			$update['channel_rate_id'] = sanitize_text_field( $data['channel_rate_id'] );
		}

		if ( isset( $data['is_active'] ) ) {
			$update['is_active'] = (int) (bool) $data['is_active'];
		}

		if ( isset( $data['local_room_type_id'] ) ) {
			$update['local_room_type_id'] = (int) $data['local_room_type_id'];
		}

		if ( isset( $data['local_rate_plan_id'] ) ) {
			$update['local_rate_plan_id'] = (int) $data['local_rate_plan_id'];
		}

		if ( isset( $data['channel_name'] ) ) {
			$update['channel_name'] = sanitize_text_field( $data['channel_name'] );
		}

		if ( empty( $update ) ) {
			return true;
		}

		$update['updated_at'] = current_time( 'mysql', true );

		return $this->db->update( $this->table, $update, [ 'id' => $id ] ) !== false;
	}

	/**
	 * Delete a rate mapping.
	 */
	public function delete( int $id ): bool {
		return $this->db->delete( $this->table, [ 'id' => $id ] ) !== false;
	}

	/**
	 * Delete all mappings for a channel.
	 */
	public function deleteByChannel( string $channelName ): bool {
		$table = $this->tableName();
		$result = $this->db->query(
			"DELETE FROM {$table} WHERE channel_name = %s",
			$channelName
		);

		return $result !== false;
	}
}
