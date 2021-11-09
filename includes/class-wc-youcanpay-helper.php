<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Provides static methods as helpers.
 *
 * @since 4.0.0
 */
class WC_YouCanPay_Helper {
	const LEGACY_META_NAME_FEE      = 'YouCan Pay Fee';
	const LEGACY_META_NAME_NET      = 'Net Revenue From YouCan Pay';
	const META_NAME_FEE             = '_youcanpay_fee';
	const META_NAME_NET             = '_youcanpay_net';
	const META_NAME_YOUCAN_PAY_CURRENCY = '_youcanpay_currency';

	/**
	 * Gets the YouCan Pay currency for order.
	 *
	 * @since 4.1.0
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
	 * Updates the YouCan Pay currency for order.
	 *
	 * @since 4.1.0
	 * @param object $order
	 * @param string $currency
	 */
	public static function update_youcanpay_currency( $order, $currency ) {
		if ( is_null( $order ) ) {
			return false;
		}

		$order->update_meta_data( self::META_NAME_YOUCAN_PAY_CURRENCY, $currency );
	}

	/**
	 * Gets the YouCan Pay fee for order. With legacy check.
	 *
	 * @since 4.1.0
	 * @param object $order
	 * @return string $amount
	 */
	public static function get_youcanpay_fee( $order = null ) {
		if ( is_null( $order ) ) {
			return false;
		}

		$amount = $order->get_meta( self::META_NAME_FEE, true );

		// If not found let's check for legacy name.
		if ( empty( $amount ) ) {
			$amount = $order->get_meta( self::LEGACY_META_NAME_FEE, true );

			// If found update to new name.
			if ( $amount ) {
				self::update_youcanpay_fee( $order, $amount );
			}
		}

		return $amount;
	}

	/**
	 * Updates the YouCan Pay fee for order.
	 *
	 * @since 4.1.0
	 * @param object $order
	 * @param float  $amount
	 */
	public static function update_youcanpay_fee( $order = null, $amount = 0.0 ) {
		if ( is_null( $order ) ) {
			return false;
		}

		$order->update_meta_data( self::META_NAME_FEE, $amount );
	}

	/**
	 * Deletes the YouCan Pay fee for order.
	 *
	 * @since 4.1.0
	 * @param object $order
	 */
	public static function delete_youcanpay_fee( $order = null ) {
		if ( is_null( $order ) ) {
			return false;
		}

		$order_id = $order->get_id();

		delete_post_meta( $order_id, self::META_NAME_FEE );
		delete_post_meta( $order_id, self::LEGACY_META_NAME_FEE );
	}

	/**
	 * Gets the YouCan Pay net for order. With legacy check.
	 *
	 * @since 4.1.0
	 * @param object $order
	 * @return string $amount
	 */
	public static function get_youcanpay_net( $order = null ) {
		if ( is_null( $order ) ) {
			return false;
		}

		$amount = $order->get_meta( self::META_NAME_NET, true );

		// If not found let's check for legacy name.
		if ( empty( $amount ) ) {
			$amount = $order->get_meta( self::LEGACY_META_NAME_NET, true );

			// If found update to new name.
			if ( $amount ) {
				self::update_youcanpay_net( $order, $amount );
			}
		}

		return $amount;
	}

	/**
	 * Updates the YouCan Pay net for order.
	 *
	 * @since 4.1.0
	 * @param object $order
	 * @param float  $amount
	 */
	public static function update_youcanpay_net( $order = null, $amount = 0.0 ) {
		if ( is_null( $order ) ) {
			return false;
		}

		$order->update_meta_data( self::META_NAME_NET, $amount );
	}

	/**
	 * Deletes the YouCan Pay net for order.
	 *
	 * @since 4.1.0
	 * @param object $order
	 */
	public static function delete_youcanpay_net( $order = null ) {
		if ( is_null( $order ) ) {
			return false;
		}

		$order_id = $order->get_id();

		delete_post_meta( $order_id, self::META_NAME_NET );
		delete_post_meta( $order_id, self::LEGACY_META_NAME_NET );
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
	 * Localize YouCan Pay messages based on code
	 *
	 * @since 3.0.6
	 * @version 3.0.6
	 * @return array
	 */
	public static function get_localized_messages() {
		return apply_filters(
			'wc_youcanpay_localized_messages',
			[
				'invalid_number'           => __( 'The card number is not a valid credit card number.', 'woocommerce-gateway-youcanpay' ),
				'invalid_expiry_month'     => __( 'The card\'s expiration month is invalid.', 'woocommerce-gateway-youcanpay' ),
				'invalid_expiry_year'      => __( 'The card\'s expiration year is invalid.', 'woocommerce-gateway-youcanpay' ),
				'invalid_cvc'              => __( 'The card\'s security code is invalid.', 'woocommerce-gateway-youcanpay' ),
				'incorrect_number'         => __( 'The card number is incorrect.', 'woocommerce-gateway-youcanpay' ),
				'incomplete_number'        => __( 'The card number is incomplete.', 'woocommerce-gateway-youcanpay' ),
				'incomplete_cvc'           => __( 'The card\'s security code is incomplete.', 'woocommerce-gateway-youcanpay' ),
				'incomplete_expiry'        => __( 'The card\'s expiration date is incomplete.', 'woocommerce-gateway-youcanpay' ),
				'expired_card'             => __( 'The card has expired.', 'woocommerce-gateway-youcanpay' ),
				'incorrect_cvc'            => __( 'The card\'s security code is incorrect.', 'woocommerce-gateway-youcanpay' ),
				'incorrect_zip'            => __( 'The card\'s zip code failed validation.', 'woocommerce-gateway-youcanpay' ),
				'invalid_expiry_year_past' => __( 'The card\'s expiration year is in the past', 'woocommerce-gateway-youcanpay' ),
				'card_declined'            => __( 'The card was declined.', 'woocommerce-gateway-youcanpay' ),
				'missing'                  => __( 'There is no card on a customer that is being charged.', 'woocommerce-gateway-youcanpay' ),
				'processing_error'         => __( 'An error occurred while processing the card.', 'woocommerce-gateway-youcanpay' ),
				'invalid_sofort_country'   => __( 'The billing country is not accepted by SOFORT. Please try another country.', 'woocommerce-gateway-youcanpay' ),
				'email_invalid'            => __( 'Invalid email address, please correct and try again.', 'woocommerce-gateway-youcanpay' ),
				'invalid_request_error'    => is_add_payment_method_page()
					? __( 'Unable to save this payment method, please try again or use alternative method.', 'woocommerce-gateway-youcanpay' )
					: __( 'Unable to process this payment, please try again or use alternative method.', 'woocommerce-gateway-youcanpay' ),
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
	 * YouCan Pay uses smallest denomination in currencies such as cents.
	 * We need to format the returned currency from YouCan Pay into human readable form.
	 * The amount is not used in any calculations so returning string is sufficient.
	 *
	 * @param object $balance_transaction
	 * @param string $type Type of number to format
	 * @return string
	 */
	public static function format_balance_fee( $balance_transaction, $type = 'fee' ) {
		if ( ! is_object( $balance_transaction ) ) {
			return;
		}

		if ( in_array( strtolower( $balance_transaction->currency ), self::no_decimal_currencies() ) ) {
			if ( 'fee' === $type ) {
				return $balance_transaction->fee;
			}

			return $balance_transaction->net;
		}

		if ( 'fee' === $type ) {
			return number_format( $balance_transaction->fee / 100, 2, '.', '' );
		}

		return number_format( $balance_transaction->net / 100, 2, '.', '' );
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
	 * @since 4.0.0
	 * @version 4.0.0
	 * @param string $method The payment method to get the settings from.
	 * @param string $setting The name of the setting to get.
	 */
	public static function get_settings( $method = null, $setting = null ) {
		$all_settings = null === $method ? get_option( 'woocommerce_youcanpay_settings', [] ) : get_option( 'woocommerce_youcanpay_' . $method . '_settings', [] );

		if ( null === $setting ) {
			return $all_settings;
		}

		return isset( $all_settings[ $setting ] ) ? $all_settings[ $setting ] : '';
	}

	/**
	 * Checks if Pre Orders is available.
	 *
	 * @since 4.1.0
	 * @return bool
	 */
	public static function is_pre_orders_exists() {
		return class_exists( 'WC_Pre_Orders' );
	}

	/**
	 * Checks if WC version is less than passed in version.
	 *
	 * @since 4.1.11
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
	 * @since 4.0.0
	 * @version 4.0.0
	 * @return string
	 */
	public static function get_webhook_url() {
		return add_query_arg( 'wc-api', 'wc_youcanpay', trailingslashit( get_home_url() ) );
	}

	/**
	 * Gets the order by YouCan Pay source ID.
	 *
	 * @since 4.0.0
	 * @version 4.0.0
	 * @param string $source_id
	 */
	public static function get_order_by_source_id( $source_id ) {
		global $wpdb;

		$order_id = $wpdb->get_var( $wpdb->prepare( "SELECT DISTINCT ID FROM $wpdb->posts as posts LEFT JOIN $wpdb->postmeta as meta ON posts.ID = meta.post_id WHERE meta.meta_value = %s AND meta.meta_key = %s", $source_id, '_youcanpay_source_id' ) );

		if ( ! empty( $order_id ) ) {
			return wc_get_order( $order_id );
		}

		return false;
	}

	/**
	 * Gets the order by YouCan Pay charge ID.
	 *
	 * @since 4.0.0
	 * @since 4.1.16 Return false if charge_id is empty.
	 * @param string $charge_id
	 */
	public static function get_order_by_charge_id( $charge_id ) {
		global $wpdb;

		if ( empty( $charge_id ) ) {
			return false;
		}

		$order_id = $wpdb->get_var( $wpdb->prepare( "SELECT DISTINCT ID FROM $wpdb->posts as posts LEFT JOIN $wpdb->postmeta as meta ON posts.ID = meta.post_id WHERE meta.meta_value = %s AND meta.meta_key = %s", $charge_id, '_transaction_id' ) );

		if ( ! empty( $order_id ) ) {
			return wc_get_order( $order_id );
		}

		return false;
	}

	/**
	 * Gets the order by YouCan Pay PaymentIntent ID.
	 *
	 * @since 4.2
	 * @param string $intent_id The ID of the intent.
	 * @return WC_Order|bool Either an order or false when not found.
	 */
	public static function get_order_by_intent_id( $intent_id ) {
		global $wpdb;

		$order_id = $wpdb->get_var( $wpdb->prepare( "SELECT DISTINCT ID FROM $wpdb->posts as posts LEFT JOIN $wpdb->postmeta as meta ON posts.ID = meta.post_id WHERE meta.meta_value = %s AND meta.meta_key = %s", $intent_id, '_youcanpay_intent_id' ) );

		if ( ! empty( $order_id ) ) {
			return wc_get_order( $order_id );
		}

		return false;
	}

	/**
	 * Gets the order by YouCan Pay SetupIntent ID.
	 *
	 * @since 4.3
	 * @param string $intent_id The ID of the intent.
	 * @return WC_Order|bool Either an order or false when not found.
	 */
	public static function get_order_by_setup_intent_id( $intent_id ) {
		global $wpdb;

		$order_id = $wpdb->get_var( $wpdb->prepare( "SELECT DISTINCT ID FROM $wpdb->posts as posts LEFT JOIN $wpdb->postmeta as meta ON posts.ID = meta.post_id WHERE meta.meta_value = %s AND meta.meta_key = %s", $intent_id, '_youcanpay_setup_intent' ) );

		if ( ! empty( $order_id ) ) {
			return wc_get_order( $order_id );
		}

		return false;
	}

	/**
	 * Sanitize statement descriptor text.
	 *
	 * YouCan Pay requires max of 22 characters and no special characters.
	 *
	 * @since 4.0.0
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
		$statement_descriptor = substr( trim( $statement_descriptor ), 0, 22 );

		return $statement_descriptor;
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
		// List copied from: https://youcanpay.com/docs/js/appendix/supported_locales.
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
		// We need to map these locales to YouCanPay's Spanish (Latin America) 'es-419' locale.
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

		// Default to 'auto' so YouCanPay.js uses the browser locale.
		return 'auto';
	}

	/**
	 * Checks if this page is a cart or checkout page.
	 *
	 * @since 5.2.3
	 * @return boolean
	 */
	public static function has_cart_or_checkout_on_current_page() {
		return is_cart() || is_checkout();
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
}
