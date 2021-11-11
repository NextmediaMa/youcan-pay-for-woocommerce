<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * SEPA Payment Method class extending UPE base class
 */
class WC_YouCanPay_UPE_Payment_Method_Sepa extends WC_YouCanPay_UPE_Payment_Method {

	const YOUCAN_PAY_ID = 'sepa_debit';

	const LPM_GATEWAY_CLASS = WC_Gateway_YouCanPay_Sepa::class;

	/**
	 * Constructor for SEPA payment method
	 *
	 * @param WC_Payments_Token_Service $token_service Token class instance.
	 */
	public function __construct() {
		parent::__construct();
		$this->youcanpay_id            = self::YOUCAN_PAY_ID;
		$this->title                = __( 'Pay with SEPA Direct Debit', 'woocommerce-youcan-pay' );
		$this->is_reusable          = true;
		$this->supported_currencies = [ 'EUR' ];
		$this->label                = __( 'SEPA Direct Debit', 'woocommerce-youcan-pay' );
		$this->description          = __(
			'Reach 500 million customers and over 20 million businesses across the European Union.',
			'woocommerce-youcan-pay'
		);
	}

	/**
	 * Returns string representing payment method type
	 * to query to retrieve saved payment methods from YouCan Pay.
	 */
	public function get_retrievable_type() {
		return $this->get_id();
	}
}
