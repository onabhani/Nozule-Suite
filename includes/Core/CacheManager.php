<?php

namespace Venezia\Core;

/**
 * Cache Manager using WordPress object cache and transients.
 */
class CacheManager {

    private const GROUP = 'venezia';

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

        // Fallback to transient
        return get_transient( 'vhm_' . $key );
    }

    /**
     * Set a cached value.
     *
     * @param mixed $value
     */
    public function set( string $key, $value, int $ttl = 300 ): bool {
        wp_cache_set( $key, $value, self::GROUP, $ttl );
        return set_transient( 'vhm_' . $key, $value, $ttl );
    }

    /**
     * Delete a cached value.
     */
    public function delete( string $key ): bool {
        wp_cache_delete( $key, self::GROUP );
        return delete_transient( 'vhm_' . $key );
    }

    /**
     * Invalidate all cache entries matching a tag pattern.
     */
    public function invalidateTag( string $tag ): void {
        // Increment a version counter for this tag
        $version_key = 'tag_version_' . $tag;
        $current     = (int) $this->get( $version_key );
        $this->set( $version_key, $current + 1, 86400 );

        // Clear the WP object cache group if supported
        if ( function_exists( 'wp_cache_flush_group' ) ) {
            wp_cache_flush_group( self::GROUP );
        }
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
            "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_vhm_%' OR option_name LIKE '_transient_timeout_vhm_%'"
        );
    }
}
