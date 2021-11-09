<?php
/**
 * Class WC_REST_YouCanPay_Account_Keys_Controller
 */

defined( 'ABSPATH' ) || exit;

/**
 * REST controller for saving YouCanPay's test/live account keys.
 *
 * This includes Live Publishable Key, Live Secret Key, Webhook Secret.
 *
 * @since 5.6.0
 */
class WC_REST_YouCanPay_Account_Keys_Controller extends WC_YouCanPay_REST_Base_Controller {
	const YOUCAN_PAY_GATEWAY_SETTINGS_OPTION_NAME = 'woocommerce_youcanpay_settings';

	/**
	 * Endpoint path.
	 *
	 * @var string
	 */
	protected $rest_base = 'wc_youcanpay/account_keys';

	/**
	 * The instance of the YouCanPay account.
	 *
	 * @var WC_YouCanPay_Account
	 */
	private $account;

	/**
	 * Constructor.
	 *
	 * @param WC_YouCanPay_Account $account The instance of the YouCan Pay account.
	 */
	public function __construct( WC_YouCanPay_Account $account ) {
		$this->account = $account;
	}

	/**
	 * Configure REST API routes.
	 */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_account_keys' ],
				'permission_callback' => [ $this, 'check_permission' ],
			]
		);
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			[
				'methods'             => WP_REST_Server::EDITABLE,
				'callback'            => [ $this, 'set_account_keys' ],
				'permission_callback' => [ $this, 'check_permission' ],
				'args'                => [
					'publishable_key'      => [
						'description'       => __( 'Your YouCan Pay API Publishable key, obtained from your YouCan Pay dashboard.', 'woocommerce-gateway-youcanpay' ),
						'type'              => 'string',
						'validate_callback' => [ $this, 'validate_publishable_key' ],
					],
					'secret_key'           => [
						'description'       => __( 'Your YouCan Pay API Secret, obtained from your YouCan Pay dashboard.', 'woocommerce-gateway-youcanpay' ),
						'type'              => 'string',
						'validate_callback' => [ $this, 'validate_secret_key' ],
					],
					'webhook_secret'       => [
						'description'       => __( 'Your YouCan Pay webhook endpoint URL, obtained from your YouCan Pay dashboard.', 'woocommerce-gateway-youcanpay' ),
						'type'              => 'string',
						'validate_callback' => 'rest_validate_request_arg',
					],
					'test_publishable_key' => [
						'description'       => __( 'Your YouCan Pay testing API Publishable key, obtained from your YouCan Pay dashboard.', 'woocommerce-gateway-youcanpay' ),
						'type'              => 'string',
						'validate_callback' => [ $this, 'validate_test_publishable_key' ],
					],
					'test_secret_key'      => [
						'description'       => __( 'Your YouCan Pay testing API Secret, obtained from your YouCan Pay dashboard.', 'woocommerce-gateway-youcanpay' ),
						'type'              => 'string',
						'validate_callback' => [ $this, 'validate_test_secret_key' ],
					],
					'test_webhook_secret'  => [
						'description'       => __( 'Your YouCan Pay testing webhook endpoint URL, obtained from your YouCan Pay dashboard.', 'woocommerce-gateway-youcanpay' ),
						'type'              => 'string',
						'validate_callback' => 'rest_validate_request_arg',
					],
				],
			]
		);
	}

	/**
	 * Retrieve flag status.
	 *
	 * @return WP_REST_Response
	 */
	public function get_account_keys() {
		$allowed_params  = [ 'publishable_key', 'secret_key', 'webhook_secret', 'test_publishable_key', 'test_secret_key', 'test_webhook_secret' ];
		$youcanpay_settings = get_option( self::YOUCAN_PAY_GATEWAY_SETTINGS_OPTION_NAME, [] );
		// Filter only the fields we want to return
		$account_keys = array_intersect_key( $youcanpay_settings, array_flip( $allowed_params ) );

		return new WP_REST_Response( $account_keys );
	}

	/**
	 * Validate youcanpay publishable keys and secrets. Allow empty string to erase key.
	 * Also validates against explicit key prefixes based on live/test environment.
	 *
	 * @param mixed           $value
	 * @param WP_REST_Request $request
	 * @param string          $param
	 * @param array $validate_options
	 * @return true|WP_Error
	 */
	private function validate_youcanpay_param( $param, $request, $key, $validate_options ) {
		if ( empty( $param ) ) {
			return true;
		}
		$result = rest_validate_request_arg( $param, $request, $key );
		if ( ! empty( $result ) && ! preg_match( $validate_options['regex'], $param ) ) {
			return new WP_Error( 400, $validate_options['error_message'] );
		}
		return true;
	}

	public function validate_publishable_key( $param, $request, $key ) {
		return $this->validate_youcanpay_param(
			$param,
			$request,
			$key,
			[
				'regex'         => '/^pub_/',
				'error_message' => __( 'The "Live Publishable Key" should start with "pub", enter the correct key.', 'woocommerce-gateway-youcanpay' ),
			]
		);
	}

	public function validate_secret_key( $param, $request, $key ) {
		return $this->validate_youcanpay_param(
			$param,
			$request,
			$key,
			[
				'regex'         => '/^pri_/',
				'error_message' => __( 'The "Live Secret Key" should start with "pri", enter the correct key.', 'woocommerce-gateway-youcanpay' ),
			]
		);
	}

	public function validate_test_publishable_key( $param, $request, $key ) {
		return $this->validate_youcanpay_param(
			$param,
			$request,
			$key,
			[
				'regex'         => '/^pub_sandbox_/',
				'error_message' => __( 'The "Test Publishable Key" should start with "pub_sandbox", enter the correct key.', 'woocommerce-gateway-youcanpay' ),
			]
		);
	}

	public function validate_test_secret_key( $param, $request, $key ) {
		return $this->validate_youcanpay_param(
			$param,
			$request,
			$key,
			[
				'regex'         => '/^pri_sandbox_/',
				'error_message' => __( 'The "Test Secret Key" should start with "pri_sandbox", enter the correct key.', 'woocommerce-gateway-youcanpay' ),
			]
		);
	}

	/**
	 * Update the data.
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 */
	public function set_account_keys( WP_REST_Request $request ) {
		$publishable_key      = $request->get_param( 'publishable_key' );
		$secret_key           = $request->get_param( 'secret_key' );
		$webhook_secret       = $request->get_param( 'webhook_secret' );
		$test_publishable_key = $request->get_param( 'test_publishable_key' );
		$test_secret_key      = $request->get_param( 'test_secret_key' );
		$test_webhook_secret  = $request->get_param( 'test_webhook_secret' );

		$settings                         = get_option( self::YOUCAN_PAY_GATEWAY_SETTINGS_OPTION_NAME, [] );
		$settings['publishable_key']      = is_null( $publishable_key ) ? $settings['publishable_key'] : $publishable_key;
		$settings['secret_key']           = is_null( $secret_key ) ? $settings['secret_key'] : $secret_key;
		$settings['webhook_secret']       = is_null( $webhook_secret ) ? $settings['webhook_secret'] : $webhook_secret;
		$settings['test_publishable_key'] = is_null( $test_publishable_key ) ? $settings['test_publishable_key'] : $test_publishable_key;
		$settings['test_secret_key']      = is_null( $test_secret_key ) ? $settings['test_secret_key'] : $test_secret_key;
		$settings['test_webhook_secret']  = is_null( $test_webhook_secret ) ? $settings['test_webhook_secret'] : $test_webhook_secret;

		update_option( self::YOUCAN_PAY_GATEWAY_SETTINGS_OPTION_NAME, $settings );
		$this->account->clear_cache();

		return new WP_REST_Response( [], 200 );
	}
}
