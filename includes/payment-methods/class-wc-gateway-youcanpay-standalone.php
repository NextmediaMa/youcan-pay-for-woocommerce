<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class that handles Standalone payment method.
 *
 * @extends WC_Gateway_YouCanPay
 */
class WC_Gateway_YouCanPay_Standalone extends WC_YouCanPay_Payment_Gateway {

	const ID = 'youcanpay_standalone';

	/**
	 * Notices (array)
	 *
	 * @var array
	 */
	public $notices = [];

	/**
	 * Is test mode active?
	 *
	 * @var bool
	 */
	public $testmode;

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
	 * Should we store the users credit cards?
	 *
	 * @var bool
	 */
	public $saved_cards;

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->id           = self::ID;
		$this->method_title = __( 'YouCanPay Standalone', 'woocommerce-youcan-pay' );
		/* translators: link */
		$this->method_description = sprintf( __( 'All other general YouCanPay settings can be adjusted <a href="%s">here</a>.', 'woocommerce-youcan-pay' ), admin_url( 'admin.php?page=wc-settings&tab=checkout&section=youcanpay' ) );
		$this->supports           = [
			'products',
			'refunds',
		];

		// Load the form fields.
		$this->init_form_fields();

		// Load the settings.
		$this->init_settings();

		$main_settings              = get_option( 'woocommerce_youcanpay_settings' );
		$this->title                = $this->get_option( 'title' );
		$this->description          = $this->get_option( 'description' );
		$this->enabled              = $this->get_option( 'enabled' );
		$this->testmode             = ( ! empty( $main_settings['testmode'] ) && 'yes' === $main_settings['testmode'] ) ? true : false;
		$this->saved_cards          = ( ! empty( $main_settings['saved_cards'] ) && 'yes' === $main_settings['saved_cards'] ) ? true : false;
		$this->publishable_key      = ! empty( $main_settings['publishable_key'] ) ? $main_settings['publishable_key'] : '';
		$this->secret_key           = ! empty( $main_settings['secret_key'] ) ? $main_settings['secret_key'] : '';

		if ( $this->testmode ) {
			$this->publishable_key = ! empty( $main_settings['test_publishable_key'] ) ? $main_settings['test_publishable_key'] : '';
			$this->secret_key      = ! empty( $main_settings['test_secret_key'] ) ? $main_settings['test_secret_key'] : '';
		}

		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, [ $this, 'process_admin_options' ] );
		add_action( 'wp_enqueue_scripts', [ $this, 'payment_scripts' ] );
	}

	/**
	 * Checks to see if all criteria is met before showing payment method.
	 *
	 * @return bool
	 */
	public function is_available() {
		return parent::is_available();
	}

	/**
	 * Get_icon function.
	 *
	 * @return string
	 */
	public function get_icon() {
		$icons = $this->payment_icons();

		$icons_str = '';

		$icons_str .= isset( $icons['standalone'] ) ? $icons['standalone'] : '';

		return apply_filters( 'woocommerce_gateway_icon', $icons_str, $this->id );
	}

	/**
	 * Payment_scripts function.
	 */
	public function payment_scripts() {
		if ( ! is_cart() && ! is_checkout() && ! isset( $_GET['pay_for_order'] ) && ! is_add_payment_method_page() ) {
			return;
		}

		wp_enqueue_style( 'youcanpay_styles' );
		wp_enqueue_script( 'woocommerce_youcanpay' );
	}

	/**
	 * Initialize Gateway Settings Form Fields.
	 */
	public function init_form_fields() {
		$this->form_fields = require WC_YOUCAN_PAY_PLUGIN_PATH . '/includes/admin/youcanpay-standalone-settings.php';
	}

	/**
	 * Payment form on checkout page
	 */
	public function payment_fields() {
		global $wp;
		$total       = WC()->cart->total;
		$description = $this->get_description();

		// If paying from order, we need to get total from order not cart.
		if ( isset( $_GET['pay_for_order'] ) && ! empty( $_GET['key'] ) ) {
			$order = wc_get_order( wc_clean( $wp->query_vars['order-pay'] ) );
			$total = $order->get_total();
		}

		echo '<div
			id="youcanpay-standalone-payment-data"
			data-amount="' . esc_attr( WC_YouCanPay_Helper::get_youcanpay_amount( $total ) ) . '"
			data-currency="' . esc_attr( strtolower( get_woocommerce_currency() ) ) . '">';

		if ( $description ) {
			echo apply_filters( 'wc_youcanpay_description', wpautop( wp_kses_post( $description ) ), $this->id );
		}

		echo '</div>';
	}

	/**
	 * Creates the source for charge.
	 *
	 * @param object $order
	 *
	 * @return mixed
	 * @throws WC_YouCanPay_Exception
	 */
	public function create_source( $order ) {
		$currency              = $order->get_currency();
		$return_url            = $this->get_youcanpay_return_url( $order );
		$post_data             = [];
		$post_data['amount']   = WC_YouCanPay_Helper::get_youcanpay_amount( $order->get_total(), $currency );
		$post_data['currency'] = strtoupper( $currency );
		$post_data['type']     = 'standalone';
		$post_data['owner']    = $this->get_owner_details( $order );
		$post_data['redirect'] = [ 'return_url' => $return_url ];

		WC_YouCanPay_Logger::log( 'Info: Begin creating YouCan Pay Standalone source' );

		return WC_YouCanPay_API::requestV2($order, $post_data,'sources' );
	}

	/**
	 * Process the payment
	 *
	 * @param int $order_id Reference.
	 * @param bool $retry Should we retry on fail.
	 * @param bool $force_save_save
	 *
	 * @return array|void
	 */
	public function process_payment( $order_id, $retry = true, $force_save_save = false ) {
		try {
			$order = wc_get_order( $order_id );

			// This will throw exception if not valid.
			$this->validate_minimum_order_amount( $order );

			// This comes from the create account checkbox in the checkout page.
			$create_account = ! empty( $_POST['createaccount'] ) ? true : false;

			if ( $create_account ) {
				$new_customer_id     = $order->get_customer_id();
				$new_youcanpay_customer = new WC_YouCanPay_Customer( $new_customer_id );
				$new_youcanpay_customer->create_customer();
			}

			$response = $this->create_source( $order );

			if ( ! empty( $response->error ) ) {
				$order->add_order_note( $response->error->message );

				throw new WC_YouCanPay_Exception( print_r( $response, true ), $response->error->message );
			}

			$order->update_meta_data( '_youcanpay_source_id', $response->id );
			$order->save();

			WC_YouCanPay_Logger::log( 'Info: Redirecting to YouCan Pay Standalone...' );

			return [
				'result'   => 'success',
				'redirect' => esc_url_raw( $response->redirect->url ),
			];
		} catch ( WC_YouCanPay_Exception $e ) {
			wc_add_notice( $e->getLocalizedMessage(), 'error' );
			WC_YouCanPay_Logger::log( 'Error: ' . $e->getMessage() );

			do_action( 'wc_gateway_youcanpay_process_payment_error', $e, $order );

			$statuses = apply_filters(
				'wc_youcanpay_allowed_payment_processing_statuses',
				[ 'pending', 'failed' ]
			);

			if ( $order->has_status( $statuses ) ) {
				$this->send_failed_order_email( $order_id );
			}

			return [
				'result'   => 'fail',
				'redirect' => '',
			];
		}
	}
}
