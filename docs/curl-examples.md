# cURL Test Examples for PMPro Magic Levels

Replace `yoursite.com` with your actual domain.

## Basic Level with Group (Required)

**Important:** All level names must include a group using the format `"GroupName - LevelName"`.

```bash
curl -X POST https://yoursite.com/wp-json/pmpro-magic-levels/v1/process \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Premium - Gold",
    "billing_amount": 29.99,
    "cycle_period": "Month",
    "cycle_number": 1
  }'
```

## Level with Group

Use the format: `"GroupName - LevelName"`

The plugin automatically creates a group called "GroupName" and assigns the level to it.

```bash
curl -X POST https://yoursite.com/wp-json/pmpro-magic-levels/v1/process \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Basic - Gold",
    "billing_amount": 19.99,
    "cycle_period": "Month",
    "cycle_number": 1,
    "description": "Gold tier in Basic group"
  }'
```

## Multiple Levels in Same Group

```bash
# Basic - Silver
curl -X POST https://yoursite.com/wp-json/pmpro-magic-levels/v1/process \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Basic - Silver",
    "billing_amount": 9.99,
    "cycle_period": "Month",
    "cycle_number": 1
  }'

# Basic - Gold
curl -X POST https://yoursite.com/wp-json/pmpro-magic-levels/v1/process \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Basic - Gold",
    "billing_amount": 19.99,
    "cycle_period": "Month",
    "cycle_number": 1
  }'

# Basic - Platinum
curl -X POST https://yoursite.com/wp-json/pmpro-magic-levels/v1/process \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Basic - Platinum",
    "billing_amount": 29.99,
    "cycle_period": "Month",
    "cycle_number": 1
  }'
```

All three levels will be in the "Basic" group.

## Different Groups

```bash
# Pro group
curl -X POST https://yoursite.com/wp-json/pmpro-magic-levels/v1/process \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Pro - Premium",
    "billing_amount": 49.99,
    "cycle_period": "Month",
    "cycle_number": 1
  }'

# Enterprise group
curl -X POST https://yoursite.com/wp-json/pmpro-magic-levels/v1/process \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Enterprise - Ultimate",
    "billing_amount": 99.99,
    "cycle_period": "Month",
    "cycle_number": 1
  }'
```

## Yearly Billing

```bash
curl -X POST https://yoursite.com/wp-json/pmpro-magic-levels/v1/process \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Annual - Premium",
    "billing_amount": 299.99,
    "cycle_period": "Year",
    "cycle_number": 1
  }'
```

## With Trial Period

```bash
curl -X POST https://yoursite.com/wp-json/pmpro-magic-levels/v1/process \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Trial - Premium",
    "billing_amount": 29.99,
    "cycle_period": "Month",
    "cycle_number": 1,
    "trial_amount": 0,
    "trial_limit": 1,
    "description": "Premium with 1 month free trial"
  }'
```

## With Setup Fee (Initial Payment)

```bash
curl -X POST https://yoursite.com/wp-json/pmpro-magic-levels/v1/process \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Enterprise - Setup",
    "billing_amount": 99.99,
    "cycle_period": "Month",
    "cycle_number": 1,
    "initial_payment": 199.99,
    "description": "Enterprise with $199.99 setup fee"
  }'
```

## With Expiration

```bash
curl -X POST https://yoursite.com/wp-json/pmpro-magic-levels/v1/process \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Limited - 30 Days",
    "billing_amount": 19.99,
    "cycle_period": "Month",
    "cycle_number": 1,
    "expiration_number": 30,
    "expiration_period": "Day"
  }'
```

## With Billing Limit

```bash
curl -X POST https://yoursite.com/wp-json/pmpro-magic-levels/v1/process \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Fixed - 12 Months",
    "billing_amount": 29.99,
    "cycle_period": "Month",
    "cycle_number": 1,
    "billing_limit": 12,
    "description": "12 monthly payments then stops"
  }'
```

## Free Level

```bash
curl -X POST https://yoursite.com/wp-json/pmpro-magic-levels/v1/process \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Free - Basic",
    "billing_amount": 0,
    "cycle_period": "Month",
    "cycle_number": 1
  }'
```

## With Authentication

First, enable authentication in `functions.php`:

```php
add_filter( 'pmpro_magic_levels_webhook_require_auth', '__return_true' );
add_filter( 'pmpro_magic_levels_webhook_auth_key', function() {
    return 'your-secret-key-here';
} );
```

Then include the auth key in your request:

```bash
curl -X POST https://yoursite.com/wp-json/pmpro-magic-levels/v1/process \
  -H "Content-Type: application/json" \
  -d '{
    "auth_key": "your-secret-key-here",
    "name": "Secure - Premium",
    "billing_amount": 39.99,
    "cycle_period": "Month",
    "cycle_number": 1
  }'
```

## How Groups Work

The plugin automatically creates groups based on the level name:

- **Format:** `"GroupName - LevelName"`
- **Separator:** ` - ` (space, hyphen, space)
- **Example:** `"Basic - Gold"` creates a group called "Basic"

**Examples:**
- `"Basic - Silver"` → Group: "Basic", Level: "Silver"
- `"Pro - Premium"` → Group: "Pro", Level: "Premium"
- `"Enterprise - Ultimate"` → Group: "Enterprise", Level: "Ultimate"
- `"Premium Membership"` → No group (no separator)

**Customizing Group Assignment:**

You can customize how groups are assigned using a filter:

```php
add_filter( 'pmpro_magic_levels_group_name', function( $group_name, $params ) {
    // Custom logic to determine group name
    // Return empty string to skip group assignment
    return $group_name;
}, 10, 2 );
```

## Expected Responses

### Success Response

```json
{
  "success": true,
  "level_id": 5,
  "redirect_url": "https://yoursite.com/checkout/?level=5",
  "level_created": true,
  "cached": false,
  "message": "New level created"
}
```

### Error Response

```json
{
  "success": false,
  "error": "Name is required",
  "code": "missing_required_field"
}
```

## Testing Tips

1. **Check created levels:** Go to `wp-admin/admin.php?page=pmpro-membershiplevels`
2. **Check groups:** If using PMPro 3.0+, groups will be visible in the levels page
3. **Test redirect:** Copy the `redirect_url` from the response and visit it
4. **Check caching:** Run the same request twice - second time should return `"cached": true`

## WSForm Integration

To use groups in WSForm, just format the name field with the separator:

```json
{
  "name": "Basic - #field(1)",
  "billing_amount": "#field(2)",
  "cycle_period": "Month",
  "cycle_number": 1
}
```

If field(1) contains "Gold", the level will be "Basic - Gold" in the "Basic" group.
