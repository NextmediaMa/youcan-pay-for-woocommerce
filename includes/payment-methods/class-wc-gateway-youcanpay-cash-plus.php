<?php

use YouCan\Pay\Models\Token;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class that handles Cash Plus payment method.
 *
 * @extends WC_Gateway_YouCanPay
 */
class WC_Gateway_YouCanPay_Cash_Plus extends WC_YouCanPay_Payment_Gateway
{

    const ID = 'youcanpay_cash_plus';

    /**
     * Notices (array)
     *
     * @var array
     */
    public $notices = [];

    /**
     * Is sandbox mode active?
     *
     * @var bool
     */
    public $sandbox_mode;

    /**
     * API access secret key
     *
     * @var string
     */
    public $private_key;

    /**
     * Api access publishable key
     *
     * @var string
     */
    public $public_key;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->id = self::ID;
        $this->method_title = __('Cash Plus', 'youcan-pay');
        $this->method_description = sprintf(
            __('All other general Cash Plus settings can be adjusted <a href="%s">here</a>.', 'youcan-pay'),
            admin_url('admin.php?page=wc-settings&tab=checkout&section=youcanpay')
        );
        $this->supports = [
            'products',
            'refunds',
        ];

        // Load the form fields.
        $this->init_form_fields();

        // Load the settings.
        $this->init_settings();

        $main_settings = get_option('woocommerce_youcanpay_settings');
        $this->title = $this->get_option('title');
        $this->description = $this->get_option('description');
        $this->enabled = $this->get_option('enabled');
        $this->sandbox_mode = !empty($main_settings['sandbox_mode']) && 'yes' === $main_settings['sandbox_mode'];
        $this->public_key = !empty($main_settings['public_key']) ? $main_settings['public_key'] : '';
        $this->private_key = !empty($main_settings['private_key']) ? $main_settings['private_key'] : '';

        if ($this->sandbox_mode) {
            $this->public_key = !empty($main_settings['sandbox_public_key']) ? $main_settings['sandbox_public_key'] : '';
            $this->private_key = !empty($main_settings['sandbox_private_key']) ? $main_settings['sandbox_private_key'] : '';
        }

        add_action('woocommerce_update_options_payment_gateways_' . $this->id, [$this, 'process_admin_options']);
        add_action('wp_enqueue_scripts', [$this, 'payment_scripts']);
    }

    /**
     * Renders the Cash Plus elements form.
     */
    public function elements_form()
    {
        ?>
        <fieldset id="wc-<?php
        echo esc_attr($this->id); ?>-cc-form" class="wc-credit-card-form wc-payment-form"
                  style="background:transparent;">
            <?php
            do_action('woocommerce_cash_plus_form_start', $this->id); ?>

            <div class="form-row form-row-wide" id="container-cash-plus"></div>

            <div class="clear"></div>

            <!-- Used to display form errors -->
            <div class="youcanpay-source-errors" role="alert"></div>
            <?php
            do_action('woocommerce_cash_plus_form_end', $this->id); ?>
            <div class="clear"></div>
        </fieldset>
        <?php
    }

    /**
     * Checks to see if all criteria is met before showing payment method.
     *
     * @return bool
     */
    public function is_available()
    {
        if (!in_array(get_woocommerce_currency(), $this->get_supported_currency())) {
            return false;
        }

        return parent::is_available();
    }

    /**
     * Get_icon function.
     *
     * @return string
     */
    public function get_icon()
    {
        $icons = $this->payment_icons();
        $icons_str = $icons['cash_plus'] ?? '';

        return apply_filters('woocommerce_gateway_icon', $icons_str, $this->id);
    }

    /**
     * Payment_scripts function.
     */
    public function payment_scripts()
    {
        if (!WC_YouCanPay_Helper::has_cart_or_checkout_on_current_page()
            && !array_key_exists('pay_for_order', $_GET)
            && !is_add_payment_method_page()
        ) {
            return;
        }

        wp_enqueue_style('youcanpay_styles');
        wp_enqueue_script('woocommerce_youcanpay');
    }

    /**
     * Initialize Gateway Settings Form Fields.
     */
    public function init_form_fields()
    {
        $this->form_fields = require WC_YOUCAN_PAY_PLUGIN_PATH . '/includes/admin/youcanpay-cash-plus-settings.php';
    }

    /**
     * Payment form on checkout page
     */
    public function payment_fields()
    {
        $description = $this->get_description() ?? '';

        ob_start();

        echo '<div id="youcanpay-payment-data-cash-plus">';

        $description = trim($description);
        echo wpautop(wp_kses_post($description));

        $this->elements_form();

        echo '</div>';

        ob_end_flush();
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
     * Returns all supported currencies for this payment method.
     *
     * @return array
     */
    public function get_supported_currency()
    {
        return WC_YouCanPay_Currencies::get_all_index();
    }
}
