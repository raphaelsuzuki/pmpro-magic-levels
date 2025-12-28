<?php
/**
 * Bearer Token Manager for PMPro Magic Levels.
 *
 * @package PMPro_Magic_Levels
 * @since 1.1.0
 */

defined('ABSPATH') || exit;

/**
 * PMPRO_Magic_Levels_Token_Manager class.
 *
 * Handles creation, validation, and management of additional API tokens.
 *
 * @since 1.1.0
 */
class PMPRO_Magic_Levels_Token_Manager
{

    /**
     * Option name for storing tokens.
     *
     * @var string
     */
    private static $option_name = 'pmpro_magic_levels_additional_tokens';

    /**
     * Create a new token.
     *
     * @since 1.1.0
     *
     * @param string $name Token name/description.
     * @return array|WP_Error Array with 'token' (raw) and 'id' on success, WP_Error on failure.
     */
    public static function create_token($name)
    {
        if (empty($name)) {
            return new WP_Error('empty_name', __('Token name cannot be empty.', 'pmpro-magic-levels'));
        }

        $tokens = get_option(self::$option_name, array());

        // Generate a secure 64-char token.
        $raw_token = base64_encode(random_bytes(48));

        // Hash it for storage.
        $hashed_token = hash('sha256', $raw_token);

        $token_id = uniqid('tk_', true);

        $tokens[$token_id] = array(
            'name' => sanitize_text_field($name),
            'hash' => $hashed_token,
            'created' => current_time('mysql'),
            'last_used' => null,
            'last_rotated' => null,
        );

        if (update_option(self::$option_name, $tokens)) {
            return array(
                'id' => $token_id,
                'name' => $name,
                'token' => $raw_token, // Only time this is exposed.
            );
        }

        return new WP_Error('db_error', __('Failed to save token to database.', 'pmpro-magic-levels'));
    }

    /**
     * Revoke a token.
     *
     * @since 1.1.0
     *
     * @param string $token_id The ID of the token to revoke.
     * @return bool True on success, false on failure.
     */
    public static function revoke_token($token_id)
    {
        $tokens = get_option(self::$option_name, array());

        if (isset($tokens[$token_id])) {
            unset($tokens[$token_id]);
            $updated = update_option(self::$option_name, $tokens);
            if ( $updated ) {
                self::audit( 'revoked', $token_id );
            }
            return $updated;
        }

        return false;
    }

    /**
     * Rotate an existing token and return the new raw token.
     *
     * @since 1.1.0
     * @param string $token_id Token ID to rotate.
     * @return array|WP_Error Array with 'id' and 'token' on success, WP_Error on failure.
     */
    public static function rotate_token( $token_id ) {
        $tokens = get_option( self::$option_name, array() );

        if ( empty( $tokens ) || ! isset( $tokens[ $token_id ] ) ) {
            return new WP_Error( 'not_found', __( 'Token not found.', 'pmpro-magic-levels' ) );
        }

        // Generate a new raw token and replace stored hash.
        $new_raw = base64_encode( random_bytes( 48 ) );
        $new_hash = hash( 'sha256', $new_raw );

        $tokens[ $token_id ]['hash'] = $new_hash;
        $tokens[ $token_id ]['last_rotated'] = current_time( 'mysql' );

        if ( update_option( self::$option_name, $tokens ) ) {
            self::audit( 'rotated', $token_id, array( 'rotated_by' => get_current_user_id() ) );
            return array( 'id' => $token_id, 'token' => $new_raw );
        }

        return new WP_Error( 'db_error', __( 'Failed to rotate token in database.', 'pmpro-magic-levels' ) );
    }

    /**
     * Validate a token.
     *
     * @since 1.1.0
     *
     * @param string $raw_token The raw token string from the request.
     * @return bool|string Token ID if valid, false otherwise.
     */
    public static function validate_token($raw_token)
    {
        $tokens = get_option(self::$option_name, array());

        if (empty($tokens)) {
            return false;
        }

        $hashed_input = hash('sha256', $raw_token);

        foreach ($tokens as $id => $data) {
            if (hash_equals($data['hash'], $hashed_input)) {
                self::record_usage($id);
                return $id;
            }
        }

        return false;
    }

    /**
     * Get all tokens (metadata only).
     *
     * @since 1.1.0
     *
     * @return array List of tokens with metadata (no hashes).
     */
    public static function get_tokens()
    {
        $tokens = get_option(self::$option_name, array());
        $safe_list = array();

        foreach ($tokens as $id => $data) {
            $safe_list[$id] = array(
                'name' => $data['name'],
                'created' => $data['created'],
                'last_used' => $data['last_used'],
            );
        }

        // Sort by created date (newest first).
        uasort($safe_list, function ($a, $b) {
            return strtotime($b['created']) - strtotime($a['created']);
        });

        return $safe_list;
    }

    /**
     * Record token usage.
     *
     * @since 1.1.0
     *
     * @param string $token_id Token ID.
     * @return void
     */
    private static function record_usage($token_id)
    {
        $tokens = get_option(self::$option_name, array());

        if (isset($tokens[$token_id])) {
            $tokens[$token_id]['last_used'] = current_time('mysql');
            update_option(self::$option_name, $tokens);
        }
    }

    /**
     * Lightweight audit helper that writes structured entries to the system log.
     * This is intentionally minimal for development workflow. Do NOT log raw tokens.
     *
     * @since 1.1.0
     * @param string $action Action name (created, rotated, revoked, validate_success, validate_failed, etc.).
     * @param string|null $token_id Token identifier.
     * @param array $meta Optional metadata.
     * @return void
     */
    private static function audit( $action, $token_id = null, $meta = array() ) {
        $ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';

        // Mask IPv4 addresses to reduce PII in logs (e.g. 192.0.x.x).
        if ( $ip && false !== strpos( $ip, '.' ) ) {
            $ip = preg_replace( '/^(\d+\.\d+)\.\d+\.\d+$/', '$1.x.x', $ip );
        } else {
            $ip = $ip ? 'redacted' : '';
        }

        $entry = array(
            'time'     => current_time( 'mysql' ),
            'action'   => $action,
            'token_id' => $token_id,
            'user_id'  => get_current_user_id(),
            'ip'       => $ip,
            'meta'     => $meta,
        );

        // Structured JSON to system log.
        if ( function_exists( 'wp_json_encode' ) ) {
            error_log( 'pmpro-magic-levels-audit: ' . wp_json_encode( $entry ) );
        } else {
            error_log( 'pmpro-magic-levels-audit: ' . json_encode( $entry ) );
        }

        /**
         * Action hook for external logging integrations.
         * Receives the structured audit entry as the only parameter.
         *
         * @param array $entry Structured audit entry.
         */
        do_action( 'pmpro_magic_levels_audit', $entry );
    }
}
