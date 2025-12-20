# Getting Started with PMPro Magic Levels

## What is PMPro Magic Levels?

PMPro Magic Levels allows you to dynamically create membership levels from form submissions. Perfect for variable pricing, custom membership builders, and dynamic pricing calculators.

## Requirements

- WordPress 5.0+
- PHP 7.4+
- Paid Memberships Pro 3.0+ (active)

## Installation

1. Upload the `pmpro-magic-levels` folder to `/wp-content/plugins/`
2. Activate the plugin through the 'Plugins' menu in WordPress
3. That's it! No configuration needed to get started.

### Method 1: Direct PHP Integration (Recommended)

For internal WordPress integrations (forms on the same site), use the PHP function directly. It is faster, more secure, and allows for instant redirects.

```php
$result = pmpro_magic_levels_process([
    'name'           => 'Basic - Gold',
    'billing_amount' => 29.99,
    'cycle_period'   => 'Month',
    'cycle_number'   => 1
]);

if ($result['success']) {
    // Redirect the user to the generated checkout URL
    wp_redirect($result['redirect_url']);
    exit;
}
```

**Note:** The PHP function doesn't require authentication because it runs within your WordPress environment.

### Method 2: External Webhook Integration

Use the webhook endpoint if you are connecting external services (like automation tools) or if you are using an AJAX-based frontend.

1. Go to **PMPro > Magic Levels** in WordPress admin
2. Check "Enable webhook endpoint"
3. Copy your Bearer token
4. Send a POST request:

```bash
curl -X POST https://yoursite.com/wp-json/pmpro-magic-levels/v1/process \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer YOUR_TOKEN_FROM_ADMIN" \
  -d '{
    "name": "Basic - Gold",
    "billing_amount": 29.99,
    "cycle_period": "Month",
    "cycle_number": 1
  }'
```

**Response:**
```json
{
  "success": true,
  "level_id": 5,
  "redirect_url": "https://yoursite.com/checkout/?pmpro_level=5",
  "level_created": true,
  "message": "New level created"
}
```

## Understanding Groups

**Important:** All level names must include a group using the format `"GroupName - LevelName"`.

- `"Basic - Gold"` → Group: "Basic", Level: "Basic - Gold"
- `"Pro - Premium"` → Group: "Pro", Level: "Pro - Premium"

This is required for PMPro 3.x's group-based level management.

## Next Steps

- [Configuration Options](../reference/filters.md) - Customize validation rules
- [WSForm Integration](../integrations/wsform.md) - Step-by-step WSForm setup

