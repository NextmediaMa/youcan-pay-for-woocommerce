<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

return apply_filters(
	'wc_youcanpay_sepa_settings',
	[
		'geo_target'  => [
			'description' => __( 'Customer Geography: France, Germany, Spain, Belgium, Netherlands, Luxembourg, Italy, Portugal, Austria, Ireland', 'woocommerce-gateway-youcanpay' ),
			'type'        => 'title',
		],
		'guide'       => [
			'description' => __( '<a href="https://youcanpay.com/payments/payment-methods-guide#sepa-direct-debit" target="_blank">Payment Method Guide</a>', 'woocommerce-gateway-youcanpay' ),
			'type'        => 'title',
		],
		'activation'  => [
			'description' => __( 'Must be activated from your YouCan Pay Dashboard Settings <a href="https://dashboard.youcanpay.com/account/payments/settings" target="_blank">here</a>', 'woocommerce-gateway-youcanpay' ),
			'type'        => 'title',
		],
		'enabled'     => [
			'title'       => __( 'Enable/Disable', 'woocommerce-gateway-youcanpay' ),
			'label'       => __( 'Enable YouCan Pay SEPA Direct Debit', 'woocommerce-gateway-youcanpay' ),
			'type'        => 'checkbox',
			'description' => '',
			'default'     => 'no',
		],
		'title'       => [
			'title'       => __( 'Title', 'woocommerce-gateway-youcanpay' ),
			'type'        => 'text',
			'description' => __( 'This controls the title which the user sees during checkout.', 'woocommerce-gateway-youcanpay' ),
			'default'     => __( 'SEPA Direct Debit', 'woocommerce-gateway-youcanpay' ),
			'desc_tip'    => true,
		],
		'description' => [
			'title'       => __( 'Description', 'woocommerce-gateway-youcanpay' ),
			'type'        => 'text',
			'description' => __( 'This controls the description which the user sees during checkout.', 'woocommerce-gateway-youcanpay' ),
			'default'     => __( 'Mandate Information.', 'woocommerce-gateway-youcanpay' ),
			'desc_tip'    => true,
		],
		'webhook'     => [
			'title'       => __( 'Webhook Endpoints', 'woocommerce-gateway-youcanpay' ),
			'type'        => 'title',
			/* translators: webhook URL */
			'description' => $this->display_admin_settings_webhook_description(),
		],
	]
);
