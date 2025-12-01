<?php
/**
 * Webhook Handler - REST API endpoint
 */

defined( 'ABSPATH' ) || exit;

class PMPRO_Magic_Levels_Webhook_Handler {

    /**
     * Initialize webhook handler
     */
    public static function init() {
        add_action( 'rest_api_init', [ __CLASS__, 'register_routes' ] );
    }

    /**
     * Register REST API routes
     */
    public static function register_routes() {
        // Check if webhook is enabled
        if ( ! apply_filters( 'pmpro_magic_levels_enable_webhook', true ) ) {
            return;
        }

        register_rest_route( 'pmpro-magic-levels/v1', '/process', [
            'methods' => 'POST',
            'callback' => [ __CLASS__, 'process_request' ],
            'permission_callback' => [ __CLASS__, 'check_permissions' ],
        ] );
    }

    /**
     * Check permissions
     */
    public static function check_permissions( $request ) {
        // Check if authentication is required
        $require_auth = apply_filters( 'pmpro_magic_levels_webhook_require_auth', false );

        if ( ! $require_auth ) {
            return true;
        }

        // Get auth key from request
        $params = $request->get_json_params();
        $provided_key = isset( $params['auth_key'] ) ? $params['auth_key'] : '';

        // Get configured auth key
        $auth_key = apply_filters( 'pmpro_magic_levels_webhook_auth_key', '' );

        if ( empty( $auth_key ) ) {
            return true; // No key configured, allow access
        }

        // Verify key
        if ( $provided_key !== $auth_key ) {
            return new WP_Error(
                'invalid_auth_key',
                'Invalid authentication key',
                [ 'status' => 403 ]
            );
        }

        return true;
    }

    /**
     * Process webhook request
     */
    public static function process_request( $request ) {
        // Get request data
        $params = $request->get_json_params();

        // Remove auth_key from params if present
        if ( isset( $params['auth_key'] ) ) {
            unset( $params['auth_key'] );
        }

        // Process level
        $result = pmpro_magic_levels_process( $params );

        // Return response
        if ( $result['success'] ) {
            return new WP_REST_Response( $result, 200 );
        } else {
            return new WP_REST_Response( $result, 400 );
        }
    }
}
