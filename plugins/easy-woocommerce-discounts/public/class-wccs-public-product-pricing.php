<?php
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WCCS_Public_Product_Pricing extends WCCS_Public_Controller {

	protected $pricing;

	protected $apply_method;

	protected $simple_discounts;

	protected $bulk_pricings;

	protected $is_in_excludes;

	public $product;

	public $product_type;

	public $product_id;

	public $parent_id;

	public function __construct( $product, WCCS_Pricing $pricing, $apply_method = '' ) {
		if ( is_numeric( $product ) ) {
			$this->product = wc_get_product( $product );
		} else {
			$this->product = $product;
		}

		$wccs = WCCS();

		$this->product_type = $this->product->get_type();
		$this->product_id   = $this->product->get_id();
		$this->parent_id    = 'variation' === $this->product_type ? $wccs->product_helpers->get_parent_id( $this->product ) : $this->product_id;
		$this->pricing      = $pricing;
		$this->apply_method = ! empty( $apply_method ) ? $apply_method : $wccs->settings->get_setting( 'product_pricing_discount_apply_method', 'first' );
	}

	public function get_price_html( $price = '' ) {
		do_action( 'wccs_public_product_pricing_before_get_price_html', $this, $price );

		if ( 'variable' === $this->product_type ) {
			$product_discounted_price = WCCS()->product_helpers->wc_get_variation_prices( $this->product, true, false );
			if ( empty( $product_discounted_price['price'] ) ) {
				do_action( 'wccs_public_product_pricing_after_get_price_html', $this, $price );
				return $price;
			}

			$min_product_discounted_price = apply_filters(
				'wccs_public_product_pricing_get_price_html_min_variation_price',
				current( $product_discounted_price['price'] ),
				key( $product_discounted_price['price'] ),
				$price,
				$this
			);
			$max_product_discounted_price = apply_filters(
				'wccs_public_product_pricing_get_price_html_max_variation_price',
				end( $product_discounted_price['price'] ),
				key( $product_discounted_price['price'] ),
				$price,
				$this
			);

			$prices = WCCS()->product_helpers->wc_get_variation_prices( $this->product, true );
			if ( empty( $prices['regular_price'] ) ) {
				do_action( 'wccs_public_product_pricing_after_get_price_html', $this, $price );
				return $price;
			}

			$min_price = current( $prices['regular_price'] );
			$max_price = end( $prices['regular_price'] );

			if ( (float) $min_price == $min_product_discounted_price && (float) $max_price == $max_product_discounted_price ) {
				do_action( 'wccs_public_product_pricing_after_get_price_html', $this, $price );
				return $price;
			}

			if ( $min_price !== $max_price ) {
				$display_price = WCCS_Helpers::wc_format_price_range( $min_price, $max_price );
			} else {
				$display_price = wc_price( $min_price );
			}

			if ( $min_product_discounted_price !== $max_product_discounted_price ) {
				$discounted_price = WCCS_Helpers::wc_format_price_range( $min_product_discounted_price, $max_product_discounted_price );
			} else {
				$discounted_price = wc_price( $min_product_discounted_price );
			}

			if ( (float) $min_price > $min_product_discounted_price || (float) $max_price > $max_product_discounted_price ) {
				$discounted_price = '<del aria-hidden="true">' . $display_price . '</del> <ins>' . $discounted_price . '</ins>' . $this->product->get_price_suffix();
			} else {
				$discounted_price = $discounted_price . $this->product->get_price_suffix();
			}
		} else {
			$display_price            = WCCS()->product_helpers->wc_get_price_to_display( $this->product, $this->product->is_on_sale( 'edit' ) ? array( 'price' => WCCS()->product_helpers->wc_get_regular_price( $this->product ) ) : array() );
			$product_discounted_price = WCCS()->product_helpers->wc_get_price_to_display( $this->product, array(), false );
			if ( $product_discounted_price < 0 || $product_discounted_price == $display_price || false === $product_discounted_price ) {
				do_action( 'wccs_public_product_pricing_after_get_price_html', $this, $price );
				return $price;
			}

			if ( $product_discounted_price < $display_price ) {
				$discounted_price = wc_format_sale_price( wc_price( $display_price ), wc_price( $product_discounted_price ) ) . $this->product->get_price_suffix();
			} else {
				$discounted_price = wc_price( $product_discounted_price ) . $this->product->get_price_suffix();
			}
		}

		do_action( 'wccs_public_product_pricing_after_get_price_html', $this, $price );

		return apply_filters( 'wccs_product_pricing_get_price_html', $discounted_price, $this->product );
	}

	/**
	 * Getting price.
	 *
	 * @since  1.0.0
	 *
	 * @return float
	 */
	public function get_price() {
		if ( 'variable' === $this->product_type ) {
			return false;
		}

		if ( $this->is_in_exclude_rules() ) {
			return false;
		}

		// Fix #13 and using get_base_price instead of get_base_price_to_display that caused issues.
		$base_price     = $this->get_base_price();
		$adjusted_price = $this->apply_simple_discounts( $base_price );

		if ( $base_price != $adjusted_price ) {
			if ( apply_filters( 'wccs_public_product_pricing_apply_adjusted_price', true, $adjusted_price, $this->product ) ) {
				return $adjusted_price;
			}
		}

		return false;
	}

	public function get_base_price( $product = null ) {
		$product = null === $product ? $this->product : $product;

		do_action( 'wccs_public_product_pricing_before_get_base_price', $this );

		$base_price = (float) $product->get_price( 'edit' );
		if ( $product->is_on_sale( 'edit' ) ) {
			if ( 'regular_price' === WCCS()->settings->get_setting( 'on_sale_products_price', 'on_sale_price' ) ) {
				$base_price = (float) $product->get_regular_price( 'edit' );
			}
		}

		do_action( 'wccs_public_product_pricing_after_get_base_price', $this );

		return apply_filters(
			'wccs_public_product_pricing_' . __FUNCTION__,
			$base_price,
			$product,
			$this
		);
	}

	/**
	 * Getting product price based on given discount and discount_type.
	 *
	 * @since  1.0.0
	 *
	 * @param  $discount      float
	 * @param  $discount_type string
	 *
	 * @return string
	 */
	public function get_discounted_price( $discount, $discount_type ) {
		$discount = (float) $discount;
		if ( $discount <= 0 || empty( $discount_type ) ) {
			return WCCS()->product_helpers->wc_get_price_html( $this->product );
		}

		do_action( 'wccs_public_product_pricing_before_get_discounted_price', $discount, $discount_type, $this );

		if ( 'variable' === $this->product_type ) {
			$variation_ids = $this->product->get_visible_children();
			if ( empty( $variation_ids ) ) {
				do_action( 'wccs_public_product_pricing_after_get_discounted_price', $discount, $discount_type, $this );
				return WCCS()->product_helpers->wc_get_price_html( $this->product );
			}

			$variable_prices = array();
			foreach ( $variation_ids as $variation_id ) {
				$variation  = wc_get_product( $variation_id );
				$base_price = $this->get_base_price( $variation );
				if ( $base_price < 0 ) {
					continue;
				}

				$discount_amount = 0;
				if ( 'percentage_discount' === $discount_type ) {
					if ( $discount / 100 * $base_price > 0 ) {
						$discount_amount = $discount / 100 * $base_price;
					}
				} elseif ( 'price_discount' === $discount_type ) {
					if ( $discount > 0 ) {
						$discount_amount = $discount;
					}
				}

				$variation_price = WCCS()->product_helpers->wc_get_price_to_display( $variation );
				if ( $base_price - $discount_amount >= 0 ) {
					$variation_price = WCCS()->product_helpers->wc_get_price_to_display(
						$variation,
						array(
							'qty'   => 1,
							'price' => $base_price - $discount_amount
						)
					);
					$variation_price = WCCS_Helpers::maybe_exchange_price( $variation_price );
				}

				$variable_prices[ $variation_id ] = apply_filters(
					'wccs_public_product_pricing_get_discounted_price_variation',
					$variation_price,
					$variation_id,
					$variation,
					$discount,
					$discount_type,
					$this
				);
			}

			if ( ! empty( $variable_prices ) ) {
				$min_price = min( $variable_prices );
				$max_price = max( $variable_prices );

				if ( $min_price !== $max_price ) {
					$price = WCCS_Helpers::wc_format_price_range( $min_price, $max_price );
				} else {
					$price = wc_price( $min_price );
				}

				do_action( 'wccs_public_product_pricing_after_get_discounted_price', $discount, $discount_type, $this );

				return $price . $this->product->get_price_suffix( $price );
			}
		} // End if().
		// Simple and Variation product.
		else {
			$base_price      = $this->get_base_price();
			$discount_amount = 0;
			if ( 'percentage_discount' === $discount_type ) {
				if ( $discount / 100 * $base_price > 0 ) {
					$discount_amount = $discount / 100 * $base_price;
				}
			} elseif ( 'price_discount' === $discount_type ) {
				if ( $discount > 0 ) {
					$discount_amount = $discount;
				}
			}

			$price = WCCS()->product_helpers->wc_get_price_to_display( $this->product );
			if ( $base_price - $discount_amount >= 0 ) {
				$price = WCCS()->product_helpers->wc_get_price_to_display(
					$this->product,
					array(
						'qty'   => 1,
						'price' => $base_price - $discount_amount,
					)
				);
				$price = WCCS_Helpers::maybe_exchange_price( $price );
			}

			$price = apply_filters(
				'wccs_public_product_pricing_get_discounted_price_product',
				$price,
				$this->product,
				$discount,
				$discount_type,
				$this
			);

			do_action( 'wccs_public_product_pricing_after_get_discounted_price', $discount, $discount_type, $this );

			return wc_price( $price ) . $this->product->get_price_suffix( $price );
		}

		do_action( 'wccs_public_product_pricing_after_get_discounted_price', $discount, $discount_type, $this );

		return WCCS()->product_helpers->wc_get_price_html( $this->product );
	}

	/**
	 * Get discount value html.
	 *
	 * @since  2.8.0
	 *
	 * @param  float  $discount
	 * @param  string $discount_type
	 *
	 * @return string
	 */
	public function get_discount_value_html( $discount, $discount_type ) {
		$discount = (float) $discount;
		if ( $discount < 0 || empty( $discount_type ) ) {
			return apply_filters( 'wccs_product_pricing_discount_value_html', '' );
		}

		if ( 'percentage_discount' === $discount_type ) {
			return apply_filters( 'wccs_product_pricing_discount_value_html', $discount . '%' );
		} elseif ( 'price_discount' === $discount_type ) {
			return apply_filters( 'wccs_product_pricing_discount_value_html', wc_price( WCCS_Helpers::maybe_exchange_price( $discount ) ) );
		}

		return apply_filters( 'wccs_product_pricing_discount_value_html', '' );
	}

	public function bulk_pricing_table() {
		$bulks = $this->get_bulk_pricings();

		if ( ! empty( $bulks ) ) {
            $settings       = WCCS()->settings;
            $view           = $settings->get_setting( 'quantity_table_layout', 'bulk-pricing-table-vertical' );
			$exclude_rules  = $this->pricing->get_exclude_rules();
			$table_title    = __( 'Discount per Quantity', 'easy-woocommerce-discounts' );
			$price_label    = __( 'Price', 'easy-woocommerce-discounts' );
			$discount_label = __( 'Discount', 'easy-woocommerce-discounts' );
			$quantity_label = __( 'Quantity', 'easy-woocommerce-discounts' );
			if ( (int) $settings->get_setting( 'localization_enabled', 1 ) ) {
				$table_title    = $settings->get_setting( 'quantity_table_title', $table_title );
				$price_label    = $settings->get_setting( 'price_label', $price_label );
				$discount_label = $settings->get_setting( 'discount_label', $discount_label );
				$quantity_label = $settings->get_setting( 'quantity_label', $quantity_label );
			}

            $cache_args = array(
                'product_id'     => $this->product_id,
				'parent_id'      => $this->parent_id,
				'price_html'     => WCCS()->product_helpers->wc_get_price_html( $this->product ),
                'rules'          => $bulks,
                'exclude_rules'  => $exclude_rules,
                'view'           => $view,
                'table_title'    => $table_title,
                'quantity_label' => $quantity_label,
                'price_label'    => $price_label,
                'discount_label' => $discount_label,
                'variation'      => 'variation' === $this->product_type ? $this->product_id : '',
            );
            $cache = WCCS()->WCCS_Product_Quantity_Table_Cache->get_quantity_table( $cache_args );
            if ( false !== $cache ) {
                if ( ! empty( $cache ) ) {
                    echo apply_filters( 'wccs_product_pricing_bulk_pricing_table', $cache, $this );
                }
            } else {
				if ( $this->is_in_exclude_rules() ) {
					WCCS()->WCCS_Product_Quantity_Table_Cache->set_quantity_table( $cache_args, '' );
					return;
				}

				$table = '';
				foreach ( $bulks as $discount ) {
					ob_start();
					$this->render_view(
						"product-pricing.$view",
						array(
							'controller'     => $this,
							'discount'       => $discount,
							'table_title'    => $settings->get_setting( 'quantity_table_title', 'Discount per Quantity' ),
							'quantity_label' => $settings->get_setting( 'quantity_label', 'Quantity' ),
							'price_label'    => $settings->get_setting( 'price_label', 'Price' ),
							'discount_label' => $settings->get_setting( 'discount_label', 'Discount' ),
							'variation'      => 'variation' === $this->product_type ? $this->product_id : '',
						)
					);
					$table .= ob_get_clean();
				}

				WCCS()->WCCS_Product_Quantity_Table_Cache->set_quantity_table( $cache_args, $table );

				echo apply_filters( 'wccs_product_pricing_bulk_pricing_table', $table, $this );
            }
		}

		if ( 'variable' === $this->product_type ) {
			add_filter( 'woocommerce_show_variation_price', '__return_false', 100 );
			$variations = $this->product->get_available_variations();
			remove_filter( 'woocommerce_show_variation_price', '__return_false', 100 );
			if ( ! empty( $variations ) ) {
				foreach ( $variations as $variation ) {
					$variation_pricing = new WCCS_Public_Product_Pricing( $variation['variation_id'], $this->pricing, $this->apply_method );
					$variation_pricing->bulk_pricing_table();
				}
			}
		}
	}

	public function get_simple_discounts() {
		if ( isset( $this->simple_discounts ) ) {
			return $this->simple_discounts;
		}

		$simples = $this->pricing->get_simple_pricings();
		if ( empty( $simples ) ) {
			$this->simple_discounts = array();
			return array();
		}

		$discounts = array();

		foreach ( $simples as $pricing_id => $pricing ) {
			if ( in_array( $pricing['discount_type'], array( 'percentage_fee', 'price_fee' ) ) ) {
				continue;
			}

			if ( ! WCCS()->WCCS_Product_Validator->is_valid_product( $pricing['items'], $this->parent_id, ( 'variation' === $this->product_type ? $this->product_id : 0 ) ) ) {
				continue;
			}

			if ( ! empty( $pricing['exclude_items'] ) && WCCS()->WCCS_Product_Validator->is_valid_product( $pricing['exclude_items'], $this->parent_id, ( 'variation' === $this->product_type ? $this->product_id : 0 ) ) ) {
				continue;
			}

			$discounts[ $pricing_id ] = $pricing;
		}

		if ( ! empty( $discounts ) ) {
			usort( $discounts, array( WCCS()->WCCS_Sorting, 'sort_by_order_asc' ) );
			$discounts = $this->pricing->rules_filter->by_apply_mode( $discounts );
		}

		$this->simple_discounts = $discounts;
		return $discounts;
	}

	public function get_bulk_pricings() {
		if ( isset( $this->bulk_pricings ) ) {
			return $this->bulk_pricings;
		}

		$bulks = $this->pricing->get_bulk_pricings();
		if ( empty( $bulks ) ) {
			$this->bulk_pricings = array();
			return array();
		}

		$pricings = array();
		foreach ( $bulks as $pricing_id => $pricing ) {
			if ( ! WCCS()->WCCS_Product_Validator->is_valid_product( $pricing['items'], $this->parent_id, ( 'variation' === $this->product_type ? $this->product_id : 0 ), array() ) ) {
				continue;
			}

			if ( ! empty( $pricing['exclude_items'] ) && WCCS()->WCCS_Product_Validator->is_valid_product( $pricing['exclude_items'], $this->parent_id, ( 'variation' === $this->product_type ? $this->product_id : 0 ), array() ) ) {
				continue;
			}

			$pricings[ $pricing_id ] = $pricing;
		}

		if ( ! empty( $pricings ) ) {
			usort( $pricings, array( WCCS()->WCCS_Sorting, 'sort_by_order_asc' ) );
			$pricings = $this->pricing->rules_filter->by_apply_mode( $pricings );
		}

		$this->bulk_pricings = $pricings;
		return $pricings;
	}

	protected function apply_simple_discounts( $base_price ) {
		$discounts = $this->get_simple_discounts();
		if ( empty( $discounts ) ) {
			return $base_price;
		}

		// Get discount limit.
		$discount_limit = '';

		$discount_amounts = array();
		foreach ( $discounts as $discount ) {
			$discount_amount = false;
			if ( '' !== $discount_limit && 0 >= $discount_limit ) {
				break;
			}

			if ( 'percentage_discount' === $discount['discount_type'] ) {
				if ( (float) $discount['discount'] / 100 * $base_price > 0 ) {
					$discount_amount = (float) $discount['discount'] / 100 * $base_price;
					// Limit discount amount if limit exists.
					if ( '' !== $discount_limit && (float) $discount_amount > (float) $discount_limit ) {
						$discount_amount = (float) $discount_limit;
					}
				}
			} elseif ( 'price_discount' === $discount['discount_type'] ) {
				if ( (float) $discount['discount'] > 0 ) {
					$discount_amount = (float) $discount['discount'];
					// Limit discount amount if limit exists.
					if ( '' !== $discount_limit && (float) $discount_amount > (float) $discount_limit ) {
						$discount_amount = (float) $discount_limit;
					}
				}
			}

			if ( false !== $discount_amount ) {
				if ( '' !== $discount_limit ) {
					$discount_limit -= $discount_amount;
				}

				$discount_amounts[] = $discount_amount;
			}
		}

		if ( ! empty( $discount_amounts ) ) {
			$discount_amount = 0;
			if ( 'first' === $this->apply_method ) {
				$discount_amount = $discount_amounts[0];
			} elseif ( 'max' === $this->apply_method ) {
				$discount_amount = max( $discount_amounts );
			} elseif ( 'min' === $this->apply_method ) {
				$discount_amount = min( $discount_amounts );
			} elseif ( 'sum' === $this->apply_method ) {
				$discount_amount = array_sum( $discount_amounts );
			}

			if ( $base_price - $discount_amount >= 0 ) {
				return $base_price - $discount_amount;
			}
			return 0;
		}

		return $base_price;
	}

	protected function is_in_exclude_rules() {
		if ( isset( $this->is_in_excludes ) ) {
			return $this->is_in_excludes;
		}

		if ( $this->pricing->is_in_exclude_rules( $this->parent_id, ( 'variation' === $this->product_type ? $this->product_id : 0 ), array() ) ) {
			$this->is_in_excludes = true;
			return true;
		}

		$this->is_in_excludes = false;
		return false;
	}

}
