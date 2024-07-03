<?php
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WCCS_Cart_Items_Helpers {

    /**
     * Check is given products exists inside given cart items.
     *
     * @since  4.0.0
     *
     * @param  array   $cart_items
	 * @param  array   $products
	 * @param  string  $type
     * @param  integer $number
     *
     * @return boolean
     */
    public static function products_exists_in_items( array $cart_items, array $products, $type = 'at_least_one_of', $number = 2 ) {
		if ( empty( $products ) ) {
			return true;
		}

		$products = array_map( 'WCCS_Helpers::maybe_get_exact_item_id', $products );

		if ( empty( $cart_items ) ) {
			return WCCS()->WCCS_Comparison->union_compare( $products, array(), $type, (int) $number );
		}

		$found_count = 0;
		foreach ( $products as $product ) {
			$found = false;
			foreach ( $cart_items as $cart_item ) {
				if ( $product == $cart_item['product_id'] || ( ! empty( $cart_item['variation_id'] ) && $product == $cart_item['variation_id'] ) ) {
					++$found_count;
					$found = true;
					break;
				}
			}

			if ( $found ) {
				if ( 'at_least_one_of' === $type ) {
					return true;
				} elseif ( 'at_least_number_of' === $type ) {
					if ( $found_count >= (int) $number ) {
						return true;
					}
				} elseif ( 'none_of' === $type ) {
					return false;
				}
			} elseif ( 'all_of' === $type || 'only' === $type ) {
				return false;
			}
		}

		if ( 'at_least_one_of' === $type || 'at_least_number_of' === $type ) {
			return false;
		} elseif ( 'none_of' === $type || 'all_of' === $type  ) {
			return true;
		} elseif ( 'only' === $type ) {
			foreach ( $cart_items as $cart_item ) {
				if ( ! in_array( $cart_item['product_id'], $products ) && ( empty( $cart_item['variation_id' ] ) || ! in_array( $cart_item['variation_id'], $products ) ) ) {
					return false;
				}
			}

			return true;
		}

		return false;
    }

    /**
     * Check is given categories exists inside given cart items.
     *
     * @since  4.0.0
     *
     * @param  array   $cart_items
	 * @param  array   $categories
	 * @param  string  $type
     * @param  integer $number
     *
     * @return boolean
     */
    public static function categories_exists_in_items( array $cart_items, array $categories, $type = 'at_least_one_of', $number = 2 ) {
		if ( empty( $categories ) ) {
			return true;
		}

		if ( empty( $cart_items ) ) {
			return WCCS()->WCCS_Comparison->union_compare( $categories, array(), $type, (int) $number );
		}

		$categories = array_map( 'WCCS_Helpers::maybe_get_exact_category_id', $categories );

		$cart_categories = array();

		foreach ( $cart_items as $item => $item_data ) {
			$product_categories = wc_get_product_cat_ids( $item_data['product_id'] );
			if ( 'at_least_one_of' === $type || 'none_of' === $type ) {
				if ( count( array_intersect( $categories, $product_categories ) ) ) {
					return 'at_least_one_of' === $type;
				}
			} else {
				$cart_categories = array_merge( $cart_categories, $product_categories );
			}
		}

		if ( 'at_least_one_of' === $type ) {
			return false;
		} elseif ( 'none_of' === $type ) {
			return true;
		}

		if ( ! empty( $cart_categories ) ) {
			return WCCS()->WCCS_Comparison->union_compare( $categories, $cart_categories, $type, (int) $number );
		}

		return false;
    }

	public static function get_product_ids( array $cart_items ) {
		if ( empty( $cart_items ) ) {
			return array();
		}

		$product_ids = array();
		foreach ( $cart_items as $cart_item ) {
			if ( $product_id = self::get_product_id( $cart_item ) ) {
				$product_ids[] = $product_id;
			}
		}
		return $product_ids;
	}

	public static function get_product_id( array $cart_item ) {
		if ( empty( $cart_item ) ) {
			return false;
		}

		$product_id = (int) $cart_item['product_id'];
		if ( isset( $cart_item['variation_id'] ) && 0 < (int) $cart_item['variation_id'] ) {
			$product_id = (int) $cart_item['variation_id'];
		}
		return $product_id;
	}

}
