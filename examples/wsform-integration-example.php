<?php
/**
 * WSForm Integration Examples.
 *
 * Complete guide for integrating PMPro Magic Levels with WSForm.
 * Includes both PHP action hooks and REST API webhook methods.
 *
 * NOTE: The API returns level_id, not redirect_url. 
 * In WSForm redirect action, use: /checkout/?level=#webhook_response(level_id)
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
 *    IMPORTANT: WSForm has field type settings for webhook body fields.
 *    When you add a field to the webhook body, you can set its type:
 *    - Source (default) - Uses the webhook settings type
 *    - String - Sends as text
 *    - Integer - Sends as whole number
 *    - Float - Sends as decimal number
 *    - Boolean - Sends as true/false
 *
 *    CORRECT FIELD TYPES:
 *    {
 *      "name": "#field(1)",              ← Type: String or Source
 *      "billing_amount": "#field(2)",    ← Type: Float (IMPORTANT!)
 *      "cycle_period": "#field(3)",      ← Type: String or Source
 *      "cycle_number": 1                 ← Type: Integer or Source
 *    }
 *
 *    HOW TO SET FIELD TYPES IN WSFORM:
 *    a) Click on the field in the webhook body editor
 *    b) Look for "Type" dropdown on the right side
 *    c) Set the appropriate type:
 *       - name → String
 *       - billing_amount → Float
 *       - cycle_period → String
 *       - cycle_number → Integer
 *       - initial_payment → Float
 *       - trial_amount → Float
 *       - trial_limit → Integer
 *       - billing_limit → Integer
 *       - expiration_number → Integer
 *
 *    Replace field(1), field(2), field(3) with your actual field IDs.
 *
 * 6. ALTERNATIVE: Use Source type with proper JSON syntax
 *
 *    If you set all fields to "Source" type, you need to write proper JSON:
 *
 *    {
 *      "name": "#field(1)",
 *      "billing_amount": #field(2),      ← No quotes for numbers
 *      "cycle_period": "#field(3)",
 *      "cycle_number": 1                 ← No quotes for numbers
 *    }
 *
 *    Notice: billing_amount has NO quotes around #field(2)
 *    This tells WSForm to send it as a number, not a string.
 *
 * 7. Optional - Add authentication:
 *
 *    {
 *      "auth_key": "your-secret-key-here",
 *      "name": "#field(1)",
 *      "billing_amount": "#field(2)",
 *      "cycle_period": "#field(3)",
 *      "cycle_number": 1
 *    }
 *
 *    Set field types:
 *    - auth_key → String
 *    - name → String
 *    - billing_amount → Float
 *    - cycle_period → String
 *    - cycle_number → Integer
 *
 *    Then enable authentication in your functions.php:
 *
 *    add_filter( 'pmpro_magic_levels_webhook_require_auth', '__return_true' );
 *    add_filter( 'pmpro_magic_levels_webhook_auth_key', function() {
 *        return 'your-secret-key-here';
 *    } );
 *
 * 7. To redirect after success, add a "Redirect" action after the webhook
 *    URL: /checkout/?level=#webhook_response(level_id)
 *    Or custom: /custom-checkout/?level=#webhook_response(level_id)
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
 *    - URL: /checkout/?level=#webhook_response(level_id)
 *    - Or custom: /custom-checkout/?level=#webhook_response(level_id)
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
// METHOD 8: Hardcoded Values in PHP
// ============================================

/**
 * Process WSForm with hardcoded values.
 *
 * This example shows how to hardcode certain values instead of
 * getting them from form fields. Useful for:
 * - Fixed pricing tiers
 * - Predefined membership types
 * - Hidden configuration values
 *
 * @param object $submit Submit object.
 * @return void
 */
function pmpro_magic_levels_wsform_hardcoded_handler( $submit ) {

	// Get form data.
	$form_object = $submit->form_object;

	// Only get the name from the form.
	$user_name = WS_Form_Common::get_object_meta_value( $form_object, 'field_1', '' );

	// Hardcode everything else.
	$level_data = array(
		'name'           => 'Premium - ' . sanitize_text_field( $user_name ), // Hardcoded prefix.
		'billing_amount' => 49.99,        // Hardcoded price.
		'cycle_period'   => 'Month',      // Hardcoded period.
		'cycle_number'   => 1,            // Hardcoded cycle.
		'description'    => 'Premium membership with full access', // Hardcoded description.
		'confirmation'   => 'Welcome to Premium!', // Hardcoded confirmation.
	);

	// Process level.
	$result = pmpro_magic_levels_process( $level_data );

	if ( $result['success'] ) {
		wp_safe_redirect( $result['redirect_url'] );
		exit;
	}
}
add_action( 'wsf_submit_post_complete', 'pmpro_magic_levels_wsform_hardcoded_handler', 10, 1 );

// ============================================
// METHOD 9: Hardcoded Values Based on Form ID
// ============================================

/**
 * Use different hardcoded values for different forms.
 *
 * This allows you to create multiple WSForms, each with
 * its own hardcoded pricing and settings.
 *
 * @param object $submit Submit object.
 * @return void
 */
function pmpro_magic_levels_wsform_multi_form_handler( $submit ) {

	// Get form ID.
	$form_id = $submit->form_id;

	// Get user input (just the name).
	$form_object = $submit->form_object;
	$user_name   = WS_Form_Common::get_object_meta_value( $form_object, 'field_1', '' );

	// Define hardcoded settings per form.
	$form_configs = array(
		// Form ID 123 = Basic Plan.
		123 => array(
			'prefix'         => 'Basic',
			'billing_amount' => 9.99,
			'cycle_period'   => 'Month',
			'description'    => 'Basic membership tier',
		),
		// Form ID 124 = Pro Plan.
		124 => array(
			'prefix'         => 'Pro',
			'billing_amount' => 29.99,
			'cycle_period'   => 'Month',
			'description'    => 'Pro membership tier',
		),
		// Form ID 125 = Enterprise Plan.
		125 => array(
			'prefix'         => 'Enterprise',
			'billing_amount' => 99.99,
			'cycle_period'   => 'Year',
			'description'    => 'Enterprise membership tier',
		),
	);

	// Get config for this form.
	if ( ! isset( $form_configs[ $form_id ] ) ) {
		return; // Form not configured.
	}

	$config = $form_configs[ $form_id ];

	// Build level data with hardcoded values.
	$level_data = array(
		'name'           => $config['prefix'] . ' - ' . sanitize_text_field( $user_name ),
		'billing_amount' => $config['billing_amount'],
		'cycle_period'   => $config['cycle_period'],
		'cycle_number'   => 1,
		'description'    => $config['description'],
	);

	// Process level.
	$result = pmpro_magic_levels_process( $level_data );

	if ( $result['success'] ) {
		wp_safe_redirect( $result['redirect_url'] );
		exit;
	}
}
add_action( 'wsf_submit_post_complete', 'pmpro_magic_levels_wsform_multi_form_handler', 10, 1 );

// ============================================
// METHOD 10: Hardcoded Values in Webhook Body
// ============================================

/**
 * HARDCODING VALUES DIRECTLY IN WSFORM WEBHOOK:
 *
 * You can hardcode values directly in the WSForm webhook body
 * instead of using field variables. This is useful when you want
 * to create a simple form without exposing all options to users.
 *
 * SETUP IN WSFORM:
 *
 * 1. Go to Actions > Add Action > Webhook
 *
 * 2. Configure webhook:
 *    URL: https://yoursite.com/wp-json/pmpro-magic-levels/v1/process
 *    Method: POST
 *    Content Type: application/json
 *
 * 3. OPTION A - Hardcode everything except name:
 *    {
 *      "name": "Premium - #field(1)",
 *      "billing_amount": 49.99,
 *      "cycle_period": "Month",
 *      "cycle_number": 1,
 *      "description": "Premium membership with full access"
 *    }
 *
 * 4. OPTION B - Hardcode prefix and settings:
 *    {
 *      "name": "Pro Plan - #field(1)",
 *      "billing_amount": 29.99,
 *      "cycle_period": "Month",
 *      "cycle_number": 1,
 *      "initial_payment": 0,
 *      "trial_amount": 0,
 *      "trial_limit": 1,
 *      "description": "Professional membership"
 *    }
 *
 * 5. OPTION C - Mix hardcoded and field values:
 *    {
 *      "name": "Custom - #field(1)",
 *      "billing_amount": "#field(2)",
 *      "cycle_period": "Month",
 *      "cycle_number": 1,
 *      "description": "Created on #date_time_local"
 *    }
 *
 * 6. OPTION D - Use WSForm calculated fields:
 *    {
 *      "name": "Team Plan - #field(1)",
 *      "billing_amount": "#calc(1)",
 *      "cycle_period": "Month",
 *      "cycle_number": 1,
 *      "description": "For #field(2) users"
 *    }
 *
 *    Where #calc(1) is a calculated field that computes
 *    the price based on other form inputs.
 *
 * 7. OPTION E - Conditional hardcoded values using WSForm logic:
 *    Create multiple webhook actions with conditions:
 *
 *    Webhook 1 (Condition: #field(2) == "basic"):
 *    {
 *      "name": "Basic - #field(1)",
 *      "billing_amount": 9.99,
 *      "cycle_period": "Month",
 *      "cycle_number": 1
 *    }
 *
 *    Webhook 2 (Condition: #field(2) == "pro"):
 *    {
 *      "name": "Pro - #field(1)",
 *      "billing_amount": 29.99,
 *      "cycle_period": "Month",
 *      "cycle_number": 1
 *    }
 */

// ============================================
// METHOD 11: Hybrid Approach - Custom Endpoint with Hardcoded Defaults
// ============================================

/**
 * Create a custom endpoint that accepts minimal data and fills in the rest.
 *
 * This is useful when you want to simplify the webhook body in WSForm
 * while still having control over defaults in PHP.
 *
 * @return void
 */
function pmpro_magic_levels_register_simple_wsform_endpoint() {
	register_rest_route(
		'my-site/v1',
		'/simple-membership',
		array(
			'methods'             => 'POST',
			'callback'            => 'pmpro_magic_levels_simple_wsform_callback',
			'permission_callback' => '__return_true',
		)
	);
}
add_action( 'rest_api_init', 'pmpro_magic_levels_register_simple_wsform_endpoint' );

/**
 * Simple endpoint callback with hardcoded defaults.
 *
 * WSForm only needs to send: { "name": "John Doe" }
 * Everything else is hardcoded here.
 *
 * @param WP_REST_Request $request Request object.
 * @return WP_REST_Response Response object.
 */
function pmpro_magic_levels_simple_wsform_callback( $request ) {
	$params = $request->get_json_params();

	// Get only the name from WSForm.
	$user_name = isset( $params['name'] ) ? sanitize_text_field( $params['name'] ) : 'Member';

	// Hardcode everything else.
	$level_data = array(
		'name'           => 'Premium - ' . $user_name,
		'billing_amount' => 49.99,
		'cycle_period'   => 'Month',
		'cycle_number'   => 1,
		'description'    => 'Premium membership with full access',
		'confirmation'   => 'Welcome to our premium community!',
		'initial_payment' => 0, // Free first month.
	);

	// Process level.
	$result = pmpro_magic_levels_process( $level_data );

	return new WP_REST_Response( $result, $result['success'] ? 200 : 400 );
}

/**
 * WSFORM WEBHOOK SETUP FOR SIMPLE ENDPOINT:
 *
 * URL: https://yoursite.com/wp-json/my-site/v1/simple-membership
 * Method: POST
 * Content Type: application/json
 *
 * Body:
 * {
 *   "name": "#field(1)"
 * }
 *
 * That's it! Everything else is hardcoded in PHP.
 */

// ============================================
// METHOD 12: Hidden Fields with Hardcoded Values
// ============================================

/**
 * Use WSForm hidden fields to store hardcoded values.
 *
 * This approach keeps the hardcoded values in WSForm itself,
 * making them easier to manage without touching PHP code.
 *
 * SETUP IN WSFORM:
 *
 * 1. Add visible fields:
 *    - Field 1: Text field for "Name"
 *
 * 2. Add hidden fields with default values:
 *    - Field 2: Hidden field, Default Value: "49.99"
 *    - Field 3: Hidden field, Default Value: "Month"
 *    - Field 4: Hidden field, Default Value: "Premium Membership"
 *
 * 3. Configure webhook:
 *    {
 *      "name": "#field(1)",
 *      "billing_amount": "#field(2)",
 *      "cycle_period": "#field(3)",
 *      "cycle_number": 1,
 *      "description": "#field(4)"
 *    }
 *
 * BENEFITS:
 * - Easy to change values without editing code
 * - Can duplicate form and change hidden field values
 * - Non-technical users can manage pricing
 */

// ============================================
// METHOD 13: Using WSForm Meta Data
// ============================================

/**
 * Store hardcoded values in WSForm meta data.
 *
 * This allows you to configure different settings per form
 * using WSForm's custom meta fields.
 *
 * @param object $submit Submit object.
 * @return void
 */
function pmpro_magic_levels_wsform_meta_handler( $submit ) {

	// Get form object.
	$form_id = $submit->form_id;

	// Get form meta data (you can set these in WSForm settings).
	$form = new WS_Form_Form();
	$form->id = $form_id;
	$form_object = $form->db_read( true, true );

	// Get custom meta values (if set in WSForm).
	$default_price = isset( $form_object->meta->default_price ) ? $form_object->meta->default_price : 29.99;
	$default_period = isset( $form_object->meta->default_period ) ? $form_object->meta->default_period : 'Month';
	$level_prefix = isset( $form_object->meta->level_prefix ) ? $form_object->meta->level_prefix : 'Member';

	// Get user input.
	$submit_form_object = $submit->form_object;
	$user_name = WS_Form_Common::get_object_meta_value( $submit_form_object, 'field_1', '' );

	// Build level data with meta values.
	$level_data = array(
		'name'           => $level_prefix . ' - ' . sanitize_text_field( $user_name ),
		'billing_amount' => floatval( $default_price ),
		'cycle_period'   => sanitize_text_field( $default_period ),
		'cycle_number'   => 1,
	);

	// Process level.
	$result = pmpro_magic_levels_process( $level_data );

	if ( $result['success'] ) {
		wp_safe_redirect( $result['redirect_url'] );
		exit;
	}
}
add_action( 'wsf_submit_post_complete', 'pmpro_magic_levels_wsform_meta_handler', 10, 1 );

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
 * 1. "Webhook error - HTTP status code: 400 (Bad Request)"
 *    This means validation failed. Common causes:
 *
 *    a) Missing required field (name):
 *       - Check that "name" field is included in webhook body
 *       - Verify field ID is correct: "name": "#field(1)"
 *       - Make sure field is not empty when submitting
 *
 *    b) Invalid price format:
 *       - Price must be a number, not a string
 *       - Use: "billing_amount": #field(2) (no quotes around #field)
 *       - NOT: "billing_amount": "#field(2)" (this sends it as string)
 *
 *    c) Price validation rules:
 *       - Check minimum price requirement (default: $0)
 *       - Check maximum price requirement (default: $9999.99)
 *       - Check price increment requirement (default: $1.00)
 *       - Verify price is not below minimum or above maximum
 *
 *    d) Invalid cycle_period:
 *       - Must be one of: "Day", "Week", "Month", "Year"
 *       - Check capitalization (case-sensitive)
 *       - Default allowed: ['Day', 'Week', 'Month', 'Year']
 *
 *    e) Invalid cycle_number:
 *       - Must be an integer
 *       - Default allowed: [1, 2, 3, 6, 12]
 *       - Use: "cycle_number": 1 (no quotes)
 *
 *    f) Name validation:
 *       - Check minimum name length (default: 1 character)
 *       - Check maximum name length (default: 255 characters)
 *       - Check for blacklisted words
 *       - Check name pattern if regex is set
 *
 *    g) Rate limiting:
 *       - Too many requests from same IP
 *       - Default: 100 requests per hour
 *       - Wait or increase limit via filter
 *
 *    h) Daily limit exceeded:
 *       - Too many levels created today
 *       - Default: 1000 levels per day
 *       - Wait until tomorrow or increase limit
 *
 *    DEBUGGING STEPS:
 *
 *    Step 1: Check webhook response in WSForm
 *    - Go to WSForm > Submissions
 *    - Click on the failed submission
 *    - Look at the webhook response
 *    - It should show the exact error message
 *
 *    Step 2: Test with minimal data
 *    Use this minimal webhook body:
 *    {
 *      "name": "Test Level",
 *      "billing_amount": 29.99,
 *      "cycle_period": "Month",
 *      "cycle_number": 1
 *    }
 *
 *    Step 3: Check field types in webhook body
 *    CORRECT:
 *    {
 *      "name": "#field(1)",              ← String (with quotes)
 *      "billing_amount": #field(2),      ← Number (no quotes)
 *      "cycle_number": 1,                ← Number (no quotes)
 *      "cycle_period": "#field(3)"       ← String (with quotes)
 *    }
 *
 *    INCORRECT:
 *    {
 *      "name": "#field(1)",
 *      "billing_amount": "#field(2)",    ← Wrong! This sends string "29.99"
 *      "cycle_number": "1",              ← Wrong! This sends string "1"
 *      "cycle_period": "#field(3)"
 *    }
 *
 *    Step 4: Temporarily disable validation rules
 *    Add to functions.php to test:
 *    add_filter( 'pmpro_magic_levels_min_price', function() { return 0.00; } );
 *    add_filter( 'pmpro_magic_levels_max_price', function() { return 99999.99; } );
 *    add_filter( 'pmpro_magic_levels_price_increment', function() { return 0.01; } );
 *    add_filter( 'pmpro_magic_levels_allowed_periods', function() {
 *        return array( 'Day', 'Week', 'Month', 'Year' );
 *    } );
 *
 *    Step 5: Enable WordPress debug logging
 *    Add to wp-config.php:
 *    define( 'WP_DEBUG', true );
 *    define( 'WP_DEBUG_LOG', true );
 *    define( 'WP_DEBUG_DISPLAY', false );
 *
 *    Then check: wp-content/debug.log
 *
 * 2. "Field values are empty"
 *    - Check your field IDs in WSForm
 *    - Use WS_Form_Common::get_object_meta_value() to get values
 *    - Enable debug logging to see actual field structure
 *
 * 3. "Webhook not firing"
 *    - Verify webhook URL is correct
 *    - Check if PMPro Magic Levels plugin is active
 *    - Test webhook URL directly with Postman or curl
 *
 * 4. "Redirect not working"
 *    - Use: /checkout/?level=#webhook_response(level_id)
 *    - Check if webhook returns success: true
 *    - Verify redirect action is after webhook action
 *
 * 5. "Authentication errors"
 *    - Verify auth_key matches in both webhook and filter
 *    - Check if authentication is enabled via filter
 *    - Test without authentication first
 *
 * 6. "Validation errors"
 *    - Check PMPro Magic Levels validation rules
 *    - Verify price meets minimum/maximum requirements
 *    - Check if cycle_period is in allowed list
 */

// ============================================
// CHOOSING THE RIGHT METHOD
// ============================================

/**
 * WHICH METHOD SHOULD YOU USE?
 *
 * FOR SIMPLE FORMS WITH FIXED PRICING:
 * - Use METHOD 8 (Hardcoded Values in PHP)
 * - Use METHOD 10 (Hardcoded Values in Webhook Body)
 * - Use METHOD 11 (Custom Endpoint with Defaults)
 *
 * FOR MULTIPLE FORMS WITH DIFFERENT PRICING:
 * - Use METHOD 9 (Hardcoded Values Based on Form ID)
 * - Use METHOD 12 (Hidden Fields)
 *
 * FOR FLEXIBLE FORMS WITH USER INPUT:
 * - Use METHOD 1 (Basic PHP Action Hook)
 * - Use METHOD 4 (REST API Webhook)
 *
 * FOR COMPLEX PRICING LOGIC:
 * - Use METHOD 6 (Conditional Logic)
 * - Use METHOD 10 OPTION D (Calculated Fields)
 *
 * FOR EASY MANAGEMENT BY NON-DEVELOPERS:
 * - Use METHOD 12 (Hidden Fields)
 * - Use METHOD 13 (WSForm Meta Data)
 *
 * FOR TRACKING AND ANALYTICS:
 * - Use METHOD 7 (Store Submission ID)
 *
 * QUICK COMPARISON:
 *
 * | Method | Hardcoded? | Easy to Change? | Requires PHP? | Best For |
 * |--------|-----------|-----------------|---------------|----------|
 * | Method 8  | Yes | No (PHP edit) | Yes | Fixed pricing |
 * | Method 9  | Yes | No (PHP edit) | Yes | Multiple forms |
 * | Method 10 | Yes | Yes (WSForm UI) | No | Simple setup |
 * | Method 11 | Yes | No (PHP edit) | Yes | Custom logic |
 * | Method 12 | Yes | Yes (WSForm UI) | No | Non-technical users |
 * | Method 13 | Yes | Yes (WSForm meta) | Yes | Per-form config |
 */

// ============================================
// EXAMPLE USE CASES
// ============================================

/**
 * USE CASE 1: Single Product with Fixed Price
 * SOLUTION: Method 10 - Hardcode in webhook body
 *
 * WSForm: Just collect name and email
 * Webhook Body:
 * {
 *   "name": "Premium - #field(1)",
 *   "billing_amount": 49.99,
 *   "cycle_period": "Month",
 *   "cycle_number": 1
 * }
 *
 * USE CASE 2: Three Pricing Tiers (Basic, Pro, Enterprise)
 * SOLUTION: Method 9 - Three separate forms with hardcoded values
 *
 * Form 123 (Basic): Hardcoded $9.99
 * Form 124 (Pro): Hardcoded $29.99
 * Form 125 (Enterprise): Hardcoded $99.99
 *
 * USE CASE 3: User Chooses Plan from Dropdown
 * SOLUTION: Method 10 OPTION E - Conditional webhooks
 *
 * WSForm: Dropdown with "basic", "pro", "enterprise"
 * Multiple webhook actions with conditions
 *
 * USE CASE 4: Variable Pricing Based on User Count
 * SOLUTION: Method 6 - Conditional logic in PHP
 *
 * WSForm: Number field for user count
 * PHP: Calculate price based on tiers
 *
 * USE CASE 5: Simple Name-Only Form
 * SOLUTION: Method 11 - Custom endpoint
 *
 * WSForm: Just name field
 * Webhook: { "name": "#field(1)" }
 * PHP: Everything else hardcoded
 */


// ============================================
// UNDERSTANDING WSFORM FIELD TYPES IN WEBHOOK
// ============================================

/**
 * WSForm has a "Type" setting for each field in the webhook body.
 * This is CRITICAL for proper data formatting.
 *
 * AVAILABLE TYPES:
 *
 * 1. SOURCE (Default)
 *    - Uses the webhook's default type handling
 *    - Requires proper JSON syntax (quotes for strings, no quotes for numbers)
 *    - Example: "billing_amount": #field(2)  ← No quotes around #field(2)
 *    - IMPORTANT: If using Source type, you MUST write JSON correctly
 *    - This is the most error-prone option for beginners
 *
 * 2. STRING
 *    - Sends value as text
 *    - Use for: name, cycle_period, description, confirmation
 *    - Example: "name": "#field(1)"
 *
 * 3. INTEGER
 *    - Sends value as whole number
 *    - Use for: cycle_number, trial_limit, billing_limit, expiration_number
 *    - Example: "cycle_number": "#field(3)"  ← Will be sent as integer
 *
 * 4. FLOAT
 *    - Sends value as decimal number
 *    - Use for: billing_amount, initial_payment, trial_amount
 *    - Example: "billing_amount": "#field(2)"  ← Will be sent as float
 *
 * 5. BOOLEAN
 *    - Sends value as true/false
 *    - Use for: allow_signups (if using checkbox)
 *    - Example: "allow_signups": "#field(5)"  ← Will be sent as boolean
 *
 * HOW TO SET FIELD TYPES IN WSFORM:
 *
 * Step 1: In webhook action, go to "Body" tab
 * Step 2: Click on any field value in the JSON (e.g., click on "#field(2)")
 * Step 3: Look at the right sidebar - you'll see "Type" dropdown
 * Step 4: Select the appropriate type from the dropdown
 *
 * RECOMMENDED FIELD TYPE MAPPING:
 *
 * Field Name              | WSForm Type | Example Value
 * ----------------------- | ----------- | -------------
 * name                    | String      | "Premium Plan"
 * description             | String      | "Full access"
 * confirmation            | String      | "Welcome!"
 * billing_amount          | Float       | 29.99
 * initial_payment         | Float       | 0.00
 * trial_amount            | Float       | 1.00
 * cycle_number            | Integer     | 1
 * trial_limit             | Integer     | 1
 * billing_limit           | Integer     | 12
 * expiration_number       | Integer     | 30
 * cycle_period            | String      | "Month"
 * expiration_period       | String      | "Day"
 * allow_signups           | Integer     | 1
 *
 * EXAMPLE WEBHOOK BODY WITH TYPES:
 *
 * {
 *   "name": "#field(1)",              ← Set Type: String
 *   "billing_amount": "#field(2)",    ← Set Type: Float
 *   "cycle_period": "#field(3)",      ← Set Type: String
 *   "cycle_number": 1,                ← Set Type: Integer (or Source)
 *   "description": "#field(4)"        ← Set Type: String
 * }
 *
 * COMMON MISTAKE:
 *
 * ❌ WRONG: Setting billing_amount type to "String"
 * This sends: "billing_amount": "29.99"  (string)
 * Result: Validation error or incorrect processing
 *
 * ✅ CORRECT: Setting billing_amount type to "Float"
 * This sends: "billing_amount": 29.99  (number)
 * Result: Works perfectly!
 *
 * WHY THIS MATTERS:
 *
 * The PMPro Magic Levels API expects numbers to be actual numbers,
 * not strings. If you send "29.99" (string) instead of 29.99 (number),
 * PHP's floatval() will convert it, but it's better to send the
 * correct type from WSForm.
 *
 * TESTING YOUR FIELD TYPES:
 *
 * 1. Submit your form
 * 2. Go to WSForm > Submissions
 * 3. Click on the submission
 * 4. Look at the webhook request body
 * 5. Verify numbers don't have quotes around them
 *
 * Correct:  "billing_amount": 29.99
 * Wrong:    "billing_amount": "29.99"
 *
 * IF YOU'RE USING "SOURCE" TYPE AND GETTING 400 ERROR:
 *
 * The problem is likely your JSON syntax. With "Source" type, WSForm
 * interprets the JSON literally. This means:
 *
 * ❌ WRONG (Source type with quotes):
 * {
 *   "name": "#field(1)",
 *   "billing_amount": "#field(2)",    ← Has quotes, sends as string
 *   "cycle_number": "1"               ← Has quotes, sends as string
 * }
 *
 * ✅ CORRECT (Source type without quotes for numbers):
 * {
 *   "name": "#field(1)",
 *   "billing_amount": #field(2),      ← No quotes, sends as number
 *   "cycle_number": 1                 ← No quotes, sends as number
 * }
 *
 * EASIEST SOLUTION - Use Explicit Types Instead of Source:
 *
 * Instead of using "Source" type and worrying about JSON syntax,
 * just set explicit types for each field:
 *
 * 1. Click on "#field(1)" → Set Type: String
 * 2. Click on "#field(2)" → Set Type: Float
 * 3. Click on "#field(3)" → Set Type: String
 * 4. Click on "1" → Set Type: Integer
 *
 * Now you can write it with quotes everywhere:
 * {
 *   "name": "#field(1)",
 *   "billing_amount": "#field(2)",
 *   "cycle_period": "#field(3)",
 *   "cycle_number": 1
 * }
 *
 * WSForm will automatically convert them to the correct types!
 */

// ============================================
// TROUBLESHOOTING "SOURCE" TYPE WITH 400 ERROR
// ============================================

/**
 * If you're using "Source" type (default) and getting 400 error:
 *
 * PROBLEM:
 * When all fields are set to "Source" type, WSForm reads your JSON
 * literally. If you write:
 *
 * {
 *   "billing_amount": "#field(2)"
 * }
 *
 * WSForm sends: "billing_amount": "29.99" (as string)
 * Because you put quotes around #field(2)
 *
 * SOLUTION 1: Remove quotes from number fields
 *
 * {
 *   "name": "#field(1)",
 *   "billing_amount": #field(2),        ← No quotes!
 *   "cycle_period": "#field(3)",
 *   "cycle_number": 1                   ← No quotes!
 * }
 *
 * SOLUTION 2: Change from "Source" to explicit types (RECOMMENDED)
 *
 * Keep the quotes, but change field types:
 * 1. Click on "#field(2)" in webhook body
 * 2. Change Type from "Source" to "Float"
 * 3. Click on "1"
 * 4. Change Type from "Source" to "Integer"
 *
 * Now this works:
 * {
 *   "name": "#field(1)",
 *   "billing_amount": "#field(2)",      ← Quotes OK, type is Float
 *   "cycle_period": "#field(3)",
 *   "cycle_number": 1                   ← Type is Integer
 * }
 *
 * WHY SOLUTION 2 IS BETTER:
 * - Less confusing (quotes everywhere)
 * - Easier to read and maintain
 * - Less prone to syntax errors
 * - WSForm handles the conversion for you
 */

// ============================================
// QUICK FIX FOR 400 ERROR
// ============================================

/**
 * If you're getting a 400 error, try this minimal webhook body first:
 *
 * STEP 1: Use this exact webhook body in WSForm:
 * {
 *   "name": "Test Membership",
 *   "billing_amount": 29.99,
 *   "cycle_period": "Month",
 *   "cycle_number": 1
 * }
 *
 * If this works, the issue is with your field variables.
 *
 * STEP 2: Add one field at a time:
 * {
 *   "name": "#field(1)",
 *   "billing_amount": 29.99,
 *   "cycle_period": "Month",
 *   "cycle_number": 1
 * }
 *
 * STEP 3: Add the price field with correct type:
 * {
 *   "name": "#field(1)",
 *   "billing_amount": "#field(2)",
 *   "cycle_period": "Month",
 *   "cycle_number": 1
 * }
 *
 * IMPORTANT: Set field type for billing_amount to "Float" in WSForm!
 * Click on the billing_amount field in webhook body editor
 * Set Type dropdown to "Float" (not "String" or "Source")
 *
 * STEP 4: Check the webhook response in WSForm submissions
 * - Go to WSForm > Submissions
 * - Click on your submission
 * - Look for the webhook action
 * - Click on "View" or expand the webhook action
 * - Look at the "Response" section
 * - You should see the actual error message like:
 *   "error": "Name is required"
 *   "error": "Price must be at least $10.00"
 *   "error": "Invalid cycle period"
 * - This tells you EXACTLY what's wrong
 *
 * IMPORTANT WSFORM SETTING:
 * In the webhook action settings, there's a checkbox:
 * "Process Response" - This should be CHECKED
 * 
 * If checked: WSForm will process response and show errors
 * If unchecked: WSForm ignores response (you won't see errors)
 * 
 * Make sure this is CHECKED so you can see the error details!
 *
 * COMMON ERROR MESSAGES AND FIXES:
 *
 * Error: "Name is required"
 * Fix: Add "name" field to webhook body
 *
 * Error: "Price must be at least $10.00"
 * Fix: Increase price or change filter:
 * add_filter( 'pmpro_magic_levels_min_price', function() { return 0.00; } );
 *
 * Error: "Price must be a multiple of $5.00" or "Price must be a multiple of $1"
 * Fix: Use price that's multiple of the increment, or change filter:
 * add_filter( 'pmpro_magic_levels_price_increment', function() { return 0.01; } );
 * 
 * Note: If you see "multiple of $1", you can only use whole dollar amounts
 * like 29, 30, 50 (not 29.99, 30.50, etc.) unless you change the filter.
 *
 * Error: "Invalid cycle period"
 * Fix: Use "Month" not "month" (case-sensitive)
 * Valid values: "Day", "Week", "Month", "Year"
 *
 * Error: "Invalid cycle number"
 * Fix: Use 1, 2, 3, 6, or 12 (default allowed values)
 * Or add filter to allow more:
 * add_filter( 'pmpro_magic_levels_allowed_cycle_numbers', function() {
 *     return array( 1, 2, 3, 6, 12, 24 );
 * } );
 */


// ============================================
// HOW TO SEE THE ACTUAL ERROR MESSAGE
// ============================================

/**
 * When you get "HTTP status code: 400 (Bad Request)", this is just
 * the HTTP status. The ACTUAL error message is in the response body.
 *
 * TO SEE THE REAL ERROR:
 *
 * 1. Go to WSForm > Submissions
 * 2. Find your failed submission
 * 3. Click on it to open details
 * 4. Scroll down to "Actions" section
 * 5. Find the "Webhook" action
 * 6. Click "View" or expand it
 * 7. Look for "Response" section
 * 8. You should see JSON like:
 *
 *    {
 *      "success": false,
 *      "error": "Price must be at least $10.00",
 *      "code": "price_below_minimum"
 *    }
 *
 * The "error" field tells you EXACTLY what's wrong!
 *
 * COMMON ERROR MESSAGES AND SOLUTIONS:
 *
 * Error: "Name is required"
 * → Add "name" field to webhook body
 *
 * Error: "Price must be at least $10.00"
 * → Your price is too low. Either increase it or add this filter:
 *   add_filter( 'pmpro_magic_levels_min_price', function() { return 0.00; } );
 *
 * Error: "Price must be a multiple of $5.00"
 * → Your price must be 5, 10, 15, 20, etc. Or add this filter:
 *   add_filter( 'pmpro_magic_levels_price_increment', function() { return 1.00; } );
 *
 * Error: "Invalid cycle period"
 * → Must be exactly: "Day", "Week", "Month", or "Year" (case-sensitive!)
 *
 * Error: "Invalid cycle number"
 * → Must be one of: 1, 2, 3, 6, 12 (default allowed values)
 *
 * Error: "Name must be at least 5 characters"
 * → Your name is too short. Make it longer or add this filter:
 *   add_filter( 'pmpro_magic_levels_min_name_length', function() { return 1; } );
 *
 * Error: "Rate limit exceeded"
 * → You've submitted too many times. Wait an hour or add this filter:
 *   add_filter( 'pmpro_magic_levels_rate_limit', function() {
 *       return array( 'max_requests' => 1000, 'time_window' => 3600, 'by' => 'ip' );
 *   } );
 *
 * IF YOU CAN'T SEE THE RESPONSE:
 *
 * Make sure "Process Response" is checked in webhook settings:
 * 1. Edit your form in WSForm
 * 2. Go to Actions tab
 * 3. Click on your Webhook action
 * 4. Look for "Process Response" checkbox
 * 5. Make sure it's CHECKED
 * 6. Save and test again
 *
 * ALTERNATIVE: Test with curl or Postman
 *
 * If you still can't see the error, test the webhook directly:
 *
 * curl -X POST https://yoursite.com/wp-json/pmpro-magic-levels/v1/process \
 *   -H "Content-Type: application/json" \
 *   -d '{
 *     "name": "Test Level",
 *     "billing_amount": 29.99,
 *     "cycle_period": "Month",
 *     "cycle_number": 1
 *   }'
 *
 * This will show you the exact response from the API.
 */

// ============================================
// VISUAL COMPARISON: SOURCE VS EXPLICIT TYPES
// ============================================

/**
 * SCENARIO: You have a form with these fields:
 * - Field 1: Text input for "Name"
 * - Field 2: Number input for "Price" (value: 29.99)
 * - Field 3: Select for "Period" (value: "Month")
 *
 * ========================================
 * OPTION A: Using "Source" Type (Default)
 * ========================================
 *
 * Webhook Body:
 * {
 *   "name": "#field(1)",
 *   "billing_amount": #field(2),        ← NO QUOTES!
 *   "cycle_period": "#field(3)",
 *   "cycle_number": 1
 * }
 *
 * Field Types: All set to "Source"
 *
 * What gets sent to API:
 * {
 *   "name": "John Doe",
 *   "billing_amount": 29.99,            ← Number (correct!)
 *   "cycle_period": "Month",
 *   "cycle_number": 1
 * }
 *
 * Result: ✅ Works!
 *
 * ========================================
 * OPTION B: Using Explicit Types (RECOMMENDED)
 * ========================================
 *
 * Webhook Body:
 * {
 *   "name": "#field(1)",
 *   "billing_amount": "#field(2)",      ← HAS QUOTES!
 *   "cycle_period": "#field(3)",
 *   "cycle_number": 1
 * }
 *
 * Field Types:
 * - name: String
 * - billing_amount: Float
 * - cycle_period: String
 * - cycle_number: Integer
 *
 * What gets sent to API:
 * {
 *   "name": "John Doe",
 *   "billing_amount": 29.99,            ← Number (correct!)
 *   "cycle_period": "Month",
 *   "cycle_number": 1
 * }
 *
 * Result: ✅ Works!
 *
 * ========================================
 * OPTION C: Source Type with Quotes (WRONG!)
 * ========================================
 *
 * Webhook Body:
 * {
 *   "name": "#field(1)",
 *   "billing_amount": "#field(2)",      ← HAS QUOTES!
 *   "cycle_period": "#field(3)",
 *   "cycle_number": 1
 * }
 *
 * Field Types: All set to "Source"
 *
 * What gets sent to API:
 * {
 *   "name": "John Doe",
 *   "billing_amount": "29.99",          ← String (wrong!)
 *   "cycle_period": "Month",
 *   "cycle_number": 1
 * }
 *
 * Result: ❌ 400 Error or incorrect processing
 *
 * ========================================
 * SUMMARY
 * ========================================
 *
 * If using "Source" type:
 * - Remove quotes from number fields
 * - Harder to maintain
 * - Easy to make mistakes
 *
 * If using explicit types:
 * - Keep quotes everywhere
 * - Easier to maintain
 * - WSForm handles conversion
 * - RECOMMENDED APPROACH
 *
 * QUICK TEST:
 * After submitting your form, go to WSForm > Submissions
 * and check the webhook request body. You should see:
 *
 * "billing_amount": 29.99     ← Good (no quotes)
 * NOT
 * "billing_amount": "29.99"   ← Bad (has quotes)
 */
