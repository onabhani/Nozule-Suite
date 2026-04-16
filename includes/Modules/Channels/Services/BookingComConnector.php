<?php

namespace Nozule\Modules\Channels\Services;

use Nozule\Core\Logger;
use Nozule\Core\Plugin;
use Nozule\Modules\Channels\Models\SyncResult;

/**
 * Booking.com channel connector.
 *
 * Thin adapter that maps the AbstractChannelConnector contract to
 * BookingComApiClient (which handles XML building, HTTP, and parsing).
 */
class BookingComConnector extends AbstractChannelConnector {

    private ?BookingComApiClient $client = null;

    public function getChannelName(): string {
        return 'booking_com';
    }

    public function getChannelLabel(): string {
        return 'Booking.com';
    }

    /**
     * Lazily resolve and configure the API client from connector config.
     */
    private function client(): ?BookingComApiClient {
        if ( $this->client instanceof BookingComApiClient ) {
            return $this->client;
        }

        $hotelId  = (string) $this->getConfigValue( 'hotel_id', '' );
        $username = (string) $this->getConfigValue( 'username', $this->getConfigValue( 'api_key', '' ) );
        $password = (string) $this->getConfigValue( 'password', '' );

        if ( $hotelId === '' || $username === '' || $password === '' ) {
            return null;
        }

        $logger = Plugin::getInstance()->container()->get( Logger::class );
        $client = new BookingComApiClient( $logger );
        $client->setCredentials( $hotelId, $username, $password );

        $endpoint = (string) $this->getConfigValue( 'api_endpoint', '' );
        if ( $endpoint !== '' ) {
            $client->setBaseUrl( $endpoint );
        } elseif ( ! empty( $this->getConfigValue( 'use_sandbox', false ) ) ) {
            $client->setBaseUrl( BookingComApiClient::SANDBOX_BASE_URL );
        }

        $this->client = $client;
        return $this->client;
    }

    public function pushAvailability( array $inventory ): SyncResult {
        $client = $this->client();
        if ( ! $client ) {
            return SyncResult::failure( __( 'Booking.com credentials are not configured.', 'nozule' ) );
        }

        $result = $client->pushAvailability( $inventory );

        return $result['success']
            ? SyncResult::success( $result['message'], $result['records_processed'] )
            : SyncResult::failure( $result['message'] );
    }

    public function pushRates( array $rates ): SyncResult {
        $client = $this->client();
        if ( ! $client ) {
            return SyncResult::failure( __( 'Booking.com credentials are not configured.', 'nozule' ) );
        }

        $result = $client->pushRates( $rates );

        return $result['success']
            ? SyncResult::success( $result['message'], $result['records_processed'] )
            : SyncResult::failure( $result['message'] );
    }

    public function pullReservations(): array {
        $client = $this->client();
        if ( ! $client ) {
            return [];
        }

        $lastSync = (string) $this->getConfigValue( 'last_sync_date', '' );
        $result   = $client->pullReservations( $lastSync !== '' ? $lastSync : null );

        return $result['success'] ? $result['reservations'] : [];
    }

    public function confirmReservation( string $id ): bool {
        // Booking.com auto-confirms reservations at source; no explicit confirm
        // endpoint exists. Treat as a no-op success so downstream sync marks
        // the local reservation as acknowledged.
        return true;
    }

    public function cancelReservation( string $id, string $reason ): bool {
        // Cancellations originate from the guest on Booking.com; partners cannot
        // cancel reservations via the Connectivity API. Log and return true so
        // local state is updated without implying a remote action.
        $logger = Plugin::getInstance()->container()->get( Logger::class );
        $logger->info( 'Booking.com cancelReservation called (no-op; cancellations are guest-initiated).', [
            'external_id' => $id,
            'reason'      => $reason,
        ] );
        return true;
    }

    public function testConnection(): bool {
        $client = $this->client();
        if ( ! $client ) {
            return false;
        }

        $result = $client->testConnection();
        return (bool) ( $result['success'] ?? false );
    }

    public function getConfigFields(): array {
        return [
            [
                'key'      => 'hotel_id',
                'label'    => __( 'Hotel ID', 'nozule' ),
                'type'     => 'text',
                'required' => true,
            ],
            [
                'key'      => 'username',
                'label'    => __( 'Username', 'nozule' ),
                'type'     => 'text',
                'required' => true,
            ],
            [
                'key'      => 'password',
                'label'    => __( 'Password', 'nozule' ),
                'type'     => 'password',
                'required' => true,
            ],
            [
                'key'      => 'api_endpoint',
                'label'    => __( 'API Endpoint (optional)', 'nozule' ),
                'type'     => 'url',
                'required' => false,
            ],
            [
                'key'      => 'use_sandbox',
                'label'    => __( 'Use Sandbox Environment', 'nozule' ),
                'type'     => 'checkbox',
                'required' => false,
            ],
        ];
    }
}
