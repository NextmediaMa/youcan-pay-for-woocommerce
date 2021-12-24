<?php

if (!defined('ABSPATH')) {
    exit;
}

class WC_YouCanPay_Card_Model
{
    /**
     * @var string $id
     */
    protected $id;

    /**
     * @var string|null $country_code
     */
    protected $country_code;

    /**
     * @var string|null $brand
     */
    protected $brand;

    /**
     * @var int $last_digits
     */
    protected $last_digits;

    /**
     * @var string $fingerprint
     */
    protected $fingerprint;

    /**
     * @var bool $is_3d_secure
     */
    protected $is_3d_secure;


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
     * @return string|null
     */
    public function get_country_code(): ?string
    {
        return $this->country_code;
    }

    /**
     * @return string|null
     */
    public function get_brand(): ?string
    {
        return $this->brand;
    }

    /**
     * @return int
     */
    public function get_last_digits(): int
    {
        return $this->last_digits;
    }

    /**
     * @return string
     */
    public function get_fingerprint(): string
    {
        return $this->fingerprint;
    }

    /**
     * @return bool
     */
    public function is_3_d_secure(): bool
    {
        return $this->is_3d_secure;
    }
}
