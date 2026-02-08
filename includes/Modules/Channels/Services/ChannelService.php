<?php

namespace Venezia\Modules\Channels\Services;

use Venezia\Core\Database;
use Venezia\Core\EventDispatcher;
use Venezia\Core\Logger;
use Venezia\Modules\Channels\Models\ChannelMapping;
use Venezia\Modules\Channels\Models\SyncResult;
use Venezia\Modules\Channels\Repositories\ChannelMappingRepository;

/**
 * Channel service orchestrator.
 *
 * Coordinates availability/rate pushes and reservation pulls across
 * all configured OTA channels. Delegates API communication to the
 * individual connector implementations.
 */
class ChannelService {

    private ChannelMappingRepository $repository;
    private Database $db;
    private EventDispatcher $events;
    private Logger $logger;

    /**
     * Registry of channel name => connector class.
     *
     * @var array<string, class-string<AbstractChannelConnector>>
     */
    private array $connectorRegistry = [];

    public function __construct(
        ChannelMappingRepository $repository,
        Database $db,
        EventDispatcher $events,
        Logger $logger
    ) {
        $this->repository = $repository;
        $this->db         = $db;
        $this->events     = $events;
        $this->logger     = $logger;

        $this->registerDefaultConnectors();
    }

    /**
     * Push availability updates to one or all channels.
     *
     * When $channelId is null, availability is pushed to every active
     * mapping that has sync_availability enabled.
     *
     * @param int|null $channelId Optional mapping ID to limit sync.
     * @return SyncResult[]       Keyed by mapping ID.
     */
    public function syncAvailability( ?int $channelId = null ): array {
        $mappings = $this->resolveMappings( $channelId );
        $results  = [];

        // Build the inventory payload for a rolling 365-day window.
        $startDate = current_time( 'Y-m-d' );
        $endDate   = wp_date( 'Y-m-d', strtotime( '+365 days' ) );

        $inventoryTable = $this->db->table( 'room_inventory' );

        foreach ( $mappings as $mapping ) {
            if ( ! $mapping->shouldSyncAvailability() ) {
                continue;
            }

            try {
                $connector = $this->getConnector( $mapping );

                // Fetch inventory for this mapping's room type.
                $rows = $this->db->getResults(
                    "SELECT * FROM {$inventoryTable}
                     WHERE room_type_id = %d
                       AND date >= %s
                       AND date <= %s
                     ORDER BY date ASC",
                    $mapping->room_type_id,
                    $startDate,
                    $endDate
                );

                $inventory = array_map( fn( $row ) => (array) $row, $rows );

                $result = $connector->pushAvailability( $inventory );
                $results[ $mapping->id ] = $result;

                // Update sync status on the mapping.
                $syncStatus = $result->isSuccess() ? 'success' : 'failed';
                $errorMsg   = $result->hasErrors()
                    ? implode( '; ', $result->getErrors() )
                    : '';

                $this->repository->updateSyncStatus( $mapping->id, $syncStatus, $errorMsg );

                $this->logger->info( 'Channel availability sync completed.', [
                    'mapping_id'   => $mapping->id,
                    'channel'      => $mapping->channel_name,
                    'success'      => $result->isSuccess(),
                    'items_synced' => $result->getItemsSynced(),
                ] );

                $this->events->dispatch( 'channels/availability_synced', $mapping, $result );
            } catch ( \Throwable $e ) {
                $failResult = SyncResult::failure( $e->getMessage(), [ $e->getMessage() ] );
                $results[ $mapping->id ] = $failResult;

                $this->repository->updateSyncStatus( $mapping->id, 'failed', $e->getMessage() );

                $this->logger->error( 'Channel availability sync failed.', [
                    'mapping_id' => $mapping->id,
                    'channel'    => $mapping->channel_name,
                    'error'      => $e->getMessage(),
                ] );
            }
        }

        return $results;
    }

    /**
     * Push rate updates to one or all channels.
     *
     * @param int|null $channelId Optional mapping ID to limit sync.
     * @return SyncResult[]       Keyed by mapping ID.
     */
    public function syncRates( ?int $channelId = null ): array {
        $mappings = $this->resolveMappings( $channelId );
        $results  = [];

        $startDate = current_time( 'Y-m-d' );
        $endDate   = wp_date( 'Y-m-d', strtotime( '+365 days' ) );

        $inventoryTable = $this->db->table( 'room_inventory' );
        $roomTypeTable  = $this->db->table( 'room_types' );

        foreach ( $mappings as $mapping ) {
            if ( ! $mapping->shouldSyncRates() ) {
                continue;
            }

            try {
                $connector = $this->getConnector( $mapping );

                // Fetch rate data: inventory overrides + base room type price.
                $rates = $this->db->getResults(
                    "SELECT i.date, i.price_override, i.min_stay, i.stop_sell,
                            rt.base_price, rt.name AS room_type_name
                     FROM {$inventoryTable} i
                     JOIN {$roomTypeTable} rt ON rt.id = i.room_type_id
                     WHERE i.room_type_id = %d
                       AND i.date >= %s
                       AND i.date <= %s
                     ORDER BY i.date ASC",
                    $mapping->room_type_id,
                    $startDate,
                    $endDate
                );

                $rateData = array_map( fn( $row ) => (array) $row, $rates );

                $result = $connector->pushRates( $rateData );
                $results[ $mapping->id ] = $result;

                $syncStatus = $result->isSuccess() ? 'success' : 'failed';
                $errorMsg   = $result->hasErrors()
                    ? implode( '; ', $result->getErrors() )
                    : '';

                $this->repository->updateSyncStatus( $mapping->id, $syncStatus, $errorMsg );

                $this->logger->info( 'Channel rate sync completed.', [
                    'mapping_id'   => $mapping->id,
                    'channel'      => $mapping->channel_name,
                    'success'      => $result->isSuccess(),
                    'items_synced' => $result->getItemsSynced(),
                ] );

                $this->events->dispatch( 'channels/rates_synced', $mapping, $result );
            } catch ( \Throwable $e ) {
                $failResult = SyncResult::failure( $e->getMessage(), [ $e->getMessage() ] );
                $results[ $mapping->id ] = $failResult;

                $this->repository->updateSyncStatus( $mapping->id, 'failed', $e->getMessage() );

                $this->logger->error( 'Channel rate sync failed.', [
                    'mapping_id' => $mapping->id,
                    'channel'    => $mapping->channel_name,
                    'error'      => $e->getMessage(),
                ] );
            }
        }

        return $results;
    }

    /**
     * Pull reservations from one or all channels.
     *
     * @param int|null $channelId Optional mapping ID to limit the pull.
     * @return array<int, array>  Keyed by mapping ID, each value is an array of reservations.
     */
    public function pullReservations( ?int $channelId = null ): array {
        $mappings = $this->resolveMappings( $channelId );
        $results  = [];

        foreach ( $mappings as $mapping ) {
            if ( ! $mapping->shouldSyncReservations() ) {
                continue;
            }

            try {
                $connector    = $this->getConnector( $mapping );
                $reservations = $connector->pullReservations();

                $results[ $mapping->id ] = $reservations;

                $this->repository->updateSyncStatus( $mapping->id, 'success' );

                $this->logger->info( 'Channel reservation pull completed.', [
                    'mapping_id'   => $mapping->id,
                    'channel'      => $mapping->channel_name,
                    'reservations' => count( $reservations ),
                ] );

                $this->events->dispatch( 'channels/reservations_pulled', $mapping, $reservations );
            } catch ( \Throwable $e ) {
                $results[ $mapping->id ] = [];

                $this->repository->updateSyncStatus( $mapping->id, 'failed', $e->getMessage() );

                $this->logger->error( 'Channel reservation pull failed.', [
                    'mapping_id' => $mapping->id,
                    'channel'    => $mapping->channel_name,
                    'error'      => $e->getMessage(),
                ] );
            }
        }

        return $results;
    }

    /**
     * Get the connector instance for a mapping or channel name.
     *
     * @param ChannelMapping|string $channel A mapping object or channel name string.
     * @throws \RuntimeException If the channel has no registered connector.
     */
    public function getConnector( ChannelMapping|string $channel ): AbstractChannelConnector {
        if ( $channel instanceof ChannelMapping ) {
            $channelName = $channel->channel_name;
            $config      = is_array( $channel->config ) ? $channel->config : [];
        } else {
            $channelName = $channel;
            $config      = [];
        }

        if ( ! isset( $this->connectorRegistry[ $channelName ] ) ) {
            throw new \RuntimeException(
                sprintf(
                    __( 'No connector registered for channel "%s".', 'venezia-hotel' ),
                    $channelName
                )
            );
        }

        $class = $this->connectorRegistry[ $channelName ];

        return new $class( $config );
    }

    /**
     * Test the connection for a specific channel mapping.
     *
     * @param int $channelId Mapping ID.
     * @return bool True if the connection test succeeded.
     * @throws \RuntimeException If the mapping is not found.
     */
    public function testConnection( int $channelId ): bool {
        $mapping = $this->repository->find( $channelId );

        if ( ! $mapping ) {
            throw new \RuntimeException(
                sprintf( __( 'Channel mapping with ID %d not found.', 'venezia-hotel' ), $channelId )
            );
        }

        try {
            $connector = $this->getConnector( $mapping );
            $result    = $connector->testConnection();

            if ( $result ) {
                $this->repository->update( $channelId, [ 'status' => 'active' ] );
                $this->logger->info( 'Channel connection test succeeded.', [
                    'mapping_id' => $channelId,
                    'channel'    => $mapping->channel_name,
                ] );
            } else {
                $this->repository->update( $channelId, [ 'status' => 'error' ] );
                $this->logger->warning( 'Channel connection test failed.', [
                    'mapping_id' => $channelId,
                    'channel'    => $mapping->channel_name,
                ] );
            }

            $this->events->dispatch( 'channels/connection_tested', $mapping, $result );

            return $result;
        } catch ( \Throwable $e ) {
            $this->repository->updateSyncStatus( $channelId, 'failed', $e->getMessage() );

            $this->logger->error( 'Channel connection test threw exception.', [
                'mapping_id' => $channelId,
                'channel'    => $mapping->channel_name,
                'error'      => $e->getMessage(),
            ] );

            return false;
        }
    }

    /**
     * Get recent sync history for a channel mapping.
     *
     * Queries the booking_logs table for channel-related log entries.
     *
     * @param int $channelId Mapping ID.
     * @param int $limit     Maximum number of entries to return.
     * @return array Log entries sorted by most recent first.
     */
    public function getSyncHistory( int $channelId, int $limit = 50 ): array {
        $mapping = $this->repository->find( $channelId );

        if ( ! $mapping ) {
            return [];
        }

        // Build a lightweight history from the mapping's own timestamps
        // and the sync status fields. For a full audit trail, check logs.
        $history = [
            'mapping_id'       => $mapping->id,
            'channel_name'     => $mapping->channel_name,
            'status'           => $mapping->status,
            'last_sync_at'     => $mapping->last_sync_at,
            'last_sync_status' => $mapping->last_sync_status,
            'last_error'       => $mapping->last_error,
        ];

        /**
         * Filter sync history to allow other modules to append
         * detailed log records.
         *
         * @param array          $history   Current history data.
         * @param ChannelMapping $mapping   The channel mapping.
         * @param int            $limit     Requested record limit.
         */
        return $this->events->filter( 'channels/sync_history', $history, $mapping, $limit );
    }

    /**
     * Register a connector class for a channel name.
     *
     * @param string                                   $channelName Channel identifier.
     * @param class-string<AbstractChannelConnector>   $class       Fully qualified class name.
     */
    public function registerConnector( string $channelName, string $class ): void {
        $this->connectorRegistry[ $channelName ] = $class;
    }

    /**
     * Get all registered connector classes.
     *
     * @return array<string, class-string<AbstractChannelConnector>>
     */
    public function getRegisteredConnectors(): array {
        return $this->connectorRegistry;
    }

    /**
     * Get available channels with their configuration fields.
     *
     * @return array<string, array{ name: string, label: string, fields: array }>
     */
    public function getAvailableChannels(): array {
        $channels = [];

        foreach ( $this->connectorRegistry as $channelName => $class ) {
            $connector = new $class( [] );
            $channels[ $channelName ] = [
                'name'   => $connector->getChannelName(),
                'label'  => $connector->getChannelLabel(),
                'fields' => $connector->getConfigFields(),
            ];
        }

        return $channels;
    }

    /**
     * Register the default built-in connectors.
     */
    private function registerDefaultConnectors(): void {
        $this->connectorRegistry['booking_com'] = BookingComConnector::class;
        $this->connectorRegistry['expedia']     = ExpediaConnector::class;

        /**
         * Allow third-party plugins to register additional connectors.
         *
         * @param array<string, class-string<AbstractChannelConnector>> $registry
         */
        $this->connectorRegistry = apply_filters(
            'venezia/channels/connector_registry',
            $this->connectorRegistry
        );
    }

    /**
     * Resolve the list of mappings to operate on.
     *
     * @param int|null $channelId If set, return only this mapping; otherwise all active.
     * @return ChannelMapping[]
     */
    private function resolveMappings( ?int $channelId ): array {
        if ( $channelId !== null ) {
            $mapping = $this->repository->find( $channelId );

            if ( ! $mapping || ! $mapping->isActive() ) {
                return [];
            }

            return [ $mapping ];
        }

        return $this->repository->getActiveMappings();
    }
}
