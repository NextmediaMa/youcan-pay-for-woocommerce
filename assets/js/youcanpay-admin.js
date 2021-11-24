+/* global wc_youcanpay_settings_params */

jQuery( function( $ ) {
	'use strict';

	/**
	 * Object to handle YouCan Pay admin functions.
	 */
	var wc_youcanpay_admin = {

		isSandboxMode: function() {
			return $( '#woocommerce_youcanpay_sandbox_mode' ).is( ':checked' );
		},

		getSecretKey: function() {
			if ( wc_youcanpay_admin.isSandboxMode() ) {
				return $( '#woocommerce_youcanpay_sandbox_private_key' ).val();
			} else {
				return $( '#woocommerce_youcanpay_private_key' ).val();
			}
		},

		/**
		 * Initialize.
		 */
		init: function() {
			$( document.body ).on( 'change', '#woocommerce_youcanpay_sandbox_mode', function() {
				var sandbox_private_key = $( '#woocommerce_youcanpay_sandbox_private_key' ).parents( 'tr' ).eq( 0 ),
					sandbox_public_key = $( '#woocommerce_youcanpay_sandbox_public_key' ).parents( 'tr' ).eq( 0 ),
					production_private_key = $( '#woocommerce_youcanpay_private_key' ).parents( 'tr' ).eq( 0 ),
					production_public_key = $( '#woocommerce_youcanpay_public_key' ).parents( 'tr' ).eq( 0 );

				if ( $( this ).is( ':checked' ) ) {
					sandbox_private_key.show();
					sandbox_public_key.show();
					production_private_key.hide();
					production_public_key.hide();
				} else {
					sandbox_private_key.hide();
					sandbox_public_key.hide();
					production_private_key.show();
					production_public_key.show();
				}
			} );

			$( '#woocommerce_youcanpay_sandbox_mode' ).trigger( 'change' );

			// Toggle Payment Request buttons settings.
			$( '#woocommerce_youcanpay_payment_request' ).on( 'change', function() {
				if ( $( this ).is( ':checked' ) ) {
					$( '#woocommerce_youcanpay_payment_request_button_theme, #woocommerce_youcanpay_payment_request_button_type, #woocommerce_youcanpay_payment_request_button_locations, #woocommerce_youcanpay_payment_request_button_size, #woocommerce_youcanpay_payment_request_button_height' ).closest( 'tr' ).show();
				} else {
					$( '#woocommerce_youcanpay_payment_request_button_theme, #woocommerce_youcanpay_payment_request_button_type, #woocommerce_youcanpay_payment_request_button_locations, #woocommerce_youcanpay_payment_request_button_size, #woocommerce_youcanpay_payment_request_button_height' ).closest( 'tr' ).hide();
				}
			} ).trigger( 'change' );

			// Toggle Custom Payment Request configs.
			$( '#woocommerce_youcanpay_payment_request_button_type' ).on( 'change', function() {
				if ( 'custom' === $( this ).val() ) {
					$( '#woocommerce_youcanpay_payment_request_button_label' ).closest( 'tr' ).show();
				} else {
					$( '#woocommerce_youcanpay_payment_request_button_label' ).closest( 'tr' ).hide();
				}
			} ).trigger( 'change' )

			// Toggle Branded Payment Request configs.
			$( '#woocommerce_youcanpay_payment_request_button_type' ).on( 'change', function() {
				if ( 'branded' === $( this ).val() ) {
					$( '#woocommerce_youcanpay_payment_request_button_branded_type' ).closest( 'tr' ).show();
				} else {
					$( '#woocommerce_youcanpay_payment_request_button_branded_type' ).closest( 'tr' ).hide();
				}
			} ).trigger( 'change' )

			// Make the 3DS notice dismissable.
			$( '.wc-youcanpay-3ds-missing' ).each( function() {
				var $setting = $( this );

				$setting.find( '.notice-dismiss' ).on( 'click.wc-youcanpay-dismiss-notice', function() {
					$.ajax( {
						type: 'head',
						url: window.location.href + '&youcanpay_dismiss_3ds=' + $setting.data( 'nonce' ),
					} );
				} );
			} );

			$( 'form' ).find( 'input, select' ).on( 'change input', function disableConnect() {

				$( '#wc_youcanpay_connect_button' ).addClass( 'disabled' );

				$( '#wc_youcanpay_connect_button' ).on( 'click', function() { return false; } );

				$( '#woocommerce_youcanpay_api_credentials' )
					.next( 'p' )
					.append( ' (Please save changes before selecting this button.)' );

				$( 'form' ).find( 'input, select' ).off( 'change input', disableConnect );
			} );

			// Toggle UPE methods on/off.
			$( '.wc_gateways' ).on( 'click', '.wc-payment-upe-method-toggle-enabled, .wc-payment-upe-method-toggle-disabled', function() {
				var $toggle = $( this ).find( '.woocommerce-input-toggle' );
				$toggle.toggleClass( 'woocommerce-input-toggle--enabled  woocommerce-input-toggle--disabled' );
				$toggle.parent().toggleClass( 'wc-payment-upe-method-toggle-enabled  wc-payment-upe-method-toggle-disabled' );
				$( '#wc_youcanpay_upe_change_notice' ).removeClass( 'hidden' );
				return false;
			});

			$( '#mainform' ).submit( function() {
				var $form = $( this );
				$( '.wc_gateways .wc-payment-upe-method-toggle-enabled').each( function() {
					$form.append( '<input type="hidden" name="woocommerce_youcanpay_upe_checkout_experience_accepted_payments[]" value="' + $( this ).closest( 'tr' ).data( 'upe_method_id' ) + '" />' );
				});
			});
		}
	};

	wc_youcanpay_admin.init();
} );
