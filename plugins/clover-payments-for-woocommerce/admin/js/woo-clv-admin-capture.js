/**
 * Gateway Script
 *
 * @package woo-clover-payments
 */

(function( $ ) {
	'use strict';

	$( document ).on(
		'click',
		'.clv-wc-payment-gateway-capture:not(.disabled)',
		function(e) {

			// get the order_id from the button tag.
			var order_id       = $( this ).data( 'order_id' );
			var order_id_nonce = $( this ).data( 'order_id_nonce' );

			// send the data via ajax to the sever.
			$.ajax(
				{
					type: 'POST',
					url: wc_clover_setting_params.ajaxurl,
					data: {
						action: wc_clover_setting_params.action,
						order_id: order_id,
						order_id_nonce: order_id_nonce
					},
					success: function (response) {
						if ((response.data != null)) {
							if (response.data.message != null) {
								console.log( response.data.message );
								alert( response.data.message );
							}
							if (response.data.success) {
								return location.reload();
							}
						}

					},
					error: function (XMLHttpRequest, textStatus, errorThrown) {
						alert( errorThrown );

					}
				}
			);

		}
	);

})( jQuery );
