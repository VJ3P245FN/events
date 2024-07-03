<?php
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WCCS_Products {

	protected $custom_taxonomies;

	public function get_products( array $args = array() ) {
		$args = wp_parse_args( $args, array(
			'status'         => array( 'draft', 'pending', 'private', 'publish' ),
			'type'           => array_merge( array_keys( wc_get_product_types() ) ),
			'parent'         => null,
			'sku'            => '',
			'category'       => array(),
			'tag'            => array(),
			'tag_tax_opts'   => array(
				'field'    => 'slug',
				'operator' => 'IN',
			),
			'limit'          => get_option( 'posts_per_page' ),
			'offset'         => null,
			'page'           => 1,
			'include'        => array(),
			'exclude'        => array(),
			'orderby'        => 'date',
			'order'          => 'DESC',
			'return'         => 'objects',
			'paginate'       => false,
			'shipping_class' => array(),
			'meta_query'     => array(),
			'tax_query'      => array(),
			'date_query'     => array(),
			'post_title'     => '',
			'post_id'        => '',
		) );

		/**
		 * Generate WP_Query args.
		 */
		$wp_query_args = array(
			'post_type'      => 'variation' === $args['type'] ? 'product_variation' : 'product',
			'post_status'    => $args['status'],
			'posts_per_page' => $args['limit'],
			'meta_query'     => $args['meta_query'],
			'orderby'        => $args['orderby'],
			'order'          => $args['order'],
			'tax_query'      => $args['tax_query'],
			'date_query'     => $args['date_query'],
		);
		// Do not load unnecessary post data if the user only wants IDs.
		if ( 'ids' === $args['return'] ) {
			$wp_query_args['fields'] = 'ids';
		}

		if ( 'variation' !== $args['type'] ) {
			$wp_query_args['tax_query'][] = array(
				'taxonomy' => 'product_type',
				'field'    => 'slug',
				'terms'    => $args['type'],
			);
		}

		if ( ! empty( $args['sku'] ) ) {
			$wp_query_args['meta_query'][] = array(
				'key'     => '_sku',
				'value'   => $args['sku'],
				'compare' => 'LIKE',
			);
		}

		if ( ! empty( $args['category'] ) ) {
			$wp_query_args['tax_query'][] = array(
				'taxonomy' => 'product_cat',
				'field'    => 'slug',
				'terms'   => $args['category'],
			);
		}

		if ( ! empty( $args['tag'] ) ) {
			$wp_query_args['tax_query'][] = array(
				'taxonomy' => 'product_tag',
				'field'    => isset( $args['tag_tax_opts']['field'] ) ? $args['tag_tax_opts']['field'] : 'slug',
				'terms'    => $args['tag'],
				'operator' => isset( $args['tag_tax_opts']['operator'] ) ? $args['tag_tax_opts']['operator'] : 'IN',
			);
		}

		if ( ! empty( $args['shipping_class'] ) ) {
			$wp_query_args['tax_query'][] = array(
				'taxonomy' => 'product_shipping_class',
				'field'    => 'slug',
				'terms'    => $args['shipping_class'],
			);
		}

		if ( ! is_null( $args['parent'] ) ) {
			$wp_query_args['post_parent'] = absint( $args['parent'] );
		}

		if ( ! is_null( $args['offset'] ) ) {
			$wp_query_args['offset'] = absint( $args['offset'] );
		} else {
			$wp_query_args['paged'] = absint( $args['page'] );
		}

		if ( ! empty( $args['include'] ) ) {
			$wp_query_args['post__in'] = array_map( 'absint', $args['include'] );
		}

		if ( ! empty( $args['exclude'] ) ) {
			$wp_query_args['post__not_in'] = array_map( 'absint', $args['exclude'] );
		}

		if ( ! $args['paginate'] ) {
			$wp_query_args['no_found_rows'] = true;
		}

		if ( ! empty( $args['meta_key'] ) ) {
			$wp_query_args['meta_key'] = $args['meta_key'];
		}

		if ( ! empty( $args['post_title'] ) ) {
			$wp_query_args['wccs_post_title'] = $args['post_title'];
		}

		if ( ! empty( $args['post_id'] ) ) {
			$wp_query_args['wccs_post_id'] = $args['post_id'];
		}

		$wp_query_args = apply_filters( 'wccs_products_query', $wp_query_args, $args );

		// Get results.
		$products = new WP_Query( $wp_query_args );

		if ( 'wp_query' === strtolower( $args['return'] ) ) {
			$return = $products;
		} elseif ( 'objects' === $args['return'] ) {
			$return = array_map( 'wc_get_product', $products->posts );
		} else {
			$return = $products->posts;
		}

		if ( $args['paginate'] ) {
			return (object) array(
				'products'      => $return,
				'total'         => $products->found_posts,
				'max_num_pages' => $products->max_num_pages,
			);
		} else {
			return $return;
		}
	}

	public function get_top_rated_products( $limit = 12, $return = 'ids' ) {
		return array();
	}

	public function get_recently_viewed_products( $limit = 12 ) {
		return array();
	}

	public function get_categories( array $args = array() ) {
		$defaults = array(
			'separator'          => '/',
			'nicename'           => false,
			'pad_counts'         => 1,
			'show_count'         => 1,
			'hierarchical'       => 1,
			'hide_empty'         => 0,
			'show_uncategorized' => 0,
			'orderby'            => 'name',
			'menu_order'         => false,
		);

		$args = wp_parse_args( $args, $defaults );

		if ( 'order' === $args['orderby'] ) {
			$args['menu_order'] = 'asc';
			$args['orderby']    = 'name';
		}

		$terms = get_terms( 'product_cat', apply_filters( 'wccs_wc_products_get_categories_args', $args ) );

		if ( empty( $terms ) ) {
			return array();
		}

		$categories = array();
		foreach ( $terms as $category ) {
			$categories[] = (object) array(
				'id'   => $category->term_id,
				'text' => rtrim( WCCS_Helpers::get_term_hierarchy_name( $category->term_id, 'product_cat', $args['separator'], $args['nicename'] ), $args['separator'] ),
				'slug' => $category->slug,
				'name' => $category->name,
			);
		}

		return $categories;
	}

	public function get_categories_not_in_list( array $categories ) {
		return array();
	}

	public function get_tags( array $args = array() ) {
		return array();
	}

	public function get_products_have_tags( array $tags, $have = 'at_least_one_of', $return_only_ids = true ) {
		return array();
	}

	public function get_categories_products( array $categories, $return_only_ids = true ) {
		if ( empty( $categories ) ) {
			return array();
		}

		$all_categories = $this->get_categories();

		$categories_slug = array();

		foreach ( $categories as $category ) {
			foreach ( $all_categories as $cat ) {
				if ( $category == $cat->id ) {
					$categories_slug[] = $cat->slug;
					break;
				}
			}
		}

		if ( empty( $categories_slug ) ) {
			return array();
		}

		$args = array(
			'status'   => 'publish',
			'category' => $categories_slug,
			'limit'    => -1,
		);

		if ( $return_only_ids ) {
			$args['return'] = 'ids';
		}

		$products = $this->get_products( $args );

		if ( empty( $products ) ) {
			return array();
		}

		return $products;
	}

	/**
	 * Get products by specified price value and type.
	 *
	 * @since  2.0.0
	 *
	 * @param  array $args
	 *
	 * @return array
	 */
	public function get_products_by_price( array $args ) {
		return array();
	}

	/**
	 * Get products by stock quantity value.
	 *
	 * @since  2.0.0
	 *
	 * @param  array $args
	 *
	 * @return array
	 */
	public function get_products_by_stock_quantity( array $args ) {
		return array();
	}

	/**
	 * Getting discounted products.
	 *
	 * @since  1.0.0
	 *
	 * @param  array $args
	 *
	 * @return array|string all_products string when all of products discounted.
	 */
	public function get_discounted_products( array $args = array() ) {
		return array();
	}

	/**
     * Get product custom taxonomies.
	 *
     * @return array|null
     */
    public function get_custom_taxonomies() {
        if ( isset( $this->custom_taxonomies ) ) {
            return $this->custom_taxonomies;
        }

        $taxonomies = get_taxonomies( array(
			'show_ui'      => true,
			'show_in_menu' => true,
			'object_type'  => array( 'product' ),
		), 'objects' );

		$this->custom_taxonomies = array();
		foreach ( (array) $taxonomies as $key => $taxonomy ) {
			if ( ! in_array( $taxonomy->name, array( 'product_cat', 'product_tag' ) ) ) {
				$this->custom_taxonomies[ $key ] = $taxonomy;
			}
		}

		$this->custom_taxonomies = apply_filters( 'wccs_product_helpers_' . __FUNCTION__, $this->custom_taxonomies );

		return $this->custom_taxonomies;
    }

}
