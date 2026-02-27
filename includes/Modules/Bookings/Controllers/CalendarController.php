<?php

namespace Nozule\Modules\Bookings\Controllers;

use Nozule\Modules\Bookings\Models\Booking;
use Nozule\Modules\Bookings\Repositories\BookingRepository;

/**
 * REST controller for the calendar/timeline view.
 *
 * Provides booking data for visual calendar rendering, returning
 * bookings that overlap with the requested date range.
 *
 * Route namespace: nozule/v1
 */
class CalendarController {

	private BookingRepository $bookingRepository;

	private const NAMESPACE = 'nozule/v1';

	public function __construct( BookingRepository $bookingRepository ) {
		$this->bookingRepository = $bookingRepository;
	}

	/**
	 * Register routes.
	 */
	public function registerRoutes(): void {
		// GET /admin/calendar
		register_rest_route( self::NAMESPACE, '/admin/calendar', [
			'methods'             => \WP_REST_Server::READABLE,
			'callback'            => [ $this, 'index' ],
			'permission_callback' => [ $this, 'checkAdminPermission' ],
			'args'                => [
				'start' => [
					'required'          => true,
					'type'              => 'string',
					'description'       => __( 'Start date (Y-m-d).', 'nozule' ),
					'sanitize_callback' => 'sanitize_text_field',
				],
				'end' => [
					'required'          => true,
					'type'              => 'string',
					'description'       => __( 'End date (Y-m-d).', 'nozule' ),
					'sanitize_callback' => 'sanitize_text_field',
				],
			],
		] );
	}

	/**
	 * Permission check.
	 */
	public function checkAdminPermission(): bool {
		return current_user_can( 'manage_options' ) || current_user_can( 'nzl_view_calendar' );
	}

	/**
	 * GET /admin/calendar?start=YYYY-MM-DD&end=YYYY-MM-DD
	 *
	 * Returns all bookings that overlap the given date range, formatted
	 * for calendar/timeline rendering.
	 */
	public function index( \WP_REST_Request $request ): \WP_REST_Response {
		$start = $request->get_param( 'start' );
		$end   = $request->get_param( 'end' );

		// Basic date validation.
		if ( ! strtotime( $start ) || ! strtotime( $end ) ) {
			return new \WP_REST_Response( [
				'success' => false,
				'message' => __( 'Invalid date format. Use Y-m-d.', 'nozule' ),
			], 400 );
		}

		if ( strtotime( $end ) < strtotime( $start ) ) {
			return new \WP_REST_Response( [
				'success' => false,
				'message' => __( 'End date must be after start date.', 'nozule' ),
			], 400 );
		}

		$bookings = $this->bookingRepository->getForCalendar( $start, $end );

		$events = array_map( function ( Booking $booking ) {
			return [
				'id'             => $booking->id,
				'booking_number' => $booking->booking_number,
				'guest_id'       => $booking->guest_id,
				'room_type_id'   => $booking->room_type_id,
				'room_id'        => $booking->room_id,
				'start'          => $booking->check_in,
				'end'            => $booking->check_out,
				'nights'         => $booking->nights,
				'status'         => $booking->status,
				'adults'         => $booking->adults,
				'children'       => $booking->children,
				'total_amount'   => $booking->total_amount,
				'balance_due'    => $booking->balance_due,
			];
		}, $bookings );

		return new \WP_REST_Response( [
			'success' => true,
			'data'    => $events,
			'meta'    => [
				'start' => $start,
				'end'   => $end,
				'count' => count( $events ),
			],
		], 200 );
	}
}
