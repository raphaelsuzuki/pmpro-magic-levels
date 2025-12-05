<?php
/**
 * Webhook Handler - REST API endpoint.
 *
 * @package PMPro_Magic_Levels
 * @since 1.0.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * PMPRO_Magic_Levels_Webhook_Handler class.
 *
 * Handles REST API endpoint for processing level requests.
 *
 * @since 1.0.0
 */
class PMPRO_Magic_Levels_Webhook_Handler {

	/**
	 * Initialize webhook handler.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
	}

	/**
	 * Register REST API routes.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public static function register_routes() {
		// Check if webhook is enabled.
		if ( ! apply_filters( 'pmpro_magic_levels_enable_webhook', true ) ) {
			return;
		}

		register_rest_route(
			'pmpro-magic-levels/v1',
			'/process',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'process_request' ),
				'permission_callback' => array( __CLASS__, 'check_permissions' ),
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
	public static function check_permissions( $request ) {
		// Check if webhook is enabled.
		$webhook_enabled = get_option( 'pmpro_ml_webhook_enabled', '0' );

		if ( '1' !== $webhook_enabled ) {
			return new WP_Error(
				'webhook_disabled',
				'Webhook Endpoint is disabled. Enable it in PMPro > Magic Levels settings.',
				array( 'status' => 403 )
			);
		}

		// Get stored webhook key.
		$webhook_key = get_option( 'pmpro_ml_webhook_key' );

		if ( empty( $webhook_key ) ) {
			return new WP_Error(
				'no_key_configured',
				'No security key configured. Generate one in PMPro > Magic Levels settings.',
				array( 'status' => 500 )
			);
		}

		// Get Bearer token from Authorization header.
		$auth_header = $request->get_header( 'authorization' );
		
		if ( empty( $auth_header ) ) {
			return new WP_Error(
				'missing_authorization',
				'Missing Authorization header. Include: Authorization: Bearer YOUR_TOKEN',
				array( 'status' => 401 )
			);
		}

		// Extract token from "Bearer TOKEN" format.
		if ( ! preg_match( '/Bearer\s+(.*)$/i', $auth_header, $matches ) ) {
			return new WP_Error(
				'invalid_authorization_format',
				'Invalid Authorization header format. Use: Authorization: Bearer YOUR_TOKEN',
				array( 'status' => 401 )
			);
		}

		$provided_token = trim( $matches[1] );

		// Verify token using timing-safe comparison.
		if ( ! hash_equals( $webhook_key, $provided_token ) ) {
			return new WP_Error(
				'invalid_token',
				'Invalid bearer token',
				array( 'status' => 403 )
			);
		}

		return true;
	}

	/**
	 * Process webhook request.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response Response object.
	 */
	public static function process_request( $request ) {
		// Get request data.
		$params = $request->get_json_params();

		// Remove auth_key from params if present.
		if ( isset( $params['auth_key'] ) ) {
			unset( $params['auth_key'] );
		}

		// Process level.
		$result = pmpro_magic_levels_process( $params );

		// Add debug info to error responses.
		if ( ! $result['success'] ) {
			$result['debug'] = array(
				'received_params' => $params,
				'timestamp'       => current_time( 'mysql' ),
			);
		}

		// Add redirect_url to successful responses.
		if ( $result['success'] && isset( $result['level_id'] ) ) {
			$checkout_url = apply_filters(
				'pmpro_magic_levels_checkout_url',
				home_url( '/checkout/' ),
				$result['level_id'],
				$params
			);
			$result['redirect_url'] = add_query_arg( 'pmpro_level', $result['level_id'], $checkout_url );
		}

		// Return response.
		if ( $result['success'] ) {
			return new WP_REST_Response( $result, 200 );
		} else {
			return new WP_REST_Response( $result, 400 );
		}
	}
}
