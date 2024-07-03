<?php
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Easy WooCommerce Discounts compatibility with Product Add-Ons.
 *
 * @since 3.9.0
 */
class WCCS_Compatibility_Product_Addons {

    protected $loader;

    public function __construct( WCCS_Loader $loader ) {
        $this->loader = $loader;
    }

    public function init() {
        if ( 'product_price' === WCCS()->settings->get_setting( 'pricing_product_base_price', 'cart_item_price' ) ) {
            $this->loader->add_filter( 'wccs_cart_item_discounted_price', $this, 'cart_item_discounted_price', 10, 2 );
            $this->loader->add_filter( 'wccs_cart_item_main_price', $this, 'cart_item_main_price', 10, 2 );
            $this->loader->add_filter( 'wccs_cart_item_main_display_price', $this, 'cart_item_main_display_price', 10, 2 );
            $this->loader->add_filter( 'wccs_cart_item_before_discounted_price', $this, 'cart_item_before_discounted_price', 10, 2 );
            $this->loader->add_filter( 'wccs_cart_item_prices', $this, 'cart_item_prices', 10, 2 );
        }
    }

    public function cart_item_discounted_price( $discounted_price, $cart_item ) {
        if ( empty( $cart_item['addons'] ) ) {
            return $discounted_price;
        }

        return (float) $discounted_price + $this->get_addons_price( $cart_item, $discounted_price );
    }

    public function cart_item_main_price( $price, $cart_item ) {
        if ( empty( $cart_item['addons'] ) ) {
            return $price;
        }

        return (float) $price + $this->get_addons_price( $cart_item, (float) $price );
    }

    public function cart_item_main_display_price( $price, $cart_item ) {
        if ( empty( $cart_item['addons'] ) ) {
            return $price;
        }

        return (float) $price + $this->get_addons_price( $cart_item, (float) $price );
    }

    public function cart_item_before_discounted_price( $price, $cart_item ) {
        if ( empty( $cart_item['addons'] ) ) {
            return $price;
        }

        $product_price = (float) WCCS()->product_helpers->wc_get_price( $cart_item['data']->get_id() );
        return WCCS()->cart->get_product_price(
            $cart_item['data'],
            array(
                'price' => $product_price + $this->get_addons_price( $cart_item, $product_price ),
            )
        );
    }

    public function cart_item_prices( $prices, $cart_item ) {
        if ( empty( $cart_item['addons'] ) || empty( $prices ) ) {
            return $prices;
        }

        $product_price  = (float) WCCS()->product_helpers->wc_get_price( $cart_item['data']->get_id() );
        $options_prices = $this->get_addons_price( $cart_item, $product_price );
        if ( empty( $options_prices ) ) {
            return $prices;
        }

        $value = array();

        foreach ( $prices as $price => $qty ) {
            $price                    = (float) $price + $options_prices;
            $value[ (string) $price ] = $qty;
        }

        return $value;
    }

    public function get_addons_price( $cart_item_data, $product_price ) {
        if ( empty( $cart_item_data ) ) {
            return 0;
        }

        // Adapted from WC_Product_Addons_Cart->add_cart_item method.
        $price    = 0;
        $quantity = $cart_item_data['quantity'];
        foreach ( $cart_item_data['addons'] as $addon ) {
            $price_type  = $addon['price_type'];
            $addon_price = $addon['price'];

            switch ( $price_type ) {
                case 'percentage_based':
                    $price += (float) ( $product_price * ( $addon_price / 100 ) );
                    break;
                case 'flat_fee':
                    $price += (float) ( $addon_price / $quantity );
                    break;
                default:
                    $price += (float) $addon_price;
                    break;
            }
        }

        return (float) $price;
    }

}
