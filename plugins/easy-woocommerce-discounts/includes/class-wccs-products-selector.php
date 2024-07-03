<?php
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WCCS_Products_Selector {

	protected $customer;

	protected $products;

	protected $product_helpers;

	protected $cart;

	public function __construct( $customer = null ) {
		$wccs                  = WCCS();
		$this->customer        = ! is_null( $customer ) ? new WCCS_Customer( $customer ) : new WCCS_Customer( wp_get_current_user() );
		$this->products        = $wccs->products;
		$this->product_helpers = $wccs->product_helpers;
		$this->cart            = $wccs->cart;
	}

	public function select_products( $items, $type = 'include' ) {
		$products = array(
			'include' => array(),
			'exclude' => array(),
		);

		if ( empty( $items ) ) {
			return $products;
		}

		$other_type = 'include' === $type ? 'exclude' : 'include';

		$products[ $other_type ] = array_unique( $products[ $other_type ] );

		foreach ( $items as $item ) {
			$limit = 12;
			if ( ! empty( $item['limit'] ) ) {
				$limit = intval( $item['limit'] ) > 0 ? intval( $item['limit'] ) : -1;
			}

			switch ( $item['item'] ) {
				case 'all_products':
					if ( 'include' === $type ) {
						$products[ $type ] = array( 'all_products' );
						return $products;
					}
					break;

				case 'products_in_list' :
					if ( ! empty( $item['products'] ) ) {
						$products[ $type ] = array_merge( $products[ $type ], $item['products'] );
					}
					break;

				default:
					break;
			}
		}

		$products[ $type ] = array_unique( $products[ $type ] );

		return $products;
	}

}
