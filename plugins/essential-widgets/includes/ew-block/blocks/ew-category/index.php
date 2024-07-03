<?php

// Hook the post rendering to the block
if ( function_exists( 'register_block_type' ) ) :
	register_block_type(
		'ew-block/ew-category',
		array(
			'attributes'      => array(
				'title'              => array(
					'type'    => 'string',
					'default' => esc_html__( 'Categories', 'essential-widgets' ),
				),
				'taxonomy'           => array(
					'type'    => 'string',
					'default' => 'category',
				),
				'style'              => array(
					'type'    => 'string',
					'default' => '',
				),
				'include'            => array(
					'type'    => 'string',
					'default' => '',
				),
				'exclude'            => array(
					'type'    => 'string',
					'default' => '',
				),
				'exclude_tree'       => array(
					'type'    => 'string',
					'default' => '',
				),
				'child_of'           => array(
					'type'    => 'string',
					'default' => '',
				),
				'current_category'   => array(
					'type'    => 'string',
					'default' => '',
				),
				'search'             => array(
					'type'    => 'string',
					'default' => '',
				),
				'hierarchical'       => array(
					'type'    => 'boolean',
					'default' => true,
				),
				'hide_empty'         => array(
					'type'    => 'boolean',
					'default' => true,
				),
				'order'              => array(
					'type'    => 'string',
					'default' => 'ASC',
				),
				'orderby'            => array(
					'type'    => 'string',
					'default' => 'name',
				),
				'depth'              => array(
					'type'    => 'number',
					'default' => 0,
				),
				'number'             => array(
					'type'    => 'number',
					'default' => 10,
				),
				'feed'               => array(
					'type'    => 'string',
					'default' => '',
				),
				'feed_type'          => array(
					'type'    => 'string',
					'default' => '',
				),
				'feed_image'         => array(
					'type'    => 'string',
					'default' => '',
				),
				'use_desc_for_title' => array(
					'type'    => 'boolean',
					'default' => false,
				),
				'show_count'         => array(
					'type'    => 'boolean',
					'default' => false,
				),
				'is_block'           => array(
					'type'    => 'boolean',
					'default' => true,
				),
			),
			'render_callback' => 'ew_category_render_shortcode',
		)
	);
endif;

if ( ! function_exists( 'ew_category_render_shortcode' ) ) :
	add_shortcode( 'ew-category', 'ew_category_render_shortcode' );
	function ew_category_render_shortcode( $atts ) {
		$instance['title']              = $atts['title'];
		$instance['taxonomy']           = $atts['taxonomy'];
		$instance['style']              = $atts['style'];
		$instance['include']            = $atts['include'];
		$instance['exclude']            = $atts['exclude'];
		$instance['exclude_tree']       = $atts['exclude_tree'];
		$instance['child_of']           = $atts['child_of'];
		$instance['current_category']   = $atts['current_category'];
		$instance['search']             = $atts['search'];
		$instance['hierarchical']       = $atts['hierarchical'];
		$instance['hide_empty']         = $atts['hide_empty'];
		$instance['order']              = $atts['order'];
		$instance['orderby']            = $atts['orderby'];
		$instance['depth']              = $atts['depth'];
		$instance['number']             = $atts['number'];
		$instance['feed']               = $atts['feed'];
		$instance['feed_type']          = $atts['feed_type'];
		$instance['feed_image']         = $atts['feed_image'];
		$instance['use_desc_for_title'] = $atts['use_desc_for_title'];
		$instance['show_count']         = $atts['show_count'];
		$instance['is_block']           = $atts['is_block'];

		$ew_category = new EW_Categories();

		return $ew_category->shortcode( $instance );
	}
endif;
