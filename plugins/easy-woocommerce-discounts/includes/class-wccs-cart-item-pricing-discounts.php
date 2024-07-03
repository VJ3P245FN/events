<?php
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WCCS_Cart_Item_Pricing_Discounts {

    public $item_id;

	public $item;

	public $product_id;

    public $variation_id;

    protected $cart;

    protected $pricing;

    protected $pricings;

    protected $pricing_cache;

    public function __construct( $cart_item_id, $cart_item, WCCS_Pricing $pricing, $cart = null, WCCS_Cart_Pricing_Cache $pricing_cache = null ) {
        $this->item_id               = $cart_item_id;
		$this->item                  = $cart_item;
        $this->pricing               = $pricing;
        $this->pricings              = $this->pricing->get_pricings();
        $this->product_id            = $cart_item['product_id'];
        $this->variation_id          = $cart_item['variation_id'];
        $this->cart                  = null !== $cart ? $cart : WCCS()->cart;
        $this->pricing_cache         = $pricing_cache;
	}

	public function get_discounts() {
		$discounts = $this->get_simple_discounts()
			+ $this->get_bulk_discounts();

		if ( ! empty( $discounts ) ) {
			usort( $discounts, array( WCCS()->WCCS_Sorting, 'sort_by_order_asc' ) );
			$discounts = $this->pricing->rules_filter->by_apply_mode( $discounts );
		}

		return $discounts;
	}

	public function get_pricings() {
		$pricings = $this->get_simple_pricings()
            + $this->get_bulk_pricings();

		if ( ! empty( $pricings ) ) {
			usort( $pricings, array( WCCS()->WCCS_Sorting, 'sort_by_order_asc' ) );
			$pricings = $this->pricing->rules_filter->by_apply_mode( $pricings );
		}

		return $pricings;
	}

	public function get_simple_discounts() {
		if ( empty( $this->pricings ) || empty( $this->pricings['simple'] ) ) {
			return apply_filters( 'wccs_cart_item_pricing_simple_discounts', array() );
        }

		$discounts = array();
		foreach ( $this->pricings['simple'] as $pricing_id => $pricing ) {
			if ( in_array( $pricing['discount_type'], array( 'percentage_fee', 'price_fee' ) ) ) {
				continue;
			} elseif ( ! WCCS()->WCCS_Product_Validator->is_valid_product( $pricing['items'], $this->product_id, $this->variation_id, ( ! empty( $this->item['variation'] ) ? $this->item['variation'] : array() ), $this->item ) ) {
				continue;
			} elseif ( ! empty( $pricing['exclude_items'] ) && WCCS()->WCCS_Product_Validator->is_valid_product( $pricing['exclude_items'], $this->product_id, $this->variation_id, ( ! empty( $this->item['variation'] ) ? $this->item['variation'] : array() ), $this->item ) ) {
				continue;
			}

			$discounts[ $pricing_id ] = array(
				'mode'                  => $pricing['mode'],
				'apply_mode'            => $pricing['apply_mode'],
				'order'                 => (int) $pricing['order'],
				'discount'              => (float) $pricing['discount'],
				'discount_type'         => $pricing['discount_type'],
				'date_time'             => $pricing['date_time'],
				'date_times_match_mode' => $pricing['date_times_match_mode'],
			);
        }

		return apply_filters( 'wccs_cart_item_pricing_simple_discounts', $discounts );
	}

	public function get_simple_pricings() {
		if ( empty( $this->pricings ) || empty( $this->pricings['simple'] ) ) {
			return apply_filters( 'wccs_cart_item_pricing_simple_pricings', array() );
        }

		$pricings = array();
		foreach ( $this->pricings['simple'] as $pricing_id => $pricing ) {
			if ( in_array( $pricing['discount_type'], array( 'percentage_fee', 'price_fee' ) ) ) {
				continue;
			} elseif ( ! WCCS()->WCCS_Product_Validator->is_valid_product( $pricing['items'], $this->product_id, $this->variation_id, ( ! empty( $this->item['variation'] ) ? $this->item['variation'] : array() ), $this->item ) ) {
				continue;
			} elseif ( ! empty( $pricing['exclude_items'] ) && WCCS()->WCCS_Product_Validator->is_valid_product( $pricing['exclude_items'], $this->product_id, $this->variation_id, ( ! empty( $this->item['variation'] ) ? $this->item['variation'] : array() ), $this->item ) ) {
				continue;
			}

			$pricings[ $pricing_id ] = array(
				'mode'                  => $pricing['mode'],
				'apply_mode'            => $pricing['apply_mode'],
				'order'                 => (int) $pricing['order'],
				'date_time'             => $pricing['date_time'],
				'date_times_match_mode' => $pricing['date_times_match_mode'],
			);
        }

		return apply_filters( 'wccs_cart_item_pricing_simple_pricings', $pricings );
    }

	public function get_bulk_discounts() {
		if ( empty( $this->pricings ) || empty( $this->pricings['bulk'] ) ) {
			return apply_filters( 'wccs_cart_item_pricing_bulk_discounts', array() );
        }

		$discounts = array();
		foreach ( $this->pricings['bulk'] as $pricing_id => $pricing ) {
			if ( empty( $pricing['quantities'] ) ) {
				continue;
			} elseif ( ! WCCS()->WCCS_Product_Validator->is_valid_product( $pricing['items'], $this->product_id, $this->variation_id, ( ! empty( $this->item['variation'] ) ? $this->item['variation'] : array() ), $this->item ) ) {
				continue;
			} elseif ( ! empty( $pricing['exclude_items'] ) && WCCS()->WCCS_Product_Validator->is_valid_product( $pricing['exclude_items'], $this->product_id, $this->variation_id, ( ! empty( $this->item['variation'] ) ? $this->item['variation'] : array() ), $this->item ) ) {
				continue;
			}

			$items_quantities = $this->cart->get_items_quantities( $pricing['items'], $pricing['quantity_based_on'], true );
			if ( empty( $items_quantities ) ) {
				continue;
			}

			$item_quantity = 0;

			if ( 'single_product' === $pricing['quantity_based_on'] ) {
				if ( isset( $items_quantities[ $this->product_id ] ) ) {
					$item_quantity += $items_quantities[ $this->product_id ]['count'];
				}
			}

			if ( $item_quantity > 0 ) {
				foreach ( $pricing['quantities'] as $quantity ) {
					if ( intval( $quantity['min'] ) <= $item_quantity && ( '' === $quantity['max'] || intval( $quantity['max'] ) >= $item_quantity ) ) {
						$discounts[ $pricing_id ] = array(
							'mode'                  => $pricing['mode'],
							'apply_mode'            => $pricing['apply_mode'],
							'order'                 => (int) $pricing['order'],
							'discount'              => (float) $quantity['discount'],
							'discount_type'         => $quantity['discount_type'],
							'date_time'             => $pricing['date_time'],
							'date_times_match_mode' => $pricing['date_times_match_mode'],
						);
						break;
					}
				}
			}
		}

		return apply_filters( 'wccs_cart_item_pricing_bulk_discounts', $discounts );
	}

	public function get_bulk_pricings() {
		if ( empty( $this->pricings ) || empty( $this->pricings['bulk'] ) ) {
			return apply_filters( 'wccs_cart_item_pricing_bulk_pricings', array() );
        }

		$pricings = array();
		foreach ( $this->pricings['bulk'] as $pricing_id => $pricing ) {
			if ( empty( $pricing['quantities'] ) ) {
				continue;
			} elseif ( ! WCCS()->WCCS_Product_Validator->is_valid_product( $pricing['items'], $this->product_id, $this->variation_id, ( ! empty( $this->item['variation'] ) ? $this->item['variation'] : array() ), $this->item ) ) {
				continue;
			} elseif ( ! empty( $pricing['exclude_items'] ) && WCCS()->WCCS_Product_Validator->is_valid_product( $pricing['exclude_items'], $this->product_id, $this->variation_id, ( ! empty( $this->item['variation'] ) ? $this->item['variation'] : array() ), $this->item ) ) {
				continue;
			}

			$pricings[ $pricing_id ] = array(
				'mode'                  => $pricing['mode'],
				'apply_mode'            => $pricing['apply_mode'],
				'order'                 => (int) $pricing['order'],
				'date_time'             => $pricing['date_time'],
				'date_times_match_mode' => $pricing['date_times_match_mode'],
			);
		}

		return apply_filters( 'wccs_cart_item_pricing_bulk_pricings', $pricings );
	}

}
