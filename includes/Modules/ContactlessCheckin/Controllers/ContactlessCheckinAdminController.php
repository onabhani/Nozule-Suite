<?php

namespace Nozule\Modules\ContactlessCheckin\Controllers;

use Nozule\Core\ResponseHelper;
use Nozule\Modules\ContactlessCheckin\Models\CheckinRegistration;
use Nozule\Modules\ContactlessCheckin\Services\ContactlessCheckinService;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * Admin REST controller for managing contactless check-in registrations.
 *
 * Routes (staff-level):
 *   GET    /nozule/v1/admin/contactless-checkin                        List registrations
 *   GET    /nozule/v1/admin/contactless-checkin/stats                  Status counts
 *   GET    /nozule/v1/admin/contactless-checkin/settings               Get settings
 *   PUT    /nozule/v1/admin/contactless-checkin/settings               Update settings
 *   POST   /nozule/v1/admin/contactless-checkin/send/{booking_id}      Send check-in link
 *   GET    /nozule/v1/admin/contactless-checkin/{id}                   View registration
 *   PUT    /nozule/v1/admin/contactless-checkin/{id}/approve           Approve
 *   PUT    /nozule/v1/admin/contactless-checkin/{id}/reject            Reject
 */
class ContactlessCheckinAdminController {

	private ContactlessCheckinService $service;

	public function __construct( ContactlessCheckinService $service ) {
		$this->service = $service;
	}

	/**
	 * Register admin REST routes.
	 */
	public function registerRoutes(): void {
		$namespace = 'nozule/v1';

		// Settings (before parameterised routes).
		register_rest_route( $namespace, '/admin/contactless-checkin/settings', [
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

		// Stats.
		register_rest_route( $namespace, '/admin/contactless-checkin/stats', [
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => [ $this, 'stats' ],
			'permission_callback' => [ $this, 'checkStaffPermission' ],
		] );

		// Send check-in link.
		register_rest_route( $namespace, '/admin/contactless-checkin/send/(?P<booking_id>\d+)', [
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => [ $this, 'sendLink' ],
			'permission_callback' => [ $this, 'checkStaffPermission' ],
			'args'                => [
				'booking_id' => [
					'required'          => true,
					'validate_callback' => fn( $v ) => is_numeric( $v ) && $v > 0,
					'sanitize_callback' => 'absint',
				],
			],
		] );

		// List registrations.
		register_rest_route( $namespace, '/admin/contactless-checkin', [
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => [ $this, 'index' ],
			'permission_callback' => [ $this, 'checkStaffPermission' ],
			'args'                => [
				'status' => [
					'required'          => false,
					'validate_callback' => fn( $v ) => in_array( $v, CheckinRegistration::validStatuses(), true ),
					'sanitize_callback' => 'sanitize_text_field',
				],
				'page' => [
					'required'          => false,
					'default'           => 1,
					'sanitize_callback' => 'absint',
				],
				'per_page' => [
					'required'          => false,
					'default'           => 50,
					'sanitize_callback' => 'absint',
				],
			],
		] );

		// Single registration.
		register_rest_route( $namespace, '/admin/contactless-checkin/(?P<id>\d+)', [
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => [ $this, 'show' ],
			'permission_callback' => [ $this, 'checkStaffPermission' ],
			'args'                => $this->getIdArgs(),
		] );

		// Approve.
		register_rest_route( $namespace, '/admin/contactless-checkin/(?P<id>\d+)/approve', [
			'methods'             => WP_REST_Server::EDITABLE,
			'callback'            => [ $this, 'approve' ],
			'permission_callback' => [ $this, 'checkStaffPermission' ],
			'args'                => $this->getIdArgs(),
		] );

		// Reject.
		register_rest_route( $namespace, '/admin/contactless-checkin/(?P<id>\d+)/reject', [
			'methods'             => WP_REST_Server::EDITABLE,
			'callback'            => [ $this, 'reject' ],
			'permission_callback' => [ $this, 'checkStaffPermission' ],
			'args'                => $this->getIdArgs(),
		] );
	}

	/**
	 * List registrations.
	 */
	public function index( WP_REST_Request $request ): WP_REST_Response {
		$status  = $request->get_param( 'status' );
		$page    = absint( $request->get_param( 'page' ) ) ?: 1;
		$perPage = absint( $request->get_param( 'per_page' ) ) ?: 50;

		$result = $this->service->list( $status, $page, $perPage );

		$data = array_map(
			fn( CheckinRegistration $reg ) => $reg->toArray(),
			$result['items']
		);

		return ResponseHelper::paginated( $data, $result['total'], $page, $perPage );
	}

	/**
	 * Show a single registration.
	 */
	public function show( WP_REST_Request $request ): WP_REST_Response {
		$id  = (int) $request->get_param( 'id' );
		$reg = $this->service->find( $id );

		if ( ! $reg ) {
			return ResponseHelper::notFound( __( 'Registration not found.', 'nozule' ) );
		}

		return ResponseHelper::success( $reg->toArray() );
	}

	/**
	 * Find a registration by ID (delegates to repository via service).
	 */
	private function find( int $id ): ?CheckinRegistration {
		return $this->service->find( $id );
	}

	/**
	 * Send a check-in link for a booking.
	 */
	public function sendLink( WP_REST_Request $request ): WP_REST_Response {
		$bookingId = (int) $request->get_param( 'booking_id' );
		$result    = $this->service->sendCheckinLink( $bookingId );

		if ( $result instanceof CheckinRegistration ) {
			return ResponseHelper::created(
				$result->toArray(),
				__( 'Contactless check-in link sent successfully.', 'nozule' )
			);
		}

		$status = isset( $result['general'] ) ? 400 : 422;
		return ResponseHelper::error( __( 'Failed to send check-in link.', 'nozule' ), $status, $result );
	}

	/**
	 * Approve a submitted registration.
	 */
	public function approve( WP_REST_Request $request ): WP_REST_Response {
		$id     = (int) $request->get_param( 'id' );
		$result = $this->service->approve( $id );

		if ( $result instanceof CheckinRegistration ) {
			return ResponseHelper::success( $result->toArray(), __( 'Registration approved.', 'nozule' ) );
		}

		if ( isset( $result['id'] ) ) {
			return ResponseHelper::error( $result['id'][0], 404, $result );
		}

		return ResponseHelper::error( __( 'Failed to approve registration.', 'nozule' ), 422, $result );
	}

	/**
	 * Reject a registration.
	 */
	public function reject( WP_REST_Request $request ): WP_REST_Response {
		$id     = (int) $request->get_param( 'id' );
		$result = $this->service->reject( $id );

		if ( $result instanceof CheckinRegistration ) {
			return ResponseHelper::success( $result->toArray(), __( 'Registration rejected.', 'nozule' ) );
		}

		if ( isset( $result['id'] ) ) {
			return ResponseHelper::error( $result['id'][0], 404, $result );
		}

		return ResponseHelper::error( __( 'Failed to reject registration.', 'nozule' ), 422, $result );
	}

	/**
	 * Get contactless check-in settings.
	 */
	public function getSettings( WP_REST_Request $request ): WP_REST_Response {
		return ResponseHelper::success( [ 'settings' => $this->service->getSettings() ] );
	}

	/**
	 * Update contactless check-in settings.
	 */
	public function updateSettings( WP_REST_Request $request ): WP_REST_Response {
		$data = [];
		foreach ( [ 'enabled', 'require_document', 'require_signature', 'token_expiry_hours', 'auto_approve' ] as $key ) {
			$value = $request->get_param( $key );
			if ( $value !== null ) {
				$data[ $key ] = $value;
			}
		}

		$settings = $this->service->updateSettings( $data );

		return ResponseHelper::success(
			[ 'settings' => $settings ],
			__( 'Contactless check-in settings updated.', 'nozule' )
		);
	}

	/**
	 * Status counts.
	 */
	public function stats( WP_REST_Request $request ): WP_REST_Response {
		return ResponseHelper::success( $this->service->getStatusCounts() );
	}

	private function getIdArgs(): array {
		return [
			'id' => [
				'required'          => true,
				'validate_callback' => fn( $v ) => is_numeric( $v ) && $v > 0,
				'sanitize_callback' => 'absint',
			],
		];
	}

	public function checkStaffPermission( WP_REST_Request $request ): bool {
		return current_user_can( 'manage_options' ) || current_user_can( 'nzl_admin' ) || current_user_can( 'nzl_staff' );
	}

	public function checkAdminPermission( WP_REST_Request $request ): bool {
		return current_user_can( 'manage_options' ) || current_user_can( 'nzl_admin' );
	}
}
