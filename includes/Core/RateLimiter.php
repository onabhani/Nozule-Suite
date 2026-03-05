<?php

namespace Nozule\Core;

class RateLimiter {

    public static function check(
        string $action,
        string $identifier,
        int $maxAttempts,
        int $windowSeconds
    ): bool {
        $key     = 'nzl_rl_' . md5( $action . '_' . $identifier );
        $data    = get_transient( $key );

        if ( $data === false ) {
            set_transient( $key, [ 'count' => 1, 'first' => time() ], $windowSeconds );
            return true;
        }

        // Window expired — reset
        if ( time() - $data['first'] >= $windowSeconds ) {
            set_transient( $key, [ 'count' => 1, 'first' => time() ], $windowSeconds );
            return true;
        }

        // Over limit
        if ( $data['count'] >= $maxAttempts ) {
            return false;
        }

        // Increment
        $data['count']++;
        set_transient( $key, $data, $windowSeconds - ( time() - $data['first'] ) );
        return true;
    }

    public static function getRemainingSeconds(
        string $action,
        string $identifier,
        int $windowSeconds = 60
    ): int {
        $key  = 'nzl_rl_' . md5( $action . '_' . $identifier );
        $data = get_transient( $key );
        if ( $data === false ) return 0;
        $elapsed = time() - $data['first'];
        return max( 0, $windowSeconds - $elapsed );
    }

    private static function getIdentifier( \WP_REST_Request $request ): string {
        $remote_addr = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

        // Only trust forwarded headers when the direct client is a configured trusted proxy.
        $trusted_proxies = apply_filters( 'nzl_trusted_proxies', [] );
        if ( is_array( $trusted_proxies ) && in_array( $remote_addr, $trusted_proxies, true ) ) {
            $forwarded = $request->get_header( 'x-forwarded-for' )
                ?? $request->get_header( 'x-real-ip' );
            if ( $forwarded ) {
                return sanitize_text_field( trim( explode( ',', $forwarded )[0] ) );
            }
        }

        return sanitize_text_field( $remote_addr );
    }

    public static function middleware(
        \WP_REST_Request $request,
        string $action,
        int $maxAttempts,
        int $windowSeconds
    ): ?\WP_REST_Response {
        $identifier = self::getIdentifier( $request );
        if ( ! self::check( $action, $identifier, $maxAttempts, $windowSeconds ) ) {
            $retry = self::getRemainingSeconds( $action, $identifier, $windowSeconds );
            return new \WP_REST_Response(
                [ 'success' => false, 'message' => 'Too many requests. Please try again later.' ],
                429,
                [ 'Retry-After' => $retry ]
            );
        }
        return null;
    }
}
