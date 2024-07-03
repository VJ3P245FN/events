/**
 * Gateway Script
 *
 * @package woo-clover-payments
 */
/* global wc_checkout_params */
jQuery(
	function($) {
		"use strict";

		var clover_gateway = {
			initFormEvents:function(){
				// pay order page
				if ( $("form#order_review").length){
					this.form = $("form#order_review");
					$( document.body ).trigger( 'wc-credit-card-form-init' );
					$( 'form#order_review' ).on(
						'submit',
						function() {
							if ( ! clover_gateway.ifWeChosen()) {
								return true;
							}
							if ( ! clover_gateway.isValidated()) {
								clover_gateway.onSubmit();
								return false;
							}
						}
					);
				}
				//checkout page
				if ( $( 'form.woocommerce-checkout' ).length ) {
					this.form = $( 'form.woocommerce-checkout' );

					$( 'form.checkout' ).on(
						'checkout_place_order',
						function() {
							if ( ! clover_gateway.ifWeChosen()) {
								return true;
							}

							if ( ! clover_gateway.isValidated()) {
								clover_gateway.onSubmit();
						        return false;
							}
						}
					);
				}
			},
			resetValidationFlag: function(){
				document.getElementById( "transresult" ).value = 0;
			}
			,
			initCloverElement: function () {
					self                 = this;
					self.clover          = new Clover(
						this.getPublicKey(),
						{
							locale: this.getLocale(),
							merchantId: this.getMerchantId()
							}
					);
					const elements       = self.clover.elements();
					const styles         = {
						body: {
							fontFamily: 'Roboto, Open Sans, sans-serif',
							fontSize: '16px'
						},
						input: {
							fontSize: '16px',
							padding: '0px',
							margin: '0px',
							backgroundColor: 'beige'
						},
						"input:focus": {border: "1px solid red"}
				};

					const cardNumber     = elements.create( 'CARD_NUMBER', styles );
					const cardDate       = elements.create( 'CARD_DATE', styles );
					const cardCvv        = elements.create( 'CARD_CVV', styles );
					const cardPostalCode = elements.create( 'CARD_POSTAL_CODE', styles );

					cardNumber.mount( '#card-number' );
					cardDate.mount( '#card-date' );
					cardCvv.mount( '#card-cvv' );
					cardPostalCode.mount( '#card-postal-code' );

					const cardResponse = document.getElementById( 'card-response' );

					const displayCardNumberError     = document.getElementById( 'card-number-errors' );
					const displayCardDateError       = document.getElementById( 'card-date-errors' );
					const displayCardCvvError        = document.getElementById( 'card-cvv-errors' );
					const displayCardPostalCodeError = document.getElementById( 'card-postal-code-errors' );
					// Handle real-time validation errors from the card Element.
					cardNumber.addEventListener(
						'change',
						function (event) {
							console.log( `cardNumber changed ${JSON.stringify( event )}` );
							displayCardNumberError.title = displayCardNumberError.innerHTML = event.CARD_NUMBER.error || '';
							clover_gateway.resetValidationFlag();
						}
					);

					cardNumber.addEventListener(
						'blur',
						function (event) {
							console.log( `cardNumber blur ${JSON.stringify( event )}` );
							displayCardNumberError.title = displayCardNumberError.innerHTML = event.CARD_NUMBER.error || '';

						}
					);

					cardDate.addEventListener(
						'change',
						function (event) {
							console.log( `cardDate changed ${JSON.stringify( event )}` );
							displayCardDateError.title = displayCardDateError.innerHTML = event.CARD_DATE.error || '';
							clover_gateway.resetValidationFlag();
						}
					);

					cardDate.addEventListener(
						'blur',
						function (event) {
							console.log( `cardDate blur ${JSON.stringify( event )}` );
							displayCardDateError.title = displayCardDateError.innerHTML = event.CARD_DATE.error || '';
						}
					);

					cardCvv.addEventListener(
						'change',
						function (event) {
							console.log( `cardCvv changed ${JSON.stringify( event )}` );
							displayCardCvvError.title = displayCardCvvError.innerHTML = event.CARD_CVV.error || '';
							clover_gateway.resetValidationFlag();
						}
					);

					cardCvv.addEventListener(
						'blur',
						function (event) {
							console.log( `cardCvv blur ${JSON.stringify( event )}` );
							displayCardCvvError.title = displayCardCvvError.innerHTML = event.CARD_CVV.error || '';
						}
					);

					cardPostalCode.addEventListener(
						'change',
						function (event) {
							console.log( `cardPostalCode changed ${JSON.stringify( event )}` );
							displayCardPostalCodeError.title = displayCardPostalCodeError.innerHTML = event.CARD_POSTAL_CODE.error || '';
							clover_gateway.resetValidationFlag();
						}
					);

					cardPostalCode.addEventListener(
						'blur',
						function (event) {
							console.log( `cardPostalCode blur ${JSON.stringify( event )}` );
							displayCardPostalCodeError.title = displayCardPostalCodeError.innerHTML = event.CARD_POSTAL_CODE.error || '';
						}
					);

			},
			getPublicKey:function(){
				return clover_params.publishableKey;
			},
			cloverChargeToken:function(){
				return clover_gateway.clover.createToken()
					.then(
						function(result){
							console.log( result );
							clover_gateway.chargeTokenSuccess( result );
						}
					).catch(
						function(err){
							console.log( err );

							clover_gateway.chargeTokenFailure( err );
						}
					);
			},
			block: function() {
				clover_gateway.form.block(
					{
						message: null,
						overlayCSS: {
							background: "#fff",
							opacity: .6
						}
					}
				)
			},
			unblock: function() {
				clover_gateway.form && clover_gateway.form.unblock()
			},
			onSubmit:function(){
				var deferred = clover_gateway.cloverChargeToken();
				$.when( deferred ).done(
					function (result) {
						return clover_gateway.placeOrderHandler();
					}
				).fail(
					function (result) {
						$( '#card-errors' ).html( "(0001) Transaction could be processed, please contact the merchant" );
						return false;
					}
				);
			},
			placeOrderHandler: function () {
				if (clover_gateway.isValidated()) {
					return true;
				} else {
					return false;
				}
			},
			isValidated: function () {
				if (document.getElementById( "transresult" ).value == 1) {
					return true;
				}
				return false;
			},
			chargeTokenSuccess:function(result){
				var defer = $.Deferred();
				if (result.errors) {
					clover_gateway.chargeTokenError( result.errors );
					return false;
				}
				if (result.token && result.token != '') {
								var trans    = document.getElementById( "transresult" );
								var ctoken   = document.getElementById( "cloverToken" );
								ctoken.value = result.token;
								trans.value  = 1;
								clover_gateway.form.submit();
								defer.resolve( {} );
				} else {
					$( '#card-errors' ).html( "(0003) Transaction could be processed, please contact the merchant" );
					defer.reject( 'Token should not be empty' );
				}
			},
			chargeTokenFailure:function(err){
				var defer    = $.Deferred();
				var trans    = document.getElementById( "transresult" );
				var ctoken   = document.getElementById( "cloverToken" );
				ctoken.value = '';
				trans.value  = 0;
				$( '#card-errors' ).html( "(0001) Transaction could be processed, please contact the merchant" );
				defer.reject( 'An error occurred on server, please try again' );
			},
			chargeTokenError:function(errors){
				var defer = $.Deferred();
				document.getElementById( 'card-number-errors' ).title      = document.getElementById( 'card-number-errors' ).innerHTML = errors.CARD_NUMBER || '';
				document.getElementById( 'card-date-errors' ).title        = document.getElementById( 'card-date-errors' ).innerHTML = errors.CARD_DATE || '';
				document.getElementById( 'card-cvv-errors' ).title         = document.getElementById( 'card-cvv-errors' ).innerHTML = errors.CARD_CVV || '';
				document.getElementById( 'card-postal-code-errors' ).title = document.getElementById( 'card-postal-code-errors' ).innerHTML = errors.CARD_POSTAL_CODE || '';
				var trans    = document.getElementById( "transresult" );
				var ctoken   = document.getElementById( "cloverToken" );
				document.getElementById( "transresult" ).value = 0;
				document.getElementById( "cloverToken" ).value = '';
				ctoken.value = '';
				trans.value  = 0;
				defer.reject( 'Token should not be empty' );
			},
			ifWeChosen: function() {
				return $( "#payment_method_clover_payments" ).is( ":checked" );
			},
			getLocale: function () {
				var locale = clover_params.locale;
				return locale.replace( '_','-' );
			},
			getMerchantId: function() {
				return clover_params.merchantId;
			}

		};

		clover_gateway.initFormEvents();

		// initializes clover iframe elements for pay for order page
		if( document.getElementById("add_payment_method") || document.getElementById("order_review") ) {

			$(document).ready(function () {
				(function () {
					$('#gap_form').wrap('<form action="/charge" method="post" class="clover-gateway" id="payment-form"></form>');
				})();});
			clover_gateway.initCloverElement();

		}
		$( document.body ).on(
			"updated_checkout",
			function() {
				if ( ! document.querySelector( '#card-number>iframe' )) {
					clover_gateway.initCloverElement();
				}
			}
		);
	}
);
