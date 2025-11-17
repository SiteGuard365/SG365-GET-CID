# SG365-GET-CID
Generate Confirmation IDs (CID) via PIDKey / CIDMS API and integrate with WooCommerce orders.

=== SG365 CID ===
Contributors: siteguard365
Tags: wooCommerce, api, cid, license, confirmation
Requires at least: 5.0
Tested up to: 6.6
Stable tag: 1.3.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Generate Confirmation IDs (CID) through PIDKey / CIDMS API and integrate with WooCommerce orders.

## Features

* **Self-serve CID form** – Customers can safely request their Confirmation ID using `[sg365_cid_form]` with nonce protection, rate limiting, math captcha (optional) and automatic API fallback handling.
* **WooCommerce product link** – Flag specific products as CID-enabled so each order automatically receives an allowance based on the quantity that was purchased.
* **CID history** – Logged-in customers can review the last 50 Confirmation IDs they generated via `[sg365_cid_history]`.
* **CID allowance dashboard (new)** – `[sg365_cid_limits]` surfaces every qualifying order, its remaining CID allowance and the last CID that was issued so customers know exactly what they can still redeem.
* **Admin experience** – Full settings screen, inline CID generator on orders, searchable log viewer with retention controls, WooCommerce order detail summaries and per-order CID counters stored in post meta.
* **Premium dashboard + license manager** – Dedicated Dashboard / Logs / Settings / License menus highlight usage stats, enforce license status, hide API secrets and add reminders, including a Thank You page CTA that sends users directly to the Get CID page you configure.
* **License guard (new)** – Product-ID aware license verification masks activated keys, syncs with the Site Guard 365 API at least weekly, and immediately disables premium/business modules when the status changes to pending, suspended, expired or deleted.
* **Business tokens** – Business license holders can create reusable CID tokens with limits, expiry dates and suspension controls, allow customers to enter those tokens in lieu of an order number, and monitor token usage inside a management table.
* **Product variation support** – Both simple products and variations can unlock CID allowances so every qualifying item automatically grants the right number of CID requests.

## Shortcodes

| Shortcode | Description |
| --- | --- |
| `[sg365_cid_form]` | AJAX form customers use to request a Confirmation ID (CID). |
| `[sg365_cid_history]` | Logged-in users can see up to 50 of their previous CID requests. |
| `[sg365_cid_limits]` | NEW: CID allowance dashboard that lists recent orders, remaining quota and last CID issued. |

== Description ==
SG365 CID integrates with PIDKey / CIDMS API to generate Confirmation IDs (CID) for customers who purchased specific WooCommerce products. Admin configures API key, products grant CID allowances. Customers use a shortcode form to request their CID by providing Order ID, Email and Installation ID (IID).

== Installation ==
1. Upload the `sg365-cid` folder to the `/wp-content/plugins/` directory.
2. Activate the plugin through the 'Plugins' screen in WordPress.
3. Go to Settings → SG365 CID and set your API Key (PIDKey).
4. Edit WooCommerce product(s) and enable "Enable CID limit for this product".
5. Add shortcode `[sg365_cid_form]` to a page to let users request CIDs.
6. Optionally add `[sg365_cid_history]` for users to view history.
7. Optionally add `[sg365_cid_limits]` to give customers visibility into their CID allowance per order.

== Frequently Asked Questions ==
= Does this plugin email CID to customers? =
No — per configuration, email notifications are not sent automatically.

= What if the API times out? =
Plugin will attempt a fallback API endpoint (khoatoantin.com) if primary fails.

== Changelog ==
= 1.3.1 =
* Updated the license manager to send the SG365 CID product ID with every verification, mask activated keys, display plan/status/expiry guidance, and automatically re-check the key weekly so expired, suspended or domain-reset licenses disable paid features immediately.

= 1.3.0 =
* Introduced the SG365 CID dashboard menu, license screen and premium-only order detail summaries, plus an improved thank you message that links customers to your configured Get CID page.
* Added product variation toggles so configurable products can grant CID allowances and ensured order allowances are recalculated automatically.
* Rolled out Business token management (create tokens with limits + expiry, suspend/delete, use tokens instead of order IDs on the shortcode form, and expose allowances in `[sg365_cid_limits]`).
* Enhanced logs (20 per page, full IID view, bulk delete by date), captcha variety, and token-aware rate limiting / logging.

= 1.2.0 =
* Added `[sg365_cid_limits]` shortcode so customers can review total vs. remaining CID allowance for their recent orders.
* Surfaced last generated CID + timestamp in the allowance table for quick reference.
* Improved documentation with a dedicated features and shortcodes section.

= 1.0.0 =
* Initial release - modular plugin with admin settings, product integration, shortcode, logs, and secure API integration.

== Upgrade Notice ==
N/A

Version 1.3.0 - introduces the admin dashboard, license manager, improved thank-you CTA, variation-aware allowances and business token automation.
