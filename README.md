# PMPro Magic Levels

Dynamically create or find membership levels from form submissions and automatically redirect users to checkout.

## Overview

PMPro Magic Levels allows you to **dynamically create or find Paid Memberships Pro (PMPro) membership levels on-the-fly** based on user input.

Instead of manually creating every possible membership variation in the admin dashboard, this plugin lets you:

1. **Accept pricing parameters** from a frontend form (Price, Billing Period, Name, etc.)
2. **Automatically check** if a matching level already exists (to prevent duplicates)
3. **Create a new level** if one doesn't exist
4. **Assign** the level to a group (required for PMPro 3.x)
5. **Redirect the user** instantly to the checkout page for that specific level

**Important:** Level names must use the format `"GroupName - LevelName"` (e.g., "Basic - Gold"). This is required for PMPro's group-based level management.

## Key Use Cases

*   **"Name Your Price" Forms**: Let users donate or pay a custom amount.
*   **Custom Plan Builders**: Allow users to toggle features that adjust the final price programmatically.
*   **Dynamic Pricing**: Generate hundreds of price/duration combinations without cluttering your admin panel manually.

## Key Features

*   **Smart Deduplication**: Automatically finds and reuses existing levels if the parameters match exactly.
*   **High Performance**: Implements a 3-tier caching system (Memory, Transient, DB) to ensure fast lookups (~0-50ms).
*   **Safety First**: Extensive validation rules (min/max price, name patterns, rate limiting) configurable via WordPress filters.
*   **Developer Friendly**: Works via REST API webhook or direct PHP function call.

## Compatible Form Plugins

PMPro Magic Levels works via webhook endpoint. Form plugins must be able to send webhook requests AND handle the response for redirect.

**Compatible:**
- ✅ WSForm - Supports webhook response handling and redirect
- ✅ Gravity Forms - With webhook addons that support response handling
- ✅ Formidable Forms - With webhook addons that support response handling

**Not Compatible:**
- ❌ Contact Form 7 - CF7 webhook plugins (like CF7-to-Zapier) make server-to-server calls and cannot pass webhook responses back to the browser for automatic redirect
- User-defined membership configurations
- Any scenario requiring flexible, on-demand level creation

## Requirements

- WordPress 5.0+
- PHP 7.4+
- Paid Memberships Pro (active)

## Installation

1. Upload the `pmpro-magic-levels` folder to `/wp-content/plugins/`
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Configure validation rules via filters (optional - see Configuration section)

## Quick Start

### Minimum Working Example

```php
// In your form handler or custom code
$result = pmpro_magic_levels_process([
    'name' => 'Premium - Gold',  // Format: "GroupName - LevelName"
    'billing_amount' => 29.99,
    'cycle_period' => 'Month',
    'cycle_number' => 1
]);

if ($result['success']) {
    $checkout_url = pmpro_url('checkout', '?level=' . $result['level_id']);
    wp_redirect($checkout_url);
    exit;
}
```

### Using the REST API Webhook

**Note:** Get your Bearer token from PMPro > Magic Levels admin page.

```javascript
fetch('/wp-json/pmpro-magic-levels/v1/process', {
    method: 'POST',
    headers: { 
        'Content-Type': 'application/json',
        'Authorization': 'Bearer YOUR_TOKEN_FROM_ADMIN'
    },
    body: JSON.stringify({
        name: 'Premium - Gold',  // Format: "GroupName - LevelName"
        billing_amount: 29.99,
        cycle_period: 'Month',
        cycle_number: 1
    })
})
.then(response => response.json())
.then(data => {
    if (data.success) {
        window.location.href = data.redirect_url;
    }
});
```

## How It Works

### Smart Level Matching

The plugin matches levels based on ALL pricing parameters:
- Name
- Billing amount
- Cycle period and number
- Initial payment
- Trial settings
- Expiration settings
- Billing limit

If all parameters match an existing level, that level is returned. Otherwise, a new level is created.

### Three-Tier Caching System

For optimal performance:
1. **Memory cache** (~0.001ms) - Current request only
2. **Transient cache** (~1ms) - Across requests
3. **Database** (~10-50ms) - With optimized composite indexes

### Architecture

The plugin consists of four main classes:

- **Validator** - Validates input against configurable rules
- **Level Matcher** - Finds existing levels or creates new ones
- **Cache** - Manages the three-tier caching system
- **Webhook Handler** - Provides REST API endpoint

## Configuration

All settings are configured via WordPress filters. Add these to your theme's `functions.php` or a custom plugin.

### Complete Configuration Example

```php
<?php
// Strict pricing rules
add_filter('pmpro_magic_levels_price_increment', fn() => 5.00);  // Prices must be $5, $10, $15, etc.
add_filter('pmpro_magic_levels_min_price', fn() => 10.00);       // Minimum $10
add_filter('pmpro_magic_levels_max_price', fn() => 200.00);      // Maximum $200
add_filter('pmpro_magic_levels_allow_free_levels', '__return_false');

// Only monthly and yearly subscriptions
add_filter('pmpro_magic_levels_allowed_periods', fn() => ['Month', 'Year']);
add_filter('pmpro_magic_levels_allowed_cycle_numbers', fn() => [1]);

// Name validation
add_filter('pmpro_magic_levels_min_name_length', fn() => 5);
add_filter('pmpro_magic_levels_max_name_length', fn() => 50);
add_filter('pmpro_magic_levels_name_blacklist', fn() => ['test', 'demo', 'free']);
add_filter('pmpro_magic_levels_name_pattern', fn() => '/^[a-zA-Z0-9\s\-]+$/');

// Rate limiting: 5 requests per hour per IP
add_filter('pmpro_magic_levels_rate_limit', function() {
    return [
        'max_requests' => 5,
        'time_window' => 3600,
        'by' => 'ip'  // or 'user'
    ];
});

// Daily limits
add_filter('pmpro_magic_levels_max_levels_per_day', fn() => 20);

// Caching
add_filter('pmpro_magic_levels_enable_cache', '__return_true');
add_filter('pmpro_magic_levels_cache_duration', fn() => HOUR_IN_SECONDS);
add_filter('pmpro_magic_levels_cache_method', fn() => 'transient');
```

**📖 See [filters.md](docs/filters.md) for complete filter reference with all 20+ available options.**

## Usage Examples

**Note:** All REST API examples require Bearer token authentication. Get your token from PMPro > Magic Levels admin page and replace `YOUR_TOKEN_FROM_ADMIN` in the examples below.

### Example 1: Custom HTML Form

```html
<form id="membership-form">
    <h2>Create Your Membership</h2>
    
    <label>Membership Name:</label>
    <input type="text" id="level-name" required>
    
    <label>Monthly Price ($):</label>
    <input type="number" id="price" step="5" min="10" max="200" required>
    
    <label>Billing Period:</label>
    <select id="period">
        <option value="Month">Monthly</option>
        <option value="Year">Yearly</option>
    </select>
    
    <button type="submit">Create & Checkout</button>
    <div id="error-message" style="color: red; display: none;"></div>
</form>

<script>
jQuery(document).ready(function($) {
    $('#membership-form').on('submit', function(e) {
        e.preventDefault();
        $('#error-message').hide();
        
        var formData = {
            name: $('#level-name').val(),
            billing_amount: parseFloat($('#price').val()),
            cycle_period: $('#period').val(),
            cycle_number: 1
        };
        
        $.ajax({
            url: '/wp-json/pmpro-magic-levels/v1/process',
            method: 'POST',
            headers: {
                'Authorization': 'Bearer YOUR_TOKEN_FROM_ADMIN'
            },
            data: JSON.stringify(formData),
            contentType: 'application/json',
            success: function(response) {
                if (response.success) {
                    window.location.href = response.redirect_url;
                } else {
                    $('#error-message').text(response.error).show();
                }
            }
        });
    });
});
</script>
```

### Example 2: WPForms Integration

```php
<?php
add_action('wpforms_process_complete', function($fields, $entry, $form_data) {
    
    // Only process form ID 123 (change to your form ID)
    if ($form_data['id'] != 123) {
        return;
    }
    
    $level_data = [
        'name' => $fields[1]['value'],              // Field 1 = Name
        'billing_amount' => $fields[2]['value'],    // Field 2 = Price
        'cycle_period' => $fields[3]['value'],      // Field 3 = Period
        'cycle_number' => 1,
    ];
    
    $result = pmpro_magic_levels_process($level_data);
    
    if ($result['success']) {
        $checkout_url = pmpro_url('checkout', '?level=' . $result['level_id']);
        wp_redirect($checkout_url);
        exit;
    } else {
        wp_die('Error: ' . $result['error']);
    }
    
}, 10, 3);
```

### Example 3: Gravity Forms Integration

```php
<?php
add_action('gform_after_submission_5', function($entry, $form) {
    
    $level_data = [
        'name' => rgar($entry, '1'),           // Field 1
        'billing_amount' => rgar($entry, '2'), // Field 2
        'cycle_period' => rgar($entry, '3'),   // Field 3
        'cycle_number' => 1,
    ];
    
    $result = pmpro_magic_levels_process($level_data);
    
    if ($result['success']) {
        wp_redirect($result['redirect_url']);
        exit;
    }
    
}, 10, 2);
```

### Example 4: Variable Pricing Calculator

```html
<div id="pricing-calculator">
    <h2>Build Your Plan</h2>
    
    <label>Number of Users:</label>
    <input type="number" id="users" value="1" min="1" max="100">
    
    <label>Features:</label>
    <label><input type="checkbox" value="10" class="feature"> Email Support (+$10/mo)</label>
    <label><input type="checkbox" value="20" class="feature"> Priority Support (+$20/mo)</label>
    <label><input type="checkbox" value="50" class="feature"> Custom Branding (+$50/mo)</label>
    
    <h3>Total: $<span id="total">0</span>/month</h3>
    <button id="create-plan">Create My Plan</button>
</div>

<script>
jQuery(document).ready(function($) {
    function calculatePrice() {
        var basePrice = 10;
        var users = parseInt($('#users').val());
        var featuresPrice = 0;
        
        $('.feature:checked').each(function() {
            featuresPrice += parseInt($(this).val());
        });
        
        var total = (basePrice * users) + featuresPrice;
        $('#total').text(total);
        return total;
    }
    
    $('#users, .feature').on('change', calculatePrice);
    calculatePrice();
    
    $('#create-plan').on('click', function() {
        var users = $('#users').val();
        var price = calculatePrice();
        
        $.ajax({
            url: '/wp-json/pmpro-magic-levels/v1/process',
            method: 'POST',
            headers: {
                'Authorization': 'Bearer YOUR_TOKEN_FROM_ADMIN'
            },
            data: JSON.stringify({
                name: 'Custom Plan - ' + users + ' Users',
                billing_amount: price,
                cycle_period: 'Month',
                cycle_number: 1,
                description: 'Custom plan for ' + users + ' users'
            }),
            contentType: 'application/json',
            success: function(response) {
                if (response.success) {
                    window.location.href = response.redirect_url;
                }
            }
        });
    });
});
</script>
```

### Example 5: With Bearer Token Authentication

Authentication is managed through the admin interface (PMPro > Magic Levels). Get your Bearer token from the admin page.

```javascript
// Use Bearer token from admin settings
var bearerToken = 'YOUR_TOKEN_FROM_ADMIN_PAGE';

var formData = {
    name: 'Premium Plan',
    billing_amount: 29.99,
    cycle_period: 'Month',
    cycle_number: 1
};

fetch('/wp-json/pmpro-magic-levels/v1/process', {
    method: 'POST',
    headers: { 
        'Content-Type': 'application/json',
        'Authorization': 'Bearer ' + bearerToken
    },
    body: JSON.stringify(formData)
})
.then(response => response.json())
.then(data => {
    if (data.success) {
        window.location.href = '/checkout/?level=' + data.level_id;
    }
});
```

**📖 See `examples/` folder for more integration examples with different form plugins.**

## Level Parameters

### Required
- `name` (string) - Level name in format "GroupName - LevelName" (e.g., "Basic - Gold")

### Optional
- `description` (string) - Level description
- `confirmation` (string) - Confirmation message
- `initial_payment` (float) - One-time payment
- `billing_amount` (float) - Recurring amount
- `cycle_number` (int) - Billing frequency (1, 3, 6, 12)
- `cycle_period` (string) - 'Day', 'Week', 'Month', 'Year'
- `billing_limit` (int) - Number of payments (0 = unlimited)
- `trial_amount` (float) - Trial cost
- `trial_limit` (int) - Trial cycles
- `expiration_number` (int) - Expiration duration
- `expiration_period` (string) - 'Day', 'Week', 'Month', 'Year'
- `allow_signups` (int) - 1 or 0

## API Response Format

### Success Response
```json
{
  "success": true,
  "level_id": 5,
  "level_created": false,
  "cached": true,
  "message": "Existing level found"
}
```

You can then build your own redirect URL:
- Standard: `/checkout/?level=5`
- Custom: `/custom-checkout/?level=5`
- With params: `/checkout/?level=5&discount=SAVE10`

### Error Response
```json
{
  "success": false,
  "error": "Price must be a multiple of $5.00",
  "code": "invalid_price_increment"
}
```

## Error Codes

- `missing_required_field` - Missing name
- `missing_group_separator` - Name doesn't include " - " separator for group
- `invalid_price_increment` - Price not multiple of increment
- `price_below_minimum` - Price below minimum
- `price_above_maximum` - Price above maximum
- `free_levels_disabled` - Free levels not allowed
- `invalid_cycle_period` - Invalid period
- `invalid_cycle_number` - Invalid cycle number
- `billing_limit_exceeded` - Billing limit too high
- `name_too_short` - Name too short
- `name_too_long` - Name too long
- `invalid_name_pattern` - Name pattern mismatch
- `blacklisted_name` - Blacklisted word in name
- `rate_limit_exceeded` - Too many requests
- `daily_limit_exceeded` - Daily limit reached
- `invalid_token` - Invalid Bearer token
- `missing_authorization` - Missing Authorization header
- `webhook_disabled` - Webhook endpoint is disabled
- `level_creation_failed` - Database error

## Testing

### Test 1: Create a Level

```php
<?php
$result = pmpro_magic_levels_process([
    'name' => 'Test Level',
    'billing_amount' => 25.00,
    'cycle_period' => 'Month',
    'cycle_number' => 1
]);

echo '<pre>';
print_r($result);
echo '</pre>';
```

Expected output:
```
Array
(
    [success] => 1
    [level_id] => 5
    [level_created] => 1
    [cached] => 
    [message] => New level created
)
```

### Test 2: Find Existing Level

Run the same code again. Expected output:
```
Array
(
    [success] => 1
    [level_id] => 5
    [level_created] => 
    [cached] => 1
    [message] => Existing level found
)
```

### Test 3: Validation Error

```php
<?php
// Configure minimum price
add_filter('pmpro_magic_levels_min_price', fn() => 10.00);

$result = pmpro_magic_levels_process([
    'name' => 'Test',
    'billing_amount' => 7.00,  // Below minimum
    'cycle_period' => 'Month',
    'cycle_number' => 1
]);

print_r($result);
```

Expected output:
```
Array
(
    [success] => 
    [error] => Price must be at least $10.00
    [code] => price_below_minimum
)
```

## Troubleshooting

### Issue: "PMPro Magic Levels requires Paid Memberships Pro"
**Solution**: Install and activate Paid Memberships Pro plugin first.

### Issue: Validation errors
**Solution**: Check your filter configuration. Temporarily disable restrictions for testing:
```php
add_filter('pmpro_magic_levels_min_price', fn() => 0.00);
add_filter('pmpro_magic_levels_max_price', fn() => 99999.99);
add_filter('pmpro_magic_levels_price_increment', fn() => 0.01);
```

### Issue: Rate limit exceeded
**Solution**: Increase rate limits or clear transients:
```php
add_filter('pmpro_magic_levels_rate_limit', function() {
    return [
        'max_requests' => 1000,
        'time_window' => 3600,
        'by' => 'ip'
    ];
});
```

### Issue: Levels not being found (always creating new)
**Solution**: Check that ALL pricing parameters match exactly. Even a 0.01 difference will create a new level.

### Issue: Cache not working
**Solution**: Verify caching is enabled and check your cache method:
```php
add_filter('pmpro_magic_levels_enable_cache', '__return_true');
add_filter('pmpro_magic_levels_cache_method', fn() => 'transient');
```

## Documentation

📚 **[Complete Documentation](docs/)** - Full documentation in the `/docs` folder

Quick links:
- **[Getting Started](docs/getting-started.md)** - Installation and quick start
- **[Security Best Practices](docs/security.md)** - Rate limiting and security recommendations
- **[WSForm Integration](docs/integrations/wsform.md)** - Complete WSForm guide
- **[Configuration](docs/configuration.md)** - All filter options
- **[API Reference](docs/api-reference.md)** - REST API documentation
- **[cURL Examples](docs/curl-examples.md)** - Test examples
- **[Troubleshooting](docs/troubleshooting.md)** - Common issues

Additional resources:
- **[filters.md](docs/filters.md)** - Complete filter reference
- **[examples/](examples/)** - Code examples for various form plugins

## Support

For issues and feature requests, please use the GitHub repository.

## License

GPL v2 or later
