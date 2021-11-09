<?php
/**
 * Display a notice to merchants to inform about UPE.
 *
 * @package WooCommerce\Payments\Admin
 */

use Automattic\WooCommerce\Admin\Notes\NoteTraits;
use Automattic\WooCommerce\Admin\Notes\Note;
use Automattic\WooCommerce\Admin\Notes\WC_Admin_Note;

defined( 'ABSPATH' ) || exit;

/**
 * Class WC_YouCanPay_UPE_Availability_Note
 */
class WC_YouCanPay_UPE_Availability_Note {
	use NoteTraits;

	/**
	 * Name of the note for use in the database.
	 */
	const NOTE_NAME = 'wc-youcanpay-upe-availability-note';

	/**
	 * Link to enable the UPE in store.
	 */
	const ENABLE_IN_STORE_LINK = '?page=wc_youcanpay-onboarding_wizard';


	/**
	 * Get the note.
	 */
	public static function get_note() {
		$note_class = self::get_note_class();
		$note       = new $note_class();

		$note->set_title( __( 'Boost your sales with the new payment experience in YouCan Pay', 'woocommerce-gateway-youcanpay' ) );
		$note->set_content( __( 'Get early access to an improved checkout experience, now available to select merchants. <a href="?TODO" target="_blank">Learn more</a>.', 'woocommerce-gateway-youcanpay' ) );
		$note->set_type( $note_class::E_WC_ADMIN_NOTE_INFORMATIONAL );
		$note->set_name( self::NOTE_NAME );
		$note->set_source( 'woocommerce-gateway-youcanpay' );
		$note->add_action(
			self::NOTE_NAME,
			__( 'Enable in your store', 'woocommerce-gateway-youcanpay' ),
			self::ENABLE_IN_STORE_LINK,
			$note_class::E_WC_ADMIN_NOTE_UNACTIONED,
			true
		);

		return $note;
	}

	/**
	 * Get the class type to be used for the note.
	 *
	 * @return string
	 */
	private static function get_note_class() {
		if ( class_exists( 'Automattic\WooCommerce\Admin\Notes\Note' ) ) {
			return Note::class;
		} else {
			return WC_Admin_Note::class;
		}
	}

	public static function init() {
		/**
		 * No need to display the admin inbox note when
		 * - UPE preview is disabled
		 * - UPE is already enabled
		 * - UPE has been manually disabled
		 * - YouCan Pay is not enabled
		 */
		if ( ! WC_YouCanPay_Feature_Flags::is_upe_preview_enabled() ) {
			return;
		}

		if ( WC_YouCanPay_Feature_Flags::is_upe_checkout_enabled() ) {
			return;
		}

		if ( WC_YouCanPay_Feature_Flags::did_merchant_disable_upe() ) {
			return;
		}

		if ( ! woocommerce_gateway_youcanpay()->connect->is_connected() ) {
			return;
		}

		$youcanpay_settings = get_option( 'woocommerce_youcanpay_settings', [] );
		$youcanpay_enabled  = isset( $youcanpay_settings['enabled'] ) && 'yes' === $youcanpay_settings['enabled'];
		if ( ! $youcanpay_enabled ) {
			return;
		}

		self::possibly_add_note();
	}
}
