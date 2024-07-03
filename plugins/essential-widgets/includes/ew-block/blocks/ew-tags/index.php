<?php

// Set up the defaults.
$topic_count_text = _n_noop( '%s topic', '%s topics', 'essential-widgets' );

// Hook the post rendering to the block
if ( function_exists( 'register_block_type' ) ) :
	register_block_type(
		'ew-block/ew-tags',
		array(
			'attributes'      => array(
				'title'                      => array(
					'type'    => 'string',
					'default' => esc_html__( 'Tags', 'essential-widgets' ),
				),
				'order'                      => array(
					'type'    => 'string',
					'default' => 'DESC',
				),
				'orderby'                    => array(
					'type'    => 'string',
					'default' => 'name',
				),
				'format'                     => array(
					'type'    => 'string',
					'default' => 'flat',
				),
				'include'                    => array(
					'type'    => 'string',
					'default' => '',
				),
				'exclude'                    => array(
					'type'    => 'string',
					'default' => '',
				),
				'unit'                       => array(
					'type'    => 'string',
					'default' => 'flat',
				),
				'smallest'                   => array(
					'type'    => 'number',
					'default' => 8,
				),
				'largest'                    => array(
					'type'    => 'number',
					'default' => 22,
				),
				'number'                     => array(
					'type'    => 'number',
					'default' => 25,
				),
				'separator'                  => array(
					'type'    => 'string',
					'default' => ' ',
				),
				'child_of'                   => array(
					'type'    => 'string',
					'default' => '',
				),
				'parent'                     => array(
					'type'    => 'string',
					'default' => '',
				),
				'taxonomy'                   => array(
					'type'    => 'array',
					'default' => array( 'post_tag' ),
					'items'   => array( 'type' => 'string' ),
				),
				'hide_empty'                 => array(
					'type'    => 'boolean',
					'default' => true,
				),
				'show_count'                 => array(
					'type'    => 'boolean',
					'default' => false,
				),
				'pad_counts'                 => array(
					'type'    => 'boolean',
					'default' => false,
				),
				'search'                     => array(
					'type'    => 'string',
					'default' => '',
				),
				'name__like'                 => array(
					'type'    => 'string',
					'default' => '',
				),
				'single_text'                => array(
					'type'    => 'string',
					'default' => $topic_count_text['singular'],
				),
				'multiple_text'              => array(
					'type'    => 'string',
					'default' => $topic_count_text['plural'],
				),
				'topic_count_text_callback'  => array(
					'type'    => 'string',
					'default' => '',
				),
				'topic_count_scale_callback' => array(
					'type'    => 'string',
					'default' => 'default_topic_count_scale',
				),
				'is_block'                   => array(
					'type'    => 'boolean',
					'default' => true,
				),
			),
			'render_callback' => 'ew_tags_render_shortcode',
		)
	);
endif;

if ( ! function_exists( 'ew_tags_render_shortcode' ) && class_exists( 'EW_Tags' ) ) :
	add_shortcode( 'ew-tags', 'ew_tags_render_shortcode' );
	function ew_tags_render_shortcode( $atts ) {
		$instance['title']                      = $atts['title'];
		$instance['order']                      = $atts['order'];
		$instance['orderby']                    = $atts['orderby'];
		$instance['format']                     = $atts['format'];
		$instance['include']                    = $atts['include'];
		$instance['exclude']                    = $atts['exclude'];
		$instance['unit']                       = $atts['unit'];
		$instance['smallest']                   = $atts['smallest'];
		$instance['largest']                    = $atts['largest'];
		$instance['number']                     = $atts['number'];
		$instance['separator']                  = $atts['separator'];
		$instance['child_of']                   = $atts['child_of'];
		$instance['parent']                     = $atts['parent'];
		$instance['taxonomy']                   = $atts['taxonomy'];
		$instance['hide_empty']                 = $atts['hide_empty'];
		$instance['show_count']                 = $atts['show_count'];
		$instance['pad_counts']                 = $atts['pad_counts'];
		$instance['search']                     = $atts['search'];
		$instance['name__like']                 = $atts['name__like'];
		$instance['single_text']                = $atts['single_text'];
		$instance['multiple_text']              = $atts['multiple_text'];
		$instance['topic_count_text_callback']  = $atts['topic_count_text_callback'];
		$instance['topic_count_scale_callback'] = $atts['topic_count_scale_callback'];
		$instance['is_block']                   = $atts['is_block'];

		$ew_tags = new EW_Tags();

		return $ew_tags->shortcode( $instance );
	}
endif;
