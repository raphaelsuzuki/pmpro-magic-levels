# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [1.1.0] - 2024-12-12

### Added

#### Content Protection Integration
- **Content Protection System**: Automatically protect categories, pages, and posts when creating membership levels
- **New API Parameters**:
  - `protected_categories` - Array of category/tag IDs to protect
  - `protected_pages` - Array of page IDs to protect  
  - `protected_posts` - Array of post IDs to protect
- **PMPro Integration**: Uses PMPro's native content protection system (`pmpro_memberships_categories` and `pmpro_memberships_pages` tables)
- **Additive Protection**: New levels are added to existing content restrictions (doesn't replace)

#### Validator Enhancements
- **Content Protection Validation**: New `validate_content_protection()` method
- **Category/Tag Validation**: Validates IDs exist and are actual categories or tags
- **Page Validation**: Validates page IDs exist and are actual pages
- **Post Validation**: Validates post IDs exist in database
- **New Error Codes**: 
  - `invalid_content_protection` - Parameter not an array
  - `invalid_category_id` - Invalid category ID
  - `category_not_found` - Category doesn't exist
  - `invalid_taxonomy` - Term is not a category or tag
  - `invalid_page_id` - Invalid page ID
  - `page_not_found` - Page doesn't exist
  - `invalid_post_id` - Invalid post ID
  - `post_not_found` - Post doesn't exist

#### Level Matcher Enhancements
- **Content Assignment**: New `assign_content_protection()` method
- **Category Protection**: Uses PMPro's `pmpro_updateMembershipCategories()` function
- **Page/Post Protection**: Uses PMPro's `pmpro_update_post_level_restrictions()` function (PMPro 3.1+)
- **Fallback Support**: Direct database insert for older PMPro versions
- **Account Message Fix**: Corrected meta key from `account_message` to `membership_account_message`

#### Admin Interface Improvements
- **Reorganized API Parameters Table**: 
  - Merged Required and Optional parameters into single table
  - Added "Required" column with clear indicators
  - Organized into logical sections: General Information, Billing Details, Expiration Settings, Content Settings, Other Settings
  - Added examples for every parameter
  - Clear default value indicators ("Defaults to 0", "Optional", etc.)
- **Content Protection Documentation**: Added comprehensive documentation for new parameters
- **Enhanced Test Webhook**: 
  - Now includes content protection examples
  - Automatically protects sample categories and pages
  - Added confirmation and account message examples
  - More realistic test data generation

#### Documentation
- **Complete Content Protection Guide**: New `docs/content-protection.md` with 300+ lines
- **Usage Examples**: New `examples/content-protection-example.php` with 10 complete examples
- **Research Documentation**: `RESEARCH-CONTENT-PROTECTION.md` with PMPro integration details
- **Updated README**: Added content protection examples and documentation links
- **API Reference**: Updated with new parameters and examples

### Changed

#### API Parameters Table
- **Single Table Structure**: Merged Required and Optional parameters
- **Section Organization**: Grouped parameters by functionality
- **Enhanced Descriptions**: Added examples and default values for all parameters
- **Visual Improvements**: Better formatting and clearer required/optional indicators

#### Test Functionality
- **Comprehensive Testing**: Test webhook now demonstrates all features
- **Content Protection Demo**: Automatically protects sample content
- **Complete Examples**: Includes confirmation messages and account messages

### Fixed

- **Account Message Storage**: Fixed meta key from `account_message` to `membership_account_message`
- **Content Protection Validation**: Proper validation of all content IDs before assignment
- **PMPro Compatibility**: Ensures compatibility with PMPro 3.0+ and older versions

### Technical Details

#### Database Integration
- **No New Tables**: Uses PMPro's existing `pmpro_memberships_categories` and `pmpro_memberships_pages` tables
- **Efficient Queries**: Leverages PMPro's indexed lookups and caching
- **Performance**: No performance impact, uses PMPro's existing systems

#### PMPro Functions Used
- `pmpro_updateMembershipCategories($level_id, $category_ids)` - Category assignment
- `pmpro_update_post_level_restrictions($post_id, $level_ids)` - Page/post assignment (PMPro 3.1+)
- `update_pmpro_membership_level_meta($level_id, $key, $value)` - Account message storage

#### Compatibility
- **PMPro 3.0+**: Full compatibility with latest PMPro features
- **PMPro 3.1+**: Uses newer API functions when available
- **Backward Compatible**: Fallback support for older PMPro versions
- **All Payment Gateways**: Works with all PMPro payment gateways
- **Custom Post Types**: Supports protection via `protected_posts` parameter

### Examples

#### Basic Content Protection
```json
{
  "name": "Premium - Gold",
  "billing_amount": 29.99,
  "protected_categories": [5, 12],
  "protected_pages": [42, 67],
  "protected_posts": [123, 456]
}
```

#### PHP Usage
```php
$result = pmpro_magic_levels_process([
    'name' => 'Premium - Gold',
    'billing_amount' => 29.99,
    'protected_categories' => [5, 12],
    'protected_pages' => [42]
]);
```

### Files Added
- `docs/content-protection.md` - Complete content protection guide with usage examples

### Files Modified
- `includes/class-validator.php` - Added content protection validation
- `includes/class-level-matcher.php` - Added content protection assignment
- `includes/class-admin-page.php` - Enhanced admin interface and documentation
- `README.md` - Updated with content protection information
- `docs/README.md` - Added content protection link

## [1.0.0] - 2024-12-01

### Added
- Initial release of PMPro Magic Levels
- **Dynamic Level Creation**: Create or find membership levels from form submissions
- **Smart Deduplication**: Automatically finds and reuses existing levels
- **High Performance**: 3-tier caching system (Memory, Transient, DB)
- **REST API Webhook**: Secure Bearer token authentication
- **Extensive Validation**: Configurable rules via WordPress filters
- **PMPro Integration**: Native integration with Paid Memberships Pro
- **Group Support**: Automatic level group assignment based on name format
- **Rate Limiting**: Built-in protection with external CDN recommendations
- **Admin Interface**: Complete settings and documentation page
- **Developer Friendly**: Works via REST API webhook or direct PHP function

#### Core Features
- **Level Matching**: Find existing levels by exact parameter match
- **Level Creation**: Create new levels with full PMPro compatibility
- **Caching System**: Memory → Transient → Database with automatic invalidation
- **Validation Rules**: 20+ configurable filters for customization
- **Security**: Cryptographically secure 64-character Bearer tokens
- **Error Handling**: Comprehensive error codes and messages

#### API Parameters
- **Required**: `name`, `billing_amount`
- **Optional**: All PMPro level parameters supported
- **Validation**: Extensive validation with helpful error messages
- **Flexibility**: Support for all PMPro billing configurations

#### Admin Features
- **Settings Page**: Complete configuration interface
- **API Documentation**: Built-in parameter reference
- **Test Functionality**: One-click webhook testing
- **Security Management**: Token generation and regeneration
- **Site Health Integration**: WordPress Site Health compatibility

### Technical Specifications
- **WordPress**: 5.0+ required
- **PHP**: 7.4+ required
- **PMPro**: 3.0+ required
- **Performance**: ~0-50ms level lookups with caching
- **Security**: Bearer token authentication with rate limiting
- **Compatibility**: All PMPro payment gateways and add-ons

---

## Links

- [Repository](https://github.com/yourusername/pmpro-magic-levels)
- [Documentation](docs/)
- [Content Protection Guide](docs/content-protection.md)
- [Examples](examples/)
- [Security Guide](docs/security.md)
