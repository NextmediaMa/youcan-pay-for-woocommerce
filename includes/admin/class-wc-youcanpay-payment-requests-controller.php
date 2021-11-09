<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin page for UPE Customize Express Checkouts.
 *
 * @since 5.4.1
 */
class WC_YouCanPay_Payment_Requests_Controller {
	public function __construct() {
		add_action( 'admin_enqueue_scripts', [ $this, 'admin_scripts' ] );
		add_action( 'wc_youcanpay_gateway_admin_options_wrapper', [ $this, 'admin_options' ] );
	}

	/**
	 * Load admin scripts.
	 */
	public function admin_scripts() {
		// Webpack generates an assets file containing a dependencies array for our built JS file.
		$script_asset_path = WC_YOUCAN_PAY_PLUGIN_PATH . '/build/payment_requests_settings.asset.php';
		$asset_metadata    = file_exists( $script_asset_path )
			? require $script_asset_path
			: [
				'dependencies' => [],
				'version'      => WC_YOUCAN_PAY_VERSION,
			];
		wp_register_script(
			'wc-youcanpay-payment-request-settings',
			plugins_url( 'build/payment_requests_settings.js', WC_YOUCAN_PAY_MAIN_FILE ),
			$asset_metadata['dependencies'],
			$asset_metadata['version'],
			true
		);
		wp_enqueue_script( 'wc-youcanpay-payment-request-settings' );

		wp_register_style(
			'wc-youcanpay-payment-request-settings',
			plugins_url( 'build/payment_requests_settings.css', WC_YOUCAN_PAY_MAIN_FILE ),
			[ 'wc-components' ],
			$asset_metadata['version']
		);
		wp_enqueue_style( 'wc-youcanpay-payment-request-settings' );
	}

	/**
	 * Prints the admin options for the gateway.
	 * Remove this action once we're fully migrated to UPE and move the wrapper in the `admin_options` method of the UPE gateway.
	 */
	public function admin_options() {
		global $hide_save_button;
		$hide_save_button = true;
		echo '<h2>' . __( 'Customize express checkouts', 'woocommerce-gateway-youcanpay' );
		wc_back_link( __( 'Return to YouCan Pay', 'woocommerce-gateway-youcanpay' ), admin_url( 'admin.php?page=wc-settings&tab=checkout&section=youcanpay' ) );
		echo '</h2>';
		echo '<div class="wrap"><div id="wc-youcanpay-payment-request-settings-container"></div></div>';
	}
}
