# SG365-GET-CID
Generate Confirmation IDs (CID) via PIDKey / CIDMS API and integrate with WooCommerce orders.

=== SG365 CID ===
Contributors: siteguard365
Tags: wooCommerce, api, cid, license, confirmation
Requires at least: 5.0
Tested up to: 6.6
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Generate Confirmation IDs (CID) through PIDKey / CIDMS API and integrate with WooCommerce orders.

== Description ==
SG365 CID integrates with PIDKey / CIDMS API to generate Confirmation IDs (CID) for customers who purchased specific WooCommerce products. Admin configures API key, products grant CID allowances. Customers use a shortcode form to request their CID by providing Order ID, Email and Installation ID (IID).

== Installation ==
1. Upload the `sg365-cid` folder to the `/wp-content/plugins/` directory.
2. Activate the plugin through the 'Plugins' screen in WordPress.
3. Go to Settings → SG365 CID and set your API Key (PIDKey).
4. Edit WooCommerce product(s) and enable "Enable CID limit for this product".
5. Add shortcode `[sg365_cid_form]` to a page to let users request CIDs.
6. Optionally add `[sg365_cid_history]` for users to view history.

== Frequently Asked Questions ==
= Does this plugin email CID to customers? =
No — per configuration, email notifications are not sent automatically.

= What if the API times out? =
Plugin will attempt a fallback API endpoint (khoatoantin.com) if primary fails.

== Changelog ==
= 1.0.0 =
* Initial release - modular plugin with admin settings, product integration, shortcode, logs, and secure API integration.

== Upgrade Notice ==
N/A

Version 1.1.0 - added captcha, reuse CID, admin logs search, mobile-friendly logs, guest handling improvements.


