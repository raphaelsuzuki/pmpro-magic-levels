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
	 * Get cache method and duration.
	 *
	 * @since 1.0.0
	 * @return array Cache method and duration.
	 */
	private static function get_cache_config() {
		return array(
			'method'   => apply_filters( 'pmpro_magic_levels_cache_method', 'transient' ),
			'duration' => apply_filters( 'pmpro_magic_levels_cache_duration', HOUR_IN_SECONDS ),
		);
	}

	/**
	 * Get from cache using configured method.
	 *
	 * @since 1.0.0
	 * @param string $cache_key Cache key.
	 * @param string $method Cache method.
	 * @return mixed Cached value or false if not found.
	 */
	private static function get_from_cache( $cache_key, $method ) {
		if ( 'transient' === $method ) {
			return get_transient( $cache_key );
		} elseif ( 'object' === $method ) {
			return wp_cache_get( $cache_key, 'pmpro_magic_levels' );
		}
		return false;
	}

	/**
	 * Set cache using configured method.
	 *
	 * @since 1.0.0
	 * @param string $cache_key Cache key.
	 * @param mixed  $value     Value to cache.
	 * @param string $method    Cache method.
	 * @param int    $duration  Cache duration.
	 * @return bool True on success, false on failure.
	 */
	private static function set_cache( $cache_key, $value, $method, $duration ) {
		if ( 'transient' === $method ) {
			return set_transient( $cache_key, $value, $duration );
		} elseif ( 'object' === $method ) {
			return wp_cache_set( $cache_key, $value, 'pmpro_magic_levels', $duration );
		}
		return false;
	}

	/**
	 * Delete from cache using configured method.
	 *
	 * @since 1.0.0
	 * @param string $cache_key Cache key.
	 * @param string $method    Cache method.
	 * @return void
	 */
	private static function delete_from_cache( $cache_key, $method ) {
		if ( 'transient' === $method ) {
			delete_transient( $cache_key );
		} elseif ( 'object' === $method ) {
			wp_cache_delete( $cache_key, 'pmpro_magic_levels' );
		}
	}

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
		$config = self::get_cache_config();
		$value = self::get_from_cache( $cache_key, $config['method'] );

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
		$config = self::get_cache_config();
		return self::set_cache( $cache_key, $value, $config['method'], $config['duration'] );
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
		$config = self::get_cache_config();
		if ( 'object' === $config['method'] ) {
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
		$config = self::get_cache_config();
		self::delete_from_cache( $cache_key, $config['method'] );
	}
}
