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
        /* translators: 1) link to YouCan Pay register page 2) link to YouCan Pay api keys page */
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
        add_action('wp_enqueue_scripts', [$this, 'payment_scripts']);
        add_action('woocommerce_update_options_payment_gateways_' . $this->id, [$this, 'process_admin_options']);
        add_action('set_logged_in_cookie', [$this, 'set_cookie_on_current_request']);
        add_filter('woocommerce_get_checkout_payment_url', [$this, 'get_checkout_payment_url'], 10, 2);

        // Note: display error is in the parent class.
        add_action('admin_notices', [$this, 'display_errors'], 9999);
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
        $description = $this->get_description();
        $description = !empty($description) ? $description : '';

        ob_start();

        echo '<div id="youcanpay-payment-data-credit-card">';

        if ($this->sandbox_mode) {
            $text = __(
                'SANDBOX MODE ENABLED. In sandbox mode, you can use the card number 4242424242424242 with 112 CVC and 10/24 date or check the <a href="%s" target="_blank">Testing YouCan Pay documentation</a> for more card numbers.',
                'youcan-pay'
            );
            $description .= ' ' . sprintf($text, 'https://pay.youcan.shop/docs#testing-and-test-cards');
        }

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
                  style="background:transparent;">
            <?php
            do_action('woocommerce_credit_card_form_start', $this->id); ?>

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
     * @return array  The configuration object to be loaded to JS.
     */
    public function javascript_params()
    {
        try {
            $youcanpay_params = [
                'title'         => $this->title,
                'key'           => $this->public_key,
                'gateway'       => self::ID,
                'locale'        => WC_YouCanPay_Helper::get_supported_local(get_locale()),
                'checkout_url'  => WC_YouCanPay_Helper::get_ajax_checkout_url(),
                'is_test_mode'  => $this->is_in_test_mode(),
                'is_pre_order'  => 0,
                'order_actions' => WC_YouCanPay_Order_Action_Enum::get_all(),
            ];

            if (array_key_exists('order-pay', $_GET)) {
                $response = $this->validated_order_and_process_payment(wc_sanitize_order_id($_GET['order-pay']));
                /** @var Token $token */
                $token = $response['token'] ?? null;
                $order = $response['order'] ?? null;
                $redirect = $response['redirect'] ?? null;

                $youcanpay_params['token_transaction'] = (isset($token)) ? $token->getId() : 0;
                $youcanpay_params['is_pre_order'] = WC_YouCanPay_Order_Action_Enum::get_pre_order();
                $youcanpay_params['redirect'] = $redirect;
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
        if (
            !is_product()
            && !WC_YouCanPay_Helper::has_cart_or_checkout_on_current_page()
            && !array_key_exists('pay_for_order', $_GET)
            && !is_add_payment_method_page()
            && !array_key_exists('change_payment_method', $_GET) // wpcs: csrf ok.
            || (is_order_received_page())
        ) {
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
            WC_YouCanPay_Logger::info('YouCan Pay live mode requires SSL.');

            return;
        }

        add_action('wp_head', [$this, 'pw_load_scripts']);
    }

    /**
     * @throws WC_YouCanPay_Exception
     */
    public function pw_load_scripts()
    {
        $extension = '.js';
        if (!$this->sandbox_mode) {
            $extension = '.min.js';
        }

        wp_enqueue_script('py-script', WC_YouCanPay_Api_Enum::get_javascript_url());
        wp_enqueue_script('youcan-pay-script', WC_YOUCAN_PAY_PLUGIN_URL . '/assets/js/youcan-pay' . $extension);
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
            $response = $this->validated_order_and_process_payment($order_id);
            $token = $response['token'] ?? null;
            $order = $response['order'] ?? null;
            $redirect = $response['redirect'] ?? null;

            return [
                'result'            => 'success',
                'redirect'          => $redirect,
                'token_transaction' => (isset($token)) ? $token->getId() : 0,
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
     * @param $order_id
     *
     * @return array
     * @throws WC_YouCanPay_Exception|Throwable
     */
    public function validated_order_and_process_payment($order_id)
    {
        try {
            $order = wc_get_order($order_id);
            if (!isset($order)) {
                WC_YouCanPay_Logger::info('arrived on process payment: order not exists', [
                    'method'   => 'YouCan Pay (Credit Card)',
                    'code'     => '#0021',
                    'order_id' => $order_id,
                ]);

                throw new WC_YouCanPay_Exception(
                    'Order not found',
                    __('Fatal error, please try again or contact support.', 'youcan-pay')
                );
            }

            $order->set_status('on-hold');

            $redirect = $this->get_youcanpay_return_url($order, self::ID);

            $token = WC_YouCanPay_API::create_token(
                $order,
                $order->get_total(),
                $order->get_currency(),
                $redirect
            );

            if (is_wp_error($token) || empty($token)) {
                WC_YouCanPay_Logger::info('there was a problem connecting to the YouCan Pay API endpoint', [
                    'order_id' => $order->get_id(),
                ]);

                throw new WC_YouCanPay_Exception(
                    print_r($token, true),
                    __('There was a problem connecting to the YouCan Pay API endpoint.', 'youcan-pay')
                );
            }

            return [
                'token'    => $token,
                'order'    => $order,
                'redirect' => $redirect,
            ];
        } catch (WC_YouCanPay_Exception $e) {
            throw new WC_YouCanPay_Exception($e->getMessage(), $e->getLocalizedMessage());
        } catch (Throwable $e) {
            throw new Exception(__('Fatal error, please try again.', 'youcan-pay'));
        }
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
