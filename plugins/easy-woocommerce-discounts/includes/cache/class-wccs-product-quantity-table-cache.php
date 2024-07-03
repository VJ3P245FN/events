<?php
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WCCS_Product_Quantity_Table_Cache extends WCCS_Abstract_Cache {

    public function __construct() {
        parent::__construct( 'wccs_product_quantity_table_', 'wccs_product_quantity_table' );
    }

    public function get_quantity_table( array $args ) {
        if ( empty( $args ) ) {
            return false;
        }

        $transient_name = $this->get_transient_name( array( 'product_id' => $args['product_id'] ) );
        $transient_key  = md5( wp_json_encode( $args ) );
        $transient      = get_transient( $transient_name );
        $transient      = false === $transient ? array() : $transient;
        
        return isset( $transient[ $transient_key ] ) ? $transient[ $transient_key ] : false;
    }

    public function set_quantity_table( array $args, $table ) {
        if ( empty( $args ) ) {
            return false;
        }

        $transient_name = $this->get_transient_name( array( 'product_id' => $args['product_id'] ) );
        $transient_key  = md5( wp_json_encode( $args ) );
        $transient      = get_transient( $transient_name );
        $transient      = false === $transient ? array() : $transient;

        $transient[ $transient_key ] = $table;

        return set_transient( $transient_name, $transient );
    }

}
