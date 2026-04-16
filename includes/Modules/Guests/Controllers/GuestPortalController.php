<?php

namespace Nozule\Modules\Guests\Controllers;

use Nozule\Modules\Bookings\Repositories\BookingRepository;
use Nozule\Modules\Bookings\Services\BookingService;
use Nozule\Modules\Guests\Models\Guest;
use Nozule\Modules\Guests\Repositories\GuestRepository;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

/**
 * REST controller for the public guest portal.
 *
 * Endpoints (namespace /nozule/v1):
 *   POST /me/register                 — public self-service registration
 *   GET  /me/profile                  — current user's guest profile
 *   PUT  /me/profile                  — update profile
 *   GET  /me/bookings                 — list the user's bookings
 *   GET  /me/bookings/{id}            — single booking (owned by user)
 *   POST /me/bookings/{id}/cancel     — self-service cancellation
 *
 * All endpoints except /me/register require an authenticated WP user
 * with a linked nzl_guest record (Guest.wp_user_id).
 */
class GuestPortalController {

	public function __construct(
		private GuestRepository $guests,
		private BookingRepository $bookings,
		private BookingService $bookingService
	) {}

	// ------------------------------------------------------------------
	// Registration (public)
	// ------------------------------------------------------------------

	public function register( WP_REST_Request $request ) {
		$email    = sanitize_email( (string) $request->get_param( 'email' ) );
		$password = (string) $request->get_param( 'password' );
		$first    = sanitize_text_field( (string) $request->get_param( 'first_name' ) );
		$last     = sanitize_text_field( (string) $request->get_param( 'last_name' ) );
		$phone    = sanitize_text_field( (string) $request->get_param( 'phone' ) );
		$locale   = sanitize_text_field( (string) $request->get_param( 'locale' ) );

		if ( ! is_email( $email ) ) {
			return new WP_Error( 'nzl_invalid_email', __( 'Invalid email address.', 'nozule' ), [ 'status' => 400 ] );
		}
		if ( strlen( $password ) < 8 ) {
			return new WP_Error( 'nzl_weak_password', __( 'Password must be at least 8 characters.', 'nozule' ), [ 'status' => 400 ] );
		}
		if ( $first === '' || $last === '' ) {
			return new WP_Error( 'nzl_missing_name', __( 'First and last name are required.', 'nozule' ), [ 'status' => 400 ] );
		}

		// Check if WP user already exists for this email.
		$existingWpUser = get_user_by( 'email', $email );
		if ( $existingWpUser ) {
			return new WP_Error(
				'nzl_email_exists',
				__( 'An account with this email already exists. Please sign in.', 'nozule' ),
				[ 'status' => 409 ]
			);
		}

		// Create the WP user.
		$username = $this->generateUsername( $email );
		$userId   = wp_create_user( $username, $password, $email );
		if ( is_wp_error( $userId ) ) {
			return new WP_Error(
				'nzl_user_create_failed',
				$userId->get_error_message(),
				[ 'status' => 500 ]
			);
		}

		wp_update_user( [
			'ID'         => $userId,
			'first_name' => $first,
			'last_name'  => $last,
			'display_name' => trim( $first . ' ' . $last ),
		] );

		// Link or create guest record.
		$guest = $this->guests->findByEmail( $email );
		if ( $guest ) {
			$this->guests->update( $guest->id, [ 'wp_user_id' => $userId ] );
		} else {
			$guestData = [
				'first_name' => $first,
				'last_name'  => $last,
				'email'      => $email,
				'phone'      => $phone,
				'wp_user_id' => $userId,
			];
			if ( $locale !== '' ) {
				$guestData['locale'] = $locale;
			}
			$this->guests->create( $guestData );
		}

		/**
		 * Fires after a guest self-registers via the portal.
		 *
		 * @param int   $userId WP user ID.
		 * @param array $data   Sanitized registration data.
		 */
		do_action( 'nozule/guest_portal/registered', $userId, [
			'email'      => $email,
			'first_name' => $first,
			'last_name'  => $last,
			'phone'      => $phone,
		] );

		// Auto-login.
		wp_set_current_user( $userId );
		wp_set_auth_cookie( $userId, true, is_ssl() );

		return new WP_REST_Response( [
			'success' => true,
			'user_id' => $userId,
			'message' => __( 'Account created successfully.', 'nozule' ),
		], 201 );
	}

	// ------------------------------------------------------------------
	// Profile
	// ------------------------------------------------------------------

	public function profile( WP_REST_Request $request ) {
		$guest = $this->currentGuest();
		if ( $guest instanceof WP_Error ) {
			return $guest;
		}

		return new WP_REST_Response( $guest->toArray(), 200 );
	}

	public function updateProfile( WP_REST_Request $request ) {
		$guest = $this->currentGuest();
		if ( $guest instanceof WP_Error ) {
			return $guest;
		}

		$updatable = [ 'first_name', 'last_name', 'phone', 'phone_alt', 'nationality', 'language', 'locale', 'address', 'city', 'country' ];
		$updates   = [];

		foreach ( $updatable as $field ) {
			$value = $request->get_param( $field );
			if ( $value !== null ) {
				$updates[ $field ] = sanitize_text_field( (string) $value );
			}
		}

		if ( empty( $updates ) ) {
			return new WP_Error( 'nzl_no_updates', __( 'No fields to update.', 'nozule' ), [ 'status' => 400 ] );
		}

		$this->guests->update( $guest->id, $updates );

		// Mirror name changes onto the WP user for consistency.
		if ( isset( $updates['first_name'] ) || isset( $updates['last_name'] ) ) {
			wp_update_user( [
				'ID'         => (int) $guest->wp_user_id,
				'first_name' => $updates['first_name'] ?? $guest->first_name,
				'last_name'  => $updates['last_name']  ?? $guest->last_name,
			] );
		}

		$refreshed = $this->guests->find( $guest->id );
		return new WP_REST_Response( $refreshed->toArray(), 200 );
	}

	// ------------------------------------------------------------------
	// Bookings
	// ------------------------------------------------------------------

	public function bookings( WP_REST_Request $request ) {
		$guest = $this->currentGuest();
		if ( $guest instanceof WP_Error ) {
			return $guest;
		}

		$bookings = $this->bookings->getByGuestId( $guest->id );

		$data = array_map(
			static fn( $booking ) => method_exists( $booking, 'toArray' ) ? $booking->toArray() : (array) $booking,
			$bookings
		);

		return new WP_REST_Response( [
			'bookings' => $data,
			'total'    => count( $data ),
		], 200 );
	}

	public function booking( WP_REST_Request $request ) {
		$guest = $this->currentGuest();
		if ( $guest instanceof WP_Error ) {
			return $guest;
		}

		$booking = $this->bookings->find( (int) $request->get_param( 'id' ) );
		if ( ! $booking || (int) $booking->guest_id !== (int) $guest->id ) {
			return new WP_Error( 'nzl_not_found', __( 'Booking not found.', 'nozule' ), [ 'status' => 404 ] );
		}

		return new WP_REST_Response(
			method_exists( $booking, 'toArray' ) ? $booking->toArray() : (array) $booking,
			200
		);
	}

	public function cancelBooking( WP_REST_Request $request ) {
		$guest = $this->currentGuest();
		if ( $guest instanceof WP_Error ) {
			return $guest;
		}

		$bookingId = (int) $request->get_param( 'id' );
		$booking   = $this->bookings->find( $bookingId );

		if ( ! $booking || (int) $booking->guest_id !== (int) $guest->id ) {
			return new WP_Error( 'nzl_not_found', __( 'Booking not found.', 'nozule' ), [ 'status' => 404 ] );
		}

		if ( in_array( $booking->status, [ 'cancelled', 'checked_out', 'no_show' ], true ) ) {
			return new WP_Error(
				'nzl_not_cancellable',
				__( 'This booking is not in a cancellable state.', 'nozule' ),
				[ 'status' => 409 ]
			);
		}

		$reason = sanitize_text_field( (string) $request->get_param( 'reason' ) );

		try {
			$updated = $this->bookingService->cancelBooking( $bookingId, $reason, get_current_user_id() );
		} catch ( \Throwable $e ) {
			return new WP_Error( 'nzl_cancel_failed', $e->getMessage(), [ 'status' => 400 ] );
		}

		return new WP_REST_Response(
			method_exists( $updated, 'toArray' ) ? $updated->toArray() : (array) $updated,
			200
		);
	}

	// ------------------------------------------------------------------
	// Helpers
	// ------------------------------------------------------------------

	/**
	 * Resolve the current user's linked Guest record, or a WP_Error.
	 */
	private function currentGuest(): Guest|WP_Error {
		$userId = get_current_user_id();
		if ( $userId <= 0 ) {
			return new WP_Error( 'nzl_unauthenticated', __( 'You must be signed in.', 'nozule' ), [ 'status' => 401 ] );
		}

		$guest = $this->guests->findByWpUserId( $userId );
		if ( ! $guest ) {
			// Auto-link: if the WP user's email matches an existing guest, link them.
			$wpUser = get_userdata( $userId );
			if ( $wpUser && $wpUser->user_email ) {
				$existing = $this->guests->findByEmail( $wpUser->user_email );
				if ( $existing ) {
					$this->guests->update( $existing->id, [ 'wp_user_id' => $userId ] );
					return $this->guests->find( $existing->id );
				}

				// Create a minimal guest record for this WP user.
				$created = $this->guests->create( [
					'first_name' => $wpUser->first_name ?: $wpUser->display_name,
					'last_name'  => $wpUser->last_name ?: '',
					'email'      => $wpUser->user_email,
					'wp_user_id' => $userId,
				] );
				if ( $created instanceof Guest ) {
					return $created;
				}
			}

			return new WP_Error( 'nzl_no_guest_profile', __( 'No guest profile is linked to your account.', 'nozule' ), [ 'status' => 404 ] );
		}

		return $guest;
	}

	/**
	 * Generate a unique username from an email address.
	 */
	private function generateUsername( string $email ): string {
		$base = sanitize_user( current( explode( '@', $email ) ), true );
		if ( $base === '' ) {
			$base = 'guest';
		}
		$candidate = $base;
		$i         = 1;
		while ( username_exists( $candidate ) ) {
			$candidate = $base . $i;
			$i++;
			if ( $i > 100 ) {
				$candidate = $base . '_' . wp_generate_password( 6, false );
				break;
			}
		}
		return $candidate;
	}
}
