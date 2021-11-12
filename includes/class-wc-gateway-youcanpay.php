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
	 * Should we store the users credit cards?
	 *
	 * @var bool
	 */
	public $saved_cards;

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
		$this->method_title = __( 'YouCanPay', 'woocommerce-gateway-youcanpay' );
		/* translators: 1) link to YouCanPay register page 2) link to YouCanPay api keys page */
		$this->method_description = __( 'YouCanPay works by adding payment fields on the checkout and then sending the details to YouCanPay for verification.', 'woocommerce-gateway-youcanpay' );
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
		$this->title                = $this->get_option( 'title' );
		$this->description          = $this->get_option( 'description' );
		$this->enabled              = $this->get_option( 'enabled' );
		$this->testmode             = 'yes' === $this->get_option( 'testmode' );
		$this->capture              = 'yes' === $this->get_option( 'capture', 'yes' );
		$this->saved_cards          = 'yes' === $this->get_option( 'saved_cards' );
		$this->secret_key           = $this->testmode ? $this->get_option( 'test_secret_key' ) : $this->get_option( 'secret_key' );
		$this->publishable_key      = $this->testmode ? $this->get_option( 'test_publishable_key' ) : $this->get_option( 'publishable_key' );

		WC_YouCanPay_API::set_secret_key( $this->secret_key );

		// Hooks.
		add_action( 'wp_enqueue_scripts', [ $this, 'payment_scripts' ] );
		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, [ $this, 'process_admin_options' ] );
		add_action( 'woocommerce_admin_order_totals_after_total', [ $this, 'display_order_fee' ] );
		add_action( 'woocommerce_admin_order_totals_after_total', [ $this, 'display_order_payout' ], 20 );
		add_action( 'woocommerce_customer_save_address', [ $this, 'show_update_card_notice' ], 10, 2 );
		add_filter( 'woocommerce_available_payment_gateways', [ $this, 'prepare_order_pay_page' ] );
		add_action( 'woocommerce_account_view-order_endpoint', [ $this, 'check_intent_status_on_order_page' ], 1 );
		add_filter( 'woocommerce_payment_successful_result', [ $this, 'modify_successful_payment_result' ], 99999, 2 );
		add_action( 'set_logged_in_cookie', [ $this, 'set_cookie_on_current_request' ] );
		add_filter( 'woocommerce_get_checkout_payment_url', [ $this, 'get_checkout_payment_url' ], 10, 2 );
		add_filter( 'woocommerce_settings_api_sanitized_fields_' . $this->id, [ $this, 'settings_api_sanitized_fields' ] );

		// Note: display error is in the parent class.
		add_action( 'admin_notices', [ $this, 'display_errors' ], 9999 );
	}

	/**
	 * Checks if gateway should be available to use.
	 *
	 * @since 4.0.2
	 */
	public function is_available() {
		if ( is_add_payment_method_page() && ! $this->saved_cards ) {
			return false;
		}

		return parent::is_available();
	}

	/**
	 * Adds a notice for customer when they update their billing address.
	 *
	 * @since 4.1.0
	 * @param int    $user_id      The ID of the current user.
	 * @param string $load_address The address to load.
	 */
	public function show_update_card_notice( $user_id, $load_address ) {
		if ( ! $this->saved_cards || ! WC_YouCanPay_Payment_Tokens::customer_has_saved_methods( $user_id ) || 'billing' !== $load_address ) {
			return;
		}

		/* translators: 1) Opening anchor tag 2) closing anchor tag */
		wc_add_notice( sprintf( __( 'If your billing address has been changed for saved payment methods, be sure to remove any %1$ssaved payment methods%2$s on file and re-add them.', 'woocommerce-gateway-youcanpay' ), '<a href="' . esc_url( wc_get_endpoint_url( 'payment-methods' ) ) . '" class="wc-youcanpay-update-card-notice" style="text-decoration:underline;">', '</a>' ), 'notice' );
	}

	/**
	 * Get_icon function.
	 *
	 * @since 1.0.0
	 * @version 5.6.2
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
		$display_tokenization = $this->supports( 'tokenization' ) && is_checkout() && $this->saved_cards;
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
			$description .= ' ' . sprintf( __( 'TEST MODE ENABLED. In test mode, you can use the card number 4242424242424242 with any CVC and a valid expiration date or check the <a href="%s" target="_blank">Testing YouCanPay documentation</a> for more card numbers.', 'woocommerce-gateway-youcanpay' ), 'https://youcanpay.com/docs/testing' );
		}

		$description = trim( $description );

		echo apply_filters( 'wc_youcanpay_description', wpautop( wp_kses_post( $description ) ), $this->id ); // wpcs: xss ok.

		if ( $display_tokenization ) {
			$this->tokenization_script();
			$this->saved_payment_methods();
		}

		$this->elements_form();

		if ( apply_filters( 'wc_youcanpay_display_save_payment_method_checkbox', $display_tokenization ) && ! is_add_payment_method_page() && ! isset( $_GET['change_payment_method'] ) ) { // wpcs: csrf ok.

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
		<fieldset id="wc-<?php echo esc_attr( $this->id ); ?>-cc-form" class="wc-credit-card-form wc-payment-form" style="background:transparent;">
			<?php do_action( 'woocommerce_credit_card_form_start', $this->id ); ?>

				<div class="form-row form-row-wide">
					<label for="youcanpay-card-number"><?php esc_html_e( 'Card Number', 'woocommerce-gateway-youcanpay' ); ?> <span class="required">*</span></label>
					<div class="youcanpay-card-group">
						<div id="youcanpay-card-element" class="wc-youcanpay-elements-field">
						    <!-- a YouCanPay Element will be inserted here. -->
						</div>

						<i class="youcanpay-credit-card-brand youcanpay-card-brand" alt="Credit Card"></i>
					</div>
				</div>

				<div class="form-row form-row-first">
					<label for="youcanpay-exp-element"><?php esc_html_e( 'Expiry Date', 'woocommerce-gateway-youcanpay' ); ?> <span class="required">*</span></label>

					<div id="youcanpay-exp-element" class="wc-youcanpay-elements-field">
					    <!-- a YouCanPay Element will be inserted here. -->
					</div>
				</div>

				<div class="form-row form-row-last">
					<label for="youcanpay-cvc-element"><?php esc_html_e( 'Card Code (CVC)', 'woocommerce-gateway-youcanpay' ); ?> <span class="required">*</span></label>
				<div id="youcanpay-cvc-element" class="wc-youcanpay-elements-field">
				    <!-- a YouCanPay Element will be inserted here. -->
				</div>
				</div>
				<div class="clear"></div>

			<!-- Used to display form errors -->
			<div class="youcanpay-source-errors" role="alert"></div>
			<?php do_action( 'woocommerce_credit_card_form_end', $this->id ); ?>
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
		global $wp;

		$youcanpay_params = [
			'title'                => $this->title,
			'key'                  => $this->publishable_key,
			'i18n_terms'           => __( 'Please accept the terms and conditions first', 'woocommerce-gateway-youcanpay' ),
			'i18n_required_fields' => __( 'Please fill in required checkout fields first', 'woocommerce-gateway-youcanpay' ),
		];

		// If we're on the pay page we need to pass youcanpay.js the address of the order.
		if ( isset( $_GET['pay_for_order'] ) && 'true' === $_GET['pay_for_order'] ) { // wpcs: csrf ok.
			$order_id = wc_clean( $wp->query_vars['order-pay'] ); // wpcs: csrf ok, sanitization ok, xss ok.
			$order    = wc_get_order( $order_id );

			if ( is_a( $order, 'WC_Order' ) ) {
				$youcanpay_params['billing_first_name'] = $order->get_billing_first_name();
				$youcanpay_params['billing_last_name']  = $order->get_billing_last_name();
				$youcanpay_params['billing_address_1']  = $order->get_billing_address_1();
				$youcanpay_params['billing_address_2']  = $order->get_billing_address_2();
				$youcanpay_params['billing_state']      = $order->get_billing_state();
				$youcanpay_params['billing_city']       = $order->get_billing_city();
				$youcanpay_params['billing_postcode']   = $order->get_billing_postcode();
				$youcanpay_params['billing_country']    = $order->get_billing_country();
			}
		}

		$sepa_elements_options = apply_filters(
			'wc_youcanpay_sepa_elements_options',
			[
				'supportedCountries' => [ 'SEPA' ],
				'placeholderCountry' => WC()->countries->get_base_country(),
				'style'              => [ 'base' => [ 'fontSize' => '15px' ] ],
			]
		);

		$youcanpay_params['youcanpay_locale']             = WC_YouCanPay_Helper::convert_wc_locale_to_youcanpay_locale( get_locale() );
		$youcanpay_params['no_prepaid_card_msg']       = __( 'Sorry, we\'re not accepting prepaid cards at this time. Your credit card has not been charged. Please try with alternative payment method.', 'woocommerce-gateway-youcanpay' );
		$youcanpay_params['no_sepa_owner_msg']         = __( 'Please enter your IBAN account name.', 'woocommerce-gateway-youcanpay' );
		$youcanpay_params['no_sepa_iban_msg']          = __( 'Please enter your IBAN account number.', 'woocommerce-gateway-youcanpay' );
		$youcanpay_params['payment_intent_error']      = __( 'We couldn\'t initiate the payment. Please try again.', 'woocommerce-gateway-youcanpay' );
		$youcanpay_params['sepa_mandate_notification'] = apply_filters( 'wc_youcanpay_sepa_mandate_notification', 'email' );
		$youcanpay_params['allow_prepaid_card']        = apply_filters( 'wc_youcanpay_allow_prepaid_card', true ) ? 'yes' : 'no';
		//$youcanpay_params['inline_cc_form']            = $this->inline_cc_form ? 'yes' : 'no';
		$youcanpay_params['is_checkout']               = ( is_checkout() && empty( $_GET['pay_for_order'] ) ) ? 'yes' : 'no'; // wpcs: csrf ok.
		$youcanpay_params['return_url']                = $this->get_youcanpay_return_url();
		$youcanpay_params['ajaxurl']                   = WC_AJAX::get_endpoint( '%%endpoint%%' );
		$youcanpay_params['youcanpay_nonce']              = wp_create_nonce( '_wc_youcanpay_nonce' );
		$youcanpay_params['elements_options']          = apply_filters( 'wc_youcanpay_elements_options', [] );
		$youcanpay_params['sepa_elements_options']     = $sepa_elements_options;
		$youcanpay_params['invalid_owner_name']        = __( 'Billing First Name and Last Name are required.', 'woocommerce-gateway-youcanpay' );
		$youcanpay_params['is_change_payment_page']    = isset( $_GET['change_payment_method'] ) ? 'yes' : 'no'; // wpcs: csrf ok.
		$youcanpay_params['is_add_payment_page']       = is_wc_endpoint_url( 'add-payment-method' ) ? 'yes' : 'no';
		$youcanpay_params['is_pay_for_order_page']     = is_wc_endpoint_url( 'order-pay' ) ? 'yes' : 'no';
		$youcanpay_params['elements_styling']          = apply_filters( 'wc_youcanpay_elements_styling', false );
		$youcanpay_params['elements_classes']          = apply_filters( 'wc_youcanpay_elements_classes', false );
		$youcanpay_params['add_card_nonce']            = wp_create_nonce( 'wc_youcanpay_create_si' );

		// Merge localized messages to be use in JS.
		$youcanpay_params = array_merge( $youcanpay_params, WC_YouCanPay_Helper::get_localized_messages() );

		return $youcanpay_params;
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
			&& ! ( ! empty( get_query_var( 'view-subscription' ) ) && is_callable( 'WCS_Early_Renewal_Manager::is_early_renewal_via_modal_enabled' ) && WCS_Early_Renewal_Manager::is_early_renewal_via_modal_enabled() )
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

		$suffix = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';

		wp_register_style( 'youcanpay_styles', plugins_url( 'assets/css/youcanpay-styles.css', WC_YOUCAN_PAY_MAIN_FILE ), [], WC_YOUCAN_PAY_VERSION );
		wp_enqueue_style( 'youcanpay_styles' );

		//wp_register_script( 'youcanpay', 'https://js.youcanpay.com/v3/', '', '3.0', true );
		wp_register_script( 'woocommerce_youcanpay', plugins_url( 'assets/js/youcanpay' . $suffix . '.js', WC_YOUCAN_PAY_MAIN_FILE ), [ 'jquery-payment', 'youcanpay' ], WC_YOUCAN_PAY_VERSION, true );

		wp_localize_script(
			'woocommerce_youcanpay',
			'wc_youcanpay_params',
			apply_filters( 'wc_youcanpay_params', $this->javascript_params() )
		);

		$this->tokenization_script();
		wp_enqueue_script( 'woocommerce_youcanpay' );
	}

	/**
	 * Completes an order without a positive value.
	 *
	 * @since 4.2.0
	 * @param WC_Order $order             The order to complete.
	 * @param WC_Order $prepared_source   Payment source and customer data.
	 * @param boolean  $force_save_source Whether the payment source must be saved, like when dealing with a Subscription setup.
	 * @return array                      Redirection data for `process_payment`.
	 */
	public function complete_free_order( $order, $prepared_source, $force_save_source ) {
		if ( $force_save_source ) {
			$intent_secret = $this->setup_intent( $order, $prepared_source );

			if ( ! empty( $intent_secret ) ) {
				// `get_return_url()` must be called immediately before returning a value.
				return [
					'result'              => 'success',
					'redirect'            => $this->get_return_url( $order ),
					'setup_intent_secret' => $intent_secret,
				];
			}
		}

		// Remove cart.
		WC()->cart->empty_cart();

		$order->payment_complete();

		// Return thank you page redirect.
		return [
			'result'   => 'success',
			'redirect' => $this->get_return_url( $order ),
		];
	}

	/**
	 * Process the payment
	 *
	 * @since 1.0.0
	 * @since 4.1.0 Add 4th parameter to track previous error.
	 * @version 5.6.0
	 *
	 * @param int  $order_id Reference.
	 * @param bool $retry Should we retry on fail.
	 * @param bool $force_save_source Force save the payment source.
	 * @param mix  $previous_error Any error message from previous request.
	 * @param bool $use_order_source Whether to use the source, which should already be attached to the order.
	 *
	 * @throws Exception If payment will not be accepted.
	 * @return array|void
	 */
	public function process_payment( $order_id, $retry = true, $force_save_source = false, $previous_error = false, $use_order_source = false ) {
		try {
			$order = wc_get_order( $order_id );

			if ( $this->has_subscription( $order_id ) ) {
				$force_save_source = true;
			}

			if ( $this->maybe_change_subscription_payment_method( $order_id ) ) {
				return $this->process_change_subscription_payment_method( $order_id );
			}

			// ToDo: `process_pre_order` saves the source to the order for a later payment.
			// This might not work well with PaymentIntents.
			if ( $this->maybe_process_pre_orders( $order_id ) ) {
				return $this->pre_orders->process_pre_order( $order_id );
			}

			// Check whether there is an existing intent.
			$intent = $this->get_intent_from_order( $order );
			if ( isset( $intent->object ) && 'setup_intent' === $intent->object ) {
				$intent = false; // This function can only deal with *payment* intents
			}

			$youcanpay_customer_id = null;
			if ( $intent && ! empty( $intent->customer ) ) {
				$youcanpay_customer_id = $intent->customer;
			}

			// For some payments the source should already be present in the order.
			if ( $use_order_source ) {
				$prepared_source = $this->prepare_order_source( $order );
			} else {
				$prepared_source = $this->prepare_source( get_current_user_id(), $force_save_source, $youcanpay_customer_id );
			}

			// If we are using a saved payment method that is PaymentMethod (pm_) and not a Source (src_) we need to use
			// the process_payment() from the UPE gateway which uses the PaymentMethods API instead of Sources API.
			// This happens when using a saved payment method that was added with the UPE gateway.
			if ( $this->is_using_saved_payment_method() && ! empty( $prepared_source->source ) && substr( $prepared_source->source, 0, 3 ) === 'pm_' ) {
				$upe_gateway = new WC_YouCanPay_UPE_Payment_Gateway();
				return $upe_gateway->process_payment_with_saved_payment_method( $order_id );
			}

			$this->maybe_disallow_prepaid_card( $prepared_source->source_object );
			$this->check_source( $prepared_source );
			$this->save_source_to_order( $order, $prepared_source );

			if ( 0 >= $order->get_total() ) {
				return $this->complete_free_order( $order, $prepared_source, $force_save_source );
			}

			// This will throw exception if not valid.
			$this->validate_minimum_order_amount( $order );

			WC_YouCanPay_Logger::log( "Info: Begin processing payment for order $order_id for the amount of {$order->get_total()}" );

			if ( $intent ) {
				$intent = $this->update_existing_intent( $intent, $order, $prepared_source );
			} else {
				$intent = $this->create_intent( $order, $prepared_source );
			}

			// Confirm the intent after locking the order to make sure webhooks will not interfere.
			if ( empty( $intent->error ) ) {
				$this->lock_order_payment( $order, $intent );
				$intent = $this->confirm_intent( $intent, $order, $prepared_source );
			}

			$force_save_source_value = apply_filters( 'wc_youcanpay_force_save_source', $force_save_source, $prepared_source->source );

			if ( 'succeeded' === $intent->status && ! $this->is_using_saved_payment_method() && ( $this->save_payment_method_requested() || $force_save_source_value ) ) {
				$this->save_payment_method( $prepared_source->source_object );
			}

			if ( ! empty( $intent->error ) ) {
				$this->maybe_remove_non_existent_customer( $intent->error, $order );

				// We want to retry.
				if ( $this->is_retryable_error( $intent->error ) ) {
					return $this->retry_after_error( $intent, $order, $retry, $force_save_source, $previous_error, $use_order_source );
				}

				$this->unlock_order_payment( $order );
				$this->throw_localized_message( $intent, $order );
			}

			if ( ! empty( $intent ) ) {
				// Use the last charge within the intent to proceed.
				$response = end( $intent->charges->data );

				// If the intent requires a 3DS flow, redirect to it.
				if ( 'requires_action' === $intent->status ) {
					$this->unlock_order_payment( $order );

					if ( is_wc_endpoint_url( 'order-pay' ) ) {
						$redirect_url = add_query_arg( 'wc-youcanpay-confirmation', 1, $order->get_checkout_payment_url( false ) );

						return [
							'result'   => 'success',
							'redirect' => $redirect_url,
						];
					} else {
						/**
						 * This URL contains only a hash, which will be sent to `checkout.js` where it will be set like this:
						 * `window.location = result.redirect`
						 * Once this redirect is sent to JS, the `onHashChange` function will execute `handleCardPayment`.
						 */

						return [
							'result'                => 'success',
							'redirect'              => $this->get_return_url( $order ),
							'payment_intent_secret' => $intent->client_secret,
							'save_payment_method'   => $this->save_payment_method_requested(),
						];
					}
				}
			}

			// Process valid response.
			$this->process_response( $response, $order );

			// Remove cart.
			if ( isset( WC()->cart ) ) {
				WC()->cart->empty_cart();
			}

			// Unlock the order.
			$this->unlock_order_payment( $order );

			// Return thank you page redirect.
			return [
				'result'   => 'success',
				'redirect' => $this->get_return_url( $order ),
			];

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
	 * @throws WC_YouCanPay_Exception
	 */
	public function save_payment_method( $source_object ) {
		$user_id  = get_current_user_id();
		$customer = new WC_YouCanPay_Customer( $user_id );

		if ( ( $user_id && 'reusable' === $source_object->usage ) ) {
			$response = $customer->add_source( $source_object->id );

			if ( ! empty( $response->error ) ) {
				throw new WC_YouCanPay_Exception( print_r( $response, true ), $this->get_localized_error_message_from_response( $response ) );
			}
			if ( is_wp_error( $response ) ) {
				throw new WC_YouCanPay_Exception( $response->get_error_message(), $response->get_error_message() );
			}
		}
	}

	/**
	 * Displays the YouCanPay fee
	 *
	 * @since 4.1.0
	 *
	 * @param int $order_id The ID of the order.
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
				<?php echo wc_help_tip( __( 'This represents the fee YouCanPay collects for the transaction.', 'woocommerce-gateway-youcanpay' ) ); // wpcs: xss ok. ?>
				<?php esc_html_e( 'YouCanPay Fee:', 'woocommerce-gateway-youcanpay' ); ?>
			</td>
			<td width="1%"></td>
			<td class="total">
				-<?php echo wc_price( $fee, [ 'currency' => $currency ] ); // wpcs: xss ok. ?>
			</td>
		</tr>

		<?php
	}

	/**
	 * Displays the net total of the transaction without the charges of YouCanPay.
	 *
	 * @since 4.1.0
	 *
	 * @param int $order_id The ID of the order.
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
				<?php echo wc_help_tip( __( 'This represents the net total that will be credited to your YouCanPay bank account. This may be in the currency that is set in your YouCanPay account.', 'woocommerce-gateway-youcanpay' ) ); // wpcs: xss ok. ?>
				<?php esc_html_e( 'YouCanPay Payout:', 'woocommerce-gateway-youcanpay' ); ?>
			</td>
			<td width="1%"></td>
			<td class="total">
				<?php echo wc_price( $net, [ 'currency' => $currency ] ); // wpcs: xss ok. ?>
			</td>
		</tr>

		<?php
	}

	/**
	 * Retries the payment process once an error occured.
	 *
	 * @since 4.2.0
	 * @param object   $response          The response from the YouCanPay API.
	 * @param WC_Order $order             An order that is being paid for.
	 * @param bool     $retry             A flag that indicates whether another retry should be attempted.
	 * @param bool     $force_save_source Force save the payment source.
	 * @param mixed    $previous_error    Any error message from previous request.
	 * @param bool     $use_order_source  Whether to use the source, which should already be attached to the order.
	 * @throws WC_YouCanPay_Exception        If the payment is not accepted.
	 * @return array|void
	 */
	public function retry_after_error( $response, $order, $retry, $force_save_source, $previous_error, $use_order_source ) {
		if ( ! $retry ) {
			$localized_message = __( 'Sorry, we are unable to process your payment at this time. Please retry later.', 'woocommerce-gateway-youcanpay' );
			$order->add_order_note( $localized_message );
			throw new WC_YouCanPay_Exception( print_r( $response, true ), $localized_message ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.
		}

		// Don't do anymore retries after this.
		if ( 5 <= $this->retry_interval ) {
			return $this->process_payment( $order->get_id(), false, $force_save_source, $response->error, $previous_error );
		}

		sleep( $this->retry_interval );
		$this->retry_interval++;

		return $this->process_payment( $order->get_id(), true, $force_save_source, $response->error, $previous_error, $use_order_source );
	}

	/**
	 * Adds the necessary hooks to modify the "Pay for order" page in order to clean
	 * it up and prepare it for the YouCanPay PaymentIntents modal to confirm a payment.
	 *
	 * @since 4.2
	 * @param WC_Payment_Gateway[] $gateways A list of all available gateways.
	 * @return WC_Payment_Gateway[]          Either the same list or an empty one in the right conditions.
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
		add_filter( 'woocommerce_no_available_payment_methods_message', [ $this, 'change_no_available_methods_message' ] );
		add_action( 'woocommerce_pay_order_after_submit', [ $this, 'render_payment_intent_inputs' ] );

		return [];
	}

	/**
	 * Changes the text of the "No available methods" message to one that indicates
	 * the need for a PaymentIntent to be confirmed.
	 *
	 * @since 4.2
	 * @return string the new message.
	 */
	public function change_no_available_methods_message() {
		return wpautop( __( "Almost there!\n\nYour order has already been created, the only thing that still needs to be done is for you to authorize the payment with your bank.", 'woocommerce-gateway-youcanpay' ) );
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
					__( 'Payment Intent not found for order #%s', 'woocommerce-gateway-youcanpay' ),
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
	 * @since 4.2.0
	 * @param WC_Payment_Token $token Payment Token.
	 * @return string                 Generated payment method HTML
	 */
	public function get_saved_payment_method_option_html( $token ) {
		$html          = parent::get_saved_payment_method_option_html( $token );
		$error_wrapper = '<div class="youcanpay-source-errors" role="alert"></div>';

		return preg_replace( '~</(\w+)>\s*$~', "$error_wrapper</$1>", $html );
	}

	/**
	 * Attempt to manually complete the payment process for orders, which are still pending
	 * before displaying the View Order page. This is useful in case webhooks have not been set up.
	 *
	 * @since 4.2.0
	 * @param int $order_id The ID that will be used for the thank you page.
	 */
	public function check_intent_status_on_order_page( $order_id ) {
		if ( empty( $order_id ) || absint( $order_id ) <= 0 ) {
			return;
		}

		$order = wc_get_order( absint( $order_id ) );

		if ( ! $order ) {
			return;
		}

		$this->verify_intent_after_checkout( $order );
	}

	/**
	 * Attached to `woocommerce_payment_successful_result` with a late priority,
	 * this method will combine the "naturally" generated redirect URL from
	 * WooCommerce and a payment/setup intent secret into a hash, which contains both
	 * the secret, and a proper URL, which will confirm whether the intent succeeded.
	 *
	 * @since 4.2.0
	 * @param array $result   The result from `process_payment`.
	 * @param int   $order_id The ID of the order which is being paid for.
	 * @return array
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
			$redirect = sprintf( '#confirm-pi-%s:%s', $result['payment_intent_secret'], rawurlencode( $verification_url ) );
		} elseif ( isset( $result['setup_intent_secret'] ) ) {
			$redirect = sprintf( '#confirm-si-%s:%s', $result['setup_intent_secret'], rawurlencode( $verification_url ) );
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
	 * Executed between the "Checkout" and "Thank you" pages, this
	 * method updates orders based on the status of associated PaymentIntents.
	 *
	 * @since 4.2.0
	 * @param WC_Order $order The order which is in a transitional state.
	 */
	public function verify_intent_after_checkout( $order ) {
		$payment_method = $order->get_payment_method();
		if ( $payment_method !== $this->id ) {
			// If this is not the payment method, an intent would not be available.
			return;
		}

		$intent = $this->get_intent_from_order( $order );
		if ( ! $intent ) {
			// No intent, redirect to the order received page for further actions.
			return;
		}

		// A webhook might have modified or locked the order while the intent was retreived. This ensures we are reading the right status.
		clean_post_cache( $order->get_id() );
		$order = wc_get_order( $order->get_id() );

		if ( ! $order->has_status(
			apply_filters(
				'wc_youcanpay_allowed_payment_processing_statuses',
				[ 'pending', 'failed' ]
			)
		) ) {
			// If payment has already been completed, this function is redundant.
			return;
		}

		if ( $this->lock_order_payment( $order, $intent ) ) {
			return;
		}

		if ( 'setup_intent' === $intent->object && 'succeeded' === $intent->status ) {
			WC()->cart->empty_cart();
			if ( WC_YouCanPay_Helper::is_pre_orders_exists() && WC_Pre_Orders_Order::order_contains_pre_order( $order ) ) {
				WC_Pre_Orders_Order::mark_order_as_pre_ordered( $order );
			} else {
				$order->payment_complete();
			}
		} elseif ( 'succeeded' === $intent->status || 'requires_capture' === $intent->status ) {
			// Proceed with the payment completion.
			$this->handle_intent_verification_success( $order, $intent );
		} elseif ( 'requires_payment_method' === $intent->status ) {
			// `requires_payment_method` means that SCA got denied for the current payment method.
			$this->handle_intent_verification_failure( $order, $intent );
		}

		$this->unlock_order_payment( $order );
	}

	/**
	 * Called after an intent verification succeeds, this allows
	 * specific APNs or children of this class to modify its behavior.
	 *
	 * @param WC_Order $order The order whose verification succeeded.
	 * @param stdClass $intent The Payment Intent object.
	 */
	protected function handle_intent_verification_success( $order, $intent ) {
		$this->process_response( end( $intent->charges->data ), $order );
		$this->maybe_process_subscription_early_renewal_success( $order, $intent );
	}

	/**
	 * Called after an intent verification fails, this allows
	 * specific APNs or children of this class to modify its behavior.
	 *
	 * @param WC_Order $order The order whose verification failed.
	 * @param stdClass $intent The Payment Intent object.
	 */
	protected function handle_intent_verification_failure( $order, $intent ) {
		$this->failed_sca_auth( $order, $intent );
		$this->maybe_process_subscription_early_renewal_failure( $order, $intent );
	}

	/**
	 * Checks if the payment intent associated with an order failed and records the event.
	 *
	 * @since 4.2.0
	 * @param WC_Order $order  The order which should be checked.
	 * @param object   $intent The intent, associated with the order.
	 */
	public function failed_sca_auth( $order, $intent ) {
		// If the order has already failed, do not repeat the same message.
		if ( $order->has_status( 'failed' ) ) {
			return;
		}

		// Load the right message and update the status.
		$status_message = isset( $intent->last_payment_error )
			/* translators: 1) The error message that was received from YouCanPay. */
			? sprintf( __( 'YouCanPay SCA authentication failed. Reason: %s', 'woocommerce-gateway-youcanpay' ), $intent->last_payment_error->message )
			: __( 'YouCanPay SCA authentication failed.', 'woocommerce-gateway-youcanpay' );
		$order->update_status( 'failed', $status_message );
	}

	/**
	 * Preserves the "wc-youcanpay-confirmation" URL parameter so the user can complete the SCA authentication after logging in.
	 *
	 * @param string   $pay_url Current computed checkout URL for the given order.
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
		$has_changed = function( $old_value, $new_value ) {
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
			throw new Exception( __( 'The "Live Publishable Key" should start with "pub", enter the correct key.', 'woocommerce-gateway-youcanpay' ) );
		}
		return $value;
	}

	public function validate_secret_key_field( $key, $value ) {
		$value = $this->validate_text_field( $key, $value );
		if ( ! empty( $value ) && ! preg_match( '/^pri_/', $value ) ) {
			throw new Exception( __( 'The "Live Secret Key" should start with "pri", enter the correct key.', 'woocommerce-gateway-youcanpay' ) );
		}
		return $value;
	}

	public function validate_test_publishable_key_field( $key, $value ) {
		$value = $this->validate_text_field( $key, $value );
		if ( ! empty( $value ) && ! preg_match( '/^pub_sandbox_/', $value ) ) {
			throw new Exception( __( 'The "Test Publishable Key" should start with "pub_sandbox", enter the correct key.', 'woocommerce-gateway-youcanpay' ) );
		}
		return $value;
	}

	public function validate_test_secret_key_field( $key, $value ) {
		$value = $this->validate_text_field( $key, $value );
		if ( ! empty( $value ) && ! preg_match( '/^pri_sandbox_/', $value ) ) {
			throw new Exception( __( 'The "Test Secret Key" should start with "pri_sandbox", enter the correct key.', 'woocommerce-gateway-youcanpay' ) );
		}
		return $value;
	}

	/**
	 * Ensures the statement descriptor about to be saved to options does not contain any invalid characters.
	 *
	 * @since 4.8.0
	 * @param $settings WC_Settings_API settings to be filtered
	 * @return Filtered settings
	 */
	public function settings_api_sanitized_fields( $settings ) {
		if ( is_array( $settings ) ) {

		}
		return $settings;
	}

	/**
	 * This is overloading the title type so the oauth url is only fetched if we are on the settings page.
	 *
	 * @param string $key Field key.
	 * @param array  $data Field data.
	 * @return string
	 */
	public function generate_youcanpay_account_keys_html( $key, $data ) {
		if ( woocommerce_gateway_youcanpay()->connect->is_connected() ) {
			$reset_link = add_query_arg(
				[
					'_wpnonce'                     => wp_create_nonce( 'reset_youcanpay_api_credentials' ),
					'reset_youcanpay_api_credentials' => true,
				],
				admin_url( 'admin.php?page=wc-settings&tab=checkout&section=youcanpay' )
			);

			$api_credentials_text = sprintf(
			/* translators: %1, %2, %3, and %4 are all HTML markup tags */
				__( '%1$sClear all YouCan Pay account keys.%2$s %3$sThis will disable any connection to YouCan Pay.%4$s', 'woocommerce-gateway-youcanpay' ),
				'<a id="wc_youcanpay_connect_button" href="' . $reset_link . '" class="button button-secondary">',
				'</a>',
				'<span style="color:red;">',
				'</span>'
			);
		} else {
			$oauth_url = woocommerce_gateway_youcanpay()->connect->get_oauth_url();

			if ( ! is_wp_error( $oauth_url ) ) {
				$api_credentials_text = sprintf(
				/* translators: %1, %2 and %3 are all HTML markup tags */
					__( '%1$sSet up or link an existing YouCanPay account.%2$s By clicking this button you agree to the %3$sTerms of Service%2$s. Or, manually enter YouCanPay account keys below.', 'woocommerce-gateway-youcanpay' ),
					'<a id="wc_youcanpay_connect_button" href="' . $oauth_url . '" class="button button-primary">',
					'</a>',
					'<a href="https://wordpress.com/tos">'
				);
			} else {
				$api_credentials_text = __( 'Manually enter YouCanPay keys below.', 'woocommerce-gateway-youcanpay' );
			}
		}
		$data['description'] = $api_credentials_text;
		return $this->generate_title_html( $key, $data );
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
	 * @param int    $max_length Maximum statement length.
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
			throw new InvalidArgumentException( __( 'Customer bank statement is invalid. Statement should be between 5 and 22 characters long, contain at least single Latin character and does not contain special characters: \' " * &lt; &gt;', 'woocommerce-gateway-youcanpay' ) );
		}

		return $value;
	}
}
