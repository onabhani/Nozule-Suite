<?php

namespace Nozule\Core;

/**
 * Plugin logger.
 */
class Logger {

    private Database $db;

    public function __construct( Database $db ) {
        $this->db = $db;
    }

    /**
     * Log an info message.
     */
    public function info( string $message, array $context = [] ): void {
        $this->log( 'info', $message, $context );
    }

    /**
     * Log a warning message.
     */
    public function warning( string $message, array $context = [] ): void {
        $this->log( 'warning', $message, $context );
    }

    /**
     * Log an error message.
     */
    public function error( string $message, array $context = [] ): void {
        $this->log( 'error', $message, $context );
    }

    /**
     * Log a debug message.
     */
    public function debug( string $message, array $context = [] ): void {
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            $this->log( 'debug', $message, $context );
        }
    }

    /**
     * Write to WordPress debug log.
     */
    private function log( string $level, string $message, array $context = [] ): void {
        $formatted = sprintf(
            '[Nozule %s] %s %s',
            strtoupper( $level ),
            $message,
            ! empty( $context ) ? wp_json_encode( $context ) : ''
        );

        if ( defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
            error_log( $formatted ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
        }

        do_action( 'nozule/log', $level, $message, $context );
    }

    /**
     * Clean old log entries.
     */
    public function cleanOldEntries( int $days = 90 ): int {
        $table = $this->db->table( 'booking_logs' );
        return (int) $this->db->query(
            "DELETE FROM {$table} WHERE created_at < DATE_SUB(NOW(), INTERVAL %d DAY)",
            $days
        );
    }
}
