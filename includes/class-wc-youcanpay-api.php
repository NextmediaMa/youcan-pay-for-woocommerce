<?php

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
	 * YouCan Pay API Endpoint
	 */
	const ENDPOINT = 'https://api.youcanpay.com/v1/';
	const YOUCAN_PAY_API_VERSION = '2019-09-09';

	/**
	 * Secret API Key.
	 *
	 * @var string
	 */
	private static $secret_key = '';

	/**
	 * Secret API Key.
	 *
	 * @var string
	 */
	private static $public_key = '';

	/**
	 * Test Mode is enabled.
	 *
	 * @var string
	 */
	private static $is_test_mode = '';

	/**
	 * Set secret API Key.
	 *
	 * @param string $key
	 */
	public static function set_secret_key( $secret_key ) {
		self::$secret_key = $secret_key;
	}

	/**
	 * Set secret API Key.
	 *
	 * @param string $key
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
	public static function get_secret_key() {
		if ( ! self::$secret_key ) {
			$options = get_option( 'woocommerce_youcanpay_settings' );

			if ( isset( $options['testmode'], $options['secret_key'], $options['test_secret_key'] ) ) {
				self::set_secret_key( 'yes' === $options['testmode'] ? $options['test_secret_key'] : $options['secret_key'] );
			}
		}

		return self::$secret_key;
	}

	/**
	 * Get public key.
	 *
	 * @return string
	 */
	public static function get_public_key() {
		if ( ! self::$public_key ) {
			$options = get_option( 'woocommerce_youcanpay_settings' );

			if ( isset( $options['testmode'], $options['publishable_key'], $options['test_publishable_key'] ) ) {
				self::set_public_key( 'yes' === $options['testmode'] ? $options['test_publishable_key'] : $options['publishable_key'] );
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

			if ( isset( $options['testmode'] ) ) {
				self::set_test_mode( $options['testmode'] );
			}
		}

		return self::$is_test_mode;
	}

	/**
	 * Generates the user agent we use to pass to API request so
	 * YouCan Pay can identify our application.
	 *
	 * @since 4.0.0
	 * @version 4.0.0
	 */
	public static function get_user_agent() {
		$app_info = [
			'name'       => 'WooCommerce YouCan Pay Gateway',
			'version'    => WC_YOUCAN_PAY_VERSION,
			'url'        => 'https://woocommerce.com/products/youcanpay/',
			'partner_id' => 'pp_partner_EYuSt9peR0WTMg',
		];

		return [
			'lang'         => 'php',
			'lang_version' => phpversion(),
			'publisher'    => 'woocommerce',
			'uname'        => php_uname(),
			'application'  => $app_info,
		];
	}

	/**
	 * Generates the headers to pass to API request.
	 *
	 * @since 4.0.0
	 * @version 4.0.0
	 */
	public static function get_headers() {
		$user_agent = self::get_user_agent();
		$app_info   = $user_agent['application'];

		$headers = apply_filters(
			'woocommerce_youcanpay_request_headers',
			[
				'Authorization'     => 'Basic ' . base64_encode( self::get_secret_key() . ':' ),
				'YouCanPay-Version' => self::YOUCAN_PAY_API_VERSION,
			]
		);

		// These headers should not be overridden for this gateway.
		$headers['User-Agent']                    = $app_info['name'] . '/' . $app_info['version'] . ' (' . $app_info['url'] . ')';
		$headers['X-YouCanPay-Client-User-Agent'] = wp_json_encode( $user_agent );

		return $headers;
	}

	/**
	 * Send the request to YouCan Pay's API
	 *
	 * @param array $request
	 * @param string $api
	 * @param string $method
	 * @param bool $with_headers To get the response with headers.
	 *
	 * @return stdClass|array
	 * @throws WC_YouCanPay_Exception
	 * @since 3.1.0
	 * @version 4.0.6
	 */
	public static function request( $request, $api = 'charges', $method = 'POST', $with_headers = false ) {
		WC_YouCanPay_Logger::log( "{$api} request: " . print_r( $request, true ) );

		$headers         = self::get_headers();
		$idempotency_key = '';

		if ( 'charges' === $api && 'POST' === $method ) {
			$customer        = ! empty( $request['customer'] ) ? $request['customer'] : '';
			$source          = ! empty( $request['source'] ) ? $request['source'] : $customer;
			$idempotency_key = apply_filters( 'wc_youcanpay_idempotency_key',
				$request['metadata']['order_id'] . '-' . $source,
				$request );

			$headers['Idempotency-Key'] = $idempotency_key;
		}

		$response = wp_safe_remote_post(
			self::ENDPOINT . $api,
			[
				'method'  => $method,
				'headers' => $headers,
				'body'    => apply_filters( 'woocommerce_youcanpay_request_body', $request, $api ),
				'timeout' => 70,
			]
		);

		if ( is_wp_error( $response ) || empty( $response['body'] ) ) {
			WC_YouCanPay_Logger::log(
				'Error Response: ' . print_r( $response, true ) . PHP_EOL . PHP_EOL . 'Failed request: ' . print_r(
					[
						'api'             => $api,
						'request'         => $request,
						'idempotency_key' => $idempotency_key,
					],
					true
				)
			);

			throw new WC_YouCanPay_Exception( print_r( $response, true ),
				__( 'There was a problem connecting to the YouCan Pay API endpoint.',
					'woocommerce-youcan-pay' ) );
		}

		if ( $with_headers ) {
			return [
				'headers' => wp_remote_retrieve_headers( $response ),
				'body'    => json_decode( $response['body'] ),
			];
		}

		return json_decode( $response['body'] );
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
	 * @version 4.0.6
	 * @since 3.1.0
	 */
	public static function requestV2( $order, $post_data, $api = 'charges' ) {
		WC_YouCanPay_Logger::log( "{$api}" );

		if (self::is_test_mode()) {
			\YouCan\Pay\YouCanPay::setIsSandboxMode( true );
		}
		$py = \YouCan\Pay\YouCanPay::instance()->useKeys(
			self::get_secret_key(),
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
			WC_YouCanPay_Logger::log(
				'Error Response: ' . print_r( $token, true ) . PHP_EOL . PHP_EOL . 'Failed request: ' . print_r(
					[
						'api'             => $api,
					],
					true
				)
			);

			throw new WC_YouCanPay_Exception( print_r( $token, true ),
				__( 'There was a problem connecting to the YouCan Pay API endpoint.',
					'woocommerce-youcan-pay' ) );
		}

		$response = [
			'id' => $token->getId(),
			'redirect' => [
				'url' => $token->getPaymentURL()
			]
		];

		return json_decode(json_encode($response), FALSE);
	}

	/**
	 * @return \YouCan\Pay\Models\Transaction|null
	 */
	public static function get_transaction($transaction_id) {
		WC_YouCanPay_Logger::log( "getTransaction" );

		if (self::is_test_mode()) {
			\YouCan\Pay\YouCanPay::setIsSandboxMode( true );
		}
		$py = \YouCan\Pay\YouCanPay::instance()->useKeys(
			self::get_secret_key(),
			self::get_public_key()
		);

		return $py->transaction->get($transaction_id);
	}

	public static function create_token($order_id, $total, $currency) {
		$amount = WC_YouCanPay_Helper::get_youcanpay_amount($total, $currency);

		if (self::is_test_mode()) {
			\YouCan\Pay\YouCanPay::setIsSandboxMode(true);
		}
		$py = \YouCan\Pay\YouCanPay::instance()->useKeys(
			self::get_secret_key(),
			self::get_public_key()
		);

		return $py->token->create(
			$order_id,
			$amount,
			strtoupper($currency),
			self::get_the_user_ip()
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

	/**
	 * Retrieve API endpoint.
	 *
	 * @param string $api
	 *
	 * @version 4.0.0
	 * @since 4.0.0
	 */
	public static function retrieve( $api ) {
		WC_YouCanPay_Logger::log( "{$api}" );

		$response = wp_safe_remote_get(
			self::ENDPOINT . $api,
			[
				'method'  => 'GET',
				'headers' => self::get_headers(),
				'timeout' => 70,
			]
		);

		if ( is_wp_error( $response ) || empty( $response['body'] ) ) {
			WC_YouCanPay_Logger::log( 'Error Response: ' . print_r( $response, true ) );

			return new WP_Error( 'youcanpay_error',
				__( 'There was a problem connecting to the YouCan Pay API endpoint.',
					'woocommerce-youcan-pay' ) );
		}

		return json_decode( $response['body'] );
	}

	/**
	 * Send the request to YouCan Pay's API with level 3 data generated
	 * from the order. If the request fails due to an error related
	 * to level3 data, make the request again without it to allow
	 * the payment to go through.
	 *
	 * @param array $request Array with request parameters.
	 * @param string $api The API path for the request.
	 * @param array $level3_data The level 3 data for this request.
	 * @param WC_Order $order The order associated with the payment.
	 *
	 * @return stdClass|array The response
	 * @version 5.1.0
	 *
	 * @since 4.3.2
	 */
	public static function request_with_level3_data( $request, $api, $level3_data, $order ) {
		// 1. Do not add level3 data if the array is empty.
		// 2. Do not add level3 data if there's a transient indicating that level3 was
		// not accepted by YouCan Pay in the past for this account.
		// 3. Do not try to add level3 data if merchant is not based in the US.
		// https://youcanpay.com/docs/level3#level-iii-usage-requirements
		// (Needs to be authenticated with a level3 gated account to see above docs).
		if (
			empty( $level3_data ) ||
			get_transient( 'wc_youcanpay_level3_not_allowed' ) ||
			'US' !== WC()->countries->get_base_country()
		) {
			return self::request(
				$request,
				$api
			);
		}

		// Add level 3 data to the request.
		$request['level3'] = $level3_data;

		$result = self::request(
			$request,
			$api
		);

		$is_level3_param_not_allowed = (
			isset( $result->error )
			&& isset( $result->error->code )
			&& 'parameter_unknown' === $result->error->code
			&& isset( $result->error->param )
			&& 'level3' === $result->error->param
		);

		$is_level_3data_incorrect = (
			isset( $result->error )
			&& isset( $result->error->type )
			&& 'invalid_request_error' === $result->error->type
		);

		if ( $is_level3_param_not_allowed ) {
			// Set a transient so that future requests do not add level 3 data.
			// Transient is set to expire in 3 months, can be manually removed if needed.
			set_transient( 'wc_youcanpay_level3_not_allowed', true, 3 * MONTH_IN_SECONDS );
		} elseif ( $is_level_3data_incorrect ) {
			// Log the issue so we could debug it.
			WC_YouCanPay_Logger::log(
				'Level3 data sum incorrect: ' . PHP_EOL
				. print_r( $result->error->message, true ) . PHP_EOL
				. print_r( 'Order line items: ', true ) . PHP_EOL
				. print_r( $order->get_items(), true ) . PHP_EOL
				. print_r( 'Order shipping amount: ', true ) . PHP_EOL
				. print_r( $order->get_shipping_total(), true ) . PHP_EOL
				. print_r( 'Order currency: ', true ) . PHP_EOL
				. print_r( $order->get_currency(), true )
			);
		}

		// Make the request again without level 3 data.
		if ( $is_level3_param_not_allowed || $is_level_3data_incorrect ) {
			unset( $request['level3'] );

			return self::request(
				$request,
				$api
			);
		}

		return $result;
	}
}
