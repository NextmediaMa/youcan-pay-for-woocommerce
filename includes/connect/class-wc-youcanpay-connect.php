<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WC_YouCanPay_Connect' ) ) {
	/**
	 * YouCan Pay Connect class.
	 */
	class WC_YouCanPay_Connect {

		const SETTINGS_OPTION = 'woocommerce_youcanpay_settings';

		/**
		 * YouCan Pay connect api.
		 *
		 * @var object $api
		 */
		private $api;

		/**
		 * Constructor.
		 *
		 * @param WC_YouCanPay_Connect_API $api youcanpay connect api.
		 */
		public function __construct( WC_YouCanPay_Connect_API $api ) {
			$this->api = $api;

			add_action( 'admin_init', [ $this, 'maybe_handle_redirect' ] );
		}

		/**
		 * Gets the OAuth URL for YouCan Pay onboarding flow
		 *
		 * @param  string $return_url url to return to after oauth flow.
		 *
		 * @return string|WP_Error
		 */
		public function get_oauth_url( $return_url = '' ) {

			if ( empty( $return_url ) ) {
				$return_url = admin_url( 'admin.php?page=wc-settings&tab=checkout&section=youcanpay' );
			}

			if ( substr( $return_url, 0, 8 ) !== 'https://' ) {
				return new WP_Error( 'invalid_url_protocol', __( 'Your site must be served over HTTPS in order to connect your YouCan Pay account automatically.', 'woocommerce-youcan-pay' ) );
			}

			$result = $this->api->get_youcanpay_oauth_init( $return_url );

			if ( is_wp_error( $result ) ) {
				return $result;
			}

			return $result->oauthUrl; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
		}

		/**
		 * Initiate OAuth connection request to Connect Server
		 *
		 * @param  bool $state YouCan Pay onboarding state.
		 * @param  int  $code  OAuth code.
		 *
		 * @return string|WP_Error
		 */
		public function connect_oauth( $state, $code ) {

			$response = $this->api->get_youcanpay_oauth_keys( $code );

			if ( is_wp_error( $response ) ) {
				return $response;
			}

			return $this->save_youcanpay_keys( $response );
		}

		/**
		 * Handle redirect back from oauth-init or credentials reset
		 */
		public function maybe_handle_redirect() {
			if ( ! is_admin() ) {
				return;
			}

			// redirect from oauth-init
			if ( isset( $_GET['wcs_youcanpay_code'], $_GET['wcs_youcanpay_state'] ) ) {

				$response = $this->connect_oauth( wc_clean( wp_unslash( $_GET['wcs_youcanpay_state'] ) ), wc_clean( wp_unslash( $_GET['wcs_youcanpay_code'] ) ) );
				wp_safe_redirect( remove_query_arg( [ 'wcs_youcanpay_state', 'wcs_youcanpay_code' ] ) );
				exit;

				// redirect from credentials reset
			} elseif ( isset( $_GET['reset_youcanpay_api_credentials'], $_GET['_wpnonce'] ) ) {

				if ( ! wp_verify_nonce( wc_clean( wp_unslash( $_GET['_wpnonce'] ) ), 'reset_youcanpay_api_credentials' ) ) {
					die( __( 'You are not authorized to clear YouCan Pay account keys.', 'woocommerce-youcan-pay' ) );
				}

				$this->clear_youcanpay_keys();
				wp_safe_redirect(
					remove_query_arg(
						[
							'_wpnonce',
							'reset_youcanpay_api_credentials',
						]
					)
				);
				exit;
			}

		}

		/**
		 * Saves youcanpay keys after OAuth response
		 *
		 * @param  array $result OAuth response result.
		 *
		 * @return array|WP_Error
		 */
		private function save_youcanpay_keys( $result ) {

			if ( ! isset( $result->publishableKey, $result->secretKey ) ) { // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
				return new WP_Error( 'Invalid credentials received from WooCommerce Connect server' );
			}

			$is_test                                = false !== strpos( $result->publishableKey, '_test_' ); // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
			$prefix                                 = $is_test ? 'test_' : '';
			$default_options                        = $this->get_default_youcanpay_config();
			$options                                = array_merge( $default_options, get_option( self::SETTINGS_OPTION, [] ) );
			$options['enabled']                     = 'yes';
			$options['testmode']                    = $is_test ? 'yes' : 'no';
			$options[ $prefix . 'publishable_key' ] = $result->publishableKey; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
			$options[ $prefix . 'secret_key' ]      = $result->secretKey; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase

			// While we are at it, let's also clear the account_id and
			// test_account_id if present.
			unset( $options['account_id'] );
			unset( $options['test_account_id'] );

			update_option( self::SETTINGS_OPTION, $options );

			return $result;
		}

		/**
		 * Clears keys for test or production (whichever is presently enabled).
		 */
		private function clear_youcanpay_keys() {

			$options = get_option( self::SETTINGS_OPTION, [] );

			if ( 'yes' === $options['testmode'] ) {
				$options['test_publishable_key'] = '';
				$options['test_secret_key']      = '';
				// clear test_account_id if present
				unset( $options['test_account_id'] );
			} else {
				$options['publishable_key'] = '';
				$options['secret_key']      = '';
				// clear account_id if present
				unset( $options['account_id'] );
			}

			update_option( self::SETTINGS_OPTION, $options );

		}

		/**
		 * Gets default YouCan Pay settings
		 */
		private function get_default_youcanpay_config() {

			$result  = [];
			$gateway = new WC_Gateway_YouCanPay();
			foreach ( $gateway->form_fields as $key => $value ) {
				if ( isset( $value['default'] ) ) {
					$result[ $key ] = $value['default'];
				}
			}

			return $result;
		}

		public function is_connected() {

			$options = get_option( self::SETTINGS_OPTION, [] );

			if ( isset( $options['testmode'] ) && 'yes' === $options['testmode'] ) {
				return isset( $options['test_publishable_key'], $options['test_secret_key'] ) && trim( $options['test_publishable_key'] ) && trim( $options['test_secret_key'] );
			} else {
				return isset( $options['publishable_key'], $options['secret_key'] ) && trim( $options['publishable_key'] ) && trim( $options['secret_key'] );
			}
		}
	}
}
