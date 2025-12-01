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
		// Check if authentication is required.
		$require_auth = apply_filters( 'pmpro_magic_levels_webhook_require_auth', false );

		if ( ! $require_auth ) {
			return true;
		}

		// Get auth key from request.
		$params       = $request->get_json_params();
		$provided_key = isset( $params['auth_key'] ) ? $params['auth_key'] : '';

		// Get configured auth key.
		$auth_key = apply_filters( 'pmpro_magic_levels_webhook_auth_key', '' );

		if ( empty( $auth_key ) ) {
			return true; // No key configured, allow access.
		}

		// Verify key.
		if ( $provided_key !== $auth_key ) {
			return new WP_Error(
				'invalid_auth_key',
				'Invalid authentication key',
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

		// Return response.
		if ( $result['success'] ) {
			return new WP_REST_Response( $result, 200 );
		} else {
			return new WP_REST_Response( $result, 400 );
		}
	}
}
