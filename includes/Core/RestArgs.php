<?php

namespace Nozule\Core;

/**
 * Common REST API argument definitions.
 *
 * Replaces the identical getIdArgs() and getListArgs() methods
 * that were duplicated across 12+ controllers.
 */
class RestArgs {

    /**
     * Standard ID path parameter (required, positive integer).
     *
     * Usage in route registration:
     *   'args' => RestArgs::id()
     *
     * @return array
     */
    public static function id(): array {
        return [
            'id' => [
                'required'          => true,
                'validate_callback' => fn( $value ) => is_numeric( $value ) && $value > 0,
                'sanitize_callback' => 'absint',
            ],
        ];
    }

    /**
     * Standard pagination + sorting arguments for list endpoints.
     *
     * Usage in route registration:
     *   'args' => array_merge( RestArgs::paginationArgs( ['id', 'name', 'created_at'] ), $moduleSpecificArgs )
     *
     * @param string[] $orderbyColumns Allowed columns for sorting.
     * @return array
     */
    public static function paginationArgs( array $orderbyColumns = [ 'id', 'created_at' ] ): array {
        return [
            'search'   => [
                'type'              => 'string',
                'default'           => '',
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'orderby'  => [
                'type'    => 'string',
                'default' => 'created_at',
                'enum'    => $orderbyColumns,
            ],
            'order'    => [
                'type'    => 'string',
                'default' => 'DESC',
                'enum'    => [ 'ASC', 'DESC' ],
            ],
            'per_page' => [
                'type'              => 'integer',
                'default'           => 20,
                'minimum'           => 1,
                'maximum'           => 100,
                'sanitize_callback' => 'absint',
            ],
            'page'     => [
                'type'              => 'integer',
                'default'           => 1,
                'minimum'           => 1,
                'sanitize_callback' => 'absint',
            ],
        ];
    }
}
