<?php
/**
 * Cache handler for Magic Levels.
 *
 * @package PMPro_Magic_Levels
 * @since 1.0.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * PMPRO_Magic_Levels_Cache class.
 *
 * Handles three-tier caching (memory, transient, database).
 *
 * @since 1.0.0
 */
class PMPRO_Magic_Levels_Cache {

	/**
	 * Memory cache (static variable).
	 *
	 * @since 1.0.0
	 * @var array
	 */
	private static $memory_cache = array();

	/**
	 * Generate cache key from level parameters.
	 *
	 * @since 1.0.0
	 *
	 * @param array $params Level parameters.
	 * @return string Cache key.
	 */
	public static function generate_key( $params ) {
		$key_data = array(
			'name'              => isset( $params['name'] ) ? $params['name'] : '',
			'billing_amount'    => isset( $params['billing_amount'] ) ? $params['billing_amount'] : 0,
			'cycle_number'      => isset( $params['cycle_number'] ) ? $params['cycle_number'] : 0,
			'cycle_period'      => isset( $params['cycle_period'] ) ? $params['cycle_period'] : '',
			'initial_payment'   => isset( $params['initial_payment'] ) ? $params['initial_payment'] : 0,
			'trial_amount'      => isset( $params['trial_amount'] ) ? $params['trial_amount'] : 0,
			'trial_limit'       => isset( $params['trial_limit'] ) ? $params['trial_limit'] : 0,
			'billing_limit'     => isset( $params['billing_limit'] ) ? $params['billing_limit'] : 0,
			'expiration_number' => isset( $params['expiration_number'] ) ? $params['expiration_number'] : 0,
			'expiration_period' => isset( $params['expiration_period'] ) ? $params['expiration_period'] : '',
		);

		return 'pmpro_magic_level_' . md5( wp_json_encode( $key_data ) );
	}

	/**
	 * Get from cache.
	 *
	 * @since 1.0.0
	 *
	 * @param string $cache_key Cache key.
	 * @return mixed Cached value or false if not found.
	 */
	public static function get( $cache_key ) {
		// Check if caching is enabled.
		if ( ! apply_filters( 'pmpro_magic_levels_enable_cache', true ) ) {
			return false;
		}

		// Check memory cache first.
		if ( isset( self::$memory_cache[ $cache_key ] ) ) {
			return self::$memory_cache[ $cache_key ];
		}

		// Check transient/object cache.
		$cache_method = apply_filters( 'pmpro_magic_levels_cache_method', 'transient' );

		if ( 'transient' === $cache_method ) {
			$value = get_transient( $cache_key );
		} elseif ( 'object' === $cache_method ) {
			$value = wp_cache_get( $cache_key, 'pmpro_magic_levels' );
		} else {
			return false;
		}

		// Store in memory cache if found.
		if ( false !== $value ) {
			self::$memory_cache[ $cache_key ] = $value;
		}

		return $value;
	}

	/**
	 * Set cache.
	 *
	 * @since 1.0.0
	 *
	 * @param string $cache_key Cache key.
	 * @param mixed  $value     Value to cache.
	 * @return bool True on success, false on failure.
	 */
	public static function set( $cache_key, $value ) {
		// Check if caching is enabled.
		if ( ! apply_filters( 'pmpro_magic_levels_enable_cache', true ) ) {
			return false;
		}

		// Store in memory cache.
		self::$memory_cache[ $cache_key ] = $value;

		// Store in transient/object cache.
		$cache_method   = apply_filters( 'pmpro_magic_levels_cache_method', 'transient' );
		$cache_duration = apply_filters( 'pmpro_magic_levels_cache_duration', HOUR_IN_SECONDS );

		if ( 'transient' === $cache_method ) {
			return set_transient( $cache_key, $value, $cache_duration );
		} elseif ( 'object' === $cache_method ) {
			return wp_cache_set( $cache_key, $value, 'pmpro_magic_levels', $cache_duration );
		}

		return false;
	}

	/**
	 * Clear all caches.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public static function clear_all() {
		global $wpdb;

		// Clear memory cache.
		self::$memory_cache = array();

		// Clear transients.
		$wpdb->query(
			"DELETE FROM {$wpdb->options} 
			WHERE option_name LIKE '_transient_pmpro_magic_level_%' 
			OR option_name LIKE '_transient_timeout_pmpro_magic_level_%'"
		);

		// Clear object cache if using it.
		$cache_method = apply_filters( 'pmpro_magic_levels_cache_method', 'transient' );
		if ( 'object' === $cache_method ) {
			wp_cache_flush();
		}

		do_action( 'pmpro_magic_levels_cache_cleared' );
	}

	/**
	 * Clear specific cache entry.
	 *
	 * @since 1.0.0
	 *
	 * @param string $cache_key Cache key.
	 * @return void
	 */
	public static function clear( $cache_key ) {
		// Clear from memory.
		unset( self::$memory_cache[ $cache_key ] );

		// Clear from transient/object cache.
		$cache_method = apply_filters( 'pmpro_magic_levels_cache_method', 'transient' );

		if ( 'transient' === $cache_method ) {
			delete_transient( $cache_key );
		} elseif ( 'object' === $cache_method ) {
			wp_cache_delete( $cache_key, 'pmpro_magic_levels' );
		}
	}
}
