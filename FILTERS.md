# PMPro Magic Levels - Complete Filter Reference

All available filters with descriptions, defaults, and examples.

---

## Price Validation Filters

### `pmpro_magic_levels_price_increment`
**Description:** Price must be a multiple of this value  
**Type:** Float  
**Default:** `1.00`  
**Example:**
```php
// Prices must be multiples of $5 ($5, $10, $15, etc.)
add_filter('pmpro_magic_levels_price_increment', fn() => 5.00);
```

### `pmpro_magic_levels_min_price`
**Description:** Minimum allowed price  
**Type:** Float  
**Default:** `0.00`  
**Example:**
```php
// Minimum price $10
add_filter('pmpro_magic_levels_min_price', fn() => 10.00);
```

### `pmpro_magic_levels_max_price`
**Description:** Maximum allowed price  
**Type:** Float  
**Default:** `9999.99`  
**Example:**
```php
// Maximum price $500
add_filter('pmpro_magic_levels_max_price', fn() => 500.00);
```

### `pmpro_magic_levels_allow_free_levels`
**Description:** Allow levels with $0 price  
**Type:** Boolean  
**Default:** `true`  
**Example:**
```php
// Disable free levels
add_filter('pmpro_magic_levels_allow_free_levels', '__return_false');
```

### `pmpro_magic_levels_require_initial_payment`
**Description:** Require initial payment to be greater than 0  
**Type:** Boolean  
**Default:** `false`  
**Example:**
```php
// Require initial payment
add_filter('pmpro_magic_levels_require_initial_payment', '__return_true');
```

---

## Cycle/Billing Filters

### `pmpro_magic_levels_allowed_periods`
**Description:** Allowed billing periods  
**Type:** Array  
**Default:** `['Day', 'Week', 'Month', 'Year']`  
**Example:**
```php
// Only monthly and yearly
add_filter('pmpro_magic_levels_allowed_periods', fn() => ['Month', 'Year']);
```

### `pmpro_magic_levels_allowed_cycle_numbers`
**Description:** Allowed cycle numbers (billing frequency)  
**Type:** Array  
**Default:** `[1, 2, 3, 6, 12]`  
**Example:**
```php
// Only allow billing every 1 or 3 months
add_filter('pmpro_magic_levels_allowed_cycle_numbers', fn() => [1, 3]);
```

### `pmpro_magic_levels_max_billing_limit`
**Description:** Maximum number of billing cycles  
**Type:** Integer  
**Default:** `999`  
**Example:**
```php
// Max 24 payments
add_filter('pmpro_magic_levels_max_billing_limit', fn() => 24);
```

---

## Name Validation Filters

### `pmpro_magic_levels_min_name_length`
**Description:** Minimum level name length  
**Type:** Integer  
**Default:** `1`  
**Example:**
```php
// Name must be at least 5 characters
add_filter('pmpro_magic_levels_min_name_length', fn() => 5);
```

### `pmpro_magic_levels_max_name_length`
**Description:** Maximum level name length  
**Type:** Integer  
**Default:** `255`  
**Example:**
```php
// Name cannot exceed 50 characters
add_filter('pmpro_magic_levels_max_name_length', fn() => 50);
```

### `pmpro_magic_levels_name_pattern`
**Description:** Regex pattern for name validation  
**Type:** String (regex) or null  
**Default:** `null` (no pattern restriction)  
**Example:**
```php
// Only alphanumeric, spaces, and hyphens
add_filter('pmpro_magic_levels_name_pattern', fn() => '/^[a-zA-Z0-9\s\-]+$/');

// Only letters and spaces
add_filter('pmpro_magic_levels_name_pattern', fn() => '/^[a-zA-Z\s]+$/');
```

### `pmpro_magic_levels_name_blacklist`
**Description:** Words not allowed in level names  
**Type:** Array  
**Default:** `[]` (empty)  
**Example:**
```php
// Blacklist certain words
add_filter('pmpro_magic_levels_name_blacklist', fn() => ['test', 'demo', 'free', 'admin']);
```

---

## Rate Limiting Filters

### `pmpro_magic_levels_rate_limit`
**Description:** Rate limiting configuration  
**Type:** Array  
**Default:**
```php
[
    'max_requests' => 100,
    'time_window' => 3600,  // 1 hour in seconds
    'by' => 'ip'            // 'ip' or 'user'
]
```
**Example:**
```php
// 10 requests per hour per IP
add_filter('pmpro_magic_levels_rate_limit', function() {
    return [
        'max_requests' => 10,
        'time_window' => 3600,
        'by' => 'ip'
    ];
});

// 50 requests per 30 minutes per user
add_filter('pmpro_magic_levels_rate_limit', function() {
    return [
        'max_requests' => 50,
        'time_window' => 1800,
        'by' => 'user'
    ];
});
```

### `pmpro_magic_levels_max_levels_per_day`
**Description:** Maximum levels that can be created per day  
**Type:** Integer  
**Default:** `1000`  
**Example:**
```php
// Max 50 levels per day
add_filter('pmpro_magic_levels_max_levels_per_day', fn() => 50);
```

---

## Webhook/Security Filters

### `pmpro_magic_levels_enable_webhook`
**Description:** Enable/disable the REST API webhook  
**Type:** Boolean  
**Default:** `true`  
**Example:**
```php
// Disable webhook (only use PHP function)
add_filter('pmpro_magic_levels_enable_webhook', '__return_false');
```

### `pmpro_magic_levels_webhook_require_auth`
**Description:** Require authentication for webhook  
**Type:** Boolean  
**Default:** `false`  
**Example:**
```php
// Require auth key
add_filter('pmpro_magic_levels_webhook_require_auth', '__return_true');
```

### `pmpro_magic_levels_webhook_auth_key`
**Description:** Authentication key for webhook  
**Type:** String  
**Default:** `''` (empty)  
**Example:**
```php
// Set auth key
add_filter('pmpro_magic_levels_webhook_auth_key', fn() => 'my-secret-key-12345');
```

---

## Caching Filters

### `pmpro_magic_levels_enable_cache`
**Description:** Enable/disable caching system  
**Type:** Boolean  
**Default:** `true`  
**Example:**
```php
// Disable caching (not recommended)
add_filter('pmpro_magic_levels_enable_cache', '__return_false');
```

### `pmpro_magic_levels_cache_duration`
**Description:** Cache duration in seconds  
**Type:** Integer  
**Default:** `3600` (1 hour)  
**Example:**
```php
// Cache for 30 minutes
add_filter('pmpro_magic_levels_cache_duration', fn() => 1800);

// Cache for 24 hours
add_filter('pmpro_magic_levels_cache_duration', fn() => DAY_IN_SECONDS);
```

### `pmpro_magic_levels_cache_method`
**Description:** Caching method to use  
**Type:** String  
**Default:** `'transient'`  
**Options:** `'transient'`, `'object'`, `'none'`  
**Example:**
```php
// Use object cache (Redis/Memcached)
add_filter('pmpro_magic_levels_cache_method', fn() => 'object');

// Use transients (default)
add_filter('pmpro_magic_levels_cache_method', fn() => 'transient');

// Disable cache
add_filter('pmpro_magic_levels_cache_method', fn() => 'none');
```

---

## Complete Configuration Examples

### Example 1: Strict Validation
```php
<?php
// Strict pricing
add_filter('pmpro_magic_levels_price_increment', fn() => 5.00);
add_filter('pmpro_magic_levels_min_price', fn() => 10.00);
add_filter('pmpro_magic_levels_max_price', fn() => 200.00);
add_filter('pmpro_magic_levels_allow_free_levels', '__return_false');

// Only monthly/yearly
add_filter('pmpro_magic_levels_allowed_periods', fn() => ['Month', 'Year']);
add_filter('pmpro_magic_levels_allowed_cycle_numbers', fn() => [1]);

// Strict rate limiting
add_filter('pmpro_magic_levels_rate_limit', function() {
    return ['max_requests' => 5, 'time_window' => 3600, 'by' => 'ip'];
});

// Name validation
add_filter('pmpro_magic_levels_min_name_length', fn() => 5);
add_filter('pmpro_magic_levels_max_name_length', fn() => 50);
add_filter('pmpro_magic_levels_name_blacklist', fn() => ['test', 'demo']);

// Webhook security
add_filter('pmpro_magic_levels_webhook_require_auth', '__return_true');
add_filter('pmpro_magic_levels_webhook_auth_key', fn() => 'secret-key-123');
```

### Example 2: Relaxed Validation
```php
<?php
// Allow any price
add_filter('pmpro_magic_levels_min_price', fn() => 0.00);
add_filter('pmpro_magic_levels_max_price', fn() => 99999.99);
add_filter('pmpro_magic_levels_price_increment', fn() => 0.01);
add_filter('pmpro_magic_levels_allow_free_levels', '__return_true');

// Allow all periods
add_filter('pmpro_magic_levels_allowed_periods', fn() => ['Day', 'Week', 'Month', 'Year']);
add_filter('pmpro_magic_levels_allowed_cycle_numbers', fn() => range(1, 12));

// Generous rate limiting
add_filter('pmpro_magic_levels_rate_limit', function() {
    return ['max_requests' => 1000, 'time_window' => 3600, 'by' => 'ip'];
});
add_filter('pmpro_magic_levels_max_levels_per_day', fn() => 10000);
```

### Example 3: High-Performance Caching
```php
<?php
// Use object cache (requires Redis/Memcached)
add_filter('pmpro_magic_levels_cache_method', fn() => 'object');

// Cache for 24 hours
add_filter('pmpro_magic_levels_cache_duration', fn() => DAY_IN_SECONDS);
```

### Example 4: Secure Webhook Only
```php
<?php
// Require authentication
add_filter('pmpro_magic_levels_webhook_require_auth', '__return_true');
add_filter('pmpro_magic_levels_webhook_auth_key', fn() => wp_generate_password(32, false));

// Strict rate limiting
add_filter('pmpro_magic_levels_rate_limit', function() {
    return ['max_requests' => 10, 'time_window' => 3600, 'by' => 'ip'];
});
```

---

## Filter Priority

All filters use default WordPress priority (10). To override other filters, use higher priority:

```php
// This runs after other filters
add_filter('pmpro_magic_levels_min_price', fn() => 20.00, 20);
```

---

## Actions (Hooks)

### `pmpro_magic_levels_cache_cleared`
**Description:** Fired when cache is cleared  
**Parameters:** None  
**Example:**
```php
add_action('pmpro_magic_levels_cache_cleared', function() {
    error_log('Magic Levels cache was cleared');
});
```

---

## Default Values Summary

| Filter | Default | Type |
|--------|---------|------|
| `price_increment` | 1.00 | float |
| `min_price` | 0.00 | float |
| `max_price` | 9999.99 | float |
| `allow_free_levels` | true | bool |
| `require_initial_payment` | false | bool |
| `allowed_periods` | ['Day', 'Week', 'Month', 'Year'] | array |
| `allowed_cycle_numbers` | [1, 2, 3, 6, 12] | array |
| `max_billing_limit` | 999 | int |
| `min_name_length` | 1 | int |
| `max_name_length` | 255 | int |
| `name_pattern` | null | string/null |
| `name_blacklist` | [] | array |
| `rate_limit` | 100 req/hour | array |
| `max_levels_per_day` | 1000 | int |
| `enable_webhook` | true | bool |
| `webhook_require_auth` | false | bool |
| `webhook_auth_key` | '' | string |
| `enable_cache` | true | bool |
| `cache_duration` | 3600 | int |
| `cache_method` | 'transient' | string |

---

## Testing Filters

To test if your filters are working:

```php
<?php
// Test validation
$result = pmpro_magic_levels_process([
    'name' => 'Test',
    'billing_amount' => 7.00  // Should fail if min_price is 10
]);

var_dump($result);
// Expected: ['success' => false, 'error' => 'Price must be at least $10.00']
```
