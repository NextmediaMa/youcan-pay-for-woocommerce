<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Bancontact Payment Method class extending UPE base class
 */
class WC_YouCanPay_UPE_Payment_Method_Bancontact extends WC_YouCanPay_UPE_Payment_Method {

	const YOUCAN_PAY_ID = 'bancontact';

	const LPM_GATEWAY_CLASS = WC_Gateway_YouCanPay_Bancontact::class;

	/**
	 * Constructor for Bancontact payment method
	 */
	public function __construct() {
		parent::__construct();
		$this->youcanpay_id            = self::YOUCAN_PAY_ID;
		$this->title                = 'Pay with Bancontact';
		$this->is_reusable          = true;
		$this->supported_currencies = [ 'EUR' ];
		$this->label                = __( 'Bancontact', 'woocommerce-gateway-youcanpay' );
		$this->description          = __(
			'Bancontact is the most popular online payment method in Belgium, with over 15 million cards in circulation.',
			'woocommerce-gateway-youcanpay'
		);
	}
}
