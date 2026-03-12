<?php

namespace Nozule\Modules\ContactlessCheckin\Controllers;

use Nozule\Core\ResponseHelper;
use Nozule\Modules\ContactlessCheckin\Services\ContactlessCheckinService;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * Public REST controller for guest-facing contactless check-in.
 *
 * All routes are unauthenticated — access is controlled by the unique token.
 *
 * Routes:
 *   GET    /nozule/v1/contactless-checkin/{token}          Verify token & get form data
 *   POST   /nozule/v1/contactless-checkin/{token}/submit   Submit registration form
 *   POST   /nozule/v1/contactless-checkin/{token}/signature  Upload signature
 */
class ContactlessCheckinPublicController {

	private ContactlessCheckinService $service;

	public function __construct( ContactlessCheckinService $service ) {
		$this->service = $service;
	}

	/**
	 * Register public REST routes.
	 */
	public function registerRoutes(): void {
		$namespace = 'nozule/v1';

		register_rest_route( $namespace, '/contactless-checkin/(?P<token>[a-f0-9]{64})', [
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => [ $this, 'verify' ],
			'permission_callback' => '__return_true',
			'args'                => $this->getTokenArgs(),
		] );

		register_rest_route( $namespace, '/contactless-checkin/(?P<token>[a-f0-9]{64})/submit', [
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => [ $this, 'submit' ],
			'permission_callback' => '__return_true',
			'args'                => $this->getTokenArgs(),
		] );

		register_rest_route( $namespace, '/contactless-checkin/(?P<token>[a-f0-9]{64})/signature', [
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => [ $this, 'uploadSignature' ],
			'permission_callback' => '__return_true',
			'args'                => $this->getTokenArgs(),
		] );
	}

	/**
	 * Verify a check-in token and return booking/guest data for the form.
	 */
	public function verify( WP_REST_Request $request ): WP_REST_Response {
		$token = sanitize_text_field( $request->get_param( 'token' ) );
		$data  = $this->service->verifyToken( $token );

		if ( ! $data ) {
			return ResponseHelper::error(
				__( 'Invalid or expired check-in link.', 'nozule' ),
				404
			);
		}

		return ResponseHelper::success( $data );
	}

	/**
	 * Submit the guest's contactless check-in form.
	 */
	public function submit( WP_REST_Request $request ): WP_REST_Response {
		$token = sanitize_text_field( $request->get_param( 'token' ) );

		$guestDetails = $request->get_param( 'guest_details' );
		if ( is_string( $guestDetails ) ) {
			$guestDetails = json_decode( $guestDetails, true ) ?: [];
		}

		// Sanitize guest details.
		$sanitized = [];
		foreach ( $guestDetails as $key => $value ) {
			$sanitized[ sanitize_key( $key ) ] = is_string( $value ) ? sanitize_text_field( $value ) : $value;
		}

		$data = [
			'guest_details'    => $sanitized,
			'room_preference'  => sanitize_textarea_field( $request->get_param( 'room_preference' ) ?? '' ),
			'special_requests' => sanitize_textarea_field( $request->get_param( 'special_requests' ) ?? '' ),
			'document_ids'     => $request->get_param( 'document_ids' ) ?? [],
			'signature_path'   => sanitize_text_field( $request->get_param( 'signature_path' ) ?? '' ),
		];

		if ( is_string( $data['document_ids'] ) ) {
			$data['document_ids'] = json_decode( $data['document_ids'], true ) ?: [];
		}
		$data['document_ids'] = array_map( 'absint', (array) $data['document_ids'] );

		$result = $this->service->submitRegistration( $token, $data );

		if ( $result instanceof \Nozule\Modules\ContactlessCheckin\Models\CheckinRegistration ) {
			return ResponseHelper::success(
				$result->toArray(),
				__( 'Check-in registration submitted successfully.', 'nozule' )
			);
		}

		return ResponseHelper::error( __( 'Submission failed.', 'nozule' ), 422, $result );
	}

	/**
	 * Upload a digital signature (base64 PNG).
	 */
	public function uploadSignature( WP_REST_Request $request ): WP_REST_Response {
		$token = sanitize_text_field( $request->get_param( 'token' ) );

		$verifyData = $this->service->verifyToken( $token );
		if ( ! $verifyData ) {
			return ResponseHelper::error( __( 'Invalid or expired check-in link.', 'nozule' ), 404 );
		}

		$signatureData = $request->get_param( 'signature' );
		if ( ! $signatureData ) {
			return ResponseHelper::error( __( 'Signature data is required.', 'nozule' ), 422 );
		}

		$bookingId = $verifyData['registration']['booking_id'];
		$result    = $this->service->saveSignature( $signatureData, $bookingId );

		if ( is_string( $result ) ) {
			return ResponseHelper::success(
				[ 'signature_path' => $result ],
				__( 'Signature uploaded successfully.', 'nozule' )
			);
		}

		return ResponseHelper::error( __( 'Failed to upload signature.', 'nozule' ), 422, $result );
	}

	/**
	 * Token argument definition.
	 */
	private function getTokenArgs(): array {
		return [
			'token' => [
				'required'          => true,
				'validate_callback' => fn( $v ) => preg_match( '/^[a-f0-9]{64}$/', $v ),
				'sanitize_callback' => 'sanitize_text_field',
			],
		];
	}
}
