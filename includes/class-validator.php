<?php
/**
 * Validator for Magic Levels.
 *
 * @package PMPro_Magic_Levels
 * @since 1.0.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * PMPRO_Magic_Levels_Validator class.
 *
 * Handles validation of level data against configurable rules.
 *
 * @since 1.0.0
 */
class PMPRO_Magic_Levels_Validator {

	/**
	 * Get default validation rules.
	 *
	 * @since 1.0.0
	 *
	 * @return array Validation rules.
	 */
	private function get_defaults() {
		return array(
			'allowed_periods'         => apply_filters( 'pmpro_magic_levels_allowed_periods', array( 'Day', 'Week', 'Month', 'Year' ) ),
			'allowed_cycle_numbers'   => apply_filters( 'pmpro_magic_levels_allowed_cycle_numbers', array( 1, 2, 3, 6, 12 ) ),
			'min_name_length'         => apply_filters( 'pmpro_magic_levels_min_name_length', 1 ),
			'max_name_length'         => apply_filters( 'pmpro_magic_levels_max_name_length', 255 ),
			'name_pattern'            => apply_filters( 'pmpro_magic_levels_name_pattern', null ),
			'require_initial_payment' => apply_filters( 'pmpro_magic_levels_require_initial_payment', false ),
			'name_blacklist'          => apply_filters( 'pmpro_magic_levels_name_blacklist', array() ),
			'max_levels_per_day'      => apply_filters( 'pmpro_magic_levels_max_levels_per_day', 1000 ),
		);
	}

	/**
	 * Validate level data.
	 *
	 * @since 1.0.0
	 *
	 * @param array $data Level data to validate.
	 * @return array Validation result with 'valid' key and optional 'error' and 'code'.
	 */
	public function validate( $data ) {
		$rules = $this->get_defaults();

		// Check required fields.
		if ( empty( $data['name'] ) ) {
			return $this->error( 'Name is required', 'missing_required_field' );
		}

		if ( ! isset( $data['billing_amount'] ) || '' === $data['billing_amount'] ) {
			return $this->error( 'Billing amount is required', 'missing_required_field' );
		}

		// Check if name contains group separator (required for PMPro groups).
		if ( false === strpos( $data['name'], ' - ' ) ) {
			return $this->error( 'Name must include a group using format "GroupName - LevelName"', 'missing_group_separator' );
		}

		// Validate name length.
		$name_length = strlen( $data['name'] );
		if ( $name_length < $rules['min_name_length'] ) {
			return $this->error( "Name must be at least {$rules['min_name_length']} characters", 'name_too_short' );
		}
		if ( $name_length > $rules['max_name_length'] ) {
			return $this->error( "Name must be less than {$rules['max_name_length']} characters", 'name_too_long' );
		}

		// Validate name pattern.
		if ( ! empty( $rules['name_pattern'] ) && ! preg_match( $rules['name_pattern'], $data['name'] ) ) {
			return $this->error( 'Name contains invalid characters', 'invalid_name_pattern' );
		}

		// Check name blacklist.
		foreach ( $rules['name_blacklist'] as $blacklisted ) {
			if ( false !== stripos( $data['name'], $blacklisted ) ) {
				return $this->error( 'Name contains blacklisted word', 'blacklisted_name' );
			}
		}

		// Validate prices.
		if ( isset( $data['billing_amount'] ) ) {
			// Parse formatted currency strings (e.g., "¥219,450" or "$1,234.56").
			if ( is_string( $data['billing_amount'] ) ) {
				$data['billing_amount'] = preg_replace( '/[^0-9.]/', '', $data['billing_amount'] );
			}
			$billing_amount = floatval( $data['billing_amount'] );

			// Check if it's a valid number (not negative).
			if ( $billing_amount < 0 ) {
				return $this->error( 'Price cannot be negative', 'invalid_price' );
			}
		}

		// Validate initial payment.
		if ( isset( $data['initial_payment'] ) ) {
			$initial_payment = floatval( $data['initial_payment'] );

			if ( $rules['require_initial_payment'] && 0 === $initial_payment ) {
				return $this->error( 'Initial payment is required', 'initial_payment_required' );
			}

			if ( $initial_payment < 0 ) {
				return $this->error( 'Initial payment cannot be negative', 'invalid_price' );
			}
		}

		// Validate cycle period.
		if ( isset( $data['cycle_period'] ) && ! empty( $data['cycle_period'] ) ) {
			if ( ! in_array( $data['cycle_period'], $rules['allowed_periods'], true ) ) {
				return $this->error( 'Invalid cycle period', 'invalid_cycle_period' );
			}
		}

		// Validate cycle number.
		if ( isset( $data['cycle_number'] ) && ! empty( $data['cycle_number'] ) ) {
			if ( ! in_array( intval( $data['cycle_number'] ), $rules['allowed_cycle_numbers'], true ) ) {
				return $this->error( 'Invalid cycle number', 'invalid_cycle_number' );
			}
		}

		// Validate billing limit (must be non-negative integer).
		if ( isset( $data['billing_limit'] ) ) {
			$billing_limit = intval( $data['billing_limit'] );
			if ( $billing_limit < 0 ) {
				return $this->error( 'Billing limit cannot be negative', 'invalid_billing_limit' );
			}
		}

		// Check rate limiting.
		$rate_limit_check = $this->check_rate_limit();
		if ( ! $rate_limit_check['valid'] ) {
			return $rate_limit_check;
		}

		// Check daily limit.
		$daily_limit_check = $this->check_daily_limit( $rules['max_levels_per_day'] );
		if ( ! $daily_limit_check['valid'] ) {
			return $daily_limit_check;
		}

		// Validate content protection parameters.
		$content_validation = $this->validate_content_protection( $data );
		if ( ! $content_validation['valid'] ) {
			return $content_validation;
		}

		return array( 'valid' => true );
	}

	/**
	 * Validate content protection parameters.
	 *
	 * @since 1.0.0
	 *
	 * @param array $data Level data.
	 * @return array Validation result.
	 */
	private function validate_content_protection( $data ) {
		// Validate protected_categories.
		if ( isset( $data['protected_categories'] ) ) {
			if ( ! is_array( $data['protected_categories'] ) ) {
				return $this->error( 'protected_categories must be an array', 'invalid_content_protection' );
			}

			foreach ( $data['protected_categories'] as $cat_id ) {
				$cat_id = intval( $cat_id );
				if ( $cat_id <= 0 ) {
					return $this->error( 'Invalid category ID in protected_categories', 'invalid_category_id' );
				}

				// Check if category exists.
				$term = get_term( $cat_id );
				if ( is_wp_error( $term ) || ! $term ) {
					return $this->error( "Category ID {$cat_id} does not exist", 'category_not_found' );
				}

				// Verify it's a category or post_tag.
				if ( ! in_array( $term->taxonomy, array( 'category', 'post_tag' ), true ) ) {
					return $this->error( "Term ID {$cat_id} is not a category or tag", 'invalid_taxonomy' );
				}
			}
		}

		// Validate protected_pages.
		if ( isset( $data['protected_pages'] ) ) {
			if ( ! is_array( $data['protected_pages'] ) ) {
				return $this->error( 'protected_pages must be an array', 'invalid_content_protection' );
			}

			foreach ( $data['protected_pages'] as $page_id ) {
				$page_id = intval( $page_id );
				if ( $page_id <= 0 ) {
					return $this->error( 'Invalid page ID in protected_pages', 'invalid_page_id' );
				}

				// Check if page exists.
				$page = get_post( $page_id );
				if ( ! $page || 'page' !== $page->post_type ) {
					return $this->error( "Page ID {$page_id} does not exist", 'page_not_found' );
				}
			}
		}

		// Validate protected_posts.
		if ( isset( $data['protected_posts'] ) ) {
			if ( ! is_array( $data['protected_posts'] ) ) {
				return $this->error( 'protected_posts must be an array', 'invalid_content_protection' );
			}

			foreach ( $data['protected_posts'] as $post_id ) {
				$post_id = intval( $post_id );
				if ( $post_id <= 0 ) {
					return $this->error( 'Invalid post ID in protected_posts', 'invalid_post_id' );
				}

				// Check if post exists.
				$post = get_post( $post_id );
				if ( ! $post ) {
					return $this->error( "Post ID {$post_id} does not exist", 'post_not_found' );
				}
			}
		}

		return array( 'valid' => true );
	}

	/**
	 * Check rate limiting.
	 *
	 * Basic token-based rate limiting. For production sites, we recommend
	 * implementing rate limiting at the CDN/proxy level (Cloudflare, etc.)
	 * for better performance and security.
	 *
	 * @since 1.0.0
	 *
	 * @return array Validation result.
	 */
	private function check_rate_limit() {
		// Allow users to disable built-in rate limiting if using external solutions.
		if ( ! apply_filters( 'pmpro_magic_levels_enable_rate_limit', true ) ) {
			return array( 'valid' => true );
		}

		$rate_limit = apply_filters(
			'pmpro_magic_levels_rate_limit',
			array(
				'max_requests' => 100,
				'time_window'  => 3600,
			)
		);

		// Use Bearer token for rate limiting (more reliable for webhooks).
		$token      = $this->get_current_token();
		$identifier = ! empty( $token ) ? $token : 'anonymous_' . $this->get_client_ip();

		$transient_key = 'pmpro_magic_levels_rate_' . md5( $identifier );

		$requests = get_transient( $transient_key );

		if ( false === $requests ) {
			set_transient( $transient_key, 1, $rate_limit['time_window'] );
			return array( 'valid' => true );
		}

		if ( $requests >= $rate_limit['max_requests'] ) {
			$ttl = max( 0, get_option( '_transient_timeout_' . $transient_key ) - time() );
			$minutes = ceil( $ttl / 60 );
			// Return retry information so callers can map to HTTP 429 and send
			// a Retry-After header. Keep the existing error shape for
			// backwards compatibility and include `retry_after` in seconds.
			return array(
				'valid'       => false,
				'error'       => sprintf( 'Rate limit exceeded. Try again in %d minutes.', $minutes ),
				'code'        => 'rate_limit_exceeded',
				'retry_after' => $ttl,
			);
		}

		set_transient( $transient_key, $requests + 1, $rate_limit['time_window'] );
		return array( 'valid' => true );
	}

	/**
	 * Check daily level creation limit.
	 *
	 * @since 1.0.0
	 *
	 * @param int $max_levels Maximum levels allowed per day.
	 * @return array Validation result.
	 */
	private function check_daily_limit( $max_levels ) {
		$today     = date( 'Y-m-d' );
		$count_key = 'pmpro_magic_levels_count_' . $today;

		$count = get_transient( $count_key );

		if ( false === $count ) {
			set_transient( $count_key, 0, DAY_IN_SECONDS );
			return array( 'valid' => true );
		}

		if ( $count >= $max_levels ) {
			return $this->error( 'Daily level creation limit exceeded', 'daily_limit_exceeded' );
		}

		return array( 'valid' => true );
	}

	/**
	 * Increment daily counter.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function increment_daily_counter() {
		$today     = date( 'Y-m-d' );
		$count_key = 'pmpro_magic_levels_count_' . $today;
		$count     = get_transient( $count_key );
		set_transient( $count_key, $count + 1, DAY_IN_SECONDS );
	}

	/**
	 * Get client IP address.
	 *
	 * Returns REMOTE_ADDR only (cannot be spoofed). For accurate IP detection
	 * behind CDNs/proxies, implement rate limiting at the CDN/proxy level.
	 *
	 * @since 1.0.0
	 *
	 * @return string Client IP address.
	 */
	private function get_client_ip() {
		return isset( $_SERVER['REMOTE_ADDR'] )
			? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) )
			: '';
	}

	/**
	 * Get current Bearer token from request.
	 *
	 * @since 1.0.0
	 *
	 * @return string Token or empty string.
	 */
	private function get_current_token() {
		// Get Authorization header.
		$auth_header = '';

		if ( isset( $_SERVER['HTTP_AUTHORIZATION'] ) ) {
			$auth_header = sanitize_text_field( wp_unslash( $_SERVER['HTTP_AUTHORIZATION'] ) );
		} elseif ( function_exists( 'apache_request_headers' ) ) {
			$headers     = apache_request_headers();
			$auth_header = isset( $headers['Authorization'] ) ? sanitize_text_field( $headers['Authorization'] ) : '';
		}

		// Extract token from "Bearer TOKEN" format.
		if ( preg_match( '/Bearer\s+(.*)$/i', $auth_header, $matches ) ) {
			return trim( $matches[1] );
		}

		return '';
	}

	/**
	 * Return error response.
	 *
	 * @since 1.0.0
	 *
	 * @param string $message Error message.
	 * @param string $code    Error code.
	 * @return array Error response.
	 */
	private function error( $message, $code ) {
		return array(
			'valid' => false,
			'error' => $message,
			'code'  => $code,
		);
	}
}
