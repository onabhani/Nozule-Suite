<?php

namespace Nozule\Modules\Integrations\Services;

use Nozule\Core\SettingsManager;

/**
 * Orchestrates dispatching plugin events to configured external systems.
 */
class IntegrationService {

	private SettingsManager $settings;
	private OdooConnector $odoo;
	private WebhookConnector $webhook;

	public function __construct(
		SettingsManager $settings,
		OdooConnector $odoo,
		WebhookConnector $webhook
	) {
		$this->settings = $settings;
		$this->odoo     = $odoo;
		$this->webhook  = $webhook;
	}

	/**
	 * Subscribe to all relevant plugin events.
	 */
	public function subscribeToEvents(): void {
		if ( ! $this->isEnabled() ) {
			return;
		}

		// Booking events.
		add_action( 'nozule/booking/created',      [ $this, 'onBookingCreated' ], 20, 2 );
		add_action( 'nozule/booking/confirmed',     [ $this, 'onBookingConfirmed' ], 20 );
		add_action( 'nozule/booking/cancelled',     [ $this, 'onBookingCancelled' ], 20, 2 );
		add_action( 'nozule/booking/checked_in',    [ $this, 'onBookingCheckedIn' ], 20, 2 );
		add_action( 'nozule/booking/checked_out',   [ $this, 'onBookingCheckedOut' ], 20 );
		add_action( 'nozule/booking/payment_added', [ $this, 'onPaymentAdded' ], 20, 2 );

		// Guest events.
		add_action( 'nozule/guests/created', [ $this, 'onGuestCreated' ], 20 );
		add_action( 'nozule/guests/updated', [ $this, 'onGuestUpdated' ], 20 );
	}

	/**
	 * Check if integrations are globally enabled.
	 */
	public function isEnabled(): bool {
		return (bool) $this->settings->get( 'integrations.enabled', false );
	}

	/**
	 * Get the active provider name.
	 */
	public function getProvider(): string {
		return $this->settings->get( 'integrations.provider', 'none' );
	}

	// ── Event Handlers ──────────────────────────────────────────────

	public function onBookingCreated( $booking, $data = [] ): void {
		if ( ! $this->shouldSync( 'sync_bookings' ) ) {
			return;
		}

		$payload = [
			'event'   => 'booking.created',
			'booking' => is_object( $booking ) ? $booking->toArray() : $booking,
		];

		$this->dispatch( $payload );
	}

	public function onBookingConfirmed( $bookingId ): void {
		if ( ! $this->shouldSync( 'sync_bookings' ) ) {
			return;
		}

		$this->dispatch( [
			'event'      => 'booking.confirmed',
			'booking_id' => $bookingId,
		] );
	}

	public function onBookingCancelled( $bookingId, $reason = '' ): void {
		if ( ! $this->shouldSync( 'sync_bookings' ) ) {
			return;
		}

		$this->dispatch( [
			'event'      => 'booking.cancelled',
			'booking_id' => $bookingId,
			'reason'     => $reason,
		] );
	}

	public function onBookingCheckedIn( $bookingId, $roomId = null ): void {
		if ( ! $this->shouldSync( 'sync_bookings' ) ) {
			return;
		}

		$this->dispatch( [
			'event'      => 'booking.checked_in',
			'booking_id' => $bookingId,
			'room_id'    => $roomId,
		] );
	}

	public function onBookingCheckedOut( $bookingId ): void {
		if ( ! $this->shouldSync( 'sync_bookings' ) ) {
			return;
		}

		$this->dispatch( [
			'event'      => 'booking.checked_out',
			'booking_id' => $bookingId,
		] );
	}

	public function onPaymentAdded( $bookingId, $payment = null ): void {
		if ( ! $this->shouldSync( 'sync_invoices' ) ) {
			return;
		}

		$this->dispatch( [
			'event'      => 'payment.added',
			'booking_id' => $bookingId,
			'payment'    => is_object( $payment ) ? $payment->toArray() : $payment,
		] );
	}

	public function onGuestCreated( $guest ): void {
		if ( ! $this->shouldSync( 'sync_contacts' ) ) {
			return;
		}

		$this->dispatch( [
			'event' => 'guest.created',
			'guest' => is_object( $guest ) ? $guest->toArray() : $guest,
		] );
	}

	public function onGuestUpdated( $guest ): void {
		if ( ! $this->shouldSync( 'sync_contacts' ) ) {
			return;
		}

		$this->dispatch( [
			'event' => 'guest.updated',
			'guest' => is_object( $guest ) ? $guest->toArray() : $guest,
		] );
	}

	// ── Dispatch ────────────────────────────────────────────────────

	/**
	 * Check if a specific sync category is enabled.
	 */
	private function shouldSync( string $key ): bool {
		return $this->isEnabled() && (bool) $this->settings->get( 'integrations.' . $key, false );
	}

	/**
	 * Dispatch payload to the active provider.
	 */
	private function dispatch( array $payload ): void {
		$payload['timestamp'] = current_time( 'c' );
		$payload['site_url']  = home_url();

		$provider = $this->getProvider();

		try {
			switch ( $provider ) {
				case 'odoo':
					$this->odoo->send( $payload );
					break;

				case 'webhook':
					$this->webhook->send( $payload );
					break;

				default:
					// No provider configured — skip silently.
					break;
			}
		} catch ( \Throwable $e ) {
			// Log the error but never break the main flow.
			do_action( 'nozule/log', 'error', 'Integration dispatch failed', [
				'provider' => $provider,
				'event'    => $payload['event'] ?? 'unknown',
				'error'    => $e->getMessage(),
			] );
		}
	}
}
