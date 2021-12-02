<?php
/**
 * YouCan Pay Exception Class
 *
 * Extends Exception to provide additional data
 *
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WC_YouCanPay_Exception extends Exception {

	/**
	 * String sanitized/localized error message.
	 *
	 * @var string
	 */
	protected $localized_message;

	/**
	 * Setup exception
	 *
	 * @param string $error_message Full response
	 * @param string $localized_message user-friendly translated error message
	 */
	public function __construct( $error_message = '', $localized_message = '' ) {
		$this->localized_message = $localized_message;
		parent::__construct( $error_message );
	}

	/**
	 * Returns the localized message.
	 *
	 * @return string
	 */
	public function getLocalizedMessage() {
		return $this->localized_message;
	}
}
