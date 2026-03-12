<?php

namespace Nozule\Modules\PayPal\Services;

use Nozule\Core\Logger;
use Nozule\Core\SettingsManager;

/**
 * Low-level PayPal REST API connector.
 *
 * Handles OAuth token acquisition and API calls to PayPal's Orders v2 API.
 * Supports both sandbox and live environments via settings toggle.
 */
class PayPalConnector {

	private const SANDBOX_URL = 'https://api-m.sandbox.paypal.com';
	private const LIVE_URL    = 'https://api-m.paypal.com';

	private SettingsManager $settings;
	private Logger $logger;
	private ?string $accessToken = null;

	public function __construct( SettingsManager $settings, Logger $logger ) {
		$this->settings = $settings;
		$this->logger   = $logger;
	}

	/**
	 * Check if PayPal is configured (credentials present).
	 */
	public function isConfigured(): bool {
		$clientId = $this->settings->get( 'integrations.paypal_client_id', '' );
		$secret   = $this->settings->get( 'integrations.paypal_secret', '' );

		return ! empty( $clientId ) && ! empty( $secret );
	}

	/**
	 * Get the PayPal API base URL for the current mode.
	 */
	public function getBaseUrl(): string {
		$mode = $this->settings->get( 'integrations.paypal_mode', 'sandbox' );

		return $mode === 'live' ? self::LIVE_URL : self::SANDBOX_URL;
	}

	/**
	 * Obtain an OAuth 2.0 access token from PayPal.
	 */
	public function getAccessToken(): ?string {
		if ( $this->accessToken ) {
			return $this->accessToken;
		}

		$clientId = $this->settings->get( 'integrations.paypal_client_id', '' );
		$secret   = $this->settings->get( 'integrations.paypal_secret', '' );

		if ( empty( $clientId ) || empty( $secret ) ) {
			$this->logger->error( 'PayPal credentials not configured' );
			return null;
		}

		$response = wp_remote_post( $this->getBaseUrl() . '/v1/oauth2/token', [
			'headers' => [
				'Authorization' => 'Basic ' . base64_encode( $clientId . ':' . $secret ),
				'Content-Type'  => 'application/x-www-form-urlencoded',
			],
			'body'    => 'grant_type=client_credentials',
			'timeout' => 30,
		] );

		if ( is_wp_error( $response ) ) {
			$this->logger->error( 'PayPal OAuth failed', [
				'error' => $response->get_error_message(),
			] );
			return null;
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( $code !== 200 || empty( $body['access_token'] ) ) {
			$this->logger->error( 'PayPal OAuth error response', [
				'status' => $code,
				'body'   => $body,
			] );
			return null;
		}

		$this->accessToken = $body['access_token'];

		return $this->accessToken;
	}

	/**
	 * Create a PayPal order (Orders v2 API).
	 *
	 * @param float  $amount      Order amount.
	 * @param string $currency    ISO currency code.
	 * @param string $description Order description.
	 * @param string $returnUrl   URL PayPal redirects to after approval.
	 * @param string $cancelUrl   URL PayPal redirects to on cancellation.
	 * @param array  $metadata    Custom metadata (booking_id, etc.).
	 * @return array|null Order data from PayPal, or null on failure.
	 */
	public function createOrder(
		float $amount,
		string $currency,
		string $description,
		string $returnUrl,
		string $cancelUrl,
		array $metadata = []
	): ?array {
		$token = $this->getAccessToken();
		if ( ! $token ) {
			return null;
		}

		$payload = [
			'intent'         => 'CAPTURE',
			'purchase_units' => [
				[
					'description'    => substr( $description, 0, 127 ),
					'custom_id'      => wp_json_encode( $metadata ),
					'amount'         => [
						'currency_code' => strtoupper( $currency ),
						'value'         => number_format( $amount, 2, '.', '' ),
					],
				],
			],
			'application_context' => [
				'return_url' => $returnUrl,
				'cancel_url' => $cancelUrl,
				'brand_name' => get_bloginfo( 'name' ),
				'user_action' => 'PAY_NOW',
			],
		];

		$response = wp_remote_post( $this->getBaseUrl() . '/v2/checkout/orders', [
			'headers' => [
				'Authorization' => 'Bearer ' . $token,
				'Content-Type'  => 'application/json',
			],
			'body'    => wp_json_encode( $payload ),
			'timeout' => 30,
		] );

		if ( is_wp_error( $response ) ) {
			$this->logger->error( 'PayPal create order failed', [
				'error' => $response->get_error_message(),
			] );
			return null;
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( $code !== 201 ) {
			$this->logger->error( 'PayPal create order error', [
				'status' => $code,
				'body'   => $body,
			] );
			return null;
		}

		return $body;
	}

	/**
	 * Capture an approved PayPal order.
	 *
	 * @param string $orderId PayPal order ID.
	 * @return array|null Capture data, or null on failure.
	 */
	public function captureOrder( string $orderId ): ?array {
		$token = $this->getAccessToken();
		if ( ! $token ) {
			return null;
		}

		$response = wp_remote_post( $this->getBaseUrl() . '/v2/checkout/orders/' . $orderId . '/capture', [
			'headers' => [
				'Authorization' => 'Bearer ' . $token,
				'Content-Type'  => 'application/json',
			],
			'body'    => '{}',
			'timeout' => 30,
		] );

		if ( is_wp_error( $response ) ) {
			$this->logger->error( 'PayPal capture order failed', [
				'error' => $response->get_error_message(),
			] );
			return null;
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( ! in_array( $code, [ 200, 201 ], true ) ) {
			$this->logger->error( 'PayPal capture order error', [
				'status' => $code,
				'body'   => $body,
			] );
			return null;
		}

		return $body;
	}

	/**
	 * Get order details from PayPal.
	 *
	 * @param string $orderId PayPal order ID.
	 * @return array|null Order data, or null on failure.
	 */
	public function getOrder( string $orderId ): ?array {
		$token = $this->getAccessToken();
		if ( ! $token ) {
			return null;
		}

		$response = wp_remote_get( $this->getBaseUrl() . '/v2/checkout/orders/' . $orderId, [
			'headers' => [
				'Authorization' => 'Bearer ' . $token,
				'Content-Type'  => 'application/json',
			],
			'timeout' => 30,
		] );

		if ( is_wp_error( $response ) ) {
			return null;
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		return $code === 200 ? $body : null;
	}

	/**
	 * Verify a PayPal webhook signature.
	 *
	 * @param array  $headers     HTTP headers from the webhook request.
	 * @param string $body        Raw request body.
	 * @param string $webhookId   PayPal webhook ID from settings.
	 * @return bool True if signature is valid.
	 */
	public function verifyWebhookSignature( array $headers, string $body, string $webhookId ): bool {
		$token = $this->getAccessToken();
		if ( ! $token ) {
			return false;
		}

		$payload = [
			'auth_algo'         => $headers['PAYPAL-AUTH-ALGO'] ?? '',
			'cert_url'          => $headers['PAYPAL-CERT-URL'] ?? '',
			'transmission_id'   => $headers['PAYPAL-TRANSMISSION-ID'] ?? '',
			'transmission_sig'  => $headers['PAYPAL-TRANSMISSION-SIG'] ?? '',
			'transmission_time' => $headers['PAYPAL-TRANSMISSION-TIME'] ?? '',
			'webhook_id'        => $webhookId,
			'webhook_event'     => json_decode( $body, true ),
		];

		$response = wp_remote_post( $this->getBaseUrl() . '/v1/notifications/verify-webhook-signature', [
			'headers' => [
				'Authorization' => 'Bearer ' . $token,
				'Content-Type'  => 'application/json',
			],
			'body'    => wp_json_encode( $payload ),
			'timeout' => 30,
		] );

		if ( is_wp_error( $response ) ) {
			$this->logger->error( 'PayPal webhook verification failed', [
				'error' => $response->get_error_message(),
			] );
			return false;
		}

		$result = json_decode( wp_remote_retrieve_body( $response ), true );

		return ( $result['verification_status'] ?? '' ) === 'SUCCESS';
	}
}
