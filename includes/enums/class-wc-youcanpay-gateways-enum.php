<?php

if (!defined('ABSPATH')) {
    exit;
}

class WC_YouCanPay_Gateways_Enum
{

    /** @var string */
    private static $youcan_pay = 'youcanpay';

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
            self::$youcan_pay,
            self::$cash_plus,
        ];
    }
    /**
     * @return string
     */
    public static function get_youcan_pay(): string
    {
        return self::$youcan_pay;
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
