<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Ideal Payment Method class extending UPE base class
 */
class WC_YouCanPay_UPE_Payment_Method_Ideal extends WC_YouCanPay_UPE_Payment_Method {

	const YOUCAN_PAY_ID = 'ideal';

	const LPM_GATEWAY_CLASS = WC_Gateway_YouCanPay_Ideal::class;

	/**
	 * Constructor for iDEAL payment method
	 */
	public function __construct() {
		parent::__construct();
		$this->youcanpay_id            = self::YOUCAN_PAY_ID;
		$this->title                = __( 'Pay with iDEAL', 'woocommerce-gateway-youcanpay' );
		$this->is_reusable          = true;
		$this->supported_currencies = [ 'EUR' ];
		$this->label                = __( 'iDEAL', 'woocommerce-gateway-youcanpay' );
		$this->description          = __(
			'iDEAL is a Netherlands-based payment method that allows customers to complete transactions online using their bank credentials.',
			'woocommerce-gateway-youcanpay'
		);
	}
}
