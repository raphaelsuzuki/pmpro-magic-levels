# WSForm Integration

This guide provides step-by-step instructions for integrating **Paid Memberships Pro Magic Levels** with **WSForm** using webhooks.

## Webhook Setup in WSForm

1.  In WSForm, go to your **Form Settings**.
2.  Click on the **Actions** tab.
3.  Add a new action and select **Webhook**.
4.  Configure the webhook settings:
    *   **URL**: `https://yoursite.com/wp-json/pmpro-magic-levels/v1/create-level`
    *   **Method**: `POST`
    *   **Content-Type**: `application/json`
    *   **Headers**: Add the following header for authentication:
        *   **Name**: `Authorization`
        *   **Value**: `Bearer YOUR_TOKEN` (Get this from *PMPro > Magic Levels* admin page)

### Body Configuration

In the "Body" or "Field Mapping" section (use **Custom Mapping** for best results), construct a JSON object with the following structure:

```json
{
  "name": "#field(1)",
  "billing_amount": "#field(2)",
  "cycle_period": "Month",
  "cycle_number": 1
}
```

*Replace `#field(1)`, `#field(2)` with your actual field IDs.*

### strict Field Types (Important)

When configuring the Custom Mapping, ensure you set the correct **Type** for each field:

*   **name** → `String`
*   **billing_amount** → `Float` (Critical!)
*   **cycle_period** → `String`
*   **cycle_number** → `Integer`

---

## Handling Currency Symbols (Crucial)

If your price field (e.g., `#field(2)`) is a "Price" or "Cart Total" field, it likely contains currency symbols (e.g., `$`, `¥`, `€`) formatted as a string. sending this directly as a `Float` often results in `0`.

**The Fix:**

1.  Add a **Hidden Field** to your form (e.g., Label: "Clean Price").
2.  Set its **Default Value** to a calculation: `#calc(#field(2))`
    *   *This forces WSForm to strip symbols and calculate the raw numeric value.*
3.  Use this **Hidden Field's ID** in your Webhook mapping for `billing_amount`.
    *   e.g., Key: `billing_amount`, Value: `#field(99)`, Type: `Float`.

---

## Redirect Setup

To redirect the user to checkout after a successful submission:

1.  Add a **Redirect** action *after* the Webhook action.
2.  Set the **URL** to: `#webhook_response(redirect_url)`
3.  In the **Webhook Action > Response Settings**, ensure **"Process Response"** is checked.

---

## Example Webhook Bodies

### Basic Monthly Plan
```json
{
  "name": "#field(1)",
  "billing_amount": "#field(2)",
  "cycle_period": "Month",
  "cycle_number": 1
}
```

### Full Example with All Options
```json
{
  "name": "#field(1)",
  "billing_amount": "#field(2)",
  "cycle_period": "#field(3)",
  "cycle_number": 1,
  "initial_payment": "#field(4)",
  "trial_amount": "#field(5)",
  "trial_limit": "#field(6)",
  "billing_limit": "#field(7)",
  "expiration_number": "#field(8)",
  "expiration_period": "#field(9)"
}
```

---

## Troubleshooting

### 1. "400 Bad Request"
*   **Cause**: Validation failed.
*   **Fix**:
    *   Check that `billing_amount` is mapped as a **Float**.
    *   Verify `name` is not empty and follows strict format if required (e.g. `Group - Level`).
    *   Ensure `cycle_period` is valid (Day, Week, Month, Year).

### 2. "401 Unauthorized"
*   **Cause**: Invalid token.
*   **Fix**: Check `Authorization: Bearer YOUR_TOKEN` header and ensure the webhook is enabled in PMPro settings.

### 3. Redirect not working (Redirects to root or text url)
*   **Cause**: WSForm not parsing response.
*   **Fix**: Check "Process Response" in Webhook settings. Use `#webhook_response(redirect_url)`.

### 4. Price is 0 or ignored
*   **Cause**: Currency symbols in input.
*   **Fix**: Use the **Hidden Field** technique described above with `#calc(#field(ID))`.
