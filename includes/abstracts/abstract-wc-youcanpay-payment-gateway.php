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
		wc_back_link( __( 'Return to payments', 'woocommerce-youcan-pay' ), admin_url( 'admin.php?page=wc-settings&tab=checkout' ) );
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
				&& preg_match( '/^pri_sandbox_/', $this->production_private_key );
		} else {
			return preg_match( '/^pub_/', $this->public_key )
				&& preg_match( '/^pri_/', $this->production_private_key );
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
		return apply_filters(
			'wc_youcanpay_payment_icons',
			[
				'standalone' => '<img src="' . WC_YOUCAN_PAY_PLUGIN_URL . '/assets/images/youcan-pay.svg" class="youcanpay-standalone-icon youcanpay-icon" alt="YouCan Pay Standalone" />',
			]
		);
	}

	/**
	 * Validates that the order meets the minimum order amount
	 * set by YouCan Pay.
	 *
	 * @param object $order
	 *
	 * @throws WC_YouCanPay_Exception
	 */
	public function validate_minimum_order_amount( $order ) {
		if ( $order->get_total() * 100 < WC_YouCanPay_Helper::get_minimum_amount() ) {
			/* translators: 1) amount (including currency symbol) */
			throw new WC_YouCanPay_Exception( 'Did not meet minimum amount', sprintf( __( 'Sorry, the minimum allowed order total is %1$s to use this payment method.', 'woocommerce-youcan-pay' ), wc_price( WC_YouCanPay_Helper::get_minimum_amount() / 100 ) ) );
		}
	}

	/**
	 * Validates that the cart meets the minimum order amount
	 * set by YouCan Pay.
	 *
	 * @throws WC_YouCanPay_Exception
	 */
	public function validate_minimum_cart_amount() {
		if ( WC()->cart->get_total() * 100 < WC_YouCanPay_Helper::get_minimum_amount() ) {
			/* translators: 1) amount (including currency symbol) */
			throw new WC_YouCanPay_Exception( 'Did not meet minimum amount', sprintf( __( 'Sorry, the minimum allowed order total is %1$s to use this payment method.', 'woocommerce-youcan-pay' ), wc_price( WC_YouCanPay_Helper::get_minimum_amount() / 100 ) ) );
		}
	}

	/**
	 * Gets the transaction URL linked to YouCan Pay dashboard.
	 */
	public function get_transaction_url( $order ) {
		$this->view_transaction_url = 'https://pay.youcan.shop/backoffice/transactions/%s';

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
			$args = [
				'gateway' => $gateway,
				'wc-api' => 'wc_youcanpay',
				'key' => $order->get_order_key(),
			];

            return wp_sanitize_redirect( esc_url_raw( add_query_arg( $args, get_site_url()) ) );
		}

		return wp_sanitize_redirect( esc_url_raw( add_query_arg( [ 'utm_nooverride' => '1' ], $this->get_return_url() ) ) );
	}

	/**
	 * Sends the failed order email to admin.
	 *
	 * @param int $order_id
	 * @return null
	 */
	public function send_failed_order_email( $order_id ) {
		$emails = WC()->mailer()->get_emails();
		if ( ! empty( $emails ) && ! empty( $order_id ) ) {
			$emails['WC_Email_Failed_Order']->trigger( $order_id );
		}
	}

	/**
	 * Get owner details.
	 *
	 * @param object $order
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
