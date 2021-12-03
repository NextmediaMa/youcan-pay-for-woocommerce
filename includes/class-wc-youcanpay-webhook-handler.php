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
	 * @var bool $sandbox_mode
	 */
	public $sandbox_mode;

	/**
	 * @var string $public_key
	 */
	public $public_key;

	/**
	 * @var string $private_key
	 */
	public $private_key;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->retry_interval = 2;
		$youcanpay_settings   = get_option( 'woocommerce_youcanpay_settings', [] );
		$this->sandbox_mode   = ! empty( $youcanpay_settings['sandbox_mode'] ) && 'yes' === $youcanpay_settings['sandbox_mode'];

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
			return false;
		}

		switch ( $_GET['gateway'] ) {
			case WC_Gateway_YouCanPay::ID:
				return $this->youcanpay_credit_card();
			case WC_Gateway_YouCanPay_Standalone::ID:
				return $this->youcanpay_standalone();
		}

		return false;
	}

	/**
	 * @return bool
	 */
	private function youcanpay_credit_card() {
		$transaction_id = $_GET['transaction_id'] ?? '';
		$action         = $_GET['action'] ?? WC_YouCanPay_Order_Action_Enum::get_incomplete();
		$transaction    = WC_YouCanPay_API::get_transaction( $transaction_id );
		$checkout_url   = $this->get_checkout_url_by_action( $action );

		if ( ! isset( $transaction ) ) {
			WC_YouCanPay_Logger::log( 'arrived on process payment: transaction not exists', array(
				'payment_method' => 'YouCan Pay (Credit Card)',
				'code'           => '#0023',
				'transaction_id' => $transaction_id,
			) );

			wc_add_notice( __( 'Please try again, This payment has been canceled!', 'youcan-pay' ), 'error' );

			return wp_redirect( wp_sanitize_redirect( esc_url_raw( $checkout_url ) ) );
		}

		$order = wc_get_order( $transaction->getOrderId() );
		if ( ! isset( $order ) ) {
			WC_YouCanPay_Logger::log( 'arrived on process payment: order not exists', array(
				'payment_method' => 'YouCan Pay (Credit Card)',
				'code'           => '#0024',
				'transaction_id' => $transaction_id,
				'order_id'       => $order->get_id(),
			) );

			wc_add_notice( __( 'Fatal error! Please contact support.', 'youcan-pay' ), 'error' );

			return wp_redirect( wp_sanitize_redirect( esc_url_raw( get_home_url() ) ) );
		}

		if ( $transaction->getStatus() === 1 ) {
			WC_YouCanPay_Logger::log( 'payment successfully processed', array(
				'payment_method' => 'YouCan Pay (Credit Card)',
				'transaction_id' => $transaction->getId(),
				'order_id'       => $order->get_id(),
				'order_total'    => $order->get_total(),
			) );

			WC_YouCanPay_Helper::set_payment_method_to_order($order, WC_Gateway_YouCanPay::ID);
			$order->payment_complete( $transaction->getId() );

			$order->update_meta_data( '_youcanpay_source_id', $transaction->getId());
			$order->save();

			if ( isset( WC()->cart ) ) {
				WC()->cart->empty_cart();
			}

			return wp_redirect( wp_sanitize_redirect( esc_url_raw( $this->get_return_url( $order ) ) ) );
		} else {
			WC_YouCanPay_Logger::log( 'payment not processed', array(
				'payment_method'     => 'YouCan Pay (Credit Card)',
				'transaction_id'     => $transaction->getId(),
				'transaction_status' => $transaction->getStatus(),
				'order_id'           => $order->get_id(),
			) );

			wc_add_notice( __( 'Sorry, payment not completed please try again.', 'youcan-pay' ), 'error' );

			$order->set_status( 'failed' );
			$order->save();

			return wp_redirect( wp_sanitize_redirect( esc_url_raw( $checkout_url ) ) );
		}
	}

	private function get_checkout_url_by_action( $action ) {
		$checkout_url = wc_get_checkout_url();
		if ( $action === WC_YouCanPay_Order_Action_Enum::get_pre_order() ) {
			$order_id  = $_GET['order_id'] ?? null;
			$order_key = $_GET['key'] ?? null;

			$checkout_url = add_query_arg(
				array(
					'pay_for_order' => 'true',
					'key'           => $order_key,
				),
				wc_get_endpoint_url( 'order-pay', $order_id, wc_get_checkout_url() )
			);
		}

		return $checkout_url;
	}

	/**
	 * @return bool
	 */
	private function youcanpay_standalone() {
		if ( ! isset( $_GET['key'] ) ) {
			wc_add_notice( __( 'Fatal error, please try again.', 'youcan-pay' ), 'error' );

			return wp_redirect( wp_sanitize_redirect( esc_url_raw( get_home_url() ) ) );
		}

		/** @var WC_Order|WC_Order_Refund $order $order */
		$order_key    = $_GET['key'];
		$action       = $_GET['action'] ?? WC_YouCanPay_Order_Action_Enum::get_incomplete();
		$order_id     = wc_get_order_id_by_order_key( $order_key );
		$order        = wc_get_order( $order_id );
		$checkout_url = $this->get_checkout_url_by_action( $action );

		if ( ! isset( $order ) ) {
			WC_YouCanPay_Logger::log( 'arrived on process payment: order not exists', array(
				'payment_method' => 'YouCan Pay (Standalone)',
				'code'           => '#0021',
				'order_key'      => $order_key,
				'order_id'       => $order_id,
				'action'         => $action,
			) );

			wc_add_notice( __( 'Fatal error! Please contact support.', 'youcan-pay' ), 'error' );

			return wp_redirect( wp_sanitize_redirect( esc_url_raw( get_home_url() ) ) );
		}

		$transaction_id = $_GET['transaction_id'] ?? '';
		$transaction    = WC_YouCanPay_API::get_transaction( $transaction_id );

		if ( ! isset( $transaction ) ) {
			WC_YouCanPay_Logger::log( 'arrived on process payment: transaction not exists', array(
				'payment_method' => 'YouCan Pay (Standalone)',
				'code'           => '#0022',
				'transaction_id' => $transaction_id,
				'order_id'       => $order->get_id(),
				'action'         => $action,
			) );

			wc_add_notice( __( 'Please try again, This payment has been canceled!', 'youcan-pay' ), 'error' );

			return wp_redirect( wp_sanitize_redirect( esc_url_raw( $checkout_url ) ) );
		}

		if ( $transaction->getOrderId() != $order->get_id() ) {
			WC_YouCanPay_Logger::log( 'arrived on process payment: order not identical with transaction', array(
				'payment_method'       => 'YouCan Pay (Standalone)',
				'code'                 => '#0023',
				'transaction_id'       => $transaction->getId(),
				'transaction_order_id' => $transaction->getOrderId(),
				'order_id'             => $order->get_id(),
				'action'               => $action,
			) );

			wc_add_notice( __( 'Fatal error, please try again or contact support.', 'youcan-pay' ),
				'error' );

			return wp_redirect( wp_sanitize_redirect( esc_url_raw( get_home_url() ) ) );
		}

		if ( $transaction->getStatus() === 1 ) {
			WC_YouCanPay_Logger::log( 'payment successfully processed', array(
				'payment_method' => 'YouCan Pay (Standalone)',
				'transaction_id' => $transaction->getId(),
				'order_id'       => $order->get_id(),
				'order_total'    => $order->get_total(),
				'action'         => $action,
			) );

			WC_YouCanPay_Helper::set_payment_method_to_order($order, WC_Gateway_YouCanPay_Standalone::ID);
			$order->payment_complete( $transaction->getId() );

			return wp_redirect( wp_sanitize_redirect( esc_url_raw( $this->get_return_url( $order ) ) ) );
		} else {
			WC_YouCanPay_Logger::log( 'payment not processed', array(
				'payment_method'     => 'YouCan Pay (Standalone)',
				'transaction_id'     => $transaction->getId(),
				'transaction_status' => $transaction->getStatus(),
				'order_id'           => $order->get_id(),
				'action'             => $action,
			) );

			wc_add_notice( __( 'Sorry, payment not completed please try again.', 'youcan-pay' ),
				'error' );

			$order->set_status( 'failed' );
			$order->save();

			return wp_redirect( wp_sanitize_redirect( esc_url_raw( $checkout_url ) ) );
		}
	}
}

new WC_YouCanPay_Webhook_Handler();
