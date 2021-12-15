<?php

if ( ! class_exists( 'WC_Abstract_Privacy' ) ) {
	return;
}

class WC_YouCanPay_Privacy extends WC_Abstract_Privacy {
	/**
	 * Constructor
	 */
	public function __construct() {
		parent::__construct( __( 'YouCan Pay', 'youcan-pay' ) );

		$this->add_exporter( 'youcan-pay-order-data',
			__( 'YouCan Pay Order Data', 'youcan-pay' ),
			[ $this, 'order_data_exporter' ]
		);
		$this->add_eraser(
			'youcan-pay-order-data',
			__( 'YouCan Pay Data', 'youcan-pay' ),
			[ $this, 'order_data_eraser' ]
		);
	}

	/**
	 * Returns a list of orders that are using one of YouCan Pay's payment methods.
	 *
	 * @param string $email_address
	 * @param int $page
	 *
	 * @return array WP_Post
	 */
	protected function get_youcanpay_orders( $email_address, $page ) {
		// Check if user has an ID in the DB to load stored personal data.
		$user = get_user_by( 'email', $email_address );

		$order_query = [
			'payment_method' => [ 'youcanpay', 'youcanpay_standalone' ],
			'limit'          => 10,
			'page'           => $page,
		];

		if ( $user instanceof WP_User ) {
			$order_query['customer_id'] = $user->ID;
		} else {
			$order_query['billing_email'] = $email_address;
		}

		return wc_get_orders( $order_query );
	}

	/**
	 * Gets the message of the privacy to display.
	 */
	public function get_privacy_message() {
		/* translators: %s URL to docs */
		return wpautop( sprintf( __( 'By using this extension, you may be storing personal data or sharing data with an external service. <a href="%s" target="_blank">Learn more about how this works, including what you may want to include in your privacy policy.</a>',
			'youcan-pay' ),
			'https://pay.youcan.shop/about-us' ) );
	}

	/**
	 * Handle exporting data for Orders.
	 *
	 * @param string $email_address E-mail address to export.
	 * @param int $page Pagination of data.
	 *
	 * @return array
	 */
	public function order_data_exporter( $email_address, $page = 1 ) {
		$data_to_export = [];

		$orders = $this->get_youcanpay_orders( $email_address, (int) $page );

		$done = true;

		if ( 0 < count( $orders ) ) {
			foreach ( $orders as $order ) {
				$data_to_export[] = [
					'group_id'    => 'woocommerce_orders',
					'group_label' => __( 'Orders', 'youcan-pay' ),
					'item_id'     => 'order-' . $order->get_id(),
					'data'        => [
						[
							'name'  => __( 'YouCan Pay Transaction ID', 'youcan-pay' ),
							'value' => get_post_meta( $order->get_id(), '_youcanpay_source_id', true ),
						],
					],
				];
			}

			$done = 10 > count( $orders );
		}

		return [
			'data' => $data_to_export,
			'done' => $done,
		];
	}

	/**
	 * Finds and erases order data by email address.
	 *
	 * @param string $email_address The user email address.
	 * @param int $page Page.
	 *
	 * @return array An array of personal data in name value pairs
	 */
	public function order_data_eraser( $email_address, $page ) {
		$orders = $this->get_youcanpay_orders( $email_address, (int) $page );

		$items_removed  = false;
		$items_retained = false;
		$messages       = [];

		foreach ( $orders as $order ) {
			$order = wc_get_order( $order->get_id() );

			list( $removed, $retained, $msgs ) = $this->maybe_handle_order( $order );
			$items_removed  |= $removed;
			$items_retained |= $retained;
			$messages       = array_merge( $messages, $msgs );
		}

		// Tell the kernel if we still have other orders to work on
		$done = count( $orders ) < 10;

		return [
			'items_removed'  => $items_removed,
			'items_retained' => $items_retained,
			'messages'       => $messages,
			'done'           => $done,
		];
	}

	/**
	 * Handle eraser of data tied to Orders
	 *
	 * @param WC_Order $order
	 *
	 * @return array
	 */
	protected function maybe_handle_order( $order ) {
		$order_id            = $order->get_id();
		$youcanpay_source_id = get_post_meta( $order_id, '_youcanpay_source_id', true );

		if ( ! $this->is_retention_expired( $order->get_date_created()->getTimestamp() ) ) {
			/* translators: %d Order ID */
			return [
				false,
				true,
				[
					sprintf(
						__( 'Order ID %d is less than set retention days. Personal data retained. (YouCan Pay)',
							'youcan-pay' ),
						$order->get_id()
					)
				]
			];
		}

		if ( empty( $youcanpay_source_id ) ) {
			return [ false, false, [] ];
		}

		delete_post_meta( $order_id, '_youcanpay_source_id' );

		return [ true, false, [ __( 'YouCan Pay personal data erased.', 'youcan-pay' ) ] ];
	}

	/**
	 * Checks if create date is passed retention duration.
	 */
	public function is_retention_expired( $created_date ) {
		$retention  = wc_parse_relative_date_option( get_option( 'woocommerce_gateway_youcanpay_retention' ) );
		$is_expired = false;
		$time_span  = time() - strtotime( $created_date );
		if ( empty( $retention ) || empty( $created_date ) ) {
			return false;
		}
		switch ( $retention['unit'] ) {
			case 'days':
				$retention = $retention['number'] * DAY_IN_SECONDS;
				if ( $time_span > $retention ) {
					$is_expired = true;
				}
				break;
			case 'weeks':
				$retention = $retention['number'] * WEEK_IN_SECONDS;
				if ( $time_span > $retention ) {
					$is_expired = true;
				}
				break;
			case 'months':
				$retention = $retention['number'] * MONTH_IN_SECONDS;
				if ( $time_span > $retention ) {
					$is_expired = true;
				}
				break;
			case 'years':
				$retention = $retention['number'] * YEAR_IN_SECONDS;
				if ( $time_span > $retention ) {
					$is_expired = true;
				}
				break;
		}

		return $is_expired;
	}
}

new WC_YouCanPay_Privacy();
