<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Provides static methods as helpers.
 */
class WC_YouCanPay_Helper {
	const META_NAME_YOUCAN_PAY_CURRENCY = '_youcanpay_currency';

	/**
	 * Gets the YouCan Pay currency for order.
	 *
	 * @param object $order
	 * @return string $currency
	 */
	public static function get_youcanpay_currency( $order = null ) {
		if ( is_null( $order ) ) {
			return false;
		}

		return $order->get_meta( self::META_NAME_YOUCAN_PAY_CURRENCY, true );
	}

	/**
	 * Get YouCan Pay amount to pay
	 *
	 * @param float  $total Amount due.
	 * @param string $currency Accepted currency.
	 *
	 * @return float|int
	 */
	public static function get_youcanpay_amount( $total, $currency = '' ) {
		if ( ! $currency ) {
			$currency = get_woocommerce_currency();
		}

		if ( in_array( strtolower( $currency ), self::no_decimal_currencies() ) ) {
			return absint( $total );
		} else {
			return absint( wc_format_decimal( ( (float) $total * 100 ), wc_get_price_decimals() ) ); // In cents.
		}
	}

	/**
	 * Get Total Payed to YouCan Pay
	 *
	 * @param float  $amount Amount.
	 * @param string $currency Accepted currency.
	 *
	 * @return float|int
	 */
	public static function get_youcanpay_order_total( $amount, $currency = '' ) {
		if ( ! $currency ) {
			$currency = get_woocommerce_currency();
		}

		if ( in_array( strtolower( $currency ), self::no_decimal_currencies() ) ) {
			return absint( $amount );
		} else {
			return abs($amount / 100);
		}
	}

	/**
	 * Localize YouCan Pay messages based on code
	 *
	 * @return array
	 */
	public static function get_localized_messages() {
		return apply_filters(
			'wc_youcanpay_localized_messages',
			[
				'processing_error'         => __( 'An error occurred while processing the card.', 'youcan-pay' ),
				'email_invalid'            => __( 'Invalid email address, please correct and try again.', 'youcan-pay' ),
				'invalid_request_error'    => is_add_payment_method_page()
					? __( 'Unable to save this payment method, please try again or use alternative method.', 'youcan-pay' )
					: __( 'Unable to process this payment, please try again or use alternative method.', 'youcan-pay' ),
			]
		);
	}

	/**
	 * List of currencies supported by YouCan Pay that has no decimals
	 * https://youcanpay.com/docs/currencies#zero-decimal from https://youcanpay.com/docs/currencies#presentment-currencies
	 *
	 * @return array $currencies
	 */
	public static function no_decimal_currencies() {
		return [
			'bif', // Burundian Franc
			'clp', // Chilean Peso
			'djf', // Djiboutian Franc
			'gnf', // Guinean Franc
			'jpy', // Japanese Yen
			'kmf', // Comorian Franc
			'krw', // South Korean Won
			'mga', // Malagasy Ariary
			'pyg', // Paraguayan Guaraní
			'rwf', // Rwandan Franc
			'ugx', // Ugandan Shilling
			'vnd', // Vietnamese Đồng
			'vuv', // Vanuatu Vatu
			'xaf', // Central African Cfa Franc
			'xof', // West African Cfa Franc
			'xpf', // Cfp Franc
		];
	}

	/**
	 * Checks YouCan Pay minimum order value authorized per currency
	 */
	public static function get_minimum_amount() {
		// Check order amount
		switch ( get_woocommerce_currency() ) {
			case 'USD':
			case 'CAD':
			case 'EUR':
			case 'CHF':
			case 'AUD':
			case 'SGD':
				$minimum_amount = 50;
				break;
			case 'GBP':
				$minimum_amount = 30;
				break;
			case 'DKK':
				$minimum_amount = 250;
				break;
			case 'NOK':
			case 'SEK':
				$minimum_amount = 300;
				break;
			case 'JPY':
				$minimum_amount = 5000;
				break;
			case 'MXN':
				$minimum_amount = 1000;
				break;
			case 'HKD':
				$minimum_amount = 400;
				break;
			default:
				$minimum_amount = 50;
				break;
		}

		return $minimum_amount;
	}

	/**
	 * Gets all the saved setting options from a specific method.
	 * If specific setting is passed, only return that.
	 *
	 * @param string $method The payment method to get the settings from.
	 * @param string $setting The name of the setting to get.
	 */
	public static function get_settings( $method = null, $setting = null ) {
		$all_settings = null === $method ? get_option( 'woocommerce_youcanpay_settings', [] ) : get_option( 'woocommerce_youcanpay_' . $method . '_settings', [] );

		if ( null === $setting ) {
			return $all_settings;
		}

		return $all_settings[ $setting ] ?? '';
	}

	/**
	 * Checks if WC version is less than passed in version.
	 *
	 * @param string $version Version to check against.
	 * @return bool
	 */
	public static function is_wc_lt( $version ) {
		return version_compare( WC_VERSION, $version, '<' );
	}

	/**
	 * Gets the webhook URL for YouCan Pay triggers. Used mainly for
	 * asyncronous redirect payment methods in which statuses are
	 * not immediately chargeable.
	 *
	 * @return string
	 */
	public static function get_webhook_url() {
		return add_query_arg( 'wc-api', 'wc_youcanpay', trailingslashit( get_home_url() ) );
	}

	/**
	 * Sanitize statement descriptor text.
	 * YouCan Pay requires max of 22 characters and no special characters.
	 *
	 * @param string $statement_descriptor
	 * @return string $statement_descriptor Sanitized statement descriptor
	 */
	public static function clean_statement_descriptor( $statement_descriptor = '' ) {
		$disallowed_characters = [ '<', '>', '\\', '*', '"', "'", '/', '(', ')', '{', '}' ];
		
		$statement_descriptor = strip_tags( $statement_descriptor );
		
		// Props https://stackoverflow.com/questions/657643/how-to-remove-html-special-chars .
		$statement_descriptor = preg_replace( '/&#?[a-z0-9]{2,8};/i', '', $statement_descriptor );

		// Next, remove any remaining disallowed characters.
		$statement_descriptor = str_replace( $disallowed_characters, '', $statement_descriptor );

		// Trim any whitespace at the ends and limit to 22 characters.
		return substr( trim( $statement_descriptor ), 0, 22 );
	}

	/**
	 * Converts a WooCommerce locale to the closest supported by YouCan Pay.js.
	 *
	 * YouCan Pay.js supports only a subset of IETF language tags, if a country specific locale is not supported we use
	 * the default for that language (https://youcanpay.com/docs/js/appendix/supported_locales).
	 * If no match is found we return 'auto' so YouCan Pay.js uses the browser locale.
	 *
	 * @param string $wc_locale The locale to convert.
	 *
	 * @return string Closest locale supported by YouCan Pay ('auto' if NONE).
	 */
	public static function convert_wc_locale_to_youcanpay_locale( $wc_locale ) {
		$supported = [
			'ar',     // Arabic.
			'bg',     // Bulgarian (Bulgaria).
			'cs',     // Czech (Czech Republic).
			'da',     // Danish.
			'de',     // German (Germany).
			'el',     // Greek (Greece).
			'en',     // English.
			'en-GB',  // English (United Kingdom).
			'es',     // Spanish (Spain).
			'es-419', // Spanish (Latin America).
			'et',     // Estonian (Estonia).
			'fi',     // Finnish (Finland).
			'fr',     // French (France).
			'fr-CA',  // French (Canada).
			'he',     // Hebrew (Israel).
			'hu',     // Hungarian (Hungary).
			'id',     // Indonesian (Indonesia).
			'it',     // Italian (Italy).
			'ja',     // Japanese.
			'lt',     // Lithuanian (Lithuania).
			'lv',     // Latvian (Latvia).
			'ms',     // Malay (Malaysia).
			'mt',     // Maltese (Malta).
			'nb',     // Norwegian Bokmål.
			'nl',     // Dutch (Netherlands).
			'pl',     // Polish (Poland).
			'pt-BR',  // Portuguese (Brazil).
			'pt',     // Portuguese (Brazil).
			'ro',     // Romanian (Romania).
			'ru',     // Russian (Russia).
			'sk',     // Slovak (Slovakia).
			'sl',     // Slovenian (Slovenia).
			'sv',     // Swedish (Sweden).
			'th',     // Thai.
			'tr',     // Turkish (Turkey).
			'zh',     // Chinese Simplified (China).
			'zh-HK',  // Chinese Traditional (Hong Kong).
			'zh-TW',  // Chinese Traditional (Taiwan).
		];

		// YouCan Pay uses '-' instead of '_' (used in WordPress).
		$locale = str_replace( '_', '-', $wc_locale );

		if ( in_array( $locale, $supported, true ) ) {
			return $locale;
		}

		// The plugin has been fully translated for Spanish (Ecuador), Spanish (Mexico), and
		// Spanish(Venezuela), and partially (88% at 2021-05-14) for Spanish (Colombia).
		// We need to map these locales to YouCan Pay's Spanish (Latin America) 'es-419' locale.
		// This list should be updated if more localized versions of Latin American Spanish are
		// made available.
		$lowercase_locale                  = strtolower( $wc_locale );
		$translated_latin_american_locales = [
			'es_co', // Spanish (Colombia).
			'es_ec', // Spanish (Ecuador).
			'es_mx', // Spanish (Mexico).
			'es_ve', // Spanish (Venezuela).
		];
		if ( in_array( $lowercase_locale, $translated_latin_american_locales, true ) ) {
			return 'es-419';
		}

		// Finally, we check if the "base locale" is available.
		$base_locale = substr( $wc_locale, 0, 2 );
		if ( in_array( $base_locale, $supported, true ) ) {
			return $base_locale;
		}

		return 'auto';
	}

	/**
	 * Checks if this page is a cart or checkout page.
	 *
	 * @return boolean
	 */
	public static function has_cart_or_checkout_on_current_page() {
		return is_cart() || is_checkout();
	}

	/**
	 * Checks if paying from order.
	 *
	 * @return boolean
	 */
	public static function is_paying_from_order() {
		return ( array_key_exists( 'pay_for_order', $_GET ) &&  array_key_exists( 'key', $_GET ));
	}

	/**
	 * Return true if the current_tab and current_section match the ones we want to check against.
	 *
	 * @param string $tab
	 * @param string $section
	 * @return boolean
	 */
	public static function should_enqueue_in_current_tab_section( $tab, $section ) {
		global $current_tab, $current_section;

		if ( ! isset( $current_tab ) || $tab !== $current_tab ) {
			return false;
		}

		if ( ! isset( $current_section ) || $section !== $current_section ) {
			return false;
		}

		return true;
	}

	public static function set_payment_method_to_order($order, $method) {
		try {
			$available_payment_gateways = WC()->payment_gateways()->get_available_payment_gateways();
			if (isset($available_payment_gateways[$method])) {
				$order->set_payment_method($available_payment_gateways[$method]);
			}

			return true;
		} catch (Throwable $e) {
			WC_YouCanPay_Logger::alert( 'throwable at set payment method to order exists into wc youcan pay helper', array(
				'exception.message' => $e->getMessage()
			) );
		}

		return false;
	}
}
