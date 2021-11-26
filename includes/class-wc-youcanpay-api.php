<?php

use YouCan\Pay\Models\Transaction;
use YouCan\Pay\YouCanPay;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WC_YouCanPay_API class.
 *
 * Communicates with YouCan Pay API.
 */
class WC_YouCanPay_API {

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
	public static function set_private_key( $private_key ) {
		self::$private_key = $private_key;
	}

	/**
	 * Set secret API Key.
	 *
	 * @param string $public_key
	 */
	public static function set_public_key( $public_key ) {
		self::$public_key = $public_key;
	}

	/**
	 * Set secret API Key.
	 *
	 * @param string $test_mode
	 */
	public static function set_test_mode( $test_mode ) {
		self::$is_test_mode = ( 'yes' === $test_mode );
	}

	/**
	 * Get secret key.
	 *
	 * @return string
	 */
	public static function get_private_key() {
		if ( ! self::$private_key ) {
			$options = get_option( 'woocommerce_youcanpay_settings' );

			if ( isset( $options['sandbox_mode'], $options['private_key'], $options['sandbox_private_key'] ) ) {
				self::set_private_key( 'yes' === $options['sandbox_mode'] ? $options['sandbox_private_key'] : $options['private_key'] );
			}
		}

		return self::$private_key;
	}

	/**
	 * Get public key.
	 *
	 * @return string
	 */
	public static function get_public_key() {
		if ( ! self::$public_key ) {
			$options = get_option( 'woocommerce_youcanpay_settings' );

			if ( isset( $options['sandbox_mode'], $options['public_key'], $options['sandbox_public_key'] ) ) {
				self::set_public_key( 'yes' === $options['sandbox_mode'] ? $options['sandbox_public_key'] : $options['public_key'] );
			}
		}

		return self::$public_key;
	}

	/**
	 * Get public key.
	 *
	 * @return string
	 */
	public static function is_test_mode() {
		if ( '' === self::$is_test_mode ) {
			$options = get_option( 'woocommerce_youcanpay_settings' );

			if ( isset( $options['sandbox_mode'] ) ) {
				self::set_test_mode( $options['sandbox_mode'] );
			}
		}

		return self::$is_test_mode;
	}

	/**
	 * Send the request to YouCan Pay's API
	 *
	 * @param WC_Order|WC_Order_Refund $order
	 * @param array $post_data
	 * @param string $api
	 *
	 * @return stdClass|array
	 * @throws WC_YouCanPay_Exception
	 */
	public static function request( $order, $post_data, $api = '' ) {
		if ( self::is_test_mode() ) {
			YouCanPay::setIsSandboxMode( true );
		}
		$py = YouCanPay::instance()->useKeys(
			self::get_private_key(),
			self::get_public_key()
		);

		$token = $py->token->create(
			$order->get_id(),
			(int) $post_data['amount'],
			$post_data['currency'],
			self::get_the_user_ip(),
			$post_data['redirect']['return_url'],
			$post_data['redirect']['return_url']
		);

		if ( is_wp_error( $token ) || empty( $token ) ) {
			WC_YouCanPay_Logger::log( 'Error Response: '
			                          . print_r( $token, true ) . PHP_EOL
			                          . 'Failed request API: ' . PHP_EOL
			                          . print_r( $api, true )
			);

			throw new WC_YouCanPay_Exception( print_r( $token, true ),
				__( 'There was a problem connecting to the YouCan Pay API endpoint.', 'youcan-pay-for-woocommerce' ) );
		}

		$response = [
			'id'       => $token->getId(),
			'redirect' => [
				'url' => $token->getPaymentURL()
			]
		];

		return json_decode( json_encode( $response ) );
	}

	/**
	 * @return Transaction|null
	 */
	public static function get_transaction( $transaction_id ) {
		if ( self::is_test_mode() ) {
			YouCanPay::setIsSandboxMode( true );
		}
		$py = YouCanPay::instance()->useKeys(
			self::get_private_key(),
			self::get_public_key()
		);

		return $py->transaction->get( $transaction_id );
	}

	/**
	 * @param $order WC_Order
	 * @param $total
	 * @param $currency
	 * @param $return_url
	 *
	 * @return \YouCan\Pay\Models\Token
	 */
	public static function create_token( $order, $total, $currency, $return_url ) {
		$amount = WC_YouCanPay_Helper::get_youcanpay_amount( $total, $currency );

		if ( self::is_test_mode() ) {
			YouCanPay::setIsSandboxMode( true );
		}

		$py = YouCanPay::instance()->useKeys(
			self::get_private_key(),
			self::get_public_key()
		);

		return $py->token->create(
			$order->get_id(),
			$amount,
			strtoupper( $currency ),
			self::get_the_user_ip(),
			$return_url,
			$return_url
		);
	}

	public static function get_the_user_ip() {
		if ( ! empty( $_SERVER['HTTP_CLIENT_IP'] ) ) {
			//check ip from share internet
			$ip = $_SERVER['HTTP_CLIENT_IP'];
		} elseif ( ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
			//to check ip is pass from proxy
			$ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
		} else {
			$ip = $_SERVER['REMOTE_ADDR'];
		}

		return $ip;
	}

}
