<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WC_YouCanPay_Api_Enum {

	private static $base_url_test = 'https://pay.testyoucan.shop';
	private static $base_url_production = 'https://pay.youcan.shop';

	private static $javascript_path = '/js/ycpay.js';

	/**
	 * @return string
	 */
	public static function get_base_url(): string {
		if (WC_YOUCAN_PAY_MODE_DEV !== '1') {
			return self::$base_url_production;
		}

		return self::$base_url_test;
	}

	/**
	 * @return string
	 */
	public static function get_javascript_url(): string {
		if (WC_YOUCAN_PAY_MODE_DEV !== '1') {
			return self::$base_url_production . self::$javascript_path;
		}

		return self::$base_url_test . self::$javascript_path;
	}


}
