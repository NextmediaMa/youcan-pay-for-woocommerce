<?php

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
	 * Should we capture Credit cards
	 *
	 * @var bool
	 */
	public $capture;

	/**
	 * Alternate credit card statement name
	 *
	 * @var bool
	 */
	public $statement_descriptor;

	/**
	 * API access secret key
	 *
	 * @var string
	 */
	public $secret_key;

	/**
	 * Api access publishable key
	 *
	 * @var string
	 */
	public $publishable_key;

	/**
	 * Do we accept Payment Request?
	 *
	 * @var bool
	 */
	public $payment_request;

	/**
	 * Is test mode active?
	 *
	 * @var bool
	 */
	public $testmode;

	/**
	 * Pre Orders Object
	 *
	 * @var object
	 */
	public $pre_orders;

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->id           = self::ID;
		$this->method_title = __( 'YouCan Pay', 'woocommerce-youcan-pay' );
		/* translators: 1) link to YouCan Pay register page 2) link to YouCan Pay api keys page */
		$this->method_description = __( 'YouCan Pay works by adding payment fields on the checkout and then sending the details to YouCan Pay for verification.',
			'woocommerce-youcan-pay' );
		$this->has_fields         = true;
		$this->supports           = [
			'products',
			'refunds',
			//'tokenization',
			//'add_payment_method',
			//'pre-orders',
		];

		// Load the form fields.
		$this->init_form_fields();

		// Load the settings.
		$this->init_settings();

		// Get setting values.
		$this->title           = $this->get_option( 'title' );
		$this->description     = $this->get_option( 'description' );
		$this->enabled         = $this->get_option( 'enabled' );
		$this->testmode        = 'yes' === $this->get_option( 'testmode' );
		$this->capture         = 'yes' === $this->get_option( 'capture', 'yes' );
		$this->secret_key      = $this->testmode ? $this->get_option( 'test_secret_key' ) : $this->get_option( 'secret_key' );
		$this->publishable_key = $this->testmode ? $this->get_option( 'test_publishable_key' ) : $this->get_option( 'publishable_key' );

		WC_YouCanPay_API::set_secret_key( $this->secret_key );

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
		unset( $this->form_fields['title_upe'] );
	}

	/**
	 * Payment form on checkout page
	 */
	public function payment_fields() {
		global $wp;
		$user                 = wp_get_current_user();
		$display_tokenization = $this->supports( 'tokenization' ) && is_checkout();
		$total                = WC()->cart->total;
		$user_email           = '';
		$description          = $this->get_description();
		$description          = ! empty( $description ) ? $description : '';
		$firstname            = '';
		$lastname             = '';

		// If paying from order, we need to get total from order not cart.
		if ( isset( $_GET['pay_for_order'] ) && ! empty( $_GET['key'] ) ) { // wpcs: csrf ok.
			$order      = wc_get_order( wc_clean( $wp->query_vars['order-pay'] ) ); // wpcs: csrf ok, sanitization ok.
			$total      = $order->get_total();
			$user_email = $order->get_billing_email();
		} else {
			if ( $user->ID ) {
				$user_email = get_user_meta( $user->ID, 'billing_email', true );
				$user_email = $user_email ? $user_email : $user->user_email;
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

		if ( $this->testmode ) {
			/* translators: link to YouCan Pay testing page */
			$description .= ' ' . sprintf(__( 'TEST MODE ENABLED. In test mode, you can use the card number 4242424242424242 with 112 CVC and 10/24 date or check the <a href="%s" target="_blank">Testing YouCan Pay documentation</a> for more card numbers.',
					'woocommerce-youcan-pay' ), 'https://pay.youcan.shop/docs#testing-and-test-cards' );
		}

		$description = trim( $description );

		echo apply_filters( 'wc_youcanpay_description', wpautop( wp_kses_post( $description ) ), $this->id ); // wpcs: xss ok.

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

            <input type="hidden" name="transaction_id" id="transaction-id">

            <div class="form-row form-row-wide" id="payment-card"></div>

            <script type="text/javascript">
                jQuery(function ($) {
                    var ycPay = new YCPay(youcan_pay_script_vars.key);
                    if (parseInt(youcan_pay_script_vars.is_test_mode) === 1) {
                        ycPay.isSandboxMode = true;
                    }
                    ycPay.renderForm('#payment-card');

                    $('#place_order').on('click', function (e) {
                        e.preventDefault();
                        var $form = $(this);

                        if ($('input[name=payment_method]:checked').val() === youcan_pay_script_vars.youcanpay) {
                            ycPay.pay(youcan_pay_script_vars.token_transaction)
                                .then(function (transactionId) {
                                    $('#transaction-id').val(transactionId);
                                    $form.submit();
                                })
                                .catch(function (errorMessage) {
                                    console.log(errorMessage);
                                });
                        } else {
                            $form.submit();
                        }
                    });
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
		if ( ! WC_YouCanPay_Feature_Flags::is_upe_settings_redesign_enabled() ) {
			parent::admin_options();

			return;
		}

		do_action( 'wc_youcanpay_gateway_admin_options_wrapper', $this );
	}

	/**
	 * Returns the JavaScript configuration object used on the product, cart, and checkout pages.
	 *
	 * @return array  The configuration object to be loaded to JS.
	 */
	public function javascript_params() {
		$youcanpay_params = [
			'title'                => $this->title,
			'key'                  => $this->publishable_key,
			'youcanpay'            => self::ID,
			'is_test_mode'         => $this->is_in_test_mode(),
			'youcanpay_locale'     => WC_YouCanPay_Helper::convert_wc_locale_to_youcanpay_locale( get_locale() ),
		];

		$token_id = 0;
		$token = WC_YouCanPay_API::create_token(
			WC()->cart->get_cart_hash(),
			$this->get_order_total(),
			get_woocommerce_currency()
		);
        if (isset($token)) {
	        $token_id = $token->getId();
        }

		$youcanpay_params['token_transaction'] = $token_id;

		return array_merge( $youcanpay_params, WC_YouCanPay_Helper::get_localized_messages() );
	}

	/**
	 * Payment_scripts function.
	 *
	 * Outputs scripts used for youcanpay payment
	 */
	public function payment_scripts() {
		if (
			! is_product()
			&& ! WC_YouCanPay_Helper::has_cart_or_checkout_on_current_page()
			&& ! isset( $_GET['pay_for_order'] ) // wpcs: csrf ok.
			&& ! is_add_payment_method_page()
			&& ! isset( $_GET['change_payment_method'] ) // wpcs: csrf ok.
			&& ! ( ! empty( get_query_var( 'view-subscription' ) ) && is_callable( 'WCS_Early_Renewal_Manager::is_early_renewal_via_modal_enabled' ) )
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
			WC_YouCanPay_Logger::log( 'Keys are not set correctly.' );

			return;
		}

		// If no SSL bail.
		if ( ! $this->testmode && ! is_ssl() ) {
			WC_YouCanPay_Logger::log( 'YouCan Pay live mode requires SSL.' );

			return;
		}

		add_action( 'wp_head', [ $this, 'pw_load_scripts' ] );
	}

	public function pw_load_scripts() {
		wp_enqueue_script( 'py-script', 'https://pay.youcan.shop/js/ycpay.js');
		wp_localize_script( 'py-script', 'youcan_pay_script_vars', $this->javascript_params() );
	}

	/**
	 * Process the payment
	 *
	 * @param int $order_id Reference.
	 * @param bool $retry Should we retry on fail.
	 * @param bool $force_save_source Force save the payment source.
	 * @param mix $previous_error Any error message from previous request.
	 * @param bool $use_order_source Whether to use the source, which should already be attached to the order.
	 *
	 * @return array|void
	 * @throws Exception If payment will not be accepted.
	 */
	public function process_payment( $order_id, $retry = true, $force_save_source = false, $previous_error = false, $use_order_source = false ) {
		try {
			$order = wc_get_order( $order_id );
			if ( ! isset( $order ) ) {
				WC_YouCanPay_Logger::log( "arrived on process payment: order not exists" . PHP_EOL
				                          . print_r( 'Payment method: YouCan Pay (Credit Card)', true ) . PHP_EOL
				                          . print_r( 'Code: #0021', true ) . PHP_EOL
				                          . print_r( 'Order Id: ', true ) . PHP_EOL
				                          . print_r( $order_id, true )
				);
				throw new WC_YouCanPay_Exception( 'Order not found', __( 'Fatal error! Please contact support.', 'woocommerce-youcan-pay' ) );
			}

			if ( ! isset( $_POST['transaction_id'] ) ) {
				WC_YouCanPay_Logger::log( "arrived on process payment: transaction_id is null" . PHP_EOL
				                          . print_r( 'Payment method: YouCan Pay (Credit Card)', true ) . PHP_EOL
				                          . print_r( 'Code: #0022', true ) . PHP_EOL
				                          . print_r( 'Order Id: ', true ) . PHP_EOL
				                          . print_r( $order->get_id(), true )
				);
				throw new WC_YouCanPay_Exception( 'Transaction must not be null', __( 'Sorry, transaction must not be null.', 'woocommerce-youcan-pay' ) );
			}

			$transaction_id = $_POST['transaction_id'] ?? '';
			$transaction = WC_YouCanPay_API::get_transaction($transaction_id);

			if ( ! isset( $transaction ) ) {
				WC_YouCanPay_Logger::log( "arrived on process payment: transaction not exists" . PHP_EOL
				                          . print_r( 'Payment method: YouCan Pay (Credit Card)', true ) . PHP_EOL
				                          . print_r( 'Code: #0023', true ) . PHP_EOL
				                          . print_r( 'Transaction Id: ', true ) . PHP_EOL
				                          . print_r( $transaction_id, true ) . PHP_EOL
				                          . print_r( 'Order Id: ', true ) . PHP_EOL
				                          . print_r( $order->get_id(), true )
				);
				throw new WC_YouCanPay_Exception( 'Transaction not found', __( 'Please try again, This payment has been canceled!', 'woocommerce-youcan-pay' ) );
			}

			if ( $transaction->getOrderId() !== WC()->cart->get_cart_hash() ) {
				WC_YouCanPay_Logger::log( "arrived on process payment: order not identical with transaction" . PHP_EOL
				                          . print_r( 'Payment method: YouCan Pay (Credit Card)', true ) . PHP_EOL
				                          . print_r( 'Code: #0024', true ) . PHP_EOL
				                          . print_r( 'Transaction Id: ', true ) . PHP_EOL
				                          . print_r( $transaction->getId(), true ) . PHP_EOL
				                          . print_r( 'Transaction Order Id: ', true ) . PHP_EOL
				                          . print_r( $transaction->getOrderId(), true ) . PHP_EOL
				                          . print_r( 'Cart Hash: ', true ) . PHP_EOL
				                          . print_r( WC()->cart->get_cart_hash(), true )
				);
				throw new WC_YouCanPay_Exception( 'Fatal error try again', __( 'Fatal error, please try again or contact support.', 'woocommerce-youcan-pay' ) );
			}

			$this->validate_minimum_order_amount( $order );

			if ( $transaction->getStatus() === 1 ) {
				WC_YouCanPay_Logger::log( "info: payment complete for order {$order->get_id()} for the amount of {$order->get_total()}" );

				$order->payment_complete( $transaction->getId() );

				if ( isset( WC()->cart ) ) {
					WC()->cart->empty_cart();
				}

				return [
					'result'   => 'success',
					'redirect' => $this->get_return_url( $order ),
				];
			} else {
				throw new WC_YouCanPay_Exception( 'Transaction not completed', __( 'Sorry, payment not completed please try again.', 'woocommerce-youcan-pay' ) );
			}
		} catch ( WC_YouCanPay_Exception $e ) {
			wc_add_notice( $e->getLocalizedMessage(), 'error' );
			WC_YouCanPay_Logger::log( 'Error: ' . $e->getMessage() );

			do_action( 'wc_gateway_youcanpay_process_payment_error', $e, $order );

			/* translators: error message */
			$order->update_status( 'failed' );
			$this->send_failed_order_email( $order_id );

			return [
				'result'   => 'fail',
				'redirect' => '',
			];
		}
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
		if ( isset( $_GET['wc-youcanpay-confirmation'] ) && isset( $wp->query_vars['order-pay'] ) && $wp->query_vars['order-pay'] == $order->get_id() ) {
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

	public function validate_publishable_key_field( $key, $value ) {
		$value = $this->validate_text_field( $key, $value );
		if ( ! empty( $value ) && ! preg_match( '/^pub_/', $value ) ) {
			throw new Exception( __( 'The "Live Publishable Key" should start with "pub", enter the correct key.',
				'woocommerce-youcan-pay' ) );
		}

		return $value;
	}

	public function validate_secret_key_field( $key, $value ) {
		$value = $this->validate_text_field( $key, $value );
		if ( ! empty( $value ) && ! preg_match( '/^pri_/', $value ) ) {
			throw new Exception( __( 'The "Live Secret Key" should start with "pri", enter the correct key.',
				'woocommerce-youcan-pay' ) );
		}

		return $value;
	}

	public function validate_test_publishable_key_field( $key, $value ) {
		$value = $this->validate_text_field( $key, $value );
		if ( ! empty( $value ) && ! preg_match( '/^pub_sandbox_/', $value ) ) {
			throw new Exception( __( 'The "Test Publishable Key" should start with "pub_sandbox", enter the correct key.',
				'woocommerce-youcan-pay' ) );
		}

		return $value;
	}

	public function validate_test_secret_key_field( $key, $value ) {
		$value = $this->validate_text_field( $key, $value );
		if ( ! empty( $value ) && ! preg_match( '/^pri_sandbox_/', $value ) ) {
			throw new Exception( __( 'The "Test Secret Key" should start with "pri_sandbox", enter the correct key.',
				'woocommerce-youcan-pay' ) );
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
	 * @return boolean Test mode enabled if true, disabled if false.
	 */
	public function is_in_test_mode() {
		return 'yes' === $this->get_option( 'testmode' );
	}
}
