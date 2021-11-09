<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WC_YouCanPay_Webhook_Handler.
 *
 * Handles webhooks from YouCanPay on sources that are not immediately chargeable.
 *
 * @since 4.0.0
 */
class WC_YouCanPay_Webhook_Handler extends WC_YouCanPay_Payment_Gateway {
	/**
	 * Is test mode active?
	 *
	 * @var bool
	 */
	public $testmode;

	/**
	 * The secret to use when verifying webhooks.
	 *
	 * @var string
	 */
	protected $secret;

	/**
	 * Constructor.
	 *
	 * @since 4.0.0
	 * @version 5.0.0
	 */
	public function __construct() {
		$this->retry_interval = 2;
		$youcanpay_settings      = get_option( 'woocommerce_youcanpay_settings', [] );
		$this->testmode       = ( ! empty( $youcanpay_settings['testmode'] ) && 'yes' === $youcanpay_settings['testmode'] ) ? true : false;
		$secret_key           = ( $this->testmode ? 'test_' : '' ) . 'webhook_secret';
		$this->secret         = ! empty( $youcanpay_settings[ $secret_key ] ) ? $youcanpay_settings[ $secret_key ] : false;

		add_action( 'woocommerce_api_wc_youcanpay', [ $this, 'check_for_webhook' ] );

		// Get/set the time we began monitoring the health of webhooks by fetching it.
		// This should be roughly the same as the activation time of the version of the
		// plugin when this code first appears.
		WC_YouCanPay_Webhook_State::get_monitoring_began_at();
	}

	/**
	 * Check incoming requests for YouCanPay Webhook data and process them.
	 *
	 * @since 4.0.0
	 * @version 5.0.0
	 */
	public function check_for_webhook() {
		if ( ! isset( $_SERVER['REQUEST_METHOD'] )
			//|| ( 'POST' !== $_SERVER['REQUEST_METHOD'] )
			|| ! isset( $_GET['wc-api'] )
			|| ( 'wc_youcanpay' !== $_GET['wc-api'] )
		) {
			return;
		}

		if ((isset( $_GET['wc-original'] )) && ($_GET['wc-original'] == 'wc_youcanpay')) {
			if ( ! isset($_GET['transaction_id']) ) {
				wc_add_notice( __( '#0020: Fatal error, please try again', 'woocommerce-gateway-youcanpay' ), 'error' );
				return wp_redirect(wp_sanitize_redirect(esc_url_raw(get_home_url())));
			}

			$this->autoload_sdk();
			$transaction = WC_YouCanPay_API::get_transaction($_GET['transaction_id']);

			if (! isset($transaction)) {
				wc_add_notice( __( '#0022: Fatal error, please try again', 'woocommerce-gateway-youcanpay' ), 'error' );
				return wp_redirect(wp_sanitize_redirect(esc_url_raw(get_home_url())));
			}

			/** @var WC_Order|WC_Order_Refund $order $order */
			$orderId = $transaction->getOrderId();
			$order = wc_get_order($orderId);

			if (! isset($order)) {
				wc_add_notice( __( '#0021: Fatal error, please contact support', 'woocommerce-gateway-youcanpay' ), 'error' );
				return wp_redirect(wp_sanitize_redirect(esc_url_raw(get_home_url())));
			}

			if ($transaction->getStatus() === 1) {
				$order->payment_complete($transaction->getId());
				return wp_redirect(wp_sanitize_redirect(esc_url_raw($this->get_return_url($order))));
			} else {
				wc_add_notice( __( '#0033: Payment error please try again', 'woocommerce-gateway-youcanpay' ), 'error' );

				$order->set_status('failed');
				$order->save();

				return wp_redirect(wp_sanitize_redirect(esc_url_raw(wc_get_checkout_url())));
			}
		} else {
			$request_body    = file_get_contents( 'php://input' );
			$request_headers = array_change_key_case( $this->get_request_headers(), CASE_UPPER );

			// Validate it to make sure it is legit.
			$validation_result = $this->validate_request( $request_headers, $request_body );
			if ( WC_YouCanPay_Webhook_State::VALIDATION_SUCCEEDED === $validation_result ) {
				$this->process_webhook( $request_body );

				$notification = json_decode( $request_body );
				WC_YouCanPay_Webhook_State::set_last_webhook_success_at( $notification->created );

				status_header( 200 );
				exit;
			} else {
				WC_YouCanPay_Logger::log( 'Incoming webhook failed validation: ' . print_r( $request_body, true ) );
				WC_YouCanPay_Webhook_State::set_last_webhook_failure_at( time() );
				WC_YouCanPay_Webhook_State::set_last_error_reason( $validation_result );

				// A webhook endpoint must return a 2xx HTTP status code to prevent future webhook
				// delivery failures.
				// @see https://youcanpay.com/docs/webhooks/build#acknowledge-events-immediately
				status_header( 204 );
				exit;
			}
		}
	}

	/**
	 * Initialize Gateway Settings Form Fields.
	 */
	public function autoload_sdk() {
		require WC_YOUCAN_PAY_PLUGIN_PATH . '/vendor/autoload.php';
	}

	/**
	 * Verify the incoming webhook notification to make sure it is legit.
	 *
	 * @since 4.0.0
	 * @version 5.0.0
	 * @param array $request_headers The request headers from YouCanPay.
	 * @param array $request_body    The request body from YouCanPay.
	 * @return string The validation result (e.g. self::VALIDATION_SUCCEEDED )
	 */
	public function validate_request( $request_headers, $request_body ) {
		if ( empty( $request_headers ) ) {
			return WC_YouCanPay_Webhook_State::VALIDATION_FAILED_EMPTY_HEADERS;
		}
		if ( empty( $request_body ) ) {
			return WC_YouCanPay_Webhook_State::VALIDATION_FAILED_EMPTY_BODY;
		}

		if ( empty( $this->secret ) ) {
			return $this->validate_request_user_agent( $request_headers );
		}

		// Check for a valid signature.
		$signature_format = '/^t=(?P<timestamp>\d+)(?P<signatures>(,v\d+=[a-z0-9]+){1,2})$/';
		if ( empty( $request_headers['YOUCAN-PAY-SIGNATURE'] ) || ! preg_match( $signature_format, $request_headers['YOUCAN-PAY-SIGNATURE'], $matches ) ) {
			return WC_YouCanPay_Webhook_State::VALIDATION_FAILED_SIGNATURE_INVALID;
		}

		// Verify the timestamp.
		$timestamp = intval( $matches['timestamp'] );
		if ( abs( $timestamp - time() ) > 5 * MINUTE_IN_SECONDS ) {
			return WC_YouCanPay_Webhook_State::VALIDATION_FAILED_TIMESTAMP_MISMATCH;
		}

		// Generate the expected signature.
		$signed_payload     = $timestamp . '.' . $request_body;
		$expected_signature = hash_hmac( 'sha256', $signed_payload, $this->secret );

		// Check if the expected signature is present.
		if ( ! preg_match( '/,v\d+=' . preg_quote( $expected_signature, '/' ) . '/', $matches['signatures'] ) ) {
			return WC_YouCanPay_Webhook_State::VALIDATION_FAILED_SIGNATURE_MISMATCH;
		}

		return WC_YouCanPay_Webhook_State::VALIDATION_SUCCEEDED;
	}

	/**
	 * Verify User Agent of the incoming webhook notification. Used as fallback for the cases when webhook secret is missing.
	 *
	 * @since 5.0.0
	 * @version 5.0.0
	 * @param array $request_headers The request headers from YouCanPay.
	 * @return string The validation result (e.g. self::VALIDATION_SUCCEEDED )
	 */
	private function validate_request_user_agent( $request_headers ) {
		$ua_is_valid = empty( $request_headers['USER-AGENT'] ) || preg_match( '/YouCanPay/', $request_headers['USER-AGENT'] );
		$ua_is_valid = apply_filters( 'wc_youcanpay_webhook_is_user_agent_valid', $ua_is_valid, $request_headers );

		return $ua_is_valid ? WC_YouCanPay_Webhook_State::VALIDATION_SUCCEEDED : WC_YouCanPay_Webhook_State::VALIDATION_FAILED_USER_AGENT_INVALID;
	}

	/**
	 * Gets the incoming request headers. Some servers are not using
	 * Apache and "getallheaders()" will not work so we may need to
	 * build our own headers.
	 *
	 * @since 4.0.0
	 * @version 4.0.0
	 */
	public function get_request_headers() {
		if ( ! function_exists( 'getallheaders' ) ) {
			$headers = [];

			foreach ( $_SERVER as $name => $value ) {
				if ( 'HTTP_' === substr( $name, 0, 5 ) ) {
					$headers[ str_replace( ' ', '-', ucwords( strtolower( str_replace( '_', ' ', substr( $name, 5 ) ) ) ) ) ] = $value;
				}
			}

			return $headers;
		} else {
			return getallheaders();
		}
	}

	/**
	 * Process webhook payments.
	 * This is where we charge the source.
	 *
	 * @since 4.0.0
	 * @version 4.0.0
	 * @param object $notification
	 * @param bool   $retry
	 */
	public function process_webhook_payment( $notification, $retry = true ) {
		// The following 3 payment methods are synchronous so does not need to be handle via webhook.
		if ( 'card' === $notification->data->object->type || 'sepa_debit' === $notification->data->object->type || 'three_d_secure' === $notification->data->object->type ) {
			return;
		}

		$order = WC_YouCanPay_Helper::get_order_by_source_id( $notification->data->object->id );

		if ( ! $order ) {
			WC_YouCanPay_Logger::log( 'Could not find order via source ID: ' . $notification->data->object->id );
			return;
		}

		$order_id = $order->get_id();

		$is_pending_receiver = ( 'receiver' === $notification->data->object->flow );

		try {
			if ( $order->has_status( [ 'processing', 'completed' ] ) ) {
				return;
			}

			if ( $order->has_status( 'on-hold' ) && ! $is_pending_receiver ) {
				return;
			}

			// Result from YouCanPay API request.
			$response = null;

			// This will throw exception if not valid.
			$this->validate_minimum_order_amount( $order );

			WC_YouCanPay_Logger::log( "Info: (Webhook) Begin processing payment for order $order_id for the amount of {$order->get_total()}" );

			// Prep source object.
			$prepared_source = $this->prepare_order_source( $order );

			// Make the request.
			$response = WC_YouCanPay_API::request( $this->generate_payment_request( $order, $prepared_source ), 'charges', 'POST', true );
			$headers  = $response['headers'];
			$response = $response['body'];

			if ( ! empty( $response->error ) ) {
				// Customer param wrong? The user may have been deleted on youcanpay's end. Remove customer_id. Can be retried without.
				if ( $this->is_no_such_customer_error( $response->error ) ) {
					delete_user_option( $order->get_customer_id(), '_youcanpay_customer_id' );
					$order->delete_meta_data( '_youcanpay_customer_id' );
					$order->save();
				}

				if ( $this->is_no_such_token_error( $response->error ) && $prepared_source->token_id ) {
					// Source param wrong? The CARD may have been deleted on youcanpay's end. Remove token and show message.
					$wc_token = WC_Payment_Tokens::get( $prepared_source->token_id );
					$wc_token->delete();
					$localized_message = __( 'This card is no longer available and has been removed.', 'woocommerce-gateway-youcanpay' );
					$order->add_order_note( $localized_message );
					throw new WC_YouCanPay_Exception( print_r( $response, true ), $localized_message );
				}

				// We want to retry.
				if ( $this->is_retryable_error( $response->error ) ) {
					if ( $retry ) {
						// Don't do anymore retries after this.
						if ( 5 <= $this->retry_interval ) {

							return $this->process_webhook_payment( $notification, false );
						}

						sleep( $this->retry_interval );

						$this->retry_interval++;
						return $this->process_webhook_payment( $notification, true );
					} else {
						$localized_message = __( 'Sorry, we are unable to process your payment at this time. Please retry later.', 'woocommerce-gateway-youcanpay' );
						$order->add_order_note( $localized_message );
						throw new WC_YouCanPay_Exception( print_r( $response, true ), $localized_message );
					}
				}

				$localized_messages = WC_YouCanPay_Helper::get_localized_messages();

				if ( 'card_error' === $response->error->type ) {
					$localized_message = isset( $localized_messages[ $response->error->code ] ) ? $localized_messages[ $response->error->code ] : $response->error->message;
				} else {
					$localized_message = isset( $localized_messages[ $response->error->type ] ) ? $localized_messages[ $response->error->type ] : $response->error->message;
				}

				$order->add_order_note( $localized_message );

				throw new WC_YouCanPay_Exception( print_r( $response, true ), $localized_message );
			}

			// To prevent double processing the order on WC side.
			if ( ! $this->is_original_request( $headers ) ) {
				return;
			}

			do_action( 'wc_gateway_youcanpay_process_webhook_payment', $response, $order );

			$this->process_response( $response, $order );

		} catch ( WC_YouCanPay_Exception $e ) {
			WC_YouCanPay_Logger::log( 'Error: ' . $e->getMessage() );

			do_action( 'wc_gateway_youcanpay_process_webhook_payment_error', $order, $notification, $e );

			$statuses = [ 'pending', 'failed' ];

			if ( $order->has_status( $statuses ) ) {
				$this->send_failed_order_email( $order_id );
			}
		}
	}

	/**
	 * Process webhook dispute that is created.
	 * This is triggered when fraud is detected or customer processes chargeback.
	 * We want to put the order into on-hold and add an order note.
	 *
	 * @since 4.0.0
	 * @param object $notification
	 */
	public function process_webhook_dispute( $notification ) {
		$order = WC_YouCanPay_Helper::get_order_by_charge_id( $notification->data->object->charge );

		if ( ! $order ) {
			WC_YouCanPay_Logger::log( 'Could not find order via charge ID: ' . $notification->data->object->charge );
			return;
		}

		$order->update_meta_data( '_youcanpay_status_before_hold', $order->get_status() );

		/* translators: 1) The URL to the order. */
		$message = sprintf( __( 'A dispute was created for this order. Response is needed. Please go to your <a href="%s" title="YouCanPay Dashboard" target="_blank">YouCanPay Dashboard</a> to review this dispute.', 'woocommerce-gateway-youcanpay' ), $this->get_transaction_url( $order ) );
		if ( ! $order->get_meta( '_youcanpay_status_final', false ) ) {
			$order->update_status( 'on-hold', $message );
		} else {
			$order->add_order_note( $message );
		}

		do_action( 'wc_gateway_youcanpay_process_webhook_payment_error', $order, $notification );

		$order_id = $order->get_id();
		$this->send_failed_order_email( $order_id );
	}

	/**
	 * Process webhook dispute that is closed.
	 *
	 * @since 4.4.1
	 * @param object $notification
	 */
	public function process_webhook_dispute_closed( $notification ) {
		$order  = WC_YouCanPay_Helper::get_order_by_charge_id( $notification->data->object->charge );
		$status = $notification->data->object->status;

		if ( ! $order ) {
			WC_YouCanPay_Logger::log( 'Could not find order via charge ID: ' . $notification->data->object->charge );
			return;
		}

		if ( 'lost' === $status ) {
			$message = __( 'The dispute was lost or accepted.', 'woocommerce-gateway-youcanpay' );
		} elseif ( 'won' === $status ) {
			$message = __( 'The dispute was resolved in your favor.', 'woocommerce-gateway-youcanpay' );
		} elseif ( 'warning_closed' === $status ) {
			$message = __( 'The inquiry or retrieval was closed.', 'woocommerce-gateway-youcanpay' );
		} else {
			return;
		}

		if ( apply_filters( 'wc_youcanpay_webhook_dispute_change_order_status', true, $order, $notification ) ) {
			// Mark final so that order status is not overridden by out-of-sequence events.
			$order->update_meta_data( '_youcanpay_status_final', true );

			// Fail order if dispute is lost, or else revert to pre-dispute status.
			$order_status = 'lost' === $status ? 'failed' : $order->get_meta( '_youcanpay_status_before_hold', 'processing' );
			$order->update_status( $order_status, $message );
		} else {
			$order->add_order_note( $message );
		}
	}

	/**
	 * Process webhook capture. This is used for an authorized only
	 * transaction that is later captured via YouCanPay not WC.
	 *
	 * @since 4.0.0
	 * @version 4.0.0
	 * @param object $notification
	 */
	public function process_webhook_capture( $notification ) {
		$order = WC_YouCanPay_Helper::get_order_by_charge_id( $notification->data->object->id );

		if ( ! $order ) {
			WC_YouCanPay_Logger::log( 'Could not find order via charge ID: ' . $notification->data->object->id );
			return;
		}

		if ( 'youcanpay' === $order->get_payment_method() ) {
			$charge   = $order->get_transaction_id();
			$captured = $order->get_meta( '_youcanpay_charge_captured', true );

			if ( $charge && 'no' === $captured ) {
				$order->update_meta_data( '_youcanpay_charge_captured', 'yes' );

				// Store other data such as fees
				$order->set_transaction_id( $notification->data->object->id );

				if ( isset( $notification->data->object->balance_transaction ) ) {
					$this->update_fees( $order, $notification->data->object->balance_transaction );
				}

				// Check and see if capture is partial.
				if ( $this->is_partial_capture( $notification ) ) {
					$partial_amount = $this->get_partial_amount_to_charge( $notification );
					$order->set_total( $partial_amount );
					$this->update_fees( $order, $notification->data->object->refunds->data[0]->balance_transaction );
					/* translators: partial captured amount */
					$order->add_order_note( sprintf( __( 'This charge was partially captured via YouCanPay Dashboard in the amount of: %s', 'woocommerce-gateway-youcanpay' ), $partial_amount ) );
				} else {
					$order->payment_complete( $notification->data->object->id );

					/* translators: transaction id */
					$order->add_order_note( sprintf( __( 'YouCanPay charge complete (Charge ID: %s)', 'woocommerce-gateway-youcanpay' ), $notification->data->object->id ) );
				}

				if ( is_callable( [ $order, 'save' ] ) ) {
					$order->save();
				}
			}
		}
	}

	/**
	 * Process webhook charge succeeded. This is used for payment methods
	 * that takes time to clear which is asynchronous. e.g. SEPA, SOFORT.
	 *
	 * @since 4.0.0
	 * @version 4.0.0
	 * @param object $notification
	 */
	public function process_webhook_charge_succeeded( $notification ) {
		// The following payment methods are synchronous so does not need to be handle via webhook.
		if ( ( isset( $notification->data->object->source->type ) && 'card' === $notification->data->object->source->type ) || ( isset( $notification->data->object->source->type ) && 'three_d_secure' === $notification->data->object->source->type ) ) {
			return;
		}

		$order = WC_YouCanPay_Helper::get_order_by_charge_id( $notification->data->object->id );

		if ( ! $order ) {
			WC_YouCanPay_Logger::log( 'Could not find order via charge ID: ' . $notification->data->object->id );
			return;
		}

		if ( ! $order->has_status( 'on-hold' ) ) {
			return;
		}

		// Store other data such as fees
		$order->set_transaction_id( $notification->data->object->id );

		if ( isset( $notification->data->object->balance_transaction ) ) {
			$this->update_fees( $order, $notification->data->object->balance_transaction );
		}

		$order->payment_complete( $notification->data->object->id );

		/* translators: transaction id */
		$order->add_order_note( sprintf( __( 'YouCanPay charge complete (Charge ID: %s)', 'woocommerce-gateway-youcanpay' ), $notification->data->object->id ) );

		if ( is_callable( [ $order, 'save' ] ) ) {
			$order->save();
		}
	}

	/**
	 * Process webhook charge failed.
	 *
	 * @since 4.0.0
	 * @since 4.1.5 Can handle any fail payments from any methods.
	 * @param object $notification
	 */
	public function process_webhook_charge_failed( $notification ) {
		$order = WC_YouCanPay_Helper::get_order_by_charge_id( $notification->data->object->id );

		if ( ! $order ) {
			WC_YouCanPay_Logger::log( 'Could not find order via charge ID: ' . $notification->data->object->id );
			return;
		}

		// If order status is already in failed status don't continue.
		if ( $order->has_status( 'failed' ) ) {
			return;
		}

		$message = __( 'This payment failed to clear.', 'woocommerce-gateway-youcanpay' );
		if ( ! $order->get_meta( '_youcanpay_status_final', false ) ) {
			$order->update_status( 'failed', $message );
		} else {
			$order->add_order_note( $message );
		}

		do_action( 'wc_gateway_youcanpay_process_webhook_payment_error', $order, $notification );
	}

	/**
	 * Process webhook source canceled. This is used for payment methods
	 * that redirects and awaits payments from customer.
	 *
	 * @since 4.0.0
	 * @since 4.1.15 Add check to make sure order is processed by YouCanPay.
	 * @param object $notification
	 */
	public function process_webhook_source_canceled( $notification ) {
		$order = WC_YouCanPay_Helper::get_order_by_charge_id( $notification->data->object->id );

		// If can't find order by charge ID, try source ID.
		if ( ! $order ) {
			$order = WC_YouCanPay_Helper::get_order_by_source_id( $notification->data->object->id );

			if ( ! $order ) {
				WC_YouCanPay_Logger::log( 'Could not find order via charge/source ID: ' . $notification->data->object->id );
				return;
			}
		}

		// Don't proceed if payment method isn't YouCanPay.
		if ( 'youcanpay' !== $order->get_payment_method() ) {
			WC_YouCanPay_Logger::log( 'Canceled webhook abort: Order was not processed by YouCanPay: ' . $order->get_id() );
			return;
		}

		$message = __( 'This payment was cancelled.', 'woocommerce-gateway-youcanpay' );
		if ( ! $order->has_status( 'cancelled' ) && ! $order->get_meta( '_youcanpay_status_final', false ) ) {
			$order->update_status( 'cancelled', $message );
		} else {
			$order->add_order_note( $message );
		}

		do_action( 'wc_gateway_youcanpay_process_webhook_payment_error', $order, $notification );
	}

	/**
	 * Process webhook refund.
	 *
	 * @since 4.0.0
	 * @version 4.9.0
	 * @param object $notification
	 */
	public function process_webhook_refund( $notification ) {
		$order = WC_YouCanPay_Helper::get_order_by_charge_id( $notification->data->object->id );

		if ( ! $order ) {
			WC_YouCanPay_Logger::log( 'Could not find order via charge ID: ' . $notification->data->object->id );
			return;
		}

		$order_id = $order->get_id();

		if ( 'youcanpay' === $order->get_payment_method() ) {
			$charge     = $order->get_transaction_id();
			$captured   = $order->get_meta( '_youcanpay_charge_captured' );
			$refund_id  = $order->get_meta( '_youcanpay_refund_id' );
			$currency   = $order->get_currency();
			$raw_amount = $notification->data->object->refunds->data[0]->amount;

			if ( ! in_array( strtolower( $currency ), WC_YouCanPay_Helper::no_decimal_currencies(), true ) ) {
				$raw_amount /= 100;
			}

			$amount = wc_price( $raw_amount, [ 'currency' => $currency ] );

			// If charge wasn't captured, skip creating a refund.
			if ( 'yes' !== $captured ) {
				// If the process was initiated from wp-admin,
				// the order was already cancelled, so we don't need a new note.
				if ( 'cancelled' !== $order->get_status() ) {
					/* translators: amount (including currency symbol) */
					$order->add_order_note( sprintf( __( 'Pre-Authorization for %s voided from the YouCanPay Dashboard.', 'woocommerce-gateway-youcanpay' ), $amount ) );
					$order->update_status( 'cancelled' );
				}

				return;
			}

			// If the refund ID matches, don't continue to prevent double refunding.
			if ( $notification->data->object->refunds->data[0]->id === $refund_id ) {
				return;
			}

			if ( $charge ) {
				$reason = __( 'Refunded via YouCanPay Dashboard', 'woocommerce-gateway-youcanpay' );

				// Create the refund.
				$refund = wc_create_refund(
					[
						'order_id' => $order_id,
						'amount'   => $this->get_refund_amount( $notification ),
						'reason'   => $reason,
					]
				);

				if ( is_wp_error( $refund ) ) {
					WC_YouCanPay_Logger::log( $refund->get_error_message() );
				}

				$order->update_meta_data( '_youcanpay_refund_id', $notification->data->object->refunds->data[0]->id );

				if ( isset( $notification->data->object->refunds->data[0]->balance_transaction ) ) {
					$this->update_fees( $order, $notification->data->object->refunds->data[0]->balance_transaction );
				}

				/* translators: 1) amount (including currency symbol) 2) transaction id 3) refund message */
				$order->add_order_note( sprintf( __( 'Refunded %1$s - Refund ID: %2$s - %3$s', 'woocommerce-gateway-youcanpay' ), $amount, $notification->data->object->refunds->data[0]->id, $reason ) );
			}
		}
	}

	/**
	 * Process a refund update.
	 *
	 * @param object $notification
	 */
	public function process_webhook_refund_updated( $notification ) {
		$refund_object = $notification->data->object;
		$order         = WC_YouCanPay_Helper::get_order_by_charge_id( $refund_object->charge );

		if ( ! $order ) {
			WC_YouCanPay_Logger::log( 'Could not find order to update refund via charge ID: ' . $refund_object->charge );
			return;
		}

		$order_id = $order->get_id();

		if ( 'youcanpay' === $order->get_payment_method() ) {
			$charge     = $order->get_transaction_id();
			$refund_id  = $order->get_meta( '_youcanpay_refund_id' );
			$currency   = $order->get_currency();
			$raw_amount = $refund_object->amount;

			if ( ! in_array( strtolower( $currency ), WC_YouCanPay_Helper::no_decimal_currencies(), true ) ) {
				$raw_amount /= 100;
			}

			$amount = wc_price( $raw_amount, [ 'currency' => $currency ] );

			// If the refund IDs do not match stop.
			if ( $refund_object->id !== $refund_id ) {
				return;
			}

			if ( $charge ) {
				$refunds = wc_get_orders(
					[
						'limit'  => 1,
						'parent' => $order_id,
					]
				);

				if ( empty( $refunds ) ) {
					// No existing refunds nothing to update.
					return;
				}

				$refund = $refunds[0];

				if ( in_array( $refund_object->status, [ 'failed', 'canceled' ], true ) ) {
					if ( isset( $refund_object->failure_balance_transaction ) ) {
						$this->update_fees( $order, $refund_object->failure_balance_transaction );
					}
					$refund->delete( true );
					do_action( 'woocommerce_refund_deleted', $refund_id, $order_id );
					if ( 'failed' === $refund_object->status ) {
						/* translators: 1) amount (including currency symbol) 2) transaction id 3) refund failure code */
						$note = sprintf( __( 'Refund failed for %1$s - Refund ID: %2$s - Reason: %3$s', 'woocommerce-gateway-youcanpay' ), $amount, $refund_object->id, $refund_object->failure_reason );
					} else {
						/* translators: 1) amount (including currency symbol) 2) transaction id 3) refund failure code */
						$note = sprintf( __( 'Refund canceled for %1$s - Refund ID: %2$s - Reason: %3$s', 'woocommerce-gateway-youcanpay' ), $amount, $refund_object->id, $refund_object->failure_reason );
					}

					$order->add_order_note( $note );
				}
			}
		}
	}

	/**
	 * Process webhook reviews that are opened. i.e Radar.
	 *
	 * @since 4.0.6
	 * @param object $notification
	 */
	public function process_review_opened( $notification ) {
		if ( isset( $notification->data->object->payment_intent ) ) {
			$order = WC_YouCanPay_Helper::get_order_by_intent_id( $notification->data->object->payment_intent );

			if ( ! $order ) {
				WC_YouCanPay_Logger::log( '[Review Opened] Could not find order via intent ID: ' . $notification->data->object->payment_intent );
				return;
			}
		} else {
			$order = WC_YouCanPay_Helper::get_order_by_charge_id( $notification->data->object->charge );

			if ( ! $order ) {
				WC_YouCanPay_Logger::log( '[Review Opened] Could not find order via charge ID: ' . $notification->data->object->charge );
				return;
			}
		}

		$order->update_meta_data( '_youcanpay_status_before_hold', $order->get_status() );

		/* translators: 1) The URL to the order. 2) The reason type. */
		$message = sprintf( __( 'A review has been opened for this order. Action is needed. Please go to your <a href="%1$s" title="YouCanPay Dashboard" target="_blank">YouCanPay Dashboard</a> to review the issue. Reason: (%2$s)', 'woocommerce-gateway-youcanpay' ), $this->get_transaction_url( $order ), $notification->data->object->reason );

		if ( apply_filters( 'wc_youcanpay_webhook_review_change_order_status', true, $order, $notification ) && ! $order->get_meta( '_youcanpay_status_final', false ) ) {
			$order->update_status( 'on-hold', $message );
		} else {
			$order->add_order_note( $message );
		}
	}

	/**
	 * Process webhook reviews that are closed. i.e Radar.
	 *
	 * @since 4.0.6
	 * @param object $notification
	 */
	public function process_review_closed( $notification ) {
		if ( isset( $notification->data->object->payment_intent ) ) {
			$order = WC_YouCanPay_Helper::get_order_by_intent_id( $notification->data->object->payment_intent );

			if ( ! $order ) {
				WC_YouCanPay_Logger::log( '[Review Closed] Could not find order via intent ID: ' . $notification->data->object->payment_intent );
				return;
			}
		} else {
			$order = WC_YouCanPay_Helper::get_order_by_charge_id( $notification->data->object->charge );

			if ( ! $order ) {
				WC_YouCanPay_Logger::log( '[Review Closed] Could not find order via charge ID: ' . $notification->data->object->charge );
				return;
			}
		}

		/* translators: 1) The reason type. */
		$message = sprintf( __( 'The opened review for this order is now closed. Reason: (%s)', 'woocommerce-gateway-youcanpay' ), $notification->data->object->reason );

		if (
			$order->has_status( 'on-hold' ) &&
			apply_filters( 'wc_youcanpay_webhook_review_change_order_status', true, $order, $notification ) &&
			! $order->get_meta( '_youcanpay_status_final', false )
		) {
			$order->update_status( $order->get_meta( '_youcanpay_status_before_hold', 'processing' ), $message );
		} else {
			$order->add_order_note( $message );
		}
	}

	/**
	 * Checks if capture is partial.
	 *
	 * @since 4.0.0
	 * @version 4.0.0
	 * @param object $notification
	 */
	public function is_partial_capture( $notification ) {
		return 0 < $notification->data->object->amount_refunded;
	}

	/**
	 * Gets the amount refunded.
	 *
	 * @since 4.0.0
	 * @version 4.0.0
	 * @param object $notification
	 */
	public function get_refund_amount( $notification ) {
		if ( $this->is_partial_capture( $notification ) ) {
			$amount = $notification->data->object->refunds->data[0]->amount / 100;

			if ( in_array( strtolower( $notification->data->object->currency ), WC_YouCanPay_Helper::no_decimal_currencies() ) ) {
				$amount = $notification->data->object->refunds->data[0]->amount;
			}

			return $amount;
		}

		return false;
	}

	/**
	 * Gets the amount we actually charge.
	 *
	 * @since 4.0.0
	 * @version 4.0.0
	 * @param object $notification
	 */
	public function get_partial_amount_to_charge( $notification ) {
		if ( $this->is_partial_capture( $notification ) ) {
			$amount = ( $notification->data->object->amount - $notification->data->object->amount_refunded ) / 100;

			if ( in_array( strtolower( $notification->data->object->currency ), WC_YouCanPay_Helper::no_decimal_currencies() ) ) {
				$amount = ( $notification->data->object->amount - $notification->data->object->amount_refunded );
			}

			return $amount;
		}

		return false;
	}

	public function process_payment_intent_success( $notification ) {
		$intent = $notification->data->object;
		$order  = WC_YouCanPay_Helper::get_order_by_intent_id( $intent->id );

		if ( ! $order ) {
			WC_YouCanPay_Logger::log( 'Could not find order via intent ID: ' . $intent->id );
			return;
		}

		if ( ! $order->has_status(
			apply_filters(
				'wc_youcanpay_allowed_payment_processing_statuses',
				[ 'pending', 'failed' ]
			)
		) ) {
			return;
		}

		if ( $this->lock_order_payment( $order, $intent ) ) {
			return;
		}

		$order_id = $order->get_id();
		if ( 'payment_intent.succeeded' === $notification->type || 'payment_intent.amount_capturable_updated' === $notification->type ) {
			$charge = end( $intent->charges->data );
			WC_YouCanPay_Logger::log( "YouCanPay PaymentIntent $intent->id succeeded for order $order_id" );

			do_action( 'wc_gateway_youcanpay_process_payment', $charge, $order );

			// Process valid response.
			$this->process_response( $charge, $order );

		} else {
			$error_message = $intent->last_payment_error ? $intent->last_payment_error->message : '';

			/* translators: 1) The error message that was received from YouCanPay. */
			$message = sprintf( __( 'YouCanPay SCA authentication failed. Reason: %s', 'woocommerce-gateway-youcanpay' ), $error_message );

			if ( ! $order->get_meta( '_youcanpay_status_final', false ) ) {
				$order->update_status( 'failed', $message );
			} else {
				$order->add_order_note( $message );
			}

			do_action( 'wc_gateway_youcanpay_process_webhook_payment_error', $order, $notification );

			$this->send_failed_order_email( $order_id );
		}

		$this->unlock_order_payment( $order );
	}

	public function process_setup_intent( $notification ) {
		$intent = $notification->data->object;
		$order  = WC_YouCanPay_Helper::get_order_by_setup_intent_id( $intent->id );

		if ( ! $order ) {
			WC_YouCanPay_Logger::log( 'Could not find order via setup intent ID: ' . $intent->id );
			return;
		}

		if ( ! $order->has_status(
			apply_filters(
				'wc_gateway_youcanpay_allowed_payment_processing_statuses',
				[ 'pending', 'failed' ]
			)
		) ) {
			return;
		}

		if ( $this->lock_order_payment( $order, $intent ) ) {
			return;
		}

		$order_id = $order->get_id();
		if ( 'setup_intent.succeeded' === $notification->type ) {
			WC_YouCanPay_Logger::log( "YouCanPay SetupIntent $intent->id succeeded for order $order_id" );
			if ( WC_YouCanPay_Helper::is_pre_orders_exists() && WC_Pre_Orders_Order::order_contains_pre_order( $order ) ) {
				WC_Pre_Orders_Order::mark_order_as_pre_ordered( $order );
			} else {
				$order->payment_complete();
			}
		} else {
			$error_message = $intent->last_setup_error ? $intent->last_setup_error->message : '';

			/* translators: 1) The error message that was received from YouCanPay. */
			$message = sprintf( __( 'YouCanPay SCA authentication failed. Reason: %s', 'woocommerce-gateway-youcanpay' ), $error_message );

			if ( ! $order->get_meta( '_youcanpay_status_final', false ) ) {
				$order->update_status( 'failed', $message );
			} else {
				$order->add_order_note( $message );
			}

			$this->send_failed_order_email( $order_id );
		}

		$this->unlock_order_payment( $order );
	}

	/**
	 * Processes the incoming webhook.
	 *
	 * @since 4.0.0
	 * @version 4.0.0
	 * @param string $request_body
	 */
	public function process_webhook( $request_body ) {
		$notification = json_decode( $request_body );

		switch ( $notification->type ) {
			case 'source.chargeable':
				$this->process_webhook_payment( $notification );
				break;

			case 'source.canceled':
				$this->process_webhook_source_canceled( $notification );
				break;

			case 'charge.succeeded':
				$this->process_webhook_charge_succeeded( $notification );
				break;

			case 'charge.failed':
				$this->process_webhook_charge_failed( $notification );
				break;

			case 'charge.captured':
				$this->process_webhook_capture( $notification );
				break;

			case 'charge.dispute.created':
				$this->process_webhook_dispute( $notification );
				break;

			case 'charge.dispute.closed':
				$this->process_webhook_dispute_closed( $notification );
				break;

			case 'charge.refunded':
				$this->process_webhook_refund( $notification );
				break;

			case 'charge.refund.updated':
				$this->process_webhook_refund_updated( $notification );
				break;

			case 'review.opened':
				$this->process_review_opened( $notification );
				break;

			case 'review.closed':
				$this->process_review_closed( $notification );
				break;

			case 'payment_intent.succeeded':
			case 'payment_intent.payment_failed':
			case 'payment_intent.amount_capturable_updated':
				$this->process_payment_intent_success( $notification );
				break;

			case 'setup_intent.succeeded':
			case 'setup_intent.setup_failed':
				$this->process_setup_intent( $notification );

		}
	}
}

new WC_YouCanPay_Webhook_Handler();
