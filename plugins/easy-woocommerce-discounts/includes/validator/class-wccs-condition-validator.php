<?php
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WCCS_Condition_Validator {

	protected $customer;

	protected $products;

	protected $cart;

	public function __construct(
		$customer = null,
		WCCS_Products $products = null,
		WCCS_Cart $cart = null
	) {
		$wccs            = WCCS();
		$this->customer  = ! is_null( $customer ) ? new WCCS_Customer( $customer ) : new WCCS_Customer( wp_get_current_user() );
		$this->products  = ! is_null( $products ) ? $products : $wccs->products;
		$this->cart      = ! is_null( $cart ) ? $cart : $wccs->cart;
	}

	protected function init_cart() {
		if ( isset( $this->cart ) ) {
			return;
		}

		if ( WCCS()->cart ) {
			$this->cart = WCCS()->cart;
		}
	}

	public function is_valid_conditions( array $conditions, $match_mode = 'all' ) {
		if ( empty( $conditions ) ) {
			return true;
		}

		$this->init_cart();

		// New structure conditions that supports OR conditions too.
		if ( is_array( $conditions[0] ) && ! isset( $conditions[0]['condition'] ) ) {
			$empty = true;
			foreach ( $conditions as $group ) {
				if ( empty( $group ) ) {
					continue;
				}

				$empty = false;
				$valid = true;
				foreach ( $group as $condition ) {
					if ( ! $this->is_valid( $condition ) ) {
						$valid = false;
						break;
					}
				}
				if ( $valid ) {
					return true;
				}
			}
			return $empty;
		}

		foreach ( $conditions as $condition ) {
			if ( 'one' === $match_mode && $this->is_valid( $condition ) ) {
				return true;
			} elseif ( 'all' === $match_mode && ! $this->is_valid( $condition ) ) {
				return false;
			}
		}

		return 'all' === $match_mode;
	}

	public function is_valid( array $condition ) {
		if ( empty( $condition ) ) {
			return false;
		}

		$is_valid = false;
		if ( method_exists( $this, $condition['condition'] ) ) {
			$is_valid = $this->{$condition['condition']}( $condition );
		}

		return apply_filters( 'wccs_condition_validator_is_valid_' . $condition['condition'], $is_valid, $condition );
	}

	public function number_of_cart_items( array $condition ) {
		$value = ! empty( $condition['number_value_2'] ) ? intval( $condition['number_value_2'] ) : 0;
		if ( $value < 0 ) {
			return false;
		}

		/**
		 * Checking is WooCommerce cart initialized.
		 * Avoid making an issue in WooCommerce API.
		 */
		if ( ! $this->cart || ! WC()->cart ) {
			return false;
		}

		return WCCS()->WCCS_Comparison->math_compare( $this->cart->get_cart_contents_count(), $value, $condition['math_operation_type'] );
	}

	public function subtotal_including_tax( array $condition ) {
		$value = ! empty( $condition['number_value_2'] ) ? floatval( $condition['number_value_2'] ) : 0;
		if ( $value < 0 ) {
			return false;
		}

		/**
		 * Checking is WooCommerce cart initialized.
		 * Avoid making an issue in WooCommerce API.
		 */
		if ( ! $this->cart || ! WC()->cart ) {
			return false;
		}

		$value = WCCS_Helpers::maybe_exchange_price( $value, 'coupon' );

		return WCCS()->WCCS_Comparison->math_compare( $this->cart->subtotal, $value, $condition['math_operation_type'] );
	}

	public function cart_total_weight( array $condition ) {
		if ( ! $this->cart || ! WC()->cart ) {
			return false;
		}

		$value = ! empty( $condition['number_value_2'] ) ? floatval( $condition['number_value_2'] ) : 0;
		if ( $value < 0 ) {
			return false;
		}

		return WCCS()->WCCS_Comparison->math_compare( $this->cart->get_cart_contents_weight(), $value, $condition['math_operation_type'] );
	}

}
