<?php

namespace Nozule\Modules\Bookings\Controllers;

use Nozule\Core\PropertyScope;
use Nozule\Modules\Bookings\Exceptions\InvalidStateException;
use Nozule\Modules\Bookings\Exceptions\NoAvailabilityException;
use Nozule\Modules\Bookings\Models\Booking;
use Nozule\Modules\Bookings\Repositories\BookingRepository;
use Nozule\Modules\Bookings\Repositories\PaymentRepository;
use Nozule\Modules\Bookings\Services\BookingService;
use Nozule\Modules\Bookings\Validators\BookingValidator;
use Nozule\Core\ResponseHelper;

/**
 * REST controller for admin booking management.
 *
 * All endpoints require the 'manage_options' capability.
 * Route namespace: nozule/v1
 */
class AdminBookingController {

	private BookingService $service;
	private BookingRepository $bookingRepository;
	private PaymentRepository $paymentRepository;
	private BookingValidator $validator;
	private PropertyScope $propertyScope;

	private const NAMESPACE = 'nozule/v1';

	public function __construct(
		BookingService $service,
		BookingRepository $bookingRepository,
		PaymentRepository $paymentRepository,
		BookingValidator $validator,
		PropertyScope $propertyScope
	) {
		$this->service           = $service;
		$this->bookingRepository = $bookingRepository;
		$this->paymentRepository = $paymentRepository;
		$this->validator         = $validator;
		$this->propertyScope     = $propertyScope;
	}

	/**
	 * Register admin REST routes.
	 */
	public function registerRoutes(): void {
		$admin_perm = [ $this, 'checkAdminPermission' ];

		// GET /admin/bookings -- list/search/filter.
		register_rest_route( self::NAMESPACE, '/admin/bookings', [
			'methods'             => \WP_REST_Server::READABLE,
			'callback'            => [ $this, 'index' ],
			'permission_callback' => $admin_perm,
			'args'                => $this->getListArgs(),
		] );

		// GET /admin/bookings/(?P<id>\d+) -- get single booking.
		register_rest_route( self::NAMESPACE, '/admin/bookings/(?P<id>\d+)', [
			'methods'             => \WP_REST_Server::READABLE,
			'callback'            => [ $this, 'show' ],
			'permission_callback' => $admin_perm,
		] );

		// PUT /admin/bookings/(?P<id>\d+) -- update a booking.
		register_rest_route( self::NAMESPACE, '/admin/bookings/(?P<id>\d+)', [
			'methods'             => 'PUT, PATCH',
			'callback'            => [ $this, 'update' ],
			'permission_callback' => $admin_perm,
		] );

		// POST /admin/bookings -- create a booking (admin).
		register_rest_route( self::NAMESPACE, '/admin/bookings', [
			'methods'             => \WP_REST_Server::CREATABLE,
			'callback'            => [ $this, 'store' ],
			'permission_callback' => $admin_perm,
		] );

		// POST /admin/bookings/(?P<id>\d+)/confirm
		register_rest_route( self::NAMESPACE, '/admin/bookings/(?P<id>\d+)/confirm', [
			'methods'             => \WP_REST_Server::CREATABLE,
			'callback'            => [ $this, 'confirm' ],
			'permission_callback' => $admin_perm,
		] );

		// POST /admin/bookings/(?P<id>\d+)/cancel
		register_rest_route( self::NAMESPACE, '/admin/bookings/(?P<id>\d+)/cancel', [
			'methods'             => \WP_REST_Server::CREATABLE,
			'callback'            => [ $this, 'cancel' ],
			'permission_callback' => $admin_perm,
			'args'                => [
				'reason' => [
					'type'              => 'string',
					'default'           => '',
					'sanitize_callback' => 'sanitize_textarea_field',
				],
			],
		] );

		// POST /admin/bookings/(?P<id>\d+)/check-in
		register_rest_route( self::NAMESPACE, '/admin/bookings/(?P<id>\d+)/check-in', [
			'methods'             => \WP_REST_Server::CREATABLE,
			'callback'            => [ $this, 'checkIn' ],
			'permission_callback' => $admin_perm,
			'args'                => [
				'room_id' => [
					'type'              => 'integer',
					'sanitize_callback' => 'absint',
				],
			],
		] );

		// POST /admin/bookings/(?P<id>\d+)/check-out
		register_rest_route( self::NAMESPACE, '/admin/bookings/(?P<id>\d+)/check-out', [
			'methods'             => \WP_REST_Server::CREATABLE,
			'callback'            => [ $this, 'checkOut' ],
			'permission_callback' => $admin_perm,
		] );

		// POST /admin/bookings/(?P<id>\d+)/assign-room
		register_rest_route( self::NAMESPACE, '/admin/bookings/(?P<id>\d+)/assign-room', [
			'methods'             => \WP_REST_Server::CREATABLE,
			'callback'            => [ $this, 'assignRoom' ],
			'permission_callback' => $admin_perm,
			'args'                => [
				'room_id' => [
					'required'          => true,
					'type'              => 'integer',
					'sanitize_callback' => 'absint',
				],
			],
		] );

		// POST /admin/bookings/(?P<id>\d+)/payments -- add payment.
		register_rest_route( self::NAMESPACE, '/admin/bookings/(?P<id>\d+)/payments', [
			'methods'             => \WP_REST_Server::CREATABLE,
			'callback'            => [ $this, 'addPayment' ],
			'permission_callback' => $admin_perm,
		] );

		// GET /admin/bookings/(?P<id>\d+)/payments -- list payments.
		register_rest_route( self::NAMESPACE, '/admin/bookings/(?P<id>\d+)/payments', [
			'methods'             => \WP_REST_Server::READABLE,
			'callback'            => [ $this, 'getPayments' ],
			'permission_callback' => $admin_perm,
		] );

		// GET /admin/bookings/(?P<id>\d+)/logs -- get audit logs.
		register_rest_route( self::NAMESPACE, '/admin/bookings/(?P<id>\d+)/logs', [
			'methods'             => \WP_REST_Server::READABLE,
			'callback'            => [ $this, 'getLogs' ],
			'permission_callback' => $admin_perm,
		] );
	}

	/**
	 * Permission check: current user has manage_options or nzl_manage_bookings.
	 */
	public function checkAdminPermission(): bool {
		return current_user_can( 'manage_options' ) || current_user_can( 'nzl_manage_bookings' );
	}

	// ── Endpoints ───────────────────────────────────────────────────

	/**
	 * GET /admin/bookings
	 *
	 * List bookings with filters, search, and pagination.
	 */
	public function index( \WP_REST_Request $request ): \WP_REST_Response {
		$repo   = $this->bookingRepository->scopeToProperty( $this->propertyScope->getActivePropertyId() );
		$result = $repo->list( [
			'status'    => $request->get_param( 'status' ) ?? '',
			'source'    => $request->get_param( 'source' ) ?? '',
			'date_from' => $request->get_param( 'date_from' ) ?? '',
			'date_to'   => $request->get_param( 'date_to' ) ?? '',
			'search'    => $request->get_param( 'search' ) ?? '',
			'orderby'   => $request->get_param( 'orderby' ) ?? 'created_at',
			'order'     => $request->get_param( 'order' ) ?? 'DESC',
			'per_page'  => (int) ( $request->get_param( 'per_page' ) ?? 20 ),
			'page'      => (int) ( $request->get_param( 'page' ) ?? 1 ),
		] );

		$page    = (int) ( $request->get_param( 'page' ) ?? 1 );
		$perPage = (int) ( $request->get_param( 'per_page' ) ?? 20 );

		return ResponseHelper::paginated(
			array_map( fn( Booking $b ) => $b->toArray(), $result['bookings'] ),
			$result['total'],
			$page,
			$perPage
		);
	}

	/**
	 * GET /admin/bookings/{id}
	 *
	 * Get a single booking with full details.
	 */
	public function show( \WP_REST_Request $request ): \WP_REST_Response {
		$booking = $this->bookingRepository->find( (int) $request->get_param( 'id' ) );

		if ( ! $booking ) {
			return ResponseHelper::notFound( __( 'Booking not found.', 'nozule' ) );
		}

		if ( ! $this->propertyScope->canAccessAllProperties() && ( $booking->property_id ?? null ) !== $this->propertyScope->getActivePropertyId() ) {
			return ResponseHelper::forbidden( __( 'Forbidden', 'nozule' ) );
		}

		return ResponseHelper::success( $booking->toArray() );
	}

	/**
	 * POST /admin/bookings
	 *
	 * Create a booking from the admin panel.
	 */
	public function store( \WP_REST_Request $request ): \WP_REST_Response {
		try {
			$booking = $this->service->createBooking( $request->get_params() );

			return ResponseHelper::created( $booking->toArray() );
		} catch ( \InvalidArgumentException $e ) {
			return ResponseHelper::error( $e->getMessage(), 400 );
		} catch ( NoAvailabilityException $e ) {
			return ResponseHelper::error( $e->getMessage(), 409 );
		} catch ( \Throwable $e ) {
			return ResponseHelper::error( __( 'Failed to create booking.', 'nozule' ), 500 );
		}
	}

	/**
	 * PUT /admin/bookings/{id}
	 *
	 * Update booking fields directly. For status transitions, use the
	 * dedicated confirm/cancel/check-in/check-out endpoints instead.
	 */
	public function update( \WP_REST_Request $request ): \WP_REST_Response {
		$id = (int) $request->get_param( 'id' );

		$booking = $this->bookingRepository->find( $id );
		if ( ! $booking ) {
			return ResponseHelper::notFound( __( 'Booking not found.', 'nozule' ) );
		}

		if ( ! $this->propertyScope->canAccessAllProperties() && ( $booking->property_id ?? null ) !== $this->propertyScope->getActivePropertyId() ) {
			return ResponseHelper::forbidden( __( 'Forbidden', 'nozule' ) );
		}

		$data = $request->get_params();

		// Remove non-updatable fields.
		unset(
			$data['id'],
			$data['booking_number'],
			$data['created_at'],
			$data['created_by']
		);

		if ( ! $this->validator->validateUpdate( $data ) ) {
			return ResponseHelper::error( implode( ' ', $this->validator->getAllErrors() ), 400 );
		}

		// Sanitize text fields.
		if ( isset( $data['special_requests'] ) ) {
			$data['special_requests'] = sanitize_textarea_field( $data['special_requests'] );
		}
		if ( isset( $data['internal_notes'] ) ) {
			$data['internal_notes'] = sanitize_textarea_field( $data['internal_notes'] );
		}

		// Recalculate nights if dates changed.
		$checkIn  = $data['check_in'] ?? $booking->check_in;
		$checkOut = $data['check_out'] ?? $booking->check_out;
		if ( isset( $data['check_in'] ) || isset( $data['check_out'] ) ) {
			$data['nights'] = (int) ( ( strtotime( $checkOut ) - strtotime( $checkIn ) ) / DAY_IN_SECONDS );
		}

		$success = $this->bookingRepository->update( $id, $data );

		if ( ! $success ) {
			return ResponseHelper::error( __( 'Failed to update booking.', 'nozule' ), 500 );
		}

		$this->bookingRepository->createLog( [
			'booking_id' => $id,
			'action'     => 'updated',
			'details'    => wp_json_encode( array_keys( $data ) ),
			'user_id'    => get_current_user_id() ?: null,
			'ip_address' => BookingService::getClientIP(),
		] );

		return ResponseHelper::success( $this->bookingRepository->findOrFail( $id )->toArray() );
	}

	/**
	 * POST /admin/bookings/{id}/confirm
	 */
	public function confirm( \WP_REST_Request $request ): \WP_REST_Response {
		try {
			$booking = $this->service->confirmBooking( (int) $request->get_param( 'id' ) );

			return ResponseHelper::success( $booking->toArray() );
		} catch ( InvalidStateException $e ) {
			return ResponseHelper::error( $e->getMessage(), 409 );
		} catch ( \Throwable $e ) {
			return ResponseHelper::error( __( 'Failed to confirm booking.', 'nozule' ), 500 );
		}
	}

	/**
	 * POST /admin/bookings/{id}/cancel
	 */
	public function cancel( \WP_REST_Request $request ): \WP_REST_Response {
		try {
			$booking = $this->service->cancelBooking(
				(int) $request->get_param( 'id' ),
				$request->get_param( 'reason' ) ?? ''
			);

			return ResponseHelper::success( $booking->toArray() );
		} catch ( InvalidStateException $e ) {
			return ResponseHelper::error( $e->getMessage(), 409 );
		} catch ( \Throwable $e ) {
			return ResponseHelper::error( __( 'Failed to cancel booking.', 'nozule' ), 500 );
		}
	}

	/**
	 * POST /admin/bookings/{id}/check-in
	 */
	public function checkIn( \WP_REST_Request $request ): \WP_REST_Response {
		try {
			$roomId  = $request->get_param( 'room_id' ) ? (int) $request->get_param( 'room_id' ) : null;
			$booking = $this->service->checkIn( (int) $request->get_param( 'id' ), $roomId );

			return ResponseHelper::success( $booking->toArray() );
		} catch ( InvalidStateException $e ) {
			return ResponseHelper::error( $e->getMessage(), 409 );
		} catch ( \Throwable $e ) {
			return ResponseHelper::error( __( 'Failed to check in.', 'nozule' ), 500 );
		}
	}

	/**
	 * POST /admin/bookings/{id}/check-out
	 */
	public function checkOut( \WP_REST_Request $request ): \WP_REST_Response {
		try {
			$booking = $this->service->checkOut( (int) $request->get_param( 'id' ) );

			return ResponseHelper::success( $booking->toArray() );
		} catch ( InvalidStateException $e ) {
			return ResponseHelper::error( $e->getMessage(), 409 );
		} catch ( \Throwable $e ) {
			return ResponseHelper::error( __( 'Failed to check out.', 'nozule' ), 500 );
		}
	}

	/**
	 * POST /admin/bookings/{id}/assign-room
	 *
	 * Assign a specific physical room to a booking.
	 */
	public function assignRoom( \WP_REST_Request $request ): \WP_REST_Response {
		$id     = (int) $request->get_param( 'id' );
		$roomId = (int) $request->get_param( 'room_id' );

		$booking = $this->bookingRepository->find( $id );
		if ( ! $booking ) {
			return ResponseHelper::notFound( __( 'Booking not found.', 'nozule' ) );
		}

		$this->bookingRepository->update( $id, [
			'room_id' => $roomId,
		] );

		$this->bookingRepository->createLog( [
			'booking_id' => $id,
			'action'     => 'room_assigned',
			'details'    => sprintf( __( 'Room ID %d assigned.', 'nozule' ), $roomId ),
			'user_id'    => get_current_user_id() ?: null,
			'ip_address' => BookingService::getClientIP(),
		] );

		return ResponseHelper::success( $this->bookingRepository->findOrFail( $id )->toArray() );
	}

	/**
	 * POST /admin/bookings/{id}/payments
	 *
	 * Record a payment against a booking.
	 */
	public function addPayment( \WP_REST_Request $request ): \WP_REST_Response {
		try {
			$payment = $this->service->addPayment(
				(int) $request->get_param( 'id' ),
				$request->get_params()
			);

			return ResponseHelper::created( $payment->toArray() );
		} catch ( \InvalidArgumentException $e ) {
			return ResponseHelper::error( $e->getMessage(), 400 );
		} catch ( \Throwable $e ) {
			return ResponseHelper::error( __( 'Failed to record payment.', 'nozule' ), 500 );
		}
	}

	/**
	 * GET /admin/bookings/{id}/payments
	 *
	 * List all payments for a booking.
	 */
	public function getPayments( \WP_REST_Request $request ): \WP_REST_Response {
		$id = (int) $request->get_param( 'id' );

		$booking = $this->bookingRepository->find( $id );
		if ( ! $booking ) {
			return ResponseHelper::notFound( __( 'Booking not found.', 'nozule' ) );
		}

		$payments = $this->paymentRepository->getByBookingId( $id );

		return ResponseHelper::success( array_map( fn( $p ) => $p->toArray(), $payments ) );
	}

	/**
	 * GET /admin/bookings/{id}/logs
	 *
	 * Get the audit log for a booking.
	 */
	public function getLogs( \WP_REST_Request $request ): \WP_REST_Response {
		$id = (int) $request->get_param( 'id' );

		$booking = $this->bookingRepository->find( $id );
		if ( ! $booking ) {
			return ResponseHelper::notFound( __( 'Booking not found.', 'nozule' ) );
		}

		$logs = $this->bookingRepository->getLogsForBooking( $id );

		return ResponseHelper::success( array_map( fn( $log ) => $log->toArray(), $logs ) );
	}

	// ── Helpers ─────────────────────────────────────────────────────

	/**
	 * Argument definitions for the list endpoint.
	 */
	private function getListArgs(): array {
		return [
			'status' => [
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			],
			'source' => [
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			],
			'date_from' => [
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			],
			'date_to' => [
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
