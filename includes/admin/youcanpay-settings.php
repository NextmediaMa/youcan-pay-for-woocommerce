<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$youcanpay_settings = apply_filters(
	'wc_youcanpay_settings',
	[
		'enabled'                      => [
			'title'       => __( 'Enable/Disable', 'youcan-pay-for-woocommerce-en' ),
			'label'       => __( 'Enable YouCan Pay', 'youcan-pay-for-woocommerce' ),
			'type'        => 'checkbox',
			'description' => '',
			'default'     => 'no',
		],
		'title'                        => [
			'title'       => __( 'Title', 'youcan-pay-for-woocommerce' ),
			'type'        => 'text',
			'description' => __( 'This controls the title which the user sees during checkout.',
				'youcan-pay-for-woocommerce' ),
			'default'     => __( 'Credit Card (YouCan Pay)', 'youcan-pay-for-woocommerce' ),
			'desc_tip'    => true,
		],
		'description'                  => [
			'title'       => __( 'Description', 'youcan-pay-for-woocommerce' ),
			'type'        => 'text',
			'description' => __( 'This controls the description which the user sees during checkout.',
				'youcan-pay-for-woocommerce' ),
			'default'     => __( 'Pay with your credit card via YouCan Pay.', 'youcan-pay-for-woocommerce' ),
			'desc_tip'    => true,
		],
		'sandbox_mode'                 => [
			'title'       => __( 'Sandbox mode', 'youcan-pay-for-woocommerce' ),
			'label'       => __( 'Enable Sandbox mode', 'youcan-pay-for-woocommerce' ),
			'type'        => 'checkbox',
			'description' => __( 'Place the payment gateway in Sandbox mode using test API keys.',
				'youcan-pay-for-woocommerce' ),
			'default'     => 'yes',
			'desc_tip'    => true,
		],
		'sandbox_public_key'           => [
			'title'       => __( 'Sandbox Public key', 'youcan-pay-for-woocommerce' ),
			'type'        => 'text',
			'description' => __( 'Get your API keys from your YouCan Pay account. Invalid values will be rejected. Only values starting with "pub_sandbox_" will be saved.',
				'youcan-pay-for-woocommerce' ),
			'default'     => '',
			'desc_tip'    => true,
		],
		'sandbox_private_key'          => [
			'title'       => __( 'Sandbox Private key', 'youcan-pay-for-woocommerce' ),
			'type'        => 'password',
			'description' => __( 'Get your API keys from your YouCan Pay account. Invalid values will be rejected. Only values starting with "pri_sandbox_" will be saved.',
				'youcan-pay-for-woocommerce' ),
			'default'     => '',
			'desc_tip'    => true,
		],
		'public_key'                   => [
			'title'       => __( 'Production Public key', 'youcan-pay-for-woocommerce' ),
			'type'        => 'text',
			'description' => __( 'Get your API keys from your YouCan Pay account. Invalid values will be rejected. Only values starting with "pub_" will be saved.',
				'youcan-pay-for-woocommerce' ),
			'default'     => '',
			'desc_tip'    => true,
		],
		'private_key'                  => [
			'title'       => __( 'Production Private key', 'youcan-pay-for-woocommerce' ),
			'type'        => 'password',
			'description' => __( 'Get your API keys from your YouCan Pay account. Invalid values will be rejected. Only values starting with "pri_" will be saved.',
				'youcan-pay-for-woocommerce' ),
			'default'     => '',
			'desc_tip'    => true,
		],
		'payment_request_button_type'  => [
			'title'       => __( 'Payment Request Button Type', 'youcan-pay-for-woocommerce' ),
			'label'       => __( 'Button Type', 'youcan-pay-for-woocommerce' ),
			'type'        => 'select',
			'description' => __( 'Select the button type you would like to show.', 'youcan-pay-for-woocommerce' ),
			'default'     => 'buy',
			'desc_tip'    => true,
			'options'     => [
				'default' => __( 'Default', 'youcan-pay-for-woocommerce' ),
				'buy'     => __( 'Buy', 'youcan-pay-for-woocommerce' ),
				'donate'  => __( 'Donate', 'youcan-pay-for-woocommerce' ),
				'branded' => __( 'Branded', 'youcan-pay-for-woocommerce' ),
				'custom'  => __( 'Custom', 'youcan-pay-for-woocommerce' ),
			],
		],
		'payment_request_button_label' => [
			'title'       => __( 'Payment Request Button Label', 'youcan-pay-for-woocommerce' ),
			'label'       => __( 'Button Label', 'youcan-pay-for-woocommerce' ),
			'type'        => 'text',
			'description' => __( 'Enter the custom text you would like the button to have.',
				'youcan-pay-for-woocommerce' ),
			'default'     => __( 'Buy now', 'youcan-pay-for-woocommerce' ),
			'desc_tip'    => true,
		],
		'logging'                      => [
			'title'       => __( 'Logging', 'youcan-pay-for-woocommerce' ),
			'label'       => __( 'Log debug messages', 'youcan-pay-for-woocommerce' ),
			'type'        => 'checkbox',
			'description' => __( 'Save debug messages to the WooCommerce System Status log.',
				'youcan-pay-for-woocommerce' ),
			'default'     => 'no',
			'desc_tip'    => true,
		],
	]
);

return apply_filters(
	'wc_youcanpay_settings',
	$youcanpay_settings
);
