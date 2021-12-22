<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Abstract class that will be inherited by all payment methods.
 *
 * @extends WC_Payment_Gateway_CC
 *
 */
abstract class WC_YouCanPay_Payment_Gateway extends WC_Payment_Gateway_CC {

	/**
	 * The delay between retries.
	 *
	 * @var int
	 */
	protected $retry_interval = 1;

	/**
	 * Prints the admin options for the gateway.
	 * Inserts an empty placeholder div feature flag is enabled.
	 */
	public function admin_options() {
		$form_fields = $this->get_form_fields();

		echo '<h2>' . esc_html( $this->get_method_title() );
		wc_back_link( __( 'Return to payments', 'youcan-pay' ),
			admin_url( 'admin.php?page=wc-settings&tab=checkout' ) );
		echo '</h2>';

		echo '<table class="form-table">' . $this->generate_settings_html( $form_fields, false ) . '</table>';
	}

	/**
	 * Checks if keys are set and valid.
	 *
	 * @return bool True if the keys are set *and* valid, false otherwise (for example, if keys are empty or the secret key was pasted as publishable key).
	 */
	public function are_keys_set() {
		if ( $this->sandbox_mode ) {
			return preg_match( '/^pub_sandbox_/', $this->public_key )
			       && preg_match( '/^pri_sandbox_/', $this->private_key );
		} else {
			return preg_match( '/^pub_/', $this->public_key )
			       && preg_match( '/^pri_/', $this->private_key );
		}
	}

	/**
	 * Check if we need to make gateways available.
	 */
	public function is_available() {
		if ( 'yes' === $this->enabled ) {
			return $this->are_keys_set();
		}

		return parent::is_available();
	}

	/**
	 * All payment icons that work with YouCan Pay. Some icons reference
	 * WC core icons.
	 *
	 * @return array
	 */
	public function payment_icons() {
		$title_standalone = __( 'YouCan Pay Standalone', 'youcan-pay' );
		$title_cash_plus = __( 'Cash Plus', 'youcan-pay' );
		$url   = WC_YOUCAN_PAY_PLUGIN_URL . '/assets/images/youcan-pay.svg';

		return apply_filters(
			'wc_youcanpay_payment_icons',
			[
				'standalone' => '<img src="' . $url . '" alt="' . $title_standalone . '" class="youcanpay-standalone-icon youcanpay-icon" style="width: 55px" />',
				'cash_plus' => '<img src="' . $url . '" alt="' . $title_cash_plus . '" class="youcanpay-standalone-icon youcanpay-icon" style="width: 55px" />',
			]
		);
	}

	/**
	 * Gets the transaction URL linked to YouCan Pay dashboard.
	 */
	public function get_transaction_url( $order ) {
		$this->view_transaction_url = 'https://pay.youcan.shop/transactions/%s';

		return parent::get_transaction_url( $order );
	}

	/**
	 * Builds the return URL from redirects.
	 *
	 * @param null $order
	 * @param null $gateway
	 *
	 * @return string
	 */
	public function get_youcanpay_return_url( $order = null, $gateway = null ) {
		if ( is_object( $order ) ) {
			$action = WC_YouCanPay_Order_Action_Enum::get_incomplete();
			if ( array_key_exists( 'order-pay', $_GET ) ) {
				$action = WC_YouCanPay_Order_Action_Enum::get_pre_order();
			}

			$args = [
				'gateway' => $gateway,
				'action'  => $action,
				'wc-api'  => 'wc_youcanpay',
				'key'     => $order->get_order_key(),
			];

			$response = wp_sanitize_redirect( esc_url_raw( add_query_arg( $args, get_site_url() ) ) );
		} else {
			$response = wp_sanitize_redirect( esc_url_raw( add_query_arg( [ 'utm_nooverride' => '1' ],
				$this->get_return_url() ) ) );
		}

		return $response;
	}

	/**
	 * Sends the failed order email to admin.
	 *
	 * @param int $order_id
	 *
	 * @return void
	 */
	public function send_failed_order_email( $order_id ) {
		$emails = WC()->mailer()->get_emails();
		if ( ! empty( $emails ) && ! empty( $order_id ) ) {
			/** @var WC_Email_Failed_Order $wcFailedOrder */
			$wcFailedOrder = $emails['WC_Email_Failed_Order'];

			$wcFailedOrder->trigger( $order_id );
		}
	}

	/**
	 * Get owner details.
	 *
	 * @param object $order
	 *
	 * @return object $details
	 */
	public function get_owner_details( $order ) {
		$billing_first_name = $order->get_billing_first_name();
		$billing_last_name  = $order->get_billing_last_name();

		$details = [];

		$name  = $billing_first_name . ' ' . $billing_last_name;
		$email = $order->get_billing_email();
		$phone = $order->get_billing_phone();

		if ( ! empty( $phone ) ) {
			$details['phone'] = $phone;
		}

		if ( ! empty( $name ) ) {
			$details['name'] = $name;
		}

		if ( ! empty( $email ) ) {
			$details['email'] = $email;
		}

		$details['address']['line1']       = $order->get_billing_address_1();
		$details['address']['line2']       = $order->get_billing_address_2();
		$details['address']['state']       = $order->get_billing_state();
		$details['address']['city']        = $order->get_billing_city();
		$details['address']['postal_code'] = $order->get_billing_postcode();
		$details['address']['country']     = $order->get_billing_country();

		return (object) apply_filters( 'wc_youcanpay_owner_details', $details, $order );
	}

	/**
	 * Gets the locale with normalization that only YouCan Pay accepts.
	 *
	 * @return string $locale
	 */
	public function get_locale() {
		$locale = get_locale();

		/*
		 * YouCan Pay expects Norwegian to only be passed NO.
		 * But WP has different dialects.
		 */
		if ( 'NO' === substr( $locale, 3, 2 ) ) {
			$locale = 'no';
		} else {
			$locale = substr( get_locale(), 0, 2 );
		}

		return $locale;
	}

}
