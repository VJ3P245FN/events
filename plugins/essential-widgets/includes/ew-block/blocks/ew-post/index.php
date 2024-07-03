<?php

// Hook the post rendering to the block
if ( function_exists( 'register_block_type' ) ) :
	register_block_type(
		'ew-block/ew-post',
		array(
			'attributes'      => array(
				'title'       => array(
					'type'    => 'string',
					'default' => esc_html__( 'Posts', 'essential-widgets' ),
				),
				'post_type'   => array(
					'type'    => 'array',
					'default' => array( 'post' ),
					'items'   => array( 'type' => 'string' ),
				),
				'number'      => array(
					'type'    => 'number',
					'default' => 10,
				),
				'order'       => array(
					'type'    => 'string',
					'default' => 'desc',
				),
				'orderby'     => array(
					'type'    => 'string',
					'default' => 'date',
				),
				'show_date'   => array(
					'type'    => 'boolean',
					'default' => false,
				),
				'show_author' => array(
					'type'    => 'boolean',
					'default' => false,
				),
				'is_block'    => array(
					'type'    => 'boolean',
					'default' => true,
				),
			),
			'render_callback' => 'ew_post_render_shortcode',
		)
	);
endif;

if ( ! function_exists( 'ew_post_render_shortcode' ) ) :
	add_shortcode( 'ew-post', 'ew_post_render_shortcode' );
	function ew_post_render_shortcode( $atts ) {
		$instance['title']       = $atts['title'];
		$instance['post_type']   = $atts['post_type'];
		$instance['number']      = $atts['number'];
		$instance['show_date']   = $atts['show_date'];
		$instance['show_author'] = $atts['show_author'];
		$instance['order']       = $atts['order'];
		$instance['orderby']     = $atts['orderby'];
		$instance['is_block']    = $atts['is_block'];

		$ew_post = new EW_Posts();

		return $ew_post->shortcode( $instance );
	}
endif;

if ( ! function_exists( 'ew_post_list' ) ) :

	/**
	 * Get nav menus for select option via custom REST API!
	 *
	 * @return array|null Array of nav menus object with label and value pair, * or null if none.
	 */
	function ew_post_list() {
		$post_types = get_post_types(
			array(
				'public'       => true,
				'hierarchical' => false,
			),
			'objects'
		);

		$post_list = array();

		foreach ( $post_types as $post ) {
			$object        = new stdClass();
			$object->label = $post->labels->singular_name;
			$object->value = $post->name;
			$post_list[]   = $object;
		}

		return $post_list;
	}

	add_action(
		'rest_api_init',
		function () {
			register_rest_route(
				'ew-rest/v1',
				'ew-post-list',
				array(
					'methods'             => 'GET',
					'callback'            => 'ew_post_list',
					'permission_callback' => '__return_true', // for public use
				)
			);
		}
	);
endif;
