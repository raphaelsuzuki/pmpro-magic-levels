<?php
/**
 * Custom Endpoints & Wrapper Functions Examples
 * 
 * How to create custom webhooks or PHP functions with hardcoded values
 */

// ============================================
// Example 1: Custom Webhook with Prefix
// ============================================

/**
 * Create a custom webhook that prefixes all level names
 * URL: /wp-json/my-site/v1/create-premium-level
 */
add_action('rest_api_init', function() {
    register_rest_route('my-site/v1', '/create-premium-level', [
        'methods' => 'POST',
        'callback' => function($request) {
            $params = $request->get_json_params();
            
            // Hardcode the prefix
            $params['name'] = 'Premium - ' . $params['name'];
            
            // Hardcode other values
            $params['cycle_period'] = 'Month';
            $params['cycle_number'] = 1;
            
            // Process with Magic Levels
            $result = pmpro_magic_levels_process($params);
            
            return new WP_REST_Response($result, $result['success'] ? 200 : 400);
        },
        'permission_callback' => '__return_true'
    ]);
});

// Usage:
// POST /wp-json/my-site/v1/create-premium-level
// { "name": "Gold", "billing_amount": 49.99 }
// Creates level: "Premium - Gold"


// ============================================
// Example 2: Multiple Custom Webhooks
// ============================================

/**
 * Create separate webhooks for different membership types
 */
add_action('rest_api_init', function() {
    
    // Basic membership webhook
    register_rest_route('my-site/v1', '/create-basic', [
        'methods' => 'POST',
        'callback' => function($request) {
            $params = $request->get_json_params();
            
            return new WP_REST_Response(pmpro_magic_levels_process([
                'name' => 'Basic - ' . $params['name'],
                'billing_amount' => 9.99,  // Fixed price
                'cycle_period' => 'Month',
                'cycle_number' => 1,
                'description' => 'Basic membership tier'
            ]));
        },
        'permission_callback' => '__return_true'
    ]);
    
    // Pro membership webhook
    register_rest_route('my-site/v1', '/create-pro', [
        'methods' => 'POST',
        'callback' => function($request) {
            $params = $request->get_json_params();
            
            return new WP_REST_Response(pmpro_magic_levels_process([
                'name' => 'Pro - ' . $params['name'],
                'billing_amount' => 29.99,  // Fixed price
                'cycle_period' => 'Month',
                'cycle_number' => 1,
                'description' => 'Pro membership tier'
            ]));
        },
        'permission_callback' => '__return_true'
    ]);
    
    // Enterprise membership webhook
    register_rest_route('my-site/v1', '/create-enterprise', [
        'methods' => 'POST',
        'callback' => function($request) {
            $params = $request->get_json_params();
            
            return new WP_REST_Response(pmpro_magic_levels_process([
                'name' => 'Enterprise - ' . $params['name'],
                'billing_amount' => 99.99,  // Fixed price
                'cycle_period' => 'Year',   // Yearly billing
                'cycle_number' => 1,
                'description' => 'Enterprise membership tier'
            ]));
        },
        'permission_callback' => '__return_true'
    ]);
});


// ============================================
// Example 3: Custom PHP Wrapper Functions
// ============================================

/**
 * Create a premium level with hardcoded values
 */
function create_premium_level($name, $custom_price = null) {
    return pmpro_magic_levels_process([
        'name' => 'Premium - ' . $name,
        'billing_amount' => $custom_price ?? 49.99,
        'cycle_period' => 'Month',
        'cycle_number' => 1,
        'description' => 'Premium membership with full access',
        'confirmation' => 'Welcome to Premium!'
    ]);
}

/**
 * Create a yearly level with discount
 */
function create_yearly_level($name, $monthly_price) {
    $yearly_price = $monthly_price * 10; // 2 months free
    
    return pmpro_magic_levels_process([
        'name' => $name . ' (Yearly)',
        'billing_amount' => $yearly_price,
        'cycle_period' => 'Year',
        'cycle_number' => 1,
        'description' => 'Save 2 months with yearly billing!'
    ]);
}

/**
 * Create a trial level
 */
function create_trial_level($name, $trial_days = 7) {
    return pmpro_magic_levels_process([
        'name' => $name . ' - Trial',
        'billing_amount' => 29.99,
        'cycle_period' => 'Month',
        'cycle_number' => 1,
        'trial_amount' => 0.00,
        'trial_limit' => 1,
        'expiration_number' => $trial_days,
        'expiration_period' => 'Day'
    ]);
}

// Usage in your code:
// $result = create_premium_level('Gold Plan');
// $result = create_yearly_level('Pro Plan', 29.99);
// $result = create_trial_level('Premium', 14);


// ============================================
// Example 4: Category-Based Levels
// ============================================

/**
 * Create levels for different product categories
 */
function create_category_level($category, $name, $price) {
    $prefixes = [
        'courses' => 'Course Access',
        'videos' => 'Video Library',
        'downloads' => 'Download Pack'
    ];
    
    $prefix = $prefixes[$category] ?? 'Membership';
    
    return pmpro_magic_levels_process([
        'name' => $prefix . ' - ' . $name,
        'billing_amount' => $price,
        'cycle_period' => 'Month',
        'cycle_number' => 1,
        'description' => "Access to {$category} content"
    ]);
}

// Usage:
// create_category_level('courses', 'Beginner', 19.99);
// create_category_level('videos', 'Premium', 29.99);


// ============================================
// Example 5: Form-Specific Handlers
// ============================================

/**
 * WPForms handler with hardcoded prefix
 */
add_action('wpforms_process_complete', function($fields, $entry, $form_data) {
    if ($form_data['id'] != 123) return;
    
    $result = pmpro_magic_levels_process([
        'name' => 'Custom - ' . $fields[1]['value'],  // Prefix added
        'billing_amount' => $fields[2]['value'],
        'cycle_period' => 'Month',  // Hardcoded
        'cycle_number' => 1,        // Hardcoded
        'description' => 'Created via custom form'  // Hardcoded
    ]);
    
    if ($result['success']) {
        wp_redirect($result['redirect_url']);
        exit;
    }
}, 10, 3);


// ============================================
// Example 6: Dynamic Pricing with Hardcoded Rules
// ============================================

/**
 * Create level with automatic pricing tiers
 */
function create_tiered_level($name, $user_count) {
    // Hardcoded pricing tiers
    if ($user_count <= 5) {
        $price = 29.99;
        $tier = 'Small Team';
    } elseif ($user_count <= 20) {
        $price = 99.99;
        $tier = 'Medium Team';
    } else {
        $price = 299.99;
        $tier = 'Large Team';
    }
    
    return pmpro_magic_levels_process([
        'name' => "{$tier} - {$name}",
        'billing_amount' => $price,
        'cycle_period' => 'Month',
        'cycle_number' => 1,
        'description' => "For teams up to {$user_count} users"
    ]);
}


// ============================================
// Example 7: Webhook with Validation & Defaults
// ============================================

add_action('rest_api_init', function() {
    register_rest_route('my-site/v1', '/smart-level', [
        'methods' => 'POST',
        'callback' => function($request) {
            $params = $request->get_json_params();
            
            // Apply defaults
            $level_data = [
                'name' => 'Member - ' . ($params['name'] ?? 'Default'),
                'billing_amount' => $params['price'] ?? 19.99,
                'cycle_period' => $params['period'] ?? 'Month',
                'cycle_number' => 1,
                'description' => $params['description'] ?? 'Standard membership',
                'confirmation' => 'Thank you for joining!'
            ];
            
            // Add prefix based on price
            if ($level_data['billing_amount'] >= 50) {
                $level_data['name'] = 'Premium - ' . $params['name'];
            }
            
            return new WP_REST_Response(pmpro_magic_levels_process($level_data));
        },
        'permission_callback' => '__return_true'
    ]);
});


// ============================================
// Example 8: Shortcode with Hardcoded Values
// ============================================

/**
 * Create a shortcode that generates a level
 * Usage: [create_membership name="Gold" price="49.99"]
 */
add_shortcode('create_membership', function($atts) {
    $atts = shortcode_atts([
        'name' => 'Default',
        'price' => '29.99',
        'prefix' => 'Member'
    ], $atts);
    
    $result = pmpro_magic_levels_process([
        'name' => $atts['prefix'] . ' - ' . $atts['name'],
        'billing_amount' => floatval($atts['price']),
        'cycle_period' => 'Month',
        'cycle_number' => 1
    ]);
    
    if ($result['success']) {
        return '<a href="' . esc_url($result['redirect_url']) . '">Subscribe Now</a>';
    }
    
    return 'Error creating level';
});


// ============================================
// Example 9: AJAX Handler with Hardcoded Values
// ============================================

add_action('wp_ajax_create_custom_level', function() {
    check_ajax_referer('create_level_nonce', 'nonce');
    
    $name = sanitize_text_field($_POST['name']);
    
    $result = pmpro_magic_levels_process([
        'name' => 'VIP - ' . $name,  // Hardcoded prefix
        'billing_amount' => 99.99,   // Hardcoded price
        'cycle_period' => 'Month',
        'cycle_number' => 1,
        'initial_payment' => 0,      // Free first month
        'description' => 'VIP membership with exclusive benefits'
    ]);
    
    wp_send_json($result);
});

add_action('wp_ajax_nopriv_create_custom_level', function() {
    // Same as above for non-logged-in users
});


// ============================================
// Example 10: Conditional Logic
// ============================================

function create_smart_level($user_input) {
    // Determine prefix based on conditions
    $prefix = is_user_logged_in() ? 'Upgrade' : 'New Member';
    
    // Determine price based on user role
    $user = wp_get_current_user();
    $discount = in_array('subscriber', $user->roles) ? 0.8 : 1.0;
    
    return pmpro_magic_levels_process([
        'name' => $prefix . ' - ' . $user_input['name'],
        'billing_amount' => $user_input['price'] * $discount,
        'cycle_period' => 'Month',
        'cycle_number' => 1,
        'description' => 'Custom membership level'
    ]);
}
