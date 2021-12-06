<?php

use YouCan\Pay\Models\Token;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WC_Gateway_YouCanPay class.
 *
 * @extends WC_Payment_Gateway
 */
class WC_Gateway_YouCanPay extends WC_YouCanPay_Payment_Gateway {

	const ID = 'youcanpay';

	/**
	 * API access private key
	 *
	 * @var string
	 */
	public $private_key;

	/**
	 * Api access public key
	 *
	 * @var string
	 */
	public $public_key;

	/**
	 * Do we accept Payment Request?
	 *
	 * @var bool
	 */
	public $payment_request;

	/**
	 * Is sandbox mode active?
	 *
	 * @var bool
	 */
	public $sandbox_mode;

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->id           = self::ID;
		$this->method_title = __( 'YouCan Pay', 'youcan-pay' );
		/* translators: 1) link to YouCan Pay register page 2) link to YouCan Pay api keys page */
		$this->method_description = __( 'YouCan Pay works by adding payment fields on the checkout and then sending the details to YouCan Pay for verification.',
			'youcan-pay' );
		$this->has_fields         = true;
		$this->supports           = [
			'products',
			'refunds',
		];

		// Load the form fields.
		$this->init_form_fields();

		// Load the settings.
		$this->init_settings();

		// Get setting values.
		$this->title        = $this->get_option( 'title' );
		$this->description  = $this->get_option( 'description' );
		$this->enabled      = $this->get_option( 'enabled' );
		$this->sandbox_mode = 'yes' === $this->get_option( 'sandbox_mode' );
		$this->private_key  = $this->sandbox_mode ? $this->get_option( 'sandbox_private_key' ) : $this->get_option( 'private_key' );
		$this->public_key   = $this->sandbox_mode ? $this->get_option( 'sandbox_public_key' ) : $this->get_option( 'public_key' );

		WC_YouCanPay_API::set_private_key( $this->private_key );

		// Hooks.
		add_action( 'wp_enqueue_scripts', [ $this, 'payment_scripts' ] );
		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, [ $this, 'process_admin_options' ] );
		add_action( 'set_logged_in_cookie', [ $this, 'set_cookie_on_current_request' ] );
		add_filter( 'woocommerce_get_checkout_payment_url', [ $this, 'get_checkout_payment_url' ], 10, 2 );

		// Note: display error is in the parent class.
		add_action( 'admin_notices', [ $this, 'display_errors' ], 9999 );
	}

	/**
	 * Checks if gateway should be available to use.
	 */
	public function is_available() {
		if ( is_add_payment_method_page() ) {
			return false;
		}

		return parent::is_available();
	}

	/**
	 * Get_icon function.
	 *
	 * @return string|null
	 */
	public function get_icon() {
		return apply_filters( 'woocommerce_gateway_icon', null, $this->id );
	}

	/**
	 * Initialise Gateway Settings Form Fields
	 */
	public function init_form_fields() {
		$this->form_fields = require dirname( __FILE__ ) . '/admin/youcanpay-settings.php';
	}

	/**
	 * Payment form on checkout page
	 */
	public function payment_fields() {
		global $wp;
		$user                 = wp_get_current_user();
		$display_tokenization = $this->supports( 'tokenization' ) && is_checkout();
		$user_email           = '';
		$description          = $this->get_description();
		$description          = ! empty( $description ) ? $description : '';
		$firstname            = '';
		$lastname             = '';

		// If paying from order, we need to get total from order not cart.
		if ( WC_YouCanPay_Helper::is_paying_from_order() ) { // wpcs: csrf ok.
			$order      = wc_get_order( wc_clean( $wp->query_vars['order-pay'] ) ); // wpcs: csrf ok, sanitization ok.
			$user_email = $order->get_billing_email();
		} else {
			if ( $user->ID ) {
				$user_email = get_user_meta( $user->ID, 'billing_email', true );
				if ( ! $user_email ) {
					$user_email = $user->user_email;
				}
			}
		}

		if ( is_add_payment_method_page() ) {
			$firstname = $user->user_firstname;
			$lastname  = $user->user_lastname;
		}

		ob_start();

		echo '<div
			id="youcanpay-payment-data"
			data-email="' . esc_attr( $user_email ) . '"
			data-full-name="' . esc_attr( $firstname . ' ' . $lastname ) . '"
			data-currency="' . esc_attr( strtolower( get_woocommerce_currency() ) ) . '"
		>';

		if ( $this->sandbox_mode ) {
			/* translators: link to YouCan Pay testing page */
			$description .= ' ' . sprintf( __( 'SANDBOX MODE ENABLED. In sandbox mode, you can use the card number 4242424242424242 with 112 CVC and 10/24 date or check the <a href="%s" target="_blank">Testing YouCan Pay documentation</a> for more card numbers.',
					'youcan-pay' ),
					'https://pay.youcan.shop/docs#testing-and-test-cards' );
		}

		$description = trim( $description );

		echo apply_filters( 'wc_youcanpay_description',
			wpautop( wp_kses_post( $description ) ),
			$this->id ); // wpcs: xss ok.

		if ( $display_tokenization ) {
			$this->tokenization_script();
			$this->saved_payment_methods();
		}

		$this->elements_form();

		echo '</div>';

		ob_end_flush();
	}

	/**
	 * Renders the YouCan Pay elements form.
	 */
	public function elements_form() {
		?>
        <fieldset id="wc-<?php
		echo esc_attr( $this->id ); ?>-cc-form" class="wc-credit-card-form wc-payment-form"
                  style="background:transparent;">
			<?php
			do_action( 'woocommerce_credit_card_form_start', $this->id ); ?>

            <div class="form-row form-row-wide" id="payment-card"></div>
            <script>
                jQuery(function () {
                    if (typeof (window.setupYouCanPayForm) !== "undefined") {
                        window.setupYouCanPayForm();
                    }
                });
            </script>

            <div class="clear"></div>

            <!-- Used to display form errors -->
            <div class="youcanpay-source-errors" role="alert"></div>
			<?php
			do_action( 'woocommerce_credit_card_form_end', $this->id ); ?>
            <div class="clear"></div>
        </fieldset>
		<?php
	}

	/**
	 * Maybe override the parent admin_options method.
	 */
	public function admin_options() {
		parent::admin_options();
	}

	/**
	 * Returns the JavaScript configuration object used on the product, cart, and checkout pages.
	 *
	 * @return array  The configuration object to be loaded to JS.
	 * @throws WC_YouCanPay_Exception
	 */
	public function javascript_params() {
		$youcanpay_params = [
			'title'            => $this->title,
			'key'              => $this->public_key,
			'youcanpay'        => self::ID,
			'is_test_mode'     => $this->is_in_test_mode(),
			'youcanpay_locale' => WC_YouCanPay_Helper::convert_wc_locale_to_youcanpay_locale( get_locale() ),
			'checkout_url'     => get_site_url() . '?wc-ajax=checkout',
			'is_pre_order'     => WC_YouCanPay_Order_Action_Enum::get_incomplete(),
			'order_status'     => array(
				'incomplete' => WC_YouCanPay_Order_Action_Enum::get_incomplete(),
				'pre_order'  => WC_YouCanPay_Order_Action_Enum::get_pre_order(),
			),
		];

		if ( array_key_exists( 'order-pay', $_GET ) ) {
			$response = $this->validated_order_and_process_payment( wc_sanitize_order_id( $_GET['order-pay'] ) );
			/** @var Token $token */
			$token    = $response['token'];
			$redirect = $response['redirect'];

			$youcanpay_params['token_transaction'] = ( isset( $token ) ) ? $token->getId() : 0;
			$youcanpay_params['is_pre_order']      = WC_YouCanPay_Order_Action_Enum::get_pre_order();
			$youcanpay_params['redirect']          = $redirect;
		}

		return array_merge( $youcanpay_params, WC_YouCanPay_Helper::get_localized_messages() );
	}

	/**
	 * Payment_scripts function.
	 *
	 * Output scripts used for youcanpay payment
	 */
	public function payment_scripts() {
		if (
			! is_product()
			&& ! WC_YouCanPay_Helper::has_cart_or_checkout_on_current_page()
			&& ! array_key_exists( 'pay_for_order', $_GET )
			&& ! is_add_payment_method_page()
			&& ! array_key_exists( 'change_payment_method', $_GET ) // wpcs: csrf ok.
			|| ( is_order_received_page() )
		) {
			return;
		}

		// If YouCan Pay is not enabled bail.
		if ( 'no' === $this->enabled ) {
			return;
		}

		// If keys are not set bail.
		if ( ! $this->are_keys_set() ) {
			WC_YouCanPay_Logger::info( 'Keys are not set correctly.' );

			return;
		}

		// If no SSL bail.
		if ( ! $this->sandbox_mode && ! is_ssl() ) {
			WC_YouCanPay_Logger::info( 'YouCan Pay live mode requires SSL.' );

			return;
		}

		add_action( 'wp_head', [ $this, 'pw_load_scripts' ] );
	}

	public function pw_load_scripts() {
		$extension = '.js';
		if ( ! $this->sandbox_mode ) {
			$extension = '.min.js';
		}

		wp_enqueue_script( 'py-script', WC_YouCanPay_Api_Enum::get_javascript_url() );
		wp_enqueue_script( 'youcan-pay-script', WC_YOUCAN_PAY_PLUGIN_URL . '/assets/js/youcan-pay' . $extension );
		wp_localize_script( 'py-script', 'youcan_pay_script_vars', $this->javascript_params() );
	}

	/**
	 * Process the payment
	 *
	 * @param int $order_id Reference.
	 *
	 * @return array
	 * @throws Exception If payment will not be accepted.
	 */
	public function process_payment( $order_id ) {
		try {
			$response = $this->validated_order_and_process_payment( $order_id );
			$token    = $response['token'];
			$order    = $response['order'];
			$redirect = $response['redirect'];

			return [
				'result'            => 'success',
				'redirect'          => $redirect,
				'token_transaction' => ( isset( $token ) ) ? $token->getId() : 0
			];
		} catch ( WC_YouCanPay_Exception $e ) {
			wc_add_notice( $e->getLocalizedMessage(), 'error' );
			WC_YouCanPay_Logger::alert( 'wc youcan pay exception', array(
				'exception.message' => $e->getMessage()
			) );

			if ( isset( $order ) ) {
				$order->update_status( 'failed' );
			}
			$this->send_failed_order_email( $order_id );

			return [
				'result'   => 'fail',
				'redirect' => '',
			];
		}
	}

	/**
	 * @throws WC_YouCanPay_Exception
	 */
	public function validated_order_and_process_payment( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( ! isset( $order ) ) {
			WC_YouCanPay_Logger::info( 'arrived on process payment: order not exists', array(
				'method'   => 'YouCan Pay (Credit Card)',
				'code'     => '#0021',
				'order_id' => $order_id,
			) );

			throw new WC_YouCanPay_Exception( 'Order not found',
				__( 'Fatal error, please try again or contact support.', 'youcan-pay' ) );
		}

		$this->validate_minimum_order_amount( $order );

		$order->set_status( 'on-hold' );

		$redirect = $this->get_youcanpay_return_url( $order, self::ID );

		$token = WC_YouCanPay_API::create_token(
			$order,
			$order->get_total(),
			$order->get_currency(),
			$redirect
		);

		if ( is_wp_error( $token ) || empty( $token ) ) {
			WC_YouCanPay_Logger::info( 'there was a problem connecting to the YouCan Pay API endpoint', array(
				'order_id' => $order->get_id(),
			) );

			throw new WC_YouCanPay_Exception( print_r( $token, true ),
				__( 'There was a problem connecting to the YouCan Pay API endpoint.', 'youcan-pay' ) );
		}

		return array(
			'token'    => $token,
			'order'    => $order,
			'redirect' => $redirect,
		);
	}

	/**
	 * Proceed with current request using new login session (to ensure consistent nonce).
	 */
	public function set_cookie_on_current_request( $cookie ) {
		$_COOKIE[ LOGGED_IN_COOKIE ] = $cookie;
	}

	/**
	 * Preserves the "wc-youcanpay-confirmation" URL parameter so the user can complete the SCA authentication after logging in.
	 *
	 * @param string $pay_url Current computed checkout URL for the given order.
	 * @param WC_Order $order Order object.
	 *
	 * @return string Checkout URL for the given order.
	 */
	public function get_checkout_payment_url( $pay_url, $order ) {
		global $wp;
		if ( array_key_exists( 'wc-youcanpay-confirmation', $_GET )
		     && isset( $wp->query_vars['order-pay'] )
		     && $wp->query_vars['order-pay'] == $order->get_id()
		) {
			$pay_url = add_query_arg( 'wc-youcanpay-confirmation', 1, $pay_url );
		}

		return $pay_url;
	}

	/**
	 * Checks whether new keys are being entered when saving options.
	 */
	public function process_admin_options() {
		parent::process_admin_options();
	}

	/**
	 * @throws Exception
	 */
	public function validate_public_key_field( $key, $value ) {
		$value = $this->validate_text_field( $key, $value );
		if ( ! empty( $value ) && ! preg_match( '/^pub_/', $value ) ) {
			throw new Exception( __( 'The "Production Public key" should start with "pub", enter the correct key.',
				'youcan-pay' ) );
		}

		return $value;
	}

	/**
	 * @throws Exception
	 */
	public function validate_private_key_field( $key, $value ) {
		$value = $this->validate_text_field( $key, $value );
		if ( ! empty( $value ) && ! preg_match( '/^pri_/', $value ) ) {
			throw new Exception( __( 'The "Production Private key" should start with "pri", enter the correct key.',
				'youcan-pay' ) );
		}

		return $value;
	}

	/**
	 * @throws Exception
	 */
	public function validate_sandbox_public_key_field( $key, $value ) {
		$value = $this->validate_text_field( $key, $value );
		if ( ! empty( $value ) && ! preg_match( '/^pub_sandbox_/', $value ) ) {
			throw new Exception( __( 'The "Sandbox Public key" should start with "pub_sandbox", enter the correct key.',
				'youcan-pay' ) );
		}

		return $value;
	}

	/**
	 * @throws Exception
	 */
	public function validate_sandbox_private_key_field( $key, $value ) {
		$value = $this->validate_text_field( $key, $value );
		if ( ! empty( $value ) && ! preg_match( '/^pri_sandbox_/', $value ) ) {
			throw new Exception( __( 'The "Sandbox Private key" should start with "pri_sandbox", enter the correct key.',
				'youcan-pay' ) );
		}

		return $value;
	}

	/**
	 * Checks whether the gateway is enabled.
	 *
	 * @return bool The result.
	 */
	public function is_enabled() {
		return 'yes' === $this->get_option( 'enabled' );
	}

	/**
	 * Disables gateway.
	 */
	public function disable() {
		$this->update_option( 'enabled', 'no' );
	}

	/**
	 * Enables gateway.
	 */
	public function enable() {
		$this->update_option( 'enabled', 'yes' );
	}

	/**
	 * Returns whether test_mode is active for the gateway.
	 *
	 * @return boolean Sandbox mode enabled if true, disabled if false.
	 */
	public function is_in_test_mode() {
		return 'yes' === $this->get_option( 'sandbox_mode' );
	}
}
