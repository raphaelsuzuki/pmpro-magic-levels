# Content Protection

PMPro Magic Levels integrates with Paid Memberships Pro's built-in content protection system, allowing you to automatically protect content when creating membership levels.

## Overview

When creating a level via the webhook or PHP function, you can specify which content should be protected:

- **Categories/Tags** - Protect all posts in specific categories or tags
- **Pages** - Protect individual pages
- **Posts** - Protect individual posts

## How It Works

PMPro Magic Levels uses PMPro's native content protection system:

1. **Categories** are stored in `pmpro_memberships_categories` table
2. **Pages/Posts** are stored in `pmpro_memberships_pages` table
3. Content protection is **additive** - new levels are added to existing restrictions

### Additive Protection

If a page is already protected by Level A, and you create Level B with that same page in `protected_pages`, the page will now be accessible to members with **either** Level A **or** Level B.

This is PMPro's standard behavior - users need ANY ONE of the assigned levels to access the content.

## API Parameters

### `protected_categories`

**Type:** Array of integers  
**Description:** Category or tag IDs to protect

```json
{
  "name": "Premium - Gold",
  "billing_amount": 29.99,
  "protected_categories": [5, 12, 18]
}
```

**Effect:** All posts in categories 5, 12, and 18 will require this membership level.

### `protected_pages`

**Type:** Array of integers  
**Description:** Page IDs to protect

```json
{
  "name": "Premium - Gold",
  "billing_amount": 29.99,
  "protected_pages": [42, 67, 89]
}
```

**Effect:** Pages with IDs 42, 67, and 89 will require this membership level.

### `protected_posts`

**Type:** Array of integers  
**Description:** Post IDs to protect

```json
{
  "name": "Premium - Gold",
  "billing_amount": 29.99,
  "protected_posts": [123, 456, 789]
}
```

**Effect:** Posts with IDs 123, 456, and 789 will require this membership level.

## Complete Example

```bash
curl -X POST https://yoursite.com/wp-json/pmpro-magic-levels/v1/process \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -d '{
    "name": "Premium - Gold",
    "billing_amount": 29.99,
    "cycle_period": "Month",
    "cycle_number": 1,
    "protected_categories": [5, 12],
    "protected_pages": [42, 67],
    "protected_posts": [123, 456]
  }'
```

## JavaScript Example

```javascript
const levelData = {
    name: 'Premium - Gold',
    billing_amount: 29.99,
    cycle_period: 'Month',
    cycle_number: 1,
    protected_categories: [5, 12],
    protected_pages: [42],
    protected_posts: [123, 456]
};

fetch('/wp-json/pmpro-magic-levels/v1/process', {
    method: 'POST',
    headers: {
        'Content-Type': 'application/json',
        'Authorization': 'Bearer YOUR_TOKEN'
    },
    body: JSON.stringify(levelData)
})
.then(response => response.json())
.then(data => {
    if (data.success) {
        console.log('Level created:', data.level_id);
        console.log('Content protected automatically');
        window.location.href = data.redirect_url;
    }
});
```

## PHP Example

```php
<?php
$result = pmpro_magic_levels_process([
    'name' => 'Premium - Gold',
    'billing_amount' => 29.99,
    'cycle_period' => 'Month',
    'cycle_number' => 1,
    'protected_categories' => [5, 12],
    'protected_pages' => [42, 67],
    'protected_posts' => [123, 456]
]);

if ($result['success']) {
    echo "Level created with ID: " . $result['level_id'];
    echo "Content protection applied automatically";
}
```

## Finding Content IDs

### Get Category IDs

**Via WordPress Admin:**
1. Go to Posts > Categories
2. Hover over a category name
3. Look at the URL: `...term.php?taxonomy=category&tag_ID=5...`
4. The number after `tag_ID=` is the category ID

**Via Code:**
```php
<?php
// Get all categories
$categories = get_categories();
foreach ($categories as $cat) {
    echo $cat->term_id . ' - ' . $cat->name . "\n";
}

// Get specific category by slug
$cat = get_category_by_slug('premium-content');
echo $cat->term_id;
```

### Get Page IDs

**Via WordPress Admin:**
1. Go to Pages > All Pages
2. Hover over a page title
3. Look at the URL: `...post.php?post=42&action=edit`
4. The number after `post=` is the page ID

**Via Code:**
```php
<?php
// Get all pages
$pages = get_pages();
foreach ($pages as $page) {
    echo $page->ID . ' - ' . $page->post_title . "\n";
}

// Get specific page by slug
$page = get_page_by_path('members-only');
echo $page->ID;
```

### Get Post IDs

**Via WordPress Admin:**
1. Go to Posts > All Posts
2. Hover over a post title
3. Look at the URL: `...post.php?post=123&action=edit`
4. The number after `post=` is the post ID

**Via Code:**
```php
<?php
// Get all posts
$posts = get_posts(['numberposts' => -1]);
foreach ($posts as $post) {
    echo $post->ID . ' - ' . $post->post_title . "\n";
}

// Get specific post by slug
$post = get_page_by_path('premium-article', OBJECT, 'post');
echo $post->ID;
```

## Validation

The plugin validates all content protection parameters:

### Category Validation
- Must be an array
- All IDs must be positive integers
- Categories must exist in the database
- Must be actual categories or tags (not other taxonomies)

### Page Validation
- Must be an array
- All IDs must be positive integers
- Pages must exist in the database
- Must be actual pages (post_type = 'page')

### Post Validation
- Must be an array
- All IDs must be positive integers
- Posts must exist in the database

## Error Handling

If validation fails, you'll receive an error response:

```json
{
  "success": false,
  "error": "Category ID 999 does not exist",
  "code": "category_not_found"
}
```

### Common Errors

- `invalid_content_protection` - Parameter is not an array
- `invalid_category_id` - Category ID is not a positive integer
- `category_not_found` - Category doesn't exist
- `invalid_taxonomy` - Term is not a category or tag
- `invalid_page_id` - Page ID is not a positive integer
- `page_not_found` - Page doesn't exist
- `invalid_post_id` - Post ID is not a positive integer
- `post_not_found` - Post doesn't exist

## Use Cases

### Use Case 1: Tiered Content Access

Create different levels with different content access:

```php
<?php
// Basic Level - Access to category 5 only
pmpro_magic_levels_process([
    'name' => 'Membership - Basic',
    'billing_amount' => 9.99,
    'protected_categories' => [5]
]);

// Premium Level - Access to categories 5 and 12
pmpro_magic_levels_process([
    'name' => 'Membership - Premium',
    'billing_amount' => 29.99,
    'protected_categories' => [5, 12]
]);

// VIP Level - Access to all categories plus exclusive pages
pmpro_magic_levels_process([
    'name' => 'Membership - VIP',
    'billing_amount' => 99.99,
    'protected_categories' => [5, 12, 18],
    'protected_pages' => [42, 67]
]);
```

### Use Case 2: Course-Based Membership

Protect course pages when creating a level:

```php
<?php
// Get all course pages
$course_pages = get_pages([
    'meta_key' => 'course_type',
    'meta_value' => 'premium'
]);

$course_ids = array_map(function($page) {
    return $page->ID;
}, $course_pages);

// Create level with course access
pmpro_magic_levels_process([
    'name' => 'Courses - Premium Bundle',
    'billing_amount' => 199.99,
    'protected_pages' => $course_ids
]);
```

### Use Case 3: Dynamic Pricing with Content

Let users select content and price:

```javascript
// User selects categories they want access to
const selectedCategories = [5, 12, 18];
const pricePerCategory = 10;
const totalPrice = selectedCategories.length * pricePerCategory;

fetch('/wp-json/pmpro-magic-levels/v1/process', {
    method: 'POST',
    headers: {
        'Content-Type': 'application/json',
        'Authorization': 'Bearer YOUR_TOKEN'
    },
    body: JSON.stringify({
        name: 'Custom - ' + selectedCategories.length + ' Categories',
        billing_amount: totalPrice,
        protected_categories: selectedCategories
    })
})
.then(response => response.json())
.then(data => {
    if (data.success) {
        window.location.href = data.redirect_url;
    }
});
```

### Use Case 4: Protect Child Pages

Protect all pages under a parent page:

```php
<?php
// Get all child pages of a parent page
$parent_page_id = 10;
$child_pages = get_pages([
    'child_of' => $parent_page_id,
]);

$page_ids = array_map(function($page) {
    return $page->ID;
}, $child_pages);

$result = pmpro_magic_levels_process([
    'name' => 'Courses - Complete Bundle',
    'billing_amount' => 199.99,
    'cycle_period' => 'Year',
    'cycle_number' => 1,
    'protected_pages' => $page_ids,
]);

if ($result['success']) {
    echo 'All course pages are now protected';
}
```

### Use Case 5: Protect Posts by Custom Field

Protect posts based on custom field values:

```php
<?php
// Get all posts with a specific custom field
$premium_posts = get_posts([
    'numberposts' => -1,
    'meta_key' => 'content_type',
    'meta_value' => 'premium',
]);

$post_ids = array_map(function($post) {
    return $post->ID;
}, $premium_posts);

$result = pmpro_magic_levels_process([
    'name' => 'Premium - Content Access',
    'billing_amount' => 49.99,
    'cycle_period' => 'Month',
    'cycle_number' => 1,
    'protected_posts' => $post_ids,
]);

if ($result['success']) {
    echo 'All premium posts are now protected';
}
```

### Use Case 6: Form Plugin Integrations

**WPForms Integration:**

```php
<?php
add_action('wpforms_process_complete', function($fields, $entry, $form_data) {
    // Only process form ID 123
    if (123 !== $form_data['id']) {
        return;
    }

    // Get selected categories from checkbox field
    $selected_categories = isset($fields[5]['value']) ? explode(',', $fields[5]['value']) : [];
    $selected_categories = array_map('intval', $selected_categories);

    $result = pmpro_magic_levels_process([
        'name' => $fields[1]['value'], // Name field
        'billing_amount' => $fields[2]['value'], // Price field
        'cycle_period' => $fields[3]['value'], // Period field
        'cycle_number' => 1,
        'protected_categories' => $selected_categories,
    ]);

    if ($result['success']) {
        wp_redirect($result['redirect_url']);
        exit;
    }
}, 10, 3);
```

**Gravity Forms Integration:**

```php
<?php
add_action('gform_after_submission_5', function($entry, $form) {
    // Get category IDs from multi-select field
    $category_ids = rgar($entry, '5'); // Field 5 is multi-select
    $category_ids = json_decode($category_ids, true);

    $result = pmpro_magic_levels_process([
        'name' => rgar($entry, '1'),
        'billing_amount' => rgar($entry, '2'),
        'cycle_period' => rgar($entry, '3'),
        'cycle_number' => 1,
        'protected_categories' => $category_ids,
    ]);

    if ($result['success']) {
        wp_redirect($result['redirect_url']);
        exit;
    }
}, 10, 2);
```

## How PMPro Checks Access

When a user tries to view protected content, PMPro:

1. Checks if the post/page is in `pmpro_memberships_pages`
2. For posts, also checks if categories/tags are in `pmpro_memberships_categories`
3. Gets the list of required membership levels
4. Checks if the current user has ANY of those levels
5. Grants access if user has at least one matching level

## Removing Protection

Content protection is managed by PMPro. To remove protection:

**Via WordPress Admin:**
1. Go to Memberships > Membership Levels
2. Edit the level
3. Scroll to "Content Settings"
4. Uncheck categories or remove pages

**Via Code:**
```php
<?php
// Remove all category protections for a level
pmpro_updateMembershipCategories($level_id, []);

// Remove a page from all level restrictions
pmpro_update_post_level_restrictions($page_id, []);
```

## Performance Considerations

- Category protection is efficient (one query per post)
- Page/post protection is also efficient (indexed lookups)
- PMPro has built-in caching for content restrictions
- No performance impact from using content protection

## Compatibility

Content protection works with:
- ✅ PMPro 3.0+
- ✅ All PMPro payment gateways
- ✅ PMPro Add-ons (Advanced Levels, etc.)
- ✅ Custom post types (via `protected_posts`)
- ✅ Categories and tags

## Troubleshooting

### Content Not Protected

**Check:**
1. Is the level active? (`allow_signups = 1`)
2. Do the category/page IDs exist?
3. Is PMPro's content protection enabled?
4. Check PMPro > Settings > Advanced Settings

### Wrong Content Protected

**Check:**
1. Verify the IDs are correct
2. Remember: protection is additive
3. Check if other levels also protect the same content

### User Can't Access Content

**Check:**
1. Does the user have an active membership?
2. Is the membership level correct?
3. Check PMPro > Members to verify user's level

## Additional Resources

- [PMPro Content Restriction Documentation](https://www.paidmembershipspro.com/documentation/content-controls/)
- [PMPro Advanced Content Protection](https://www.paidmembershipspro.com/add-ons/pmpro-advanced-levels-shortcode/)
- [PMPro Series Add-on](https://www.paidmembershipspro.com/add-ons/pmpro-series/) - For drip content

