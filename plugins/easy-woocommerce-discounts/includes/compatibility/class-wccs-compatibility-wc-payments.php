<?php
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use WCPay\MultiCurrency\MultiCurrency;

class WCCS_Compatibility_WC_Payments {

    protected $loader;

    public $multi_currency;

    public function __construct( WCCS_Loader $loader, MultiCurrency $multi_currency ) {
        $this->loader = $loader;
        $this->multi_currency = $multi_currency;
    }

    public function init() {
        if ( ! $this->multi_currency ) {
            return;
        }

        $enabled_currencies = $this->multi_currency->get_enabled_currencies();
        if ( 1 < count( $enabled_currencies ) ) {
            $this->loader->add_filter( 'wccs_maybe_exchange_price', $this, 'maybe_exchange_price', 10, 2 );
        }
    }

    public function maybe_exchange_price( $price, $type = 'product' ) {
        return $this->multi_currency->get_price( $price, $type );
    }

}
