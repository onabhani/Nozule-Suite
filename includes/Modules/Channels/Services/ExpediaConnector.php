<?php

namespace Nozule\Modules\Channels\Services;

use Nozule\Core\Logger;
use Nozule\Core\Plugin;
use Nozule\Modules\Channels\Models\SyncResult;

/**
 * Expedia (EQC) channel connector.
 *
 * Thin adapter that maps the AbstractChannelConnector contract to
 * ExpediaApiClient (which handles XML building, HTTP, and parsing).
 */
class ExpediaConnector extends AbstractChannelConnector {

    private ?ExpediaApiClient $client = null;

    public function getChannelName(): string {
        return 'expedia';
    }

    public function getChannelLabel(): string {
        return 'Expedia';
    }

    private function client(): ?ExpediaApiClient {
        if ( $this->client instanceof ExpediaApiClient ) {
            return $this->client;
        }

        $propertyId = (string) $this->getConfigValue( 'property_id', '' );
        $username   = (string) $this->getConfigValue( 'username', $this->getConfigValue( 'api_key', '' ) );
        $password   = (string) $this->getConfigValue( 'password', $this->getConfigValue( 'api_secret', '' ) );

        if ( $propertyId === '' || $username === '' || $password === '' ) {
            return null;
        }

        $logger = Plugin::getInstance()->container()->get( Logger::class );
        $client = new ExpediaApiClient( $logger );
        $client->setCredentials( $propertyId, $username, $password );

        $endpoint = (string) $this->getConfigValue( 'api_endpoint', '' );
        if ( $endpoint !== '' ) {
            $client->setBaseUrl( $endpoint );
        } elseif ( ! empty( $this->getConfigValue( 'use_sandbox', false ) ) ) {
            $client->setBaseUrl( ExpediaApiClient::SANDBOX_BASE_URL );
        }

        $this->client = $client;
        return $this->client;
    }

    public function pushAvailability( array $inventory ): SyncResult {
        $client = $this->client();
        if ( ! $client ) {
            return SyncResult::failure( __( 'Expedia credentials are not configured.', 'nozule' ) );
        }

        $result = $client->pushAvailability( $inventory );

        return $result['success']
            ? SyncResult::success( $result['message'], $result['records_processed'] )
            : SyncResult::failure( $result['message'] );
    }

    public function pushRates( array $rates ): SyncResult {
        $client = $this->client();
        if ( ! $client ) {
            return SyncResult::failure( __( 'Expedia credentials are not configured.', 'nozule' ) );
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
        $client = $this->client();
        if ( ! $client ) {
            return false;
        }
        return $client->confirmReservation( $id );
    }

    public function cancelReservation( string $id, string $reason ): bool {
        // Expedia cancellations are guest-initiated through their platform; partners
        // do not cancel via EQC. Log and no-op success so local state can reflect
        // the cancellation propagated through pullReservations().
        $logger = Plugin::getInstance()->container()->get( Logger::class );
        $logger->info( 'Expedia cancelReservation called (no-op; cancellations propagate via pullReservations).', [
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
                'key'      => 'property_id',
                'label'    => __( 'Property ID', 'nozule' ),
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
