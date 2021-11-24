<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WC_YouCanPay_Webhook_Handler.
 *
 * Handles webhooks from YouCan Pay on sources that are not immediately chargeable.
 */
class WC_YouCanPay_Webhook_Handler extends WC_YouCanPay_Payment_Gateway {
	/**
	 * Is sandbox mode active?
	 *
	 * @var bool
	 */
	public $testmode;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->retry_interval = 2;
		$youcanpay_settings   = get_option( 'woocommerce_youcanpay_settings', [] );
		$this->testmode       = ( ! empty( $youcanpay_settings['testmode'] ) && 'yes' === $youcanpay_settings['testmode'] ) ? true : false;

		add_action( 'woocommerce_api_wc_youcanpay', [ $this, 'check_for_webhook' ] );
	}

	/**
	 * Check incoming requests for YouCan Pay Webhook data and process them.
	 */
	public function check_for_webhook() {
		if ( ! isset( $_SERVER['REQUEST_METHOD'] )
		     || ! isset( $_GET['wc-api'] )
		     || ! isset( $_GET['gateway'] )
		     || ( 'wc_youcanpay' !== $_GET['wc-api'] )
		) {
			return;
		}

		switch ( $_GET['gateway'] ) {
			case WC_Gateway_YouCanPay::ID:
				$this->youcanpay_credit_card();
				break;
			case WC_Gateway_YouCanPay_Standalone::ID:
				$this->youcanpay_standalone();
				break;
		}
	}

	/**
	 * @throws WC_YouCanPay_Exception
	 */
	private function youcanpay_credit_card() {
		$transaction_id = $_GET['transaction_id'] ?? '';
		$transaction    = WC_YouCanPay_API::get_transaction( $transaction_id );

		if ( ! isset( $transaction ) ) {
			WC_YouCanPay_Logger::log( "arrived on process payment: transaction not exists" . PHP_EOL
			                          . print_r( 'Payment method: YouCan Pay (Credit Card)', true ) . PHP_EOL
			                          . print_r( 'Code: #0023', true ) . PHP_EOL
			                          . print_r( 'Transaction Id: ', true ) . PHP_EOL
			                          . print_r( $transaction_id, true ) . PHP_EOL
			);

			wc_add_notice( __( 'Please try again, This payment has been canceled!', 'woocommerce-youcan-pay' ), 'error' );

			return wp_redirect( wp_sanitize_redirect( esc_url_raw( wc_get_checkout_url() ) ) );
		}

		$order = wc_get_order( $transaction->getOrderId() );
		if ( ! isset( $order ) ) {
			WC_YouCanPay_Logger::log( "arrived on process payment: order not exists" . PHP_EOL
			                          . print_r( 'Payment method: YouCan Pay (Credit Card)', true ) . PHP_EOL
			                          . print_r( 'Code: #0024', true ) . PHP_EOL
			                          . print_r( 'Order Id: ', true ) . PHP_EOL
			                          . print_r( $order->get_id(), true )
			);
			wc_add_notice( __( 'Fatal error! Please contact support.', 'woocommerce-youcan-pay' ), 'error' );

			return wp_redirect( wp_sanitize_redirect( esc_url_raw( get_home_url() ) ) );
		}

		if ( $transaction->getStatus() === 1 ) {
			WC_YouCanPay_Logger::log( "info: payment complete for order {$order->get_id()} for the amount of {$order->get_total()}" );

			$order->payment_complete( $transaction->getId() );

			if ( isset( WC()->cart ) ) {
				WC()->cart->empty_cart();
			}

			return wp_redirect( wp_sanitize_redirect( esc_url_raw( $this->get_return_url( $order ) ) ) );
		} else {
			wc_add_notice( __( 'Sorry, payment not completed please try again.', 'woocommerce-youcan-pay' ), 'error' );

			$order->set_status( 'failed' );
			$order->save();

			return wp_redirect( wp_sanitize_redirect( esc_url_raw( wc_get_checkout_url() ) ) );
		}
	}

	private function youcanpay_standalone() {
		if ( ! isset( $_GET['key'] ) ) {
			wc_add_notice( __( '#0020: Fatal error, please try again', 'woocommerce-youcan-pay' ), 'error' );

			return wp_redirect( wp_sanitize_redirect( esc_url_raw( get_home_url() ) ) );
		}

		/** @var WC_Order|WC_Order_Refund $order $order */
		$order_id = wc_get_order_id_by_order_key( $_GET['key'] );
		$order    = wc_get_order( $order_id );

		if ( ! isset( $order ) ) {
			WC_YouCanPay_Logger::log( "arrived on process payment: order not exists" . PHP_EOL
			                          . print_r( 'Payment method: YouCan Pay (Standalone)', true ) . PHP_EOL
			                          . print_r( 'Code: #0021', true ) . PHP_EOL
			                          . print_r( 'Order Id: ', true ) . PHP_EOL
			                          . print_r( $order_id, true )
			);
			wc_add_notice( __( 'Fatal error! Please contact support.', 'woocommerce-youcan-pay' ), 'error' );

			return wp_redirect( wp_sanitize_redirect( esc_url_raw( get_home_url() ) ) );
		}

		$transaction_id = $_GET['transaction_id'] ?? '';
		$transaction    = WC_YouCanPay_API::get_transaction( $transaction_id );

		if ( ! isset( $transaction ) ) {
			WC_YouCanPay_Logger::log( "arrived on process payment: transaction not exists" . PHP_EOL
			                          . print_r( 'Payment method: YouCan Pay (Standalone)', true ) . PHP_EOL
			                          . print_r( 'Code: #0022', true ) . PHP_EOL
			                          . print_r( 'Transaction Id: ', true ) . PHP_EOL
			                          . print_r( $transaction_id, true ) . PHP_EOL
			                          . print_r( 'Order Id: ', true ) . PHP_EOL
			                          . print_r( $order->get_id(), true )
			);
			wc_add_notice( __( 'Please try again, This payment has been canceled!', 'woocommerce-youcan-pay' ),
				'error' );

			return wp_redirect( wp_sanitize_redirect( esc_url_raw( wc_get_checkout_url() ) ) );
		}

		if ( $transaction->getOrderId() != $order->get_id() ) {
			WC_YouCanPay_Logger::log( "arrived on process payment: order not identical with transaction" . PHP_EOL
			                          . print_r( 'Payment method: YouCan Pay (Standalone)', true ) . PHP_EOL
			                          . print_r( 'Code: #0023', true ) . PHP_EOL
			                          . print_r( 'Transaction Id: ', true ) . PHP_EOL
			                          . print_r( $transaction->getId(), true ) . PHP_EOL
			                          . print_r( 'Transaction Order Id: ', true ) . PHP_EOL
			                          . print_r( $transaction->getOrderId(), true ) . PHP_EOL
			                          . print_r( 'Order Id: ', true ) . PHP_EOL
			                          . print_r( $order->get_id(), true )
			);
			wc_add_notice( __( 'Fatal error, please try again or contact support.', 'woocommerce-youcan-pay' ),
				'error' );

			return wp_redirect( wp_sanitize_redirect( esc_url_raw( get_home_url() ) ) );
		}

		if ( $transaction->getStatus() === 1 ) {
			WC_YouCanPay_Logger::log( "info: payment complete for order {$order->get_id()} for the amount of {$order->get_total()}" );

			$order->payment_complete( $transaction->getId() );

			return wp_redirect( wp_sanitize_redirect( esc_url_raw( $this->get_return_url( $order ) ) ) );
		} else {
			wc_add_notice( __( 'Sorry, payment not completed please try again.', 'woocommerce-youcan-pay' ), 'error' );

			$order->set_status( 'failed' );
			$order->save();

			return wp_redirect( wp_sanitize_redirect( esc_url_raw( wc_get_checkout_url() ) ) );
		}
	}
}

new WC_YouCanPay_Webhook_Handler();
