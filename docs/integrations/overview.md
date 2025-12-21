# Form Plugin Integration Guide

This guide outlines how to integrate form plugins (Gravity Forms, Contact Form 7, Formidable, etc.) with **PMPro Magic Levels**.

## The Core Concept

To successfully integrate a form plugin, it must perform two actions:
1.  **Process Data:** Call the Magic Levels engine to find or create a level.
2.  **Redirect:** Send the user to the generated checkout URL.

## Critical Validation Rule: Group Naming

PMPro 3.x requires all levels to belong to a group. To support this, **Magic Levels** requires that the `name` field follows this format:

`GroupName - LevelName`

**Examples:**
*   `Membership - Gold` (Valid)
*   `Standard - Monthly` (Valid)
*   `Gold` (**Invalid** - creation will fail)

Ensure your form mapping or PHP snippet provides a name that includes the ` - ` (space-dash-space) separator.

---

## 1. Internal Integration (Recommended)

This method uses direct PHP calls. It is recommended for any plugin running on the same site as PMPro (e.g., Contact Form 7, Formidable, Gravity Forms).

**Why use PHP?**
*   **Instant Redirect:** You can catch the response and redirect the user immediately.
*   **Security:** No need to manage Bearer tokens or expose API endpoints.
*   **Performance:** No network latency.

### Specific Guides:
*   [Contact Form 7 Guide](contact-form-7.md)
*   [Formidable Forms Guide](formidable-forms.md)
*   [Remote Webhook Guide (External)](webhooks-and-curl.md)

---

## 2. Remote Webhook Integration (Specialized)

Use this method **only** if you are connecting from an external service (like automation tools) or if your form plugin natively supports webhook-based redirects (like **WSForm**).

*   **URL:** `https://yoursite.com/wp-json/pmpro-magic-levels/v1/process`
*   **Headers:**
    *   `Content-Type`: `application/json`
    *   `Authorization`: `Bearer YOUR_TOKEN`
*   **Critical Requirement:** Your plugin must be able to **parse the JSON response** and use the `redirect_url` key to redirect the user's browser.

### What Doesn't Work (Background Webhooks)
Standard background webhooks (like standard Gravity Webhooks or background API calls) fire in the background and ignore the response. 
*   **Result:** The level is created, but the user stays on the "Thank You" page and never pays. Use the **PHP Method** instead for these plugins.

---

## Troubleshooting "No Redirect"

If levels are created but users aren't hitting the checkout page:

1.  **Switch to PHP:** If you are using a WP plugin, switch from the Webhook UI to a code snippet.
2.  **Check Sync:** Ensure your webhook is running "Synchronously" (waiting for a response).
3.  **Manual Link:** If you must use a background webhook, you must manually provide the checkout link to the user in the success message.
