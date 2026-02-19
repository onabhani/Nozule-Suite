<?php

namespace Nozule\Modules\Channels\Controllers;

use Nozule\Modules\Channels\Services\ChannelService;
use Nozule\Modules\Channels\Repositories\ChannelMappingRepository;
use Nozule\Modules\Channels\Validators\ChannelMappingValidator;

/**
 * REST API controller for channel management.
 *
 * All endpoints require the 'nzl_admin' capability.
 *
 * Routes registered under the nozule/v1 namespace:
 *   GET    /channels                   - List available channels and mappings
 *   POST   /channels/mappings          - Create a new mapping
 *   GET    /channels/mappings/{id}     - Get a single mapping
 *   PUT    /channels/mappings/{id}     - Update a mapping
 *   DELETE /channels/mappings/{id}     - Delete a mapping
 *   POST   /channels/mappings/{id}/sync        - Trigger sync
 *   POST   /channels/mappings/{id}/test        - Test connection
 *   GET    /channels/mappings/{id}/history     - Get sync history
 */
class ChannelController {

    private ChannelService $service;
    private ChannelMappingRepository $repository;
    private ChannelMappingValidator $validator;

    private const NAMESPACE = 'nozule/v1';
    private const PERMISSION = 'nzl_admin';

    public function __construct(
        ChannelService $service,
        ChannelMappingRepository $repository,
        ChannelMappingValidator $validator
    ) {
        $this->service    = $service;
        $this->repository = $repository;
        $this->validator  = $validator;
    }

    /**
     * Register all REST routes for the channels module.
     */
    public function registerRoutes(): void {
        // List available channels and all mappings.
        register_rest_route( self::NAMESPACE, '/channels', [
            'methods'             => \WP_REST_Server::READABLE,
            'callback'            => [ $this, 'listChannels' ],
            'permission_callback' => [ $this, 'checkPermission' ],
        ] );

        // Create a new mapping.
        register_rest_route( self::NAMESPACE, '/channels/mappings', [
            'methods'             => \WP_REST_Server::CREATABLE,
            'callback'            => [ $this, 'createMapping' ],
            'permission_callback' => [ $this, 'checkPermission' ],
        ] );

        // Single mapping routes.
        register_rest_route( self::NAMESPACE, '/channels/mappings/(?P<id>\d+)', [
            [
                'methods'             => \WP_REST_Server::READABLE,
                'callback'            => [ $this, 'getMapping' ],
                'permission_callback' => [ $this, 'checkPermission' ],
            ],
            [
                'methods'             => \WP_REST_Server::EDITABLE,
                'callback'            => [ $this, 'updateMapping' ],
                'permission_callback' => [ $this, 'checkPermission' ],
            ],
            [
                'methods'             => \WP_REST_Server::DELETABLE,
                'callback'            => [ $this, 'deleteMapping' ],
                'permission_callback' => [ $this, 'checkPermission' ],
            ],
        ] );

        // Trigger sync for a mapping.
        register_rest_route( self::NAMESPACE, '/channels/mappings/(?P<id>\d+)/sync', [
            'methods'             => \WP_REST_Server::CREATABLE,
            'callback'            => [ $this, 'triggerSync' ],
            'permission_callback' => [ $this, 'checkPermission' ],
        ] );

        // Test connection for a mapping.
        register_rest_route( self::NAMESPACE, '/channels/mappings/(?P<id>\d+)/test', [
            'methods'             => \WP_REST_Server::CREATABLE,
            'callback'            => [ $this, 'testConnection' ],
            'permission_callback' => [ $this, 'checkPermission' ],
        ] );

        // Get sync history for a mapping.
        register_rest_route( self::NAMESPACE, '/channels/mappings/(?P<id>\d+)/history', [
            'methods'             => \WP_REST_Server::READABLE,
            'callback'            => [ $this, 'getSyncHistory' ],
            'permission_callback' => [ $this, 'checkPermission' ],
        ] );
    }

    /**
     * Permission check: current user must have the nzl_admin capability.
     */
    public function checkPermission(): bool {
        return current_user_can( 'manage_options' ) || current_user_can( self::PERMISSION );
    }

    /**
     * GET /channels
     *
     * Returns available channel types and existing mappings.
     */
    public function listChannels( \WP_REST_Request $request ): \WP_REST_Response {
        $args = [
            'channel'  => $request->get_param( 'channel' ) ?? '',
            'status'   => $request->get_param( 'status' ) ?? '',
            'orderby'  => $request->get_param( 'orderby' ) ?? 'created_at',
            'order'    => $request->get_param( 'order' ) ?? 'DESC',
            'per_page' => (int) ( $request->get_param( 'per_page' ) ?? 20 ),
            'page'     => (int) ( $request->get_param( 'page' ) ?? 1 ),
        ];

        $result   = $this->repository->list( $args );
        $channels = $this->service->getAvailableChannels();

        return new \WP_REST_Response( [
            'available_channels' => $channels,
            'mappings'           => array_map(
                fn( $m ) => $m->toArray(),
                $result['mappings']
            ),
            'total'              => $result['total'],
            'pages'              => $result['pages'],
        ], 200 );
    }

    /**
     * POST /channels/mappings
     *
     * Create a new channel mapping.
     */
    public function createMapping( \WP_REST_Request $request ): \WP_REST_Response {
        $data = $this->extractMappingData( $request );

        if ( ! $this->validator->validateCreate( $data ) ) {
            return new \WP_REST_Response( [
                'message' => __( 'Validation failed.', 'nozule' ),
                'errors'  => $this->validator->getErrors(),
            ], 422 );
        }

        $mapping = $this->repository->create( $data );

        if ( ! $mapping ) {
            return new \WP_REST_Response( [
                'message' => __( 'Failed to create channel mapping.', 'nozule' ),
            ], 500 );
        }

        return new \WP_REST_Response( [
            'message' => __( 'Channel mapping created.', 'nozule' ),
            'mapping' => $mapping->toArray(),
        ], 201 );
    }

    /**
     * GET /channels/mappings/{id}
     *
     * Get a single mapping by ID.
     */
    public function getMapping( \WP_REST_Request $request ): \WP_REST_Response {
        $id      = (int) $request->get_param( 'id' );
        $mapping = $this->repository->find( $id );

        if ( ! $mapping ) {
            return new \WP_REST_Response( [
                'message' => __( 'Channel mapping not found.', 'nozule' ),
            ], 404 );
        }

        return new \WP_REST_Response( [
            'mapping' => $mapping->toArray(),
        ], 200 );
    }

    /**
     * PUT /channels/mappings/{id}
     *
     * Update an existing mapping.
     */
    public function updateMapping( \WP_REST_Request $request ): \WP_REST_Response {
        $id      = (int) $request->get_param( 'id' );
        $mapping = $this->repository->find( $id );

        if ( ! $mapping ) {
            return new \WP_REST_Response( [
                'message' => __( 'Channel mapping not found.', 'nozule' ),
            ], 404 );
        }

        $data = $this->extractMappingData( $request );

        if ( ! $this->validator->validateUpdate( $data, $id ) ) {
            return new \WP_REST_Response( [
                'message' => __( 'Validation failed.', 'nozule' ),
                'errors'  => $this->validator->getErrors(),
            ], 422 );
        }

        $updated = $this->repository->update( $id, $data );

        if ( ! $updated ) {
            return new \WP_REST_Response( [
                'message' => __( 'Failed to update channel mapping.', 'nozule' ),
            ], 500 );
        }

        $mapping = $this->repository->find( $id );

        return new \WP_REST_Response( [
            'message' => __( 'Channel mapping updated.', 'nozule' ),
            'mapping' => $mapping->toArray(),
        ], 200 );
    }

    /**
     * DELETE /channels/mappings/{id}
     *
     * Delete a channel mapping.
     */
    public function deleteMapping( \WP_REST_Request $request ): \WP_REST_Response {
        $id      = (int) $request->get_param( 'id' );
        $mapping = $this->repository->find( $id );

        if ( ! $mapping ) {
            return new \WP_REST_Response( [
                'message' => __( 'Channel mapping not found.', 'nozule' ),
            ], 404 );
        }

        $deleted = $this->repository->delete( $id );

        if ( ! $deleted ) {
            return new \WP_REST_Response( [
                'message' => __( 'Failed to delete channel mapping.', 'nozule' ),
            ], 500 );
        }

        return new \WP_REST_Response( [
            'message' => __( 'Channel mapping deleted.', 'nozule' ),
        ], 200 );
    }

    /**
     * POST /channels/mappings/{id}/sync
     *
     * Trigger a sync operation for a specific mapping.
     *
     * Accepts an optional 'type' parameter: 'availability', 'rates',
     * 'reservations', or 'all' (default).
     */
    public function triggerSync( \WP_REST_Request $request ): \WP_REST_Response {
        $id      = (int) $request->get_param( 'id' );
        $type    = $request->get_param( 'type' ) ?? 'all';
        $mapping = $this->repository->find( $id );

        if ( ! $mapping ) {
            return new \WP_REST_Response( [
                'message' => __( 'Channel mapping not found.', 'nozule' ),
            ], 404 );
        }

        if ( ! $mapping->isActive() ) {
            return new \WP_REST_Response( [
                'message' => __( 'Channel mapping is not active. Activate it before syncing.', 'nozule' ),
            ], 422 );
        }

        $results = [];

        if ( $type === 'all' || $type === 'availability' ) {
            $availResults = $this->service->syncAvailability( $id );
            $results['availability'] = isset( $availResults[ $id ] )
                ? $availResults[ $id ]->toArray()
                : null;
        }

        if ( $type === 'all' || $type === 'rates' ) {
            $rateResults = $this->service->syncRates( $id );
            $results['rates'] = isset( $rateResults[ $id ] )
                ? $rateResults[ $id ]->toArray()
                : null;
        }

        if ( $type === 'all' || $type === 'reservations' ) {
            $reservations = $this->service->pullReservations( $id );
            $results['reservations'] = [
                'count' => count( $reservations[ $id ] ?? [] ),
                'items' => $reservations[ $id ] ?? [],
            ];
        }

        return new \WP_REST_Response( [
            'message' => __( 'Sync operation completed.', 'nozule' ),
            'results' => $results,
        ], 200 );
    }

    /**
     * POST /channels/mappings/{id}/test
     *
     * Test the connection for a specific mapping.
     */
    public function testConnection( \WP_REST_Request $request ): \WP_REST_Response {
        $id = (int) $request->get_param( 'id' );

        try {
            $success = $this->service->testConnection( $id );

            return new \WP_REST_Response( [
                'message' => $success
                    ? __( 'Connection test succeeded.', 'nozule' )
                    : __( 'Connection test failed.', 'nozule' ),
                'success' => $success,
            ], 200 );
        } catch ( \RuntimeException $e ) {
            return new \WP_REST_Response( [
                'message' => $e->getMessage(),
                'success' => false,
            ], 404 );
        }
    }

    /**
     * GET /channels/mappings/{id}/history
     *
     * Get the sync history for a specific mapping.
     */
    public function getSyncHistory( \WP_REST_Request $request ): \WP_REST_Response {
        $id    = (int) $request->get_param( 'id' );
        $limit = (int) ( $request->get_param( 'limit' ) ?? 50 );

        $mapping = $this->repository->find( $id );

        if ( ! $mapping ) {
            return new \WP_REST_Response( [
                'message' => __( 'Channel mapping not found.', 'nozule' ),
            ], 404 );
        }

        $history = $this->service->getSyncHistory( $id, $limit );

        return new \WP_REST_Response( [
            'history' => $history,
        ], 200 );
    }

    // ------------------------------------------------------------------
    // Standard CRUD aliases (used by RestController admin routes)
    // ------------------------------------------------------------------

    public function index( \WP_REST_Request $request ): \WP_REST_Response {
        return $this->listChannels( $request );
    }

    public function store( \WP_REST_Request $request ): \WP_REST_Response {
        return $this->createMapping( $request );
    }

    public function show( \WP_REST_Request $request ): \WP_REST_Response {
        return $this->getMapping( $request );
    }

    public function update( \WP_REST_Request $request ): \WP_REST_Response {
        return $this->updateMapping( $request );
    }

    public function destroy( \WP_REST_Request $request ): \WP_REST_Response {
        return $this->deleteMapping( $request );
    }

    public function sync( \WP_REST_Request $request ): \WP_REST_Response {
        return $this->triggerSync( $request );
    }

    /**
     * Extract and sanitize mapping data from the request.
     */
    private function extractMappingData( \WP_REST_Request $request ): array {
        $data = [];

        $textFields = [
            'channel_name',
            'external_room_id',
            'external_rate_id',
            'status',
        ];

        foreach ( $textFields as $field ) {
            $value = $request->get_param( $field );
            if ( $value !== null ) {
                $data[ $field ] = sanitize_text_field( $value );
            }
        }

        $intValue = $request->get_param( 'room_type_id' );
        if ( $intValue !== null ) {
            $data['room_type_id'] = absint( $intValue );
        }

        $boolFields = [ 'sync_availability', 'sync_rates', 'sync_reservations' ];
        foreach ( $boolFields as $field ) {
            $value = $request->get_param( $field );
            if ( $value !== null ) {
                $data[ $field ] = (bool) $value;
            }
        }

        $config = $request->get_param( 'config' );
        if ( $config !== null ) {
            if ( is_string( $config ) ) {
                $decoded = json_decode( $config, true );
                $data['config'] = ( json_last_error() === JSON_ERROR_NONE ) ? $decoded : [];
            } elseif ( is_array( $config ) ) {
                $data['config'] = $config;
            }
        }

        return $data;
    }
}
