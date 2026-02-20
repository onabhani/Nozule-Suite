<?php

namespace Nozule\Modules\Reviews\Controllers;

use Nozule\Modules\Reviews\Models\ReviewRequest;
use Nozule\Modules\Reviews\Repositories\ReviewRepository;
use Nozule\Modules\Reviews\Services\ReviewService;

/**
 * REST controller for review solicitation and reputation dashboard.
 *
 * Route namespace: nozule/v1
 */
class ReviewController {

	private ReviewService $service;
	private ReviewRepository $repo;

	private const NAMESPACE = 'nozule/v1';

	public function __construct(
		ReviewService $service,
		ReviewRepository $repo
	) {
		$this->service = $service;
		$this->repo    = $repo;
	}

	/**
	 * Register REST routes.
	 */
	public function registerRoutes(): void {
		$admin_perm = [ $this, 'checkAdminPermission' ];

		// GET /admin/reviews/stats — dashboard stats.
		register_rest_route( self::NAMESPACE, '/admin/reviews/stats', [
			'methods'             => \WP_REST_Server::READABLE,
			'callback'            => [ $this, 'stats' ],
			'permission_callback' => $admin_perm,
		] );

		// GET /admin/reviews/requests — list review requests.
		register_rest_route( self::NAMESPACE, '/admin/reviews/requests', [
			'methods'             => \WP_REST_Server::READABLE,
			'callback'            => [ $this, 'listRequests' ],
			'permission_callback' => $admin_perm,
			'args'                => $this->getListArgs(),
		] );

		// GET /admin/reviews/settings — get all settings.
		register_rest_route( self::NAMESPACE, '/admin/reviews/settings', [
			'methods'             => \WP_REST_Server::READABLE,
			'callback'            => [ $this, 'getSettings' ],
			'permission_callback' => $admin_perm,
		] );

		// POST /admin/reviews/settings — update settings.
		register_rest_route( self::NAMESPACE, '/admin/reviews/settings', [
			'methods'             => \WP_REST_Server::CREATABLE,
			'callback'            => [ $this, 'updateSettings' ],
			'permission_callback' => $admin_perm,
		] );

		// GET /reviews/track/(?P<id>\d+) — public tracking pixel/redirect (no auth).
		register_rest_route( self::NAMESPACE, '/reviews/track/(?P<id>\d+)', [
			'methods'             => \WP_REST_Server::READABLE,
			'callback'            => [ $this, 'trackClick' ],
			'permission_callback' => '__return_true',
		] );
	}

	// ── Permission Callbacks ────────────────────────────────────────

	/**
	 * Permission check: current user has manage_options capability.
	 */
	public function checkAdminPermission(): bool {
		return current_user_can( 'manage_options' );
	}

	// ── Endpoints ───────────────────────────────────────────────────

	/**
	 * GET /admin/reviews/stats
	 *
	 * Return reputation dashboard statistics.
	 */
	public function stats( \WP_REST_Request $request ): \WP_REST_Response {
		$stats = $this->service->getStats();

		return new \WP_REST_Response( [
			'success' => true,
			'data'    => $stats,
		], 200 );
	}

	/**
	 * GET /admin/reviews/requests
	 *
	 * List review requests with pagination and filtering.
	 */
	public function listRequests( \WP_REST_Request $request ): \WP_REST_Response {
		$result = $this->repo->list( [
			'status'   => $request->get_param( 'status' ) ?? '',
			'search'   => $request->get_param( 'search' ) ?? '',
			'orderby'  => $request->get_param( 'orderby' ) ?? 'created_at',
			'order'    => $request->get_param( 'order' ) ?? 'DESC',
			'per_page' => (int) ( $request->get_param( 'per_page' ) ?? 20 ),
			'page'     => (int) ( $request->get_param( 'page' ) ?? 1 ),
		] );

		return new \WP_REST_Response( [
			'success' => true,
			'data'    => array_map( function ( ReviewRequest $r ) {
				return $r->toArray();
			}, $result['items'] ),
			'meta'    => [
				'total' => $result['total'],
				'pages' => $result['pages'],
			],
		], 200 );
	}

	/**
	 * GET /admin/reviews/settings
	 *
	 * Get all review settings.
	 */
	public function getSettings( \WP_REST_Request $request ): \WP_REST_Response {
		$settings = $this->service->getSettings();

		return new \WP_REST_Response( [
			'success' => true,
			'data'    => $settings,
		], 200 );
	}

	/**
	 * POST /admin/reviews/settings
	 *
	 * Update review settings.
	 */
	public function updateSettings( \WP_REST_Request $request ): \WP_REST_Response {
		$data = $this->sanitizeSettingsInput( $request->get_params() );

		if ( empty( $data ) ) {
			return new \WP_REST_Response( [
				'success' => false,
				'message' => __( 'No valid settings provided.', 'nozule' ),
			], 400 );
		}

		$this->service->updateSettings( $data );

		return new \WP_REST_Response( [
			'success' => true,
			'message' => __( 'Review settings updated.', 'nozule' ),
			'data'    => $this->service->getSettings(),
		], 200 );
	}

	/**
	 * GET /reviews/track/{id}
	 *
	 * Public endpoint: tracks a review link click.
	 * If a redirect URL is provided, redirects the user to the review platform.
	 * Otherwise returns a 1x1 transparent pixel (for open tracking).
	 */
	public function trackClick( \WP_REST_Request $request ): \WP_REST_Response {
		$id       = (int) $request->get_param( 'id' );
		$platform = sanitize_text_field( $request->get_param( 'platform' ) ?? 'google' );
		$redirect = $request->get_param( 'redirect' );

		// Track the click.
		$this->service->trackClick( $id, $platform );

		// If a redirect URL is provided, redirect to the review platform.
		if ( ! empty( $redirect ) ) {
			$redirectUrl = esc_url_raw( urldecode( $redirect ) );

			if ( ! empty( $redirectUrl ) ) {
				// Return a redirect response.
				$response = new \WP_REST_Response( null, 302 );
				$response->header( 'Location', $redirectUrl );
				$response->header( 'Cache-Control', 'no-cache, no-store, must-revalidate' );
				return $response;
			}
		}

		// No redirect — return a 1x1 transparent GIF pixel (for open tracking).
		$pixel = base64_decode( 'R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7' );

		// We must output the pixel directly since WP REST doesn't handle binary well.
		header( 'Content-Type: image/gif' );
		header( 'Content-Length: ' . strlen( $pixel ) );
		header( 'Cache-Control: no-cache, no-store, must-revalidate' );
		echo $pixel; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		exit;
	}

	// ── Helpers ─────────────────────────────────────────────────────

	/**
	 * Sanitize incoming settings fields.
	 *
	 * @return array<string, string>
	 */
	private function sanitizeSettingsInput( array $params ): array {
		$data = [];

		if ( isset( $params['google_review_url'] ) ) {
			$data['google_review_url'] = esc_url_raw( $params['google_review_url'] );
		}
		if ( isset( $params['tripadvisor_url'] ) ) {
			$data['tripadvisor_url'] = esc_url_raw( $params['tripadvisor_url'] );
		}
		if ( isset( $params['delay_hours'] ) ) {
			$data['delay_hours'] = (string) max( 0, (int) $params['delay_hours'] );
		}
		if ( isset( $params['enabled'] ) ) {
			$data['enabled'] = $params['enabled'] ? '1' : '0';
		}
		if ( isset( $params['email_subject'] ) ) {
			$data['email_subject'] = sanitize_text_field( $params['email_subject'] );
		}
		if ( isset( $params['email_subject_ar'] ) ) {
			$data['email_subject_ar'] = sanitize_text_field( $params['email_subject_ar'] );
		}
		if ( isset( $params['email_body'] ) ) {
			$data['email_body'] = wp_kses_post( $params['email_body'] );
		}
		if ( isset( $params['email_body_ar'] ) ) {
			$data['email_body_ar'] = wp_kses_post( $params['email_body_ar'] );
		}

		return $data;
	}

	/**
	 * Argument definitions for the review requests list endpoint.
	 */
	private function getListArgs(): array {
		return [
			'status' => [
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			],
			'search' => [
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			],
			'orderby' => [
				'type'              => 'string',
				'default'           => 'created_at',
				'sanitize_callback' => 'sanitize_text_field',
			],
			'order' => [
				'type'              => 'string',
				'default'           => 'DESC',
				'sanitize_callback' => 'sanitize_text_field',
			],
			'per_page' => [
				'type'              => 'integer',
				'default'           => 20,
				'sanitize_callback' => 'absint',
			],
			'page' => [
				'type'              => 'integer',
				'default'           => 1,
				'sanitize_callback' => 'absint',
			],
		];
	}
}
