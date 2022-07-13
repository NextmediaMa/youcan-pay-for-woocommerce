<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class WC_YouCanPay_Webhook_Handler.
 *
 * Handles webhooks from YouCan Pay on sources that are not immediately chargeable.
 */
class WC_YouCanPay_Webhook_Handler extends WC_YouCanPay_Payment_Gateway
{
    /**
     * Is sandbox mode active?
     *
     * @var bool $sandbox_mode
     */
    public $sandbox_mode;

    /**
     * @var string $public_key
     */
    public $public_key;

    /**
     * @var string $private_key
     */
    public $private_key;

    /**
     * Constructor.
     */
    public function __construct()
    {
        $this->retry_interval = 2;
        $youcanpay_settings = get_option('woocommerce_youcanpay_settings', []);
        $this->sandbox_mode = !empty($youcanpay_settings['sandbox_mode']) && 'yes' === $youcanpay_settings['sandbox_mode'];

        add_action('woocommerce_api_wc_youcanpay', [$this, 'check_for_webhook']);
    }

    /**
     * Check incoming requests for YouCan Pay Webhook data and process them.
     */
    public function check_for_webhook()
    {
        if (!array_key_exists('REQUEST_METHOD', $_SERVER)
            || !array_key_exists('wc-api', $_GET)
            || ('wc_youcanpay' !== $_GET['wc-api'])
        ) {
            return false;
        }

        if (isset($_SERVER['REQUEST_METHOD']) && (strtoupper($_SERVER['REQUEST_METHOD']) === 'POST')) {
            return $this->post_request();
        } else {
            return $this->get_request($_GET);
        }
    }

    /**
     * @param array $data
     *
     * @return bool
     */
    private function get_request($data)
    {
        if (!array_key_exists('gateway', $data)) {
            return false;
        }

        switch (wc_clean(wp_unslash($data['gateway']))) {
            case WC_YouCanPay_Gateways_Enum::get_youcan_pay():
                return $this->youcanpay_credit_card();
            case WC_YouCanPay_Gateways_Enum::get_standalone():
                return $this->youcanpay_standalone();
        }

        return false;
    }

    /**
     * @return bool
     * @throws WC_Data_Exception
     */
    private function post_request()
    {
        $data = json_decode(file_get_contents('php://input'), true);

        if (json_last_error() == JSON_ERROR_NONE) {
            if (!array_key_exists('payload', $data)) {
                return false;
            }

            if (!array_key_exists('transaction', $data['payload'])) {
                return false;
            }

            if (!array_key_exists('payment_method', $data['payload'])) {
                return false;
            }

            if (!array_key_exists('HTTP_X_YOUCANPAY_SIGNATURE', $_SERVER)) {
                return false;
            }

            $verified_webhook = WC_YouCanPay_API::verify_webhook_signature(
                $_SERVER['HTTP_X_YOUCANPAY_SIGNATURE'],
                $data
            );

            if (true !== $verified_webhook) {
                return false;
            }

            $transaction_id = null;
            $token = new WC_YouCanPay_Token_Model($data['payload']['token']);
            $transaction = new WC_YouCanPay_Transaction_Model($data['payload']['transaction']);
            $payment_method = new WC_YouCanPay_Payment_Method_Model($data['payload']['payment_method']);
            $payment_method_name = sprintf(__('YouCan Pay (%s)', 'youcan-pay'), $payment_method->get_name());

            if (!isset($token)) {
                WC_YouCanPay_Logger::info('arrived on process payment: token not exists', [
                    'payment_method' => $payment_method_name,
                    'code'           => '#0022',
                ]);

                WC_YouCanPay_Webhook_State::set_last_webhook_failure_at(time());
                WC_YouCanPay_Webhook_State::set_last_error_reason(__('The token does not exist', 'youcan-pay'));

                return false;
            }

            if (!isset($transaction)) {
                WC_YouCanPay_Logger::info('arrived on process payment: transaction not exists', [
                    'payment_method' => $payment_method_name,
                    'code'           => '#0023',
                    'transaction_id' => $transaction_id,
                ]);

                WC_YouCanPay_Webhook_State::set_last_webhook_failure_at(time());
                WC_YouCanPay_Webhook_State::set_last_error_reason(__('The transaction does not exist', 'youcan-pay'));

                return false;
            }

            $transaction_id = $transaction->get_id();
            $order = wc_get_order($transaction->get_order_id());

            if (!isset($order)) {
                WC_YouCanPay_Logger::info('arrived on process payment: order not exists', [
                    'payment_method' => $payment_method_name,
                    'code'           => '#0024',
                    'transaction_id' => $transaction_id,
                    'order_id'       => $order->get_id(),
                ]);

                WC_YouCanPay_Webhook_State::set_last_webhook_failure_at(time());
                WC_YouCanPay_Webhook_State::set_last_error_reason(
                    __('The order received from the webhook does not exist', 'youcan-pay')
                );

                return false;
            }

            if ($transaction->get_status() === WC_YouCanPay_Transaction_Model::PAID_STATUS) {
                WC_YouCanPay_Logger::info('payment successfully processed', [
                    'payment_method' => $payment_method_name,
                    'transaction_id' => $transaction->get_id(),
                    'order_id'       => $order->get_id(),
                    'order_total'    => $order->get_total(),
                ]);

                $order->payment_complete($transaction->get_id());

                $order->update_meta_data('_youcanpay_source_id', $transaction->get_id());
                $order->save();

                WC_YouCanPay_Webhook_State::set_last_webhook_success_at(time());

                return true;
            }

            WC_YouCanPay_Logger::info('payment not processed', [
                'payment_method'     => $payment_method_name,
                'transaction_id'     => $transaction->get_id(),
                'transaction_status' => $transaction->get_status(),
                'order_id'           => $order->get_id(),
            ]);

            $order->set_status($transaction->get_status_string());
            $order->save();

            WC_YouCanPay_Webhook_State::set_last_webhook_failure_at(time());
            WC_YouCanPay_Webhook_State::set_last_error_reason(__('Payment not made', 'youcan-pay'));

            return false;
        }

        return false;
    }

    /**
     * @return bool
     */
    private function youcanpay_credit_card()
    {
        $transaction_id = null;
        $transaction = null;
        $action = WC_YouCanPay_Order_Action_Enum::get_incomplete();
        $all_actions = WC_YouCanPay_Order_Action_Enum::get_values();

        if (array_key_exists('action', $_GET) && (in_array($_GET['action'], $all_actions))) {
            $action = $_GET['action'];
        }

        if (array_key_exists('transaction_id', $_GET)) {
            $transaction_id = wc_clean(wp_unslash($_GET['transaction_id']));
            $transaction = WC_YouCanPay_API::get_transaction($transaction_id);
        }

        $checkout_url = $this->get_checkout_url_by_action($action);

        if (!isset($transaction)) {
            WC_YouCanPay_Logger::info('arrived on process payment: transaction not exists', [
                'payment_method' => 'YouCan Pay (Credit Card)',
                'code'           => '#0023',
                'transaction_id' => $transaction_id,
            ]);

            wc_add_notice(__('Please try again, This payment has been canceled!', 'youcan-pay'), 'error');

            return wp_redirect(wp_sanitize_redirect(esc_url_raw($checkout_url)));
        }

        $order = wc_get_order($transaction->getOrderId());
        if (!isset($order)) {
            WC_YouCanPay_Logger::info('arrived on process payment: order not exists', [
                'payment_method' => 'YouCan Pay (Credit Card)',
                'code'           => '#0024',
                'transaction_id' => $transaction_id,
                'order_id'       => $order->get_id(),
            ]);

            wc_add_notice(__('Fatal error, please try again or contact support.', 'youcan-pay'), 'error');

            return wp_redirect(wp_sanitize_redirect(esc_url_raw(get_home_url())));
        }

        if ($transaction->getStatus() === WC_YouCanPay_Transaction_Model::PAID_STATUS) {
            WC_YouCanPay_Logger::info('payment successfully processed', [
                'payment_method' => 'YouCan Pay (Credit Card)',
                'transaction_id' => $transaction->getId(),
                'order_id'       => $order->get_id(),
                'order_total'    => $order->get_total(),
            ]);

            WC_YouCanPay_Helper::set_payment_method_to_order($order, WC_Gateway_YouCanPay::ID);
            $order->payment_complete($transaction->getId());

            $order->update_meta_data('_youcanpay_source_id', $transaction->getId());
            $order->save();

            if (isset(WC()->cart)) {
                WC()->cart->empty_cart();
            }

            return wp_redirect(wp_sanitize_redirect(esc_url_raw($this->get_return_url($order))));
        } else {
            WC_YouCanPay_Logger::info('payment not processed', [
                'payment_method'     => 'YouCan Pay (Credit Card)',
                'transaction_status' => $transaction->getStatus(),
                'transaction_id'     => $transaction->getId(),
                'order_id'           => $order->get_id(),
                'order_total'        => $order->get_total(),
            ]);

            $order->set_status('failed');
            $order->save();

            $error = 'Fatal error, please try again.';
            if (array_key_exists('message', $_GET)) {
                $error = wc_clean(wp_unslash($_GET['message']));
            }
            wc_add_notice(__($error, 'youcan-pay'), 'error');

            return wp_redirect(wp_sanitize_redirect(esc_url_raw($checkout_url)));
        }
    }

    /**
     * @param int $action
     *
     * @return string
     */
    private function get_checkout_url_by_action($action)
    {
        $checkout_url = wc_get_checkout_url();

        if (!in_array($action, WC_YouCanPay_Order_Action_Enum::get_values())) {
            return $checkout_url;
        }

        $order_id = null;
        $order_key = null;

        if (array_key_exists('order_id', $_GET)) {
            $order_id = wc_sanitize_order_id($_GET['order_id']);
        }

        if (array_key_exists('key', $_GET)) {
            $order_key = wc_clean(wp_unslash($_GET['key']));
        }

        if ($action == WC_YouCanPay_Order_Action_Enum::get_pre_order()) {
            $checkout_url = add_query_arg(
                [
                    'pay_for_order' => 'true',
                    'key'           => $order_key,
                ],
                wc_get_endpoint_url('order-pay', $order_id, wc_get_checkout_url())
            );
        }

        return $checkout_url;
    }

    /**
     * @return bool
     */
    private function youcanpay_standalone()
    {
        if (!array_key_exists('key', $_GET)) {
            wc_add_notice(__('Fatal error, please try again.', 'youcan-pay'), 'error');

            return wp_redirect(wp_sanitize_redirect(esc_url_raw(get_home_url())));
        }

        /** @var WC_Order|WC_Order_Refund $order $order */
        $transaction_id = null;
        $transaction = null;
        $action = WC_YouCanPay_Order_Action_Enum::get_incomplete();

        if (array_key_exists('action', $_GET)) {
            $action = wc_clean(wp_unslash($_GET['action']));
        }

        $order_key = wc_clean(wp_unslash($_GET['key']));
        $order_id = wc_get_order_id_by_order_key($order_key);
        $order = wc_get_order($order_id);
        $checkout_url = $this->get_checkout_url_by_action($action);

        if (!isset($order)) {
            WC_YouCanPay_Logger::info('arrived on process payment: order not exists', [
                'payment_method' => 'YouCan Pay (Standalone)',
                'code'           => '#0021',
                'order_key'      => $order_key,
                'order_id'       => $order_id,
                'action'         => $action,
            ]);

            wc_add_notice(__('Fatal error, please try again or contact support.', 'youcan-pay'), 'error');

            return wp_redirect(wp_sanitize_redirect(esc_url_raw(get_home_url())));
        }

        if (array_key_exists('transaction_id', $_GET)) {
            $transaction_id = wc_clean(wp_unslash($_GET['transaction_id']));
            $transaction = WC_YouCanPay_API::get_transaction($transaction_id);
        }

        if (!isset($transaction)) {
            WC_YouCanPay_Logger::info('arrived on process payment: transaction not exists', [
                'payment_method' => 'YouCan Pay (Standalone)',
                'code'           => '#0022',
                'transaction_id' => $transaction_id,
                'order_id'       => $order->get_id(),
                'action'         => $action,
            ]);

            wc_add_notice(__('Please try again, This payment has been canceled!', 'youcan-pay'), 'error');

            return wp_redirect(wp_sanitize_redirect(esc_url_raw($checkout_url)));
        }

        if ($transaction->getOrderId() != $order->get_id()) {
            WC_YouCanPay_Logger::info('arrived on process payment: order not identical with transaction', [
                'payment_method'       => 'YouCan Pay (Standalone)',
                'code'                 => '#0023',
                'transaction_id'       => $transaction->getId(),
                'transaction_order_id' => $transaction->getOrderId(),
                'order_id'             => $order->get_id(),
                'action'               => $action,
            ]);

            wc_add_notice(
                __('Fatal error, please try again or contact support.', 'youcan-pay'),
                'error'
            );

            return wp_redirect(wp_sanitize_redirect(esc_url_raw(get_home_url())));
        }

        if ($transaction->getStatus() === WC_YouCanPay_Transaction_Model::PAID_STATUS) {
            WC_YouCanPay_Logger::info('payment successfully processed', [
                'payment_method' => 'YouCan Pay (Standalone)',
                'transaction_id' => $transaction->getId(),
                'order_id'       => $order->get_id(),
                'order_total'    => $order->get_total(),
                'action'         => $action,
            ]);

            WC_YouCanPay_Helper::set_payment_method_to_order($order, WC_Gateway_YouCanPay_Standalone::ID);
            $order->payment_complete($transaction->getId());

            return wp_redirect(wp_sanitize_redirect(esc_url_raw($this->get_return_url($order))));
        } else {
            WC_YouCanPay_Logger::info('payment not processed', [
                'payment_method'     => 'YouCan Pay (Standalone)',
                'transaction_id'     => $transaction->getId(),
                'transaction_status' => $transaction->getStatus(),
                'order_id'           => $order->get_id(),
                'action'             => $action,
            ]);

            wc_add_notice(
                __('Sorry, payment not completed please try again.', 'youcan-pay'),
                'error'
            );

            $order->set_status('failed');
            $order->save();

            return wp_redirect(wp_sanitize_redirect(esc_url_raw($checkout_url)));
        }
    }
}

new WC_YouCanPay_Webhook_Handler();
