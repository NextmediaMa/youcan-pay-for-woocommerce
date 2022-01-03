<?php

if (!defined('ABSPATH')) {
    exit;
}

class WC_YouCanPay_Token_Model
{
    /**
     * @var string $id
     */
    protected $id;

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
}

