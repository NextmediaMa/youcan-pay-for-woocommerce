<?php

if (!defined('ABSPATH')) {
    exit;
}

return apply_filters(
    'wc_youcanpay_standalone_settings',
    [
        'guide'       => [
            'description' => __(
                '<a href="https://pay.youcan.shop/docs" target="_blank">Payment Method Guide</a>',
                'youcan-pay'
            ),
            'type'        => 'title',
        ],
        'activation'  => [
            'description' => __(
                'Must be activated from your YouCan Pay Settings <a href="https://pay.youcan.shop/settings" target="_blank">here</a>',
                'youcan-pay'
            ),
            'type'        => 'title',
        ],
        'enabled'     => [
            'title'       => __('Enable/Disable', 'youcan-pay'),
            'label'       => __('Enable YouCan Pay Standalone', 'youcan-pay'),
            'type'        => 'checkbox',
            'description' => '',
            'default'     => 'no',
        ],
        'title'       => [
            'title'       => __('Title', 'youcan-pay'),
            'type'        => 'text',
            'description' => __('This controls the title which the user sees during checkout.', 'youcan-pay'),
            'default'     => __('YouCan Pay Standalone', 'youcan-pay'),
            'desc_tip'    => true,
        ],
        'description' => [
            'title'       => __('Description', 'youcan-pay'),
            'type'        => 'text',
            'description' => __('This controls the description which the user sees during checkout.', 'youcan-pay'),
            'default'     => __('You will be redirected to YouCan Pay.', 'youcan-pay'),
            'desc_tip'    => true,
        ],
    ]
);
