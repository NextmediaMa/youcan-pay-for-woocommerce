<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WC_YouCanPay_Connect_REST_Oauth_Connect_Controller' ) ) {
	/**
	 * YouCanPay Connect Oauth Connect controller class.
	 */
	class WC_YouCanPay_Connect_REST_Oauth_Connect_Controller extends WC_YouCanPay_Connect_REST_Controller {

		/**
		 * REST base.
		 *
		 * @var string
		 */
		protected $rest_base = 'connect/youcanpay/oauth/connect';

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
		 * OAuth Connection flow.
		 *
		 * @param array $request POST request.
		 *
		 * @return array|WP_Error
		 */
		public function post( $request ) {

			$data     = $request->get_json_params();
			$response = $this->connect->connect_oauth( $data['state'], $data['code'] );

			if ( is_wp_error( $response ) ) {

				WC_YouCanPay_Logger::log( $response, __CLASS__ );

				return new WP_Error(
					$response->get_error_code(),
					$response->get_error_message(),
					[ 'status' => 400 ]
				);
			}

			return [
				'success'    => true,
				'account_id' => $response->accountId, // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
			];
		}
	}
}
