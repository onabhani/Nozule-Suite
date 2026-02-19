<?php

namespace Nozule\Modules\Rooms\Controllers;

use Nozule\Modules\Rooms\Services\AvailabilityService;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * REST controller for public availability search.
 *
 * Routes:
 *   GET /nozule/v1/availability   Search room availability for a date range
 */
class AvailabilityController {

	private AvailabilityService $availabilityService;

	public function __construct( AvailabilityService $availabilityService ) {
		$this->availabilityService = $availabilityService;
	}

	/**
	 * Register REST routes.
	 */
	public function registerRoutes(): void {
		$namespace = 'nozule/v1';

		register_rest_route( $namespace, '/availability', [
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => [ $this, 'search' ],
			'permission_callback' => '__return_true',
			'args'                => [
				'check_in' => [
					'required'          => true,
					'validate_callback' => [ $this, 'validateDate' ],
					'sanitize_callback' => 'sanitize_text_field',
					'description'       => __( 'Check-in date in Y-m-d format.', 'nozule' ),
				],
				'check_out' => [
					'required'          => true,
					'validate_callback' => [ $this, 'validateDate' ],
					'sanitize_callback' => 'sanitize_text_field',
					'description'       => __( 'Check-out date in Y-m-d format.', 'nozule' ),
				],
				'guests' => [
					'required'          => false,
					'default'           => 1,
					'validate_callback' => fn( $v ) => is_numeric( $v ) && $v >= 1 && $v <= 20,
					'sanitize_callback' => 'absint',
					'description'       => __( 'Number of guests.', 'nozule' ),
				],
				'room_type_id' => [
					'required'          => false,
					'validate_callback' => fn( $v ) => is_numeric( $v ) && $v > 0,
					'sanitize_callback' => 'absint',
					'description'       => __( 'Optional room type ID to restrict search.', 'nozule' ),
				],
			],
		] );
	}

	/**
	 * Search availability.
	 */
	public function search( WP_REST_Request $request ): WP_REST_Response {
		$checkIn    = $request->get_param( 'check_in' );
		$checkOut   = $request->get_param( 'check_out' );
		$guests     = absint( $request->get_param( 'guests' ) ) ?: 1;
		$roomTypeId = $request->get_param( 'room_type_id' ) ? absint( $request->get_param( 'room_type_id' ) ) : null;

		// Validate that check_out is after check_in.
		if ( strtotime( $checkOut ) <= strtotime( $checkIn ) ) {
			return new WP_REST_Response( [
				'success' => false,
				'message' => __( 'Check-out date must be after check-in date.', 'nozule' ),
			], 422 );
		}

		// Validate that check_in is not in the past.
		if ( strtotime( $checkIn ) < strtotime( 'today' ) ) {
			return new WP_REST_Response( [
				'success' => false,
				'message' => __( 'Check-in date cannot be in the past.', 'nozule' ),
			], 422 );
		}

		$results = $this->availabilityService->checkAvailability(
			$checkIn,
			$checkOut,
			$guests,
			$roomTypeId
		);

		$nights = (int) ( new \DateTimeImmutable( $checkIn ) )->diff( new \DateTimeImmutable( $checkOut ) )->days;

		return new WP_REST_Response( [
			'success' => true,
			'data'    => $results,
			'meta'    => [
				'check_in'  => $checkIn,
				'check_out' => $checkOut,
				'guests'    => $guests,
				'nights'    => $nights,
				'results'   => count( $results ),
			],
		], 200 );
	}

	/**
	 * Validate a date string is in Y-m-d format.
	 */
	public function validateDate( string $value ): bool {
		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $value ) ) {
			return false;
		}

		$parts = explode( '-', $value );

		return checkdate( (int) $parts[1], (int) $parts[2], (int) $parts[0] );
	}
}
