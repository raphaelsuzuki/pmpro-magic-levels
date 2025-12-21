# Formidable Forms Integration

Integration with Formidable Forms is best achieved using a PHP code snippet. This ensures that the user is immediately redirected to the PMPro checkout page upon form submission.

## The Strategy

We will use the `frm_redirect_url` filter to:
1.  Intercept the form submission before the redirect occurs.
2.  Process the data using the PMPro Magic Levels engine.
3.  Rewrite the redirect URL to the dynamic checkout link.

## Step-by-Step Guide

### 1. Create Your Form
Create a form with fields for:
*   **Name** (e.g., "Group - Level Name")
*   **Price** (Hidden or user-defined)
*   **Cycle** (Optional, can be hardcoded in the snippet)

**Note the Field IDs** for the data you want to send.

### 2. Configure Form Settings
*   Go to **Settings > Actions & Notifications > Form Actions**.
*   Look for the **Confirmation** action (usually created by default).
*   Under **Select an Action**, ensure **Redirect to URL** is selected.
*   Enter a placeholder URL (e.g., `https://example.com`). This will be overwritten by our snippet.

### 3. Add the Code Snippet

Add the following code to your theme's `functions.php` or a code snippets plugin. This is the minimum code required to make this work. Adjust the field IDs and form ID to match your setup.

```php
/**
 * Integrate Formidable Forms with PMPro Magic Levels
 */
add_filter( 'frm_redirect_url', 'my_pmpro_magic_levels_redirect', 10, 3 );
function my_pmpro_magic_levels_redirect( $url, $form, $params ) {
	// 1. CONFIGURATION: Set your IDs here.
	$target_form_id = 123;
	$name_field_id  = 10;   // The Field ID for Level Name.
	$price_field_id = 11;   // The Field ID for Price.

	if ( (int) $form->id !== $target_form_id ) {
		return $url;
	}

	// 2. Fetch submitted data.
	$entry_id   = $params['id'];
	$level_name = FrmEntryMeta::get_meta_value( $entry_id, $name_field_id );
	$price      = FrmEntryMeta::get_meta_value( $entry_id, $price_field_id );

	// 3. Prepare the payload.
	$body = array(
		'name'           => $level_name ? $level_name : 'Standard - Level',
		'billing_amount' => $price ? $price : 29.99,
		'cycle_period'   => 'Month',
		'cycle_number'   => 1,
	);

	// 4. Call Magic Levels.
	if ( function_exists( 'pmpro_magic_levels_process' ) ) {
		$result = pmpro_magic_levels_process( $body );

		if ( $result['success'] && ! empty( $result['redirect_url'] ) ) {
			return $result['redirect_url'];
		}
	}

	// Fallback: If something failed, return the original URL (e.g., a "Thank You" page).
	return $url;
}
```

## Troubleshooting

*   **Group Format:** The level name **must** include a group (e.g., `Group - Level`). If you don't include the ` - ` separator, the process will fail.
*   **Form ID:** Ensure `$target_form_id` matches your actual form ID (e.g., `3`).
*   **Field IDs:** Verify the keys in `FrmEntryMeta::get_meta_value` (e.g., `10`, `11`) match the field IDs from your form builder.
*   **Redirect Settings:** The form **must** be set to "Redirect to URL" in settings for this filter to trigger.
*   **Debug Tip:** If the redirect isn't working, add `error_log( print_r( $result, true ) );` after the `pmpro_magic_levels_process` call to see any validation errors in your `debug.log`.
