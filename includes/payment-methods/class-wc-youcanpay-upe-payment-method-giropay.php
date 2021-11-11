<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Giropay Payment Method class extending UPE base class
 */
class WC_YouCanPay_UPE_Payment_Method_Giropay extends WC_YouCanPay_UPE_Payment_Method {

	const YOUCAN_PAY_ID = 'giropay';

	const LPM_GATEWAY_CLASS = WC_Gateway_YouCanPay_Giropay::class;

	/**
	 * Constructor for giropay payment method
	 */
	public function __construct() {
		parent::__construct();
		$this->youcanpay_id            = self::YOUCAN_PAY_ID;
		$this->title                = __( 'Pay with giropay', 'woocommerce-youcan-pay' );
		$this->is_reusable          = false;
		$this->supported_currencies = [ 'EUR' ];
		$this->label                = __( 'giropay', 'woocommerce-youcan-pay' );
		$this->description          = __(
			'Expand your business with giropay — Germany’s second most popular payment system.',
			'woocommerce-youcan-pay'
		);
	}
}
