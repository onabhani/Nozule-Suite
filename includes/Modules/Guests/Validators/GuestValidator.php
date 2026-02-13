<?php

namespace Nozule\Modules\Guests\Validators;

use Nozule\Core\BaseValidator;
use Nozule\Modules\Guests\Repositories\GuestRepository;

/**
 * Validator for guest profile data.
 */
class GuestValidator extends BaseValidator {

    private GuestRepository $repository;

    public function __construct( GuestRepository $repository ) {
        $this->repository = $repository;
    }

    /**
     * Validate data for creating a new guest.
     */
    public function validateCreate( array $data ): bool {
        $rules = $this->getBaseRules();

        // Email is required and must be unique for creation.
        $rules['email'][] = 'uniqueEmail';

        return $this->validate( $data, $rules );
    }

    /**
     * Validate data for updating an existing guest.
     *
     * @param int $guest_id The ID of the guest being updated.
     */
    public function validateUpdate( array $data, int $guest_id ): bool {
        $this->errors = [];

        $rules = $this->getUpdateRules( $data );

        // Validate with standard rules first.
        $valid = $this->validate( $data, $rules );

        // Check email uniqueness if email is being updated.
        if ( isset( $data['email'] ) ) {
            $existing = $this->repository->findByEmail( $data['email'] );
            if ( $existing && $existing->id !== $guest_id ) {
                $this->errors['email'][] = __( 'A guest with this email address already exists.', 'nozule' );
                $valid = false;
            }
        }

        return $valid;
    }

    /**
     * Get the base validation rules for guest creation.
     *
     * @return array<string, array>
     */
    private function getBaseRules(): array {
        return [
            'first_name' => [ 'required', 'min' => 1, 'max' => 100 ],
            'last_name'  => [ 'required', 'min' => 1, 'max' => 100 ],
            'email'      => [ 'required', 'email', 'max' => 255 ],
            'phone'      => [ 'required', 'phone' ],
        ];
    }

    /**
     * Get validation rules for partial updates (only validate provided fields).
     *
     * @return array<string, array>
     */
    private function getUpdateRules( array $data ): array {
        $rules     = [];
        $all_rules = [
            'first_name'    => [ 'min' => 1, 'max' => 100 ],
            'last_name'     => [ 'min' => 1, 'max' => 100 ],
            'email'         => [ 'email', 'max' => 255 ],
            'phone'         => [ 'phone' ],
            'phone_alt'     => [ 'phone' ],
            'date_of_birth' => [ 'date' ],
            'gender'        => [ 'in' => [ 'male', 'female', 'other' ] ],
            'id_type'       => [ 'in' => [ 'passport', 'national_id', 'driving_license', 'other' ] ],
            'language'      => [ 'max' => 10 ],
            'nationality'   => [ 'max' => 100 ],
            'city'          => [ 'max' => 100 ],
            'country'       => [ 'max' => 100 ],
            'company'       => [ 'max' => 255 ],
        ];

        foreach ( $all_rules as $field => $field_rules ) {
            if ( array_key_exists( $field, $data ) ) {
                $rules[ $field ] = $field_rules;
            }
        }

        // If first_name or last_name are provided in an update, they must not be empty.
        if ( isset( $data['first_name'] ) ) {
            array_unshift( $rules['first_name'], 'required' );
        }
        if ( isset( $data['last_name'] ) ) {
            array_unshift( $rules['last_name'], 'required' );
        }
        if ( isset( $data['email'] ) ) {
            array_unshift( $rules['email'], 'required' );
        }

        return $rules;
    }

    /**
     * Custom validation: ensure email is unique in the guests table.
     *
     * @param mixed $value
     * @param mixed $param
     */
    protected function validateUniqueEmail( string $field, $value, $param = null, array $data = [] ): ?string {
        if ( empty( $value ) ) {
            return null;
        }

        $existing = $this->repository->findByEmail( $value );

        if ( $existing ) {
            return __( 'A guest with this email address already exists.', 'nozule' );
        }

        return null;
    }
}
