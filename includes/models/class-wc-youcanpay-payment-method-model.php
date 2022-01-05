<?php

if (!defined('ABSPATH')) {
    exit;
}

class WC_YouCanPay_Payment_Method_Model
{
    /**
     * @var string $id
     */
    protected $id;

    /**
     * @var string $name
     */
    protected $name;

    /**
     * @var array $card
     */
    protected $card;

    public const PAYMENT_METHOD_CREDIT_CART = 'credit_card';
    public const PAYMENT_METHOD_CASH_PLUS = 'cashplus';

    /**
     * Constructor.
     *
     * @param array $properties
     */
    public function __construct(array $properties)
    {
        foreach ($properties as $key => $value) {
            $this->{$key} = $value;
        }
    }

    /**
     * @return string
     */
    public function get_id(): string
    {
        return $this->id;
    }

    /**
     * @return string
     */
    public function get_name(): string
    {
        return $this->name;
    }

    /**
     * @return WC_YouCanPay_Card_Model
     */
    public function get_card(): WC_YouCanPay_Card_Model
    {
        return new WC_YouCanPay_Card_Model($this->card);
    }
}

