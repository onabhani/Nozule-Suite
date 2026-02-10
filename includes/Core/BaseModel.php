<?php

namespace Venezia\Core;

/**
 * Base Model class for data objects.
 */
class BaseModel {

    protected array $attributes = [];

    public function __construct( array $attributes = [] ) {
        $this->fill( $attributes );
    }

    /**
     * Fill attributes from an array.
     */
    public function fill( array $attributes ): static {
        foreach ( $attributes as $key => $value ) {
            $this->attributes[ $key ] = $value;
        }
        return $this;
    }

    /**
     * Get an attribute.
     *
     * @return mixed
     */
    public function __get( string $name ) {
        return $this->attributes[ $name ] ?? null;
    }

    /**
     * Set an attribute.
     *
     * @param mixed $value
     */
    public function __set( string $name, $value ): void {
        $this->attributes[ $name ] = $value;
    }

    /**
     * Check if attribute exists.
     */
    public function __isset( string $name ): bool {
        return isset( $this->attributes[ $name ] );
    }

    /**
     * Convert to array.
     */
    public function toArray(): array {
        return $this->attributes;
    }

    /**
     * Create from database row (stdClass).
     */
    public static function fromRow( object $row ): static {
        return new static( (array) $row );
    }

    /**
     * Create collection from database rows.
     *
     * @return static[]
     */
    public static function fromRows( array $rows ): array {
        return array_map( fn( $row ) => static::fromRow( $row ), $rows );
    }
}
