<?php

namespace Nozule\Modules\PayPal\Services;

use Nozule\Core\EventDispatcher;
use Nozule\Core\Logger;
use Nozule\Core\SettingsManager;
use Nozule\Modules\Bookings\Models\Payment;
use Nozule\Modules\Bookings\Services\BookingService;

/**
 * High-level PayPal payment orchestration service.
 *
 * Handles the checkout flow:
 *   1. Create PayPal order (initiate checkout)
 *   2. Capture payment after guest approves on PayPal
 *   3. Record payment in Nozule via BookingService
 *   4. Handle webhook events for async payment updates
 */
class PayPalService {

	private PayPalConnector $connector;
	private BookingService $bookingService;
	private SettingsManager $settings;
	private EventDispatcher $events;
	private Logger $logger;

	public function __construct(
		PayPalConnector $connector,
		BookingService $bookingService,
		SettingsManager $settings,
		EventDispatcher $events,
		Logger $logger
	) {
		$this->connector      = $connector;
		$this->bookingService = $bookingService;
		$this->settings       = $settings;
		$this->events         = $events;
		$this->logger         = $logger;
	}

	/**
	 * Check if PayPal payments are enabled and configured.
	 */
	public function isAvailable(): bool {
		return (bool) $this->settings->get( 'integrations.paypal_enabled', false )
			&& $this->connector->isConfigured();
	}

	/**
	 * Get PayPal configuration (public-safe, no secrets).
	 */
	public function getPublicConfig(): array {
		return [
			'enabled'   => $this->isAvailable(),
			'mode'      => $this->settings->get( 'integrations.paypal_mode', 'sandbox' ),
			'currency'  => $this->settings->get( 'integrations.paypal_currency', 'USD' ),
			'client_id' => $this->settings->get( 'integrations.paypal_client_id', '' ),
		];
	}

	/**
	 * Get full PayPal settings (admin only).
	 */
	public function getSettings(): array {
		return [
			'enabled'    => (bool) $this->settings->get( 'integrations.paypal_enabled', false ),
			'mode'       => $this->settings->get( 'integrations.paypal_mode', 'sandbox' ),
			'currency'   => $this->settings->get( 'integrations.paypal_currency', 'USD' ),
			'client_id'  => $this->settings->get( 'integrations.paypal_client_id', '' ),
			'secret'     => $this->settings->get( 'integrations.paypal_secret', '' ) ? '••••••••' : '',
			'webhook_id' => $this->settings->get( 'integrations.paypal_webhook_id', '' ),
		];
	}

	/**
	 * Update PayPal settings.
	 */
	public function updateSettings( array $data ): array {
		$map = [
			'enabled'    => 'integrations.paypal_enabled',
			'mode'       => 'integrations.paypal_mode',
			'currency'   => 'integrations.paypal_currency',
			'client_id'  => 'integrations.paypal_client_id',
			'secret'     => 'integrations.paypal_secret',
			'webhook_id' => 'integrations.paypal_webhook_id',
		];

		foreach ( $map as $key => $settingKey ) {
			if ( array_key_exists( $key, $data ) ) {
				// Skip masked secret value.
				if ( $key === 'secret' && str_contains( (string) $data[ $key ], '••••' ) ) {
					continue;
				}
				$this->settings->set( $settingKey, $data[ $key ] );
			}
		}

		$this->logger->info( 'PayPal settings updated' );

		return $this->getSettings();
	}

	// =========================================================================
	// Checkout Flow
	// =========================================================================

	/**
	 * Initiate a PayPal checkout for a booking.
	 *
	 * Creates a PayPal order and returns the approval URL for the guest.
	 *
	 * @return array{ order_id: string, approval_url: string }|array{ errors: array }
	 */
	public function createCheckout( int $bookingId, string $returnUrl, string $cancelUrl ): array {
		if ( ! $this->isAvailable() ) {
			return [ 'errors' => [ 'general' => [ __( 'PayPal payments are not available.', 'nozule' ) ] ] ];
		}

		$booking = $this->bookingService->getBooking( $bookingId );
		if ( ! $booking ) {
			return [ 'errors' => [ 'booking_id' => [ __( 'Booking not found.', 'nozule' ) ] ] ];
		}

		$amountDue = round( (float) $booking->total_price - (float) $booking->paid_amount, 2 );
		if ( $amountDue <= 0 ) {
			return [ 'errors' => [ 'amount' => [ __( 'Booking is already fully paid.', 'nozule' ) ] ] ];
		}

		$currency    = $this->settings->get( 'integrations.paypal_currency', 'USD' );
		$description = sprintf(
			/* translators: %s: booking number */
			__( 'Booking %s', 'nozule' ),
			$booking->booking_number
		);

		$metadata = [
			'booking_id'     => $bookingId,
			'booking_number' => $booking->booking_number,
		];

		$order = $this->connector->createOrder(
			$amountDue,
			$currency,
			$description,
			$returnUrl,
			$cancelUrl,
			$metadata
		);

		if ( ! $order ) {
			return [ 'errors' => [ 'general' => [ __( 'Failed to create PayPal order.', 'nozule' ) ] ] ];
		}

		// Extract approval URL.
		$approvalUrl = '';
		foreach ( $order['links'] ?? [] as $link ) {
			if ( $link['rel'] === 'approve' ) {
				$approvalUrl = $link['href'];
				break;
			}
		}

		$this->logger->info( 'PayPal checkout created', [
			'booking_id'    => $bookingId,
			'paypal_order'  => $order['id'],
			'amount'        => $amountDue,
			'currency'      => $currency,
		] );

		$this->events->dispatch( 'paypal/checkout_created', $bookingId, $order['id'] );

		return [
			'order_id'     => $order['id'],
			'approval_url' => $approvalUrl,
			'amount'       => $amountDue,
			'currency'     => $currency,
		];
	}

	/**
	 * Capture a PayPal payment after guest approval.
	 *
	 * Records the payment in the Nozule system on success.
	 *
	 * @return Payment|array Payment on success, errors on failure.
	 */
	public function capturePayment( string $paypalOrderId ): Payment|array {
		if ( ! $this->isAvailable() ) {
			return [ 'general' => [ __( 'PayPal payments are not available.', 'nozule' ) ] ];
		}

		// Capture the payment on PayPal.
		$capture = $this->connector->captureOrder( $paypalOrderId );
		if ( ! $capture ) {
			return [ 'general' => [ __( 'Failed to capture PayPal payment.', 'nozule' ) ] ];
		}

		$status = $capture['status'] ?? '';
		if ( $status !== 'COMPLETED' ) {
			$this->logger->warning( 'PayPal capture not completed', [
				'paypal_order' => $paypalOrderId,
				'status'       => $status,
			] );
			return [ 'general' => [ sprintf( __( 'Payment not completed. Status: %s', 'nozule' ), $status ) ] ];
		}

		// Extract payment details.
		$purchaseUnit   = $capture['purchase_units'][0] ?? [];
		$captureDetails = $purchaseUnit['payments']['captures'][0] ?? [];
		$amount         = (float) ( $captureDetails['amount']['value'] ?? 0 );
		$currency       = $captureDetails['amount']['currency_code'] ?? 'USD';
		$transactionId  = $captureDetails['id'] ?? $paypalOrderId;

		// Resolve booking from custom_id metadata.
		$customId  = $purchaseUnit['custom_id'] ?? '';
		$metadata  = json_decode( $customId, true ) ?: [];
		$bookingId = (int) ( $metadata['booking_id'] ?? 0 );

		if ( ! $bookingId ) {
			$this->logger->error( 'PayPal capture missing booking_id in metadata', [
				'paypal_order' => $paypalOrderId,
				'custom_id'    => $customId,
			] );
			return [ 'booking_id' => [ __( 'Could not determine booking from PayPal order.', 'nozule' ) ] ];
		}

		// Record the payment.
		$payment = $this->bookingService->addPayment( $bookingId, [
			'amount'           => $amount,
			'currency'         => $currency,
			'method'           => 'online',
			'gateway'          => 'paypal',
			'status'           => Payment::STATUS_COMPLETED,
			'transaction_id'   => $transactionId,
			'gateway_response' => wp_json_encode( $capture ),
			'notes'            => sprintf( 'PayPal order %s', $paypalOrderId ),
		] );

		$this->logger->info( 'PayPal payment captured and recorded', [
			'booking_id'     => $bookingId,
			'paypal_order'   => $paypalOrderId,
			'transaction_id' => $transactionId,
			'amount'         => $amount,
			'payment_id'     => $payment->id,
		] );

		$this->events->dispatch( 'paypal/payment_captured', $bookingId, $payment );

		return $payment;
	}

	// =========================================================================
	// Webhook Handling
	// =========================================================================

	/**
	 * Process a PayPal webhook event.
	 *
	 * @param array  $headers Normalized HTTP headers.
	 * @param string $body    Raw request body.
	 * @return bool True if processed successfully.
	 */
	public function handleWebhook( array $headers, string $body ): bool {
		$webhookId = $this->settings->get( 'integrations.paypal_webhook_id', '' );

		// Verify signature if webhook ID is configured.
		if ( $webhookId && ! $this->connector->verifyWebhookSignature( $headers, $body, $webhookId ) ) {
			$this->logger->warning( 'PayPal webhook signature verification failed' );
			return false;
		}

		$event = json_decode( $body, true );
		if ( ! $event ) {
			return false;
		}

		$eventType = $event['event_type'] ?? '';
		$resource  = $event['resource'] ?? [];

		$this->logger->info( 'PayPal webhook received', [
			'event_type' => $eventType,
			'resource_id' => $resource['id'] ?? null,
		] );

		switch ( $eventType ) {
			case 'PAYMENT.CAPTURE.COMPLETED':
				return $this->handleCaptureCompleted( $resource );

			case 'PAYMENT.CAPTURE.REFUNDED':
				return $this->handleCaptureRefunded( $resource );

			case 'PAYMENT.CAPTURE.DENIED':
				return $this->handleCaptureDenied( $resource );

			default:
				$this->logger->info( 'PayPal webhook event type not handled', [ 'type' => $eventType ] );
				return true; // Acknowledge but don't process.
		}
	}

	private function handleCaptureCompleted( array $resource ): bool {
		// Payment was already recorded during capturePayment() in most flows.
		// This handles async/delayed captures or webhooks arriving before redirect.
		$this->events->dispatch( 'paypal/webhook_capture_completed', $resource );

		return true;
	}

	private function handleCaptureRefunded( array $resource ): bool {
		$this->logger->info( 'PayPal refund webhook received', [
			'capture_id' => $resource['id'] ?? null,
			'amount'     => $resource['amount']['value'] ?? null,
		] );

		$this->events->dispatch( 'paypal/webhook_refunded', $resource );

		return true;
	}

	private function handleCaptureDenied( array $resource ): bool {
		$this->logger->warning( 'PayPal capture denied', [
			'capture_id' => $resource['id'] ?? null,
		] );

		$this->events->dispatch( 'paypal/webhook_denied', $resource );

		return true;
	}
}
