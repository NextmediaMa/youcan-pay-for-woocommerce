<?php

use YouCan\Pay\Models\Token;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * WC_Gateway_YouCanPay class.
 *
 * @extends WC_Payment_Gateway
 */
class WC_Gateway_YouCanPay extends WC_YouCanPay_Payment_Gateway
{

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
    public function __construct()
    {
        $this->id = self::ID;
        $this->method_title = __('YouCan Pay', 'youcan-pay');
        $this->method_description = __(
            'YouCan Pay works by adding payment fields on the checkout and then sending the details to YouCan Pay for verification.',
            'youcan-pay'
        );
        $this->has_fields = true;
        $this->supports = [
            'products',
            'refunds',
        ];

        // Load the form fields.
        $this->init_form_fields();

        // Load the settings.
        $this->init_settings();

        // Load order button from settings.
        $this->init_order_button_text();

        // Get setting values.
        $this->title = $this->get_option('title');
        $this->description = $this->get_option('description');
        $this->enabled = $this->get_option('enabled');
        $this->sandbox_mode = 'yes' === $this->get_option('sandbox_mode');
        $this->private_key = $this->sandbox_mode ? $this->get_option('sandbox_private_key') : $this->get_option(
            'private_key'
        );
        $this->public_key = $this->sandbox_mode ? $this->get_option('sandbox_public_key') : $this->get_option(
            'public_key'
        );

        WC_YouCanPay_API::set_private_key($this->private_key);

        // Hooks.
        add_action('woocommerce_update_options_payment_gateways_' . $this->id, [$this, 'process_admin_options']);
        add_action('set_logged_in_cookie', [$this, 'set_cookie_on_current_request']);
        add_filter('woocommerce_get_checkout_payment_url', [$this, 'get_checkout_payment_url'], 10, 2);

        // Note: display error is in the parent class.
        add_action('admin_notices', [$this, 'display_errors'], 9999);
    }

    /**
     * Load payment scripts
     */
    public function load_scripts() :void {
        add_action('wp_enqueue_scripts', [$this, 'payment_scripts']);
    }

    /**
     * Checks if gateway should be available to use.
     */
    public function is_available()
    {
        if (is_add_payment_method_page()) {
            return false;
        }

        if (!in_array(get_woocommerce_currency(), $this->get_supported_currency())) {
            return false;
        }

        return parent::is_available();
    }

    /**
     * Get_icon function.
     *
     * @return string|null
     */
    public function get_icon()
    {
        return apply_filters('woocommerce_gateway_icon', null, $this->id);
    }

    /**
     * Initialise Gateway Settings Form Fields
     */
    public function init_form_fields()
    {
        $this->form_fields = require dirname(__FILE__) . '/admin/youcanpay-settings.php';
    }

    /**
     * Payment form on checkout page
     */
    public function payment_fields()
    {
        ob_start();

        echo '<div id="youcanpay-payment-data-credit-card">';

        $description = $this->get_description() ?? '';
        $description = trim($description);
        echo wpautop(wp_kses_post($description));

        $this->elements_form();

        echo '</div>';

        ob_end_flush();
    }

    /**
     * Renders the YouCan Pay elements form.
     */
    public function elements_form()
    {
        ?>
        <fieldset id="wc-<?php
        echo esc_attr($this->id); ?>-cc-form" class="wc-credit-card-form wc-payment-form"
                  style="background:transparent;display:contents;">
            <?php
            do_action('woocommerce_credit_card_form_start', $this->id); ?>

            <div class="form-row form-row-wide" id="payment-card"></div>
            <script>
              (function () {
                if (typeof (window.setupYouCanPayForm) !== "undefined") {
                  window.setupYouCanPayForm();
                }
              })();
            </script>

            <div class="clear"></div>

            <!-- Used to display form errors -->
            <div class="youcanpay-source-errors" role="alert"></div>
            <?php
            do_action('woocommerce_credit_card_form_end', $this->id); ?>
            <div class="clear"></div>
        </fieldset>
        <?php
    }

    /**
     * Maybe override the parent admin_options method.
     */
    public function admin_options()
    {
        parent::admin_options();
    }

    /**
     * Returns the JavaScript configuration object used on the product, cart, and checkout pages.
     *
     * @return array The configuration object to be loaded to JS.
     */
    public function javascript_params()
    {
        try {
	        $youcanpay_params = [
		        'title'           => $this->title,
		        'key'             => $this->public_key,
		        'default_gateway' => self::ID,
		        'gateways'        => [
			        WC_YouCanPay_Gateways_Enum::get_youcan_pay(),
			        WC_YouCanPay_Gateways_Enum::get_cash_plus(),
			        WC_YouCanPay_Gateways_Enum::get_standalone(),
		        ],
		        'locale'          => WC_YouCanPay_Helper::get_supported_local( get_locale() ),
		        'checkout_url'    => WC_YouCanPay_Helper::get_ajax_checkout_url(),
		        'is_test_mode'    => $this->is_in_test_mode(),
		        'is_pre_order'    => 0,
		        'order_actions'   => WC_YouCanPay_Order_Action_Enum::get_all(),
		        'inputs'          => [
			        'billing_first_name'        => __( 'Billing First Name', 'youcan-pay' ),
			        'billing_last_name'         => __( 'Billing Last Name', 'youcan-pay' ),
			        'billing_company'           => __( 'Billing Company', 'youcan-pay' ),
			        'billing_country'           => __( 'Billing Country', 'youcan-pay' ),
			        'billing_address_1'         => __( 'Billing Address', 'youcan-pay' ),
			        'billing_city'              => __( 'Billing City', 'youcan-pay' ),
			        'billing_state'             => __( 'Billing State', 'youcan-pay' ),
			        'billing_postcode'          => __( 'Billing Postcode', 'youcan-pay' ),
			        'billing_phone'             => __( 'Billing Phone', 'youcan-pay' ),
			        'billing_email'             => __( 'Billing Email', 'youcan-pay' ),
			        'ship_to_different_address' => __( 'Ship To Different Address', 'youcan-pay' ),
			        'order_comments'            => __( 'Order Comments', 'youcan-pay' ),
			        'terms'                     => __( 'Terms', 'youcan-pay' ),
			        'shipping_first_name'       => __( 'Shipping First Name', 'youcan-pay' ),
			        'shipping_last_name'        => __( 'Shipping Last Name', 'youcan-pay' ),
			        'shipping_company'          => __( 'Shipping Company', 'youcan-pay' ),
			        'shipping_country'          => __( 'Shipping Country', 'youcan-pay' ),
			        'shipping_address_1'        => __( 'Shipping Address', 'youcan-pay' ),
			        'shipping_city'             => __( 'Shipping City', 'youcan-pay' ),
			        'shipping_state'            => __( 'Shipping State', 'youcan-pay' ),
			        'shipping_postcode'         => __( 'Shipping Postcode', 'youcan-pay' ),
		        ],
		        'errors'          => [
			        'connexion_api'  => __( 'There was a problem connecting to the YouCan Pay API endpoint.', 'youcan-pay' ),
			        'input_required' => __( '%s is a required field.', 'youcan-pay' ),
		        ],
	        ];

            global $wp;

            if (isset($wp->query_vars['order-pay']) && absint($wp->query_vars['order-pay']) > 0) {
                $order_id = wc_sanitize_order_id($wp->query_vars['order-pay']);
                $response = $this->validated_order_and_process_payment($order_id, self::ID);
                /** @var Token $token */
                $token = $response['token'];
                /** @var WC_Order $order */
                $order = $response['order'];

                $youcanpay_params['token_transaction'] = $token->getId();
                $youcanpay_params['is_pre_order'] = WC_YouCanPay_Order_Action_Enum::get_pre_order();
                $youcanpay_params['redirect'] = $response['redirect'] ?? null;
            }
            return $youcanpay_params;
        } catch (WC_YouCanPay_Exception $e) {
            wc_add_notice($e->getLocalizedMessage(), 'error');
        } catch (Throwable $e) {
            wc_add_notice(__('Fatal error, please try again.', 'youcan-pay'), 'error');
        }

        if (isset($order)) {
            $order->update_status('failed');
        }

        return [];
    }

    /**
     * Payment_scripts function.
     *
     * Output scripts used for youcanpay payment
     */
    public function payment_scripts()
    {
        if ( is_admin() ) {
            return;
        }

        // If YouCan Pay is not enabled bail.
        if ('no' === $this->enabled) {
            return;
        }

        // If keys are not set bail.
        if (!$this->are_keys_set()) {
            WC_YouCanPay_Logger::info('Keys are not set correctly.');

            return;
        }

        // If no SSL bail.
        if (!$this->sandbox_mode && !is_ssl()) {
            WC_YouCanPay_Logger::info('YouCan Pay production mode requires SSL.');

            return;
        }

        add_action('wp_head', [$this, 'pw_load_scripts']);
    }

    /**
     * @throws WC_YouCanPay_Exception
     */
    public function pw_load_scripts()
    {
        $jsSuffix = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? 'js' : 'min.js';

        wp_enqueue_script('py-script', WC_YouCanPay_Api_Enum::get_javascript_url(), [], time());
        wp_enqueue_script('youcan-pay-script', WC_YOUCAN_PAY_PLUGIN_URL . sprintf('/assets/js/youcan-pay.%s', $jsSuffix), [], WC_YOUCAN_PAY_VERSION);
        wp_localize_script('py-script', 'youcan_pay_script_vars', $this->javascript_params());
    }

    /**
     * Process the payment
     *
     * @param int $order_id Reference.
     *
     * @return array
     * @throws Throwable If payment will not be accepted.
     */
    public function process_payment($order_id)
    {
        try {
            $response = $this->validated_order_and_process_payment($order_id, self::ID);
            /** @var Token $token */
            $token = $response['token'];
            /** @var WC_Order $order */
            $order = $response['order'];
            $redirect = $response['redirect'] ?? null;

            return [
                'result'            => 'success',
                'redirect'          => $redirect,
                'token_transaction' => $token->getId(),
            ];
        } catch (WC_YouCanPay_Exception $e) {
            wc_add_notice($e->getLocalizedMessage(), 'error');
        } catch (Throwable $e) {
            wc_add_notice(__('Fatal error, please try again.', 'youcan-pay'), 'error');
        }

        if (isset($order)) {
            $order->update_status('failed');
        }

        return [
            'result'   => 'fail',
            'redirect' => '',
        ];
    }

    /**
     * Proceed with current request using new login session (to ensure consistent nonce).
     */
    public function set_cookie_on_current_request($cookie)
    {
        $_COOKIE[LOGGED_IN_COOKIE] = $cookie;
    }

    /**
     * Preserves the "wc-youcanpay-confirmation" URL parameter so the user can complete the SCA authentication after logging in.
     *
     * @param string $pay_url Current computed checkout URL for the given order.
     * @param WC_Order $order Order object.
     *
     * @return string Checkout URL for the given order.
     */
    public function get_checkout_payment_url($pay_url, $order)
    {
        global $wp;
        if (array_key_exists('wc-youcanpay-confirmation', $_GET)
            && isset($wp->query_vars['order-pay'])
            && $wp->query_vars['order-pay'] == $order->get_id()
        ) {
            $pay_url = add_query_arg('wc-youcanpay-confirmation', 1, $pay_url);
        }

        return $pay_url;
    }

    /**
     * Checks whether new keys are being entered when saving options.
     */
    public function process_admin_options()
    {
        parent::process_admin_options();
    }

    /**
     * @throws Exception
     */
    public function validate_public_key_field($key, $value)
    {
        $value = $this->validate_text_field($key, $value);
        if (!empty($value) && !preg_match('/^pub_/', $value)) {
            throw new Exception(
                __(
                    'The "Production Public key" should start with "pub", enter the correct key.',
                    'youcan-pay'
                )
            );
        }

        return $value;
    }

    /**
     * @throws Exception
     */
    public function validate_private_key_field($key, $value)
    {
        $value = $this->validate_text_field($key, $value);
        if (!empty($value) && !preg_match('/^pri_/', $value)) {
            throw new Exception(
                __(
                    'The "Production Private key" should start with "pri", enter the correct key.',
                    'youcan-pay'
                )
            );
        }

        return $value;
    }

    /**
     * @throws Exception
     */
    public function validate_sandbox_public_key_field($key, $value)
    {
        $value = $this->validate_text_field($key, $value);
        if (!empty($value) && !preg_match('/^pub_sandbox_/', $value)) {
            throw new Exception(
                __(
                    'The "Sandbox Public key" should start with "pub_sandbox", enter the correct key.',
                    'youcan-pay'
                )
            );
        }

        return $value;
    }

    /**
     * @throws Exception
     */
    public function validate_sandbox_private_key_field($key, $value)
    {
        $value = $this->validate_text_field($key, $value);
        if (!empty($value) && !preg_match('/^pri_sandbox_/', $value)) {
            throw new Exception(
                __(
                    'The "Sandbox Private key" should start with "pri_sandbox", enter the correct key.',
                    'youcan-pay'
                )
            );
        }

        return $value;
    }

    /**
     * Checks whether the gateway is enabled.
     *
     * @return bool The result.
     */
    public function is_enabled()
    {
        return 'yes' === $this->get_option('enabled');
    }

    /**
     * Disables gateway.
     */
    public function disable()
    {
        $this->update_option('enabled', 'no');
    }

    /**
     * Enables gateway.
     */
    public function enable()
    {
        $this->update_option('enabled', 'yes');
    }

    /**
     * Returns whether test_mode is active for the gateway.
     *
     * @return boolean Sandbox mode enabled if true, disabled if false.
     */
    public function is_in_test_mode()
    {
        return 'yes' === $this->get_option('sandbox_mode');
    }

    /**
     * Returns all supported currencies for this payment method.
     *
     * @return array
     */
    public function get_supported_currency()
    {
        return WC_YouCanPay_Currencies::get_all_index();
    }
}
