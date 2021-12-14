<?php
/**
 * Plugin Name: YouCan Pay
 * Plugin URI: https://pay.youcan.shop
 * Description: YouCan Pay for Woocommerce: allows you to receive fast and secure online card payments.
 * Author: YouCan Pay
 * Version: 2.0.0
 * Requires at least: 4.8
 * Tested up to: 5.8
 * WC requires at least: 4.6
 * WC tested up to: 5.9
 * Text Domain: youcan-pay
 * Domain Path: /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Required minimums and constants
 */
define( 'WC_YOUCAN_PAY_VERSION', '2.0.0' ); // WRCS: DEFINED_VERSION.
define( 'WC_YOUCAN_PAY_MIN_PHP_VER', '7.1.0' );
define( 'WC_YOUCAN_PAY_MIN_WC_VER', '4.6' );
define( 'WC_YOUCAN_PAY_FUTURE_MIN_WC_VER', '5.9' );
define( 'WC_YOUCAN_PAY_MAIN_FILE', __FILE__ );
define( 'WC_YOUCAN_PAY_ABSPATH', __DIR__ . '/' );
define( 'WC_YOUCAN_PAY_PLUGIN_URL', untrailingslashit( plugins_url( basename( plugin_dir_path( __FILE__ ) ), basename( __FILE__ ) ) ) );
define( 'WC_YOUCAN_PAY_PLUGIN_PATH', untrailingslashit( plugin_dir_path( __FILE__ ) ) );
define( 'WC_YOUCAN_PAY_PLUGIN_BASENAME', plugin_basename( dirname( __FILE__ ) ) );

/**
 * WooCommerce fallback notice.
 */
function woocommerce_youcanpay_missing_wc_notice() {
	/* translators: 1. URL link. */
	echo '<div class="error"><p><strong>' . sprintf( esc_html__( 'YouCan Pay requires WooCommerce to be installed and active. You can download %s here.',
			'youcan-pay' ),
			'<a href="https://woocommerce.com/" target="_blank">WooCommerce</a>' ) . '</strong></p></div>';
}

/**
 * WooCommerce not supported fallback notice.
 */
function woocommerce_youcanpay_wc_not_supported() {
	/* translators: $1. Minimum WooCommerce version. $2. Current WooCommerce version. */
	echo '<div class="error"><p><strong>' . sprintf( esc_html__( 'YouCan Pay requires WooCommerce %1$s or greater to be installed and active. WooCommerce %2$s is no longer supported.',
			'youcan-pay' ),
			WC_YOUCAN_PAY_MIN_WC_VER,
			WC_VERSION ) . '</strong></p></div>';
}

function woocommerce_gateway_youcanpay() {
	static $plugin;

	if ( ! isset( $plugin ) ) {
		class WC_YouCanPay {

			/**
			 * The *Singleton* instance of this class
			 *
			 * @var WC_YouCanPay
			 */
			private static $instance;

			/**
			 * Returns the *Singleton* instance of this class.
			 *
			 * @return WC_YouCanPay The *Singleton* instance.
			 */
			public static function get_instance() {
				if ( null === self::$instance ) {
					self::$instance = new self();
				}

				return self::$instance;
			}

			/**
			 * The main YouCan Pay gateway instance. Use get_main_youcanpay_gateway() to access it.
			 *
			 * @var null|WC_YouCanPay_Payment_Gateway
			 */
			protected $youcanpay_gateway = null;

			/**
			 * Private clone method to prevent cloning of the instance of the
			 * *Singleton* instance.
			 *
			 * @return void
			 */
			public function __clone() {
			}

			/**
			 * Private unserialize method to prevent unserializing of the *Singleton*
			 * instance.
			 *
			 * @return void
			 */
			public function __wakeup() {
			}

			/**
			 * Protected constructor to prevent creating a new instance of the
			 * *Singleton* via the `new` operator from outside of this class.
			 */
			public function __construct() {
				add_action( 'admin_init', [ $this, 'install' ] );

				$this->init();
			}

			/**
			 * Init the plugin after plugins_loaded so environment variables are set.
			 */
			public function init() {
				if ( is_admin() ) {
					require_once dirname( __FILE__ ) . '/includes/admin/class-wc-youcanpay-privacy.php';
				}

				require_once dirname( __FILE__ ) . '/vendor/autoload.php';
				require_once dirname( __FILE__ ) . '/includes/currencies/class-wc-youcanpay-currencies.php';
				require_once dirname( __FILE__ ) . '/includes/enums/class-wc-youcanpay-order-action-action-enum.php';
				require_once dirname( __FILE__ ) . '/includes/enums/class-wc-youcanpay-api-enum.php';
				require_once dirname( __FILE__ ) . '/includes/class-wc-youcanpay-exception.php';
				require_once dirname( __FILE__ ) . '/includes/class-wc-youcanpay-logger.php';
				require_once dirname( __FILE__ ) . '/includes/class-wc-youcanpay-helper.php';
				include_once dirname( __FILE__ ) . '/includes/class-wc-youcanpay-api.php';
				require_once dirname( __FILE__ ) . '/includes/abstracts/abstract-wc-youcanpay-payment-gateway.php';
				require_once dirname( __FILE__ ) . '/includes/class-wc-youcanpay-webhook-handler.php';
				require_once dirname( __FILE__ ) . '/includes/class-wc-gateway-youcanpay.php';
				require_once dirname( __FILE__ ) . '/includes/payment-methods/class-wc-gateway-youcanpay-standalone.php';

				if ( is_admin() ) {
					require_once dirname( __FILE__ ) . '/includes/admin/class-wc-youcanpay-admin-notices.php';
					require_once dirname( __FILE__ ) . '/includes/admin/class-wc-youcanpay-settings-controller.php';

					new WC_YouCanPay_Settings_Controller();
				}

				add_filter( 'woocommerce_payment_gateways', [ $this, 'add_gateways' ] );
				add_filter( 'pre_update_option_woocommerce_youcanpay_settings',
					[ $this, 'gateway_settings_update' ],
					10,
					2 );
				add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), [ $this, 'plugin_action_links' ] );
				add_filter( 'plugin_row_meta', [ $this, 'plugin_row_meta' ], 10, 2 );

				// Modify emails emails.
				add_filter( 'woocommerce_email_classes', [ $this, 'add_emails' ], 20 );

				if ( version_compare( WC_VERSION, '3.4', '<' ) ) {
					add_filter( 'woocommerce_get_sections_checkout', [ $this, 'filter_gateway_order_admin' ] );
				}
			}

			/**
			 * Updates the plugin version in db
			 */
			public function update_plugin_version() {
				delete_option( 'wc_youcanpay_version' );
				update_option( 'wc_youcanpay_version', WC_YOUCAN_PAY_VERSION );
			}

			/**
			 * Handles upgrade routines.
			 */
			public function install() {
				if ( ! is_plugin_active( plugin_basename( __FILE__ ) ) ) {
					return;
				}

				if ( ! defined( 'IFRAME_REQUEST' ) && ( WC_YOUCAN_PAY_VERSION !== get_option( 'wc_youcanpay_version' ) ) ) {
					do_action( 'woocommerce_youcanpay_updated' );

					if ( ! defined( 'WC_YOUCAN_PAY_INSTALLING' ) ) {
						define( 'WC_YOUCAN_PAY_INSTALLING', true );
					}

					add_woocommerce_inbox_variant();
					$this->update_plugin_version();
				}
			}

			/**
			 * Add plugin action links.
			 */
			public function plugin_action_links( $links ) {
				$plugin_links = [
					'<a href="admin.php?page=wc-settings&tab=checkout&section=youcanpay">' . esc_html__( 'Settings',
						'youcan-pay' ) . '</a>',
				];

				return array_merge( $plugin_links, $links );
			}

			/**
			 * Add plugin action links.
			 *
			 * @param array $links Original list of plugin links.
			 * @param string $file Name of current file.
			 *
			 * @return array  $links Update list of plugin links.
			 */
			public function plugin_row_meta( $links, $file ) {
				if ( plugin_basename( __FILE__ ) === $file ) {
					$row_meta = [
						'docs'    => '<a href="' . esc_url( apply_filters( 'woocommerce_gateway_youcanpay_docs_url',
								'https://youcan.shop/help/' ) ) . '" title="' . esc_attr( __( 'View Documentation',
								'youcan-pay' ) ) . '">' . __( 'Docs', 'youcan-pay' ) . '</a>',
						'support' => '<a href="' . esc_url( apply_filters( 'woocommerce_gateway_youcanpay_support_url',
								'https://youcan.shop/contact-us' ) ) . '" title="' . esc_attr( __( 'Open a support request',
								'youcan-pay' ) ) . '">' . __( 'Support', 'youcan-pay' ) . '</a>',
					];

					return array_merge( $links, $row_meta );
				}

				return (array) $links;
			}

			/**
			 * Add the gateways to WooCommerce.
			 */
			public function add_gateways( $methods ) {
				$methods[] = $this->get_main_youcanpay_gateway();

				// These payment gateways will always be visible, regardless if UPE is enabled or disabled:
				$methods[] = WC_Gateway_YouCanPay_Standalone::class;

				return $methods;
			}

			/**
			 * Modifies the order of the gateways displayed in admin.
			 */
			public function filter_gateway_order_admin( $sections ) {
				unset( $sections['youcanpay'] );
				unset( $sections['youcanpay_standalone'] );

				$sections['youcanpay']            = __( 'YouCan Pay', 'youcan-pay' );
				$sections['youcanpay_standalone'] = __( 'YouCan Pay Standalone', 'youcan-pay' );

				return $sections;
			}

			/**
			 * Provide default values for missing settings on initial gateway settings save.
			 *
			 * @param array $settings New settings to save.
			 * @param array|bool $old_settings Existing settings, if any.
			 *
			 * @return array New value but with defaults initially filled in for missing settings.
			 */
			public function gateway_settings_update( $settings, $old_settings ) {
				if ( false === $old_settings ) {
					$gateway      = new WC_Gateway_YouCanPay();
					$fields       = $gateway->get_form_fields();
					$old_settings = array_merge( array_fill_keys( array_keys( $fields ), '' ),
						wp_list_pluck( $fields, 'default' ) );
					$settings     = array_merge( $old_settings, $settings );
				}

				return $settings;
			}

			/**
			 * Adds the failed SCA auth email to WooCommerce.
			 *
			 * @param WC_Email[] $email_classes All existing emails.
			 *
			 * @return WC_Email[]
			 */
			public function add_emails( $email_classes ) {
				return $email_classes;
			}

			/**
			 * Returns the main YouCan Pay payment gateway class instance.
			 *
			 * @return WC_YouCanPay_Payment_Gateway
			 */
			public function get_main_youcanpay_gateway() {
				if ( ! is_null( $this->youcanpay_gateway ) ) {
					return $this->youcanpay_gateway;
				}

				$this->youcanpay_gateway = new WC_Gateway_YouCanPay();

				return $this->youcanpay_gateway;
			}
		}

		$plugin = WC_YouCanPay::get_instance();
	}

	return $plugin;
}

add_action( 'plugins_loaded', 'woocommerce_gateway_youcanpay_init' );

function woocommerce_gateway_youcanpay_init() {
	load_plugin_textdomain( 'youcan-pay', false, WC_YOUCAN_PAY_PLUGIN_BASENAME . '/languages' );

	if ( ! class_exists( 'WooCommerce' ) ) {
		add_action( 'admin_notices', 'woocommerce_youcanpay_missing_wc_notice' );

		return;
	}

	if ( version_compare( WC_VERSION, WC_YOUCAN_PAY_MIN_WC_VER, '<' ) ) {
		add_action( 'admin_notices', 'woocommerce_youcanpay_wc_not_supported' );

		return;
	}

	woocommerce_gateway_youcanpay();
}

/**
 * Add woocommerce_inbox_variant for the Remote Inbox Notification.
 *
 * P2 post can be found at https://wp.me/paJDYF-1uJ.
 */
if ( ! function_exists( 'add_woocommerce_inbox_variant' ) ) {
	function add_woocommerce_inbox_variant() {
		$config_name = 'woocommerce_inbox_variant_assignment';
		if ( false === get_option( $config_name, false ) ) {
			update_option( $config_name, wp_rand( 1, 12 ) );
		}
	}
}
register_activation_hook( __FILE__, 'add_woocommerce_inbox_variant' );
