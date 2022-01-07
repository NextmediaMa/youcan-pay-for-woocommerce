<?php

if (!defined('ABSPATH')) {
    exit;
}

class WC_YouCanPay_Gateways_Enum
{

    /** @var string */
    private static $credit_cart = 'credit_cart';

    /** @var string */
    private static $cash_plus = 'cash_plus';

    /** @var string */
    private static $standalone = 'youcanpay_standalone';

    /**
     * @return array
     */
    public static function get_all(): array
    {
        return [
            self::$credit_cart,
            self::$cash_plus,
        ];
    }
    /**
     * @return string
     */
    public static function get_credit_cart(): string
    {
        return self::$credit_cart;
    }

    /**
     * @return string
     */
    public static function get_cash_plus(): string
    {
        return self::$cash_plus;
    }

    /**
     * @return string
     */
    public static function get_standalone(): string
    {
        return self::$standalone;
    }

}
