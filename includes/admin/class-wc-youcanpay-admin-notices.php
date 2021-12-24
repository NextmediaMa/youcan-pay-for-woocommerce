<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class that represents admin notices.
 */
class WC_YouCanPay_Admin_Notices
{
    /**
     * Notices (array)
     *
     * @var array
     */
    public $notices = [];

    /**
     * Constructor
     */
    public function __construct()
    {
        add_action('admin_notices', [$this, 'admin_notices']);
        add_action('wp_loaded', [$this, 'hide_notices']);
        add_action('woocommerce_youcanpay_updated', [$this, 'youcanpay_updated']);
    }

    /**
     * Allow this class and other classes to add slug keyed notices (to avoid duplication).
     */
    public function add_admin_notice($slug, $class, $message, $dismissible = false)
    {
        $this->notices[$slug] = [
            'class'       => $class,
            'message'     => $message,
            'dismissible' => $dismissible,
        ];
    }

    /**
     * Display any notices we've collected thus far.
     */
    public function admin_notices()
    {
        if (!current_user_can('manage_woocommerce')) {
            return;
        }

        // Main YouCan Pay payment method.
        $this->youcanpay_check_environment();

        $this->payment_methods_check_environment();

        foreach ($this->notices as $notice_key => $notice) {
            echo '<div class="' . esc_attr($notice['class']) . '" style="position:relative;">';

            if ($notice['dismissible']) {
                ?>
                <a href="<?php
                echo esc_url(
                    wp_nonce_url(
                        add_query_arg('wc-youcanpay-hide-notice', $notice_key),
                        'wc_youcanpay_hide_notices_nonce',
                        '_wc_youcanpay_notice_nonce'
                    )
                ); ?>" class="woocommerce-message-close notice-dismiss"
                   style="position:relative;float:right;padding:9px 0 9px 9px;text-decoration:none;"></a>
                <?php
            }

            echo '<p>';
            echo wp_kses(
                $notice['message'],
                [
                    'a' => [
                        'href'   => [],
                        'target' => [],
                    ],
                ]
            );
            echo '</p></div>';
        }
    }

    /**
     * List of available payment methods.
     * @return array
     */
    public function get_payment_methods()
    {
        return [
            'YouCan Pay' => 'WC_Gateway_YouCanPay',
            'YouCan Pay Standalone' => 'WC_Gateway_YouCanPay_Standalone',
        ];
    }

    /**
     * The backup sanity check, in case the plugin is activated in a weird way,
     * or the environment changes after activation. Also handles upgrade routines.
     */
    public function youcanpay_check_environment()
    {
        $show_ssl_notice = get_option('wc_youcanpay_show_ssl_notice');
        $show_keys_notice = get_option('wc_youcanpay_show_keys_notice');
        $show_phpver_notice = get_option('wc_youcanpay_show_phpver_notice');
        $show_wcver_notice = get_option('wc_youcanpay_show_wcver_notice');
        $show_curl_notice = get_option('wc_youcanpay_show_curl_notice');
        $options = get_option('woocommerce_youcanpay_settings');
        $sandbox_mode = isset($options['sandbox_mode']) && 'yes' === $options['sandbox_mode'];
        $sandbox_public_key = $options['sandbox_public_key'] ?? '';
        $sandbox_private_key = $options['sandbox_private_key'] ?? '';
        $production_public_key = $options['public_key'] ?? '';
        $production_private_key = $options['private_key'] ?? '';

        if (isset($options['enabled']) && 'yes' === $options['enabled']) {
            if (empty($show_phpver_notice)) {
                if (version_compare(phpversion(), WC_YOUCAN_PAY_MIN_PHP_VER, '<')) {
                    /* translators: 1) int version 2) int version */
                    $message = __(
                        'YouCan Pay - The minimum PHP version required for this plugin is %1$s. You are running %2$s.',
                        'youcan-pay'
                    );

                    $this->add_admin_notice(
                        'phpver',
                        'error',
                        sprintf($message, WC_YOUCAN_PAY_MIN_PHP_VER, phpversion()),
                        true
                    );

                    return;
                }
            }

            if (empty($show_wcver_notice)) {
                if (WC_YouCanPay_Helper::is_wc_lt(WC_YOUCAN_PAY_FUTURE_MIN_WC_VER)) {
                    /* translators: 1) int version 2) int version */
                    $message = __(
                        'YouCan Pay - This is the last version of the plugin compatible with WooCommerce %1$s. All future versions of the plugin will require WooCommerce %2$s or greater.',
                        'youcan-pay'
                    );
                    $this->add_admin_notice(
                        'wcver',
                        'notice notice-warning',
                        sprintf($message, WC_VERSION, WC_YOUCAN_PAY_FUTURE_MIN_WC_VER),
                        true
                    );
                }
            }

            if (empty($show_curl_notice)) {
                if (!function_exists('curl_init')) {
                    $this->add_admin_notice(
                        'curl',
                        'notice notice-warning',
                        __('YouCan Pay - cURL is not installed.', 'youcan-pay'),
                        true
                    );
                }
            }

            if (empty($show_keys_notice)) {
                $secret = WC_YouCanPay_API::get_private_key();
                // phpcs:ignore
                $should_show_notice_on_page = !(
                    isset($_GET['page'], $_GET['section'])
                    && 'wc-settings' === $_GET['page']
                    && 0 === strpos($_GET['section'], 'youcanpay')
                );

                if (empty($secret) && $should_show_notice_on_page) {
                    $setting_link = $this->get_setting_link();
                    /* translators: 1) link */
                    $this->add_admin_notice(
                        'keys',
                        'notice notice-warning',
                        sprintf(
                            __(
                                'YouCan Pay is almost ready. To get started, <a href="%s">set your YouCan Pay account keys</a>.',
                                'youcan-pay'
                            ),
                            $setting_link
                        ),
                        true
                    );
                }

                // Check if keys are entered properly per Production/Sandbox mode.
                if ($sandbox_mode) {
                    if (
                        !empty($sandbox_public_key) && !preg_match('/^pub_sandbox_/', $sandbox_public_key)
                        || !empty($sandbox_private_key) && !preg_match(
                            '/^pri_sandbox_/',
                            $sandbox_private_key
                        )) {
                        $setting_link = $this->get_setting_link();
                        /* translators: 1) link */
                        $this->add_admin_notice(
                            'keys',
                            'notice notice-error',
                            sprintf(
                                __(
                                    'YouCan Pay is in Sandbox mode however your test keys may not be valid. Sandbox keys start with pub_sandbox and pri_sandbox. Please go to your settings and, <a href="%s">set your YouCan Pay account keys</a>.',
                                    'youcan-pay'
                                ),
                                $setting_link
                            ),
                            true
                        );
                    }
                } else {
                    if (
                        !empty($production_public_key) && !preg_match('/^pub_/', $production_public_key)
                        || !empty($production_private_key) && !preg_match('/^pri_/', $production_private_key)) {
                        $setting_link = $this->get_setting_link();
                        /* translators: 1) link */
                        $this->add_admin_notice(
                            'keys',
                            'notice notice-error',
                            sprintf(
                                __(
                                    'YouCan Pay is in production mode however your keys may not be valid. Production keys start with pub and pri. Please go to your settings and, <a href="%s">set your YouCan Pay account keys</a>.',
                                    'youcan-pay'
                                ),
                                $setting_link
                            ),
                            true
                        );
                    }
                }
            }

            if (empty($show_ssl_notice)) {
                // Show message if enabled and FORCE SSL is disabled and WordpressHTTPS plugin is not detected.
                if (!wc_checkout_is_https()) {
                    /* translators: 1) link */
                    $this->add_admin_notice(
                        'ssl',
                        'notice notice-warning',
                        sprintf(
                            __(
                                'YouCan Pay is enabled, but a SSL certificate is not detected. Your checkout may not be secure! Please ensure your server has a valid <a href="%1$s" target="_blank">SSL certificate</a>',
                                'youcan-pay'
                            ),
                            'https://woocommerce.com/document/ssl-and-https/'
                        ),
                        true
                    );
                }
            }
        }
    }

    /**
     * Environment check for all payment methods.
     */
    public function payment_methods_check_environment()
    {
        $payment_methods = $this->get_payment_methods();

        foreach ($payment_methods as $method => $class) {
            $method_converted = str_replace(' ', '_', strtolower($method));
            $show_notice = get_option('wc_youcanpay_show_' . $method_converted . '_notice');
            $gateway = new $class();

            if ('yes' !== $gateway->enabled || 'no' === $show_notice) {
                continue;
            }

            if (!in_array(get_woocommerce_currency(), $gateway->get_supported_currency(), true)) {
                $message = sprintf(
                    __('%1$s is enabled - it requires store currency to be set to %2$s', 'youcan-pay'),
                    $method,
                    implode(', ', $gateway->get_supported_currency())
                );
                $this->add_admin_notice($method_converted, 'notice notice-error', $message, true);
            }
        }
    }

    /**
     * Hides any admin notices.
     */
    public function hide_notices()
    {
        if (isset($_GET['wc-youcanpay-hide-notice']) && isset($_GET['_wc_youcanpay_notice_nonce'])) {
            if (!wp_verify_nonce(
                wc_clean(wp_unslash($_GET['_wc_youcanpay_notice_nonce'])),
                'wc_youcanpay_hide_notices_nonce'
            )) {
                wp_die(__('Action failed. Please refresh the page and retry.', 'youcan-pay'));
            }

            if (!current_user_can('manage_woocommerce')) {
                wp_die(__('Cheatin&#8217; huh?', 'youcan-pay'));
            }

            $notice = wc_clean(wp_unslash($_GET['wc-youcanpay-hide-notice']));

            switch ($notice) {
                case 'phpver':
                    update_option('wc_youcanpay_show_phpver_notice', 'no');
                    break;
                case 'wcver':
                    update_option('wc_youcanpay_show_wcver_notice', 'no');
                    break;
                case 'curl':
                    update_option('wc_youcanpay_show_curl_notice', 'no');
                    break;
                case 'ssl':
                    update_option('wc_youcanpay_show_ssl_notice', 'no');
                    break;
                case 'keys':
                    update_option('wc_youcanpay_show_keys_notice', 'no');
                    break;
                case 'YouCan Pay':
                    update_option('wc_youcanpay_show_youcan_pay_notice', 'no');
                    break;
                case 'YouCan Pay Standalone':
                    update_option('wc_youcanpay_show_youcan_pay_standalone_notice', 'no');
                    break;
            }
        }
    }

    /**
     * Get setting link.
     *
     * @return string Setting link
     */
    public function get_setting_link()
    {
        return admin_url('admin.php?page=wc-settings&tab=checkout&section=youcanpay');
    }

    /**
     * Saves options in order to hide notices based on the gateway's version.
     */
    public function youcanpay_updated()
    {
    }
}

new WC_YouCanPay_Admin_Notices();
