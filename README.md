# PMPro Magic Levels

Dynamically create or find membership levels from user interactions or predefined inputs, then automatically redirect users to checkout—no more manual creation of every membership variation in the admin dashboard.

Perfect for 'name-your-price' forms, complex plan systems, dynamic pricing systems, and any scenario requiring dozens of membership variations without admin panel clutter.

Integrate with form plugins, page builders, help desks, and workflow automations to give your membership site more power and flexibility. We have a **growing list of compatibility** with popular plugins like WSForm, Contact Form 7, and Formidable Forms.

**Notice:** This plugin is intended for developers. It exposes a powerful REST API and writes membership data based on incoming payloads. Secure your tokens and follow the [Security Best Practices](https://github.com/raphaelsuzuki/pmpro-magic-levels/wiki/Security-Best-Practices).

---

## Key Features

- **Dynamic Level Creation**: Generate membership levels on-demand from form submissions or API calls.
- **Smart Level Matching**: Automatically detects and reuses existing levels with matching parameters to prevent duplicates.
- **Three-Tier Caching System**: Lightning-fast lookups via Memory, Transient, and Database.
- **Full Pricing Control**: Support for one-time payments, subscriptions, trials, billing limits, and expirations.
- **Automatic Content Protection**: Protect categories, pages, and posts instantly during level creation.
- **Group-Based Organization**: Automatic level assignment to groups using the required "Group - Level" naming format.

Learn more about our [architecture and technical overview](https://github.com/raphaelsuzuki/pmpro-magic-levels/wiki/Technical-Overview) in the Wiki.

---

## Quick Start

The plugin provides two main methods to create membership levels. Choose the one that fits your environment:

### Integration Comparison

| Feature | Internal PHP Integration | REST API Integration (Remote) |
| :--- | :--- | :--- |
| **Recommendation** | **Best for WordPress Plugins** | Best for Automators (Zapier, n8n) |
| **Performance** | Instant (Direct call) | Slower (HTTP Request) |
| **Authentication** | Automatic (Internal) | Required (Bearer Token) |
| **Ease of Use** | High (Drop-in snippet) | Medium (Requires JSON handling) |

---

### Method A: Internal PHP Integration (Recommended)

Here's a simple example of how to generate a level and redirect the user to checkout. This can be used inside your own plugins, themes, or form handlers (e.g., WSForm, CF7, Formidable).

```php
$result = pmpro_magic_levels_process([
    'name'           => 'Premium - Gold',
    'billing_amount' => 29.99,
    'cycle_period'   => 'Month',
    'cycle_number'   => 1,
]);

if ( $result['success'] ) {
    wp_redirect( $result['redirect_url'] );
    exit;
}
```

[Check on the wiki for more details](https://github.com/raphaelsuzuki/pmpro-magic-levels/wiki/PHP-Integration)

### Method B: REST API Integration (Remote)

You can create new levels just by using a cURL request. You'll need to generate a token in **PMPro > Magic Levels**. An easiest way to test the plugin is by clicking the **Test** button in the **PMPro > Magic Levels** page.

```bash
curl -X POST https://yoursite.com/wp-json/pmpro-magic-levels/v1/process \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"name": "Standard - Silver", "billing_amount": 10.00, "cycle_period": "Month", "cycle_number": 1}'
```

[Check on the wiki for more details](https://github.com/raphaelsuzuki/pmpro-magic-levels/wiki/Webhooks-and-cURL-Examples)


---

## Documentation

Comprehensive guides, technical specifications, and advanced validation rules are available on our **[GitHub Wiki](https://github.com/raphaelsuzuki/pmpro-magic-levels/wiki)**.

### Useful Wiki Links:
*   [**Getting Started**](https://github.com/raphaelsuzuki/pmpro-magic-levels/wiki/Getting-Started) - Requirements and installation.
*   [**Full Parameter Reference**](https://github.com/raphaelsuzuki/pmpro-magic-levels/wiki/PHP-Integration#function-parameters) - Every supported field.
*   [**Filters Reference**](https://github.com/raphaelsuzuki/pmpro-magic-levels/wiki/Filters-Reference) - Customize validation, rate limits, and caching.
*   [**Technical Overview**](https://github.com/raphaelsuzuki/pmpro-magic-levels/wiki/Technical-Overview) - Architecture, Smart Level Matching, and Caching details.
*   [**Security Best Practices**](https://github.com/raphaelsuzuki/pmpro-magic-levels/wiki/Security-Best-Practices) - Token rotation and auditing.
*   [**cURL Examples**](https://github.com/raphaelsuzuki/pmpro-magic-levels/wiki/Webhooks-and-cURL-Examples) - API usage for remote services.
*   [**Integration Guides**](https://github.com/raphaelsuzuki/pmpro-magic-levels/wiki/Home#integration-guides) - WSForm, CF7, Formidable Forms.
*   [**Troubleshooting & FAQ**](https://github.com/raphaelsuzuki/pmpro-magic-levels/wiki/Troubleshooting) - Common issues and solutions.

## Support

- **Issues:** [GitHub Issues](https://github.com/raphaelsuzuki/pmpro-magic-levels/issues)
- **Documentation:** [GitHub Wiki](https://github.com/raphaelsuzuki/pmpro-magic-levels/wiki) and inline help
- **Updates:** Automatic via Git Updater

## Contributing

Contributions are welcome! Please submit pull requests or open issues on GitHub.

- **Repository:** https://github.com/raphaelsuzuki/pmpro-magic-levels
- **Bug Reports:** Use GitHub Issues for bug reports and feature requests
- **Pull Requests:** Follow [WordPress Coding Standards](https://github.com/raphaelsuzuki/pmpro-magic-levels/blob/main/CONTRIBUTING.md#coding-standards)

## License

GPL v2 or later - https://www.gnu.org/licenses/gpl-2.0.html

## Disclaimer

This repository and its documentation were created with the assistance of AI. While efforts have been made to ensure accuracy and completeness, no guarantee is provided. Use at your own risk. Always test in a safe environment before deploying to production.