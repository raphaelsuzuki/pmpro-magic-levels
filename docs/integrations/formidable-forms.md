# Formidable Forms Integration

Formidable Forms does not natively support "Redirect based on API Response" out of the box. However, you can easily achieve this with a small code snippet in your theme's `functions.php` file or a code snippet plugin.

## The Strategy

We will use the `frm_redirect_url` filter to:
1.  Intercept the form submission before the redirect happens.
2.  Send the data to PMPro Magic Levels.
3.  Get the `redirect_url` from the response.
4.  Change the Formidable redirect to point to the checkout.

## Step-by-Step Guide

### 1. Create Your Form
Create a form with fields for:
*   **Name** (e.g., "Group - Level Name")
*   **Price** (Hidden or User defined)
*   **Cycle** (Optional, can be hardcoded in the snippet)

**Note the Field IDs** for the data you want to send.

### 2. Configure Form Settings
*   Go to **Settings > General > On Submit**.
*   Select **Redirect to URL**.
*   Enter a placeholder URL (e.g., `http://example.com` - this will be overwritten by our code).

### 3. Add the Code Snippet

Add the following code to your theme's `functions.php` or using a plugin like WPCodeBox/Code Snippets.

```php
/**
 * Integrate Formidable Forms with PMPro Magic Levels
 * 
 * @param string $url    The original redirect URL.
 * @param object $form   The form object.
 * @param array  $params Entry parameters including 'id' and 'entry'.
 * @return string        The new redirect URL.
 */
add_filter('frm_redirect_url', 'my_pmpro_magic_levels_redirect', 10, 3);

function my_pmpro_magic_levels_redirect($url, $form, $params) {
    // 1. CHANGE THIS: Set your specific Form ID
    $target_form_id = 123; 
    
    if ($form->id != $target_form_id) {
        return $url;
    }

    // 2. Map your Formidable fields to Magic Levels data
    // Access posted data via $_POST['item_meta'][FIELD_ID]
    $submitted_data = $_POST['item_meta'];
    
    // Example Mapping (Replace numbers with your Field IDs)
    $level_name = isset($submitted_data[10]) ? $submitted_data[10] : 'Standard - Level';
    $price      = isset($submitted_data[11]) ? $submitted_data[11] : 29.99;
    
    // 3. Prepare the payload
    $body = [
        'name'           => $level_name,
        'billing_amount' => $price,
        'cycle_period'   => 'Month', // Or map from a field
        'cycle_number'   => 1
    ];

    // 4. Send Request to Magic Levels (Internal PHP call is faster than HTTP!)
    // If the plugin is active, we can call its function directly.
    if (function_exists('pmpro_magic_levels_process')) {
        $result = pmpro_magic_levels_process($body);
        
        // 5. Check success and redirect
        if ($result['success'] && !empty($result['redirect_url'])) {
            return $result['redirect_url'];
        }
    }

    // Fallback: If something failed, return the original URL (e.g., a Thank You page)
    return $url;
}
```

## Why use the PHP Function?

Since both Formidable and Magic Levels are running on the same WordPress site, calling `pmpro_magic_levels_process()` directly in PHP is:
*   **Faster:** No network latency.
*   **Simpler:** No need for API tokens or authentication headers.
*   **Reliable:** No risk of failed HTTP requests.

## Troubleshooting

*   **Form ID:** Ensure `$target_form_id` matches your form's ID.
*   **Field IDs:** Check `$_POST` debug data if you aren't sure which `item_meta` keys correspond to your fields.
*   **Redirect Settings:** Make sure the form is set to "Redirect to URL" in the settings, otherwise this filter won't run.
