<?php
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WCCS_Product_Onsale_Cache extends WCCS_Abstract_Cache {

    protected $pricing;

    public function __construct( WCCS_Pricing $pricing = null ) {
        $this->pricing = null === $pricing ? WCCS()->pricing : $pricing;
        parent::__construct( 'wccs_product_onsale_', 'wccs_product_onsale' );
    }

    public function is_onsale( $product, $pricing_types ) {
        if ( ! $product || empty( $pricing_types ) ) {
            return false;
        }

        if ( ! empty( $pricing_types['simple'] ) ) {
            if ( $this->onsale_simple( $product ) ) {
                return true;
            }
        }

        if ( ! empty( $pricing_types['bulk'] ) ) {
            if ( $this->onsale_bulk( $product ) ) {
                return true;
            }
        }

        return false;
    }

    public function onsale_simple( $product ) {
        if ( ! $product ) {
            return;
        }

        $rules = $this->pricing->get_simple_pricings();
        if ( empty( $rules ) ) {
            return false;
        }

        return $this->get_onsale( $product, $rules, 'simple' );
    }

    public function onsale_bulk( $product ) {
        if ( ! $product ) {
            return;
        }

        $rules = $this->pricing->get_bulk_pricings();
        if ( empty( $rules ) ) {
            return false;
        }

        return $this->get_onsale( $product, $rules, 'bulk' );
    }

    protected function get_onsale( $product, $rules, $type ) {
        if ( ! $product || empty( $rules ) || empty( $type ) ) {
            return false;
        }

        // Check cache.
        $transient_name = $this->get_transient_name( array( 'product_id' => $product->get_id() ) );
        $transient_key  = md5( wp_json_encode(
            array(
                'type'          => $type,
                'rules'         => $rules,
                'exclude_rules' => $this->pricing->get_exclude_rules(),
            )
        ) );
        $onsale_transient = get_transient( $transient_name );
        $onsale_transient = false === $onsale_transient ? array() : $onsale_transient;
        if ( ! empty( $onsale_transient[ $transient_key ] ) ) {
            return 'yes' === $onsale_transient[ $transient_key ];
        }

        // Product should not inside exclude rules to have a sale badge.
        if ( $this->pricing->is_in_exclude_rules( $product->get_id(), 0, array() ) ) {
            $onsale_transient[ $transient_key ] = 'no';
            set_transient( $transient_name, $onsale_transient );
            return false;
        }

        $onsale = $this->check_rules( $rules, $product->get_id() );

        // if product is a variable product and one of its variations is onsale set product onsale badge to true.
        if ( ! $onsale && 'variable' === $product->get_type() ) {
            $varations = WCCS()->product_helpers->get_available_variations( $product );
            foreach ( $varations as $variation ) {
                // Checking variation not in exclude rules.
                if ( $this->pricing->is_in_exclude_rules( $product->get_id(), $variation['variation_id'] ) ) {
                    continue;
                }

                $onsale = $this->check_rules( $rules, $product->get_id(), $variation['variation_id'] );
                if ( $onsale ) {
                    break;
                }
            }
        }

        $onsale_transient[ $transient_key ] = $onsale ? 'yes' : 'no';
        set_transient( $transient_name, $onsale_transient );

        return $onsale;
    }

    protected function check_rules( $rules, $product_id, $variation_id = 0 ) {
        if ( empty( $rules ) || empty( $product_id ) ) {
            return false;
        }

        foreach ( $rules as $rule ) {
            if ( empty( $rule['mode'] ) ) {
                continue;
            }

            if ( 'products_group' !== $rule['mode'] && $this->check_rule( $rule, $product_id, $variation_id ) ) {
                return true;
            }
        }

        return false;
    }

    protected function check_rule( $rule, $product_id, $variation_id = 0 ) {
        if ( empty( $rule ) || empty( $product_id ) ) {
            return false;
        }

        if ( ! empty( $rule['mode'] ) && 'simple' === $rule['mode'] ) {
            if ( isset( $rule['discount_type'] ) && in_array( $rule['discount_type'], array( 'percentage_fee', 'price_fee' ) ) ) {
                return false;
            }
        }

        if ( ! WCCS()->WCCS_Product_Validator->is_valid_product( $rule['items'], $product_id, $variation_id ) ) {
            return false;
        }

        if ( ! empty( $rule['exclude_items'] ) && WCCS()->WCCS_Product_Validator->is_valid_product( $rule['exclude_items'], $product_id, $variation_id ) ) {
            return false;
        }

        return true;
    }

}
