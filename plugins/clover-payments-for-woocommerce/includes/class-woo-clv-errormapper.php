<?php
/**
 * Error mapper class
 *
 * @package woo-clover-payments
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Class WOO_CLV_ERRORMAPPER
 */
class WOO_CLV_ERRORMAPPER {

	/**
	 * Error mapper array.
	 *
	 * @return type
	 */
	public static function get_localized_messages() {
		return apply_filters(
			'wc_clv_localized_messages',
			array(
				'amount_too_large'        => __( 'Transaction cannot be processed, please contact the merchant', 'woo-clv-payments' ),
				'card_declined'           => __( 'Transaction declined, please check card information or use a different card', 'woo-clv-payments' ),
				'card_on_file_missing'    => __( 'Transaction failed, incorrect card data', 'woo-clv-payments' ),
				'charge_already_captured' => __( 'Transaction as already been captured', 'woo-clv-payments' ),
				'charge_already_refunded' => __( 'Transaction has already been refunded', 'woo-clv-payments' ),
				'email_invalid'           => __( 'Email ID is invalid, enter valid email ID and retry', 'woo-clv-payments' ),
				'expired_card'            => __( 'Card expired, enter valid card number and retry', 'woo-clv-payments' ),
				'incorrect_cvc'           => __( 'CVV value is incorrect, enter correct CVV value and retry', 'woo-clv-payments' ),
				'incorrect_number'        => __( 'Card number is invalid, enter valid card number and retry', 'woo-clv-payments' ),
				'invalid_card_type'       => __( 'Card brand is invalid or not supported, please use valid card and retry', 'woo-clv-payments' ),
				'invalid_charge_amount'   => __( 'Invalid transaction amount, please contact merchant', 'woo-clv-payments' ),
				'invalid_request'         => __( 'Card is invalid, please retry with a new card', 'woo-clv-payments' ),
				'invalid_tip_amount'      => __( 'Invalid tip amount, please correct and retry', 'woo-clv-payments' ),
				'invalid_tax_amount'      => __( 'Incorrect tax amount, please correct and retry', 'woo-clv-payments' ),
				'missing'                 => __( 'Unable to process transaction', 'woo-clv-payments' ),
				'order_already_paid'      => __( 'Order already paid', 'woo-clv-payments' ),
				'processing_error'        => __( 'Transaction could not be processed', 'woo-clv-payments' ),
				'rate_limit'              => __( 'Transaction could not be processed, please contact the merchant', 'woo-clv-payments' ),
				'resource_missing'        => __( 'Transaction could not be processed due to incorrect or invalid data', 'woo-clv-payments' ),
				'token_already_used'      => __( 'Transaction could not be processed, please renter card details and retry', 'woo-clv-payments' ),
				'invalid_key'             => __( 'Unauthorized, please contact the merchant', 'woo-clv-payments' ),
				'invalid_details'         => __( 'Transaction failed, incorrect data provided', 'woo-clv-payments' ),
				'unexpected'              => __( 'Transaction could not be processed, please retry', 'woo-clv-payments' ),
			)
		);
	}

	/**
	 * Method to invoke array.
	 *
	 * @param type $response Code filter.
	 * @return type
	 */
	public static function get_localized_error_message( $response ) {
		$localized_messages        = self::get_localized_messages();
				$localized_message = isset( $localized_messages[ $response['error_code'] ] ) ? $localized_messages[ $response['error_code'] ] : $response['message'];
		return $localized_message;
	}

}
