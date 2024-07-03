<?php
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WCCS_Product_Price_Cache extends WCCS_Abstract_Cache {

    protected $pricing;

    protected $product_pricing;

    public function __construct( WCCS_Pricing $pricing = null ) {
        $this->pricing = null === $pricing ? WCCS()->pricing : $pricing;
        parent::__construct( 'wccs_product_price_', 'wccs_product_price' );
    }

    public function get_price( $product, $price, $price_type ) {
        $this->product_pricing = new WCCS_Public_Product_Pricing( $product, $this->pricing );

        $valid_rules = $this->get_valid_rules();
        if ( empty( $valid_rules ) ) {
            return $price;
        }

        $transient_name = $this->get_transient_name( array( 'product_id' => $this->product_pricing->product_id ) );
        $transient_key  = md5( wp_json_encode(
            array(
                'product_id'    => $this->product_pricing->product_id,
                'parent_id'     => $this->product_pricing->parent_id,
                'price'         => $price,
                'price_type'    => $price_type,
                'rules'         => $valid_rules,
                'exclude_rules' => $this->pricing->get_exclude_rules(),
            )
        ) );
        $transient     = get_transient( $transient_name );
        $transient     = false === $transient ? array() : $transient;

        /**
         * Fix compatibility issue with Improved Product Options for WooCommerce plugin.
         * https://codecanyon.net/item/improved-variable-product-attributes-for-woocommerce/9981757
         * Because it changes variable product to a simple product in the
         * XforWC_Improved_Options_Frontend::init_globals() method.
         */
        if ( ! is_array( $transient ) ) {
            return $price;
        }

        if ( ! isset( $transient[ $transient_key ] ) ) {
            $transient[ $transient_key ] = $this->product_pricing->get_price();
            set_transient( $transient_name, $transient );
        }

        if ( is_numeric( $transient[ $transient_key ] ) && 0 <= $transient[ $transient_key ] ) {
            return $transient[ $transient_key ];
        }

        // Note: Do not cast price to float that will causes issue for on sale tag of WooCommerce.
        return $price;
    }

    protected function get_valid_rules() {
        if ( ! $this->product_pricing ) {
            return array();
        }

        return $this->product_pricing->get_simple_discounts();
    }

}
