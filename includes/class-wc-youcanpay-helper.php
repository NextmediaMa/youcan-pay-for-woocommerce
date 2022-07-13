<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Provides static methods as helpers.
 */
class WC_YouCanPay_Helper
{

    /**
     * Get YouCan Pay amount to pay
     *
     * @param float $total Amount due.
     * @param string $currency Accepted currency.
     *
     * @return float|int
     */
    public static function get_youcanpay_amount($total, $currency = '')
    {
        if (!$currency) {
            $currency = get_woocommerce_currency();
        }

        if (in_array(strtolower($currency), self::no_decimal_currencies())) {
            return absint($total);
        } else {
            $divider_value = WC_YouCanPay_Currencies::get_divider_value($currency);

            return absint(wc_format_decimal(((float)$total * $divider_value), wc_get_price_decimals())); // In cents.
        }
    }

    /**
     * List of currencies that has no decimals
     *
     * @return array $currencies
     */
    public static function no_decimal_currencies()
    {
        return [
            'bif', // Burundian Franc
            'clp', // Chilean Peso
            'djf', // Djiboutian Franc
            'gnf', // Guinean Franc
            'jpy', // Japanese Yen
            'kmf', // Comorian Franc
            'krw', // South Korean Won
            'mga', // Malagasy Ariary
            'pyg', // Paraguayan Guaraní
            'rwf', // Rwandan Franc
            'ugx', // Ugandan Shilling
            'vnd', // Vietnamese Đồng
            'vuv', // Vanuatu Vatu
            'xaf', // Central African Cfa Franc
            'xof', // West African Cfa Franc
            'xpf', // Cfp Franc
        ];
    }

    /**
     * Checks YouCan Pay minimum order value authorized per currency
     */
    public static function get_minimum_amount()
    {
        // Check order amount
        switch (get_woocommerce_currency()) {
            case 'USD':
            case 'CAD':
            case 'EUR':
            case 'CHF':
            case 'AUD':
            case 'SGD':
                $minimum_amount = 50;
                break;
            case 'GBP':
                $minimum_amount = 30;
                break;
            case 'DKK':
                $minimum_amount = 250;
                break;
            case 'NOK':
            case 'SEK':
                $minimum_amount = 300;
                break;
            case 'JPY':
                $minimum_amount = 5000;
                break;
            case 'MXN':
                $minimum_amount = 1000;
                break;
            case 'HKD':
                $minimum_amount = 400;
                break;
            default:
                $minimum_amount = 50;
                break;
        }

        return $minimum_amount;
    }

    /**
     * Gets all the saved setting options from a specific method.
     * If specific setting is passed, only return that.
     *
     * @param string $method The payment method to get the settings from.
     * @param string $setting The name of the setting to get.
     */
    public static function get_settings($method = null, $setting = null)
    {
        $all_settings = (null === $method) ?
            get_option('woocommerce_youcanpay_settings', []) :
            get_option('woocommerce_youcanpay_' . $method . '_settings', []);

        if (null === $setting) {
            return $all_settings;
        }

        return $all_settings[$setting] ?? '';
    }

    /**
     * Checks if WC version is less than passed in version.
     *
     * @param string $version Version to check against.
     *
     * @return bool
     */
    public static function is_wc_lt($version)
    {
        return version_compare(WC_VERSION, $version, '<');
    }

    /**
     * Gets the webhook URL for YouCan Pay triggers. Used mainly for
     * asyncronous redirect payment methods in which statuses are
     * not immediately chargeable.
     *
     * @return string
     */
    public static function get_webhook_url()
    {
        return add_query_arg('wc-api', 'wc_youcanpay', trailingslashit(get_home_url()));
    }

    /**
     * Check WooCommerce locale if is supported by ycpay.js.
     *
     * @param string $wc_locale The locale to convert.
     *
     * @return string Closest locale supported by YouCan Pay ('en' if NONE).
     */
    public static function get_supported_local($wc_locale)
    {
        $supported = [
            'ar',     // Arabic.
            'en',     // English.
            'fr',     // French (France).
        ];

        // Get only language without region: en_US > en
        $wc_locale = substr($wc_locale, 0, 2);

        if (in_array($wc_locale, $supported, true)) {
            return $wc_locale;
        }

        return 'en';
    }

    /**
     * @return string
     */
    public static function get_ajax_checkout_url()
    {
        return get_site_url() . '?wc-ajax=checkout';
    }

    /**
     * Checks if this page is a cart or checkout page.
     *
     * @return boolean
     */
    public static function has_cart_or_checkout_on_current_page()
    {
        return is_cart() || is_checkout();
    }

    /**
     * Checks if paying from order.
     *
     * @return boolean
     */
    public static function is_paying_from_order()
    {
        return (array_key_exists('pay_for_order', $_GET) && array_key_exists('key', $_GET));
    }

    /**
     * Return true if the current_tab and current_section match the ones we want to check against.
     *
     * @param string $tab
     * @param string $section
     *
     * @return boolean
     */
    public static function should_enqueue_in_current_tab_section($tab, $section)
    {
        global $current_tab, $current_section;

        if (!isset($current_tab) || $tab !== $current_tab) {
            return false;
        }

        if (!isset($current_section) || $section !== $current_section) {
            return false;
        }

        return true;
    }

	/**
	 * @param WC_Order $order
	 * @param string $method
	 *
	 * @return bool
	 */
    public static function set_payment_method_to_order($order, $method)
    {
        try {
            $available_payment_gateways = WC()->payment_gateways()->get_available_payment_gateways();
            if (isset($available_payment_gateways[$method])) {
                $order->set_payment_method($available_payment_gateways[$method]);
            }

            return true;
        } catch (Throwable $e) {
            WC_YouCanPay_Logger::alert('throwable at set payment method to order exists into wc youcan pay helper', [
                'exception.message' => $e->getMessage(),
            ]);
        }

        return false;
    }
}
