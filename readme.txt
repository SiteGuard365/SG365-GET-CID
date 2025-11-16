=== SG365 CID ===
Contributors: siteguard365
Tags: woocommerce, api, cid, license, confirmation, pidkey, cidms
Requires at least: 5.0
Tested up to: 6.6
Stable tag: 1.2.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Generate Confirmation IDs (CID) through PIDKey / CIDMS and connect every CID request to WooCommerce orders, logs and customer history.

== Description ==
SG365 CID gives WooCommerce stores a secure, logged workflow for issuing Microsoft Confirmation IDs via the PIDKey / CIDMS API. Mark any product as CID-enabled, automatically track how many CIDs each order can generate, and empower customers with self-service shortcodes.

=== Highlights ===
* Secure AJAX CID form with math captcha, rate limiting and API fallback logic.
* Product-level toggle determines how many CIDs a customer receives per quantity purchased.
* CID history shortcode for logged-in customers.
* NEW CID allowance dashboard (`[sg365_cid_limits]`) summarizes each qualifying order, remaining quota and the most recent CID issued.
* Admin tools include searchable logs, retention controls, inline CID generation and per-order counters stored as post meta.

=== Shortcodes ===
* `[sg365_cid_form]` – front-end form for customers to request a CID after providing their order ID, email and IID.
* `[sg365_cid_history]` – shows the previous 50 CID requests for the logged-in user.
* `[sg365_cid_limits]` – responsive table that lists each qualifying order, total CID allowance, remaining requests and the last CID generated (new in 1.2.0).

== Installation ==
1. Upload the `sg365-cid` folder to `/wp-content/plugins/`.
2. Activate through **Plugins → Installed Plugins**.
3. Go to **Settings → SG365 CID** and enter your API key plus optional fallback endpoints, rate limit and captcha preferences.
4. Edit a WooCommerce product and enable **"Enable CID limit for this product"**. Each quantity purchased unlocks one CID request for that order.
5. Add the `[sg365_cid_form]` shortcode to any page.
6. Optionally add `[sg365_cid_history]` or `[sg365_cid_limits]` to customer-only sections (for example, the My Account dashboard).

== Frequently Asked Questions ==
= Does this plugin email CID to customers? =
Not automatically. The CID is displayed instantly on the form and logged for reference.

= What happens if the primary API endpoint fails? =
A fallback endpoint (khoatoantin.com by default) is attempted before the request is marked as failed.

= Can customers re-use a CID without consuming the limit again? =
Yes. If a matching order/email/IID already produced a successful CID, the plugin reuses it and keeps the remaining allowance intact.

== Screenshots ==
1. Customer-facing CID request form with timer and copy-to-clipboard.
2. Math captcha for blocking automated abuse.
3. CID allowance dashboard created by `[sg365_cid_limits]`.
4. Admin logs with inline CID generation button.

== Changelog ==
= 1.2.0 =
* Added `[sg365_cid_limits]` shortcode so customers can review their CID allowance and last issued CID per order.
* New logger helpers power accurate allowance totals (allocated vs. remaining).
* Documentation refreshed with feature overview, shortcodes table and screenshots section.

= 1.0.0 =
* Initial release with PIDKey / CIDMS integration, WooCommerce hooks, logging and customer history.

== Upgrade Notice ==
= 1.2.0 =
A CID allowance dashboard is now available via `[sg365_cid_limits]`, and logging improvements were made to support the new view.
