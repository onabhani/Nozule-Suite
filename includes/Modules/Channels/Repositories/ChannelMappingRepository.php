<?php

namespace Venezia\Modules\Channels\Repositories;

use Venezia\Core\BaseRepository;
use Venezia\Core\Database;
use Venezia\Modules\Channels\Models\ChannelMapping;

/**
 * Repository for channel mapping CRUD and querying.
 */
class ChannelMappingRepository extends BaseRepository {

    protected string $table = 'channel_mappings';
    protected string $model = ChannelMapping::class;

    public function __construct( Database $db ) {
        parent::__construct( $db );
    }

    /**
     * Find a channel mapping by ID.
     */
    public function find( int $id ): ?ChannelMapping {
        $table = $this->tableName();
        $row   = $this->db->getRow(
            "SELECT * FROM {$table} WHERE id = %d",
            $id
        );

        return $row ? ChannelMapping::fromRow( $row ) : null;
    }

    /**
     * Get all mappings for a specific channel.
     *
     * @return ChannelMapping[]
     */
    public function getByChannel( string $channelName ): array {
        $table = $this->tableName();
        $rows  = $this->db->getResults(
            "SELECT * FROM {$table} WHERE channel_name = %s ORDER BY room_type_id ASC",
            $channelName
        );

        return ChannelMapping::fromRows( $rows );
    }

    /**
     * Get all mappings for a specific room type.
     *
     * @return ChannelMapping[]
     */
    public function getByRoomType( int $roomTypeId ): array {
        $table = $this->tableName();
        $rows  = $this->db->getResults(
            "SELECT * FROM {$table} WHERE room_type_id = %d ORDER BY channel_name ASC",
            $roomTypeId
        );

        return ChannelMapping::fromRows( $rows );
    }

    /**
     * Get all active mappings, optionally filtered by channel.
     *
     * @return ChannelMapping[]
     */
    public function getActiveMappings( ?string $channelName = null ): array {
        $table = $this->tableName();

        if ( $channelName ) {
            $rows = $this->db->getResults(
                "SELECT * FROM {$table} WHERE status = 'active' AND channel_name = %s ORDER BY room_type_id ASC",
                $channelName
            );
        } else {
            $rows = $this->db->getResults(
                "SELECT * FROM {$table} WHERE status = 'active' ORDER BY channel_name ASC, room_type_id ASC"
            );
        }

        return ChannelMapping::fromRows( $rows );
    }

    /**
     * Create a new channel mapping.
     *
     * @return ChannelMapping|false
     */
    public function create( array $data ): ChannelMapping|false {
        $data = $this->prepareJsonFields( $data );

        $now                = current_time( 'mysql', true );
        $data['created_at'] = $data['created_at'] ?? $now;
        $data['updated_at'] = $data['updated_at'] ?? $now;

        // Set defaults for boolean fields.
        $data['sync_availability'] = isset( $data['sync_availability'] ) ? (int) (bool) $data['sync_availability'] : 1;
        $data['sync_rates']        = isset( $data['sync_rates'] ) ? (int) (bool) $data['sync_rates'] : 1;
        $data['sync_reservations'] = isset( $data['sync_reservations'] ) ? (int) (bool) $data['sync_reservations'] : 1;

        // Default status.
        $data['status'] = $data['status'] ?? 'inactive';

        $id = $this->db->insert( $this->table, $data );

        if ( $id === false ) {
            return false;
        }

        return $this->find( $id );
    }

    /**
     * Update a channel mapping.
     */
    public function update( int $id, array $data ): bool {
        $data = $this->prepareJsonFields( $data );

        // Cast boolean fields for storage.
        foreach ( [ 'sync_availability', 'sync_rates', 'sync_reservations' ] as $field ) {
            if ( isset( $data[ $field ] ) ) {
                $data[ $field ] = (int) (bool) $data[ $field ];
            }
        }

        $data['updated_at'] = current_time( 'mysql', true );

        return $this->db->update( $this->table, $data, [ 'id' => $id ] ) !== false;
    }

    /**
     * Delete a channel mapping.
     */
    public function delete( int $id ): bool {
        return $this->db->delete( $this->table, [ 'id' => $id ] ) !== false;
    }

    /**
     * Update the sync status after a sync operation.
     *
     * @param int    $id         Mapping ID.
     * @param string $status     Sync result: 'success', 'partial', 'failed'.
     * @param string $error      Error message (empty on success).
     */
    public function updateSyncStatus( int $id, string $status, string $error = '' ): bool {
        $table = $this->tableName();
        $now   = current_time( 'mysql', true );

        $mappingStatus = ( $status === 'failed' ) ? 'error' : 'active';

        $result = $this->db->query(
            "UPDATE {$table}
             SET last_sync_at     = %s,
                 last_sync_status = %s,
                 last_error       = %s,
                 status           = %s,
                 updated_at       = %s
             WHERE id = %d",
            $now,
            $status,
            $error,
            $mappingStatus,
            $now,
            $id
        );

        return $result !== false;
    }

    /**
     * Get distinct channel names that have at least one mapping.
     *
     * @return string[]
     */
    public function getDistinctChannels(): array {
        $table = $this->tableName();
        $rows  = $this->db->getResults(
            "SELECT DISTINCT channel_name FROM {$table} ORDER BY channel_name ASC"
        );

        return array_map( fn( $row ) => $row->channel_name, $rows );
    }

    /**
     * List mappings with pagination.
     *
     * @param array $args {
     *     Optional arguments.
     *
     *     @type string $channel   Filter by channel name.
     *     @type string $status    Filter by status.
     *     @type string $orderby   Column to order by. Default 'created_at'.
     *     @type string $order     Sort direction. Default 'DESC'.
     *     @type int    $per_page  Results per page. Default 20.
     *     @type int    $page      Page number (1-based). Default 1.
     * }
     * @return array{ mappings: ChannelMapping[], total: int, pages: int }
     */
    public function list( array $args = [] ): array {
        $defaults = [
            'channel'  => '',
            'status'   => '',
            'orderby'  => 'created_at',
            'order'    => 'DESC',
            'per_page' => 20,
            'page'     => 1,
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

        if ( ! empty( $args['status'] ) ) {
            $conditions[] = 'status = %s';
            $params[]     = $args['status'];
        }

        $where = '';
        if ( ! empty( $conditions ) ) {
            $where = 'WHERE ' . implode( ' AND ', $conditions );
        }

        // Sanitize ordering.
        $allowed_columns = [
            'id', 'channel_name', 'room_type_id', 'status',
            'last_sync_at', 'created_at', 'updated_at',
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
            'mappings' => ChannelMapping::fromRows( $rows ),
            'total'    => $total,
            'pages'    => (int) ceil( $total / max( 1, $args['per_page'] ) ),
        ];
    }

    /**
     * Encode array fields to JSON strings for database storage.
     */
    private function prepareJsonFields( array $data ): array {
        if ( isset( $data['config'] ) && is_array( $data['config'] ) ) {
            $data['config'] = wp_json_encode( $data['config'] );
        }

        return $data;
    }
}
