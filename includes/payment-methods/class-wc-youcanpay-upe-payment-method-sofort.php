<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Sofort Payment Method class extending UPE base class
 */
class WC_YouCanPay_UPE_Payment_Method_Sofort extends WC_YouCanPay_UPE_Payment_Method {

	const YOUCAN_PAY_ID = 'sofort';

	const LPM_GATEWAY_CLASS = WC_Gateway_YouCanPay_Sofort::class;

	/**
	 * Constructor for Sofort payment method
	 */
	public function __construct() {
		parent::__construct();
		$this->youcanpay_id            = self::YOUCAN_PAY_ID;
		$this->title                = __( 'Pay with SOFORT', 'woocommerce-youcan-pay' );
		$this->is_reusable          = true;
		$this->supported_currencies = [ 'EUR' ];
		$this->label                = __( 'SOFORT', 'woocommerce-youcan-pay' );
		$this->description          = __(
			'Accept secure bank transfers from Austria, Belgium, Germany, Italy, Netherlands, and Spain.',
			'woocommerce-youcan-pay'
		);
	}
}
