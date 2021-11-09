<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WC_YouCanPay_Feature_Flags {
	const UPE_CHECKOUT_FEATURE_ATTRIBUTE_NAME = 'upe_checkout_experience_enabled';

	/**
	 * Checks whether UPE "preview" feature flag is enabled.
	 * This allows the merchant to enable/disable UPE checkout.
	 *
	 * @return bool
	 */
	public static function is_upe_preview_enabled() {
		return 'yes' === get_option( '_wcyoucanpay_feature_upe', 'yes' ) || self::is_upe_settings_redesign_enabled();
	}

	/**
	 * Checks whether UPE is enabled.
	 *
	 * @return bool
	 */
	public static function is_upe_checkout_enabled() {
		$youcanpay_settings = get_option( 'woocommerce_youcanpay_settings', null );
		return ! empty( $youcanpay_settings[ self::UPE_CHECKOUT_FEATURE_ATTRIBUTE_NAME ] ) && 'yes' === $youcanpay_settings[ self::UPE_CHECKOUT_FEATURE_ATTRIBUTE_NAME ];
	}

	/**
	 * Checks whether UPE has been manually disabled by the merchant.
	 *
	 * @return bool
	 */
	public static function did_merchant_disable_upe() {
		$youcanpay_settings = get_option( 'woocommerce_youcanpay_settings', null );
		return ! empty( $youcanpay_settings[ self::UPE_CHECKOUT_FEATURE_ATTRIBUTE_NAME ] ) && 'disabled' === $youcanpay_settings[ self::UPE_CHECKOUT_FEATURE_ATTRIBUTE_NAME ];
	}

	/**
	 * Checks whether the feature flag used for the new settings + UPE is enabled.
	 *
	 * @return bool
	 */
	public static function is_upe_settings_redesign_enabled() {
		return 'yes' === get_option( '_wcyoucanpay_feature_upe_settings', 'no' );
	}
}
