<?php

namespace Venezia\Core;

/**
 * Simple Dependency Injection Container.
 */
class Container {

    /** @var array<string, array{concrete: callable|string, singleton: bool}> */
    private array $bindings = [];

    /** @var array<string, object> */
    private array $instances = [];

    /**
     * Register a singleton binding.
     */
    public function singleton( string $abstract, ?callable $concrete = null ): void {
        $this->bindings[ $abstract ] = [
            'concrete'  => $concrete ?? $abstract,
            'singleton' => true,
        ];
    }

    /**
     * Register a binding.
     */
    public function bind( string $abstract, ?callable $concrete = null ): void {
        $this->bindings[ $abstract ] = [
            'concrete'  => $concrete ?? $abstract,
            'singleton' => false,
        ];
    }

    /**
     * Resolve a service from the container.
     *
     * @template T
     * @param class-string<T> $abstract
     * @return T
     */
    public function get( string $abstract ) {
        if ( isset( $this->instances[ $abstract ] ) ) {
            return $this->instances[ $abstract ];
        }

        $binding = $this->bindings[ $abstract ] ?? null;

        if ( ! $binding ) {
            return $this->resolve( $abstract );
        }

        $instance = is_callable( $binding['concrete'] )
            ? ( $binding['concrete'] )( $this )
            : $this->resolve( $binding['concrete'] );

        if ( $binding['singleton'] ) {
            $this->instances[ $abstract ] = $instance;
        }

        return $instance;
    }

    /**
     * Check if a binding exists.
     */
    public function has( string $abstract ): bool {
        return isset( $this->bindings[ $abstract ] ) || isset( $this->instances[ $abstract ] );
    }

    /**
     * Resolve a class by reflection.
     */
    private function resolve( string $class ): object {
        if ( ! class_exists( $class ) ) {
            throw new \RuntimeException( "Class {$class} not found in container." );
        }

        $reflector   = new \ReflectionClass( $class );
        $constructor = $reflector->getConstructor();

        if ( ! $constructor ) {
            return new $class();
        }

        $dependencies = [];
        foreach ( $constructor->getParameters() as $param ) {
            $type = $param->getType();
            if ( $type && ! $type->isBuiltin() ) {
                $dependencies[] = $this->get( $type->getName() );
            } elseif ( $param->isDefaultValueAvailable() ) {
                $dependencies[] = $param->getDefaultValue();
            } else {
                throw new \RuntimeException(
                    "Cannot resolve parameter \${$param->getName()} for {$class}."
                );
            }
        }

        return $reflector->newInstanceArgs( $dependencies );
    }
}
