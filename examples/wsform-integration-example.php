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
