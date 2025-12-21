# Contact Form 7 Integration

Contact Form 7 (CF7) does not natively support redirection or advanced API handling. However, effective from 2024, the best way to integrate it with PMPro Magic Levels is by using a custom code snippet that hooks into the submission process.

## The Strategy

We will use the `wpcf7_before_send_mail` action to:
1.  Intercept the form data before email is sent.
2.  Send the data to PMPro Magic Levels internally.
3.  Pass the `redirect_url` back to the frontend.
4.  Use a small Javascript snippet to handle the actual redirect.

## Step-by-Step Guide

### 1. Create Your Form
Create a standard CF7 form. Note the field names (e.g., `your-name`, `your-email`, `your-price`).

### 2. Add the PHP Code
Add this to your theme's `functions.php` or a snippet plugin.

```php
/**
 * Integrate Contact Form 7 with PMPro Magic Levels
 */
add_action( 'wpcf7_before_send_mail', 'my_pmpro_cf7_integration' );

function my_pmpro_cf7_integration( $contact_form ) {
	// 1. Check if this is the correct form (Replace 123 with your Form ID).
	$submission = WPCF7_Submission::get_instance();
	if ( ! $submission || $contact_form->id() != 123 ) {
		return;
	}

	// 2. Get submitted data.
	$posted_data = $submission->get_posted_data();

	// 3. Prepare payload for Magic Levels.
	// Map your CF7 field names to the required keys here.
	$level_data = array(
		'name'           => $posted_data['your-level-name'], // e.g., "Group - Level".
		'billing_amount' => $posted_data['your-price'],      // e.g., "19.99".
		'cycle_period'   => 'Month',
		'cycle_number'   => 1,
	);

	// 4. Call Magic Levels internally.
	if ( function_exists( 'pmpro_magic_levels_process' ) ) {
		$result = pmpro_magic_levels_process( $level_data );

		// 5. Store the redirect URL in a special property to pass to frontend.
		if ( $result['success'] && ! empty( $result['redirect_url'] ) ) {
			// CF7 doesn't have a direct "set_redirect" method, so we pass it 
			// via the API response properties.
			$submission->add_result_props(
				array(
					'magic_redirect_url' => $result['redirect_url'],
				)
			);
		}
	}
}
```

### 3. Add the Frontend Javascript
Add this Javascript to your site (e.g., in a "Custom Javascript" plugin, or directly in the form description wrapped in `<script>` tags).

```javascript
document.addEventListener( 'wpcf7mailsent', function( event ) {
    // Check if our custom redirect URL exists in the response
    if ( event.detail.apiResponse.magic_redirect_url ) {
        // Redirect the user
        window.location.href = event.detail.apiResponse.magic_redirect_url;
    }
}, false );
```

## How it Works
1.  **PHP Integration:** When the form is submitted, the PHP code processes the level creation internally. It attaches the resulting `redirect_url` to the JSON response that CF7 sends back to the browser.
2.  **JS Redirect:** The `wpcf7mailsent` event listener catches the success response. It sees the custom `magic_redirect_url` property and redirects the browser immediately.

## Troubleshooting
*   **Group Format:** The level name **must** include a group (e.g., `Group - Level`). If the submitted name does not include the ` - ` separator, validation will fail.
*   **Form ID:** Ensure the ID in the PHP `if ($contact_form->id() != 123)` matches your actual form ID.
*   **Field Names:** Ensure `$posted_data['your-field-name']` matches the names in your CF7 form template.
*   **Javascript Placement:** The JS must be loaded on the page where the form exists.
*   **Debug Tip:** Check your `debug.log` if the redirect doesn't happen.
