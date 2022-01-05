<?php

use YouCan\Pay\Models\Token;
use YouCan\Pay\Models\Transaction;
use YouCan\Pay\YouCanPay;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * WC_YouCanPay_API class.
 *
 * Communicates with YouCan Pay API.
 */
class WC_YouCanPay_API
{
    /**
     * Secret API Key.
     *
     * @var string
     */
    private static $private_key = '';

    /**
     * Secret API Key.
     *
     * @var string
     */
    private static $public_key = '';

    /**
     * Sandbox Mode is enabled.
     *
     * @var string
     */
    private static $is_test_mode = '';

    /**
     * Set secret API Key.
     *
     * @param string $private_key
     */
    public static function set_private_key($private_key)
    {
        self::$private_key = $private_key;
    }

    /**
     * Set secret API Key.
     *
     * @param string $public_key
     */
    public static function set_public_key($public_key)
    {
        self::$public_key = $public_key;
    }

    /**
     * Set secret API Key.
     *
     * @param string $test_mode
     */
    public static function set_test_mode($test_mode)
    {
        self::$is_test_mode = ('yes' === $test_mode);
    }

    /**
     * Get secret key.
     *
     * @return string
     */
    public static function get_private_key()
    {
        if (!self::$private_key) {
            $options = get_option('woocommerce_youcanpay_settings');

            if (isset($options['sandbox_mode'], $options['private_key'], $options['sandbox_private_key'])) {
                self::set_private_key(
                    'yes' === $options['sandbox_mode'] ? $options['sandbox_private_key'] : $options['private_key']
                );
            }
        }

        return self::$private_key;
    }

    /**
     * Get public key.
     *
     * @return string
     */
    public static function get_public_key()
    {
        if (!self::$public_key) {
            $options = get_option('woocommerce_youcanpay_settings');

            if (isset($options['sandbox_mode'], $options['public_key'], $options['sandbox_public_key'])) {
                self::set_public_key(
                    'yes' === $options['sandbox_mode'] ? $options['sandbox_public_key'] : $options['public_key']
                );
            }
        }

        return self::$public_key;
    }

    /**
     * Get public key.
     *
     * @return string
     */
    public static function is_test_mode()
    {
        if ('' === self::$is_test_mode) {
            $options = get_option('woocommerce_youcanpay_settings');

            if (isset($options['sandbox_mode'])) {
                self::set_test_mode($options['sandbox_mode']);
            }
        }

        return self::$is_test_mode;
    }

    /**
     * @param $transaction_id
     *
     * @return Transaction|null
     */
    public static function get_transaction($transaction_id)
    {
        try {
            if (self::is_test_mode()) {
                YouCanPay::setIsSandboxMode(true);
            }
            $py = YouCanPay::instance()->useKeys(
                self::get_private_key(),
                self::get_public_key()
            );

            return $py->transaction->get($transaction_id);
        } catch (Throwable $e) {
            WC_YouCanPay_Logger::alert('throwable at get transaction exists into wc youcan pay api', [
                'exception.message' => $e->getMessage(),
            ]);
        }

        return null;
    }

    /**
     * @param string $signature
     * @param array $payload
     *
     * @return bool
     */
    public static function verify_webhook_signature($signature, $payload)
    {
        try {
            if (self::is_test_mode()) {
                YouCanPay::setIsSandboxMode(true);
            }
            $py = YouCanPay::instance()->useKeys(
                self::get_private_key(),
                self::get_public_key()
            );

            return $py->verifyWebhookSignature($signature, $payload);
        } catch (Throwable $e) {
            WC_YouCanPay_Logger::alert('throwable at verify webhook signature exists into wc youcan pay api', [
                'exception.message' => $e->getMessage(),
            ]);
        }

        return false;
    }

    /**
     * @param $order WC_Order
     * @param $total
     * @param $currency
     * @param $return_url
     *
     * @return Token|null
     * @throws WC_YouCanPay_Exception|Throwable
     */
    public static function create_token($order, $total, $currency, $return_url)
    {
        try {
            $amount = WC_YouCanPay_Helper::get_youcanpay_amount($total, $currency);

            if (self::is_test_mode()) {
                YouCanPay::setIsSandboxMode(true);
            }

            $py = YouCanPay::instance()->useKeys(
                self::get_private_key(),
                self::get_public_key()
            );

            $token = $py->token->create(
                $order->get_id(),
                $amount,
                strtoupper($currency),
                self::get_the_user_ip(),
                $return_url,
                $return_url
            );

            if (is_wp_error($token) || (!isset($token))) {
                throw new WC_YouCanPay_Exception(
                    print_r($token, true),
                    __('There was a problem connecting to the YouCan Pay API endpoint.', 'youcan-pay')
                );
            }

            return $token;
        } catch (WC_YouCanPay_Exception $e) {
            throw new WC_YouCanPay_Exception($e->getMessage(), $e->getLocalizedMessage());
        } catch (Throwable $e) {
            throw new Exception(__('Fatal error, please try again.', 'youcan-pay'));
        }
    }

    /**
     * @return string
     */
    public static function get_the_user_ip()
    {
        //check ip from share internet
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            return $_SERVER['HTTP_CLIENT_IP'];
        }

        //to check ip is pass from proxy
        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            return $_SERVER['HTTP_X_FORWARDED_FOR'];
        }

        return $_SERVER['REMOTE_ADDR'];
    }

}
