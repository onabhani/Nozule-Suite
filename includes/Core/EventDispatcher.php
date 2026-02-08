<?php

namespace Venezia\Core;

/**
 * Event Dispatcher wrapping WordPress action/filter hooks.
 */
class EventDispatcher {

    /**
     * Dispatch an event.
     *
     * @param mixed ...$args
     */
    public function dispatch( string $event, ...$args ): void {
        do_action( 'venezia/' . $event, ...$args );
    }

    /**
     * Listen to an event.
     */
    public function listen( string $event, callable $listener, int $priority = 10 ): void {
        add_action( 'venezia/' . $event, $listener, $priority, 10 );
    }

    /**
     * Apply filters.
     *
     * @param mixed $value
     * @param mixed ...$args
     * @return mixed
     */
    public function filter( string $filter, $value, ...$args ) {
        return apply_filters( 'venezia/' . $filter, $value, ...$args );
    }

    /**
     * Register a filter listener.
     */
    public function addFilter( string $filter, callable $callback, int $priority = 10 ): void {
        add_filter( 'venezia/' . $filter, $callback, $priority, 10 );
    }
}
