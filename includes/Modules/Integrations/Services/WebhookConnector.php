<?php

namespace Venezia\Modules\Integrations\Services;

use Venezia\Core\SettingsManager;

/**
 * Generic webhook connector.
 *
 * Sends event payloads as JSON POST requests to a configured URL.
 * Optionally signs the payload with HMAC-SHA256 for verification.
 */
class WebhookConnector {

	private SettingsManager $settings;

	public function __construct( SettingsManager $settings ) {
		$this->settings = $settings;
	}

	/**
	 * Test the webhook endpoint.
	 *
	 * @return array{success: bool, message: string}
	 */
	public function testConnection(): array {
		$url = $this->getUrl();

		if ( empty( $url ) ) {
			return [ 'success' => false, 'message' => __( 'Webhook URL is not configured.', 'venezia-hotel' ) ];
		}

		$testPayload = [
			'event'     => 'connection.test',
			'timestamp' => current_time( 'c' ),
			'site_url'  => home_url(),
		];

		$result = $this->post( $url, $testPayload );

		if ( is_wp_error( $result ) ) {
			return [
				'success' => false,
				'message' => sprintf( __( 'Webhook test failed: %s', 'venezia-hotel' ), $result->get_error_message() ),
			];
		}

		$statusCode = wp_remote_retrieve_response_code( $result );

		if ( $statusCode >= 200 && $statusCode < 300 ) {
			return [
				'success' => true,
				'message' => sprintf( __( 'Webhook responded with HTTP %d', 'venezia-hotel' ), $statusCode ),
			];
		}

		return [
			'success' => false,
			'message' => sprintf( __( 'Webhook returned HTTP %d', 'venezia-hotel' ), $statusCode ),
		];
	}

	/**
	 * Send an event payload to the webhook URL.
	 */
	public function send( array $payload ): void {
		$url = $this->getUrl();

		if ( empty( $url ) ) {
			return;
		}

		$result = $this->post( $url, $payload );

		if ( is_wp_error( $result ) ) {
			do_action( 'venezia/log', 'error', 'Webhook dispatch failed', [
				'url'   => $url,
				'event' => $payload['event'] ?? 'unknown',
				'error' => $result->get_error_message(),
			] );
		}
	}

	// ── Internals ───────────────────────────────────────────────────

	private function getUrl(): string {
		return $this->settings->get( 'integrations.webhook_url', '' );
	}

	private function getSecret(): string {
		return $this->settings->get( 'integrations.webhook_secret', '' );
	}

	/**
	 * Send a signed POST request.
	 *
	 * @return array|\WP_Error
	 */
	private function post( string $url, array $payload ) {
		$body = wp_json_encode( $payload );

		$headers = [
			'Content-Type'        => 'application/json',
			'X-Venezia-Event'     => $payload['event'] ?? 'unknown',
			'X-Venezia-Timestamp' => $payload['timestamp'] ?? current_time( 'c' ),
		];

		// HMAC signature for payload verification.
		$secret = $this->getSecret();
		if ( ! empty( $secret ) ) {
			$headers['X-Venezia-Signature'] = 'sha256=' . hash_hmac( 'sha256', $body, $secret );
		}

		return wp_remote_post( $url, [
			'headers' => $headers,
			'body'    => $body,
			'timeout' => 15,
		] );
	}
}
