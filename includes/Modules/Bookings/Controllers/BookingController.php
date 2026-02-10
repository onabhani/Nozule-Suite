<?php

namespace Venezia\Modules\Bookings\Controllers;

use Venezia\Modules\Bookings\Exceptions\NoAvailabilityException;
use Venezia\Modules\Bookings\Repositories\BookingRepository;
use Venezia\Modules\Bookings\Services\BookingService;

/**
 * REST controller for public-facing booking operations.
 *
 * Route namespace: venezia/v1
 */
class BookingController {

	private BookingService $service;
	private BookingRepository $repository;

	private const NAMESPACE = 'venezia/v1';

	public function __construct( BookingService $service, BookingRepository $repository ) {
		$this->service    = $service;
		$this->repository = $repository;
	}

	/**
	 * Register routes.
	 */
	public function registerRoutes(): void {
		// POST /bookings -- create a new booking (public).
		register_rest_route( self::NAMESPACE, '/bookings', [
			'methods'             => \WP_REST_Server::CREATABLE,
			'callback'            => [ $this, 'create' ],
			'permission_callback' => '__return_true',
			'args'                => $this->getCreateArgs(),
		] );

		// GET /bookings/lookup -- lookup by booking number + email (public).
		register_rest_route( self::NAMESPACE, '/bookings/lookup', [
			'methods'             => \WP_REST_Server::READABLE,
			'callback'            => [ $this, 'lookup' ],
			'permission_callback' => '__return_true',
			'args'                => [
				'booking_number' => [
					'required'          => true,
					'type'              => 'string',
					'sanitize_callback' => 'sanitize_text_field',
				],
				'email' => [
					'required'          => true,
					'type'              => 'string',
					'sanitize_callback' => 'sanitize_email',
				],
			],
		] );

		// POST /bookings/cancel -- cancel a booking (public, authenticated by booking number + email).
		register_rest_route( self::NAMESPACE, '/bookings/cancel', [
			'methods'             => \WP_REST_Server::CREATABLE,
			'callback'            => [ $this, 'cancel' ],
			'permission_callback' => '__return_true',
			'args'                => [
				'booking_number' => [
					'required'          => true,
					'type'              => 'string',
					'sanitize_callback' => 'sanitize_text_field',
				],
				'email' => [
					'required'          => true,
					'type'              => 'string',
					'sanitize_callback' => 'sanitize_email',
				],
				'reason' => [
					'type'              => 'string',
					'default'           => '',
					'sanitize_callback' => 'sanitize_textarea_field',
				],
			],
		] );
	}

	/**
	 * POST /bookings
	 *
	 * Create a new booking from the public booking form.
	 */
	public function create( \WP_REST_Request $request ): \WP_REST_Response {
		try {
			$booking = $this->service->createBooking( $request->get_params() );

			return new \WP_REST_Response( [
				'success' => true,
				'data'    => [
					'booking_number' => $booking->booking_number,
					'status'         => $booking->status,
					'total_amount'   => $booking->total_amount,
					'check_in'       => $booking->check_in,
					'check_out'      => $booking->check_out,
					'nights'         => $booking->nights,
				],
			], 201 );
		} catch ( \InvalidArgumentException $e ) {
			return new \WP_REST_Response( [
				'success' => false,
				'message' => $e->getMessage(),
			], 400 );
		} catch ( NoAvailabilityException $e ) {
			return new \WP_REST_Response( [
				'success' => false,
				'message' => $e->getMessage(),
			], 409 );
		} catch ( \Throwable $e ) {
			return new \WP_REST_Response( [
				'success' => false,
				'message' => __( 'An unexpected error occurred. Please try again.', 'venezia-hotel' ),
			], 500 );
		}
	}

	/**
	 * GET /bookings/lookup
	 *
	 * Look up a booking by its booking number and the guest's email.
	 * This provides a secure, public-facing way for guests to retrieve
	 * their booking information without authentication.
	 */
	public function lookup( \WP_REST_Request $request ): \WP_REST_Response {
		$bookingNumber = $request->get_param( 'booking_number' );
		$email         = $request->get_param( 'email' );

		$booking = $this->repository->findByBookingNumber( $bookingNumber );

		if ( ! $booking ) {
			return new \WP_REST_Response( [
				'success' => false,
				'message' => __( 'Booking not found.', 'venezia-hotel' ),
			], 404 );
		}

		// Verify guest email matches.
		$guest = $this->getGuestForBooking( $booking );

		if ( ! $guest || strtolower( $guest->email ) !== strtolower( $email ) ) {
			return new \WP_REST_Response( [
				'success' => false,
				'message' => __( 'Booking not found.', 'venezia-hotel' ),
			], 404 );
		}

		return new \WP_REST_Response( [
			'success' => true,
			'data'    => [
				'booking_number' => $booking->booking_number,
				'status'         => $booking->status,
				'check_in'       => $booking->check_in,
				'check_out'      => $booking->check_out,
				'nights'         => $booking->nights,
				'adults'         => $booking->adults,
				'children'       => $booking->children,
				'total_amount'   => $booking->total_amount,
				'paid_amount'    => $booking->paid_amount,
				'balance_due'    => $booking->balance_due,
				'currency'       => $booking->currency,
				'guest_name'     => $guest->full_name,
			],
		], 200 );
	}

	/**
	 * POST /bookings/cancel
	 *
	 * Allow a guest to cancel their own booking using booking number + email
	 * as a form of authentication.
	 */
	public function cancel( \WP_REST_Request $request ): \WP_REST_Response {
		$bookingNumber = $request->get_param( 'booking_number' );
		$email         = $request->get_param( 'email' );
		$reason        = $request->get_param( 'reason' );

		$booking = $this->repository->findByBookingNumber( $bookingNumber );

		if ( ! $booking ) {
			return new \WP_REST_Response( [
				'success' => false,
				'message' => __( 'Booking not found.', 'venezia-hotel' ),
			], 404 );
		}

		// Verify guest email.
		$guest = $this->getGuestForBooking( $booking );

		if ( ! $guest || strtolower( $guest->email ) !== strtolower( $email ) ) {
			return new \WP_REST_Response( [
				'success' => false,
				'message' => __( 'Booking not found.', 'venezia-hotel' ),
			], 404 );
		}

		try {
			$updated = $this->service->cancelBooking( $booking->id, $reason );

			return new \WP_REST_Response( [
				'success' => true,
				'data'    => [
					'booking_number' => $updated->booking_number,
					'status'         => $updated->status,
				],
			], 200 );
		} catch ( \Venezia\Modules\Bookings\Exceptions\InvalidStateException $e ) {
			return new \WP_REST_Response( [
				'success' => false,
				'message' => $e->getMessage(),
			], 409 );
		} catch ( \Throwable $e ) {
			return new \WP_REST_Response( [
				'success' => false,
				'message' => __( 'An unexpected error occurred. Please try again.', 'venezia-hotel' ),
			], 500 );
		}
	}

	// ── Helpers ─────────────────────────────────────────────────────

	/**
	 * Retrieve the guest associated with a booking.
	 *
	 * @return \Venezia\Modules\Guests\Models\Guest|null
	 */
	private function getGuestForBooking( $booking ) {
		if ( ! $booking->guest_id ) {
			return null;
		}

		/** @var \Venezia\Modules\Guests\Repositories\GuestRepository $guestRepo */
		$guestRepo = apply_filters( 'venezia/container/get', null, \Venezia\Modules\Guests\Repositories\GuestRepository::class );

		if ( $guestRepo ) {
			return $guestRepo->find( $booking->guest_id );
		}

		return null;
	}

	/**
	 * Argument definitions for the create endpoint.
	 */
	private function getCreateArgs(): array {
		return [
			'room_type_id' => [
				'required'          => true,
				'type'              => 'integer',
				'sanitize_callback' => 'absint',
			],
			'check_in' => [
				'required'          => true,
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			],
			'check_out' => [
				'required'          => true,
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			],
			'adults' => [
				'required'          => true,
				'type'              => 'integer',
				'sanitize_callback' => 'absint',
			],
			'children' => [
				'type'              => 'integer',
				'default'           => 0,
				'sanitize_callback' => 'absint',
			],
			'guest_email' => [
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_email',
			],
			'guest_first_name' => [
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			],
			'guest_last_name' => [
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			],
			'guest_phone' => [
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			],
			'source' => [
				'type'              => 'string',
				'default'           => 'website',
				'sanitize_callback' => 'sanitize_text_field',
			],
			'special_requests' => [
				'type'              => 'string',
				'default'           => '',
				'sanitize_callback' => 'sanitize_textarea_field',
			],
		];
	}
}
