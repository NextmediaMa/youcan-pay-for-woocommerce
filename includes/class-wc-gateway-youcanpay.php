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
	 * Inline CC form styling
	 *
	 * @var string
	 */
	//public $inline_cc_form;

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
		$this->method_title = __( 'YouCanPay', 'woocommerce-youcan-pay' );
		/* translators: 1) link to YouCanPay register page 2) link to YouCanPay api keys page */
		$this->method_description = __( 'YouCanPay works by adding payment fields on the checkout and then sending the details to YouCanPay for verification.',
			'woocommerce-youcan-pay' );
		$this->has_fields         = true;
		$this->supports           = [
			'products',
			'refunds',
			'tokenization',
			'add_payment_method',
			'pre-orders',
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
		add_action( 'woocommerce_admin_order_totals_after_total', [ $this, 'display_order_fee' ] );
		add_action( 'woocommerce_admin_order_totals_after_total', [ $this, 'display_order_payout' ], 20 );
		//add_action( 'woocommerce_customer_save_address', [ $this, 'show_update_card_notice' ], 10, 2 );
		add_filter( 'woocommerce_available_payment_gateways', [ $this, 'prepare_order_pay_page' ] );
		//add_action( 'woocommerce_account_view-order_endpoint', [ $this, 'check_intent_status_on_order_page' ], 1 );
		add_filter( 'woocommerce_payment_successful_result', [ $this, 'modify_successful_payment_result' ], 99999, 2 );
		add_action( 'set_logged_in_cookie', [ $this, 'set_cookie_on_current_request' ] );
		add_filter( 'woocommerce_get_checkout_payment_url', [ $this, 'get_checkout_payment_url' ], 10, 2 );
		add_filter( 'woocommerce_settings_api_sanitized_fields_' . $this->id,
			[ $this, 'settings_api_sanitized_fields' ] );

		// Note: display error is in the parent class.
		add_action( 'admin_notices', [ $this, 'display_errors' ], 9999 );
	}

	/**
	 * Checks if gateway should be available to use.
	 *
	 * @since 4.0.2
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
	 * @version 5.6.2
	 * @since 1.0.0
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
			/* translators: link to YouCanPay testing page */
			$description .= ' ' . sprintf(__( 'TEST MODE ENABLED. In test mode, you can use the card number 4242424242424242 with 112 CVC and 10/24 date or check the <a href="%s" target="_blank">Testing YouCan Pay documentation</a> for more card numbers.',
					'woocommerce-youcan-pay' ), 'https://pay.youcan.shop/docs#testing-and-test-cards' );
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

		if ( apply_filters( 'wc_youcanpay_display_save_payment_method_checkbox',
				$display_tokenization ) && ! is_add_payment_method_page() && ! isset( $_GET['change_payment_method'] ) ) { // wpcs: csrf ok.

			$this->save_payment_method_checkbox();
		}

		do_action( 'wc_youcanpay_payment_fields_youcanpay', $this->id );

		echo '</div>';

		ob_end_flush();
	}

	/**
	 * Renders the YouCanPay elements form.
	 *
	 * @since 4.0.0
	 * @version 4.0.0
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
        if (! isset($token)) {
	        $token_id = $token->getId();
        }

		$youcanpay_params['token_transaction'] = $token_id;

		return array_merge( $youcanpay_params, WC_YouCanPay_Helper::get_localized_messages() );
	}

	/**
	 * Payment_scripts function.
	 *
	 * Outputs scripts used for youcanpay payment
	 *
	 * @since 3.1.0
	 * @version 4.0.0
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

		// If YouCanPay is not enabled bail.
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
			WC_YouCanPay_Logger::log( 'YouCanPay live mode requires SSL.' );

			return;
		}

		//$suffix = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';

		wp_register_style(
                'youcanpay_styles',
            plugins_url( 'assets/css/youcanpay-styles.css', WC_YOUCAN_PAY_MAIN_FILE ),
			[],
			WC_YOUCAN_PAY_VERSION
        );
		wp_enqueue_style( 'youcanpay_styles' );

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
	 * @since 1.0.0
	 * @since 4.1.0 Add 4th parameter to track previous error.
	 * @version 5.6.0
	 *
	 */
	public function process_payment( $order_id, $retry = true, $force_save_source = false, $previous_error = false, $use_order_source = false ) {
		try {
			$order = wc_get_order( $order_id );
			if ( ! isset( $order ) ) {
				throw new WC_YouCanPay_Exception( 'Order not found', __( 'Sorry, this order does not exist.', 'woocommerce-youcan-pay' ) );
			}

			if ( ! isset( $_POST['transaction_id'] ) ) {
				throw new WC_YouCanPay_Exception( 'Transaction must not be null', __( 'Sorry, transaction must not be null.', 'woocommerce-youcan-pay' ) );
			}

			$transaction = WC_YouCanPay_API::get_transaction( $_POST['transaction_id'] );
			if ( ! isset( $transaction ) ) {
				throw new WC_YouCanPay_Exception( 'Transaction not found', __( 'Sorry, this transaction does not exist.', 'woocommerce-youcan-pay' ) );
			}

			if ( $transaction->getOrderId() !== WC()->cart->get_cart_hash() ) {
				throw new WC_YouCanPay_Exception( 'Fatal error try again', __( 'Fatal error, please try again or contact support.', 'woocommerce-youcan-pay' ) );
			}

			$this->validate_minimum_order_amount( $order );

			if ( $transaction->getStatus() === 1 ) {
				WC_YouCanPay_Logger::log( "Info: Begin processing payment for order $order_id for the amount of {$order->get_total()}" );

				$order->payment_complete( $transaction->getId() );

				if ( isset( WC()->cart ) ) {
					WC()->cart->empty_cart();
				}

				$this->unlock_order_payment( $order );

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

			return [
				'result'   => 'fail',
				'redirect' => '',
			];
		}
	}

	/**
	 * Saves payment method
	 *
	 * @param object $source_object
	 *
	 * @throws WC_YouCanPay_Exception
	 */
	public function save_payment_method( $source_object ) {
		$user_id  = get_current_user_id();
		$customer = new WC_YouCanPay_Customer( $user_id );

		if ( ( $user_id && 'reusable' === $source_object->usage ) ) {
			$response = $customer->add_source( $source_object->id );

			if ( ! empty( $response->error ) ) {
				throw new WC_YouCanPay_Exception( print_r( $response, true ),
					$this->get_localized_error_message_from_response( $response ) );
			}
			if ( is_wp_error( $response ) ) {
				throw new WC_YouCanPay_Exception( $response->get_error_message(), $response->get_error_message() );
			}
		}
	}

	/**
	 * Displays the YouCanPay fee
	 *
	 * @param int $order_id The ID of the order.
	 *
	 * @since 4.1.0
	 *
	 */
	public function display_order_fee( $order_id ) {
		if ( apply_filters( 'wc_youcanpay_hide_display_order_fee', false, $order_id ) ) {
			return;
		}

		$order = wc_get_order( $order_id );

		$fee      = WC_YouCanPay_Helper::get_youcanpay_fee( $order );
		$currency = WC_YouCanPay_Helper::get_youcanpay_currency( $order );

		if ( ! $fee || ! $currency ) {
			return;
		}

		?>

        <tr>
            <td class="label youcanpay-fee">
				<?php
				echo wc_help_tip( __( 'This represents the fee YouCanPay collects for the transaction.',
					'woocommerce-youcan-pay' ) ); // wpcs: xss ok. ?>
				<?php
				esc_html_e( 'YouCanPay Fee:', 'woocommerce-youcan-pay' ); ?>
            </td>
            <td width="1%"></td>
            <td class="total">
                -<?php
				echo wc_price( $fee, [ 'currency' => $currency ] ); // wpcs: xss ok. ?>
            </td>
        </tr>

		<?php
	}

	/**
	 * Displays the net total of the transaction without the charges of YouCanPay.
	 *
	 * @param int $order_id The ID of the order.
	 *
	 * @since 4.1.0
	 *
	 */
	public function display_order_payout( $order_id ) {
		if ( apply_filters( 'wc_youcanpay_hide_display_order_payout', false, $order_id ) ) {
			return;
		}

		$order = wc_get_order( $order_id );

		$net      = WC_YouCanPay_Helper::get_youcanpay_net( $order );
		$currency = WC_YouCanPay_Helper::get_youcanpay_currency( $order );

		if ( ! $net || ! $currency ) {
			return;
		}

		?>

        <tr>
            <td class="label youcanpay-payout">
				<?php
				echo wc_help_tip( __( 'This represents the net total that will be credited to your YouCanPay bank account. This may be in the currency that is set in your YouCanPay account.',
					'woocommerce-youcan-pay' ) ); // wpcs: xss ok. ?>
				<?php
				esc_html_e( 'YouCanPay Payout:', 'woocommerce-youcan-pay' ); ?>
            </td>
            <td width="1%"></td>
            <td class="total">
				<?php
				echo wc_price( $net, [ 'currency' => $currency ] ); // wpcs: xss ok. ?>
            </td>
        </tr>

		<?php
	}

	/**
	 * Retries the payment process once an error occured.
	 *
	 * @param object $response The response from the YouCanPay API.
	 * @param WC_Order $order An order that is being paid for.
	 * @param bool $retry A flag that indicates whether another retry should be attempted.
	 * @param bool $force_save_source Force save the payment source.
	 * @param mixed $previous_error Any error message from previous request.
	 * @param bool $use_order_source Whether to use the source, which should already be attached to the order.
	 *
	 * @return array|void
	 * @throws WC_YouCanPay_Exception        If the payment is not accepted.
	 * @since 4.2.0
	 */
	public function retry_after_error(
		$response,
		$order,
		$retry,
		$force_save_source,
		$previous_error,
		$use_order_source
	) {
		if ( ! $retry ) {
			$localized_message = __( 'Sorry, we are unable to process your payment at this time. Please retry later.',
				'woocommerce-youcan-pay' );
			$order->add_order_note( $localized_message );
			throw new WC_YouCanPay_Exception( print_r( $response, true ),
				$localized_message ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.
		}

		// Don't do anymore retries after this.
		if ( 5 <= $this->retry_interval ) {
			return $this->process_payment( $order->get_id(),
				false,
				$force_save_source,
				$response->error,
				$previous_error );
		}

		sleep( $this->retry_interval );
		$this->retry_interval ++;

		return $this->process_payment( $order->get_id(),
			true,
			$force_save_source,
			$response->error,
			$previous_error,
			$use_order_source );
	}

	/**
	 * Adds the necessary hooks to modify the "Pay for order" page in order to clean
	 * it up and prepare it for the YouCanPay PaymentIntents modal to confirm a payment.
	 *
	 * @param WC_Payment_Gateway[] $gateways A list of all available gateways.
	 *
	 * @return WC_Payment_Gateway[]          Either the same list or an empty one in the right conditions.
	 * @since 4.2
	 */
	public function prepare_order_pay_page( $gateways ) {
		if ( ! is_wc_endpoint_url( 'order-pay' ) || ! isset( $_GET['wc-youcanpay-confirmation'] ) ) { // wpcs: csrf ok.
			return $gateways;
		}

		try {
			$this->prepare_intent_for_order_pay_page();
		} catch ( WC_YouCanPay_Exception $e ) {
			// Just show the full order pay page if there was a problem preparing the Payment Intent
			return $gateways;
		}

		add_filter( 'woocommerce_checkout_show_terms', '__return_false' );
		add_filter( 'woocommerce_pay_order_button_html', '__return_false' );
		add_filter( 'woocommerce_available_payment_gateways', '__return_empty_array' );
		add_filter( 'woocommerce_no_available_payment_methods_message',
			[ $this, 'change_no_available_methods_message' ] );
		add_action( 'woocommerce_pay_order_after_submit', [ $this, 'render_payment_intent_inputs' ] );

		return [];
	}

	/**
	 * Changes the text of the "No available methods" message to one that indicates
	 * the need for a PaymentIntent to be confirmed.
	 *
	 * @return string the new message.
	 * @since 4.2
	 */
	public function change_no_available_methods_message() {
		return wpautop( __( "Almost there!\n\nYour order has already been created, the only thing that still needs to be done is for you to authorize the payment with your bank.",
			'woocommerce-youcan-pay' ) );
	}

	/**
	 * Prepares the Payment Intent for it to be completed in the "Pay for Order" page.
	 *
	 * @param WC_Order|null $order Order object, or null to get the order from the "order-pay" URL parameter
	 *
	 * @throws WC_YouCanPay_Exception
	 * @since 4.3
	 */
	public function prepare_intent_for_order_pay_page( $order = null ) {
		if ( ! isset( $order ) || empty( $order ) ) {
			$order = wc_get_order( absint( get_query_var( 'order-pay' ) ) );
		}
		$intent = $this->get_intent_from_order( $order );

		if ( ! $intent ) {
			throw new WC_YouCanPay_Exception(
				'Payment Intent not found',
				sprintf(
				/* translators: %s is the order Id */
					__( 'Payment Intent not found for order #%s', 'woocommerce-youcan-pay' ),
					$order->get_id()
				)
			);
		}

		if ( 'requires_payment_method' === $intent->status && isset( $intent->last_payment_error )
		     && 'authentication_required' === $intent->last_payment_error->code ) {
			$level3_data = $this->get_level3_data_from_order( $order );
			$intent      = WC_YouCanPay_API::request_with_level3_data(
				[
					'payment_method' => $intent->last_payment_error->source->id,
				],
				'payment_intents/' . $intent->id . '/confirm',
				$level3_data,
				$order
			);

			if ( isset( $intent->error ) ) {
				throw new WC_YouCanPay_Exception( print_r( $intent, true ), $intent->error->message );
			}
		}

		$this->order_pay_intent = $intent;
	}

	/**
	 * Renders hidden inputs on the "Pay for Order" page in order to let YouCanPay handle PaymentIntents.
	 *
	 * @param WC_Order|null $order Order object, or null to get the order from the "order-pay" URL parameter
	 *
	 * @throws WC_YouCanPay_Exception
	 * @since 4.2
	 */
	public function render_payment_intent_inputs( $order = null ) {
		if ( ! isset( $order ) || empty( $order ) ) {
			$order = wc_get_order( absint( get_query_var( 'order-pay' ) ) );
		}
		if ( ! isset( $this->order_pay_intent ) ) {
			$this->prepare_intent_for_order_pay_page( $order );
		}

		$verification_url = add_query_arg(
			[
				'order'            => $order->get_id(),
				'nonce'            => wp_create_nonce( 'wc_youcanpay_confirm_pi' ),
				'redirect_to'      => rawurlencode( $this->get_return_url( $order ) ),
				'is_pay_for_order' => true,
			],
			WC_AJAX::get_endpoint( 'wc_youcanpay_verify_intent' )
		);

		echo '<input type="hidden" id="youcanpay-intent-id" value="' . esc_attr( $this->order_pay_intent->client_secret ) . '" />';
		echo '<input type="hidden" id="youcanpay-intent-return" value="' . esc_attr( $verification_url ) . '" />';
	}

	/**
	 * Adds an error message wrapper to each saved method.
	 *
	 * @param WC_Payment_Token $token Payment Token.
	 *
	 * @return string                 Generated payment method HTML
	 * @since 4.2.0
	 */
	public function get_saved_payment_method_option_html( $token ) {
		$html          = parent::get_saved_payment_method_option_html( $token );
		$error_wrapper = '<div class="youcanpay-source-errors" role="alert"></div>';

		return preg_replace( '~</(\w+)>\s*$~', "$error_wrapper</$1>", $html );
	}

	/**
	 * Attached to `woocommerce_payment_successful_result` with a late priority,
	 * this method will combine the "naturally" generated redirect URL from
	 * WooCommerce and a payment/setup intent secret into a hash, which contains both
	 * the secret, and a proper URL, which will confirm whether the intent succeeded.
	 *
	 * @param array $result The result from `process_payment`.
	 * @param int $order_id The ID of the order which is being paid for.
	 *
	 * @return array
	 * @since 4.2.0
	 */
	public function modify_successful_payment_result( $result, $order_id ) {
		if ( ! isset( $result['payment_intent_secret'] ) && ! isset( $result['setup_intent_secret'] ) ) {
			// Only redirects with intents need to be modified.
			return $result;
		}

		// Put the final thank you page redirect into the verification URL.
		$query_params = [
			'order'       => $order_id,
			'nonce'       => wp_create_nonce( 'wc_youcanpay_confirm_pi' ),
			'redirect_to' => rawurlencode( $result['redirect'] ),
		];

		$force_save_source_value = apply_filters( 'wc_youcanpay_force_save_source', false );

		if ( $this->save_payment_method_requested() || $force_save_source_value ) {
			$query_params['save_payment_method'] = true;
		}

		$verification_url = add_query_arg( $query_params, WC_AJAX::get_endpoint( 'wc_youcanpay_verify_intent' ) );

		if ( isset( $result['payment_intent_secret'] ) ) {
			$redirect = sprintf( '#confirm-pi-%s:%s',
				$result['payment_intent_secret'],
				rawurlencode( $verification_url ) );
		} elseif ( isset( $result['setup_intent_secret'] ) ) {
			$redirect = sprintf( '#confirm-si-%s:%s',
				$result['setup_intent_secret'],
				rawurlencode( $verification_url ) );
		}

		return [
			'result'   => 'success',
			'redirect' => $redirect,
		];
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
		// Load all old values before the new settings get saved.
		$old_publishable_key      = $this->get_option( 'publishable_key' );
		$old_secret_key           = $this->get_option( 'secret_key' );
		$old_test_publishable_key = $this->get_option( 'test_publishable_key' );
		$old_test_secret_key      = $this->get_option( 'test_secret_key' );

		parent::process_admin_options();

		// Load all old values after the new settings have been saved.
		$new_publishable_key      = $this->get_option( 'publishable_key' );
		$new_secret_key           = $this->get_option( 'secret_key' );
		$new_test_publishable_key = $this->get_option( 'test_publishable_key' );
		$new_test_secret_key      = $this->get_option( 'test_secret_key' );

		// Checks whether a value has transitioned from a non-empty value to a new one.
		$has_changed = function ( $old_value, $new_value ) {
			return ! empty( $old_value ) && ( $old_value !== $new_value );
		};

		// Look for updates.
		if (
			$has_changed( $old_publishable_key, $new_publishable_key )
			|| $has_changed( $old_secret_key, $new_secret_key )
			|| $has_changed( $old_test_publishable_key, $new_test_publishable_key )
			|| $has_changed( $old_test_secret_key, $new_test_secret_key )
		) {
			update_option( 'wc_youcanpay_show_changed_keys_notice', 'yes' );
		}
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
	 * Ensures the statement descriptor about to be saved to options does not contain any invalid characters.
	 *
	 * @param $settings WC_Settings_API settings to be filtered
	 *
	 * @return Filtered settings
	 * @since 4.8.0
	 */
	public function settings_api_sanitized_fields( $settings ) {
		if ( is_array( $settings ) ) {
		}

		return $settings;
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

	/**
	 * Determines whether the "automatic" or "manual" capture setting is enabled.
	 *
	 * @return bool
	 */
	public function is_automatic_capture_enabled() {
		return empty( $this->get_option( 'capture' ) ) || $this->get_option( 'capture' ) === 'yes';
	}

	/**
	 * Validates statement descriptor value
	 *
	 * @param string $value Posted Value.
	 * @param int $max_length Maximum statement length.
	 *
	 * @return string                   Sanitized statement descriptor.
	 * @throws InvalidArgumentException When statement descriptor is invalid.
	 */
	public function validate_account_statement_descriptor_field( $value, $max_length ) {
		// Since the value is escaped, and we are saving in a place that does not require escaping, apply stripslashes.
		$value = trim( stripslashes( $value ) );

		// Validation can be done with a single regex but splitting into multiple for better readability.
		$valid_length   = '/^.{5,' . $max_length . '}$/';
		$has_one_letter = '/^.*[a-zA-Z]+/';
		$no_specials    = '/^[^*"\'<>]*$/';

		if (
			! preg_match( $valid_length, $value ) ||
			! preg_match( $has_one_letter, $value ) ||
			! preg_match( $no_specials, $value )
		) {
			throw new InvalidArgumentException( __( 'Customer bank statement is invalid. Statement should be between 5 and 22 characters long, contain at least single Latin character and does not contain special characters: \' " * &lt; &gt;',
				'woocommerce-youcan-pay' ) );
		}

		return $value;
	}
}
