<?php
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WCCS_Pricing_Condition_Validator extends WCCS_Condition_Validator {

    protected $cart_totals;

	public function subtotal_including_tax( array $condition ) {
		$value = ! empty( $condition['number_value_2'] ) ? floatval( $condition['number_value_2'] ) : 0;
		if ( $value < 0 ) {
			return false;
		}

		/**
		 * Checking is WooCommerce cart initialized.
		 * Avoid making an issue in WooCommerce API.
		 */
		if ( ! WC()->cart ) {
			return false;
		}

		$this->calculate_cart_totals();

		return WCCS()->WCCS_Comparison->math_compare(
			$this->cart_totals->get_total( 'items_subtotal' ) + $this->cart_totals->get_total( 'items_subtotal_tax' ),
			WCCS_Helpers::maybe_exchange_price( $value, 'coupon' ),
			$condition['math_operation_type']
		);
	}

	protected function get_items_subtotal( array $items, $include_tax = true ) {
		if ( empty( $items ) ) {
			return 0;
		}

		if ( ! $this->cart || ! WC()->cart ) {
			return 0;
		}

		$cart_items = $this->cart->filter_cart_items( $items, false );
		if ( empty( $cart_items ) ) {
			return 0;
		}

		$subtotal = 0;
		foreach ( $cart_items as $cart_item_key => $cart_item ) {
			$subtotal += $include_tax ?
				$this->cart_totals->get_line_item_subtotal( $cart_item_key ) +
				$this->cart_totals->get_line_item_subtotal_tax( $cart_item_key ) :
				$this->cart_totals->get_line_item_subtotal( $cart_item_key );
		}

		return $subtotal;
	}

    protected function calculate_cart_totals( $force = true ) {
        if ( ! $this->cart_totals ) {
            $this->cart_totals = new WCCS_Cart_Totals( WC()->cart );
        }

        $this->cart_totals->calculate( $force );
    }

}
