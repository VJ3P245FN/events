<?php

// Hook the post rendering to the block
if ( function_exists( 'register_block_type' ) ) :
	register_block_type(
		'ew-block/ew-page',
		array(
			'attributes'      => array(
				'title'        => array(
					'type'    => 'string',
					'default' => esc_html__( 'Pages', 'essential-widgets' ),
				),
				'post_type'    => array(
					'type'    => 'string',
					'default' => 'page',
				),
				'depth'        => array(
					'type'    => 'number',
					'default' => 0,
				),
				'number'       => array(
					'type'    => 'number',
					'default' => 10,
				),
				'offset'       => array(
					'type'    => 'string',
					'default' => '',
				),
				'child_of'     => array(
					'type'    => 'string',
					'default' => '',
				),
				'include'      => array(
					'type'    => 'string',
					'default' => '',
				),
				'exclude'      => array(
					'type'    => 'string',
					'default' => '',
				),
				'exclude_tree' => array(
					'type'    => 'string',
					'default' => '',
				),

				'meta_key'     => array(
					'type'    => 'string',
					'default' => '',
				),
				'meta_value'   => array(
					'type'    => 'string',
					'default' => '',
				),
				'authors'      => array(
					'type'    => 'string',
					'default' => '',
				),
				'link_before'  => array(
					'type'    => 'string',
					'default' => '',
				),
				'link_after'   => array(
					'type'    => 'string',
					'default' => '',
				),
				'show_date'    => array(
					'type'    => 'string',
					'default' => '',
				),
				'hierarchical' => array(
					'type'    => 'boolean',
					'default' => true,
				),
				'sort_column'  => array(
					'type'    => 'string',
					'default' => 'post_title',
				),
				'sort_order'   => array(
					'type'    => 'string',
					'default' => 'ASC',
				),
				'date_format'  => array(
					'type'    => 'string',
					'default' => '',
				),
				'is_block'     => array(
					'type'    => 'boolean',
					'default' => true,
				),
			),
			'render_callback' => 'ew_page_render_shortcode',
		)
	);
endif;

if ( ! function_exists( 'ew_page_render_shortcode' ) ) :
	add_shortcode( 'ew-page', 'ew_page_render_shortcode' );
	function ew_page_render_shortcode( $atts ) {
		$instance['title']        = $atts['title'];
		$instance['post_type']    = $atts['post_type'];
		$instance['depth']        = $atts['depth'];
		$instance['number']       = $atts['number'];
		$instance['offset']       = $atts['offset'];
		$instance['child_of']     = $atts['child_of'];
		$instance['include']      = $atts['include'];
		$instance['exclude']      = $atts['exclude'];
		$instance['exclude_tree'] = $atts['exclude_tree'];
		$instance['meta_key']     = $atts['meta_key'];
		$instance['meta_value']   = $atts['meta_value'];
		$instance['authors']      = $atts['authors'];
		$instance['link_before']  = $atts['link_before'];
		$instance['link_after']   = $atts['link_after'];
		$instance['show_date']    = $atts['show_date'];
		$instance['hierarchical'] = $atts['hierarchical'];
		$instance['sort_column']  = $atts['sort_column'];
		$instance['sort_order']   = $atts['sort_order'];
		$instance['date_format']  = $atts['date_format'];
		$instance['is_block']     = $atts['is_block'];

		$ew_page = new EW_Pages();

		return $ew_page->shortcode( $instance );
	}
endif;

if ( ! function_exists( 'ew_page_list' ) ) :

	/**
	 * Get nav menus for select option via custom REST API!
	 *
	 * @return array|null Array of nav menus object with label and value pair, * or null if none.
	 */
	function ew_page_list() {
		$post_types = get_post_types(
			array(
				'public'       => true,
				'hierarchical' => true,
			),
			'objects'
		);

		$page_list = array();

		foreach ( $post_types as $page ) {
			$object        = new stdClass();
			$object->label = $page->labels->singular_name;
			$object->value = $page->name;
			$page_list[]   = $object;
		}

		return $page_list;
	}

	add_action(
		'rest_api_init',
		function () {
			register_rest_route(
				'ew-rest/v1',
				'ew-page-list',
				array(
					'methods'             => 'GET',
					'callback'            => 'ew_page_list',
					'permission_callback' => '__return_true', // for public use
				)
			);
		}
	);
endif;
