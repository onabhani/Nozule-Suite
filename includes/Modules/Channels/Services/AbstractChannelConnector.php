<?php

namespace Venezia\Modules\Channels\Services;

use Venezia\Modules\Channels\Models\SyncResult;

/**
 * Abstract base class for OTA channel connectors.
 *
 * Each concrete connector implements the API communication logic
 * for a specific OTA (Booking.com, Expedia, etc.). The connector
 * is instantiated with channel-specific configuration (API keys,
 * hotel IDs, endpoints) and exposes a uniform interface for pushing
 * and pulling data.
 */
abstract class AbstractChannelConnector {

    /**
     * Channel-specific configuration (API keys, endpoints, etc.).
     *
     * @var array<string, mixed>
     */
    protected array $config = [];

    /**
     * @param array<string, mixed> $config Channel configuration.
     */
    public function __construct( array $config ) {
        $this->config = $config;
    }

    /**
     * Push room availability/inventory to the channel.
     *
     * @param array $inventory Array of inventory records, each containing
     *                         room_type_id, date, available_rooms, etc.
     */
    abstract public function pushAvailability( array $inventory ): SyncResult;

    /**
     * Push rate/pricing data to the channel.
     *
     * @param array $rates Array of rate records, each containing
     *                     room_type_id, date, price, min_stay, etc.
     */
    abstract public function pushRates( array $rates ): SyncResult;

    /**
     * Pull new and updated reservations from the channel.
     *
     * @return array Array of reservation data from the OTA.
     */
    abstract public function pullReservations(): array;

    /**
     * Confirm a reservation on the channel side.
     *
     * @param string $id External reservation ID on the OTA.
     * @return bool True if confirmation succeeded.
     */
    abstract public function confirmReservation( string $id ): bool;

    /**
     * Cancel a reservation on the channel side.
     *
     * @param string $id     External reservation ID on the OTA.
     * @param string $reason Cancellation reason.
     * @return bool True if cancellation succeeded.
     */
    abstract public function cancelReservation( string $id, string $reason ): bool;

    /**
     * Test the connection to the channel API.
     *
     * @return bool True if the connection is alive and credentials are valid.
     */
    abstract public function testConnection(): bool;

    /**
     * Get the configuration fields required by this connector.
     *
     * Returns an array of field definitions for the admin UI, e.g.:
     *   [
     *       [ 'key' => 'api_key', 'label' => 'API Key', 'type' => 'text', 'required' => true ],
     *       [ 'key' => 'hotel_id', 'label' => 'Hotel ID', 'type' => 'text', 'required' => true ],
     *   ]
     *
     * @return array<int, array{ key: string, label: string, type: string, required: bool }>
     */
    abstract public function getConfigFields(): array;

    /**
     * Get a configuration value by key.
     *
     * @param mixed $default
     * @return mixed
     */
    protected function getConfigValue( string $key, $default = null ) {
        return $this->config[ $key ] ?? $default;
    }

    /**
     * Get the channel name identifier (e.g. 'booking_com').
     */
    abstract public function getChannelName(): string;

    /**
     * Get a human-readable label for the channel.
     */
    abstract public function getChannelLabel(): string;
}
