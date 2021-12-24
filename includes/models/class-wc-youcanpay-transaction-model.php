<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WC_YouCanPay_Transaction_Model {

	/**
	 * @var string $id
	 */
	protected $id;

	/**
	 * @var int $status
	 */
	protected $status;

	/**
	 * @var string $order_id
	 */
	protected $order_id;

	/**
	 * @var int $amount
	 */
	protected $amount;

	/**
	 * @var string $currency
	 */
	protected $currency;

	/**
	 * @var string|null $currency
	 */
	protected $base_currency;

	/**
	 * @var string|null $base_amount
	 */
	protected $base_amount;

	/**
	 * @var string $created_at
	 */
	protected $created_at;

	public const FAILED_STATUS = -3;
	public const REFUNDED_STATUS = -2;
	public const CANCELED_STATUS = -1;
	public const PENDING_STATUS = 0;
	public const PAID_STATUS = 1;

	public const LIST_STATUS = [
		WC_YouCanPay_Transaction_Model::REFUNDED_STATUS => 'refunded',
		WC_YouCanPay_Transaction_Model::FAILED_STATUS => 'failed',
		WC_YouCanPay_Transaction_Model::CANCELED_STATUS => 'cancelled',
		WC_YouCanPay_Transaction_Model::PENDING_STATUS => 'pending',
		WC_YouCanPay_Transaction_Model::PAID_STATUS => 'processing',
	];

	/**
	 * Constructor.
	 *
	 * @param array $properties
	 */
	public function __construct( array $properties ) {
		foreach ( $properties as $key => $value ) {
			$this->{$key} = $value;
		}
	}

	/**
	 * @return string
	 */
	public function get_id(): string {
		return $this->id;
	}

	/**
	 * @return int
	 */
	public function get_status(): int {
		return $this->status;
	}

	/**
	 * @return string|null
	 */
	public function get_status_string(): ?string {
		return self::LIST_STATUS[$this->status] ?? null;
	}

	/**
	 * @return string
	 */
	public function get_order_id(): string {
		return $this->order_id;
	}

	/**
	 * @return int
	 */
	public function get_amount(): int {
		return $this->amount;
	}

	/**
	 * @return string
	 */
	public function get_currency(): string {
		return $this->currency;
	}

	/**
	 * @return string|null
	 */
	public function get_base_currency(): ?string {
		return $this->base_currency;
	}

	/**
	 * @return string|null
	 */
	public function get_base_amount(): ?string {
		return $this->base_amount;
	}

	/**
	 * @return string
	 */
	public function get_created_at(): string {
		return $this->created_at;
	}
}

