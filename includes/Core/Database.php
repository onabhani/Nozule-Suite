<?php

namespace Nozule\Core;

/**
 * Database wrapper for custom tables.
 */
class Database {

    private \wpdb $wpdb;

    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
    }

    /**
     * Get the WordPress database instance.
     */
    public function wpdb(): \wpdb {
        return $this->wpdb;
    }

    /**
     * Get table name with prefix.
     */
    public function table( string $name ): string {
        return $this->wpdb->prefix . 'nzl_' . $name;
    }

    /**
     * Insert a row.
     *
     * @return int|false The insert ID or false on failure.
     */
    public function insert( string $table, array $data, ?array $format = null ) {
        $result = $this->wpdb->insert( $this->table( $table ), $data, $format );
        return $result ? $this->wpdb->insert_id : false;
    }

    /**
     * Update rows.
     *
     * @return int|false Number of rows updated or false on error.
     */
    public function update( string $table, array $data, array $where, ?array $format = null, ?array $where_format = null ) {
        return $this->wpdb->update( $this->table( $table ), $data, $where, $format, $where_format );
    }

    /**
     * Delete rows.
     *
     * @return int|false Number of rows deleted or false on error.
     */
    public function delete( string $table, array $where, ?array $where_format = null ) {
        return $this->wpdb->delete( $this->table( $table ), $where, $where_format );
    }

    /**
     * Get a single row.
     */
    public function getRow( string $query, ...$args ): ?object {
        if ( ! empty( $args ) ) {
            $query = $this->wpdb->prepare( $query, ...$args );
        }
        return $this->wpdb->get_row( $query );
    }

    /**
     * Get multiple rows.
     */
    public function getResults( string $query, ...$args ): array {
        if ( ! empty( $args ) ) {
            $query = $this->wpdb->prepare( $query, ...$args );
        }
        return $this->wpdb->get_results( $query ) ?: [];
    }

    /**
     * Get a single value.
     */
    public function getVar( string $query, ...$args ) {
        if ( ! empty( $args ) ) {
            $query = $this->wpdb->prepare( $query, ...$args );
        }
        return $this->wpdb->get_var( $query );
    }

    /**
     * Run a raw query.
     */
    public function query( string $query, ...$args ) {
        if ( ! empty( $args ) ) {
            $query = $this->wpdb->prepare( $query, ...$args );
        }
        return $this->wpdb->query( $query );
    }

    /**
     * Begin a transaction.
     */
    public function beginTransaction(): void {
        $this->wpdb->query( 'START TRANSACTION' );
    }

    /**
     * Commit a transaction.
     */
    public function commit(): void {
        $this->wpdb->query( 'COMMIT' );
    }

    /**
     * Rollback a transaction.
     */
    public function rollback(): void {
        $this->wpdb->query( 'ROLLBACK' );
    }

    /**
     * Get the last error.
     */
    public function lastError(): string {
        return $this->wpdb->last_error;
    }

    /**
     * Get the last insert ID.
     */
    public function lastInsertId(): int {
        return (int) $this->wpdb->insert_id;
    }

    /**
     * Prepare a query safely.
     */
    public function prepare( string $query, ...$args ): string {
        return $this->wpdb->prepare( $query, ...$args );
    }
}
