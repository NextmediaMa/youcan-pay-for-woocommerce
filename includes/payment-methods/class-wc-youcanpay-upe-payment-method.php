<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Abstract UPE Payment Method class
 *
 * Handles general functionality for UPE payment methods
 */


/**
 * Extendable abstract class for payment methods.
 */
abstract class WC_YouCanPay_UPE_Payment_Method {

	use WC_YouCanPay_Subscriptions_Utilities_Trait;

	/**
	 * YouCanPay key name
	 *
	 * @var string
	 */
	protected $youcanpay_id;

	/**
	 * Display title
	 *
	 * @var string
	 */
	protected $title;

	/**
	 * Method label
	 *
	 * @var string
	 */
	protected $label;

	/**
	 * Method description
	 *
	 * @var string
	 */
	protected $description;

	/**
	 * Can payment method be saved or reused?
	 *
	 * @var bool
	 */
	protected $is_reusable;

	/**
	 * Array of currencies supported by this UPE method
	 *
	 * @var array
	 */
	protected $supported_currencies;

	/**
	 * Wether this UPE method is enabled
	 *
	 * @var bool
	 */
	protected $enabled;

	/**
	 * Create instance of payment method
	 */
	public function __construct() {
		$main_settings = get_option( 'woocommerce_youcanpay_settings' );

		if ( isset( $main_settings['upe_checkout_experience_accepted_payments'] ) ) {
			$enabled_upe_methods = $main_settings['upe_checkout_experience_accepted_payments'];
		} else {
			$enabled_upe_methods = [ WC_YouCanPay_UPE_Payment_Method_CC::YOUCAN_PAY_ID ];
		}

		$this->enabled = in_array( static::YOUCAN_PAY_ID, $enabled_upe_methods, true );
	}

	/**
	 * Returns payment method ID
	 *
	 * @return string
	 */
	public function get_id() {
		return $this->youcanpay_id;
	}

	/**
	 * Returns true if the UPE method is enabled.
	 *
	 * @return bool
	 */
	public function is_enabled() {
		return $this->enabled;
	}

	/**
	 * Returns payment method title
	 *
	 * @param array|bool $payment_details Optional payment details from charge object.
	 *
	 * @return string
	 */
	public function get_title( $payment_details = false ) {
		return $this->title;
	}

	/**
	 * Returns payment method label
	 *
	 * @return string
	 */
	public function get_label() {
		return $this->label;
	}

	/**
	 * Returns payment method description
	 *
	 * @return string
	 */
	public function get_description() {
		return $this->description;
	}

	/**
	 * Returns boolean dependent on whether payment method
	 * can be used at checkout
	 *
	 * @param int|null $order_id
	 * @return bool
	 */
	public function is_enabled_at_checkout( $order_id = null ) {
		// Check capabilities first.
		if ( ! $this->is_capability_active() ) {
			return false;
		}

		// Check currency compatibility.
		$currencies = $this->get_supported_currencies();
		if ( ! empty( $currencies ) && ! in_array( $this->get_woocommerce_currency(), $currencies, true ) ) {
			return false;
		}

		// If cart or order contains subscription, enable payment method if it's reusable.
		if ( $this->is_subscription_item_in_cart() || ( ! empty( $order_id ) && $this->has_subscription( $order_id ) ) ) {
			return $this->is_reusable();
		}

		return true;
	}

	/**
	 * Returns boolean dependent on whether payment method
	 * will support saved payments/subscription payments
	 *
	 * @return bool
	 */
	public function is_reusable() {
		return $this->is_reusable;
	}

	/**
	 * Returns boolean dependent on whether capability
	 * for site account is enabled for payment method.
	 *
	 * @return bool
	 */
	public function is_capability_active() {
		$capabilities = $this->get_capabilities_response();
		if ( empty( $capabilities ) ) {
			return false;
		}
		$key = $this->get_id() . '_payments';
		return isset( $capabilities[ $key ] ) && 'active' === $capabilities[ $key ];
	}

	/**
	 * Returns capabilities response object for site account.
	 *
	 * @return object
	 */
	public function get_capabilities_response() {
		$account = WC_YouCanPay::get_instance()->account;
		$data    = $account->get_cached_account_data();
		if ( empty( $data ) || ! isset( $data['capabilities'] ) ) {
			return [];
		}
		return $data['capabilities'];
	}

	/**
	 * Returns string representing payment method type
	 * to query to retrieve saved payment methods from YouCanPay.
	 */
	public function get_retrievable_type() {
		return $this->is_reusable() ? WC_YouCanPay_UPE_Payment_Method_Sepa::YOUCAN_PAY_ID : null;
	}

	/**
	 * Create new WC payment token and add to user.
	 *
	 * @param int $user_id        WP_User ID
	 * @param object $payment_method YouCanPay payment method object
	 *
	 * @return WC_YouCan_Pay_Token_SEPA
	 */
	public function create_payment_token_for_user( $user_id, $payment_method ) {
		$token = new WC_YouCan_Pay_Token_SEPA();
		$token->set_last4( $payment_method->sepa_debit->last4 );
		$token->set_gateway_id( WC_YouCanPay_UPE_Payment_Gateway::ID );
		$token->set_token( $payment_method->id );
		$token->set_payment_method_type( $this->get_id() );
		$token->set_user_id( $user_id );
		$token->save();
		return $token;
	}

	/**
	 * Returns the currencies this UPE method supports.
	 *
	 * @return array|null
	 */
	public function get_supported_currencies() {
		return apply_filters(
			'wc_youcanpay_' . static::YOUCAN_PAY_ID . '_upe_supported_currencies',
			$this->supported_currencies
		);
	}

	/**
	 * Wrapper function for get_woocommerce_currency global function
	 */
	public function get_woocommerce_currency() {
		return get_woocommerce_currency();
	}

	/**
	 * Returns whether the payment method requires automatic capture.
	 * By default all the UPE payment methods require automatic capture, except for "card".
	 *
	 * @return bool
	 */
	public function requires_automatic_capture() {
		return true;
	}

	/**
	 * Returns the HTML for the subtext messaging in the old settings UI.
	 *
	 * @param string $youcanpay_method_status (optional) Status of this payment method based on the YouCanPay's account capabilities
	 * @return string
	 */
	public function get_subtext_messages( $youcanpay_method_status ) {
		// can be either a `currency` or `activation` messaging, to be displayed in the old settings UI.
		$messages = [];

		if ( ! empty( $youcanpay_method_status ) && 'active' !== $youcanpay_method_status ) {
			$text            = __( 'Pending activation', 'woocommerce-gateway-youcanpay' );
			$tooltip_content = sprintf(
				/* translators: %1: Payment method name */
				esc_attr__( '%1$s won\'t be visible to your customers until you provide the required information. Follow the instructions YouCanPay has sent to your e-mail address.', 'woocommerce-gateway-youcanpay' ),
				$this->get_label()
			);
			$messages[] = $text . '<span class="tips" data-tip="' . $tooltip_content . '"><span class="woocommerce-help-tip" style="margin-top: 0;"></span></span>';
		}

		$currencies = $this->get_supported_currencies();
		if ( ! empty( $currencies ) && ! in_array( get_woocommerce_currency(), $currencies, true ) ) {
			/* translators: %s: List of comma-separated currencies. */
			$tooltip_content = sprintf( esc_attr__( 'In order to be used at checkout, the payment method requires the store currency to be set to one of: %s', 'woocommerce-gateway-youcanpay' ), implode( ', ', $currencies ) );
			$text            = __( 'Requires currency', 'woocommerce-gateway-youcanpay' );

			$messages[] = $text . '<span class="tips" data-tip="' . $tooltip_content . '"><span class="woocommerce-help-tip" style="margin-top: 0;"></span></span>';
		}

		return count( $messages ) > 0 ? join( '&nbsp;–&nbsp;', $messages ) : '';
	}
}
