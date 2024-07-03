<?php
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WCCS_Admin_Select_Data_Provider {

	public function search_products( array $args = array() ) {
		if ( empty( $args['search'] ) ) {
			throw new Exception( 'Search term is required to search products.' );
		}

		$data_store = WC_Data_Store::load( 'product' );

		if ( WCCS_Helpers::wc_version_check( '3.5.0', '>=' ) && ! empty( $args['limit'] ) && 0 < (int) $args['limit'] ) {
			$products = $data_store->search_products( wc_clean( wp_unslash( $args['search'] ) ), '', false, true, (int) $args['limit'] );
		} else {
			$products = $data_store->search_products( wc_clean( wp_unslash( $args['search'] ) ), '', false, true );
		}

		$products = array_filter( $products );

		return ! empty( $products ) ? $this->prepare_product_select( $products ) : array();
	}

	public function get_products( array $args = array() ) {
		$args = wp_parse_args( $args, array( 'limit' => -1 ) );
		if ( empty( $args['include'] ) && empty( $args['post_id']  ) ) {
			return array();
		}

		$products = WCCS()->products->get_products( $args );
		if ( empty( $products ) ) {
			return array();
		}

		return $this->prepare_product_select( $products );
	}

	public function search_variations( array $args = array() ) {
		if ( empty( $args['search'] ) ) {
			throw new Exception( 'Search term is required to search products.' );
		}

		$data_store = WC_Data_Store::load( 'product' );

		if ( WCCS_Helpers::wc_version_check( '3.5.0', '>=' ) && ! empty( $args['limit'] ) && 0 < (int) $args['limit'] ) {
			$products = $data_store->search_products( wc_clean( wp_unslash( $args['search'] ) ), '', true, true, (int) $args['limit'] );
		} else {
			$products = $data_store->search_products( wc_clean( wp_unslash( $args['search'] ) ), '', true, true );
		}

		$products = array_filter( $products );

		return ! empty( $products ) ? $this->prepare_product_select( $products, array( 'variation' ) ) : array();
	}

	public function get_variations( array $args = array() ) {
		$args = wp_parse_args( $args, array( 'type' => 'variation', 'limit' => -1 ) );
		if ( empty( $args['include'] ) && empty( $args['post_id']  ) ) {
			return array();
		}

		$products = WCCS()->products->get_products( $args );
		if ( empty( $products ) ) {
			return array();
		}

		return $this->prepare_product_select( $products, array( 'variation' ) );
	}

	protected function prepare_product_select( array $products, $allowed_types = array() ) {
		$products_select = array();
		foreach ( $products as $product ) {
			if ( is_numeric( $product ) ) {
				$product = wc_get_product( $product );
			}
			if ( ! $product ) {
				continue;
			}

			if ( ! empty( $allowed_types ) && ! in_array( $product->get_type(), $allowed_types ) ) {
				continue;
			}

			$id = WCCS_Helpers::maybe_get_exact_item_id( $product->get_id() );
			if ( isset( $products_select[ $id ] ) ) {
				continue;
			}

			if ( $product->get_sku() ) {
				$identifier = $product->get_sku();
			} else {
				$identifier = '#' . $product->get_id();
			}

			if ( 'variation' === $product->get_type() ) {
				$formatted_variation_list = wc_get_formatted_variation( $product, true );
				$text = sprintf( '%2$s (%1$s)', $identifier, $product->get_title() ) . ' ' . $formatted_variation_list;
			} else {
				$text = sprintf( '%2$s (%1$s)', $identifier, $product->get_title() );
			}

			$products_select[ $id ] = (object) array(
				'id'   => $product->get_id(),
				'text' => $text,
			);
		}

		return array_values( $products_select );
	}

}
