<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Order_Action (Order Page): This is the page from which the user clicked on the Pay action
 * We are free to modify these values
 */
class WC_YouCanPay_Order_Action_Enum {

	private static $incomplete = 1;
	private static $pre_order = 2;

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

    /**
     * @return array
     */
	public static function get_all(): array
    {
		return array(
			self::$incomplete,
			self::$pre_order,
		);
	}

}
