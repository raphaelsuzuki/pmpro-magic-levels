# PMPro Magic Levels Documentation

Welcome to the PMPro Magic Levels documentation!

## Table of Contents

### Getting Started
- [Getting Started Guide](getting-started.md) - Installation and quick start
- [How Groups Work](groups.md) - Understanding the group system

### Configuration
- [Configuration Options](configuration.md) - All available filters and settings
- [Validation Rules](validation-rules.md) - Security and validation

### Integration Guides
- [WSForm Integration](integrations/wsform.md) - Complete WSForm guide
- [WPForms Integration](integrations/wpforms.md) - WPForms examples
- [Gravity Forms Integration](integrations/gravity-forms.md) - Gravity Forms examples
- [Custom Integrations](integrations/custom.md) - Build your own

### Reference
- [API Reference](api-reference.md) - REST API documentation
- [cURL Examples](curl-examples.md) - Test examples with cURL
- [Error Codes](api-reference.md#error-codes) - All error codes explained
- [Troubleshooting](troubleshooting.md) - Common issues and solutions

## Quick Links

- [GitHub Repository](https://github.com/yourusername/pmpro-magic-levels)
- [Report an Issue](https://github.com/yourusername/pmpro-magic-levels/issues)
- [Request a Feature](https://github.com/yourusername/pmpro-magic-levels/issues/new)

## Need Help?

1. Check the [Troubleshooting Guide](troubleshooting.md)
2. Search [existing issues](https://github.com/yourusername/pmpro-magic-levels/issues)
3. Create a [new issue](https://github.com/yourusername/pmpro-magic-levels/issues/new) with details

## Contributing

We welcome contributions! Please see our [Contributing Guide](../CONTRIBUTING.md) for details.

## Advanced Validation

You can add custom validation rules using filters. Here are some examples:

### Set Price Limits

```php
// Add to your theme's functions.php or a custom plugin

// Set minimum price
add_filter( 'pmpro_magic_levels_min_price', function() {
	return 10.00; // Minimum $10
} );

// Set maximum price
add_filter( 'pmpro_magic_levels_max_price', function() {
	return 999.99; // Maximum $999.99
} );
```

### Restrict Billing Cycles

```php
// Only allow monthly and yearly billing
add_filter( 'pmpro_magic_levels_allowed_periods', function() {
	return array( 'Month', 'Year' );
} );

// Only allow specific cycle numbers
add_filter( 'pmpro_magic_levels_allowed_cycle_numbers', function() {
	return array( 1, 12 ); // Only 1 month or 12 months
} );
```

### Disable Free Levels

```php
// Require all levels to have a price
add_filter( 'pmpro_magic_levels_allow_free_levels', '__return_false' );
```

### Set Billing Limit Maximum

```php
// Limit billing cycles to 24
add_filter( 'pmpro_magic_levels_max_billing_limit', function() {
	return 24;
} );
```

### Name Pattern Validation

```php
// Require names to match a specific pattern
add_filter( 'pmpro_magic_levels_name_pattern', function() {
	return '/^[A-Z]+ - [A-Za-z0-9 ]+$/'; // Must start with uppercase letters
} );

// Block certain words in level names
add_filter( 'pmpro_magic_levels_name_blacklist', function() {
	return array( 'test', 'demo', 'free' );
} );
```

### Rate Limiting

```php
// Reduce rate limit for tighter security
add_filter( 'pmpro_magic_levels_rate_limit', function( $config ) {
	$config['max_requests'] = 50;  // 50 requests
	$config['time_window']  = 3600; // per hour
	$config['by']           = 'ip'; // by IP address
	return $config;
} );

// Limit daily level creation
add_filter( 'pmpro_magic_levels_max_levels_per_day', function() {
	return 100; // Maximum 100 levels per day
} );
```
