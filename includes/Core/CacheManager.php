<?php

namespace Nozule\Core;

/**
 * Cache Manager using WordPress object cache and transients.
 */
class CacheManager {

    private const GROUP = 'nozule';

    /**
     * Get a cached value.
     *
     * @return mixed|false
     */
    public function get( string $key ) {
        $value = wp_cache_get( $key, self::GROUP );
        if ( $value !== false ) {
            return $value;
        }

        // Fallback to transient only when no persistent object cache (Redis/Memcached).
        if ( ! wp_using_ext_object_cache() ) {
            return get_transient( 'nzl_' . $key );
        }

        return false;
    }

    /**
     * Set a cached value.
     *
     * When a persistent object cache (Redis/Memcached) is available, only
     * writes to the object cache — skipping the redundant transient write
     * that would hit wp_options on every set.
     *
     * @param mixed $value
     */
    public function set( string $key, $value, int $ttl = 300 ): bool {
        wp_cache_set( $key, $value, self::GROUP, $ttl );

        // Skip transient write when a persistent object cache handles it.
        if ( ! wp_using_ext_object_cache() ) {
            return set_transient( 'nzl_' . $key, $value, $ttl );
        }

        return true;
    }

    /**
     * Delete a cached value.
     */
    public function delete( string $key ): bool {
        wp_cache_delete( $key, self::GROUP );

        if ( ! wp_using_ext_object_cache() ) {
            return delete_transient( 'nzl_' . $key );
        }

        return true;
    }

    /**
     * Invalidate all cache entries matching a tag/prefix pattern.
     *
     * Clears the object cache group and removes transients with the
     * matching prefix. This replaces the previous version-counter approach
     * which did not work because cache keys did not incorporate the version.
     */
    public function invalidateTag( string $tag ): void {
        // Clear the WP object cache group if supported.
        if ( function_exists( 'wp_cache_flush_group' ) ) {
            wp_cache_flush_group( self::GROUP );
        }

        // Delete transients matching this tag prefix.
        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
                '_transient_nzl_' . $wpdb->esc_like( $tag ) . '%',
                '_transient_timeout_nzl_' . $wpdb->esc_like( $tag ) . '%'
            )
        );
    }

    /**
     * Flush all plugin cache.
     */
    public function flush(): void {
        if ( function_exists( 'wp_cache_flush_group' ) ) {
            wp_cache_flush_group( self::GROUP );
        }

        global $wpdb;
        $wpdb->query(
            "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_nzl_%' OR option_name LIKE '_transient_timeout_nzl_%'"
        );
    }
}
