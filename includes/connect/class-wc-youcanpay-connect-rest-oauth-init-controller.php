<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WC_YouCanPay_Connect_REST_Oauth_Init_Controller' ) ) {
	/**
	 * YouCanPay Connect Oauth Init controller class.
	 */
	class WC_YouCanPay_Connect_REST_Oauth_Init_Controller extends WC_YouCanPay_Connect_REST_Controller {

		/**
		 * REST base.
		 *
		 * @var string
		 */
		protected $rest_base = 'connect/youcanpay/oauth/init';

		/**
		 * YouCanPay Connect.
		 *
		 * @var WC_YouCanPay_Connect
		 */
		protected $connect;

		/**
		 * Constructor.
		 *
		 * @param WC_YouCanPay_Connect     $connect youcanpay connect.
		 * @param WC_YouCanPay_Connect_API $api     youcanpay connect api.
		 */
		public function __construct( WC_YouCanPay_Connect $connect, WC_YouCanPay_Connect_API $api ) {

			parent::__construct( $api );

			$this->connect = $connect;
		}

		/**
		 * Initiate OAuth flow.
		 *
		 * @param array $request POST request.
		 *
		 * @return array|WP_Error
		 */
		public function post( $request ) {

			$data     = $request->get_json_params();
			$response = $this->connect->get_oauth_url( isset( $data['returnUrl'] ) ? $data['returnUrl'] : '' );

			if ( is_wp_error( $response ) ) {

				WC_YouCanPay_Logger::log( $response, __CLASS__ );

				return new WP_Error(
					$response->get_error_code(),
					$response->get_error_message(),
					[ 'status' => 400 ]
				);
			}

			return [
				'success'  => true,
				'oauthUrl' => $response,
			];
		}
	}
}
