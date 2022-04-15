<?php

if (!defined('ABSPATH')) {
    exit;
}

class WC_YouCanPay_Api_Enum
{

    /** @var string */
    private static $base_url = 'https://youcanpay.com';

    /** @var string */
    private static $javascript_path = '/js/ycpay.js';

    /**
     * @return string
     */
    public static function get_base_url(): string
    {
        return self::$base_url;
    }

    /**
     * @return string
     */
    public static function get_javascript_url(): string
    {
        return self::$base_url . self::$javascript_path;
    }

}
