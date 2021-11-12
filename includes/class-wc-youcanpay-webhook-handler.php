<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WC_YouCanPay_Webhook_Handler.
 *
 * Handles webhooks from YouCanPay on sources that are not immediately chargeable.
 *
 * @since 4.0.0
 */
class WC_YouCanPay_Webhook_Handler extends WC_YouCanPay_Payment_Gateway {
	/**
	 * Is test mode active?
	 *
	 * @var bool
	 */
	public $testmode;

	/**
	 * Constructor.
	 *
	 * @since 4.0.0
	 * @version 5.0.0
	 */
	public function __construct() {
		$this->retry_interval = 2;
		$youcanpay_settings      = get_option( 'woocommerce_youcanpay_settings', [] );
		$this->testmode       = ( ! empty( $youcanpay_settings['testmode'] ) && 'yes' === $youcanpay_settings['testmode'] ) ? true : false;

		add_action( 'woocommerce_api_wc_youcanpay', [ $this, 'check_for_webhook' ] );

		// Get/set the time we began monitoring the health of webhooks by fetching it.
		// This should be roughly the same as the activation time of the version of the
		// plugin when this code first appears.
		WC_YouCanPay_Webhook_State::get_monitoring_began_at();
	}

	/**
	 * Check incoming requests for YouCanPay Webhook data and process them.
	 *
	 * @since 4.0.0
	 * @version 5.0.0
	 */
	public function check_for_webhook() {
		if ( ! isset( $_SERVER['REQUEST_METHOD'] )
			|| ! isset( $_GET['wc-api'] )
			|| ( 'wc_youcanpay' !== $_GET['wc-api'] )
		) {
			return;
		}

		if ( ! isset($_GET['transaction_id']) ) {
			wc_add_notice( __( '#0020: Fatal error, please try again', 'woocommerce-youcan-pay' ), 'error' );
			return wp_redirect(wp_sanitize_redirect(esc_url_raw(get_home_url())));
		}

		$transaction = WC_YouCanPay_API::get_transaction($_GET['transaction_id']);

		if (! isset($transaction)) {
			wc_add_notice( __( '#0022: Fatal error, please try again', 'woocommerce-youcan-pay' ), 'error' );
			return wp_redirect(wp_sanitize_redirect(esc_url_raw(get_home_url())));
		}

		/** @var WC_Order|WC_Order_Refund $order $order */
		$orderId = $transaction->getOrderId();
		$order = wc_get_order($orderId);

		if (! isset($order)) {
			wc_add_notice( __( '#0021: Fatal error, please contact support', 'woocommerce-youcan-pay' ), 'error' );
			return wp_redirect(wp_sanitize_redirect(esc_url_raw(get_home_url())));
		}

		if ($transaction->getStatus() === 1) {
			$order->payment_complete($transaction->getId());
			return wp_redirect(wp_sanitize_redirect(esc_url_raw($this->get_return_url($order))));
		} else {
			wc_add_notice( __( '#0033: Payment error please try again', 'woocommerce-youcan-pay' ), 'error' );

			$order->set_status('failed');
			$order->save();

			return wp_redirect(wp_sanitize_redirect(esc_url_raw(wc_get_checkout_url())));
		}
	}
}

new WC_YouCanPay_Webhook_Handler();
