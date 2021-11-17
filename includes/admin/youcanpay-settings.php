<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$youcanpay_settings = apply_filters(
	'wc_youcanpay_settings',
	[
		'enabled'                             => [
			'title'       => __( 'Enable/Disable', 'woocommerce-youcan-pay' ),
			'label'       => __( 'Enable YouCan Pay', 'woocommerce-youcan-pay' ),
			'type'        => 'checkbox',
			'description' => '',
			'default'     => 'no',
		],
		'title'                               => [
			'title'       => __( 'Title', 'woocommerce-youcan-pay' ),
			'type'        => 'text',
			'description' => __( 'This controls the title which the user sees during checkout.', 'woocommerce-youcan-pay' ),
			'default'     => __( 'Credit Card (YouCan Pay)', 'woocommerce-youcan-pay' ),
			'desc_tip'    => true,
		],
		'description'                         => [
			'title'       => __( 'Description', 'woocommerce-youcan-pay' ),
			'type'        => 'text',
			'description' => __( 'This controls the description which the user sees during checkout.', 'woocommerce-youcan-pay' ),
			'default'     => __( 'Pay with your credit card via YouCan Pay.', 'woocommerce-youcan-pay' ),
			'desc_tip'    => true,
		],
		'testmode'                            => [
			'title'       => __( 'Test mode', 'woocommerce-youcan-pay' ),
			'label'       => __( 'Enable Test Mode', 'woocommerce-youcan-pay' ),
			'type'        => 'checkbox',
			'description' => __( 'Place the payment gateway in test mode using test API keys.', 'woocommerce-youcan-pay' ),
			'default'     => 'yes',
			'desc_tip'    => true,
		],
		'test_publishable_key'                => [
			'title'       => __( 'Test Publishable Key', 'woocommerce-youcan-pay' ),
			'type'        => 'text',
			'description' => __( 'Get your API keys from your youcanpay account. Invalid values will be rejected. Only values starting with "pub_sandbox_" will be saved.', 'woocommerce-youcan-pay' ),
			'default'     => '',
			'desc_tip'    => true,
		],
		'test_secret_key'                     => [
			'title'       => __( 'Test Secret Key', 'woocommerce-youcan-pay' ),
			'type'        => 'password',
			'description' => __( 'Get your API keys from your youcanpay account. Invalid values will be rejected. Only values starting with "pri_sandbox_" will be saved.', 'woocommerce-youcan-pay' ),
			'default'     => '',
			'desc_tip'    => true,
		],
		'publishable_key'                     => [
			'title'       => __( 'Live Publishable Key', 'woocommerce-youcan-pay' ),
			'type'        => 'text',
			'description' => __( 'Get your API keys from your youcanpay account. Invalid values will be rejected. Only values starting with "pub_" will be saved.', 'woocommerce-youcan-pay' ),
			'default'     => '',
			'desc_tip'    => true,
		],
		'secret_key'                          => [
			'title'       => __( 'Live Secret Key', 'woocommerce-youcan-pay' ),
			'type'        => 'password',
			'description' => __( 'Get your API keys from your YouCan Pay account. Invalid values will be rejected. Only values starting with "pri_" will be saved.', 'woocommerce-youcan-pay' ),
			'default'     => '',
			'desc_tip'    => true,
		],
		'payment_request_button_type'         => [
			'title'       => __( 'Payment Request Button Type', 'woocommerce-youcan-pay' ),
			'label'       => __( 'Button Type', 'woocommerce-youcan-pay' ),
			'type'        => 'select',
			'description' => __( 'Select the button type you would like to show.', 'woocommerce-youcan-pay' ),
			'default'     => 'buy',
			'desc_tip'    => true,
			'options'     => [
				'default' => __( 'Default', 'woocommerce-youcan-pay' ),
				'buy'     => __( 'Buy', 'woocommerce-youcan-pay' ),
				'donate'  => __( 'Donate', 'woocommerce-youcan-pay' ),
				'branded' => __( 'Branded', 'woocommerce-youcan-pay' ),
				'custom'  => __( 'Custom', 'woocommerce-youcan-pay' ),
			],
		],
		'payment_request_button_label'        => [
			'title'       => __( 'Payment Request Button Label', 'woocommerce-youcan-pay' ),
			'label'       => __( 'Button Label', 'woocommerce-youcan-pay' ),
			'type'        => 'text',
			'description' => __( 'Enter the custom text you would like the button to have.', 'woocommerce-youcan-pay' ),
			'default'     => __( 'Buy now', 'woocommerce-youcan-pay' ),
			'desc_tip'    => true,
		],
		'logging'                             => [
			'title'       => __( 'Logging', 'woocommerce-youcan-pay' ),
			'label'       => __( 'Log debug messages', 'woocommerce-youcan-pay' ),
			'type'        => 'checkbox',
			'description' => __( 'Save debug messages to the WooCommerce System Status log.', 'woocommerce-youcan-pay' ),
			'default'     => 'no',
			'desc_tip'    => true,
		],
	]
);

return apply_filters(
	'wc_youcanpay_settings',
	$youcanpay_settings
);
