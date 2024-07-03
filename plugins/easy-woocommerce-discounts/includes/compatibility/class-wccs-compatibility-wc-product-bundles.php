<?php
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WCCS_Compatibility_WC_Product_Bundles {

    protected $loader;

    public function __construct( WCCS_Loader $loader ) {
        $this->loader = $loader;
    }

    public function init() {
        $this->loader->add_filter( 'wccs_product_validator_is_valid_cart_item', $this, 'is_valid_cart_item', 100, 2 );
    }

    public function is_valid_cart_item( $valid, $cart_item ) {
        if ( $bundle_container_item = wc_pb_get_bundled_cart_item_container( $cart_item ) ) {
            $bundle          = $bundle_container_item['data'];
            $bundled_item_id = $cart_item['bundled_item_id'];
            if ( ! empty( $bundled_item_id ) ) {
                $bundled_item = $bundle->get_bundled_item( $bundled_item_id );
                if ( false === $bundled_item->is_priced_individually() ) {
                    $valid = false;
                }
            }
        }

        return apply_filters( 'wccs_compatibility_wc_product_bundles_' . __FUNCTION__, $valid, $cart_item );
    }

}
