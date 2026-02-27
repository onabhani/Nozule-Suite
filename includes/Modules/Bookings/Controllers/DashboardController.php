<?php

namespace Nozule\Modules\Bookings\Controllers;

use Nozule\Modules\Bookings\Models\Booking;
use Nozule\Modules\Bookings\Repositories\BookingRepository;
use Nozule\Modules\Bookings\Services\BookingService;
use Nozule\Modules\Rooms\Repositories\RoomRepository;

/**
 * REST controller for the admin dashboard.
 *
 * Provides aggregate stats and today's operational lists (arrivals,
 * departures, in-house guests).
 *
 * Route namespace: nozule/v1
 */
class DashboardController {

	private BookingService $service;
	private BookingRepository $bookingRepository;
	private RoomRepository $roomRepository;

	private const NAMESPACE = 'nozule/v1';

	public function __construct(
		BookingService $service,
		BookingRepository $bookingRepository,
		RoomRepository $roomRepository
	) {
		$this->service           = $service;
		$this->bookingRepository = $bookingRepository;
		$this->roomRepository    = $roomRepository;
	}

	/**
	 * Register routes.
	 */
	public function registerRoutes(): void {
		$admin_perm = [ $this, 'checkAdminPermission' ];

		// GET /admin/dashboard/stats
		register_rest_route( self::NAMESPACE, '/admin/dashboard/stats', [
			'methods'             => \WP_REST_Server::READABLE,
			'callback'            => [ $this, 'stats' ],
			'permission_callback' => $admin_perm,
		] );

		// GET /admin/dashboard/arrivals
		register_rest_route( self::NAMESPACE, '/admin/dashboard/arrivals', [
			'methods'             => \WP_REST_Server::READABLE,
			'callback'            => [ $this, 'arrivals' ],
			'permission_callback' => $admin_perm,
		] );

		// GET /admin/dashboard/departures
		register_rest_route( self::NAMESPACE, '/admin/dashboard/departures', [
			'methods'             => \WP_REST_Server::READABLE,
			'callback'            => [ $this, 'departures' ],
			'permission_callback' => $admin_perm,
		] );

		// GET /admin/dashboard/in-house
		register_rest_route( self::NAMESPACE, '/admin/dashboard/in-house', [
			'methods'             => \WP_REST_Server::READABLE,
			'callback'            => [ $this, 'inHouse' ],
			'permission_callback' => $admin_perm,
		] );
	}

	/**
	 * Permission check: current user has manage_options or nzl_staff.
	 */
	public function checkAdminPermission(): bool {
		return current_user_can( 'manage_options' ) || current_user_can( 'nzl_staff' );
	}

	// ── Endpoints ───────────────────────────────────────────────────

	/**
	 * GET /admin/dashboard/stats
	 *
	 * Returns today's key operational metrics.
	 */
	public function stats( \WP_REST_Request $request ): \WP_REST_Response {
		$arrivals   = $this->service->getTodayArrivals();
		$departures = $this->service->getTodayDepartures();
		$inHouse    = $this->service->getInHouseGuests();

		// Calculate occupancy rate.
		$totalRooms  = $this->roomRepository->count();
		$inHouseCount = count( $inHouse );
		$occupancy   = $totalRooms > 0
			? round( ( $inHouseCount / $totalRooms ) * 100, 1 )
			: 0.0;

		return new \WP_REST_Response( [
			'success' => true,
			'data'    => [
				'today_arrivals'   => count( $arrivals ),
				'today_departures' => count( $departures ),
				'in_house'         => $inHouseCount,
				'total_rooms'      => $totalRooms,
				'occupancy_rate'   => $occupancy,
				'date'             => current_time( 'Y-m-d' ),
			],
		], 200 );
	}

	/**
	 * GET /admin/dashboard/arrivals
	 *
	 * Today's arrivals list with booking details.
	 */
	public function arrivals( \WP_REST_Request $request ): \WP_REST_Response {
		$arrivals = $this->service->getTodayArrivals();

		return new \WP_REST_Response( [
			'success' => true,
			'data'    => array_map( fn( Booking $b ) => $b->toArray(), $arrivals ),
		], 200 );
	}

	/**
	 * GET /admin/dashboard/departures
	 *
	 * Today's departures list with booking details.
	 */
	public function departures( \WP_REST_Request $request ): \WP_REST_Response {
		$departures = $this->service->getTodayDepartures();

		return new \WP_REST_Response( [
			'success' => true,
			'data'    => array_map( fn( Booking $b ) => $b->toArray(), $departures ),
		], 200 );
	}

	/**
	 * GET /admin/dashboard/in-house
	 *
	 * List of currently in-house guests.
	 */
	public function inHouse( \WP_REST_Request $request ): \WP_REST_Response {
		$inHouse = $this->service->getInHouseGuests();

		return new \WP_REST_Response( [
			'success' => true,
			'data'    => array_map( fn( Booking $b ) => $b->toArray(), $inHouse ),
		], 200 );
	}
}
