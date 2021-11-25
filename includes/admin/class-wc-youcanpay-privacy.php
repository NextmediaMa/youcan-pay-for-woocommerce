<?php

if ( ! class_exists( 'WC_Abstract_Privacy' ) ) {
	return;
}

class WC_YouCanPay_Privacy extends WC_Abstract_Privacy {
	/**
	 * Constructor
	 */
	public function __construct() {
		parent::__construct( __( 'YouCan Pay', 'woocommerce-youcan-pay' ) );

		$this->add_exporter(
			'woocommerce-youcan-pay-order-data',
			__( 'WooCommerce YouCan Pay Order Data', 'woocommerce-youcan-pay' ),
			[ $this, 'order_data_exporter' ]
		);
		$this->add_exporter(
			'woocommerce-youcan-pay-customer-data',
			__( 'WooCommerce YouCan Pay Customer Data', 'woocommerce-youcan-pay' ),
			[ $this, 'customer_data_exporter' ]
		);
		$this->add_eraser(
			'woocommerce-youcan-pay-customer-data',
			__( 'WooCommerce YouCan Pay Customer Data', 'woocommerce-youcan-pay' ),
			[ $this, 'customer_data_eraser' ]
		);
		$this->add_eraser(
			'woocommerce-youcan-pay-order-data',
			__( 'WooCommerce YouCan Pay Data', 'woocommerce-youcan-pay' ),
			[ $this, 'order_data_eraser' ]
		);

		add_filter( 'woocommerce_get_settings_account', [ $this, 'account_settings' ] );
	}

	/**
	 * Add retention settings to account tab.
	 *
	 * @param array $settings
	 *
	 * @return array $settings Updated
	 */
	public function account_settings( $settings ) {
		$insert_setting = [
			[
				'title'       => __( 'Retain YouCan Pay Data', 'woocommerce-youcan-pay' ),
				'desc_tip'    => __( 'Retains any YouCan Pay data such as YouCan Pay customer ID, source ID.',
					'woocommerce-youcan-pay' ),
				'id'          => 'woocommerce_gateway_youcanpay_retention',
				'type'        => 'relative_date_selector',
				'placeholder' => __( 'N/A', 'woocommerce-youcan-pay' ),
				'default'     => '',
				'autoload'    => false,
			],
		];

		$index = null;

		foreach ( $settings as $key => $value ) {
			if ( 'sectionend' === $value['type'] && 'personal_data_retention' === $value['id'] ) {
				$index = $key;
				break;
			}
		}

		if ( ! is_null( $index ) ) {
			array_splice( $settings, $index, 0, $insert_setting );
		}

		return $settings;
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
			$order_query['customer_id'] = (int) $user->ID;
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
			'woocommerce-youcan-pay' ),
			'https://docs.woocommerce.com/document/privacy-payments/#woocommerce-youcan-pay' ) );
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
		$done           = false;
		$data_to_export = [];

		$orders = $this->get_youcanpay_orders( $email_address, (int) $page );

		$done = true;

		if ( 0 < count( $orders ) ) {
			foreach ( $orders as $order ) {
				$data_to_export[] = [
					'group_id'    => 'woocommerce_orders',
					'group_label' => __( 'Orders', 'woocommerce-youcan-pay' ),
					'item_id'     => 'order-' . $order->get_id(),
					'data'        => [
						[
							'name'  => __( 'YouCan Pay payment id', 'woocommerce-youcan-pay' ),
							'value' => get_post_meta( $order->get_id(), '_youcanpay_source_id', true ),
						],
						[
							'name'  => __( 'YouCan Pay customer id', 'woocommerce-youcan-pay' ),
							'value' => get_post_meta( $order->get_id(), '_youcanpay_customer_id', true ),
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
	 * Finds and exports customer data by email address.
	 *
	 * @param string $email_address The user email address.
	 * @param int $page Page.
	 *
	 * @return array An array of personal data in name value pairs
	 */
	public function customer_data_exporter( $email_address, $page ) {
		$user           = get_user_by( 'email',
			$email_address ); // Check if user has an ID in the DB to load stored personal data.
		$data_to_export = [];

		if ( $user instanceof WP_User ) {
			$data_to_export[] = [
				'group_id'    => 'woocommerce_customer',
				'group_label' => __( 'Customer Data', 'woocommerce-youcan-pay' ),
				'item_id'     => 'user',
				'data'        => [
					[
						'name'  => __( 'YouCan Pay payment id', 'woocommerce-youcan-pay' ),
						'value' => get_user_option( '_youcanpay_source_id', $user->ID ),
					]
				],
			];
		}

		return [
			'data' => $data_to_export,
			'done' => true,
		];
	}

	/**
	 * Finds and erases customer data by email address.
	 *
	 * @param string $email_address The user email address.
	 * @param int $page Page.
	 *
	 * @return array An array of personal data in name value pairs
	 */
	public function customer_data_eraser( $email_address, $page ) {
		$page                  = (int) $page;
		$user                  = get_user_by( 'email',
			$email_address ); // Check if user has an ID in the DB to load stored personal data.
		$youcanpay_customer_id = '';
		$youcanpay_source_id   = '';

		if ( $user instanceof WP_User ) {
			$youcanpay_customer_id = get_user_option( '_youcanpay_customer_id', $user->ID );
			$youcanpay_source_id   = get_user_option( '_youcanpay_source_id', $user->ID );
		}

		$items_removed = false;
		$messages      = [];

		if ( ! empty( $youcanpay_customer_id ) || ! empty( $youcanpay_source_id ) ) {
			$items_removed = true;
			delete_user_option( $user->ID, '_youcanpay_customer_id' );
			delete_user_option( $user->ID, '_youcanpay_source_id' );
			$messages[] = __( 'YouCan Pay User Data Erased.', 'woocommerce-youcan-pay' );
		}

		return [
			'items_removed'  => $items_removed,
			'items_retained' => false,
			'messages'       => $messages,
			'done'           => true,
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

		foreach ( (array) $orders as $order ) {
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
		$order_id              = $order->get_id();
		$youcanpay_source_id   = get_post_meta( $order_id, '_youcanpay_source_id', true );
		$youcanpay_refund_id   = get_post_meta( $order_id, '_youcanpay_refund_id', true );
		$youcanpay_customer_id = get_post_meta( $order_id, '_youcanpay_customer_id', true );

		if ( ! $this->is_retention_expired( $order->get_date_created()->getTimestamp() ) ) {
			/* translators: %d Order ID */
			return [
				false,
				true,
				[
					sprintf( __( 'Order ID %d is less than set retention days. Personal data retained. (YouCan Pay)',
						'woocommerce-youcan-pay' ),
						$order->get_id() )
				]
			];
		}

		if ( empty( $youcanpay_source_id ) && empty( $youcanpay_refund_id ) && empty( $youcanpay_customer_id ) ) {
			return [ false, false, [] ];
		}

		delete_post_meta( $order_id, '_youcanpay_source_id' );
		delete_post_meta( $order_id, '_youcanpay_refund_id' );
		delete_post_meta( $order_id, '_youcanpay_customer_id' );

		return [ true, false, [ __( 'YouCan Pay personal data erased.', 'woocommerce-youcan-pay' ) ] ];
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
