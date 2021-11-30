<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * An email sent to the admin when payment fails to go through due to authentication_required error.
 */
class WC_YouCanPay_Order_Action_Enum {

	public static $incomplete = '1';
	public static $pre_order = '2';

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
