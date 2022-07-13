=== YouCan Pay ===
Plugin Name:       YouCan Pay
Plugin URI:        https://youcanpay.com
Contributors:      medoussama
Tags:              credit card, youcanpay, payment request, standalone, woocommerce
Author:            YouCan Pay
Requires at least: 4.6
Tested up to:      5.8
Requires PHP:      7.1
Stable tag:        2.0.10
Version:           2.0.10
License:           GPLv3
License URI:       https://www.gnu.org/licenses/gpl-3.0.html

Take credit card payments on your store using YouCan Pay.

== Description ==

Accept Visa, MasterCard, American Express, and more directly on your store with the YouCan Pay.

= Take Credit card payments easily and directly on your store =

The YouCan Pay plugin extends WooCommerce allowing you to take payments directly on your store via YouCan Pay’s API.

YouCan Pay is a simple way to accept payments online. With YouCan Pay you can accept Visa, MasterCard, American Express directly on your store.

= Why choose YouCan Pay? =

YouCan Pay has no setup fees, no monthly fees, no hidden costs: you only get charged when you earn money! Earnings are transferred to your bank account on a 7-day rolling basis.

= Web Payments API Support =

YouCan Pay includes [Web Payments API](https://www.w3.org/TR/payment-request/) support, which means customers can pay using payment details associated to their mobile devices, in browsers supporting the Web Payments API (Chrome for Android, amongst others). Checkout is now just a few taps away on mobile. Only supports simple, variable, and Subscription products for now. More support to come.

== Installation ==
You can download an [older version of this gateway for older versions of WooCommerce from here](https://wordpress.org/plugins/youcan-pay-for-woocommerce/#developers).

Please note, version 2.0 of this gateway requires WooCommerce 4.6 and above.

= Automatic installation =

Automatic installation is the easiest option as WordPress handles the file transfers itself, and you don’t need to leave your web browser. To do an automatic installation of the YouCan Pay plugin, log in to your WordPress dashboard, navigate to the Plugins menu and click Add New.

In the search field type “YouCan Pay” and click Search Plugins. Once you’ve found our plugin you can view details about it such as the point release, rating, and description. Most importantly, of course, you can install it by simply clicking "Install Now", then "Activate".

= Manual installation =

The manual installation method involves downloading our plugin and uploading it to your web server via your favorite FTP application. The WordPress codex contains [instructions on how to do this here](http://codex.wordpress.org/Managing_Plugins#Manual_Plugin_Installation).

= Updating =

Automatic updates should work like a charm; as always though, ensure you back up your site just in case.

== Frequently Asked Questions ==

= Does this support recurring payments, like for subscriptions? =

Yes!

= Does this require an SSL certificate? =

Yes! In Production Mode, an SSL certificate must be installed on your site to use YouCan Pay. In addition to SSL encryption, YouCan Pay provides an extra JavaScript method to secure card data using [YouCan Pay Elements](https://youcanpay.com/docs).

= Does this support both production mode and sandbox mode for testing? =

Yes, it does - production and Test (sandbox) mode is driven by the API keys you use with a checkbox in the admin settings to toggle between both.

= Where can I find documentation? =

For help with installation and configuration, please refer to our [documentation](https://youcanpay.com/docs).

= Where can I get support or talk to other users? =

If you get stuck, you can ask for help in the Plugin Forum.

== Screenshots ==

1. The YouCan Pay settings screen used to configure the main YouCan Pay.

== Changelog ==
*** Changelog ***
= 2.0.10 - 2022-07-13 =
* Fixed payment response on same payment page
* Fixed translations
* Fixed payment form display

For a complete list of changelog, please refer to the "Changelog" section of https://wordpress.org/plugins/youcan-pay-for-woocommerce/#developers
