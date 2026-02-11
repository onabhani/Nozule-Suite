<?php

namespace Venezia\Modules\Integrations\Services;

use Venezia\Core\SettingsManager;

/**
 * Odoo ERP connector via JSON-RPC 2.0.
 *
 * Supports authentication, partner (contact) creation/update,
 * invoice creation, and a test-connection endpoint.
 */
class OdooConnector {

	private SettingsManager $settings;

	/** @var int|null Cached UID from authentication. */
	private ?int $uid = null;

	public function __construct( SettingsManager $settings ) {
		$this->settings = $settings;
	}

	// ── Configuration ───────────────────────────────────────────────

	private function getUrl(): string {
		return rtrim( $this->settings->get( 'integrations.odoo_url', '' ), '/' );
	}

	private function getDatabase(): string {
		return $this->settings->get( 'integrations.odoo_database', '' );
	}

	private function getUsername(): string {
		return $this->settings->get( 'integrations.odoo_username', '' );
	}

	private function getApiKey(): string {
		return $this->settings->get( 'integrations.odoo_api_key', '' );
	}

	// ── Public Methods ──────────────────────────────────────────────

	/**
	 * Test the connection to Odoo.
	 *
	 * @return array{success: bool, message: string, version?: string}
	 */
	public function testConnection(): array {
		$url = $this->getUrl();

		if ( empty( $url ) ) {
			return [ 'success' => false, 'message' => __( 'Odoo URL is not configured.', 'venezia-hotel' ) ];
		}

		// 1. Check server version (no auth needed).
		$versionResult = $this->jsonRpc( $url . '/jsonrpc', 'call', [
			'service' => 'common',
			'method'  => 'version',
			'args'    => [],
		] );

		if ( is_wp_error( $versionResult ) ) {
			return [
				'success' => false,
				'message' => sprintf( __( 'Cannot reach Odoo: %s', 'venezia-hotel' ), $versionResult->get_error_message() ),
			];
		}

		$version = $versionResult['server_version'] ?? 'unknown';

		// 2. Try authentication.
		$uid = $this->authenticate();

		if ( $uid === null ) {
			return [
				'success' => false,
				'message' => __( 'Connected to Odoo but authentication failed. Check your credentials.', 'venezia-hotel' ),
				'version' => $version,
			];
		}

		return [
			'success' => true,
			'message' => sprintf( __( 'Connected to Odoo %s (UID: %d)', 'venezia-hotel' ), $version, $uid ),
			'version' => $version,
		];
	}

	/**
	 * Dispatch an event payload to Odoo.
	 */
	public function send( array $payload ): void {
		$event = $payload['event'] ?? '';

		switch ( $event ) {
			case 'guest.created':
			case 'guest.updated':
				$this->syncContact( $payload['guest'] ?? [] );
				break;

			case 'booking.created':
				$this->syncBooking( $payload['booking'] ?? [] );
				break;

			case 'payment.added':
				$this->syncPayment( $payload );
				break;

			default:
				// For other events, just log to Odoo note if desired.
				break;
		}
	}

	// ── Odoo Operations ─────────────────────────────────────────────

	/**
	 * Create or update a contact (res.partner) in Odoo.
	 */
	private function syncContact( array $guest ): void {
		$uid = $this->authenticate();
		if ( ! $uid ) {
			return;
		}

		$partnerData = [
			'name'    => trim( ( $guest['first_name'] ?? '' ) . ' ' . ( $guest['last_name'] ?? '' ) ),
			'email'   => $guest['email'] ?? '',
			'phone'   => $guest['phone'] ?? '',
			'street'  => $guest['address'] ?? '',
			'city'    => $guest['city'] ?? '',
			'country_id' => false,
			'comment' => sprintf( 'VHM Guest ID: %s', $guest['id'] ?? 'N/A' ),
			'customer_rank' => 1,
		];

		// Search for existing partner by email.
		$email = $guest['email'] ?? '';
		if ( ! empty( $email ) ) {
			$existing = $this->execute( 'res.partner', 'search', [
				[ [ 'email', '=', $email ] ],
			] );

			if ( ! empty( $existing ) && is_array( $existing ) ) {
				// Update existing.
				$this->execute( 'res.partner', 'write', [
					[ $existing[0] ],
					$partnerData,
				] );
				return;
			}
		}

		// Create new.
		$this->execute( 'res.partner', 'create', [ [ $partnerData ] ] );
	}

	/**
	 * Create a draft sale order in Odoo for a new booking.
	 */
	private function syncBooking( array $booking ): void {
		$uid = $this->authenticate();
		if ( ! $uid ) {
			return;
		}

		// Find the partner by looking up the guest email.
		$partnerId = $this->findPartnerByRef( $booking['guest_id'] ?? 0 );

		$orderData = [
			'partner_id'   => $partnerId ?: false,
			'client_order_ref' => $booking['booking_number'] ?? '',
			'note'         => sprintf(
				'Check-in: %s | Check-out: %s | Nights: %s',
				$booking['check_in'] ?? '',
				$booking['check_out'] ?? '',
				$booking['nights'] ?? ''
			),
		];

		$this->execute( 'sale.order', 'create', [ [ $orderData ] ] );
	}

	/**
	 * Register a payment in Odoo (creates a journal entry note).
	 */
	private function syncPayment( array $payload ): void {
		// A minimal implementation — full payment sync depends on Odoo accounting config.
		// For now, we dispatch via Odoo webhook/action if available.
		$uid = $this->authenticate();
		if ( ! $uid ) {
			return;
		}

		$payment = $payload['payment'] ?? [];

		// Log as a message on the partner.
		$bookingId = $payload['booking_id'] ?? 0;
		$partnerId = $this->findPartnerByRef( 0 );

		if ( $partnerId ) {
			$this->execute( 'mail.message', 'create', [ [
				[
					'model'   => 'res.partner',
					'res_id'  => $partnerId,
					'body'    => sprintf(
						'Payment received: %s %s (Booking #%s, Method: %s)',
						$payment['amount'] ?? 0,
						$payment['currency'] ?? '',
						$bookingId,
						$payment['method'] ?? ''
					),
					'message_type' => 'comment',
				],
			] ] );
		}
	}

	/**
	 * Find an Odoo partner by VHM guest reference in the comment field.
	 */
	private function findPartnerByRef( int $guestId ): ?int {
		if ( $guestId <= 0 ) {
			return null;
		}

		$result = $this->execute( 'res.partner', 'search', [
			[ [ 'comment', 'ilike', 'VHM Guest ID: ' . $guestId ] ],
		] );

		if ( ! empty( $result ) && is_array( $result ) ) {
			return (int) $result[0];
		}

		return null;
	}

	// ── JSON-RPC Transport ──────────────────────────────────────────

	/**
	 * Authenticate with Odoo and cache the UID.
	 */
	private function authenticate(): ?int {
		if ( $this->uid !== null ) {
			return $this->uid;
		}

		$url      = $this->getUrl();
		$database = $this->getDatabase();
		$username = $this->getUsername();
		$apiKey   = $this->getApiKey();

		if ( empty( $url ) || empty( $database ) || empty( $username ) || empty( $apiKey ) ) {
			return null;
		}

		$result = $this->jsonRpc( $url . '/jsonrpc', 'call', [
			'service' => 'common',
			'method'  => 'authenticate',
			'args'    => [ $database, $username, $apiKey, [] ],
		] );

		if ( is_wp_error( $result ) || ! is_int( $result ) || $result <= 0 ) {
			return null;
		}

		$this->uid = $result;
		return $this->uid;
	}

	/**
	 * Execute an Odoo model method via JSON-RPC.
	 *
	 * @return mixed
	 */
	private function execute( string $model, string $method, array $args = [], array $kwargs = [] ) {
		$uid = $this->authenticate();
		if ( ! $uid ) {
			return null;
		}

		return $this->jsonRpc( $this->getUrl() . '/jsonrpc', 'call', [
			'service' => 'object',
			'method'  => 'execute_kw',
			'args'    => [
				$this->getDatabase(),
				$uid,
				$this->getApiKey(),
				$model,
				$method,
				$args,
				(object) $kwargs,
			],
		] );
	}

	/**
	 * Send a JSON-RPC 2.0 request.
	 *
	 * @return mixed|\WP_Error
	 */
	private function jsonRpc( string $endpoint, string $method, array $params ) {
		$body = wp_json_encode( [
			'jsonrpc' => '2.0',
			'method'  => $method,
			'params'  => $params,
			'id'      => wp_rand( 1, 999999 ),
		] );

		$response = wp_remote_post( $endpoint, [
			'headers' => [ 'Content-Type' => 'application/json' ],
			'body'    => $body,
			'timeout' => 30,
		] );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$statusCode = wp_remote_retrieve_response_code( $response );
		if ( $statusCode < 200 || $statusCode >= 300 ) {
			return new \WP_Error(
				'odoo_http_error',
				sprintf( 'HTTP %d from Odoo', $statusCode )
			);
		}

		$decoded = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( isset( $decoded['error'] ) ) {
			$errMsg = $decoded['error']['data']['message']
				?? $decoded['error']['message']
				?? 'Unknown Odoo error';
			return new \WP_Error( 'odoo_rpc_error', $errMsg );
		}

		return $decoded['result'] ?? null;
	}
}
