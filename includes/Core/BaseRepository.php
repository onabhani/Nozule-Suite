<?php

namespace Nozule\Core;

/**
 * Base Repository with common database operations.
 */
abstract class BaseRepository {

    protected Database $db;

    /** Table name without prefix (e.g., 'rooms'). */
    protected string $table;

    /** Model class name. */
    protected string $model;

    public function __construct( Database $db ) {
        $this->db = $db;
    }

    /**
     * Get the full table name with prefix.
     */
    protected function tableName(): string {
        return $this->db->table( $this->table );
    }

    /**
     * Find a record by ID.
     */
    public function find( int $id ): ?BaseModel {
        $table = $this->tableName();
        $row   = $this->db->getRow( "SELECT * FROM {$table} WHERE id = %d", $id );
        return $row ? $this->model::fromRow( $row ) : null;
    }

    /**
     * Find a record by ID or throw.
     */
    public function findOrFail( int $id ): BaseModel {
        $result = $this->find( $id );
        if ( ! $result ) {
            throw new \RuntimeException( "Record not found in {$this->table} with ID {$id}" );
        }
        return $result;
    }

    /**
     * Get all records.
     *
     * @return BaseModel[]
     */
    public function all( string $orderBy = 'id', string $order = 'ASC' ): array {
        $table   = $this->tableName();
        $orderBy = sanitize_sql_orderby( "{$orderBy} {$order}" ) ?: 'id ASC';
        $rows    = $this->db->getResults( "SELECT * FROM {$table} ORDER BY {$orderBy}" );
        return $this->model::fromRows( $rows );
    }

    /**
     * Create a record.
     *
     * @return BaseModel|false
     */
    public function create( array $data ) {
        $id = $this->db->insert( $this->table, $data );
        if ( $id === false ) {
            return false;
        }
        return $this->find( $id );
    }

    /**
     * Update a record by ID.
     */
    public function update( int $id, array $data ): bool {
        return $this->db->update( $this->table, $data, [ 'id' => $id ] ) !== false;
    }

    /**
     * Delete a record by ID.
     */
    public function delete( int $id ): bool {
        return $this->db->delete( $this->table, [ 'id' => $id ] ) !== false;
    }

    /**
     * Count records.
     */
    public function count( ?array $where = null ): int {
        $table = $this->tableName();
        if ( $where ) {
            $conditions = [];
            $values     = [];
            foreach ( $where as $col => $val ) {
                $conditions[] = "`{$col}` = %s";
                $values[]     = $val;
            }
            $where_clause = implode( ' AND ', $conditions );
            return (int) $this->db->getVar(
                "SELECT COUNT(*) FROM {$table} WHERE {$where_clause}",
                ...$values
            );
        }
        return (int) $this->db->getVar( "SELECT COUNT(*) FROM {$table}" );
    }

    /**
     * Begin transaction.
     */
    public function beginTransaction(): void {
        $this->db->beginTransaction();
    }

    /**
     * Commit transaction.
     */
    public function commit(): void {
        $this->db->commit();
    }

    /**
     * Rollback transaction.
     */
    public function rollback(): void {
        $this->db->rollback();
    }
}
