<?php
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WCCS_Discounted_Products_Selector {

	protected $customer;

	protected $product_validator;

	protected $selected_products = array();

	protected $orders = array(
		'all_products' => '0',
		'products_in_list' => '1'
	);

	public function __construct( $customer = null ) {
		$this->customer          = ! is_null( $customer ) ? new WCCS_Customer( $customer ) : new WCCS_Customer( wp_get_current_user() );
		$this->product_validator = WCCS()->WCCS_Product_Validator;
	}

	public function get_products( array $items ) {
		$items = $this->sort_items( $items );
		if ( empty( $items ) ) {
			return array();
		}

		$this->selected_products = array();
		foreach ( $items as $item ) {
			if ( method_exists( $this, $item['item'] ) ) {
				$products = $this->{$item['item']}( $item );
				if ( empty( $products ) ) {
					return array();
				}
			}
		}

		return $this->selected_products;
	}

	protected function sort_items( array $items ) {
		if ( empty( $items ) ) {
			return array();
		}

		$ret_items = array();
		foreach ( $items as $item ) {
			if ( isset( $this->orders[ $item['item'] ] ) ) {
				$ret_items[ $this->orders[ $item['item'] ] ] = $item;
			}
		}

		if ( ! empty( $ret_items ) ) {
			ksort( $ret_items );
		}

		return $ret_items;
	}

	protected function all_products( $item ) {
		return $this->selected_products = WCCS()->products->get_products(
			array(
				'status' => 'publish',
				'limit'  => -1,
				'return' => 'ids',
			)
		);
	}

	protected function products_in_list( $item ) {
		if ( empty( $item['products'] ) ) {
			return true;
		}

		return $this->selected_products = array_merge( $this->selected_products, $item['products'] );
	}

}
