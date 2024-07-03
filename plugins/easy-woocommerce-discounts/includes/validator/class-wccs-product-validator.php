<?php
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WCCS_Product_Validator {

	protected $customer;

	public function __construct( $customer = null ) {
		$this->customer  = ! is_null( $customer ) ? new WCCS_Customer( $customer ) : new WCCS_Customer( wp_get_current_user() );
	}

	public function is_valid_product( array $items, $product, $variation = 0, array $variations = array(), $cart_item = array() ) {
		if ( empty( $items ) ) {
			return false;
		}

		if ( ! apply_filters( 'wccs_product_validator_is_valid_cart_item', true, $cart_item ) ) {
			return false;
		}

		// New structure conditions that supports OR conditions too.
		if ( is_array( $items[0] ) && ! isset( $items[0]['item'] ) && ! isset( $items[0]['condition'] ) ) {
			foreach ( $items as $group ) {
				if ( empty( $group ) ) {
					continue;
				}

				$valid = true;
				foreach ( $group as $item ) {
					if ( ! $this->is_valid( $item, $product, $variation, $variations ) ) {
						$valid = false;
						break;
					}
				}
				if ( $valid ) {
					return true;
				}
			}
			return false;
		}

		foreach ( $items as $item ) {
			if ( ! $this->is_valid( $item, $product, $variation, $variations ) ) {
				return false;
			}
		}

		return true;
	}

	public function is_valid( $item, $product, $variation = 0, array $variations = array() ) {
		if ( empty( $item ) ) {
			return false;
		}

		$method = '';
		if ( isset( $item['item'] ) ) {
			$method = $item['item'];
		} elseif ( isset( $item['condition'] ) ) {
			$method = $item['condition'];
		}

		$method = apply_filters( 'wccs_product_validator_validate_method', $method, $item, $item, $product, $variation, $variations );
		if ( empty( $method ) ) {
			return false;
		}

		$is_valid = false;
		if ( method_exists( $this, $method ) ) {
			$is_valid = $this->{$method}( $item, $product, $variation, $variations );
		}

		return apply_filters( 'wccs_product_validator_is_valid_' . $method, $is_valid, $item, $product, $variation, $variations );
	}

	public function all_products( $item, $product, $variation, $variations ) {
		if ( is_object( $product ) ) {
			return 0 < $product->get_id();
		}
		return 0 < $product;
	}

	public function products_in_list( $item, $product, $variation = 0, array $variations = array() ) {
		if ( empty( $item['products'] ) ) {
			return false;
		}

		$product = is_numeric( $product ) ? $product : $product->get_id();

		return in_array( $product, array_filter( array_map( 'WCCS_Helpers::maybe_get_exact_item_id', $item['products'] ) ) );
	}

	public function products_not_in_list( $item, $product, $variation = 0, array $variations = array() ) {
		if ( empty( $item['products'] ) ) {
			return false;
		}

		$product = is_numeric( $product ) ? $product : $product->get_id();

		return ! in_array( $product, array_filter( array_map( 'WCCS_Helpers::maybe_get_exact_item_id', $item['products'] ) ) );
	}

	public function categories_in_list( $item, $product, $variation = 0, array $variations = array() ) {
		if ( empty( $item['categories'] ) ) {
			return false;
		}

		$item_categories    = array_map( 'WCCS_Helpers::maybe_get_exact_category_id', $item['categories'] );
		$product            = is_numeric( $product ) ? $product : $product->get_id();
		$product_categories = wc_get_product_cat_ids( $product );
		foreach ( $product_categories as $category ) {
			if ( in_array( $category, $item_categories ) ) {
				return true;
			}
		}
		return false;
	}

	public function categories_not_in_list( $item, $product, $variation = 0, array $variations = array() ) {
		if ( empty( $item['categories'] ) ) {
			return false;
		}

		$item_categories    = array_map( 'WCCS_Helpers::maybe_get_exact_category_id', $item['categories'] );
		$product            = is_numeric( $product ) ? $product : $product->get_id();
		$product_categories = wc_get_product_cat_ids( $product );
		foreach ( $product_categories as $category ) {
			if ( in_array( $category, $item_categories ) ) {
				return false;
			}
		}
		return true;
	}

}
