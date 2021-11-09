=== WooCommerce YouCan Pay Payment Gateway ===
Contributors: woocommerce, automattic, royho, akeda, mattyza, bor0, woothemes
Tags: credit card, youcanpay, apple pay, payment request, google pay, sepa, sofort, bancontact, standalone, giropay, ideal, p24, woocommerce, automattic
Requires at least: 4.6
Tested up to: 5.8
Requires PHP: 5.6
Stable tag: 5.7.0
License: GPLv3
License URI: https://www.gnu.org/licenses/gpl-3.0.html
Attributions: thorsten-youcanpay

Take credit card payments on your store using YouCan Pay.

== Description ==

Accept Visa, MasterCard, American Express, Discover, JCB, Diners Club, SEPA, Sofort, iDeal, Giropay, YouCan Pay Standalone, and more directly on your store with the YouCan Pay payment gateway for WooCommerce, including Apple Pay, Google Pay, and Microsoft Pay for mobile and desktop.

= Take Credit card payments easily and directly on your store =

The YouCan Pay plugin extends WooCommerce allowing you to take payments directly on your store via YouCan Pay’s API.

YouCan Pay is available for Store Owners and Merchants in:

* Australia
* Austria
* Belgium
* Bulgaria
* Canada
* Cyprus
* Czech Republic
* Denmark
* Estonia
* Finland
* France
* Germany
* Greece
* Hong Kong
* Ireland
* Italy
* Japan
* Latvia
* Lithuania
* Luxembourg
* Malaysia
* Malta
* Mexico
* Netherlands
* New Zealand
* Norway
* Poland
* Portugal
* Puerto Rico
* Singapore
* Slovakia
* Slovenia
* Spain
* Sweden
* Switzerland
* United Kingdom
* United States
* [with more being added](https://youcanpay.com/global)

YouCan Pay is a simple way to accept payments online. With YouCan Pay you can accept Visa, MasterCard, American Express, Discover, JCB, and Diners Club cards, even Bitcoin, directly on your store.

= Why choose YouCan Pay? =

YouCan Pay has no setup fees, no monthly fees, no hidden costs: you only get charged when you earn money! Earnings are transferred to your bank account on a 7-day rolling basis.

YouCan Pay also supports the [WooCommerce Subscriptions extension](https://woocommerce.com/products/woocommerce-subscriptions/) and re-using cards. When a customer pays, they are set up in YouCan Pay as a customer. If they create another order, they can check out using the same card. A massive timesaver for returning customers.

= Apple Pay Support =

WooCommerce YouCan Pay includes [Apple Pay](https://woocommerce.com/apple-pay) support, which means customers can pay using payment details associated with their Apple ID. Checkout is now just an authorization (Touch ID or Face ID) away on both mobile and desktop. Only supports simple, variable, and Subscription products for now. More support to come.

= Web Payments API Support =

WooCommerce YouCan Pay includes [Web Payments API](https://www.w3.org/TR/payment-request/) support, which means customers can pay using payment details associated to their mobile devices, in browsers supporting the Web Payments API (Chrome for Android, amongst others). Checkout is now just a few taps away on mobile. Only supports simple, variable, and Subscription products for now. More support to come.

== Installation ==
You can download an [older version of this gateway for older versions of WooCommerce from here](https://wordpress.org/plugins/woocommerce-gateway-youcanpay/developers/).

Please note, v4 of this gateway requires WooCommerce 3.0 and above.

= Automatic installation =

Automatic installation is the easiest option as WordPress handles the file transfers itself and you don’t need to leave your web browser. To do an automatic install of the WooCommerce YouCan Pay plugin, log in to your WordPress dashboard, navigate to the Plugins menu and click Add New.

In the search field type “WooCommerce YouCan Pay Payment Gateway” and click Search Plugins. Once you’ve found our plugin you can view details about it such as the point release, rating, and description. Most importantly, of course, you can install it by simply clicking "Install Now", then "Activate".

= Manual installation =

The manual installation method involves downloading our plugin and uploading it to your web server via your favorite FTP application. The WordPress codex contains [instructions on how to do this here](http://codex.wordpress.org/Managing_Plugins#Manual_Plugin_Installation).

= Updating =

Automatic updates should work like a charm; as always though, ensure you backup your site just in case.

== Frequently Asked Questions ==

= Does this support recurring payments, like for subscriptions? =

Yes!

= Does this require an SSL certificate? =

Yes! In Live Mode, an SSL certificate must be installed on your site to use YouCanPay. In addition to SSL encryption, YouCanPay provides an extra JavaScript method to secure card data using [YouCanPay Elements](https://youcanpay.com/elements).

= Does this support both production mode and sandbox mode for testing? =

Yes, it does - production and Test (sandbox) mode is driven by the API keys you use with a checkbox in the admin settings to toggle between both.

= Where can I find documentation? =

For help setting up and configuring, please refer to our [documentation](https://docs.woocommerce.com/document/youcanpay/).

= Where can I get support or talk to other users? =

If you get stuck, you can ask for help in the Plugin Forum.

== Screenshots ==

1. The YouCanPay payment gateway settings screen used to configure the main YouCanPay gateway.
2. Offer a range of payment methods such as local and alternative payment methods.
3. Pay with a saved payment method, a new card, and allow customers to save the payment card for future transactions.
4. Apple Pay and other Payment Request buttons can be used on the Product Page and Checkout for express checkout.

== Changelog ==

= 5.7.0 - 2021-10-20 =
* Fix - Enable use of saved payment methods converted to SEPA payments.
* Tweak - "Save payment information" checkbox now has better alignment in store checkout.
* Tweak - Error notices at checkout now have more consistent design.

[See changelog for all versions](https://raw.githubusercontent.com/woocommerce/woocommerce-gateway-youcanpay/trunk/changelog.txt).
