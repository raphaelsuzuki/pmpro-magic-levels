# Advanced Validation

You can add custom validation rules using filters. Here are some examples:

## Set Price Limits

```php
// Add to your theme's functions.php or a custom plugin

// Set minimum price
add_filter( 'pmpro_magic_levels_min_price', function() {
	return 10.00; // Minimum $10.
} );

// Set maximum price
add_filter( 'pmpro_magic_levels_max_price', function() {
	return 999.99; // Maximum $999.99.
} );
```

## Restrict Billing Cycles

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

## Disable Free Levels

```php
// Require all levels to have a price
add_filter( 'pmpro_magic_levels_allow_free_levels', '__return_false' );
```

## Set Billing Limit Maximum

```php
// Limit billing cycles to 24
add_filter( 'pmpro_magic_levels_max_billing_limit', function() {
	return 24;
} );
```

## Name Pattern Validation

```php
// Require names to match a specific pattern
add_filter( 'pmpro_magic_levels_name_pattern', function() {
	return '/^[A-Z]+ - [A-Za-z0-9 ]+$/'; // Must start with uppercase letters.
} );

// Block certain words in level names
add_filter( 'pmpro_magic_levels_name_blacklist', function() {
	return array( 'test', 'demo', 'free' );
} );
```

## Rate Limiting

```php
// Reduce rate limit for tighter security.
add_filter( 'pmpro_magic_levels_rate_limit', function( $config ) {
	$config['max_requests'] = 50;   // 50 requests.
	$config['time_window']  = 3600; // per hour.
	$config['by']           = 'ip';   // by IP address.
	return $config;
} );

// Limit daily level creation.
add_filter( 'pmpro_magic_levels_max_levels_per_day', function() {
	return 100; // Maximum 100 levels per day.
} );
```
