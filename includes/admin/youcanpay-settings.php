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
		'title_upe'                           => [
			'title'       => __( 'Title', 'woocommerce-youcan-pay' ),
			'type'        => 'text',
			'description' => __( 'This controls the title which the user sees during checkout when multiple payment methods are enabled.', 'woocommerce-youcan-pay' ),
			'default'     => __( 'Popular payment methods', 'woocommerce-youcan-pay' ),
			'desc_tip'    => true,
		],
		'description'                         => [
			'title'       => __( 'Description', 'woocommerce-youcan-pay' ),
			'type'        => 'text',
			'description' => __( 'This controls the description which the user sees during checkout.', 'woocommerce-youcan-pay' ),
			'default'     => __( 'Pay with your credit card via YouCan Pay.', 'woocommerce-youcan-pay' ),
			'desc_tip'    => true,
		],
		'api_credentials'                     => [
			'title' => __( 'YouCan Pay Account Keys', 'woocommerce-youcan-pay' ),
			'type'  => 'youcanpay_account_keys',
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
		'payment_request_button_theme'        => [
			'title'       => __( 'Payment Request Button Theme', 'woocommerce-youcan-pay' ),
			'label'       => __( 'Button Theme', 'woocommerce-youcan-pay' ),
			'type'        => 'select',
			'description' => __( 'Select the button theme you would like to show.', 'woocommerce-youcan-pay' ),
			'default'     => 'dark',
			'desc_tip'    => true,
			'options'     => [
				'dark'          => __( 'Dark', 'woocommerce-youcan-pay' ),
				'light'         => __( 'Light', 'woocommerce-gateway-youcanpay' ),
				'light-outline' => __( 'Light-Outline', 'woocommerce-gateway-youcanpay' ),
			],
		],
		'payment_request_button_height'       => [
			'title'       => __( 'Payment Request Button Height', 'woocommerce-gateway-youcanpay' ),
			'label'       => __( 'Button Height', 'woocommerce-gateway-youcanpay' ),
			'type'        => 'text',
			'description' => __( 'Enter the height you would like the button to be in pixels. Width will always be 100%.', 'woocommerce-gateway-youcanpay' ),
			'default'     => '44',
			'desc_tip'    => true,
		],
		'payment_request_button_label'        => [
			'title'       => __( 'Payment Request Button Label', 'woocommerce-gateway-youcanpay' ),
			'label'       => __( 'Button Label', 'woocommerce-gateway-youcanpay' ),
			'type'        => 'text',
			'description' => __( 'Enter the custom text you would like the button to have.', 'woocommerce-gateway-youcanpay' ),
			'default'     => __( 'Buy now', 'woocommerce-gateway-youcanpay' ),
			'desc_tip'    => true,
		],
		'payment_request_button_branded_type' => [
			'title'       => __( 'Payment Request Branded Button Label Format', 'woocommerce-gateway-youcanpay' ),
			'label'       => __( 'Branded Button Label Format', 'woocommerce-gateway-youcanpay' ),
			'type'        => 'select',
			'description' => __( 'Select the branded button label format.', 'woocommerce-gateway-youcanpay' ),
			'default'     => 'long',
			'desc_tip'    => true,
			'options'     => [
				'short' => __( 'Logo only', 'woocommerce-gateway-youcanpay' ),
				'long'  => __( 'Text and logo', 'woocommerce-gateway-youcanpay' ),
			],
		],
		'payment_request_button_locations'    => [
			'title'             => __( 'Payment Request Button Locations', 'woocommerce-gateway-youcanpay' ),
			'type'              => 'multiselect',
			'description'       => __( 'Select where you would like Payment Request Buttons to be displayed', 'woocommerce-gateway-youcanpay' ),
			'desc_tip'          => true,
			'class'             => 'wc-enhanced-select',
			'options'           => [
				'product'  => __( 'Product', 'woocommerce-gateway-youcanpay' ),
				'cart'     => __( 'Cart', 'woocommerce-gateway-youcanpay' ),
				'checkout' => __( 'Checkout', 'woocommerce-gateway-youcanpay' ),
			],
			'default'           => [ 'product', 'cart' ],
			'custom_attributes' => [
				'data-placeholder' => __( 'Select pages', 'woocommerce-gateway-youcanpay' ),
			],
		],
		'payment_request_button_size'         => [
			'title'       => __( 'Payment Request Button Size', 'woocommerce-gateway-youcanpay' ),
			'type'        => 'select',
			'description' => __( 'Select the size of the button.', 'woocommerce-gateway-youcanpay' ),
			'default'     => 'default',
			'desc_tip'    => true,
			'options'     => [
				'default' => __( 'Default (40px)', 'woocommerce-gateway-youcanpay' ),
				'medium'  => __( 'Medium (48px)', 'woocommerce-gateway-youcanpay' ),
				'large'   => __( 'Large (56px)', 'woocommerce-gateway-youcanpay' ),
			],
		],
		'logging'                             => [
			'title'       => __( 'Logging', 'woocommerce-gateway-youcanpay' ),
			'label'       => __( 'Log debug messages', 'woocommerce-gateway-youcanpay' ),
			'type'        => 'checkbox',
			'description' => __( 'Save debug messages to the WooCommerce System Status log.', 'woocommerce-gateway-youcanpay' ),
			'default'     => 'no',
			'desc_tip'    => true,
		],
	]
);

return apply_filters(
	'wc_youcanpay_settings',
	$youcanpay_settings
);
