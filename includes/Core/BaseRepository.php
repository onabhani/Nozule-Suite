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

    /** Whether to auto-manage created_at / updated_at columns. */
    protected bool $timestamps = true;

    /** When set, queries are scoped to this property. */
    protected ?int $propertyFilter = null;

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
     * Return a clone of this repository scoped to a single property.
     */
    public function scopeToProperty( ?int $propertyId ): static {
        $clone                 = clone $this;
        $clone->propertyFilter = $propertyId;
        return $clone;
    }

    /**
     * Get the current property filter value.
     */
    public function getPropertyFilter(): ?int {
        return $this->propertyFilter;
    }

    /**
     * Append a property_id filter to an existing SQL fragment.
     *
     * @param string   $sql    SQL string ending with a WHERE clause (or ready for AND).
     * @param array    $args   Bind-parameter array (modified by reference).
     * @param string   $column Column name to filter on.
     * @return string  The (possibly extended) SQL string.
     */
    protected function applyPropertyScope( string $sql, array &$args, string $column = 'property_id' ): string {
        if ( $this->propertyFilter !== null ) {
            $sql   .= " AND {$column} = %d";
            $args[] = $this->propertyFilter;
        }
        return $sql;
    }

    /**
     * Find a record by ID.
     *
     * Respects property scope when set, preventing cross-property data access.
     */
    public function find( int $id ): ?BaseModel {
        $table = $this->tableName();
        $args  = [ $id ];
        $sql   = "SELECT * FROM {$table} WHERE id = %d";
        $sql   = $this->applyPropertyScope( $sql, $args );
        $row   = $this->db->getRow( $sql, ...$args );
        return $row ? $this->model::fromRow( $row ) : null;
    }

    /**
     * Find a record by ID or throw.
     */
    public function findOrFail( int $id ): BaseModel {
        $result = $this->find( $id );
        if ( ! $result ) {
            throw new \RuntimeException( __( 'Record not found.', 'nozule' ) );
        }
        return $result;
    }

    /**
     * Get all records.
     *
     * @return BaseModel[]
     */
    public function all( string $orderBy = 'id', string $order = 'ASC', int $limit = 1000 ): array {
        $table   = $this->tableName();
        $orderBy = sanitize_sql_orderby( "{$orderBy} {$order}" ) ?: 'id ASC';
        $limit   = max( 1, min( (int) $limit, 1000 ) );
        $rows    = $this->db->getResults( "SELECT * FROM {$table} ORDER BY {$orderBy} LIMIT %d", $limit );
        return $this->model::fromRows( $rows );
    }

    /**
     * Create a record.
     *
     * @return BaseModel|false
     */
    public function create( array $data ) {
        if ( $this->timestamps ) {
            $now = current_time( 'mysql', true );
            $data['created_at'] = $data['created_at'] ?? $now;
            $data['updated_at'] = $data['updated_at'] ?? $now;
        }

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
        if ( $this->timestamps ) {
            $data['updated_at'] = current_time( 'mysql', true );
        }

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
                // Sanitize column name to prevent SQL injection via array keys.
                if ( ! preg_match( '/^[a-zA-Z_][a-zA-Z0-9_]*$/', $col ) ) {
                    throw new \InvalidArgumentException( "Invalid column name in where clause." );
                }
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
