<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WC_YouCanPay_Account class.
 *
 * Communicates with YouCanPay API.
 */
class WC_YouCanPay_Account {

	const LIVE_ACCOUNT_OPTION = 'wcyoucanpay_account_data_live';
	const TEST_ACCOUNT_OPTION = 'wcyoucanpay_account_data_test';

	/**
	 * The YouCanPay connect instance.
	 *
	 * @var WC_YouCanPay_Connect
	 */
	private $connect;

	/**
	 * The YouCanPay API class to access the static method.
	 *
	 * @var WC_YouCanPay_API
	 */
	private $youcanpay_api;

	/**
	 * Constructor
	 *
	 * @param WC_YouCanPay_Connect $connect YouCanPay connect
	 * @param $youcanpay_api WC_YouCanPay_API class
	 */
	public function __construct( WC_YouCanPay_Connect $connect, $youcanpay_api ) {
		$this->connect    = $connect;
		$this->youcanpay_api = $youcanpay_api;
	}

	/**
	 * Gets and caches the data for the account connected to this site.
	 *
	 * @return array Account data or empty if failed to retrieve account data.
	 */
	public function get_cached_account_data() {
		if ( ! $this->connect->is_connected() ) {
			return [];
		}

		$account = $this->read_account_from_cache();

		if ( ! empty( $account ) ) {
			return $account;
		}

		return $this->cache_account();
	}

	/**
	 * Read the account from the WP option we cache it in.
	 *
	 * @return array empty when no data found in transient, otherwise returns cached data
	 */
	private function read_account_from_cache() {
		$account_cache = json_decode( wp_json_encode( get_transient( $this->get_transient_key() ) ), true );

		return false === $account_cache ? [] : $account_cache;
	}

	/**
	 * Caches account data for a period of time.
	 */
	private function cache_account() {
		$expiration = 2 * HOUR_IN_SECONDS;

		// need call_user_func() as (  $this->youcanpay_api )::retrieve this syntax is not supported in php < 5.2
		$account = call_user_func( [ $this->youcanpay_api, 'retrieve' ], 'account' );

		if ( is_wp_error( $account ) || isset( $account->error->message ) ) {
			return [];
		}

		// Add the account data and mode to the array we're caching.
		$account_cache = $account;

		// Create or update the account option cache.
		set_transient( $this->get_transient_key(), $account_cache, $expiration );

		return json_decode( wp_json_encode( $account ), true );
	}

	/**
	 * Checks YouCanPay connection mode if it is test mode or live mode
	 *
	 * @return string Transient key of test mode when testmode is enabled, otherwise returns the key of live mode.
	 */
	private function get_transient_key() {
		$settings_options = get_option( 'woocommerce_youcanpay_settings', [] );
		$key              = isset( $settings_options['testmode'] ) && 'yes' === $settings_options['testmode'] ? self::TEST_ACCOUNT_OPTION : self::LIVE_ACCOUNT_OPTION;

		return $key;
	}

	/**
	 * Wipes the account data option.
	 */
	public function clear_cache() {
		delete_transient( self::LIVE_ACCOUNT_OPTION );
		delete_transient( self::TEST_ACCOUNT_OPTION );
	}
}
