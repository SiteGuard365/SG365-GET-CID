# SG365 CID – WooCommerce Confirmation ID Manager

Generate and track Microsoft Confirmation IDs (CID) directly from WooCommerce orders.
The SG365 CID plugin talks to the PIDKey / CIDMS API, validates every request, and
records a complete audit trail so store owners always know when, how, and by whom a
CID was issued.

---

## ✨ Highlights
- **PIDKey / CIDMS integration** with automatic fallback endpoint and graceful error handling.
- **WooCommerce product gating** – only flagged products grant CID allowances that match the quantity purchased.
- **Self-service shortcode** (`[sg365_cid_form]`) that verifies Order ID, email, IID, optional math captcha, and rate limits requests per order/IP.
- **CID history shortcode** (`[sg365_cid_history]`) for logged-in customers.
- **Smart CID reuse** – previously generated CIDs are returned instantly without consuming allowances again.
- **Admin workflows**
  - Dedicated settings screen under *Settings → SG365 CID*.
  - Log viewer with search, pagination, bulk deletion, and mobile-friendly layout.
  - Order-side meta box that displays remaining allowance, the latest CIDs, and lets admins manually request a CID.
- **Robust logging** – every request (success or error) is stored with order, user, IID, CID, API response, and IP address.
- **Security tools** – nonce validation, math captcha, guest restriction, rate limiting, and log retention scheduling.

---

## Requirements
- WordPress 5.0+
- WooCommerce (for order data, product meta, and shortcodes)
- PHP 7.4+
- Valid PIDKey / CIDMS API key

---

## Installation
1. Upload the `sg365-cid` folder to `/wp-content/plugins/` or install the ZIP through the Plugins screen.
2. Activate the plugin via **Plugins → SG365 CID**.
3. Visit **Settings → SG365 CID** to configure the API key and behaviour.
4. Edit the WooCommerce products that should grant CID access and enable **“Enable CID limit for this product.”**
5. Place the `[sg365_cid_form]` shortcode on any page (e.g., a “Get CID” page).
6. Optionally place `[sg365_cid_history]` on a protected page so logged-in customers can review their requests.

---

## Configuration Reference
| Setting | Description |
| ------- | ----------- |
| **API Key** | PIDKey / CIDMS API key used for every request. |
| **Primary / Fallback API Endpoint** | Base URLs that receive the IID. The fallback is used when the primary errors or times out. |
| **API Timeout** | Maximum seconds to wait for an endpoint before treating the request as failed. |
| **Rate limit (seconds)** | Minimum delay between requests per order + IP combo. Applied to both customers and admins. |
| **Allow guests** | Permit requests from non-logged-in users. When disabled, the shortcode is gated behind login. |
| **Logs retention (days)** | Automatic cleanup schedule for log rows (0 = keep forever). |
| **Enable Math Captcha** | Adds a lightweight math challenge to the customer form to deter bots. |

Changes are stored in the `sg365_cid_options` option and consumed by the API, frontend, and logger components.

---

## WooCommerce Behaviour
- When an order transitions to **processing** or **completed**, the plugin counts the quantity of CID-enabled products and saves the allowance to `_sg365_cid_remaining`.
- Customers (or admins) consume one allowance per successful CID request.
- The thank-you and view-order screens display a friendly banner with the remaining allowance.
- From the order edit screen, administrators can view the 10 most recent CID responses and manually request another CID using any IID.

---

## Shortcodes
### `[sg365_cid_form]`
Interactive AJAX form that collects the order number, billing email, IID, and optional captcha answer. On submit it:
1. Validates login/guest rules, order status, rate limits, and captcha (if enabled).
2. Checks for an existing successful CID for the same order/email/IID and reuses it when available.
3. Calls the PIDKey / CIDMS API, falling back when required, and normalises any CID response format.
4. Logs both success and failure, updates the remaining allowance, and returns a copy-able CID string.

### `[sg365_cid_history]`
Displays the 50 most recent CID log entries for the current user (date, order, IID preview, CID, and status). Requires login.

---

## Logging & Monitoring
- Log entries live in the `{$wpdb->prefix}sg365_cid_logs` table created during activation.
- Each row stores timestamps, order/user/email references, product IDs, IID, CID, raw API payload, status, and IP.
- Admins can filter logs by order ID or email from **SG365 CID → Logs** and bulk-delete selected rows. A daily cron (`sg365_cid_cron_cleanup`) enforces retention.

---

## Security Notes
- Every AJAX request verifies a nonce (`sg365_cid_request`).
- Captcha answers are held in a short-lived transient and invalidated after use.
- Rate limiting uses WordPress transients keyed by order and IP. Admin AJAX actions share the same protection.
- User emails must match the billing email stored on the order before any API call occurs.

---

## Troubleshooting
| Symptom | Likely Cause | Fix |
| --- | --- | --- |
| “API key not set” error | The PIDKey field is empty | Visit **Settings → SG365 CID** and paste the key. |
| “Order not found” | Wrong order ID or the order belongs to another store | Double-check the WooCommerce order number and site. |
| “Email does not match order billing email” | Typo or a different account | Use the billing email associated with the order. |
| “Too many requests” | Rate limit triggered | Wait the configured number of seconds and try again. |
| “No CID found in response” | PIDKey/CIDMS did not return a CID block | Verify the IID or contact support—raw responses are saved in the logs. |

---

## Changelog
### 1.1.0
- Added math captcha option, CID reuse logic, mobile-friendly log table, admin log search, and guest usage improvements.
- Improved logging payloads, rate limiting, and PIDKey error messages.

### 1.0.0
- Initial release with modular architecture, WooCommerce product integration, shortcode form, log table, and PIDKey / CIDMS connectivity.

---

## License
GPL-2.0-or-later. See [LICENSE](LICENSE).

---

## Need help?
Reach out via [siteguard365.com](https://siteguard365.com/) with your order ID, IID, and any relevant log entries for the fastest turnaround.
