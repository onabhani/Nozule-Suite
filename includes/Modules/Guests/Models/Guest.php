<?php

namespace Venezia\Modules\Guests\Models;

use Venezia\Core\BaseModel;

/**
 * Guest model representing a hotel guest profile.
 */
class Guest extends BaseModel {

    /**
     * Attributes that should be cast to specific types.
     *
     * @var array<string, string>
     */
    protected static array $casts = [
        'id'             => 'int',
        'wp_user_id'     => 'int',
        'total_bookings' => 'int',
        'total_nights'   => 'int',
        'total_spent'    => 'float',
    ];

    /**
     * Fill attributes, applying type casts.
     */
    public function fill( array $attributes ): static {
        foreach ( $attributes as $key => $value ) {
            if ( $value !== null && isset( static::$casts[ $key ] ) ) {
                $value = match ( static::$casts[ $key ] ) {
                    'int'   => (int) $value,
                    'float' => (float) $value,
                    default => $value,
                };
            }
            $this->attributes[ $key ] = $value;
        }
        return $this;
    }

    /**
     * Get the guest's computed full name.
     *
     * @return mixed
     */
    public function __get( string $name ) {
        if ( $name === 'full_name' ) {
            return $this->getFullName();
        }

        if ( $name === 'tags' ) {
            return $this->getTags();
        }

        return parent::__get( $name );
    }

    /**
     * Set an attribute with special handling for tags.
     *
     * @param mixed $value
     */
    public function __set( string $name, $value ): void {
        if ( $name === 'tags' && is_array( $value ) ) {
            $this->attributes['tags'] = wp_json_encode( $value );
            return;
        }

        parent::__set( $name, $value );
    }

    /**
     * Check if attribute exists, including computed attributes.
     */
    public function __isset( string $name ): bool {
        if ( $name === 'full_name' ) {
            return isset( $this->attributes['first_name'] ) || isset( $this->attributes['last_name'] );
        }

        return parent::__isset( $name );
    }

    /**
     * Get the guest's full name.
     */
    public function getFullName(): string {
        return trim(
            ( $this->attributes['first_name'] ?? '' ) . ' ' . ( $this->attributes['last_name'] ?? '' )
        );
    }

    /**
     * Get tags as an array, decoding from JSON if necessary.
     *
     * @return array<string>
     */
    public function getTags(): array {
        $raw = $this->attributes['tags'] ?? null;

        if ( is_array( $raw ) ) {
            return $raw;
        }

        if ( is_string( $raw ) && $raw !== '' ) {
            $decoded = json_decode( $raw, true );
            return is_array( $decoded ) ? $decoded : [];
        }

        return [];
    }

    /**
     * Check if the guest has a specific tag.
     */
    public function hasTag( string $tag ): bool {
        return in_array( $tag, $this->getTags(), true );
    }

    /**
     * Convert to array, including computed full_name and decoded tags.
     */
    public function toArray(): array {
        $data              = parent::toArray();
        $data['full_name'] = $this->getFullName();
        $data['tags']      = $this->getTags();

        return $data;
    }

    /**
     * Get fields suitable for database insertion/update (excludes computed fields).
     */
    public function toDatabaseArray(): array {
        $data = parent::toArray();

        // Encode tags as JSON for storage.
        if ( isset( $data['tags'] ) && is_array( $data['tags'] ) ) {
            $data['tags'] = wp_json_encode( $data['tags'] );
        }

        // Remove computed fields.
        unset( $data['full_name'] );

        return $data;
    }
}
