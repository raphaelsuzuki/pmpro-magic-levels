<?php
/**
 * Custom Endpoints & Wrapper Functions Examples.
 *
 * How to create custom webhooks or PHP functions with hardcoded values.
 *
 * @package PMPro_Magic_Levels
 * @since 1.0.0
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

// ============================================
// Example 1: Custom Webhook with Prefix
// ============================================

/**
 * Create a custom webhook that prefixes all level names.
 * URL: /wp-json/my-site/v1/create-premium-level
 *
 * @return void
 */
function pmpro_magic_levels_register_premium_endpoint() {
	register_rest_route(
		'my-site/v1',
		'/create-premium-level',
		array(
			'methods'             => 'POST',
			'callback'            => 'pmpro_magic_levels_premium_endpoint_callback',
			'permission_callback' => '__return_true',
		)
	);
}
add_action( 'rest_api_init', 'pmpro_magic_levels_register_premium_endpoint' );

/**
 * Callback for premium endpoint.
 *
 * @param WP_REST_Request $request Request object.
 * @return WP_REST_Response Response object.
 */
function pmpro_magic_levels_premium_endpoint_callback( $request ) {
	$params = $request->get_json_params();

	// Hardcode the prefix.
	$params['name'] = 'Premium - ' . $params['name'];

	// Hardcode other values.
	$params['cycle_period'] = 'Month';
	$params['cycle_number'] = 1;

	// Process with Magic Levels.
	$result = pmpro_magic_levels_process( $params );

	return new WP_REST_Response( $result, $result['success'] ? 200 : 400 );
}

// Usage:
// POST /wp-json/my-site/v1/create-premium-level
// { "name": "Gold", "billing_amount": 49.99 }
// Creates level: "Premium - Gold".

// ============================================
// Example 2: Multiple Custom Webhooks
// ============================================

/**
 * Create separate webhooks for different membership types.
 *
 * @return void
 */
function pmpro_magic_levels_register_tier_endpoints() {

	// Basic membership webhook.
	register_rest_route(
		'my-site/v1',
		'/create-basic',
		array(
			'methods'             => 'POST',
			'callback'            => 'pmpro_magic_levels_basic_endpoint_callback',
			'permission_callback' => '__return_true',
		)
	);

	// Pro membership webhook.
	register_rest_route(
		'my-site/v1',
		'/create-pro',
		array(
			'methods'             => 'POST',
			'callback'            => 'pmpro_magic_levels_pro_endpoint_callback',
			'permission_callback' => '__return_true',
		)
	);

	// Enterprise membership webhook.
	register_rest_route(
		'my-site/v1',
		'/create-enterprise',
		array(
			'methods'             => 'POST',
			'callback'            => 'pmpro_magic_levels_enterprise_endpoint_callback',
			'permission_callback' => '__return_true',
		)
	);
}
add_action( 'rest_api_init', 'pmpro_magic_levels_register_tier_endpoints' );

/**
 * Basic tier endpoint callback.
 *
 * @param WP_REST_Request $request Request object.
 * @return WP_REST_Response Response object.
 */
function pmpro_magic_levels_basic_endpoint_callback( $request ) {
	$params = $request->get_json_params();

	return new WP_REST_Response(
		pmpro_magic_levels_process(
			array(
				'name'           => 'Basic - ' . $params['name'],
				'billing_amount' => 9.99,  // Fixed price.
				'cycle_period'   => 'Month',
				'cycle_number'   => 1,
				'description'    => 'Basic membership tier',
			)
		)
	);
}

/**
 * Pro tier endpoint callback.
 *
 * @param WP_REST_Request $request Request object.
 * @return WP_REST_Response Response object.
 */
function pmpro_magic_levels_pro_endpoint_callback( $request ) {
	$params = $request->get_json_params();

	return new WP_REST_Response(
		pmpro_magic_levels_process(
			array(
				'name'           => 'Pro - ' . $params['name'],
				'billing_amount' => 29.99,  // Fixed price.
				'cycle_period'   => 'Month',
				'cycle_number'   => 1,
				'description'    => 'Pro membership tier',
			)
		)
	);
}

/**
 * Enterprise tier endpoint callback.
 *
 * @param WP_REST_Request $request Request object.
 * @return WP_REST_Response Response object.
 */
function pmpro_magic_levels_enterprise_endpoint_callback( $request ) {
	$params = $request->get_json_params();

	return new WP_REST_Response(
		pmpro_magic_levels_process(
			array(
				'name'           => 'Enterprise - ' . $params['name'],
				'billing_amount' => 99.99,  // Fixed price.
				'cycle_period'   => 'Year',   // Yearly billing.
				'cycle_number'   => 1,
				'description'    => 'Enterprise membership tier',
			)
		)
	);
}

// ============================================
// Example 3: Custom PHP Wrapper Functions
// ============================================

/**
 * Create a premium level with hardcoded values.
 *
 * @param string     $name         Level name.
 * @param float|null $custom_price Optional custom price.
 * @return array Result array.
 */
function create_premium_level( $name, $custom_price = null ) {
	return pmpro_magic_levels_process(
		array(
			'name'           => 'Premium - ' . $name,
			'billing_amount' => $custom_price ? $custom_price : 49.99,
			'cycle_period'   => 'Month',
			'cycle_number'   => 1,
			'description'    => 'Premium membership with full access',
			'confirmation'   => 'Welcome to Premium!',
		)
	);
}

/**
 * Create a yearly level with discount.
 *
 * @param string $name          Level name.
 * @param float  $monthly_price Monthly price.
 * @return array Result array.
 */
function create_yearly_level( $name, $monthly_price ) {
	$yearly_price = $monthly_price * 10; // 2 months free.

	return pmpro_magic_levels_process(
		array(
			'name'           => $name . ' (Yearly)',
			'billing_amount' => $yearly_price,
			'cycle_period'   => 'Year',
			'cycle_number'   => 1,
			'description'    => 'Save 2 months with yearly billing!',
		)
	);
}

/**
 * Create a trial level.
 *
 * @param string $name       Level name.
 * @param int    $trial_days Trial duration in days.
 * @return array Result array.
 */
function create_trial_level( $name, $trial_days = 7 ) {
	return pmpro_magic_levels_process(
		array(
			'name'              => $name . ' - Trial',
			'billing_amount'    => 29.99,
			'cycle_period'      => 'Month',
			'cycle_number'      => 1,
			'trial_amount'      => 0.00,
			'trial_limit'       => 1,
			'expiration_number' => $trial_days,
			'expiration_period' => 'Day',
		)
	);
}

// Usage in your code:
// $result = create_premium_level( 'Gold Plan' );
// $result = create_yearly_level( 'Pro Plan', 29.99 );
// $result = create_trial_level( 'Premium', 14 );

// ============================================
// Example 4: Category-Based Levels
// ============================================

/**
 * Create levels for different product categories.
 *
 * @param string $category Category slug.
 * @param string $name     Level name.
 * @param float  $price    Price.
 * @return array Result array.
 */
function create_category_level( $category, $name, $price ) {
	$prefixes = array(
		'courses'   => 'Course Access',
		'videos'    => 'Video Library',
		'downloads' => 'Download Pack',
	);

	$prefix = isset( $prefixes[ $category ] ) ? $prefixes[ $category ] : 'Membership';

	return pmpro_magic_levels_process(
		array(
			'name'           => $prefix . ' - ' . $name,
			'billing_amount' => $price,
			'cycle_period'   => 'Month',
			'cycle_number'   => 1,
			'description'    => "Access to {$category} content",
		)
	);
}

// Usage:
// create_category_level( 'courses', 'Beginner', 19.99 );
// create_category_level( 'videos', 'Premium', 29.99 );

// ============================================
// Example 5: Form-Specific Handlers
// ============================================

/**
 * WPForms handler with hardcoded prefix.
 *
 * @param array $fields    Form fields.
 * @param array $entry     Entry data.
 * @param array $form_data Form data.
 * @return void
 */
function pmpro_magic_levels_wpforms_custom_handler( $fields, $entry, $form_data ) {
	if ( 123 !== $form_data['id'] ) {
		return;
	}

	$result = pmpro_magic_levels_process(
		array(
			'name'           => 'Custom - ' . $fields[1]['value'],  // Prefix added.
			'billing_amount' => $fields[2]['value'],
			'cycle_period'   => 'Month',  // Hardcoded.
			'cycle_number'   => 1,        // Hardcoded.
			'description'    => 'Created via custom form',  // Hardcoded.
		)
	);

	if ( $result['success'] ) {
		wp_safe_redirect( $result['redirect_url'] );
		exit;
	}
}
add_action( 'wpforms_process_complete', 'pmpro_magic_levels_wpforms_custom_handler', 10, 3 );

// ============================================
// Example 6: Dynamic Pricing with Hardcoded Rules
// ============================================

/**
 * Create level with automatic pricing tiers.
 *
 * @param string $name       Level name.
 * @param int    $user_count Number of users.
 * @return array Result array.
 */
function create_tiered_level( $name, $user_count ) {
	// Hardcoded pricing tiers.
	if ( $user_count <= 5 ) {
		$price = 29.99;
		$tier  = 'Small Team';
	} elseif ( $user_count <= 20 ) {
		$price = 99.99;
		$tier  = 'Medium Team';
	} else {
		$price = 299.99;
		$tier  = 'Large Team';
	}

	return pmpro_magic_levels_process(
		array(
			'name'           => "{$tier} - {$name}",
			'billing_amount' => $price,
			'cycle_period'   => 'Month',
			'cycle_number'   => 1,
			'description'    => "For teams up to {$user_count} users",
		)
	);
}

// ============================================
// Example 7: Webhook with Validation & Defaults
// ============================================

/**
 * Register smart level endpoint.
 *
 * @return void
 */
function pmpro_magic_levels_register_smart_endpoint() {
	register_rest_route(
		'my-site/v1',
		'/smart-level',
		array(
			'methods'             => 'POST',
			'callback'            => 'pmpro_magic_levels_smart_endpoint_callback',
			'permission_callback' => '__return_true',
		)
	);
}
add_action( 'rest_api_init', 'pmpro_magic_levels_register_smart_endpoint' );

/**
 * Smart endpoint callback with defaults.
 *
 * @param WP_REST_Request $request Request object.
 * @return WP_REST_Response Response object.
 */
function pmpro_magic_levels_smart_endpoint_callback( $request ) {
	$params = $request->get_json_params();

	// Apply defaults.
	$level_data = array(
		'name'           => 'Member - ' . ( isset( $params['name'] ) ? $params['name'] : 'Default' ),
		'billing_amount' => isset( $params['price'] ) ? $params['price'] : 19.99,
		'cycle_period'   => isset( $params['period'] ) ? $params['period'] : 'Month',
		'cycle_number'   => 1,
		'description'    => isset( $params['description'] ) ? $params['description'] : 'Standard membership',
		'confirmation'   => 'Thank you for joining!',
	);

	// Add prefix based on price.
	if ( $level_data['billing_amount'] >= 50 ) {
		$level_data['name'] = 'Premium - ' . $params['name'];
	}

	return new WP_REST_Response( pmpro_magic_levels_process( $level_data ) );
}

// ============================================
// Example 8: Shortcode with Hardcoded Values
// ============================================

/**
 * Create a shortcode that generates a level.
 * Usage: [create_membership name="Gold" price="49.99"]
 *
 * @param array $atts Shortcode attributes.
 * @return string HTML output.
 */
function pmpro_magic_levels_create_membership_shortcode( $atts ) {
	$atts = shortcode_atts(
		array(
			'name'   => 'Default',
			'price'  => '29.99',
			'prefix' => 'Member',
		),
		$atts
	);

	$result = pmpro_magic_levels_process(
		array(
			'name'           => $atts['prefix'] . ' - ' . $atts['name'],
			'billing_amount' => floatval( $atts['price'] ),
			'cycle_period'   => 'Month',
			'cycle_number'   => 1,
		)
	);

	if ( $result['success'] ) {
		return '<a href="' . esc_url( $result['redirect_url'] ) . '">Subscribe Now</a>';
	}

	return 'Error creating level';
}
add_shortcode( 'create_membership', 'pmpro_magic_levels_create_membership_shortcode' );

// ============================================
// Example 9: AJAX Handler with Hardcoded Values
// ============================================

/**
 * AJAX handler for creating custom level.
 *
 * @return void
 */
function pmpro_magic_levels_ajax_create_custom_level() {
	check_ajax_referer( 'create_level_nonce', 'nonce' );

	$name = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';

	$result = pmpro_magic_levels_process(
		array(
			'name'            => 'VIP - ' . $name,  // Hardcoded prefix.
			'billing_amount'  => 99.99,   // Hardcoded price.
			'cycle_period'    => 'Month',
			'cycle_number'    => 1,
			'initial_payment' => 0,      // Free first month.
			'description'     => 'VIP membership with exclusive benefits',
		)
	);

	wp_send_json( $result );
}
add_action( 'wp_ajax_create_custom_level', 'pmpro_magic_levels_ajax_create_custom_level' );
add_action( 'wp_ajax_nopriv_create_custom_level', 'pmpro_magic_levels_ajax_create_custom_level' );

// ============================================
// Example 10: Conditional Logic
// ============================================

/**
 * Create smart level with conditional logic.
 *
 * @param array $user_input User input data.
 * @return array Result array.
 */
function create_smart_level( $user_input ) {
	// Determine prefix based on conditions.
	$prefix = is_user_logged_in() ? 'Upgrade' : 'New Member';

	// Determine price based on user role.
	$user     = wp_get_current_user();
	$discount = in_array( 'subscriber', $user->roles, true ) ? 0.8 : 1.0;

	return pmpro_magic_levels_process(
		array(
			'name'           => $prefix . ' - ' . $user_input['name'],
			'billing_amount' => $user_input['price'] * $discount,
			'cycle_period'   => 'Month',
			'cycle_number'   => 1,
			'description'    => 'Custom membership level',
		)
	);
}
