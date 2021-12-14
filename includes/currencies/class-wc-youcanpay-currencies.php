<?php

if (!defined('ABSPATH')) {
    exit;
}

class WC_YouCanPay_Currencies
{

    /**
     * @return array|null
     */
    public static function get_all()
    {
        try {
            $currencies = file_get_contents(WC_YOUCAN_PAY_PLUGIN_PATH . '/includes/currencies/data.json');

            return json_decode($currencies, true);
        } catch (Throwable $e) {
            WC_YouCanPay_Logger::info('throwable at get currencies exists into wc youcan pay currencies', [
                'exception.code'    => $e->getCode(),
                'exception.message' => $e->getMessage(),
            ]);
        }

        return null;
    }

    /**
     * @return array
     */
    public static function get_all_index() {
        $currencies = self::get_all();
        if ($currencies == null) {
            return [];
        }

        return array_keys($currencies);
    }

    /**
     * @return array|null
     */
    public static function find($code)
    {
        if ($code == null) {
            return null;
        }

        $currencies = self::get_all();
        if ($currencies == null) {
            return null;
        }
        if ($currency = $currencies[$code]) {
            return $currency;
        }

        return null;
    }

    /**
     * @return array|null
     */
    public static function get_divider($code) {
        $currency = self::find($code);

        if ($currency == null) {
            return null;
        }

        return $currency['divider'] ?? null;
    }

    /**
     * @return string|null
     */
    public static function get_divider_name($code) {
        $divider = self::get_divider($code);

        if ($divider == null) {
            return null;
        }

        return $divider['name'] ?? null;
    }

    /**
     * @return integer|null
     */
    public static function get_divider_value($code) {
        $divider = self::get_divider($code);

        if ($divider == null) {
            return null;
        }

        return $divider['value'] ?? null;
    }

}
