<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin page for UPE onboarding wizard.
 *
 * @since 5.4.1
 */
class WC_YouCanPay_Onboarding_Controller {
	const SCREEN_ID = 'admin_page_wc_youcanpay-onboarding_wizard';

	public function __construct() {
		add_action( 'admin_menu', [ $this, 'add_onboarding_wizard' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'admin_scripts' ] );

	}

	/**
	 * Load admin scripts.
	 */
	public function admin_scripts() {
		$current_screen = get_current_screen();
		if ( ! $current_screen ) {
			return;
		}

		if ( empty( $current_screen->id ) || self::SCREEN_ID !== $current_screen->id ) {
			return;
		}

		// Webpack generates an assets file containing a dependencies array for our built JS file.
		$script_path       = 'build/upe_onboarding_wizard.js';
		$script_asset_path = WC_YOUCAN_PAY_PLUGIN_PATH . '/build/upe_onboarding_wizard.asset.php';
		$script_url        = plugins_url( $script_path, WC_YOUCAN_PAY_MAIN_FILE );
		$script_asset      = file_exists( $script_asset_path )
			? require $script_asset_path
			: [
				'dependencies' => [],
				'version'      => WC_YOUCAN_PAY_VERSION,
			];
		$style_path        = 'build/upe_onboarding_wizard.css';
		$style_url         = plugins_url( $style_path, WC_YOUCAN_PAY_MAIN_FILE );

		wp_register_script(
			'wc_youcanpay_onboarding_wizard',
			$script_url,
			$script_asset['dependencies'],
			$script_asset['version'],
			true
		);
		wp_localize_script(
			'wc_youcanpay_onboarding_wizard',
			'wc_youcanpay_onboarding_params',
			[
				'is_upe_checkout_enabled' => WC_YouCanPay_Feature_Flags::is_upe_checkout_enabled(),
			]
		);
		wp_register_style(
			'wc_youcanpay_onboarding_wizard',
			$style_url,
			[ 'wc-components' ],
			$script_asset['version']
		);

		wp_enqueue_script( 'wc_youcanpay_onboarding_wizard' );
		wp_enqueue_style( 'wc_youcanpay_onboarding_wizard' );
	}

	/**
	 * Create an admin page without a side menu: wp-admin/admin.php?page=wc_youcanpay-onboarding_wizard
	 */
	public function add_onboarding_wizard() {
		// This submenu is hidden from the admin menu
		add_submenu_page(
			'admin.php',
			__( 'YouCan Pay - Onboarding Wizard', 'woocommerce-youcan-pay' ),
			__( 'Onboarding Wizard', 'woocommerce-youcan-pay' ),
			'manage_woocommerce',
			'wc_youcanpay-onboarding_wizard',
			[ $this, 'render_onboarding_wizard' ]
		);

		// Connect PHP-powered admin page to wc-admin
		wc_admin_connect_page(
			[
				'id'        => 'wc-youcanpay-onboarding-wizard',
				'screen_id' => self::SCREEN_ID,
				'title'     => __( 'Onboarding Wizard', 'woocommerce-youcan-pay' ),
			]
		);
	}

	/**
	 * Output a container for react app to mount on.
	 */
	public function render_onboarding_wizard() {
		echo '<div class="wrap"><div id="wc-youcanpay-onboarding-wizard-container"></div></div>';
	}
}
