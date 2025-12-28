<?php
/**
 * Webhook Handler - REST API endpoint.
 *
 * @package PMPro_Magic_Levels
 * @since 1.0.0
 */

defined('ABSPATH') || exit;

/**
 * PMPRO_Magic_Levels_Webhook_Handler class.
 *
 * Handles REST API endpoint for processing level requests.
 *
 * @since 1.0.0
 */
class PMPRO_Magic_Levels_Webhook_Handler
{

	/**
	 * Initialize webhook handler.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public static function init()
	{
		add_action('rest_api_init', array(__CLASS__, 'register_routes'));
	}

	/**
	 * Register REST API routes.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public static function register_routes()
	{
		// Check if webhook is enabled. Defaults to false (0) for new installations.
		$is_enabled = get_option('pmpro_ml_webhook_enabled', '0') === '1';
		if (!apply_filters('pmpro_magic_levels_enable_webhook', $is_enabled)) {
			return;
		}

		register_rest_route(
			'pmpro-magic-levels/v1',
			'/process',
			array(
				'methods' => 'POST',
				'callback' => array(__CLASS__, 'process_request'),
				'permission_callback' => array(__CLASS__, 'check_permissions'),
			)
		);
	}

	/**
	 * Check permissions.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return bool|WP_Error True if authorized, WP_Error otherwise.
	 */
	public static function check_permissions($request)
	{
		// Get Bearer token from Authorization header.
		$auth_header = $request->get_header('authorization');

		if (empty($auth_header)) {
			return new WP_Error(
				'missing_authorization',
				__('Missing Authorization header. Include: Authorization: Bearer YOUR_TOKEN', 'pmpro-magic-levels'),
				array('status' => 401)
			);
		}

		// Extract token from "Bearer TOKEN" format.
		if (!preg_match('/Bearer\s+(.*)$/i', $auth_header, $matches)) {
			return new WP_Error(
				'invalid_authorization_format',
				__('Invalid Authorization header format. Use: Authorization: Bearer YOUR_TOKEN', 'pmpro-magic-levels'),
				array('status' => 401)
			);
		}

		$provided_token = trim($matches[1]);

		// Verify token using timing-safe comparison.
		// Check against Token Manager.
		if (class_exists('PMPRO_Magic_Levels_Token_Manager')) {
			if (PMPRO_Magic_Levels_Token_Manager::validate_token($provided_token)) {
				return true;
			}
		}

		return new WP_Error(
			'invalid_token',
			__('Invalid bearer token', 'pmpro-magic-levels'),
			array('status' => 403)
		);

		return true;
	}

	/**
	 * Redact sensitive fields from debug parameters.
	 *
	 * @since 1.1.0
	 * @param mixed $params Request params.
	 * @return mixed Redacted params.
	 */
	private static function redact_debug_params( $params ) {
		if ( ! is_array( $params ) ) {
			return $params;
		}

		// Default sensitive keys to redact (case-insensitive substring match).
		$sensitive_keys = array(
			'authorization', 'auth', 'token', 'password', 'pass', 'card_number', 'cc', 'credit_card', 'email'
		);

		$sensitive_keys = apply_filters( 'pmpro_magic_levels_debug_sensitive_keys', $sensitive_keys );

		$redact_value = function( $value ) {
			if ( is_string( $value ) ) {
				// Keep a short prefix and mask the rest. Use mb_substr when available.
				if ( function_exists( 'mb_substr' ) ) {
					return mb_substr( $value, 0, 4 ) . '…';
				}
				return substr( $value, 0, 4 ) . '…';
			}
			return 'redacted';
		};

		$walk = function( $arr ) use ( &$walk, $sensitive_keys, $redact_value ) {
			foreach ( $arr as $k => $v ) {
				$lk = strtolower( $k );
				foreach ( $sensitive_keys as $key ) {
					if ( false !== strpos( $lk, strtolower( $key ) ) ) {
						$arr[ $k ] = $redact_value( $v );
						continue 2;
					}
				}

				if ( is_array( $v ) ) {
					$arr[ $k ] = $walk( $v );
				}
			}
			return $arr;
		};

		$safe = $walk( $params );

		return apply_filters( 'pmpro_magic_levels_redact_debug_params', $safe, $params );
	}

	/**
	 * Process webhook request.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response Response object.
	 */
	public static function process_request($request)
	{
		// Get request data.
		$params = $request->get_json_params();

		// Process level.
		$result = pmpro_magic_levels_process($params);

		// Safer debug handling: always log a redacted copy server-side, but
		// only include debug information in the HTTP response when explicitly
		// enabled via filter and WP_DEBUG is active.
		if ( ! $result['success'] ) {
			$redacted = self::redact_debug_params( $params );

			// Server-side log for forensics (redacted). Limit size to avoid huge log lines.
			$log_entry = array(
				'timestamp' => current_time( 'mysql' ),
				'route'     => 'pmpro-magic-levels/v1/process',
				'params'    => $redacted,
			);

			$payload = function_exists( 'wp_json_encode' ) ? wp_json_encode( $log_entry ) : json_encode( $log_entry );
			$max_len = apply_filters( 'pmpro_magic_levels_debug_log_max_length', 10000 );
			if ( strlen( $payload ) > $max_len ) {
				$payload = substr( $payload, 0, $max_len ) . '...';
			}
			error_log( 'pmpro-magic-levels-debug: ' . $payload );

			// Only include debug payload in response when both WP_DEBUG is
			// enabled and the allow filter is explicitly turned on.
			$allow_debug = apply_filters( 'pmpro_magic_levels_allow_debug_output', false );
			if ( $allow_debug && defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				$result['debug'] = array(
					'received_params' => $redacted,
					'timestamp' => current_time( 'mysql' ),
				);
			}
		}



		// Return response.
		if ($result['success']) {
			return new WP_REST_Response($result, 200);
		} else {
			return new WP_REST_Response($result, 400);
		}
	}
}
