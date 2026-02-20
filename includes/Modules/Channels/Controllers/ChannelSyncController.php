<?php

namespace Nozule\Modules\Channels\Controllers;

use Nozule\Modules\Channels\Models\ChannelConnection;
use Nozule\Modules\Channels\Repositories\ChannelConnectionRepository;
use Nozule\Modules\Channels\Repositories\ChannelRateMappingRepository;
use Nozule\Modules\Channels\Repositories\ChannelSyncLogRepository;
use Nozule\Modules\Channels\Services\ChannelSyncService;

/**
 * REST API controller for channel sync operations.
 *
 * All endpoints require the manage_options capability.
 *
 * Routes registered under the nozule/v1 namespace:
 *   GET    /admin/channels/connections              - List all connections
 *   POST   /admin/channels/connections              - Create/update connection
 *   DELETE /admin/channels/connections/{id}          - Delete connection
 *   POST   /admin/channels/connections/{id}/test     - Test credentials
 *   POST   /admin/channels/sync/{channel}            - Full sync
 *   POST   /admin/channels/sync/{channel}/availability - Push availability
 *   POST   /admin/channels/sync/{channel}/rates      - Push rates
 *   POST   /admin/channels/sync/{channel}/reservations - Pull reservations
 *   GET    /admin/channels/sync-log                  - List sync log
 *   GET    /admin/channels/rate-mappings/{channel}   - List rate mappings
 *   POST   /admin/channels/rate-mappings             - Create/update rate mapping
 *   DELETE /admin/channels/rate-mappings/{id}         - Delete rate mapping
 */
class ChannelSyncController {

	private ChannelSyncService $syncService;
	private ChannelConnectionRepository $connectionRepo;
	private ChannelRateMappingRepository $rateMappingRepo;
	private ChannelSyncLogRepository $syncLogRepo;

	private const NAMESPACE  = 'nozule/v1';
	private const PERMISSION = 'manage_options';

	public function __construct(
		ChannelSyncService $syncService,
		ChannelConnectionRepository $connectionRepo,
		ChannelRateMappingRepository $rateMappingRepo,
		ChannelSyncLogRepository $syncLogRepo
	) {
		$this->syncService     = $syncService;
		$this->connectionRepo  = $connectionRepo;
		$this->rateMappingRepo = $rateMappingRepo;
		$this->syncLogRepo     = $syncLogRepo;
	}

	/**
	 * Register all REST routes.
	 */
	public function registerRoutes(): void {
		// --- Connections ---
		register_rest_route( self::NAMESPACE, '/admin/channels/connections', [
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ $this, 'listConnections' ],
				'permission_callback' => [ $this, 'checkPermission' ],
			],
			[
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'saveConnection' ],
				'permission_callback' => [ $this, 'checkPermission' ],
			],
		] );

		register_rest_route( self::NAMESPACE, '/admin/channels/connections/(?P<id>\d+)', [
			'methods'             => \WP_REST_Server::DELETABLE,
			'callback'            => [ $this, 'deleteConnection' ],
			'permission_callback' => [ $this, 'checkPermission' ],
		] );

		register_rest_route( self::NAMESPACE, '/admin/channels/connections/(?P<id>\d+)/test', [
			'methods'             => \WP_REST_Server::CREATABLE,
			'callback'            => [ $this, 'testConnection' ],
			'permission_callback' => [ $this, 'checkPermission' ],
		] );

		// --- Sync Operations ---
		register_rest_route( self::NAMESPACE, '/admin/channels/sync/(?P<channel>[a-z0-9_]+)', [
			'methods'             => \WP_REST_Server::CREATABLE,
			'callback'            => [ $this, 'fullSync' ],
			'permission_callback' => [ $this, 'checkPermission' ],
		] );

		register_rest_route( self::NAMESPACE, '/admin/channels/sync/(?P<channel>[a-z0-9_]+)/availability', [
			'methods'             => \WP_REST_Server::CREATABLE,
			'callback'            => [ $this, 'pushAvailability' ],
			'permission_callback' => [ $this, 'checkPermission' ],
		] );

		register_rest_route( self::NAMESPACE, '/admin/channels/sync/(?P<channel>[a-z0-9_]+)/rates', [
			'methods'             => \WP_REST_Server::CREATABLE,
			'callback'            => [ $this, 'pushRates' ],
			'permission_callback' => [ $this, 'checkPermission' ],
		] );

		register_rest_route( self::NAMESPACE, '/admin/channels/sync/(?P<channel>[a-z0-9_]+)/reservations', [
			'methods'             => \WP_REST_Server::CREATABLE,
			'callback'            => [ $this, 'pullReservations' ],
			'permission_callback' => [ $this, 'checkPermission' ],
		] );

		// --- Sync Log ---
		register_rest_route( self::NAMESPACE, '/admin/channels/sync-log', [
			'methods'             => \WP_REST_Server::READABLE,
			'callback'            => [ $this, 'listSyncLog' ],
			'permission_callback' => [ $this, 'checkPermission' ],
		] );

		// --- Rate Mappings ---
		register_rest_route( self::NAMESPACE, '/admin/channels/rate-mappings/(?P<channel>[a-z0-9_]+)', [
			'methods'             => \WP_REST_Server::READABLE,
			'callback'            => [ $this, 'listRateMappings' ],
			'permission_callback' => [ $this, 'checkPermission' ],
		] );

		register_rest_route( self::NAMESPACE, '/admin/channels/rate-mappings', [
			'methods'             => \WP_REST_Server::CREATABLE,
			'callback'            => [ $this, 'saveRateMapping' ],
			'permission_callback' => [ $this, 'checkPermission' ],
		] );

		register_rest_route( self::NAMESPACE, '/admin/channels/rate-mappings/(?P<id>\d+)', [
			'methods'             => \WP_REST_Server::DELETABLE,
			'callback'            => [ $this, 'deleteRateMapping' ],
			'permission_callback' => [ $this, 'checkPermission' ],
		] );
	}

	/**
	 * Permission check: current user must have manage_options.
	 */
	public function checkPermission(): bool {
		return current_user_can( self::PERMISSION );
	}

	// ------------------------------------------------------------------
	// Connections
	// ------------------------------------------------------------------

	/**
	 * GET /admin/channels/connections
	 *
	 * List all channel connections.
	 */
	public function listConnections( \WP_REST_Request $request ): \WP_REST_Response {
		$connections = $this->connectionRepo->getAll();

		$data = array_map( function ( ChannelConnection $c ) {
			$arr = $c->toArray();
			// Strip encrypted credentials from response.
			unset( $arr['credentials'] );
			// Include channel label.
			$arr['channel_label'] = $c->getChannelLabel();
			return $arr;
		}, $connections );

		return new \WP_REST_Response( [
			'connections' => $data,
		], 200 );
	}

	/**
	 * POST /admin/channels/connections
	 *
	 * Create or update a channel connection.
	 * If an 'id' param is present, updates; otherwise creates.
	 */
	public function saveConnection( \WP_REST_Request $request ): \WP_REST_Response {
		$id          = $request->get_param( 'id' );
		$channelName = sanitize_text_field( $request->get_param( 'channel_name' ) ?? '' );
		$hotelId     = sanitize_text_field( $request->get_param( 'hotel_id' ) ?? '' );
		$isActive    = (int) (bool) $request->get_param( 'is_active' );

		if ( empty( $channelName ) ) {
			return new \WP_REST_Response( [
				'message' => __( 'Channel name is required.', 'nozule' ),
			], 422 );
		}

		// Build credentials from request.
		$credentialFields = $request->get_param( 'credentials' );
		if ( is_string( $credentialFields ) ) {
			$credentialFields = json_decode( $credentialFields, true );
		}
		if ( ! is_array( $credentialFields ) ) {
			$credentialFields = [];
		}

		// Add username/password if provided directly.
		$username = $request->get_param( 'username' );
		$password = $request->get_param( 'password' );
		if ( $username !== null ) {
			$credentialFields['username'] = sanitize_text_field( $username );
		}
		if ( $password !== null ) {
			$credentialFields['password'] = $password; // Don't sanitize passwords.
		}

		// Preserve api_endpoint and use_sandbox.
		$apiEndpoint = $request->get_param( 'api_endpoint' );
		if ( $apiEndpoint !== null ) {
			$credentialFields['api_endpoint'] = esc_url_raw( $apiEndpoint );
		}

		$useSandbox = $request->get_param( 'use_sandbox' );
		if ( $useSandbox !== null ) {
			$credentialFields['use_sandbox'] = (bool) $useSandbox;
		}

		$encryptedCredentials = ChannelConnection::encryptCredentials( $credentialFields );

		$data = [
			'channel_name' => $channelName,
			'hotel_id'     => $hotelId,
			'credentials'  => $encryptedCredentials,
			'is_active'    => $isActive,
		];

		if ( $id ) {
			// Update existing.
			$existing = $this->connectionRepo->find( (int) $id );
			if ( ! $existing ) {
				return new \WP_REST_Response( [
					'message' => __( 'Connection not found.', 'nozule' ),
				], 404 );
			}

			$updated = $this->connectionRepo->update( (int) $id, $data );

			if ( ! $updated ) {
				return new \WP_REST_Response( [
					'message' => __( 'Failed to update connection.', 'nozule' ),
				], 500 );
			}

			$connection = $this->connectionRepo->find( (int) $id );
			$arr = $connection->toArray();
			unset( $arr['credentials'] );
			$arr['channel_label'] = $connection->getChannelLabel();

			return new \WP_REST_Response( [
				'message'    => __( 'Connection updated.', 'nozule' ),
				'connection' => $arr,
			], 200 );
		}

		// Check for duplicate channel name.
		$duplicate = $this->connectionRepo->getByChannelName( $channelName );
		if ( $duplicate ) {
			// Update the existing one instead.
			$this->connectionRepo->update( $duplicate->id, $data );
			$connection = $this->connectionRepo->find( $duplicate->id );
			$arr = $connection->toArray();
			unset( $arr['credentials'] );
			$arr['channel_label'] = $connection->getChannelLabel();

			return new \WP_REST_Response( [
				'message'    => __( 'Connection updated.', 'nozule' ),
				'connection' => $arr,
			], 200 );
		}

		// Create new.
		$connection = $this->connectionRepo->create( $data );

		if ( ! $connection ) {
			return new \WP_REST_Response( [
				'message' => __( 'Failed to create connection.', 'nozule' ),
			], 500 );
		}

		$arr = $connection->toArray();
		unset( $arr['credentials'] );
		$arr['channel_label'] = $connection->getChannelLabel();

		return new \WP_REST_Response( [
			'message'    => __( 'Connection created.', 'nozule' ),
			'connection' => $arr,
		], 201 );
	}

	/**
	 * DELETE /admin/channels/connections/{id}
	 */
	public function deleteConnection( \WP_REST_Request $request ): \WP_REST_Response {
		$id         = (int) $request->get_param( 'id' );
		$connection = $this->connectionRepo->find( $id );

		if ( ! $connection ) {
			return new \WP_REST_Response( [
				'message' => __( 'Connection not found.', 'nozule' ),
			], 404 );
		}

		$deleted = $this->connectionRepo->delete( $id );

		if ( ! $deleted ) {
			return new \WP_REST_Response( [
				'message' => __( 'Failed to delete connection.', 'nozule' ),
			], 500 );
		}

		// Also remove rate mappings for this channel.
		$this->rateMappingRepo->deleteByChannel( $connection->channel_name );

		return new \WP_REST_Response( [
			'message' => __( 'Connection deleted.', 'nozule' ),
		], 200 );
	}

	/**
	 * POST /admin/channels/connections/{id}/test
	 */
	public function testConnection( \WP_REST_Request $request ): \WP_REST_Response {
		$id         = (int) $request->get_param( 'id' );
		$connection = $this->connectionRepo->find( $id );

		if ( ! $connection ) {
			return new \WP_REST_Response( [
				'message' => __( 'Connection not found.', 'nozule' ),
			], 404 );
		}

		$credentials = $connection->getDecryptedCredentials();

		// For Booking.com, we need a BookingComApiClient instance.
		// This is a test-only operation, so we instantiate directly.
		$client = new \Nozule\Modules\Channels\Services\BookingComApiClient(
			new \Nozule\Core\Logger( new \Nozule\Core\Database() )
		);

		$client->setCredentials(
			$connection->hotel_id ?: ( $credentials['hotel_id'] ?? '' ),
			$credentials['username'] ?? '',
			$credentials['password'] ?? ''
		);

		$customUrl = $credentials['api_endpoint'] ?? '';
		if ( ! empty( $customUrl ) ) {
			$client->setBaseUrl( $customUrl );
		} elseif ( ! empty( $credentials['use_sandbox'] ) ) {
			$client->setBaseUrl( \Nozule\Modules\Channels\Services\BookingComApiClient::SANDBOX_BASE_URL );
		}

		$result = $client->testConnection();

		return new \WP_REST_Response( [
			'success' => $result['success'],
			'message' => $result['message'],
		], 200 );
	}

	// ------------------------------------------------------------------
	// Sync Operations
	// ------------------------------------------------------------------

	/**
	 * POST /admin/channels/sync/{channel}
	 */
	public function fullSync( \WP_REST_Request $request ): \WP_REST_Response {
		$channel = sanitize_text_field( $request->get_param( 'channel' ) );
		$results = $this->syncService->fullSync( $channel );

		return new \WP_REST_Response( [
			'message' => __( 'Full sync completed.', 'nozule' ),
			'results' => $results,
		], 200 );
	}

	/**
	 * POST /admin/channels/sync/{channel}/availability
	 */
	public function pushAvailability( \WP_REST_Request $request ): \WP_REST_Response {
		$channel    = sanitize_text_field( $request->get_param( 'channel' ) );
		$roomTypeId = $request->get_param( 'room_type_id' ) ? (int) $request->get_param( 'room_type_id' ) : null;
		$startDate  = sanitize_text_field( $request->get_param( 'start_date' ) ?? '' );
		$endDate    = sanitize_text_field( $request->get_param( 'end_date' ) ?? '' );

		$result = $this->syncService->pushAvailability( $channel, $roomTypeId, $startDate, $endDate );

		return new \WP_REST_Response( [
			'message' => $result['message'] ?? __( 'Availability push completed.', 'nozule' ),
			'result'  => $result,
		], 200 );
	}

	/**
	 * POST /admin/channels/sync/{channel}/rates
	 */
	public function pushRates( \WP_REST_Request $request ): \WP_REST_Response {
		$channel    = sanitize_text_field( $request->get_param( 'channel' ) );
		$roomTypeId = $request->get_param( 'room_type_id' ) ? (int) $request->get_param( 'room_type_id' ) : null;
		$startDate  = sanitize_text_field( $request->get_param( 'start_date' ) ?? '' );
		$endDate    = sanitize_text_field( $request->get_param( 'end_date' ) ?? '' );

		$result = $this->syncService->pushRates( $channel, $roomTypeId, $startDate, $endDate );

		return new \WP_REST_Response( [
			'message' => $result['message'] ?? __( 'Rate push completed.', 'nozule' ),
			'result'  => $result,
		], 200 );
	}

	/**
	 * POST /admin/channels/sync/{channel}/reservations
	 */
	public function pullReservations( \WP_REST_Request $request ): \WP_REST_Response {
		$channel = sanitize_text_field( $request->get_param( 'channel' ) );
		$result  = $this->syncService->pullReservations( $channel );

		return new \WP_REST_Response( [
			'message' => $result['message'] ?? __( 'Reservation pull completed.', 'nozule' ),
			'result'  => $result,
		], 200 );
	}

	// ------------------------------------------------------------------
	// Sync Log
	// ------------------------------------------------------------------

	/**
	 * GET /admin/channels/sync-log
	 */
	public function listSyncLog( \WP_REST_Request $request ): \WP_REST_Response {
		$args = [
			'channel'   => sanitize_text_field( $request->get_param( 'channel' ) ?? '' ),
			'direction' => sanitize_text_field( $request->get_param( 'direction' ) ?? '' ),
			'status'    => sanitize_text_field( $request->get_param( 'status' ) ?? '' ),
			'sync_type' => sanitize_text_field( $request->get_param( 'sync_type' ) ?? '' ),
			'orderby'   => sanitize_text_field( $request->get_param( 'orderby' ) ?? 'created_at' ),
			'order'     => sanitize_text_field( $request->get_param( 'order' ) ?? 'DESC' ),
			'per_page'  => (int) ( $request->get_param( 'per_page' ) ?? 20 ),
			'page'      => (int) ( $request->get_param( 'page' ) ?? 1 ),
		];

		$result = $this->syncLogRepo->list( $args );

		return new \WP_REST_Response( [
			'items' => array_map( function ( $item ) {
				return $item->toArray();
			}, $result['items'] ),
			'total' => $result['total'],
			'pages' => $result['pages'],
		], 200 );
	}

	// ------------------------------------------------------------------
	// Rate Mappings
	// ------------------------------------------------------------------

	/**
	 * GET /admin/channels/rate-mappings/{channel}
	 */
	public function listRateMappings( \WP_REST_Request $request ): \WP_REST_Response {
		$channel  = sanitize_text_field( $request->get_param( 'channel' ) );
		$mappings = $this->rateMappingRepo->getByChannel( $channel );

		return new \WP_REST_Response( [
			'mappings' => array_map( function ( $m ) {
				return $m->toArray();
			}, $mappings ),
		], 200 );
	}

	/**
	 * POST /admin/channels/rate-mappings
	 *
	 * Create or update a rate mapping.
	 */
	public function saveRateMapping( \WP_REST_Request $request ): \WP_REST_Response {
		$id = $request->get_param( 'id' );

		$data = [
			'channel_name'       => sanitize_text_field( $request->get_param( 'channel_name' ) ?? '' ),
			'local_room_type_id' => (int) ( $request->get_param( 'local_room_type_id' ) ?? 0 ),
			'local_rate_plan_id' => (int) ( $request->get_param( 'local_rate_plan_id' ) ?? 0 ),
			'channel_room_id'    => sanitize_text_field( $request->get_param( 'channel_room_id' ) ?? '' ),
			'channel_rate_id'    => sanitize_text_field( $request->get_param( 'channel_rate_id' ) ?? '' ),
			'is_active'          => $request->get_param( 'is_active' ) !== null
				? (int) (bool) $request->get_param( 'is_active' )
				: 1,
		];

		if ( empty( $data['channel_name'] ) ) {
			return new \WP_REST_Response( [
				'message' => __( 'Channel name is required.', 'nozule' ),
			], 422 );
		}

		if ( empty( $data['local_room_type_id'] ) ) {
			return new \WP_REST_Response( [
				'message' => __( 'Local room type is required.', 'nozule' ),
			], 422 );
		}

		if ( $id ) {
			$existing = $this->rateMappingRepo->find( (int) $id );
			if ( ! $existing ) {
				return new \WP_REST_Response( [
					'message' => __( 'Rate mapping not found.', 'nozule' ),
				], 404 );
			}

			$updated = $this->rateMappingRepo->update( (int) $id, $data );

			if ( ! $updated ) {
				return new \WP_REST_Response( [
					'message' => __( 'Failed to update rate mapping.', 'nozule' ),
				], 500 );
			}

			$mapping = $this->rateMappingRepo->find( (int) $id );

			return new \WP_REST_Response( [
				'message' => __( 'Rate mapping updated.', 'nozule' ),
				'mapping' => $mapping->toArray(),
			], 200 );
		}

		$mapping = $this->rateMappingRepo->create( $data );

		if ( ! $mapping ) {
			return new \WP_REST_Response( [
				'message' => __( 'Failed to create rate mapping.', 'nozule' ),
			], 500 );
		}

		return new \WP_REST_Response( [
			'message' => __( 'Rate mapping created.', 'nozule' ),
			'mapping' => $mapping->toArray(),
		], 201 );
	}

	/**
	 * DELETE /admin/channels/rate-mappings/{id}
	 */
	public function deleteRateMapping( \WP_REST_Request $request ): \WP_REST_Response {
		$id      = (int) $request->get_param( 'id' );
		$mapping = $this->rateMappingRepo->find( $id );

		if ( ! $mapping ) {
			return new \WP_REST_Response( [
				'message' => __( 'Rate mapping not found.', 'nozule' ),
			], 404 );
		}

		$deleted = $this->rateMappingRepo->delete( $id );

		if ( ! $deleted ) {
			return new \WP_REST_Response( [
				'message' => __( 'Failed to delete rate mapping.', 'nozule' ),
			], 500 );
		}

		return new \WP_REST_Response( [
			'message' => __( 'Rate mapping deleted.', 'nozule' ),
		], 200 );
	}
}
