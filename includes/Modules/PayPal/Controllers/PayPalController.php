<?php

namespace Nozule\Modules\PayPal\Controllers;

use Nozule\Core\ResponseHelper;
use Nozule\Modules\Bookings\Models\Payment;
use Nozule\Modules\PayPal\Services\PayPalService;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * REST controller for PayPal payment operations.
 *
 * Public routes (guest checkout flow):
 *   POST   /nozule/v1/payments/paypal/checkout      Create PayPal order
 *   POST   /nozule/v1/payments/paypal/capture        Capture approved payment
 *   GET    /nozule/v1/payments/paypal/config          Get public PayPal config (client_id, mode)
 *
 * Admin routes:
 *   GET    /nozule/v1/admin/payments/paypal/settings  Get PayPal settings
 *   PUT    /nozule/v1/admin/payments/paypal/settings  Update PayPal settings
 *
 * Webhook route (no auth, verified by PayPal signature):
 *   POST   /nozule/v1/webhooks/paypal                 Receive PayPal webhook events
 */
class PayPalController {

	private PayPalService $service;

	public function __construct( PayPalService $service ) {
		$this->service = $service;
	}

	/**
	 * Register all PayPal REST routes.
	 */
	public function registerRoutes(): void {
		$namespace = 'nozule/v1';

		// Public: Get PayPal client config for JS SDK.
		register_rest_route( $namespace, '/payments/paypal/config', [
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => [ $this, 'getConfig' ],
			'permission_callback' => '__return_true',
		] );

		// Public: Create checkout order.
		register_rest_route( $namespace, '/payments/paypal/checkout', [
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => [ $this, 'createCheckout' ],
			'permission_callback' => '__return_true',
			'args'                => [
				'booking_id' => [
					'required'          => true,
					'validate_callback' => fn( $v ) => is_numeric( $v ) && $v > 0,
					'sanitize_callback' => 'absint',
				],
				'return_url' => [
					'required'          => true,
					'sanitize_callback' => 'esc_url_raw',
				],
				'cancel_url' => [
					'required'          => true,
					'sanitize_callback' => 'esc_url_raw',
				],
			],
		] );

		// Public: Capture payment after approval.
		register_rest_route( $namespace, '/payments/paypal/capture', [
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => [ $this, 'capturePayment' ],
			'permission_callback' => '__return_true',
			'args'                => [
				'order_id' => [
					'required'          => true,
					'sanitize_callback' => 'sanitize_text_field',
					'validate_callback' => fn( $v ) => ! empty( $v ),
				],
			],
		] );

		// Webhook: PayPal IPN.
		register_rest_route( $namespace, '/webhooks/paypal', [
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => [ $this, 'handleWebhook' ],
			'permission_callback' => '__return_true',
		] );

		// Admin: Settings.
		register_rest_route( $namespace, '/admin/payments/paypal/settings', [
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'getSettings' ],
				'permission_callback' => [ $this, 'checkAdminPermission' ],
			],
			[
				'methods'             => WP_REST_Server::EDITABLE,
				'callback'            => [ $this, 'updateSettings' ],
				'permission_callback' => [ $this, 'checkAdminPermission' ],
			],
		] );
	}

	/**
	 * GET /payments/paypal/config — Public PayPal configuration.
	 */
	public function getConfig( WP_REST_Request $request ): WP_REST_Response {
		return ResponseHelper::success( $this->service->getPublicConfig() );
	}

	/**
	 * POST /payments/paypal/checkout — Initiate PayPal checkout.
	 */
	public function createCheckout( WP_REST_Request $request ): WP_REST_Response {
		$bookingId = (int) $request->get_param( 'booking_id' );
		$returnUrl = $request->get_param( 'return_url' );
		$cancelUrl = $request->get_param( 'cancel_url' );

		$result = $this->service->createCheckout( $bookingId, $returnUrl, $cancelUrl );

		if ( isset( $result['errors'] ) ) {
			return ResponseHelper::error(
				__( 'Failed to create PayPal checkout.', 'nozule' ),
				422,
				$result['errors']
			);
		}

		return ResponseHelper::created( $result );
	}

	/**
	 * POST /payments/paypal/capture — Capture an approved payment.
	 */
	public function capturePayment( WP_REST_Request $request ): WP_REST_Response {
		$orderId = sanitize_text_field( $request->get_param( 'order_id' ) );

		$result = $this->service->capturePayment( $orderId );

		if ( $result instanceof Payment ) {
			return ResponseHelper::success(
				$result->toArray(),
				__( 'Payment captured successfully.', 'nozule' )
			);
		}

		return ResponseHelper::error( __( 'Failed to capture payment.', 'nozule' ), 422, $result );
	}

	/**
	 * POST /webhooks/paypal — Handle PayPal webhook events.
	 */
	public function handleWebhook( WP_REST_Request $request ): WP_REST_Response {
		// Extract PayPal headers.
		$headers = [];
		foreach ( $request->get_headers() as $key => $values ) {
			$normalized           = strtoupper( str_replace( '_', '-', $key ) );
			$headers[ $normalized ] = is_array( $values ) ? $values[0] : $values;
		}

		$body = $request->get_body();

		$success = $this->service->handleWebhook( $headers, $body );

		if ( $success ) {
			return new WP_REST_Response( null, 200 );
		}

		return new WP_REST_Response( [ 'error' => 'Webhook processing failed' ], 400 );
	}

	/**
	 * GET /admin/payments/paypal/settings
	 */
	public function getSettings( WP_REST_Request $request ): WP_REST_Response {
		return ResponseHelper::success( [ 'settings' => $this->service->getSettings() ] );
	}

	/**
	 * PUT /admin/payments/paypal/settings
	 */
	public function updateSettings( WP_REST_Request $request ): WP_REST_Response {
		$data = [];
		foreach ( [ 'enabled', 'mode', 'currency', 'client_id', 'secret', 'webhook_id' ] as $key ) {
			$value = $request->get_param( $key );
			if ( $value !== null ) {
				$data[ $key ] = $key === 'enabled' ? (bool) $value : sanitize_text_field( $value );
			}
		}

		$settings = $this->service->updateSettings( $data );

		return ResponseHelper::success(
			[ 'settings' => $settings ],
			__( 'PayPal settings updated.', 'nozule' )
		);
	}

	public function checkAdminPermission( WP_REST_Request $request ): bool {
		return current_user_can( 'manage_options' ) || current_user_can( 'nzl_admin' );
	}
}
