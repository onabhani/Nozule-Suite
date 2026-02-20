<?php

namespace Nozule\Modules\Channels\Services;

use Nozule\Core\Database;
use Nozule\Core\EventDispatcher;
use Nozule\Core\Logger;
use Nozule\Modules\Channels\Models\ChannelConnection;
use Nozule\Modules\Channels\Models\ChannelRateMap;
use Nozule\Modules\Channels\Models\ChannelSyncLog;
use Nozule\Modules\Channels\Repositories\ChannelConnectionRepository;
use Nozule\Modules\Channels\Repositories\ChannelRateMappingRepository;
use Nozule\Modules\Channels\Repositories\ChannelSyncLogRepository;

/**
 * Channel sync orchestrator service.
 *
 * Coordinates availability/rate pushes and reservation pulls across
 * all configured OTA channels. Delegates API communication to the
 * BookingComApiClient (or future OTA-specific clients).
 */
class ChannelSyncService {

	private ChannelConnectionRepository $connectionRepo;
	private ChannelRateMappingRepository $rateMappingRepo;
	private ChannelSyncLogRepository $syncLogRepo;
	private Database $db;
	private EventDispatcher $events;
	private Logger $logger;

	/**
	 * Registry of channel name => API client factory callables.
	 *
	 * @var array<string, callable>
	 */
	private array $clientRegistry = [];

	public function __construct(
		ChannelConnectionRepository $connectionRepo,
		ChannelRateMappingRepository $rateMappingRepo,
		ChannelSyncLogRepository $syncLogRepo,
		Database $db,
		EventDispatcher $events,
		Logger $logger
	) {
		$this->connectionRepo  = $connectionRepo;
		$this->rateMappingRepo = $rateMappingRepo;
		$this->syncLogRepo     = $syncLogRepo;
		$this->db              = $db;
		$this->events          = $events;
		$this->logger          = $logger;
	}

	/**
	 * Register an API client factory for a channel.
	 *
	 * @param string   $channelName Channel identifier (e.g. 'booking_com').
	 * @param callable $factory     Factory returning an API client instance.
	 */
	public function registerClient( string $channelName, callable $factory ): void {
		$this->clientRegistry[ $channelName ] = $factory;
	}

	/**
	 * Push availability for a channel.
	 *
	 * @param string   $channelName Channel identifier.
	 * @param int|null $roomTypeId  Optional: limit to a single room type.
	 * @param string   $startDate   Start date (Y-m-d). Defaults to today.
	 * @param string   $endDate     End date (Y-m-d). Defaults to +365 days.
	 * @return array Sync result.
	 */
	public function pushAvailability( string $channelName, ?int $roomTypeId = null, string $startDate = '', string $endDate = '' ): array {
		$connection = $this->connectionRepo->getByChannelName( $channelName );

		if ( ! $connection || ! $connection->isActive() ) {
			return [
				'success' => false,
				'message' => __( 'Channel connection is not active.', 'nozule' ),
			];
		}

		// Create sync log entry.
		$log = $this->syncLogRepo->create( [
			'channel_name' => $channelName,
			'direction'    => ChannelSyncLog::DIRECTION_PUSH,
			'sync_type'    => ChannelSyncLog::TYPE_AVAILABILITY,
			'status'       => ChannelSyncLog::STATUS_PENDING,
		] );

		try {
			$client = $this->getClient( $connection );

			if ( empty( $startDate ) ) {
				$startDate = current_time( 'Y-m-d' );
			}
			if ( empty( $endDate ) ) {
				$endDate = wp_date( 'Y-m-d', strtotime( '+365 days' ) );
			}

			// Get active rate mappings for this channel.
			$mappings = $this->rateMappingRepo->getActiveByChannel( $channelName );

			if ( $roomTypeId ) {
				$mappings = array_filter( $mappings, function ( ChannelRateMap $m ) use ( $roomTypeId ) {
					return $m->local_room_type_id === $roomTypeId;
				} );
			}

			if ( empty( $mappings ) ) {
				$this->syncLogRepo->complete(
					$log->id,
					ChannelSyncLog::STATUS_SUCCESS,
					0,
					__( 'No rate mappings found for this channel.', 'nozule' )
				);

				return [
					'success' => true,
					'message' => __( 'No rate mappings configured. Nothing to push.', 'nozule' ),
				];
			}

			// Fetch inventory data.
			$inventoryTable = $this->db->table( 'room_inventory' );
			$availData      = [];

			foreach ( $mappings as $mapping ) {
				$rows = $this->db->getResults(
					"SELECT date, available_rooms, stop_sell, min_stay
					 FROM {$inventoryTable}
					 WHERE room_type_id = %d
					   AND date >= %s
					   AND date <= %s
					 ORDER BY date ASC",
					$mapping->local_room_type_id,
					$startDate,
					$endDate
				);

				foreach ( $rows as $row ) {
					$availData[] = [
						'channel_room_id' => $mapping->channel_room_id,
						'date'            => $row->date,
						'available_rooms' => (int) $row->available_rooms,
						'stop_sell'       => (bool) ( $row->stop_sell ?? false ),
						'min_stay'        => (int) ( $row->min_stay ?? 1 ),
					];
				}
			}

			// Push to API.
			$result = $client->pushAvailability( $availData );

			// Update log.
			$status = $result['success'] ? ChannelSyncLog::STATUS_SUCCESS : ChannelSyncLog::STATUS_FAILED;
			if ( $result['success'] && ! empty( $result['errors'] ) ) {
				$status = ChannelSyncLog::STATUS_PARTIAL;
			}

			$this->syncLogRepo->complete(
				$log->id,
				$status,
				$result['records_processed'] ?? 0,
				implode( '; ', $result['errors'] ?? [] )
			);

			// Update last sync.
			$this->connectionRepo->updateLastSync( $connection->id );

			$this->events->dispatch( 'channels/availability_pushed', $channelName, $result );

			return $result;

		} catch ( \Throwable $e ) {
			$this->logger->error( 'Channel availability push failed.', [
				'channel' => $channelName,
				'error'   => $e->getMessage(),
			] );

			if ( $log ) {
				$this->syncLogRepo->complete(
					$log->id,
					ChannelSyncLog::STATUS_FAILED,
					0,
					$e->getMessage()
				);
			}

			return [
				'success' => false,
				'message' => $e->getMessage(),
			];
		}
	}

	/**
	 * Push rates for a channel.
	 *
	 * @param string   $channelName Channel identifier.
	 * @param int|null $roomTypeId  Optional: limit to a single room type.
	 * @param string   $startDate   Start date (Y-m-d). Defaults to today.
	 * @param string   $endDate     End date (Y-m-d). Defaults to +365 days.
	 * @return array Sync result.
	 */
	public function pushRates( string $channelName, ?int $roomTypeId = null, string $startDate = '', string $endDate = '' ): array {
		$connection = $this->connectionRepo->getByChannelName( $channelName );

		if ( ! $connection || ! $connection->isActive() ) {
			return [
				'success' => false,
				'message' => __( 'Channel connection is not active.', 'nozule' ),
			];
		}

		$log = $this->syncLogRepo->create( [
			'channel_name' => $channelName,
			'direction'    => ChannelSyncLog::DIRECTION_PUSH,
			'sync_type'    => ChannelSyncLog::TYPE_RATES,
			'status'       => ChannelSyncLog::STATUS_PENDING,
		] );

		try {
			$client = $this->getClient( $connection );

			if ( empty( $startDate ) ) {
				$startDate = current_time( 'Y-m-d' );
			}
			if ( empty( $endDate ) ) {
				$endDate = wp_date( 'Y-m-d', strtotime( '+365 days' ) );
			}

			$mappings = $this->rateMappingRepo->getActiveByChannel( $channelName );

			if ( $roomTypeId ) {
				$mappings = array_filter( $mappings, function ( ChannelRateMap $m ) use ( $roomTypeId ) {
					return $m->local_room_type_id === $roomTypeId;
				} );
			}

			if ( empty( $mappings ) ) {
				$this->syncLogRepo->complete(
					$log->id,
					ChannelSyncLog::STATUS_SUCCESS,
					0,
					__( 'No rate mappings found for this channel.', 'nozule' )
				);

				return [
					'success' => true,
					'message' => __( 'No rate mappings configured. Nothing to push.', 'nozule' ),
				];
			}

			// Fetch rate/inventory data.
			$inventoryTable = $this->db->table( 'room_inventory' );
			$roomTypeTable  = $this->db->table( 'room_types' );
			$settingsTable  = $this->db->table( 'settings' );
			$rateData       = [];

			// Get the default currency.
			$currency = $this->db->getVar(
				"SELECT setting_value FROM {$settingsTable} WHERE setting_key = %s",
				'currency.default'
			);
			$currency = $currency ?: 'USD';

			foreach ( $mappings as $mapping ) {
				$rows = $this->db->getResults(
					"SELECT i.date, i.price_override, rt.base_price
					 FROM {$inventoryTable} i
					 JOIN {$roomTypeTable} rt ON rt.id = i.room_type_id
					 WHERE i.room_type_id = %d
					   AND i.date >= %s
					   AND i.date <= %s
					 ORDER BY i.date ASC",
					$mapping->local_room_type_id,
					$startDate,
					$endDate
				);

				foreach ( $rows as $row ) {
					$price = ! empty( $row->price_override ) ? (float) $row->price_override : (float) $row->base_price;

					$rateData[] = [
						'channel_room_id' => $mapping->channel_room_id,
						'channel_rate_id' => $mapping->channel_rate_id,
						'date'            => $row->date,
						'price'           => $price,
						'currency'        => $currency,
					];
				}
			}

			$result = $client->pushRates( $rateData );

			$status = $result['success'] ? ChannelSyncLog::STATUS_SUCCESS : ChannelSyncLog::STATUS_FAILED;
			if ( $result['success'] && ! empty( $result['errors'] ) ) {
				$status = ChannelSyncLog::STATUS_PARTIAL;
			}

			$this->syncLogRepo->complete(
				$log->id,
				$status,
				$result['records_processed'] ?? 0,
				implode( '; ', $result['errors'] ?? [] )
			);

			$this->connectionRepo->updateLastSync( $connection->id );

			$this->events->dispatch( 'channels/rates_pushed', $channelName, $result );

			return $result;

		} catch ( \Throwable $e ) {
			$this->logger->error( 'Channel rate push failed.', [
				'channel' => $channelName,
				'error'   => $e->getMessage(),
			] );

			if ( $log ) {
				$this->syncLogRepo->complete(
					$log->id,
					ChannelSyncLog::STATUS_FAILED,
					0,
					$e->getMessage()
				);
			}

			return [
				'success' => false,
				'message' => $e->getMessage(),
			];
		}
	}

	/**
	 * Pull reservations from a channel.
	 *
	 * @param string $channelName Channel identifier.
	 * @return array Sync result with reservations.
	 */
	public function pullReservations( string $channelName ): array {
		$connection = $this->connectionRepo->getByChannelName( $channelName );

		if ( ! $connection || ! $connection->isActive() ) {
			return [
				'success'      => false,
				'message'      => __( 'Channel connection is not active.', 'nozule' ),
				'reservations' => [],
			];
		}

		$log = $this->syncLogRepo->create( [
			'channel_name' => $channelName,
			'direction'    => ChannelSyncLog::DIRECTION_PULL,
			'sync_type'    => ChannelSyncLog::TYPE_RESERVATIONS,
			'status'       => ChannelSyncLog::STATUS_PENDING,
		] );

		try {
			$client = $this->getClient( $connection );

			// Use last sync timestamp for incremental pull.
			$lastSync = $connection->last_sync_at;

			$result = $client->pullReservations( $lastSync );

			$reservations = $result['reservations'] ?? [];

			// Process each reservation: create local bookings.
			$processed = 0;
			foreach ( $reservations as $reservation ) {
				$created = $this->processIncomingReservation( $channelName, $reservation );
				if ( $created ) {
					$processed++;
				}
			}

			$status = $result['success'] ? ChannelSyncLog::STATUS_SUCCESS : ChannelSyncLog::STATUS_FAILED;

			$this->syncLogRepo->complete(
				$log->id,
				$status,
				$processed,
				implode( '; ', $result['errors'] ?? [] )
			);

			$this->connectionRepo->updateLastSync( $connection->id );

			$this->events->dispatch( 'channels/reservations_pulled', $channelName, $reservations );

			return [
				'success'      => $result['success'],
				'message'      => $result['message'],
				'reservations' => $reservations,
				'processed'    => $processed,
			];

		} catch ( \Throwable $e ) {
			$this->logger->error( 'Channel reservation pull failed.', [
				'channel' => $channelName,
				'error'   => $e->getMessage(),
			] );

			if ( $log ) {
				$this->syncLogRepo->complete(
					$log->id,
					ChannelSyncLog::STATUS_FAILED,
					0,
					$e->getMessage()
				);
			}

			return [
				'success'      => false,
				'message'      => $e->getMessage(),
				'reservations' => [],
			];
		}
	}

	/**
	 * Run a full sync for a channel: push availability + rates, pull reservations.
	 *
	 * @param string $channelName Channel identifier.
	 * @return array Combined results.
	 */
	public function fullSync( string $channelName ): array {
		$this->logger->info( 'Starting full channel sync.', [ 'channel' => $channelName ] );

		$availResult = $this->pushAvailability( $channelName );
		$ratesResult = $this->pushRates( $channelName );
		$pullResult  = $this->pullReservations( $channelName );

		return [
			'availability' => $availResult,
			'rates'        => $ratesResult,
			'reservations' => $pullResult,
		];
	}

	/**
	 * Process an incoming reservation from a channel and create a local booking.
	 *
	 * @param string $channelName Channel identifier.
	 * @param array  $reservation Reservation data from the OTA.
	 * @return bool True if a booking was created or updated.
	 */
	private function processIncomingReservation( string $channelName, array $reservation ): bool {
		$externalId = $reservation['external_id'] ?? '';

		if ( empty( $externalId ) ) {
			$this->logger->warning( 'Skipping reservation without external ID.', [
				'channel' => $channelName,
			] );
			return false;
		}

		// Check if this reservation already exists locally.
		$bookingsTable = $this->db->table( 'bookings' );
		$existing      = $this->db->getRow(
			"SELECT id FROM {$bookingsTable} WHERE channel_booking_id = %s AND channel_name = %s",
			$externalId,
			$channelName
		);

		if ( $existing ) {
			$this->logger->debug( 'Reservation already exists locally, skipping.', [
				'external_id' => $externalId,
				'channel'     => $channelName,
				'local_id'    => $existing->id,
			] );
			return false;
		}

		// Map the channel room type code to a local room type.
		$roomTypeCode = $reservation['room_type_code'] ?? '';
		$localRoomTypeId = null;

		if ( ! empty( $roomTypeCode ) ) {
			$rateMapTable = $this->db->table( 'channel_rate_map' );
			$mapping      = $this->db->getRow(
				"SELECT local_room_type_id FROM {$rateMapTable}
				 WHERE channel_name = %s AND channel_room_id = %s AND is_active = 1
				 LIMIT 1",
				$channelName,
				$roomTypeCode
			);

			if ( $mapping ) {
				$localRoomTypeId = (int) $mapping->local_room_type_id;
			}
		}

		// Create the guest record if needed.
		$guestId = $this->findOrCreateGuest( $reservation );

		// Create the booking.
		$now         = current_time( 'mysql', true );
		$bookingData = [
			'guest_id'           => $guestId,
			'room_type_id'       => $localRoomTypeId,
			'check_in'           => $reservation['check_in'] ?? '',
			'check_out'          => $reservation['check_out'] ?? '',
			'num_guests'         => (int) ( $reservation['num_guests'] ?? 1 ),
			'total_amount'       => (float) ( $reservation['total_amount'] ?? 0 ),
			'currency'           => $reservation['currency'] ?? 'USD',
			'status'             => 'confirmed',
			'source'             => $channelName,
			'channel_name'       => $channelName,
			'channel_booking_id' => $externalId,
			'special_requests'   => $reservation['special_requests'] ?? '',
			'created_at'         => $now,
			'updated_at'         => $now,
		];

		$bookingId = $this->db->insert( 'bookings', $bookingData );

		if ( $bookingId === false ) {
			$this->logger->error( 'Failed to create local booking from channel reservation.', [
				'external_id' => $externalId,
				'channel'     => $channelName,
			] );
			return false;
		}

		$this->logger->info( 'Created local booking from channel reservation.', [
			'booking_id'  => $bookingId,
			'external_id' => $externalId,
			'channel'     => $channelName,
		] );

		$this->events->dispatch( 'channels/reservation_imported', $channelName, $bookingId, $reservation );

		return true;
	}

	/**
	 * Find or create a guest record from reservation data.
	 *
	 * @param array $reservation Reservation data.
	 * @return int|null Guest ID.
	 */
	private function findOrCreateGuest( array $reservation ): ?int {
		$email = $reservation['guest_email'] ?? '';
		$guestsTable = $this->db->table( 'guests' );

		// Try to find by email first.
		if ( ! empty( $email ) ) {
			$existing = $this->db->getRow(
				"SELECT id FROM {$guestsTable} WHERE email = %s LIMIT 1",
				$email
			);

			if ( $existing ) {
				return (int) $existing->id;
			}
		}

		// Create a new guest.
		$now       = current_time( 'mysql', true );
		$guestData = [
			'first_name' => $reservation['guest_first_name'] ?? '',
			'last_name'  => $reservation['guest_last_name'] ?? '',
			'email'      => $email,
			'phone'      => $reservation['guest_phone'] ?? '',
			'source'     => 'channel',
			'created_at' => $now,
			'updated_at' => $now,
		];

		$guestId = $this->db->insert( 'guests', $guestData );

		return $guestId !== false ? $guestId : null;
	}

	/**
	 * Get an API client for a channel connection.
	 *
	 * @param ChannelConnection $connection The channel connection.
	 * @return BookingComApiClient The API client.
	 * @throws \RuntimeException If no client is registered for the channel.
	 */
	private function getClient( ChannelConnection $connection ): BookingComApiClient {
		$channelName = $connection->channel_name;

		if ( ! isset( $this->clientRegistry[ $channelName ] ) ) {
			throw new \RuntimeException(
				sprintf(
					/* translators: %s: channel name */
					__( 'No API client registered for channel "%s".', 'nozule' ),
					$channelName
				)
			);
		}

		$client = call_user_func( $this->clientRegistry[ $channelName ] );

		// Configure client with connection credentials.
		$credentials = $connection->getDecryptedCredentials();

		$client->setCredentials(
			$connection->hotel_id ?: ( $credentials['hotel_id'] ?? '' ),
			$credentials['username'] ?? '',
			$credentials['password'] ?? ''
		);

		// Set custom base URL if configured.
		$customUrl = $credentials['api_endpoint'] ?? '';
		if ( ! empty( $customUrl ) ) {
			$client->setBaseUrl( $customUrl );
		} elseif ( ! empty( $credentials['use_sandbox'] ) ) {
			$client->setBaseUrl( BookingComApiClient::SANDBOX_BASE_URL );
		}

		return $client;
	}
}
