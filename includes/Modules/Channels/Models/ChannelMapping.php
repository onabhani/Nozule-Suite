<?php

namespace Venezia\Modules\Channels\Models;

use Venezia\Core\BaseModel;

/**
 * Channel Mapping model.
 *
 * Maps an internal room type to an external OTA channel listing,
 * tracking sync preferences and connection status.
 *
 * @property int    $id
 * @property string $channel_name       OTA identifier (e.g. 'booking_com', 'expedia').
 * @property int    $room_type_id       Local room type ID.
 * @property string $external_room_id   Room/property ID on the OTA side.
 * @property string $external_rate_id   Rate plan ID on the OTA side.
 * @property bool   $sync_availability  Whether to push availability updates.
 * @property bool   $sync_rates         Whether to push rate updates.
 * @property bool   $sync_reservations  Whether to pull reservations.
 * @property string $status             Connection status: active, inactive, error.
 * @property string $last_sync_at       Timestamp of last successful sync.
 * @property string $last_sync_status   Result of last sync: success, partial, failed.
 * @property string $last_error         Last error message, if any.
 * @property array  $config             Channel-specific configuration (JSON).
 * @property string $created_at
 * @property string $updated_at
 */
class ChannelMapping extends BaseModel {

    /**
     * @var string[]
     */
    protected static array $intFields = [
        'id',
        'room_type_id',
    ];

    /**
     * @var string[]
     */
    protected static array $boolFields = [
        'sync_availability',
        'sync_rates',
        'sync_reservations',
    ];

    /**
     * @var string[]
     */
    protected static array $jsonFields = [
        'config',
    ];

    /**
     * Create from a database row with type casting.
     */
    public static function fromRow( object $row ): static {
        $data = (array) $row;

        foreach ( static::$intFields as $field ) {
            if ( isset( $data[ $field ] ) ) {
                $data[ $field ] = (int) $data[ $field ];
            }
        }

        foreach ( static::$boolFields as $field ) {
            if ( isset( $data[ $field ] ) ) {
                $data[ $field ] = (bool) $data[ $field ];
            }
        }

        foreach ( static::$jsonFields as $field ) {
            if ( isset( $data[ $field ] ) && is_string( $data[ $field ] ) ) {
                $decoded = json_decode( $data[ $field ], true );
                $data[ $field ] = ( json_last_error() === JSON_ERROR_NONE ) ? $decoded : [];
            }
        }

        return new static( $data );
    }

    /**
     * Check whether availability sync is enabled for this mapping.
     */
    public function shouldSyncAvailability(): bool {
        return (bool) $this->sync_availability;
    }

    /**
     * Check whether rate sync is enabled for this mapping.
     */
    public function shouldSyncRates(): bool {
        return (bool) $this->sync_rates;
    }

    /**
     * Check whether reservation sync is enabled for this mapping.
     */
    public function shouldSyncReservations(): bool {
        return (bool) $this->sync_reservations;
    }

    /**
     * Check whether the mapping is active and ready to sync.
     */
    public function isActive(): bool {
        return $this->status === 'active';
    }

    /**
     * Check whether the mapping is in an error state.
     */
    public function hasError(): bool {
        return $this->status === 'error';
    }

    /**
     * Get the channel-specific configuration value.
     *
     * @param mixed $default
     * @return mixed
     */
    public function getConfig( string $key, $default = null ) {
        $config = $this->config;

        if ( ! is_array( $config ) ) {
            return $default;
        }

        return $config[ $key ] ?? $default;
    }
}
