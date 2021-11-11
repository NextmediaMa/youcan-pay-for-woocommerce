<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * EPS Payment Method class extending UPE base class
 */
class WC_YouCanPay_UPE_Payment_Method_Eps extends WC_YouCanPay_UPE_Payment_Method {

	const YOUCAN_PAY_ID = 'eps';

	const LPM_GATEWAY_CLASS = WC_Gateway_YouCanPay_Eps::class;

	/**
	 * Constructor for EPS payment method
	 */
	public function __construct() {
		parent::__construct();
		$this->youcanpay_id            = self::YOUCAN_PAY_ID;
		$this->title                = __( 'Pay with EPS', 'woocommerce-youcan-pay' );
		$this->is_reusable          = false;
		$this->supported_currencies = [ 'EUR' ];
		$this->label                = __( 'EPS', 'woocommerce-youcan-pay' );
		$this->description          = __(
			'EPS is an Austria-based payment method that allows customers to complete transactions online using their bank credentials.',
			'woocommerce-youcan-pay'
		);
	}
}
