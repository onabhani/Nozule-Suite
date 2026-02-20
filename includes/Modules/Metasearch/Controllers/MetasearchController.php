<?php

namespace Nozule\Modules\Metasearch\Controllers;

use Nozule\Modules\Metasearch\Services\GoogleHotelAdsService;

/**
 * REST API controller for the Metasearch / Google Hotel Ads module.
 *
 * Admin routes (require manage_options):
 *   GET  /admin/metasearch/settings      - Retrieve all GHA settings
 *   POST /admin/metasearch/settings      - Update GHA settings
 *   GET  /admin/metasearch/feed-preview  - Truncated XML feed (first 10 results)
 *   POST /admin/metasearch/test-feed     - Validate feed generation, return stats
 *
 * Public routes (no authentication):
 *   GET  /metasearch/google-hpa-feed     - Full XML price feed
 */
class MetasearchController {

	private const NAMESPACE  = 'nozule/v1';

	private GoogleHotelAdsService $service;

	public function __construct( GoogleHotelAdsService $service ) {
		$this->service = $service;
	}

	/**
	 * Register all REST routes for the metasearch module.
	 */
	public function registerRoutes(): void {
		// --- Admin routes --------------------------------------------------

		// GET /admin/metasearch/settings
		register_rest_route( self::NAMESPACE, '/admin/metasearch/settings', [
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ $this, 'getSettings' ],
				'permission_callback' => [ $this, 'checkAdminPermission' ],
			],
			[
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'updateSettings' ],
				'permission_callback' => [ $this, 'checkAdminPermission' ],
			],
		] );

		// GET /admin/metasearch/feed-preview
		register_rest_route( self::NAMESPACE, '/admin/metasearch/feed-preview', [
			'methods'             => \WP_REST_Server::READABLE,
			'callback'            => [ $this, 'feedPreview' ],
			'permission_callback' => [ $this, 'checkAdminPermission' ],
		] );

		// POST /admin/metasearch/test-feed
		register_rest_route( self::NAMESPACE, '/admin/metasearch/test-feed', [
			'methods'             => \WP_REST_Server::CREATABLE,
			'callback'            => [ $this, 'testFeed' ],
			'permission_callback' => [ $this, 'checkAdminPermission' ],
		] );

		// --- Public routes -------------------------------------------------

		// GET /metasearch/google-hpa-feed
		register_rest_route( self::NAMESPACE, '/metasearch/google-hpa-feed', [
			'methods'             => \WP_REST_Server::READABLE,
			'callback'            => [ $this, 'publicFeed' ],
			'permission_callback' => '__return_true',
		] );
	}

	// ------------------------------------------------------------------
	// Permission callback
	// ------------------------------------------------------------------

	/**
	 * Admin permission check: user must have manage_options.
	 */
	public function checkAdminPermission(): bool {
		return current_user_can( 'manage_options' );
	}

	// ------------------------------------------------------------------
	// Admin endpoints
	// ------------------------------------------------------------------

	/**
	 * GET /admin/metasearch/settings
	 *
	 * Returns all GHA / metasearch configuration values.
	 */
	public function getSettings( \WP_REST_Request $request ): \WP_REST_Response {
		return new \WP_REST_Response( [
			'settings' => $this->service->getSettings(),
		], 200 );
	}

	/**
	 * POST /admin/metasearch/settings
	 *
	 * Accepts a JSON body with setting key-value pairs and persists them.
	 */
	public function updateSettings( \WP_REST_Request $request ): \WP_REST_Response {
		$body = $request->get_json_params();

		if ( empty( $body ) || ! is_array( $body ) ) {
			return new \WP_REST_Response( [
				'message' => __( 'Request body must be a JSON object.', 'nozule' ),
			], 400 );
		}

		$sanitized = $this->sanitizeSettings( $body );
		$this->service->updateSettings( $sanitized );

		return new \WP_REST_Response( [
			'message'  => __( 'Metasearch settings updated.', 'nozule' ),
			'settings' => $this->service->getSettings(),
		], 200 );
	}

	/**
	 * GET /admin/metasearch/feed-preview
	 *
	 * Returns a truncated XML feed with at most 10 Result elements so admins
	 * can preview the output without generating the full 90-day feed.
	 */
	public function feedPreview( \WP_REST_Request $request ): \WP_REST_Response {
		if ( ! $this->service->isEnabled() ) {
			return new \WP_REST_Response( [
				'message' => __( 'Google Hotel Ads integration is not enabled.', 'nozule' ),
			], 400 );
		}

		$xml = $this->service->generatePriceFeedXml( 10 );

		return new \WP_REST_Response( [
			'xml' => $xml,
		], 200 );
	}

	/**
	 * POST /admin/metasearch/test-feed
	 *
	 * Validates that the feed can be generated and returns statistics about
	 * the data that would be included (room count, rate count, date range).
	 */
	public function testFeed( \WP_REST_Request $request ): \WP_REST_Response {
		if ( ! $this->service->isEnabled() ) {
			return new \WP_REST_Response( [
				'message' => __( 'Google Hotel Ads integration is not enabled.', 'nozule' ),
				'success' => false,
			], 400 );
		}

		try {
			$stats = $this->service->getFeedStats();

			return new \WP_REST_Response( [
				'message' => __( 'Feed generation test passed.', 'nozule' ),
				'success' => true,
				'stats'   => $stats,
			], 200 );
		} catch ( \Throwable $e ) {
			return new \WP_REST_Response( [
				'message' => sprintf(
					/* translators: %s: error message */
					__( 'Feed generation failed: %s', 'nozule' ),
					$e->getMessage()
				),
				'success' => false,
			], 500 );
		}
	}

	// ------------------------------------------------------------------
	// Public endpoint
	// ------------------------------------------------------------------

	/**
	 * GET /metasearch/google-hpa-feed
	 *
	 * Returns the full XML price feed with Content-Type: application/xml.
	 * Returns 503 when the integration is disabled.
	 */
	public function publicFeed( \WP_REST_Request $request ): \WP_REST_Response {
		if ( ! $this->service->isEnabled() ) {
			return new \WP_REST_Response( [
				'message' => __( 'Feed not available.', 'nozule' ),
			], 503 );
		}

		$xml = $this->service->generatePriceFeedXml();

		$response = new \WP_REST_Response( $xml, 200 );
		$response->header( 'Content-Type', 'application/xml; charset=UTF-8' );

		return $response;
	}

	// ------------------------------------------------------------------
	// Input sanitization
	// ------------------------------------------------------------------

	/**
	 * Sanitize the incoming settings payload.
	 *
	 * @param array<string, mixed> $data Raw request body.
	 * @return array<string, mixed> Sanitized data ready for the service.
	 */
	private function sanitizeSettings( array $data ): array {
		$sanitized = [];

		// Boolean fields.
		$boolKeys = [
			'enabled',
			'free_booking_links_enabled',
			'cpc_enabled',
		];

		foreach ( $boolKeys as $key ) {
			if ( array_key_exists( $key, $data ) ) {
				$sanitized[ $key ] = (bool) $data[ $key ];
			}
		}

		// String fields (sanitize_text_field).
		$stringKeys = [
			'hotel_id',
			'partner_key',
			'currency',
			'hotel_name',
			'hotel_name_ar',
			'hotel_address',
			'hotel_city',
			'hotel_country',
			'cpc_bid_type',
		];

		foreach ( $stringKeys as $key ) {
			if ( array_key_exists( $key, $data ) ) {
				$sanitized[ $key ] = sanitize_text_field( (string) $data[ $key ] );
			}
		}

		// URL fields.
		if ( array_key_exists( 'landing_page_url', $data ) ) {
			$sanitized['landing_page_url'] = esc_url_raw( (string) $data['landing_page_url'] );
		}

		// Float fields.
		if ( array_key_exists( 'cpc_budget', $data ) ) {
			$sanitized['cpc_budget'] = max( 0.0, (float) $data['cpc_budget'] );
		}

		// Validate cpc_bid_type is an allowed value.
		if ( isset( $sanitized['cpc_bid_type'] ) && ! in_array( $sanitized['cpc_bid_type'], [ 'manual', 'auto' ], true ) ) {
			$sanitized['cpc_bid_type'] = 'manual';
		}

		// Validate currency is a 3-letter code.
		if ( isset( $sanitized['currency'] ) ) {
			$sanitized['currency'] = strtoupper( substr( preg_replace( '/[^A-Za-z]/', '', $sanitized['currency'] ), 0, 3 ) );
			if ( strlen( $sanitized['currency'] ) !== 3 ) {
				$sanitized['currency'] = 'SYP';
			}
		}

		// Validate country is a 2-letter code.
		if ( isset( $sanitized['hotel_country'] ) ) {
			$sanitized['hotel_country'] = strtoupper( substr( preg_replace( '/[^A-Za-z]/', '', $sanitized['hotel_country'] ), 0, 2 ) );
			if ( strlen( $sanitized['hotel_country'] ) !== 2 ) {
				$sanitized['hotel_country'] = 'SY';
			}
		}

		return $sanitized;
	}
}
