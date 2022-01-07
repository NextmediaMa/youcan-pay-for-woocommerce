<?php

if (!defined('ABSPATH')) {
    exit;
}

$youcanpay_settings = apply_filters(
    'wc_youcanpay_settings',
    [
        'enabled'                      => [
            'title'       => __('Enable/Disable', 'youcan-pay'),
            'label'       => __('Enable YouCan Pay', 'youcan-pay'),
            'type'        => 'checkbox',
            'description' => '',
            'default'     => 'no',
        ],
        'title'                        => [
            'title'       => __('Title', 'youcan-pay'),
            'type'        => 'text',
            'description' => __(
                'This controls the title which the user sees during checkout.',
                'youcan-pay'
            ),
            'default'     => __('YouCan Pay (Gateways)', 'youcan-pay'),
            'desc_tip'    => true,
        ],
        'description'                  => [
            'title'       => __('Description', 'youcan-pay'),
            'type'        => 'text',
            'description' => __(
                'This controls the description which the user sees during checkout.',
                'youcan-pay'
            ),
            'default'     => __('Pay with your credit card or Cash Plus via YouCan Pay.', 'youcan-pay'),
            'desc_tip'    => true,
        ],
        'sandbox_mode'                 => [
            'title'       => __('Sandbox mode', 'youcan-pay'),
            'label'       => __('Enable Sandbox mode', 'youcan-pay'),
            'type'        => 'checkbox',
            'description' => __(
                'Place the payment gateway in Sandbox mode using test API keys.',
                'youcan-pay'
            ),
            'default'     => 'yes',
            'desc_tip'    => true,
        ],
        'sandbox_public_key'           => [
            'title'       => __('Sandbox Public key', 'youcan-pay'),
            'type'        => 'text',
            'description' => __(
                'Get your API keys from your YouCan Pay account. Invalid values will be rejected. Only values starting with "pub_sandbox_" will be saved.',
                'youcan-pay'
            ),
            'default'     => '',
            'desc_tip'    => true,
        ],
        'sandbox_private_key'          => [
            'title'       => __('Sandbox Private key', 'youcan-pay'),
            'type'        => 'password',
            'description' => __(
                'Get your API keys from your YouCan Pay account. Invalid values will be rejected. Only values starting with "pri_sandbox_" will be saved.',
                'youcan-pay'
            ),
            'default'     => '',
            'desc_tip'    => true,
        ],
        'public_key'                   => [
            'title'       => __('Production Public key', 'youcan-pay'),
            'type'        => 'text',
            'description' => __(
                'Get your API keys from your YouCan Pay account. Invalid values will be rejected. Only values starting with "pub_" will be saved.',
                'youcan-pay'
            ),
            'default'     => '',
            'desc_tip'    => true,
        ],
        'private_key'                  => [
            'title'       => __('Production Private key', 'youcan-pay'),
            'type'        => 'password',
            'description' => __(
                'Get your API keys from your YouCan Pay account. Invalid values will be rejected. Only values starting with "pri_" will be saved.',
                'youcan-pay'
            ),
            'default'     => '',
            'desc_tip'    => true,
        ],
        'webhook'                      => [
            'title'       => __('Webhook Endpoints', 'youcan-pay'),
            'type'        => 'title',
            'description' => $this->display_admin_settings_webhook_description(),
        ],
        'payment_request_button_type'  => [
            'title'       => __('Payment Request Button Type', 'youcan-pay'),
            'label'       => __('Button Type', 'youcan-pay'),
            'type'        => 'select',
            'description' => __('Select the button type you would like to show.', 'youcan-pay'),
            'default'     => 'buy',
            'desc_tip'    => true,
            'options'     => [
                'default' => __('Default', 'youcan-pay'),
                'buy'     => __('Buy', 'youcan-pay'),
                'donate'  => __('Donate', 'youcan-pay'),
                'branded' => __('Branded', 'youcan-pay'),
                'custom'  => __('Custom', 'youcan-pay'),
            ],
        ],
        'payment_request_button_label' => [
            'title'       => __('Payment Request Button Label', 'youcan-pay'),
            'label'       => __('Button Label', 'youcan-pay'),
            'type'        => 'text',
            'description' => __(
                'Enter the custom text you would like the button to have.',
                'youcan-pay'
            ),
            'default'     => __('Buy now', 'youcan-pay'),
            'desc_tip'    => true,
        ],
        'logging'                      => [
            'title'       => __('Logging', 'youcan-pay'),
            'label'       => __('Log debug messages', 'youcan-pay'),
            'type'        => 'checkbox',
            'description' => __(
                'Save debug messages to the WooCommerce System Status log.',
                'youcan-pay'
            ),
            'default'     => 'no',
            'desc_tip'    => true,
        ],
    ]
);

return apply_filters(
    'wc_youcanpay_settings',
    $youcanpay_settings
);
