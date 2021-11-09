<?php
/**
 * Plugin Name: Woocommerce YouCan Pay
 * Plugin URI: https://pay.youcan.shop
 * Description: Woocommerce YouCan Pay: allows you to receive fast and secure online card payments.
 * Author:  YouCan Pay.
 * Author URI: https://pay.youcan.shop
 * Version: 1.0
 * Requires at least: 5.6
 * Tested up to: 5.8
 * WC requires at least: 5.6
 * WC tested up to: 5.8
 * Text Domain: woocommerce-gateway-youcan-pay
 * Domain Path: /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Required minimums and constants
 */
define( 'WC_YOUCAN_PAY_VERSION', '1.0.0' ); // WRCS: DEFINED_VERSION.
define( 'WC_YOUCAN_PAY_MIN_PHP_VER', '5.6.0' );
define( 'WC_YOUCAN_PAY_MIN_WC_VER', '3.0' );
define( 'WC_YOUCAN_PAY_FUTURE_MIN_WC_VER', '3.3' );
define( 'WC_YOUCAN_PAY_MAIN_FILE', __FILE__ );
define( 'WC_YOUCAN_PAY_ABSPATH', __DIR__ . '/' );
define( 'WC_YOUCAN_PAY_PLUGIN_URL', untrailingslashit( plugins_url( basename( plugin_dir_path( __FILE__ ) ), basename( __FILE__ ) ) ) );
define( 'WC_YOUCAN_PAY_PLUGIN_PATH', untrailingslashit( plugin_dir_path( __FILE__ ) ) );

// phpcs:disable WordPress.Files.FileName

/**
 * WooCommerce fallback notice.
 *
 * @since 4.1.2
 */
function woocommerce_youcanpay_missing_wc_notice() {
	/* translators: 1. URL link. */
	echo '<div class="error"><p><strong>' . sprintf( esc_html__( 'YouCanPay requires WooCommerce to be installed and active. You can download %s here.', 'woocommerce-gateway-youcanpay' ), '<a href="https://woocommerce.com/" target="_blank">WooCommerce</a>' ) . '</strong></p></div>';
}

/**
 * WooCommerce not supported fallback notice.
 *
 * @since 4.4.0
 */
function woocommerce_youcanpay_wc_not_supported() {
	/* translators: $1. Minimum WooCommerce version. $2. Current WooCommerce version. */
	echo '<div class="error"><p><strong>' . sprintf( esc_html__( 'YouCanPay requires WooCommerce %1$s or greater to be installed and active. WooCommerce %2$s is no longer supported.', 'woocommerce-gateway-youcanpay' ), WC_YOUCAN_PAY_MIN_WC_VER, WC_VERSION ) . '</strong></p></div>';
}

function woocommerce_gateway_youcanpay() {

	static $plugin;

	if ( ! isset( $plugin ) ) {

		class WC_YouCanPay {

			/**
			 * The *Singleton* instance of this class
			 *
			 * @var Singleton
			 */
			private static $instance;

			/**
			 * Returns the *Singleton* instance of this class.
			 *
			 * @return Singleton The *Singleton* instance.
			 */
			public static function get_instance() {
				if ( null === self::$instance ) {
					self::$instance = new self();
				}
				return self::$instance;
			}

			/**
			 * YouCanPay Connect API
			 *
			 * @var WC_YouCanPay_Connect_API
			 */
			private $api;

			/**
			 * YouCanPay Connect
			 *
			 * @var WC_YouCanPay_Connect
			 */
			public $connect;

			/**
			 * YouCanPay Payment Request configurations.
			 *
			 * @var WC_YouCanPay_Payment_Request
			 */
			public $payment_request_configuration;

			/**
			 * YouCanPay Account.
			 *
			 * @var WC_YouCanPay_Account
			 */
			public $account;

			/**
			 * The main YouCanPay gateway instance. Use get_main_youcanpay_gateway() to access it.
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
			public function __clone() {}

			/**
			 * Private unserialize method to prevent unserializing of the *Singleton*
			 * instance.
			 *
			 * @return void
			 */
			public function __wakeup() {}

			/**
			 * Protected constructor to prevent creating a new instance of the
			 * *Singleton* via the `new` operator from outside of this class.
			 */
			public function __construct() {
				add_action( 'admin_init', [ $this, 'install' ] );

				$this->init();

				add_action( 'rest_api_init', [ $this, 'register_routes' ] );
			}

			/**
			 * Init the plugin after plugins_loaded so environment variables are set.
			 *
			 * @since 1.0.0
			 * @version 5.0.0
			 */
			public function init() {
				if ( is_admin() ) {
					require_once dirname( __FILE__ ) . '/includes/admin/class-wc-youcanpay-privacy.php';
				}

				require_once dirname( __FILE__ ) . '/includes/class-wc-youcanpay-feature-flags.php';
				require_once dirname( __FILE__ ) . '/includes/class-wc-youcanpay-upe-compatibility.php';
				require_once dirname( __FILE__ ) . '/includes/class-wc-youcanpay-exception.php';
				require_once dirname( __FILE__ ) . '/includes/class-wc-youcanpay-logger.php';
				require_once dirname( __FILE__ ) . '/includes/class-wc-youcanpay-helper.php';
				include_once dirname( __FILE__ ) . '/includes/class-wc-youcanpay-api.php';
				require_once dirname( __FILE__ ) . '/includes/compat/trait-wc-youcanpay-subscriptions-utilities.php';
				require_once dirname( __FILE__ ) . '/includes/compat/trait-wc-youcanpay-subscriptions.php';
				require_once dirname( __FILE__ ) . '/includes/abstracts/abstract-wc-youcanpay-payment-gateway.php';
				require_once dirname( __FILE__ ) . '/includes/class-wc-youcanpay-webhook-state.php';
				require_once dirname( __FILE__ ) . '/includes/class-wc-youcanpay-webhook-handler.php';
				require_once dirname( __FILE__ ) . '/includes/class-wc-youcanpay-sepa-payment-token.php';
				require_once dirname( __FILE__ ) . '/includes/class-wc-youcanpay-apple-pay-registration.php';
				require_once dirname( __FILE__ ) . '/includes/compat/class-wc-youcanpay-pre-orders-compat.php';
				require_once dirname( __FILE__ ) . '/includes/class-wc-gateway-youcanpay.php';
				require_once dirname( __FILE__ ) . '/includes/payment-methods/class-wc-youcanpay-upe-payment-gateway.php';
				require_once dirname( __FILE__ ) . '/includes/payment-methods/class-wc-youcanpay-upe-payment-method.php';
				require_once dirname( __FILE__ ) . '/includes/payment-methods/class-wc-youcanpay-upe-payment-method-cc.php';
				require_once dirname( __FILE__ ) . '/includes/payment-methods/class-wc-youcanpay-upe-payment-method-giropay.php';
				require_once dirname( __FILE__ ) . '/includes/payment-methods/class-wc-youcanpay-upe-payment-method-ideal.php';
				require_once dirname( __FILE__ ) . '/includes/payment-methods/class-wc-youcanpay-upe-payment-method-bancontact.php';
				require_once dirname( __FILE__ ) . '/includes/payment-methods/class-wc-youcanpay-upe-payment-method-eps.php';
				require_once dirname( __FILE__ ) . '/includes/payment-methods/class-wc-youcanpay-upe-payment-method-sepa.php';
				require_once dirname( __FILE__ ) . '/includes/payment-methods/class-wc-youcanpay-upe-payment-method-p24.php';
				require_once dirname( __FILE__ ) . '/includes/payment-methods/class-wc-youcanpay-upe-payment-method-sofort.php';
				require_once dirname( __FILE__ ) . '/includes/payment-methods/class-wc-gateway-youcanpay-bancontact.php';
				require_once dirname( __FILE__ ) . '/includes/payment-methods/class-wc-gateway-youcanpay-sofort.php';
				require_once dirname( __FILE__ ) . '/includes/payment-methods/class-wc-gateway-youcanpay-giropay.php';
				require_once dirname( __FILE__ ) . '/includes/payment-methods/class-wc-gateway-youcanpay-eps.php';
				require_once dirname( __FILE__ ) . '/includes/payment-methods/class-wc-gateway-youcanpay-ideal.php';
				require_once dirname( __FILE__ ) . '/includes/payment-methods/class-wc-gateway-youcanpay-p24.php';
				require_once dirname( __FILE__ ) . '/includes/payment-methods/class-wc-gateway-youcanpay-standalone.php';
				require_once dirname( __FILE__ ) . '/includes/payment-methods/class-wc-gateway-youcanpay-sepa.php';
				require_once dirname( __FILE__ ) . '/includes/payment-methods/class-wc-gateway-youcanpay-multibanco.php';
				require_once dirname( __FILE__ ) . '/includes/payment-methods/class-wc-youcanpay-payment-request.php';
				require_once dirname( __FILE__ ) . '/includes/compat/class-wc-youcanpay-woo-compat-utils.php';
				require_once dirname( __FILE__ ) . '/includes/connect/class-wc-youcanpay-connect.php';
				require_once dirname( __FILE__ ) . '/includes/connect/class-wc-youcanpay-connect-api.php';
				require_once dirname( __FILE__ ) . '/includes/class-wc-youcanpay-order-handler.php';
				require_once dirname( __FILE__ ) . '/includes/class-wc-youcanpay-payment-tokens.php';
				require_once dirname( __FILE__ ) . '/includes/class-wc-youcanpay-customer.php';
				require_once dirname( __FILE__ ) . '/includes/class-wc-youcanpay-intent-controller.php';
				require_once dirname( __FILE__ ) . '/includes/admin/class-wc-youcanpay-inbox-notes.php';
				require_once dirname( __FILE__ ) . '/includes/admin/class-wc-youcanpay-upe-compatibility-controller.php';
				require_once dirname( __FILE__ ) . '/includes/migrations/class-allowed-payment-request-button-types-update.php';
				require_once dirname( __FILE__ ) . '/includes/class-wc-youcanpay-account.php';
				new Allowed_YouCan_Pay_Request_Button_Types_Update();

				$this->api                           = new WC_YouCanPay_Connect_API();
				$this->connect                       = new WC_YouCanPay_Connect( $this->api );
				$this->payment_request_configuration = new WC_YouCanPay_Payment_Request();
				$this->account                       = new WC_YouCanPay_Account( $this->connect, 'WC_YouCanPay_API' );

				if ( is_admin() ) {
					require_once dirname( __FILE__ ) . '/includes/admin/class-wc-youcanpay-admin-notices.php';
					require_once dirname( __FILE__ ) . '/includes/admin/class-wc-youcanpay-settings-controller.php';

					if ( WC_YouCanPay_Feature_Flags::is_upe_preview_enabled() && ! WC_YouCanPay_Feature_Flags::is_upe_settings_redesign_enabled() ) {
						require_once dirname( __FILE__ ) . '/includes/admin/class-wc-youcanpay-old-settings-upe-toggle-controller.php';
						new WC_YouCanPay_Old_Settings_UPE_Toggle_Controller();
					}

					if ( isset( $_GET['area'] ) && 'payment_requests' === $_GET['area'] ) {
						require_once dirname( __FILE__ ) . '/includes/admin/class-wc-youcanpay-payment-requests-controller.php';
						new WC_YouCanPay_Payment_Requests_Controller();
					} else {
						new WC_YouCanPay_Settings_Controller( $this->account );
					}

					if ( WC_YouCanPay_Feature_Flags::is_upe_settings_redesign_enabled() ) {
						require_once dirname( __FILE__ ) . '/includes/admin/class-wc-youcanpay-onboarding-controller.php';
						new WC_YouCanPay_Onboarding_Controller();
					}

					if ( WC_YouCanPay_Feature_Flags::is_upe_checkout_enabled() && WC_YouCanPay_Feature_Flags::is_upe_settings_redesign_enabled() ) {
						require_once dirname( __FILE__ ) . '/includes/admin/class-wc-youcanpay-payment-gateways-controller.php';
						new WC_YouCanPay_Payment_Gateways_Controller();
					}
				}

				// REMOVE IN THE FUTURE.
				require_once dirname( __FILE__ ) . '/includes/deprecated/class-wc-youcanpay-apple-pay.php';

				add_filter( 'woocommerce_payment_gateways', [ $this, 'add_gateways' ] );
				add_filter( 'pre_update_option_woocommerce_youcanpay_settings', [ $this, 'gateway_settings_update' ], 10, 2 );
				add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), [ $this, 'plugin_action_links' ] );
				add_filter( 'plugin_row_meta', [ $this, 'plugin_row_meta' ], 10, 2 );

				// Modify emails emails.
				add_filter( 'woocommerce_email_classes', [ $this, 'add_emails' ], 20 );

				if ( version_compare( WC_VERSION, '3.4', '<' ) ) {
					add_filter( 'woocommerce_get_sections_checkout', [ $this, 'filter_gateway_order_admin' ] );
				}

				new WC_YouCanPay_UPE_Compatibility_Controller();

				// Disable UPE if Pre Order extension is active.
				if ( WC_YouCanPay_Helper::is_pre_orders_exists() && WC_YouCanPay_Feature_Flags::is_upe_checkout_enabled() ) {
					$youcanpay_settings = get_option( 'woocommerce_youcanpay_settings' );
					$youcanpay_settings[ WC_YouCanPay_Feature_Flags::UPE_CHECKOUT_FEATURE_ATTRIBUTE_NAME ] = 'no';
					update_option( 'woocommerce_youcanpay_settings', $youcanpay_settings );
				}
			}

			/**
			 * Updates the plugin version in db
			 *
			 * @since 3.1.0
			 * @version 4.0.0
			 */
			public function update_plugin_version() {
				delete_option( 'wc_youcanpay_version' );
				update_option( 'wc_youcanpay_version', WC_YOUCAN_PAY_VERSION );
			}

			/**
			 * Handles upgrade routines.
			 *
			 * @since 3.1.0
			 * @version 3.1.0
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

					// TODO: Remove this when we're reasonably sure most merchants have had their
					// settings updated like this. ~80% of merchants is a good threshold.
					// - @reykjalin
					$this->update_prb_location_settings();
				}
			}

			/**
			 * Updates the PRB location settings based on deprecated filters.
			 *
			 * The filters were removed in favor of plugin settings. This function can, and should,
			 * be removed when we're reasonably sure most merchants have had their settings updated
			 * through this function. Maybe ~80% of merchants is a good threshold?
			 *
			 * @since 5.5.0
			 * @version 5.5.0
			 */
			public function update_prb_location_settings() {
				$youcanpay_settings = get_option( 'woocommerce_youcanpay_settings', [] );
				$prb_locations   = isset( $youcanpay_settings['payment_request_button_locations'] )
					? $youcanpay_settings['payment_request_button_locations']
					: [];
				if ( ! empty( $youcanpay_settings ) && empty( $prb_locations ) ) {
					global $post;

					$should_show_on_product_page  = ! apply_filters( 'wc_youcanpay_hide_payment_request_on_product_page', false, $post );
					$should_show_on_cart_page     = apply_filters( 'wc_youcanpay_show_payment_request_on_cart', true );
					$should_show_on_checkout_page = apply_filters( 'wc_youcanpay_show_payment_request_on_checkout', false, $post );

					$new_prb_locations = [];

					if ( $should_show_on_product_page ) {
						$new_prb_locations[] = 'product';
					}

					if ( $should_show_on_cart_page ) {
						$new_prb_locations[] = 'cart';
					}

					if ( $should_show_on_checkout_page ) {
						$new_prb_locations[] = 'checkout';
					}

					$youcanpay_settings['payment_request_button_locations'] = $new_prb_locations;
					update_option( 'woocommerce_youcanpay_settings', $youcanpay_settings );
				}
			}

			/**
			 * Add plugin action links.
			 *
			 * @since 1.0.0
			 * @version 4.0.0
			 */
			public function plugin_action_links( $links ) {
				$plugin_links = [
					'<a href="admin.php?page=wc-settings&tab=checkout&section=youcanpay">' . esc_html__( 'Settings', 'woocommerce-gateway-youcanpay' ) . '</a>',
				];
				return array_merge( $plugin_links, $links );
			}

			/**
			 * Add plugin action links.
			 *
			 * @since 4.3.4
			 * @param  array  $links Original list of plugin links.
			 * @param  string $file  Name of current file.
			 * @return array  $links Update list of plugin links.
			 */
			public function plugin_row_meta( $links, $file ) {
				if ( plugin_basename( __FILE__ ) === $file ) {
					$row_meta = [
						'docs'    => '<a href="' . esc_url( apply_filters( 'woocommerce_gateway_youcanpay_docs_url', 'https://docs.woocommerce.com/document/youcanpay/' ) ) . '" title="' . esc_attr( __( 'View Documentation', 'woocommerce-gateway-youcanpay' ) ) . '">' . __( 'Docs', 'woocommerce-gateway-youcanpay' ) . '</a>',
						'support' => '<a href="' . esc_url( apply_filters( 'woocommerce_gateway_youcanpay_support_url', 'https://woocommerce.com/my-account/create-a-ticket?select=19612' ) ) . '" title="' . esc_attr( __( 'Open a support request at WooCommerce.com', 'woocommerce-gateway-youcanpay' ) ) . '">' . __( 'Support', 'woocommerce-gateway-youcanpay' ) . '</a>',
					];
					return array_merge( $links, $row_meta );
				}
				return (array) $links;
			}

			/**
			 * Add the gateways to WooCommerce.
			 *
			 * @since 1.0.0
			 * @version 5.6.0
			 */
			public function add_gateways( $methods ) {
				$methods[] = $this->get_main_youcanpay_gateway();

				if ( ! WC_YouCanPay_Feature_Flags::is_upe_preview_enabled() || ! WC_YouCanPay_Feature_Flags::is_upe_checkout_enabled() ) {
					// These payment gateways will be hidden when UPE is enabled:
					$methods[] = WC_Gateway_YouCanPay_Sepa::class;
					$methods[] = WC_Gateway_YouCanPay_Giropay::class;
					$methods[] = WC_Gateway_YouCanPay_Ideal::class;
					$methods[] = WC_Gateway_YouCanPay_Bancontact::class;
					$methods[] = WC_Gateway_YouCanPay_Eps::class;
					$methods[] = WC_Gateway_YouCanPay_Sofort::class;
					$methods[] = WC_Gateway_YouCanPay_P24::class;
				}

				// These payment gateways will always be visible, regardless if UPE is enabled or disabled:
				$methods[] = WC_Gateway_YouCanPay_Standalone::class;
				$methods[] = WC_Gateway_YouCanPay_Multibanco::class;

				return $methods;
			}

			/**
			 * Modifies the order of the gateways displayed in admin.
			 *
			 * @since 4.0.0
			 * @version 4.0.0
			 */
			public function filter_gateway_order_admin( $sections ) {
				unset( $sections['youcanpay'] );
				if ( WC_YouCanPay_Feature_Flags::is_upe_preview_enabled() ) {
					unset( $sections['youcanpay_upe'] );
				}
				unset( $sections['youcanpay_bancontact'] );
				unset( $sections['youcanpay_sofort'] );
				unset( $sections['youcanpay_giropay'] );
				unset( $sections['youcanpay_eps'] );
				unset( $sections['youcanpay_ideal'] );
				unset( $sections['youcanpay_p24'] );
				unset( $sections['youcanpay_standalone'] );
				unset( $sections['youcanpay_sepa'] );
				unset( $sections['youcanpay_multibanco'] );

				$sections['youcanpay'] = 'YouCanPay';
				if ( WC_YouCanPay_Feature_Flags::is_upe_preview_enabled() ) {
					$sections['youcanpay_upe'] = 'YouCanPay checkout experience';
				}
				$sections['youcanpay_bancontact'] = __( 'YouCanPay Bancontact', 'woocommerce-gateway-youcanpay' );
				$sections['youcanpay_sofort']     = __( 'YouCanPay SOFORT', 'woocommerce-gateway-youcanpay' );
				$sections['youcanpay_giropay']    = __( 'YouCanPay Giropay', 'woocommerce-gateway-youcanpay' );
				$sections['youcanpay_eps']        = __( 'YouCanPay EPS', 'woocommerce-gateway-youcanpay' );
				$sections['youcanpay_ideal']      = __( 'YouCanPay iDeal', 'woocommerce-gateway-youcanpay' );
				$sections['youcanpay_p24']        = __( 'YouCanPay P24', 'woocommerce-gateway-youcanpay' );
				$sections['youcanpay_standalone'] = __( 'YouCanPay Standalone', 'woocommerce-gateway-youcanpay' );
				$sections['youcanpay_sepa']       = __( 'YouCanPay SEPA Direct Debit', 'woocommerce-gateway-youcanpay' );
				$sections['youcanpay_multibanco'] = __( 'YouCanPay Multibanco', 'woocommerce-gateway-youcanpay' );

				return $sections;
			}

			/**
			 * Provide default values for missing settings on initial gateway settings save.
			 *
			 * @since 4.5.4
			 * @version 4.5.4
			 *
			 * @param array      $settings New settings to save.
			 * @param array|bool $old_settings Existing settings, if any.
			 * @return array New value but with defaults initially filled in for missing settings.
			 */
			public function gateway_settings_update( $settings, $old_settings ) {
				if ( false === $old_settings ) {
					$gateway      = new WC_Gateway_YouCanPay();
					$fields       = $gateway->get_form_fields();
					$old_settings = array_merge( array_fill_keys( array_keys( $fields ), '' ), wp_list_pluck( $fields, 'default' ) );
					$settings     = array_merge( $old_settings, $settings );
				}

				if ( ! WC_YouCanPay_Feature_Flags::is_upe_preview_enabled() ) {
					return $settings;
				}

				return $this->toggle_upe( $settings, $old_settings );
			}

			/**
			 * Enable or disable UPE.
			 *
			 * When enabling UPE: For each currently enabled YouCanPay LPM, the corresponding UPE method is enabled.
			 *
			 * When disabling UPE: For each currently enabled UPE method, the corresponding LPM is enabled.
			 *
			 * @param array      $settings New settings to save.
			 * @param array|bool $old_settings Existing settings, if any.
			 * @return array New value but with defaults initially filled in for missing settings.
			 */
			protected function toggle_upe( $settings, $old_settings ) {
				if ( false === $old_settings || ! isset( $old_settings[ WC_YouCanPay_Feature_Flags::UPE_CHECKOUT_FEATURE_ATTRIBUTE_NAME ] ) ) {
					$old_settings = [ WC_YouCanPay_Feature_Flags::UPE_CHECKOUT_FEATURE_ATTRIBUTE_NAME => 'no' ];
				}
				if ( ! isset( $settings[ WC_YouCanPay_Feature_Flags::UPE_CHECKOUT_FEATURE_ATTRIBUTE_NAME ] ) || $settings[ WC_YouCanPay_Feature_Flags::UPE_CHECKOUT_FEATURE_ATTRIBUTE_NAME ] === $old_settings[ WC_YouCanPay_Feature_Flags::UPE_CHECKOUT_FEATURE_ATTRIBUTE_NAME ] ) {
					return $settings;
				}

				if ( 'yes' === $settings[ WC_YouCanPay_Feature_Flags::UPE_CHECKOUT_FEATURE_ATTRIBUTE_NAME ] ) {
					return $this->enable_upe( $settings );
				}

				return $this->disable_upe( $settings );
			}

			protected function enable_upe( $settings ) {
				$settings['upe_checkout_experience_accepted_payments'] = [];
				$payment_gateways                                      = WC()->payment_gateways->payment_gateways();
				foreach ( WC_YouCanPay_UPE_Payment_Gateway::UPE_AVAILABLE_METHODS as $method_class ) {
					$lpm_gateway_id = constant( $method_class::LPM_GATEWAY_CLASS . '::ID' );
					if ( isset( $payment_gateways[ $lpm_gateway_id ] ) && 'yes' === $payment_gateways[ $lpm_gateway_id ]->enabled ) {
						// DISABLE LPM
						if ( 'youcanpay' !== $lpm_gateway_id ) {
							/**
							 * TODO: This can be replaced with:
							 *
							 *   $payment_gateways[ $lpm_gateway_id ]->update_option( 'enabled', 'no' );
							 *   $payment_gateways[ $lpm_gateway_id ]->enabled = 'no';
							 *
							 * ...once the minimum WC version is 3.4.0.
							 */
							$payment_gateways[ $lpm_gateway_id ]->settings['enabled'] = 'no';
							update_option(
								$payment_gateways[ $lpm_gateway_id ]->get_option_key(),
								apply_filters( 'woocommerce_settings_api_sanitized_fields_' . $payment_gateways[ $lpm_gateway_id ]::ID, $payment_gateways[ $lpm_gateway_id ]->settings ),
								'yes'
							);
						}
						// ENABLE UPE METHOD
						$settings['upe_checkout_experience_accepted_payments'][] = $method_class::YOUCAN_PAY_ID;
					}
				}
				if ( empty( $settings['upe_checkout_experience_accepted_payments'] ) ) {
					$settings['upe_checkout_experience_accepted_payments'] = [ 'card' ];
				} else {
					// The 'youcanpay' gateway must be enabled for UPE if any LPMs were enabled.
					$settings['enabled'] = 'yes';
				}

				return $settings;
			}

			protected function disable_upe( $settings ) {
				$upe_gateway            = new WC_YouCanPay_UPE_Payment_Gateway();
				$upe_enabled_method_ids = $upe_gateway->get_upe_enabled_payment_method_ids();
				foreach ( WC_YouCanPay_UPE_Payment_Gateway::UPE_AVAILABLE_METHODS as $method_class ) {
					if ( ! defined( "$method_class::LPM_GATEWAY_CLASS" ) || ! in_array( $method_class::YOUCAN_PAY_ID, $upe_enabled_method_ids, true ) ) {
						continue;
					}
					// ENABLE LPM
					$gateway_class = $method_class::LPM_GATEWAY_CLASS;
					$gateway       = new $gateway_class();
					/**
					 * TODO: This can be replaced with:
					 *
					 *   $gateway->update_option( 'enabled', 'yes' );
					 *
					 * ...once the minimum WC version is 3.4.0.
					 */
					$gateway->settings['enabled'] = 'yes';
					update_option( $gateway->get_option_key(), apply_filters( 'woocommerce_settings_api_sanitized_fields_' . $gateway::ID, $gateway->settings ), 'yes' );
				}
				// Disable main YouCanPay/card LPM if 'card' UPE method wasn't enabled.
				if ( ! in_array( 'card', $upe_enabled_method_ids, true ) ) {
					$settings['enabled'] = 'no';
				}
				// DISABLE ALL UPE METHODS
				if ( ! isset( $settings['upe_checkout_experience_accepted_payments'] ) ) {
					$settings['upe_checkout_experience_accepted_payments'] = [];
				}
				return $settings;
			}

			/**
			 * Adds the failed SCA auth email to WooCommerce.
			 *
			 * @param WC_Email[] $email_classes All existing emails.
			 * @return WC_Email[]
			 */
			public function add_emails( $email_classes ) {
				require_once WC_YOUCAN_PAY_PLUGIN_PATH . '/includes/compat/class-wc-youcanpay-email-failed-authentication.php';
				require_once WC_YOUCAN_PAY_PLUGIN_PATH . '/includes/compat/class-wc-youcanpay-email-failed-renewal-authentication.php';
				require_once WC_YOUCAN_PAY_PLUGIN_PATH . '/includes/compat/class-wc-youcanpay-email-failed-preorder-authentication.php';
				require_once WC_YOUCAN_PAY_PLUGIN_PATH . '/includes/compat/class-wc-youcanpay-email-failed-authentication-retry.php';

				// Add all emails, generated by the gateway.
				$email_classes['WC_YouCanPay_Email_Failed_Renewal_Authentication']  = new WC_YouCanPay_Email_Failed_Renewal_Authentication( $email_classes );
				$email_classes['WC_YouCanPay_Email_Failed_Preorder_Authentication'] = new WC_YouCanPay_Email_Failed_Preorder_Authentication( $email_classes );
				$email_classes['WC_YouCanPay_Email_Failed_Authentication_Retry']    = new WC_YouCanPay_Email_Failed_Authentication_Retry( $email_classes );

				return $email_classes;
			}

			/**
			 * Register REST API routes.
			 *
			 * New endpoints/controllers can be added here.
			 */
			public function register_routes() {
				/** API includes */
				require_once WC_YOUCAN_PAY_PLUGIN_PATH . '/includes/abstracts/abstract-wc-youcanpay-connect-rest-controller.php';
				require_once WC_YOUCAN_PAY_PLUGIN_PATH . '/includes/connect/class-wc-youcanpay-connect-rest-oauth-init-controller.php';
				require_once WC_YOUCAN_PAY_PLUGIN_PATH . '/includes/connect/class-wc-youcanpay-connect-rest-oauth-connect-controller.php';

				$oauth_init    = new WC_YouCanPay_Connect_REST_Oauth_Init_Controller( $this->connect, $this->api );
				$oauth_connect = new WC_YouCanPay_Connect_REST_Oauth_Connect_Controller( $this->connect, $this->api );

				$oauth_init->register_routes();
				$oauth_connect->register_routes();

				if ( WC_YouCanPay_Feature_Flags::is_upe_preview_enabled() ) {
					require_once WC_YOUCAN_PAY_PLUGIN_PATH . '/includes/admin/class-wc-youcanpay-rest-base-controller.php';
					require_once WC_YOUCAN_PAY_PLUGIN_PATH . '/includes/admin/class-wc-rest-youcanpay-settings-controller.php';
					require_once WC_YOUCAN_PAY_PLUGIN_PATH . '/includes/admin/class-wc-youcanpay-rest-upe-flag-toggle-controller.php';
					require_once WC_YOUCAN_PAY_PLUGIN_PATH . '/includes/admin/class-wc-rest-youcanpay-account-keys-controller.php';
					require_once WC_YOUCAN_PAY_PLUGIN_PATH . '/includes/admin/class-wc-rest-youcanpay-account-controller.php';

					$upe_flag_toggle_controller = new WC_YouCanPay_REST_UPE_Flag_Toggle_Controller();
					$upe_flag_toggle_controller->register_routes();

					$settings_controller = new WC_REST_YouCanPay_Settings_Controller( $this->get_main_youcanpay_gateway() );
					$settings_controller->register_routes();

					$youcanpay_account_keys_controller = new WC_REST_YouCanPay_Account_Keys_Controller( $this->account );
					$youcanpay_account_keys_controller->register_routes();

					$youcanpay_account_controller = new WC_REST_YouCanPay_Account_Controller( $this->account );
					$youcanpay_account_controller->register_routes();
				}
			}

			/**
			 * Returns the main YouCanPay payment gateway class instance.
			 *
			 * @return WC_YouCanPay_Payment_Gateway
			 */
			public function get_main_youcanpay_gateway() {
				if ( ! is_null( $this->youcanpay_gateway ) ) {
					return $this->youcanpay_gateway;
				}

				if ( WC_YouCanPay_Feature_Flags::is_upe_preview_enabled() && WC_YouCanPay_Feature_Flags::is_upe_checkout_enabled() ) {
					$this->youcanpay_gateway = new WC_YouCanPay_UPE_Payment_Gateway();

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
	load_plugin_textdomain( 'woocommerce-gateway-youcanpay', false, plugin_basename( dirname( __FILE__ ) ) . '/languages' );

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

function wcyoucanpay_deactivated() {
	// admin notes are not supported on older versions of WooCommerce.
	require_once WC_YOUCAN_PAY_PLUGIN_PATH . '/includes/class-wc-youcanpay-upe-compatibility.php';
	if ( WC_YouCanPay_UPE_Compatibility::are_inbox_notes_supported() ) {
		// requirements for the note
		require_once WC_YOUCAN_PAY_PLUGIN_PATH . '/includes/class-wc-youcanpay-feature-flags.php';
		require_once WC_YOUCAN_PAY_PLUGIN_PATH . '/includes/notes/class-wc-youcanpay-upe-compatibility-note.php';
		WC_YouCanPay_UPE_Compatibility_Note::possibly_delete_note();

		require_once WC_YOUCAN_PAY_PLUGIN_PATH . '/includes/notes/class-wc-youcanpay-upe-availability-note.php';
		WC_YouCanPay_UPE_Availability_Note::possibly_delete_note();
	}
}
register_deactivation_hook( __FILE__, 'wcyoucanpay_deactivated' );

// Hook in Blocks integration. This action is called in a callback on plugins loaded, so current YouCanPay plugin class
// implementation is too late.
add_action( 'woocommerce_blocks_loaded', 'woocommerce_gateway_youcanpay_woocommerce_block_support' );

function woocommerce_gateway_youcanpay_woocommerce_block_support() {
	if ( class_exists( 'Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType' ) ) {
		require_once dirname( __FILE__ ) . '/includes/class-wc-youcanpay-blocks-support.php';
		// priority is important here because this ensures this integration is
		// registered before the WooCommerce Blocks built-in YouCanPay registration.
		// Blocks code has a check in place to only register if 'youcanpay' is not
		// already registered.
		add_action(
			'woocommerce_blocks_payment_method_type_registration',
			function( Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry $payment_method_registry ) {
				// I noticed some incompatibility with WP 5.x and WC 5.3 when `_wcyoucanpay_feature_upe_settings` is enabled.
				if ( ! class_exists( 'WC_YouCanPay_Payment_Request' ) ) {
					return;
				}

				$container = Automattic\WooCommerce\Blocks\Package::container();
				// registers as shared instance.
				$container->register(
					WC_YouCanPay_Blocks_Support::class,
					function() {
						if ( class_exists( 'WC_YouCanPay' ) ) {
							return new WC_YouCanPay_Blocks_Support( WC_YouCanPay::get_instance()->payment_request_configuration );
						} else {
							return new WC_YouCanPay_Blocks_Support();
						}
					}
				);
				$payment_method_registry->register(
					$container->get( WC_YouCanPay_Blocks_Support::class )
				);
			},
			5
		);
	}
}
