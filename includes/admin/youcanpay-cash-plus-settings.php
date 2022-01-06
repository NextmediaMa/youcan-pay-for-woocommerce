<?php

if (!defined('ABSPATH')) {
    exit;
}

return apply_filters(
    'wc_youcanpay_cash_plus_settings',
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
            'label'       => __('Enable Cash Plus', 'youcan-pay'),
            'type'        => 'checkbox',
            'description' => '',
            'default'     => 'no',
        ],
        'title'       => [
            'title'       => __('Title', 'youcan-pay'),
            'type'        => 'text',
            'description' => __('This controls the title which the user sees during checkout.', 'youcan-pay'),
            'default'     => __('Cash Plus', 'youcan-pay'),
            'desc_tip'    => true,
        ],
        'description' => [
            'title'       => __('Description', 'youcan-pay'),
            'type'        => 'text',
            'description' => __('This controls the description which the user sees during checkout.', 'youcan-pay'),
            'default'     => __('Copy your code and go to nearest cache plus agency.', 'youcan-pay'),
            'desc_tip'    => true,
        ],
    ]
);
