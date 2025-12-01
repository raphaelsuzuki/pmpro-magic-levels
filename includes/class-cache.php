<?php
/**
 * Cache handler for Magic Levels
 */

defined( 'ABSPATH' ) || exit;

class PMPRO_Magic_Levels_Cache {
    
    /**
     * Memory cache (static variable)
     */
    private static $memory_cache = [];

    /**
     * Generate cache key from level parameters
     */
    public static function generate_key( $params ) {
        $key_data = [
            'name' => $params['name'] ?? '',
            'billing_amount' => $params['billing_amount'] ?? 0,
            'cycle_number' => $params['cycle_number'] ?? 0,
            'cycle_period' => $params['cycle_period'] ?? '',
            'initial_payment' => $params['initial_payment'] ?? 0,
            'trial_amount' => $params['trial_amount'] ?? 0,
            'trial_limit' => $params['trial_limit'] ?? 0,
            'billing_limit' => $params['billing_limit'] ?? 0,
            'expiration_number' => $params['expiration_number'] ?? 0,
            'expiration_period' => $params['expiration_period'] ?? '',
        ];
        
        return 'pmpro_magic_level_' . md5( serialize( $key_data ) );
    }

    /**
     * Get from cache
     */
    public static function get( $cache_key ) {
        // Check if caching is enabled
        if ( ! apply_filters( 'pmpro_magic_levels_enable_cache', true ) ) {
            return false;
        }

        // Check memory cache first
        if ( isset( self::$memory_cache[ $cache_key ] ) ) {
            return self::$memory_cache[ $cache_key ];
        }

        // Check transient/object cache
        $cache_method = apply_filters( 'pmpro_magic_levels_cache_method', 'transient' );
        
        if ( $cache_method === 'transient' ) {
            $value = get_transient( $cache_key );
        } elseif ( $cache_method === 'object' ) {
            $value = wp_cache_get( $cache_key, 'pmpro_magic_levels' );
        } else {
            return false;
        }

        // Store in memory cache if found
        if ( $value !== false ) {
            self::$memory_cache[ $cache_key ] = $value;
        }

        return $value;
    }

    /**
     * Set cache
     */
    public static function set( $cache_key, $value ) {
        // Check if caching is enabled
        if ( ! apply_filters( 'pmpro_magic_levels_enable_cache', true ) ) {
            return false;
        }

        // Store in memory cache
        self::$memory_cache[ $cache_key ] = $value;

        // Store in transient/object cache
        $cache_method = apply_filters( 'pmpro_magic_levels_cache_method', 'transient' );
        $cache_duration = apply_filters( 'pmpro_magic_levels_cache_duration', HOUR_IN_SECONDS );

        if ( $cache_method === 'transient' ) {
            return set_transient( $cache_key, $value, $cache_duration );
        } elseif ( $cache_method === 'object' ) {
            return wp_cache_set( $cache_key, $value, 'pmpro_magic_levels', $cache_duration );
        }

        return false;
    }

    /**
     * Clear all caches
     */
    public static function clear_all() {
        global $wpdb;

        // Clear memory cache
        self::$memory_cache = [];

        // Clear transients
        $wpdb->query( 
            "DELETE FROM {$wpdb->options} 
            WHERE option_name LIKE '_transient_pmpro_magic_level_%' 
            OR option_name LIKE '_transient_timeout_pmpro_magic_level_%'" 
        );

        // Clear object cache if using it
        $cache_method = apply_filters( 'pmpro_magic_levels_cache_method', 'transient' );
        if ( $cache_method === 'object' ) {
            wp_cache_flush();
        }

        do_action( 'pmpro_magic_levels_cache_cleared' );
    }

    /**
     * Clear specific cache entry
     */
    public static function clear( $cache_key ) {
        // Clear from memory
        unset( self::$memory_cache[ $cache_key ] );

        // Clear from transient/object cache
        $cache_method = apply_filters( 'pmpro_magic_levels_cache_method', 'transient' );
        
        if ( $cache_method === 'transient' ) {
            delete_transient( $cache_key );
        } elseif ( $cache_method === 'object' ) {
            wp_cache_delete( $cache_key, 'pmpro_magic_levels' );
        }
    }
}
