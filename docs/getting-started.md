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

## Quick Start

### Basic Example

```php
$result = pmpro_magic_levels_process([
    'name' => 'Basic - Gold',  // Format: "GroupName - LevelName"
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

### Using the REST API

```bash
curl -X POST https://yoursite.com/wp-json/pmpro-magic-levels/v1/process \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Basic - Gold",
    "billing_amount": 29.99,
    "cycle_period": "Month",
    "cycle_number": 1
  }'
```

## Understanding Groups

**Important:** All level names must include a group using the format `"GroupName - LevelName"`.

- `"Basic - Gold"` → Group: "Basic", Level: "Basic - Gold"
- `"Pro - Premium"` → Group: "Pro", Level: "Pro - Premium"

This is required for PMPro 3.x's group-based level management.

## Next Steps

- [Configuration Options](configuration.md) - Customize validation rules
- [WSForm Integration](integrations/wsform.md) - Step-by-step WSForm setup
- [API Reference](api-reference.md) - Complete API documentation
- [Troubleshooting](troubleshooting.md) - Common issues and solutions
