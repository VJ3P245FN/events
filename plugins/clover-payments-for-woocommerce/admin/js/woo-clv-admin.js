/**
 * Gateway Script
 *
 * @package woo-clover-payments
 */

jQuery( document ).ready(
	function() {
		let environment = jQuery( '#woocommerce_clover_payments_environment' ).val();
		hideshow( environment );
		jQuery( '#woocommerce_clover_payments_environment' ).on(
			'change',
			function() {
				hideshow( this.value );
			}
		);

		$( document ).on(
			'click',
			'.clv-wc-payment-gateway-capture',
			function(e) {
				alert( 'capture' );
			}
		);

	}
);

/**
 * Sandbox or Production fields.
 *
 * @param {type} environment
 * @returns {undefined}
 */
function hideshow(environment) {
	if (environment == 'sandbox') {
		jQuery( '.clvsdfields' ).closest( 'tr' ).show();
		jQuery( '.clvfields' ).closest( 'tr' ).hide();
	} else {
		jQuery( '.clvsdfields' ).closest( 'tr' ).hide();
		jQuery( '.clvfields' ).closest( 'tr' ).show();
	}
}
