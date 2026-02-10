<?php

namespace Venezia\Modules\Guests\Services;

use Venezia\Core\EventDispatcher;
use Venezia\Modules\Guests\Models\Guest;
use Venezia\Modules\Guests\Repositories\GuestRepository;
use Venezia\Modules\Guests\Validators\GuestValidator;

/**
 * Service layer for guest business logic.
 */
class GuestService {

    private GuestRepository $repository;
    private GuestValidator $validator;
    private EventDispatcher $events;

    public function __construct(
        GuestRepository $repository,
        GuestValidator $validator,
        EventDispatcher $events
    ) {
        $this->repository = $repository;
        $this->validator  = $validator;
        $this->events     = $events;
    }

    /**
     * Find an existing guest by email, or create a new guest profile.
     *
     * If the guest exists and a different phone number is provided,
     * the phone number is updated on the existing profile.
     *
     * @param array $data Guest data including at least email, first_name, last_name, phone.
     * @return array{ guest: Guest, created: bool } The guest and whether it was newly created.
     * @throws \RuntimeException If validation fails or creation fails.
     */
    public function findOrCreate( array $data ): array {
        $email = $data['email'] ?? '';

        if ( empty( $email ) ) {
            throw new \RuntimeException( __( 'Email address is required.', 'venezia-hotel' ) );
        }

        // Try to find an existing guest by email.
        $guest = $this->repository->findByEmail( $email );

        if ( $guest ) {
            // Update phone if provided and different from current.
            $new_phone = $data['phone'] ?? '';
            if ( ! empty( $new_phone ) && $new_phone !== $guest->phone ) {
                $this->repository->update( $guest->id, [ 'phone' => $new_phone ] );
                $guest->phone = $new_phone;

                $this->events->dispatch( 'guests/phone_updated', $guest );
            }

            return [
                'guest'   => $guest,
                'created' => false,
            ];
        }

        // Validate data for creation.
        if ( ! $this->validator->validateCreate( $data ) ) {
            throw new \RuntimeException(
                implode( ' ', $this->validator->getAllErrors() )
            );
        }

        $guest = $this->createGuest( $data );

        return [
            'guest'   => $guest,
            'created' => true,
        ];
    }

    /**
     * Create a new guest profile.
     *
     * @throws \RuntimeException If validation or creation fails.
     */
    public function createGuest( array $data ): Guest {
        $sanitized = $this->sanitizeGuestData( $data );

        $guest = $this->repository->create( $sanitized );

        if ( ! $guest ) {
            throw new \RuntimeException( __( 'Failed to create guest profile.', 'venezia-hotel' ) );
        }

        $this->events->dispatch( 'guests/created', $guest );

        return $guest;
    }

    /**
     * Update an existing guest profile.
     *
     * @param int   $guest_id The ID of the guest to update.
     * @param array $data     The fields to update.
     * @throws \RuntimeException If the guest is not found or validation fails.
     */
    public function updateGuestProfile( int $guest_id, array $data ): Guest {
        $guest = $this->repository->find( $guest_id );

        if ( ! $guest ) {
            throw new \RuntimeException(
                sprintf( __( 'Guest with ID %d not found.', 'venezia-hotel' ), $guest_id )
            );
        }

        if ( ! $this->validator->validateUpdate( $data, $guest_id ) ) {
            throw new \RuntimeException(
                implode( ' ', $this->validator->getAllErrors() )
            );
        }

        $sanitized = $this->sanitizeGuestData( $data );

        $updated = $this->repository->update( $guest_id, $sanitized );

        if ( ! $updated ) {
            throw new \RuntimeException( __( 'Failed to update guest profile.', 'venezia-hotel' ) );
        }

        $guest = $this->repository->find( $guest_id );

        $this->events->dispatch( 'guests/updated', $guest );

        return $guest;
    }

    /**
     * Get the full guest history and statistics.
     *
     * @param int $guest_id Guest ID.
     * @return array{
     *     guest: Guest,
     *     stats: array{ total_bookings: int, total_spent: float, total_nights: int, last_stay: ?string }
     * }
     * @throws \RuntimeException If the guest is not found.
     */
    public function getGuestHistory( int $guest_id ): array {
        $guest = $this->repository->find( $guest_id );

        if ( ! $guest ) {
            throw new \RuntimeException(
                sprintf( __( 'Guest with ID %d not found.', 'venezia-hotel' ), $guest_id )
            );
        }

        $stats = [
            'total_bookings' => $guest->total_bookings ?? 0,
            'total_spent'    => $guest->total_spent ?? 0.0,
            'total_nights'   => $guest->total_nights ?? 0,
            'last_stay'      => $guest->last_stay,
        ];

        /**
         * Filter the guest history data, allowing other modules
         * (e.g., Bookings) to append related records.
         *
         * @param array $history History data array.
         * @param Guest $guest   The guest model instance.
         */
        $history = $this->events->filter( 'guests/history', [
            'guest' => $guest,
            'stats' => $stats,
        ], $guest );

        return $history;
    }

    /**
     * Increment the guest's booking count and total spent.
     *
     * Called when a new booking is confirmed for this guest.
     *
     * @param int   $guest_id     Guest ID.
     * @param float $amount_spent Amount spent on the booking.
     */
    public function incrementBookingCount( int $guest_id, float $amount_spent = 0 ): bool {
        $result = $this->repository->incrementBookingCount( $guest_id, $amount_spent );

        if ( $result ) {
            $guest = $this->repository->find( $guest_id );
            $this->events->dispatch( 'guests/booking_count_incremented', $guest, $amount_spent );
        }

        return $result;
    }

    /**
     * Update guest statistics after checkout.
     *
     * Called when a guest checks out, to update total nights and last stay date.
     *
     * @param int    $guest_id Guest ID.
     * @param int    $nights   Number of nights stayed.
     * @param string $checkout Checkout date (Y-m-d).
     */
    public function updateAfterCheckout( int $guest_id, int $nights, string $checkout ): bool {
        $result = $this->repository->updateAfterCheckout( $guest_id, $nights, $checkout );

        if ( $result ) {
            $guest = $this->repository->find( $guest_id );
            $this->events->dispatch( 'guests/checked_out', $guest, $nights, $checkout );
        }

        return $result;
    }

    /**
     * Sanitize guest data before storage.
     *
     * @param array $data Raw guest data.
     * @return array Sanitized data.
     */
    private function sanitizeGuestData( array $data ): array {
        $sanitized = [];

        $text_fields = [
            'first_name', 'last_name', 'nationality', 'id_type', 'id_number',
            'gender', 'address', 'city', 'country', 'company', 'language',
        ];

        foreach ( $text_fields as $field ) {
            if ( array_key_exists( $field, $data ) ) {
                $sanitized[ $field ] = sanitize_text_field( $data[ $field ] );
            }
        }

        if ( array_key_exists( 'email', $data ) ) {
            $sanitized['email'] = sanitize_email( $data['email'] );
        }

        if ( array_key_exists( 'phone', $data ) ) {
            $sanitized['phone'] = sanitize_text_field( $data['phone'] );
        }

        if ( array_key_exists( 'phone_alt', $data ) ) {
            $sanitized['phone_alt'] = sanitize_text_field( $data['phone_alt'] );
        }

        if ( array_key_exists( 'date_of_birth', $data ) ) {
            $sanitized['date_of_birth'] = sanitize_text_field( $data['date_of_birth'] );
        }

        if ( array_key_exists( 'notes', $data ) ) {
            $sanitized['notes'] = sanitize_textarea_field( $data['notes'] );
        }

        if ( array_key_exists( 'tags', $data ) ) {
            $tags = $data['tags'];
            if ( is_string( $tags ) ) {
                $tags = json_decode( $tags, true );
            }
            $sanitized['tags'] = is_array( $tags )
                ? array_map( 'sanitize_text_field', $tags )
                : [];
        }

        if ( array_key_exists( 'wp_user_id', $data ) ) {
            $sanitized['wp_user_id'] = absint( $data['wp_user_id'] ) ?: null;
        }

        return $sanitized;
    }
}
