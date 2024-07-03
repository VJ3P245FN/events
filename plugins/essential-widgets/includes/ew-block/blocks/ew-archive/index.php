<?php

// Hook the post rendering to the block
if ( function_exists( 'register_block_type' ) ) :
	register_block_type(
		'ew-block/ew-archive',
		array(
			'attributes'      => array(
				'title'           => array(
					'type'    => 'string',
					'default' => 'Archives',
				),
				'limit'           => array(
					'type'    => 'number',
					'default' => 10,
				),
				'type'            => array(
					'type'    => 'string',
					'default' => 'monthly',
				),
				'post_type'       => array(
					'type'    => 'string',
					'default' => 'post',
				),
				'order'           => array(
					'type'    => 'string',
					'default' => 'asc',
				),
				'format'          => array(
					'type'    => 'string',
					'default' => 'html',
				),
				'before'          => array(
					'type'    => 'string',
					'default' => '',
				),
				'after'           => array(
					'type'    => 'string',
					'default' => '',
				),
				'show_post_count' => array(
					'type'    => 'boolean',
					'default' => false,
				),
				'is_block'        => array(
					'type'    => 'boolean',
					'default' => true,
				),
			),
			'render_callback' => 'ew_archive_render_shortcode',
		)
	);
endif;

if ( ! function_exists( 'ew_archive_render_shortcode' ) ) :
	add_shortcode( 'ew-archive', 'ew_archive_render_shortcode' );
	function ew_archive_render_shortcode( $atts ) {
		$instance['title']           = $atts['title'];
		$instance['limit']           = $atts['limit'];
		$instance['type']            = $atts['type'];
		$instance['post_type']       = $atts['post_type'];
		$instance['order']           = $atts['order'];
		$instance['format']          = $atts['format'];
		$instance['before']          = $atts['before'];
		$instance['after']           = $atts['after'];
		$instance['show_post_count'] = $atts['show_post_count'];
		$instance['is_block']        = $atts['is_block'];

		$ew_archive = new EW_Archives();

		return $ew_archive->shortcode( $instance );
	}
endif;
