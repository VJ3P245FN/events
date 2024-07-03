=== Clover Payments for WooCommerce===
Contributors: cloverecommerce
Tags: clover,clover payments
Plugin Name: Clover Payments for WooCommerce
Plugin URI: https://wordpress.org/plugins/clover-payments-for-woocommerce/
Description: Accepting payments in Woo Commerce using Clover eCommerce.
Version: 1.0.11
Requires at least: 5.9.3
Requires PHP: 7.4 or Higher
Author: Clover eCommerce
Author URI: https://www.clover.com
License: Clear BSD
License URI : https://directory.fsf.org/wiki/License:BSD-3-Clause-Clear
Text Domain: woo-clv-payments
Domain Path: /i18n/languages/
Tested up to: 6.4
Stable tag: 1.0.11

The Clover Payments plugin enables merchants that use Woocommerce to process online card payments using Clover.

== Description ==

The WordPress plugin from Clover allows a merchant using a WordPress based app like Woocommerce
to securely collect card information from buyers and process the payment using their Clover merchant account.
The Payment extension uses an iframe to collect card information. An associated JavaScript tokenizes the card
details by directly communicating with a Clover’s servers. The payment is processed using this token and the
card details are never  saved on WordPress or the merchant’s servers. Therefore, this plug-in does not contribute
to the PCI scope of a merchant’s eCommerce site. The plug-in is free for merchants to use and transactions processed
using this plug-in will be charged under the merchant’s account at Clover.

== Privacy Statement ==

[Clover privacy statement can be found here](https://www.clover.com/privacy-policy).

== Feature list ==

* Authorize only
* Capture
* Charge (Authorize and Capture)
* Refund
* Void
* PCI Compliance through iframe
* Multi-Lingual Support for Canadian French
* Payment option with the ‘Pay Now’ Link sent via email

= Prerequisites =

Clover Merchant or Sandbox account
If you are currently not a Clover merchant or do not have a Clover Sandbox account, you will need to sign up for one at Clover.com
prior to using the Payment extension. You can then use this extension to send transactions to your Sandbox or Production accounts.
We recommend that you test your plugin integration with your Clover sandbox account prior to sending transactions to your production/live
account(s).

Direct all questions to wordpress@clover.com

[Clover Detail Documentation](https://docs.clover.com/docs/woocommerce)

== Frequently Asked Questions ==

= Do I need a Clover POS device to use this plug in =
No, all you need is a Clover Sandbox or Clover Production Account.

== Screenshots ==
1. Clover Networks Logo Screen
2. WordPress Plugin Page with Clover Payments for WooCommerce
3. Enable Clover Payments in WooCommerce Settings
4. Plugin Settings Screen - US English
5. Plugin Settings Screen - Canadian French
6. Clover Account Dashboard
7. Clover eCommerce API Credentials
8. Clover Payment Shopping Check with Cart Screen - US English
9. Clover Payment Shopping Cart Screen - Canadian French
10. Clover Payment Admin Order details - card details
11. Clover Payment My account Order details - card details
12. Clover Payment card details on invoice page

== Changelog ==
= 1.0.11 = Patch Release

* Tested compatibility with WordPress 6.4
* Added support for reCAPTCHA
* Customer IP address is now passed in transactions

= 1.0.10 = Patch release

* Tested compatibility with WordPress 6.3

= 1.0.9 = Patch Release

* Logs Cleanup

= 1.0.8 = Patch Release

* Tested compatibility with WordPress 6.2

= 1.0.7 = Patch Release

* Display Customer Information with Orders at Clover Dashboard

= 1.0.6 = Patch Release

* Fixed Token Reuse issue

= 1.0.5 = Patch Release

* Display Error Message Updated

= 1.0.4 = Patch Release

* Added feature to make payment with the ‘Pay Now’ Link sent via email

= 1.0.3 = Patch Release

* Added feature to show payment card details with order details

= 1.0.2 = Patch Release

* Bug Fix for Check out with Multiple Payment option selection

= 1.0.1 = Patch Release

* Added bulk capture failure notifications
* Added best practices for concurrent rate limiting request handling

= 1.0.0 = Initial Release

* Authorize only
* Capture
* Charge (Authorize and Capture)
* Refund
* Void
* PCI Compliance through iframe
* Multi-Lingual Support for Canadian French

== Installation ==

MINIMUM REQUIREMENTS
PHP version 7.4 or greater
WordPress 5.9.3 or greater
WooCommerce 6.4.1 or greater
Clover Sandbox or Clover Production Account

Ref. the standard [WordPress plugin installation procedure](https://wordpress.org/support/article/managing-plugins/) for details.

Quick Steps to Install "Clover Payments for Woocommerce" Plugin
1. Install this Plugin > From Wordpress Plugin > search "Clover Payments" or "Clover Payments for Woocommerce"
2. Go to "Clover Payments for WooCommerce" Plugin > Activate
2. Go to WooCommerce > Plugin Settings > Payments > "Clover Payments" > Manage > Enable
3. Set "Clover Payments" Plugin - Environment We provide the option for Merchants and Developers to test their integrations against their sandbox accounts prior,
   to going live. Select “Production” when you want to send transactions to your production environment
4. Set "Clover Payments" Plugin - Keys Please visit clover merchant portal to get private and public key
5. Set "Clover Payments" Plugin - Payment Action and Save Changes
6. You’re done, the active payment methods should be visible in the checkout of your website
