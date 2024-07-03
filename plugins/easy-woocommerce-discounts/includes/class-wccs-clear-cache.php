<?php
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WCCS_Clear_Cache {

    /**
     * Enable hooks.
     *
     * @return void
     */
    public function enable_hooks() {
        add_action( 'woocommerce_update_product', array( &$this, 'delete_product_cache' ) );
        add_action( 'woocommerce_update_product_variation', array( &$this, 'delete_product_cache' ) );
        add_action( 'woocommerce_delete_product_transients', array( &$this, 'delete_product_cache' ) );
        add_action( 'woocommerce_settings_saved', array( &$this, 'clear_pricing_caches' ) );
    }

    /**
     * Clear pricing caches.
     *
     * @return void
     */
    public function clear_pricing_caches() {
        WCCS()->WCCS_Product_Price_Cache->clear_cache();
        WCCS()->WCCS_Product_Quantity_Table_Cache->clear_cache();
        WCCS()->WCCS_Product_Onsale_Cache->clear_cache();
    }

    /**
     * Clear a product cache.
     *
     * @param  int $product_id
     *
     * @return void
     */
    public function delete_product_cache( $product_id ) {
        WCCS()->WCCS_Product_Price_Cache->delete_transient( array( 'product_id' => $product_id ) );
        WCCS()->WCCS_Product_Quantity_Table_Cache->delete_transient( array( 'product_id' => $product_id ) );
        WCCS()->WCCS_Product_Onsale_Cache->delete_transient( array( 'product_id' => $product_id ) );
    }

}
