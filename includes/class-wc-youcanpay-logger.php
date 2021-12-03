<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * Log all things!
 */
class WC_YouCanPay_Logger {

	public static $logger;
	const WC_LOG_FILENAME = 'youcan-pay';

	/**
	 * @param string $message
	 * @param array $context
	 */
	public static function log( $message, $context = null ) {
		self::construct( 'log', $message, $context );
	}

	/**
	 * @param string $message
	 * @param array $context
	 */
	public static function debug( $message, $context = null ) {
		self::construct( 'debug', $message, $context );
	}

	/**
	 * @param string $message
	 * @param array $context
	 */
	public static function alert( $message, $context = null ) {
		self::construct( 'alert', $message, $context );
	}

	/**
	 * @param string $message
	 * @param array $context
	 */
	public static function info( $message, $context = null ) {
		self::construct( 'info', $message, $context );
	}

	/**
	 * @param $type
	 * @param $message
	 * @param null $context
	 */
	private static function construct( $type, $message, $context = null ) {
		if ( ! class_exists( 'WC_Logger' ) ) {
			return;
		}

		if ( apply_filters( 'wc_youcanpay_logging', true, $message ) ) {
			if ( empty( self::$logger ) ) {
				self::$logger = wc_get_logger();
			}

			$settings = get_option( 'woocommerce_youcanpay_settings' );

			if ( empty( $settings ) || isset( $settings['logging'] ) && 'yes' !== $settings['logging'] ) {
				return;
			}

			if ( is_array( $context ) ) {
				$context = array_merge( [ 'message' => $message ], $context );

				$array = array();
				foreach ( $context as $key => $item ) {
					$array[] = "{$key}: {$item}";
				}
				$message = implode( PHP_EOL, $array );
			}

			$log_entry = PHP_EOL . '====YouCan Pay Version: ' . WC_YOUCAN_PAY_VERSION . '====' . PHP_EOL;
			$log_entry .= '====Start Log====' . PHP_EOL . $message . PHP_EOL . '====End Log====' . PHP_EOL . PHP_EOL;

			self::$logger->{$type}( $log_entry, [ 'source' => self::WC_LOG_FILENAME ] );
		}
	}
}
