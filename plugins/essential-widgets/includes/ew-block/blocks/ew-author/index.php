<?php

// Hook the post rendering to the block
if ( function_exists( 'register_block_type' ) ) :
	register_block_type(
		'ew-block/ew-author',
		array(
			'attributes'      => array(
				'title'         => array(
					'type'    => 'string',
					'default' => esc_html__( 'Authors', 'essential-widgets' ),
				),
				'order'         => array(
					'type'    => 'string',
					'default' => 'ASC',
				),
				'orderby'       => array(
					'type'    => 'string',
					'default' => 'display_name',
				),
				'number'        => array(
					'type'    => 'number',
					'default' => 5,
				),
				'include'       => array(
					'type'    => 'string',
					'default' => '',
				),
				'exclude'       => array(
					'type'    => 'string',
					'default' => '',
				),
				'optioncount'   => array(
					'type'    => 'boolean',
					'default' => false,
				),
				'exclude_admin' => array(
					'type'    => 'boolean',
					'default' => false,
				),
				'show_fullname' => array(
					'type'    => 'boolean',
					'default' => false,
				),
				'hide_empty'    => array(
					'type'    => 'boolean',
					'default' => true,
				),
				'style'         => array(
					'type'    => 'string',
					'default' => 'list',
				),
				'html'          => array(
					'type'    => 'boolean',
					'default' => true,
				),
				'feed'          => array(
					'type'    => 'string',
					'default' => '',
				),
				'feed_type'     => array(
					'type'    => 'string',
					'default' => '',
				),
				'feed_image'    => array(
					'type'    => 'string',
					'default' => '',
				),
				'is_block'      => array(
					'type'    => 'boolean',
					'default' => true,
				),
			),
			'render_callback' => 'ew_author_render_shortcode',
		)
	);
endif;

if ( ! function_exists( 'ew_author_render_shortcode' ) ) :
	add_shortcode( 'ew-author', 'ew_author_render_shortcode' );
	function ew_author_render_shortcode( $atts ) {
		$instance['title']         = $atts['title'];
		$instance['order']         = $atts['order'];
		$instance['orderby']       = $atts['orderby'];
		$instance['number']        = $atts['number'];
		$instance['include']       = $atts['include'];
		$instance['exclude']       = $atts['exclude'];
		$instance['optioncount']   = $atts['optioncount'];
		$instance['exclude_admin'] = $atts['exclude_admin'];
		$instance['show_fullname'] = $atts['show_fullname'];
		$instance['hide_empty']    = $atts['hide_empty'];
		$instance['style']         = $atts['style'];
		$instance['html']          = $atts['html'];
		$instance['feed']          = $atts['feed'];
		$instance['feed_type']     = $atts['feed_type'];
		$instance['feed_image']    = $atts['feed_image'];
		$instance['is_block']      = $atts['is_block'];

		$ew_author = new EW_Authors();

		return $ew_author->shortcode( $instance );
	}
endif;
