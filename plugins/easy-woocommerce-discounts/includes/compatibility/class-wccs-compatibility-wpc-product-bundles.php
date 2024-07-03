<?php
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WCCS_Compatibility_WPC_Product_Bundles {

    protected $loader;

    public function __construct( WCCS_Loader $loader ) {
        $this->loader = $loader;
    }

    public function init() {
        $this->loader->add_filter( 'wccs_cart_item_line_subtotal', $this, 'cart_item_subtotal', 10, 2 );
        $this->loader->add_filter( 'wccs_product_validator_is_valid_cart_item', $this, 'is_valid_cart_item', 100, 2 );
    }

    public function cart_item_subtotal( $subtotal, $cart_item ) {
        if ( ! empty( $subtotal ) ) {
            return $subtotal;
        }

        if ( isset( $cart_item['woosb_ids'], $cart_item['woosb_price'], $cart_item['woosb_fixed_price'] ) && ! $cart_item['woosb_fixed_price'] ) {
            return $cart_item['woosb_price'] * $cart_item['quantity'];
        }

        if ( isset( $cart_item['woosb_parent_id'], $cart_item['woosb_price'], $cart_item['woosb_fixed_price'] ) && $cart_item['woosb_fixed_price'] ) {
            return $cart_item['woosb_price'] * $cart_item['quantity'];
        }

        return $subtotal;
    }

    public function is_valid_cart_item( $valid, $cart_item ) {
        if ( isset( $cart_item['woosb_parent_id'] ) ) {
            $valid = false;
        }

        return apply_filters( 'wccs_compatibility_wpc_product_bundles_' . __FUNCTION__, $valid, $cart_item );
    }

}
