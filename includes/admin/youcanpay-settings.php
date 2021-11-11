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
			'title'       => __( 'Description', 'woocommerce-gateway-youcanpay' ),
			'type'        => 'text',
			'description' => __( 'This controls the description which the user sees during checkout.', 'woocommerce-gateway-youcanpay' ),
			'default'     => __( 'Pay with your credit card via YouCan Pay.', 'woocommerce-gateway-youcanpay' ),
			'desc_tip'    => true,
		],
		'api_credentials'                     => [
			'title' => __( 'YouCan Pay Account Keys', 'woocommerce-gateway-youcanpay' ),
			'type'  => 'youcanpay_account_keys',
		],
		'testmode'                            => [
			'title'       => __( 'Test mode', 'woocommerce-gateway-youcanpay' ),
			'label'       => __( 'Enable Test Mode', 'woocommerce-gateway-youcanpay' ),
			'type'        => 'checkbox',
			'description' => __( 'Place the payment gateway in test mode using test API keys.', 'woocommerce-gateway-youcanpay' ),
			'default'     => 'yes',
			'desc_tip'    => true,
		],
		'test_publishable_key'                => [
			'title'       => __( 'Test Publishable Key', 'woocommerce-gateway-youcanpay' ),
			'type'        => 'text',
			'description' => __( 'Get your API keys from your youcanpay account. Invalid values will be rejected. Only values starting with "pub_sandbox_" will be saved.', 'woocommerce-gateway-youcanpay' ),
			'default'     => '',
			'desc_tip'    => true,
		],
		'test_secret_key'                     => [
			'title'       => __( 'Test Secret Key', 'woocommerce-gateway-youcanpay' ),
			'type'        => 'password',
			'description' => __( 'Get your API keys from your youcanpay account. Invalid values will be rejected. Only values starting with "pri_sandbox_" will be saved.', 'woocommerce-gateway-youcanpay' ),
			'default'     => '',
			'desc_tip'    => true,
		],
		'publishable_key'                     => [
			'title'       => __( 'Live Publishable Key', 'woocommerce-gateway-youcanpay' ),
			'type'        => 'text',
			'description' => __( 'Get your API keys from your youcanpay account. Invalid values will be rejected. Only values starting with "pub_" will be saved.', 'woocommerce-gateway-youcanpay' ),
			'default'     => '',
			'desc_tip'    => true,
		],
		'secret_key'                          => [
			'title'       => __( 'Live Secret Key', 'woocommerce-gateway-youcanpay' ),
			'type'        => 'password',
			'description' => __( 'Get your API keys from your YouCan Pay account. Invalid values will be rejected. Only values starting with "pri_" will be saved.', 'woocommerce-gateway-youcanpay' ),
			'default'     => '',
			'desc_tip'    => true,
		],
		'webhook'                             => [
			'title'       => __( 'Webhook Endpoints', 'woocommerce-gateway-youcanpay' ),
			'type'        => 'title',
			'description' => $this->display_admin_settings_webhook_description(),
		],
		'test_webhook_secret'                 => [
			'title'       => __( 'Test Webhook Secret', 'woocommerce-gateway-youcanpay' ),
			'type'        => 'password',
			'description' => __( 'Get your webhook signing secret from the webhooks section in your youcanpay account.', 'woocommerce-gateway-youcanpay' ),
			'default'     => '',
			'desc_tip'    => true,
		],
		'webhook_secret'                      => [
			'title'       => __( 'Webhook Secret', 'woocommerce-gateway-youcanpay' ),
			'type'        => 'password',
			'description' => __( 'Get your webhook signing secret from the webhooks section in your youcanpay account.', 'woocommerce-gateway-youcanpay' ),
			'default'     => '',
			'desc_tip'    => true,
		],
		'inline_cc_form'                      => [
			'title'       => __( 'Inline Credit Card Form', 'woocommerce-gateway-youcanpay' ),
			'type'        => 'checkbox',
			'description' => __( 'Choose the style you want to show for your credit card form. When unchecked, the credit card form will display separate credit card number field, expiry date field and cvc field.', 'woocommerce-gateway-youcanpay' ),
			'default'     => 'no',
			'desc_tip'    => true,
		],
		'statement_descriptor'                => [
			'title'       => __( 'Statement Descriptor', 'woocommerce-gateway-youcanpay' ),
			'type'        => 'text',
			'description' => __( 'Statement descriptors are limited to 22 characters, cannot use the special characters >, <, ", \, \', *, /, (, ), {, }, and must not consist solely of numbers. This will appear on your customer\'s statement in capital letters.', 'woocommerce-gateway-youcanpay' ),
			'default'     => '',
			'desc_tip'    => true,
		],
		'capture'                             => [
			'title'       => __( 'Capture', 'woocommerce-gateway-youcanpay' ),
			'label'       => __( 'Capture charge immediately', 'woocommerce-gateway-youcanpay' ),
			'type'        => 'checkbox',
			'description' => __( 'Whether or not to immediately capture the charge. When unchecked, the charge issues an authorization and will need to be captured later. Uncaptured charges expire in 7 days.', 'woocommerce-gateway-youcanpay' ),
			'default'     => 'yes',
			'desc_tip'    => true,
		],
		'payment_request'                     => [
			'title'       => __( 'Payment Request Buttons', 'woocommerce-gateway-youcanpay' ),
			'label'       => sprintf(
				/* translators: 1) br tag 2) YouCan Pay anchor tag 3) Apple anchor tag 4) YouCan Pay dashboard opening anchor tag 5) YouCan Pay dashboard closing anchor tag */
				__( 'Enable Payment Request Buttons. (Apple Pay/Google Pay) %1$sBy using Apple Pay, you agree to %2$s and %3$s\'s terms of service. (Apple Pay domain verification is performed automatically in live mode; configuration can be found on the %4$sYouCan Pay dashboard%5$s.)', 'woocommerce-gateway-youcanpay' ),
				'<br />',
				'<a href="https://youcanpay.com/apple-pay/legal" target="_blank">YouCan Pay</a>',
				'<a href="https://developer.apple.com/apple-pay/acceptable-use-guidelines-for-websites/" target="_blank">Apple</a>',
				'<a href="https://dashboard.youcanpay.com/settings/payments/apple_pay" target="_blank">',
				'</a>'
			),
			'type'        => 'checkbox',
			'description' => __( 'If enabled, users will be able to pay using Apple Pay or Chrome Payment Request if supported by the browser.', 'woocommerce-gateway-youcanpay' ),
			'default'     => 'yes',
			'desc_tip'    => true,
		],
		'payment_request_button_type'         => [
			'title'       => __( 'Payment Request Button Type', 'woocommerce-gateway-youcanpay' ),
			'label'       => __( 'Button Type', 'woocommerce-gateway-youcanpay' ),
			'type'        => 'select',
			'description' => __( 'Select the button type you would like to show.', 'woocommerce-gateway-youcanpay' ),
			'default'     => 'buy',
			'desc_tip'    => true,
			'options'     => [
				'default' => __( 'Default', 'woocommerce-gateway-youcanpay' ),
				'buy'     => __( 'Buy', 'woocommerce-gateway-youcanpay' ),
				'donate'  => __( 'Donate', 'woocommerce-gateway-youcanpay' ),
				'branded' => __( 'Branded', 'woocommerce-gateway-youcanpay' ),
				'custom'  => __( 'Custom', 'woocommerce-gateway-youcanpay' ),
			],
		],
		'payment_request_button_theme'        => [
			'title'       => __( 'Payment Request Button Theme', 'woocommerce-gateway-youcanpay' ),
			'label'       => __( 'Button Theme', 'woocommerce-gateway-youcanpay' ),
			'type'        => 'select',
			'description' => __( 'Select the button theme you would like to show.', 'woocommerce-gateway-youcanpay' ),
			'default'     => 'dark',
			'desc_tip'    => true,
			'options'     => [
				'dark'          => __( 'Dark', 'woocommerce-gateway-youcanpay' ),
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
		'saved_cards'                         => [
			'title'       => __( 'Saved Cards', 'woocommerce-gateway-youcanpay' ),
			'label'       => __( 'Enable Payment via Saved Cards', 'woocommerce-gateway-youcanpay' ),
			'type'        => 'checkbox',
			'description' => __( 'If enabled, users will be able to pay with a saved card during checkout. Card details are saved on YouCan Pay servers, not on your store.', 'woocommerce-gateway-youcanpay' ),
			'default'     => 'yes',
			'desc_tip'    => true,
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

if ( WC_YouCanPay_Feature_Flags::is_upe_preview_enabled() ) {
	// in the new settings, "checkout" is going to be enabled by default (if it is a new WC YouCan Pay installation).
	$youcanpay_settings['payment_request_button_locations']['default'][] = 'checkout';

	// no longer needed in the new settings.
	unset( $youcanpay_settings['payment_request_button_branded_type'] );
	unset( $youcanpay_settings['payment_request_button_height'] );
	unset( $youcanpay_settings['payment_request_button_label'] );
	// injecting some of the new options.
	$youcanpay_settings['payment_request_button_type']['options']['default'] = __( 'Only icon', 'woocommerce-gateway-youcanpay' );
	$youcanpay_settings['payment_request_button_type']['options']['book']    = __( 'Book', 'woocommerce-gateway-youcanpay' );
	// no longer valid options.
	unset( $youcanpay_settings['payment_request_button_type']['options']['branded'] );
	unset( $youcanpay_settings['payment_request_button_type']['options']['custom'] );
} else {
	unset( $youcanpay_settings['payment_request_button_size'] );
}

if ( WC_YouCanPay_Feature_Flags::is_upe_preview_enabled() && ! WC_YouCanPay_Helper::is_pre_orders_exists() ) {
	$upe_settings = [
		WC_YouCanPay_Feature_Flags::UPE_CHECKOUT_FEATURE_ATTRIBUTE_NAME => [
			'title'       => __( 'New checkout experience', 'woocommerce-gateway-youcanpay' ),
			'label'       => sprintf(
				/* translators: 1) br tag 2) YouCan Pay anchor tag 3) Apple anchor tag 4) YouCan Pay dashboard opening anchor tag 5) YouCan Pay dashboard closing anchor tag */
				__( 'Try the new payment experience (Early access) %1$sGet early access to a new, smarter payment experience on checkout and let us know what you think by %2$s. We recommend this feature for experienced merchants as the functionality is currently limited. %3$s', 'woocommerce-gateway-youcanpay' ),
				'<br />',
				'<a href="https://woocommerce.survey.fm/woocommerce-youcanpay-upe-opt-out-survey" target="_blank">submitting your feedback</a>',
				'<a href="https://docs.woocommerce.com/document/youcanpay/#new-checkout-experience" target="_blank">Learn more</a>'
			),
			'type'        => 'checkbox',
			'description' => __( 'New checkout experience allows you to manage all payment methods on one screen and display them to customers based on their currency and location.', 'woocommerce-gateway-youcanpay' ),
			'default'     => 'no',
			'desc_tip'    => true,
		],
	];
	if ( WC_YouCanPay_Feature_Flags::is_upe_checkout_enabled() ) {
		// This adds the payment method section
		$upe_settings['upe_checkout_experience_accepted_payments'] = [
			'title'   => __( 'Payments accepted on checkout (Early access)', 'woocommerce-gateway-youcanpay' ),
			'type'    => 'upe_checkout_experience_accepted_payments',
			'default' => [ 'card' ],
		];
	}
	// Insert UPE options below the 'logging' setting.
	$youcanpay_settings = array_merge( $youcanpay_settings, $upe_settings );
}

return apply_filters(
	'wc_youcanpay_settings',
	$youcanpay_settings
);
