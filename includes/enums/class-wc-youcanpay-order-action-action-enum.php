<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WC_YouCanPay_Order_Action_Enum {

	private static $incomplete = '1';
	private static $pre_order = '2';

	/**
	 * @return string
	 */
	public static function get_incomplete(): string {
		return self::$incomplete;
	}

	/**
	 * @return string
	 */
	public static function get_pre_order(): string {
		return self::$pre_order;
	}

}
