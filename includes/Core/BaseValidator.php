<?php

namespace Venezia\Core;

/**
 * Base Validator with common validation rules.
 */
class BaseValidator {

    protected array $errors = [];

    /**
     * Validate data against rules.
     *
     * @param array<string, array> $rules
     * @return bool True if valid.
     */
    public function validate( array $data, array $rules ): bool {
        $this->errors = [];

        foreach ( $rules as $field => $fieldRules ) {
            $value = $data[ $field ] ?? null;

            foreach ( $fieldRules as $rule => $param ) {
                if ( is_int( $rule ) ) {
                    $rule  = $param;
                    $param = true;
                }

                $method = 'validate' . ucfirst( $rule );
                if ( method_exists( $this, $method ) ) {
                    $error = $this->$method( $field, $value, $param, $data );
                    if ( $error ) {
                        $this->errors[ $field ][] = $error;
                    }
                }
            }
        }

        return empty( $this->errors );
    }

    /**
     * Get validation errors.
     */
    public function getErrors(): array {
        return $this->errors;
    }

    /**
     * Get first error for a field.
     */
    public function getFirstError( string $field ): ?string {
        return $this->errors[ $field ][0] ?? null;
    }

    /**
     * Get all errors as flat array.
     */
    public function getAllErrors(): array {
        $all = [];
        foreach ( $this->errors as $field => $errors ) {
            foreach ( $errors as $error ) {
                $all[] = $error;
            }
        }
        return $all;
    }

    // Validation methods

    protected function validateRequired( string $field, $value ): ?string {
        if ( $value === null || $value === '' || $value === [] ) {
            return sprintf( __( '%s is required.', 'venezia-hotel' ), $field );
        }
        return null;
    }

    protected function validateEmail( string $field, $value ): ?string {
        if ( $value && ! is_email( $value ) ) {
            return sprintf( __( '%s must be a valid email address.', 'venezia-hotel' ), $field );
        }
        return null;
    }

    protected function validatePhone( string $field, $value ): ?string {
        if ( $value && ! preg_match( '/^[\+]?[0-9\s\-\(\)]{7,20}$/', $value ) ) {
            return sprintf( __( '%s must be a valid phone number.', 'venezia-hotel' ), $field );
        }
        return null;
    }

    protected function validateDate( string $field, $value ): ?string {
        if ( $value && ! strtotime( $value ) ) {
            return sprintf( __( '%s must be a valid date.', 'venezia-hotel' ), $field );
        }
        return null;
    }

    protected function validateFutureDate( string $field, $value ): ?string {
        if ( $value && strtotime( $value ) < strtotime( 'today' ) ) {
            return sprintf( __( '%s must be a future date.', 'venezia-hotel' ), $field );
        }
        return null;
    }

    protected function validateMin( string $field, $value, $param ): ?string {
        if ( is_numeric( $value ) && $value < $param ) {
            return sprintf( __( '%s must be at least %s.', 'venezia-hotel' ), $field, $param );
        }
        if ( is_string( $value ) && strlen( $value ) < $param ) {
            return sprintf( __( '%s must be at least %s characters.', 'venezia-hotel' ), $field, $param );
        }
        return null;
    }

    protected function validateMax( string $field, $value, $param ): ?string {
        if ( is_numeric( $value ) && $value > $param ) {
            return sprintf( __( '%s must not exceed %s.', 'venezia-hotel' ), $field, $param );
        }
        if ( is_string( $value ) && strlen( $value ) > $param ) {
            return sprintf( __( '%s must not exceed %s characters.', 'venezia-hotel' ), $field, $param );
        }
        return null;
    }

    protected function validateMaxLength( string $field, $value, $param ): ?string {
        if ( is_string( $value ) && strlen( $value ) > $param ) {
            return sprintf( __( '%s must not exceed %s characters.', 'venezia-hotel' ), $field, $param );
        }
        return null;
    }

    protected function validateMinLength( string $field, $value, $param ): ?string {
        if ( is_string( $value ) && strlen( $value ) < $param ) {
            return sprintf( __( '%s must be at least %s characters.', 'venezia-hotel' ), $field, $param );
        }
        return null;
    }

    protected function validateIn( string $field, $value, $param ): ?string {
        $allowed = is_array( $param ) ? $param : explode( ',', $param );
        if ( $value && ! in_array( $value, $allowed, true ) ) {
            return sprintf( __( '%s must be one of: %s.', 'venezia-hotel' ), $field, implode( ', ', $allowed ) );
        }
        return null;
    }

    protected function validateNumeric( string $field, $value ): ?string {
        if ( $value !== null && $value !== '' && ! is_numeric( $value ) ) {
            return sprintf( __( '%s must be a number.', 'venezia-hotel' ), $field );
        }
        return null;
    }

    protected function validateInteger( string $field, $value ): ?string {
        if ( $value !== null && $value !== '' && filter_var( $value, FILTER_VALIDATE_INT ) === false ) {
            return sprintf( __( '%s must be an integer.', 'venezia-hotel' ), $field );
        }
        return null;
    }

    protected function validateUrl( string $field, $value ): ?string {
        if ( $value && ! filter_var( $value, FILTER_VALIDATE_URL ) ) {
            return sprintf( __( '%s must be a valid URL.', 'venezia-hotel' ), $field );
        }
        return null;
    }

    protected function validateSlug( string $field, $value ): ?string {
        if ( $value && ! preg_match( '/^[a-z0-9]+(?:[-_][a-z0-9]+)*$/', $value ) ) {
            return sprintf( __( '%s must be a valid slug (lowercase letters, numbers, hyphens, underscores).', 'venezia-hotel' ), $field );
        }
        return null;
    }
}
