<?php
/**
 * REST API Handler - REST API Endpoint.
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
	}

	/**
	 * Redact sensitive fields from debug parameters.
	 *
	 * @since 1.1.0
	 * @param mixed $params Request params.
	 * @return mixed Redacted params.
	 */
	private static function redact_debug_params($params)
	{
		if (!is_array($params)) {
			return $params;
		}

		// Default sensitive keys to redact (case-insensitive substring match).
		$sensitive_keys = array(
			'authorization',
			'auth',
			'token',
			'password',
			'pass',
			'card_number',
			'cc',
			'credit_card',
			'cvv',
			'security_code',
			'email',
			'e-mail',
			'first_name',
			'last_name',
			'name',
			'full_name',
			'phone',
			'mobile',
			'msisdn',
			'address',
			'street',
			'city',
			'state',
			'zip',
			'postal',
			'postal_code',
			'ssn',
			'sin',
			'dob',
			'birth',
			'bank',
			'iban',
			'routing',
			'account_number',
		);

		$sensitive_keys = apply_filters('pmpro_magic_levels_debug_sensitive_keys', $sensitive_keys);

		// Default redaction placeholder — configurable by integrators.
		$redaction_placeholder = apply_filters('pmpro_magic_levels_redaction_placeholder', '[REDACTED]');

		$redact_value = function ($value) use ($redaction_placeholder) {
			return $redaction_placeholder;
		};

		$walk = function ($arr) use (&$walk, $sensitive_keys, $redact_value) {
			foreach ($arr as $k => $v) {
				$lk = strtolower($k);
				foreach ($sensitive_keys as $key) {
					if (false !== strpos($lk, strtolower($key))) {
						$arr[$k] = $redact_value($v);
						continue 2;
					}
				}

				if (is_array($v)) {
					$arr[$k] = $walk($v);
				}
			}
			return $arr;
		};

		$safe = $walk($params);

		/**
		 * Filter the redacted params after the default redaction has been applied.
		 *
		 * Allows integrators to further sanitize or remove fields. Expected
		 * signature: function( array $redacted_params, array $original_params )
		 */
		return apply_filters('pmpro_magic_levels_redact_debug_params', $safe, $params);
	}

	/**
	 * Process REST API request.
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

		// Safer debug handling: server-side logging is now opt-in. By default
		// we do NOT write request params to the system log. Integrators can
		// enable logging and control whether redacted params are included.
		if (!$result['success']) {
			$allow_log = apply_filters('pmpro_magic_levels_allow_debug_log', false);
			if ($allow_log) {
				$include_params = apply_filters('pmpro_magic_levels_debug_log_include_params', false);
				$include_fingerprint = apply_filters('pmpro_magic_levels_debug_log_include_fingerprint', true);

				$raw_payload = '';
				if (function_exists('wp_json_encode')) {
					$raw_payload = wp_json_encode($params);
				} else {
					$raw_payload = json_encode($params);
				}

				$log_entry = array(
					'timestamp' => current_time('mysql'),
					'route' => 'pmpro-magic-levels/v1/process',
				);

				if ($include_fingerprint) {
					$log_entry['params_hash'] = hash('sha256', $raw_payload);
				}

				if ($include_params) {
					$log_entry['params'] = self::redact_debug_params($params);
				}

				$payload = function_exists('wp_json_encode') ? wp_json_encode($log_entry) : json_encode($log_entry);
				$max_len = apply_filters('pmpro_magic_levels_debug_log_max_length', 10000);
				if (strlen($payload) > $max_len) {
					$payload = substr($payload, 0, $max_len) . '...';
				}
				error_log('pmpro-magic-levels-debug: ' . $payload);
			}

			// Only include debug payload in response when both WP_DEBUG is
			// enabled and the allow filter is explicitly turned on.
			$allow_debug = apply_filters('pmpro_magic_levels_allow_debug_output', false);
			if ($allow_debug && defined('WP_DEBUG') && WP_DEBUG) {
				$result['debug'] = array(
					'received_params' => self::redact_debug_params($params),
					'timestamp' => current_time('mysql'),
				);
			}
		}

		// Return response.
		if ($result['success']) {
			return new WP_REST_Response($result, 200);
		} else {
			// Default to 400 for validation errors, but map rate limit errors to
			// 429 Too Many Requests and include a Retry-After header when
			// `retry_after` is provided by the validator.
			$status = 400;
			$response = new WP_REST_Response($result, $status);

			if (isset($result['code']) && 'rate_limit_exceeded' === $result['code']) {
				$status = 429;
				$response = new WP_REST_Response($result, $status);

				if (isset($result['retry_after'])) {
					$retry_after = intval($result['retry_after']);
					// Add Retry-After header in seconds.
					$response->header('Retry-After', $retry_after);
				}
			}

			return $response;
		}
	}
}
