<?php
/**
 * WSForm Integration Examples.
 *
 * Complete guide for integrating PMPro Magic Levels with WSForm.
 * Includes both PHP action hooks and REST API webhook methods.
 *
 * @package PMPro_Magic_Levels
 * @since 1.0.0
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

// ============================================
// METHOD 1: PHP Action Hook Integration
// ============================================

/**
 * Process WSForm submission using PHP action hook.
 *
 * This method uses WSForm's submit action hook to process the form
 * data directly in PHP and create/find membership levels.
 *
 * @param object $submit Submit object.
 * @return void
 */
function pmpro_magic_levels_wsform_submit_handler( $submit ) {

	// Get form ID - change this to match your form.
	$form_id = $submit->form_id;

	// Only process specific form (optional).
	if ( 123 !== $form_id ) {
		return;
	}

	// Get form data.
	$form_object = $submit->form_object;

	// Extract field values by field ID.
	// Replace these field IDs with your actual WSForm field IDs.
	$level_name      = WS_Form_Common::get_object_meta_value( $form_object, 'field_1', '' );
	$billing_amount  = WS_Form_Common::get_object_meta_value( $form_object, 'field_2', 0 );
	$cycle_period    = WS_Form_Common::get_object_meta_value( $form_object, 'field_3', 'Month' );
	$description     = WS_Form_Common::get_object_meta_value( $form_object, 'field_4', '' );

	// Build level data array.
	$level_data = array(
		'name'           => sanitize_text_field( $level_name ),
		'billing_amount' => floatval( $billing_amount ),
		'cycle_period'   => sanitize_text_field( $cycle_period ),
		'cycle_number'   => 1,
		'description'    => sanitize_textarea_field( $description ),
	);

	// Process level (find or create).
	$result = pmpro_magic_levels_process( $level_data );

	if ( $result['success'] ) {
		// Redirect to checkout.
		wp_safe_redirect( $result['redirect_url'] );
		exit;
	} else {
		// Handle error - you can log it or display to user.
		error_log( 'PMPro Magic Levels Error: ' . $result['error'] );
		wp_die( esc_html( $result['error'] ) );
	}
}
add_action( 'wsf_submit_post_complete', 'pmpro_magic_levels_wsform_submit_handler', 10, 1 );

// ============================================
// METHOD 2: Alternative PHP Hook with Field Mapping
// ============================================

/**
 * Process WSForm submission with custom field mapping.
 *
 * This example shows how to map WSForm fields by their labels
 * instead of field IDs for easier maintenance.
 *
 * @param object $submit Submit object.
 * @return void
 */
function pmpro_magic_levels_wsform_field_mapping_handler( $submit ) {

	// Get form data.
	$form_object = $submit->form_object;
	$fields      = $form_object->fields;

	// Initialize data array.
	$form_data = array();

	// Loop through fields and map by label.
	foreach ( $fields as $field ) {
		$label = isset( $field->label ) ? $field->label : '';
		$value = isset( $field->value ) ? $field->value : '';

		// Map fields by their labels.
		switch ( $label ) {
			case 'Membership Name':
			case 'Level Name':
				$form_data['name'] = sanitize_text_field( $value );
				break;

			case 'Price':
			case 'Monthly Price':
			case 'Billing Amount':
				$form_data['billing_amount'] = floatval( $value );
				break;

			case 'Billing Period':
			case 'Period':
				$form_data['cycle_period'] = sanitize_text_field( $value );
				break;

			case 'Description':
				$form_data['description'] = sanitize_textarea_field( $value );
				break;

			case 'Initial Payment':
				$form_data['initial_payment'] = floatval( $value );
				break;

			case 'Trial Days':
				$form_data['trial_limit'] = intval( $value );
				$form_data['trial_amount'] = 0;
				break;
		}
	}

	// Set default cycle number.
	$form_data['cycle_number'] = 1;

	// Process level.
	$result = pmpro_magic_levels_process( $form_data );

	if ( $result['success'] ) {
		wp_safe_redirect( $result['redirect_url'] );
		exit;
	}
}
add_action( 'wsf_submit_post_complete', 'pmpro_magic_levels_wsform_field_mapping_handler', 10, 1 );

// ============================================
// METHOD 3: Using WSForm Variables
// ============================================

/**
 * Process WSForm submission using WSForm variables.
 *
 * This method uses WSForm's variable system to access field values.
 *
 * @param object $submit Submit object.
 * @return void
 */
function pmpro_magic_levels_wsform_variables_handler( $submit ) {

	// Parse variables from submit object.
	$ws_form_submit = new WS_Form_Submit();
	$ws_form_submit->id = $submit->id;
	$variables = $ws_form_submit->db_get_submit_variables();

	// Extract values using variable names.
	// Variable names are typically: field_[field_id].
	$level_data = array(
		'name'           => isset( $variables['field_1'] ) ? sanitize_text_field( $variables['field_1'] ) : '',
		'billing_amount' => isset( $variables['field_2'] ) ? floatval( $variables['field_2'] ) : 0,
		'cycle_period'   => isset( $variables['field_3'] ) ? sanitize_text_field( $variables['field_3'] ) : 'Month',
		'cycle_number'   => 1,
	);

	// Process level.
	$result = pmpro_magic_levels_process( $level_data );

	if ( $result['success'] ) {
		wp_safe_redirect( $result['redirect_url'] );
		exit;
	}
}
add_action( 'wsf_submit_post_complete', 'pmpro_magic_levels_wsform_variables_handler', 10, 1 );

// ============================================
// METHOD 4: REST API Webhook Integration
// ============================================

/**
 * INSTRUCTIONS FOR WEBHOOK SETUP IN WSFORM:
 *
 * 1. In WSForm, go to your form settings
 * 2. Click on "Actions" tab
 * 3. Add a new action and select "Webhook"
 * 4. Configure the webhook:
 *
 *    URL: https://yoursite.com/wp-json/pmpro-magic-levels/v1/process
 *    Method: POST
 *    Content Type: application/json
 *
 * 5. In the "Body" field, use this JSON structure:
 *
 *    {
 *      "name": "#field(1)",
 *      "billing_amount": "#field(2)",
 *      "cycle_period": "#field(3)",
 *      "cycle_number": 1
 *    }
 *
 *    Replace field(1), field(2), field(3) with your actual field IDs.
 *
 * 6. Optional - Add authentication:
 *
 *    {
 *      "auth_key": "your-secret-key-here",
 *      "name": "#field(1)",
 *      "billing_amount": "#field(2)",
 *      "cycle_period": "#field(3)",
 *      "cycle_number": 1
 *    }
 *
 *    Then enable authentication in your functions.php:
 *
 *    add_filter( 'pmpro_magic_levels_webhook_require_auth', '__return_true' );
 *    add_filter( 'pmpro_magic_levels_webhook_auth_key', function() {
 *        return 'your-secret-key-here';
 *    } );
 *
 * 7. To redirect after success, add a "Redirect" action after the webhook
 *    and use the response variable: #webhook_response(redirect_url)
 */

// ============================================
// METHOD 5: Custom Webhook with WSForm Variables
// ============================================

/**
 * Create a custom webhook endpoint specifically for WSForm.
 *
 * This gives you more control over the data processing.
 * URL: /wp-json/my-site/v1/wsform-membership
 *
 * @return void
 */
function pmpro_magic_levels_register_wsform_endpoint() {
	register_rest_route(
		'my-site/v1',
		'/wsform-membership',
		array(
			'methods'             => 'POST',
			'callback'            => 'pmpro_magic_levels_wsform_endpoint_callback',
			'permission_callback' => '__return_true',
		)
	);
}
add_action( 'rest_api_init', 'pmpro_magic_levels_register_wsform_endpoint' );

/**
 * Custom WSForm endpoint callback.
 *
 * @param WP_REST_Request $request Request object.
 * @return WP_REST_Response Response object.
 */
function pmpro_magic_levels_wsform_endpoint_callback( $request ) {
	$params = $request->get_json_params();

	// Apply custom logic or defaults specific to WSForm.
	$level_data = array(
		'name'           => isset( $params['name'] ) ? sanitize_text_field( $params['name'] ) : '',
		'billing_amount' => isset( $params['billing_amount'] ) ? floatval( $params['billing_amount'] ) : 0,
		'cycle_period'   => isset( $params['cycle_period'] ) ? sanitize_text_field( $params['cycle_period'] ) : 'Month',
		'cycle_number'   => isset( $params['cycle_number'] ) ? intval( $params['cycle_number'] ) : 1,
		'description'    => isset( $params['description'] ) ? sanitize_textarea_field( $params['description'] ) : '',
	);

	// Add custom prefix for WSForm submissions.
	$level_data['name'] = 'WSForm - ' . $level_data['name'];

	// Process level.
	$result = pmpro_magic_levels_process( $level_data );

	return new WP_REST_Response( $result, $result['success'] ? 200 : 400 );
}

// ============================================
// METHOD 6: Conditional Logic Based on Form Fields
// ============================================

/**
 * Process WSForm with conditional logic.
 *
 * This example shows how to apply different rules based on form values.
 *
 * @param object $submit Submit object.
 * @return void
 */
function pmpro_magic_levels_wsform_conditional_handler( $submit ) {

	// Get form data.
	$form_object = $submit->form_object;

	// Extract field values.
	$membership_type = WS_Form_Common::get_object_meta_value( $form_object, 'field_1', '' );
	$user_count      = WS_Form_Common::get_object_meta_value( $form_object, 'field_2', 1 );
	$billing_period  = WS_Form_Common::get_object_meta_value( $form_object, 'field_3', 'Month' );

	// Determine pricing based on membership type and user count.
	$base_price = 0;
	$level_name = '';

	switch ( $membership_type ) {
		case 'basic':
			$base_price = 9.99;
			$level_name = 'Basic Plan';
			break;

		case 'pro':
			$base_price = 29.99;
			$level_name = 'Pro Plan';
			break;

		case 'enterprise':
			$base_price = 99.99;
			$level_name = 'Enterprise Plan';
			break;

		default:
			$base_price = 19.99;
			$level_name = 'Standard Plan';
			break;
	}

	// Calculate price based on user count.
	$final_price = $base_price * intval( $user_count );

	// Apply discount for yearly billing.
	if ( 'Year' === $billing_period ) {
		$final_price = $final_price * 10; // 2 months free.
		$level_name .= ' (Yearly)';
	}

	// Build level data.
	$level_data = array(
		'name'           => $level_name,
		'billing_amount' => $final_price,
		'cycle_period'   => $billing_period,
		'cycle_number'   => 1,
		'description'    => "For {$user_count} users",
	);

	// Process level.
	$result = pmpro_magic_levels_process( $level_data );

	if ( $result['success'] ) {
		wp_safe_redirect( $result['redirect_url'] );
		exit;
	}
}
add_action( 'wsf_submit_post_complete', 'pmpro_magic_levels_wsform_conditional_handler', 10, 1 );

// ============================================
// METHOD 7: Store Submission ID for Tracking
// ============================================

/**
 * Process WSForm and store submission ID in level meta.
 *
 * This allows you to track which form submission created each level.
 *
 * @param object $submit Submit object.
 * @return void
 */
function pmpro_magic_levels_wsform_tracking_handler( $submit ) {

	// Get form data.
	$form_object = $submit->form_object;
	$submit_id   = $submit->id;

	// Extract field values.
	$level_data = array(
		'name'           => WS_Form_Common::get_object_meta_value( $form_object, 'field_1', '' ),
		'billing_amount' => WS_Form_Common::get_object_meta_value( $form_object, 'field_2', 0 ),
		'cycle_period'   => WS_Form_Common::get_object_meta_value( $form_object, 'field_3', 'Month' ),
		'cycle_number'   => 1,
	);

	// Process level.
	$result = pmpro_magic_levels_process( $level_data );

	if ( $result['success'] ) {
		// Store WSForm submission ID in level meta for tracking.
		$level_id = $result['level_id'];
		update_option( "pmpro_level_{$level_id}_wsform_submit_id", $submit_id );

		// Redirect to checkout.
		wp_safe_redirect( $result['redirect_url'] );
		exit;
	}
}
add_action( 'wsf_submit_post_complete', 'pmpro_magic_levels_wsform_tracking_handler', 10, 1 );

// ============================================
// COMPLETE WSFORM WEBHOOK CONFIGURATION EXAMPLE
// ============================================

/**
 * STEP-BY-STEP WEBHOOK SETUP:
 *
 * 1. CREATE YOUR WSFORM:
 *    - Add a text field for "Membership Name" (Field ID: 1)
 *    - Add a number field for "Price" (Field ID: 2)
 *    - Add a select field for "Billing Period" with options: Month, Year (Field ID: 3)
 *    - Add a textarea for "Description" (Field ID: 4) [optional]
 *
 * 2. ADD WEBHOOK ACTION:
 *    - Go to Actions tab in WSForm
 *    - Click "Add Action"
 *    - Select "Webhook"
 *    - Name: "Create Membership Level"
 *
 * 3. CONFIGURE WEBHOOK:
 *    URL: https://yoursite.com/wp-json/pmpro-magic-levels/v1/process
 *    Method: POST
 *    Content Type: application/json
 *
 * 4. WEBHOOK BODY (Basic):
 *    {
 *      "name": "#field(1)",
 *      "billing_amount": "#field(2)",
 *      "cycle_period": "#field(3)",
 *      "cycle_number": 1
 *    }
 *
 * 5. WEBHOOK BODY (With All Options):
 *    {
 *      "name": "#field(1)",
 *      "billing_amount": "#field(2)",
 *      "cycle_period": "#field(3)",
 *      "cycle_number": 1,
 *      "description": "#field(4)",
 *      "initial_payment": 0,
 *      "trial_amount": 0,
 *      "trial_limit": 0
 *    }
 *
 * 6. WEBHOOK BODY (With Authentication):
 *    {
 *      "auth_key": "your-secret-key-123",
 *      "name": "#field(1)",
 *      "billing_amount": "#field(2)",
 *      "cycle_period": "#field(3)",
 *      "cycle_number": 1
 *    }
 *
 * 7. ADD REDIRECT ACTION:
 *    - Add another action after webhook
 *    - Select "Redirect"
 *    - URL: #webhook_response(redirect_url)
 *    - This will redirect to the PMPro checkout page
 *
 * 8. ERROR HANDLING (Optional):
 *    - Add a "Message" action
 *    - Condition: #webhook_response(success) == false
 *    - Message: #webhook_response(error)
 *    - Type: Danger
 *
 * 9. ENABLE AUTHENTICATION (Optional):
 *    Add to functions.php:
 *
 *    add_filter( 'pmpro_magic_levels_webhook_require_auth', '__return_true' );
 *    add_filter( 'pmpro_magic_levels_webhook_auth_key', function() {
 *        return 'your-secret-key-123';
 *    } );
 *
 * 10. TEST YOUR FORM:
 *     - Submit the form with test data
 *     - Check if level is created in PMPro
 *     - Verify redirect to checkout page
 */

// ============================================
// TROUBLESHOOTING TIPS
// ============================================

/**
 * Enable debug logging for WSForm submissions.
 *
 * Add this to functions.php to log all WSForm submissions.
 *
 * @param object $submit Submit object.
 * @return void
 */
function pmpro_magic_levels_wsform_debug_logger( $submit ) {
	$form_object = $submit->form_object;

	// Log form data.
	error_log( 'WSForm Submission Data: ' . print_r( $form_object, true ) );

	// Log field values.
	if ( isset( $form_object->fields ) ) {
		foreach ( $form_object->fields as $field ) {
			error_log( "Field {$field->id}: {$field->value}" );
		}
	}
}
// Uncomment to enable debugging:
// add_action( 'wsf_submit_post_complete', 'pmpro_magic_levels_wsform_debug_logger', 5, 1 );

/**
 * COMMON ISSUES AND SOLUTIONS:
 *
 * 1. "Field values are empty"
 *    - Check your field IDs in WSForm
 *    - Use WS_Form_Common::get_object_meta_value() to get values
 *    - Enable debug logging to see actual field structure
 *
 * 2. "Webhook not firing"
 *    - Verify webhook URL is correct
 *    - Check if PMPro Magic Levels plugin is active
 *    - Test webhook URL directly with Postman or curl
 *
 * 3. "Redirect not working"
 *    - Make sure you're using #webhook_response(redirect_url)
 *    - Check if webhook returns success: true
 *    - Verify redirect action is after webhook action
 *
 * 4. "Authentication errors"
 *    - Verify auth_key matches in both webhook and filter
 *    - Check if authentication is enabled via filter
 *    - Test without authentication first
 *
 * 5. "Validation errors"
 *    - Check PMPro Magic Levels validation rules
 *    - Verify price meets minimum/maximum requirements
 *    - Check if cycle_period is in allowed list
 */
