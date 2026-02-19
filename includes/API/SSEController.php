<?php

namespace Nozule\API;

use Nozule\Core\Container;
use Nozule\Core\Database;
use WP_REST_Request;

/**
 * Server-Sent Events controller.
 *
 * Provides a long-lived HTTP stream that pushes real-time updates
 * (new bookings, cancellations, check-ins, check-outs) to the admin
 * dashboard using the SSE protocol.
 *
 * Events are stored in a WordPress transient-based queue so that
 * they survive between poll cycles and can be consumed exactly once.
 */
class SSEController {

    private const NAMESPACE       = 'nozule/v1';
    private const TRANSIENT_KEY   = 'nzl_sse_events';
    private const HEARTBEAT_SEC   = 15;
    private const MAX_RUNTIME_SEC = 55;

    private Container $container;

    public function __construct( Container $container ) {
        $this->container = $container;
    }

    /**
     * Register the SSE route.
     */
    public function registerRoutes(): void {
        register_rest_route( self::NAMESPACE, '/admin/events/stream', [
            'methods'             => 'GET',
            'callback'            => [ $this, 'stream' ],
            'permission_callback' => fn() => current_user_can( 'nzl_staff' ),
        ] );
    }

    /**
     * Handle the SSE stream request.
     *
     * Sets proper headers, then enters a loop that checks the transient
     * event queue and flushes any pending events to the client.  A
     * heartbeat comment is sent every HEARTBEAT_SEC seconds to keep the
     * connection alive.  The loop exits after MAX_RUNTIME_SEC so the
     * browser can reconnect cleanly.
     */
    public function stream( WP_REST_Request $request ): void {

        // Prevent output buffering from interfering.
        while ( ob_get_level() ) {
            ob_end_clean();
        }

        // SSE headers
        header( 'Content-Type: text/event-stream' );
        header( 'Cache-Control: no-cache' );
        header( 'Connection: keep-alive' );
        header( 'X-Accel-Buffering: no' ); // nginx

        // Disable PHP time limit for this request.
        set_time_limit( 0 );
        ignore_user_abort( false );

        $last_event_id = $request->get_header( 'Last-Event-ID' ) ?: '0';
        $start_time    = time();
        $event_counter = (int) $last_event_id;

        // Initial connection event.
        $this->sendEvent( 'connected', [
            'message'   => 'SSE connection established',
            'timestamp' => current_time( 'c' ),
        ], ++$event_counter );

        if ( function_exists( 'flush' ) ) {
            flush();
        }

        while ( true ) {
            // Check time limit.
            if ( ( time() - $start_time ) >= self::MAX_RUNTIME_SEC ) {
                $this->sendEvent( 'timeout', [
                    'message' => 'Stream timeout, please reconnect',
                ], ++$event_counter );
                break;
            }

            // Check for client disconnect.
            if ( connection_aborted() ) {
                break;
            }

            // Consume queued events.
            $events = $this->consumeEvents();

            foreach ( $events as $event ) {
                $this->sendEvent(
                    $event['type'] ?? 'message',
                    $event['data'] ?? [],
                    ++$event_counter
                );
            }

            // Heartbeat.
            if ( empty( $events ) ) {
                $this->sendComment( 'heartbeat ' . current_time( 'c' ) );
            }

            if ( function_exists( 'flush' ) ) {
                flush();
            }

            sleep( self::HEARTBEAT_SEC );
        }

        exit;
    }

    // ------------------------------------------------------------------
    // Event queue helpers
    // ------------------------------------------------------------------

    /**
     * Push an event onto the transient queue.
     *
     * This is meant to be called from other parts of the plugin (e.g.
     * event listeners) to enqueue real-time updates.
     *
     * @param string $type  Event type: new_booking, cancellation, check_in, check_out, etc.
     * @param array  $data  Arbitrary payload.
     */
    public static function pushEvent( string $type, array $data ): void {
        $events   = get_transient( self::TRANSIENT_KEY ) ?: [];
        $events[] = [
            'type'      => $type,
            'data'      => $data,
            'timestamp' => current_time( 'c' ),
        ];

        // Keep only the last 100 events to avoid unbounded growth.
        if ( count( $events ) > 100 ) {
            $events = array_slice( $events, -100 );
        }

        set_transient( self::TRANSIENT_KEY, $events, HOUR_IN_SECONDS );
    }

    /**
     * Consume (read and clear) all queued events.
     *
     * @return array<int, array{type: string, data: array, timestamp: string}>
     */
    private function consumeEvents(): array {
        $events = get_transient( self::TRANSIENT_KEY );

        if ( empty( $events ) ) {
            return [];
        }

        // Clear the queue atomically.
        delete_transient( self::TRANSIENT_KEY );

        return $events;
    }

    // ------------------------------------------------------------------
    // SSE formatting helpers
    // ------------------------------------------------------------------

    /**
     * Send an SSE event.
     */
    private function sendEvent( string $type, array $data, int $id ): void {
        echo "id: {$id}\n";
        echo "event: {$type}\n";
        echo 'data: ' . wp_json_encode( $data ) . "\n\n";
    }

    /**
     * Send an SSE comment (used for heartbeats).
     */
    private function sendComment( string $comment ): void {
        echo ": {$comment}\n\n";
    }
}
