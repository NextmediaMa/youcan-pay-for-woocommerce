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
	 * Displays the save to account checkbox.
	 */
	public function save_payment_method_checkbox( $force_checked = false ) {
		$id = 'wc-' . $this->id . '-new-payment-method';
		?>
		<fieldset <?php echo $force_checked ? 'style="display:none;"' : ''; /* phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped */ ?>>
			<p class="form-row woocommerce-SavedPaymentMethods-saveNew">
				<input id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $id ); ?>" type="checkbox" value="true" style="width:auto;" <?php echo $force_checked ? 'checked' : ''; /* phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped */ ?> />
				<label for="<?php echo esc_attr( $id ); ?>" style="display:inline;">
					<?php echo esc_html( apply_filters( 'wc_youcanpay_save_to_account_text', __( 'Save payment information to my account for future purchases.', 'woocommerce-youcan-pay' ) ) ); ?>
				</label>
			</p>
		</fieldset>
		<?php
	}

	/**
	 * Checks if keys are set and valid.
	 *
	 * @return bool True if the keys are set *and* valid, false otherwise (for example, if keys are empty or the secret key was pasted as publishable key).
	 */
	public function are_keys_set() {
		if ( $this->testmode ) {
			return preg_match( '/^pub_sandbox_/', $this->publishable_key )
				&& preg_match( '/^pri_sandbox_/', $this->secret_key );
		} else {
			return preg_match( '/^pub_/', $this->publishable_key )
				&& preg_match( '/^pri_/', $this->secret_key );
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
				'standalone' => '<img src="' . WC_YOUCAN_PAY_PLUGIN_URL . '/assets/images/standalone.svg" class="youcanpay-standalone-icon youcanpay-icon" alt="YouCan Pay Standalone" />',
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
	 * Gets the transaction URL linked to YouCan Pay dashboard.
	 */
	public function get_transaction_url( $order ) {
		$this->view_transaction_url = 'https://pay.youcan.shop/backoffice/transactions/%s';

		return parent::get_transaction_url( $order );
	}

	/**
	 * Builds the return URL from redirects.
	 *
	 * @param object $order
	 * @param int    $id YouCan Pay session id.
	 */
	public function get_youcanpay_return_url( $order = null, $id = null ) {
		if ( is_object( $order ) ) {
			$args = [
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
	 * Add payment method via account screen.
	 * We don't store the token locally, but to the YouCan Pay API.
	 */
	public function add_payment_method() {
		$error     = false;
		$error_msg = __( 'There was a problem adding the payment method.', 'woocommerce-youcan-pay' );
		$source_id = '';

		if ( empty( $_POST['youcanpay_source'] ) && empty( $_POST['youcanpay_token'] ) || ! is_user_logged_in() ) {
			$error = true;
		}

		$youcanpay_customer = new WC_YouCanPay_Customer( get_current_user_id() );

		$source = ! empty( $_POST['youcanpay_source'] ) ? wc_clean( wp_unslash( $_POST['youcanpay_source'] ) ) : '';

		$source_object = WC_YouCanPay_API::retrieve( 'sources/' . $source );

		if ( isset( $source_object ) ) {
			if ( ! empty( $source_object->error ) ) {
				$error = true;
			}

			$source_id = $source_object->id;
		} elseif ( isset( $_POST['youcanpay_token'] ) ) {
			$source_id = wc_clean( wp_unslash( $_POST['youcanpay_token'] ) );
		}

		$response = $youcanpay_customer->add_source( $source_id );

		if ( ! $response || is_wp_error( $response ) || ! empty( $response->error ) ) {
			$error = true;
		}

		if ( $error ) {
			wc_add_notice( $error_msg, 'error' );
			WC_YouCanPay_Logger::log( 'Add payment method Error: ' . $error_msg );
			return;
		}

		do_action( 'wc_youcanpay_add_payment_method_' . ( isset( $_POST['payment_method'] ) ? wc_clean( wp_unslash( $_POST['payment_method'] ) ) : '' ) . '_success', $source_id, $source_object );

		return [
			'result'   => 'success',
			'redirect' => wc_get_endpoint_url( 'payment-methods' ),
		];
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
