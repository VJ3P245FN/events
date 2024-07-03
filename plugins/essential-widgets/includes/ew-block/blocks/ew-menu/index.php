<?php

// Hook the post rendering to the block
if ( function_exists( 'register_block_type' ) ) :
	register_block_type(
		'ew-block/ew-menu',
		array(
			'attributes'      => array(
				'title'           => array(
					'type'    => 'string',
					'default' => esc_html__( 'Navigation', 'essential-widgets' ),
				),
				'menu'            => array(
					'type'    => 'string',
					'default' => '',
				),
				'container'       => array(
					'type'    => 'string',
					'default' => 'div',
				),
				'container_id'    => array(
					'type'    => 'string',
					'default' => '',
				),
				'container_class' => array(
					'type'    => 'string',
					'default' => '',
				),
				'menu_id'         => array(
					'type'    => 'string',
					'default' => '',
				),
				'menu_class'      => array(
					'type'    => 'string',
					'default' => 'nav-menu',
				),
				'depth'           => array(
					'type'    => 'number',
					'default' => 0,
				),
				'before'          => array(
					'type'    => 'string',
					'default' => '',
				),
				'after'           => array(
					'type'    => 'string',
					'default' => '',
				),
				'link_before'     => array(
					'type'    => 'string',
					'default' => '',
				),
				'link_after'      => array(
					'type'    => 'string',
					'default' => '',
				),
				'fallback_cb'     => array(
					'type'    => 'string',
					'default' => 'wp_page_menu',
				),
				'is_block'        => array(
					'type'    => 'boolean',
					'default' => true,
				),

			),
			'render_callback' => 'ew_menu_render_shortcode',
		)
	);
endif;

if ( ! function_exists( 'ew_menu_render_shortcode' ) ) :
	add_shortcode( 'ew-menu', 'ew_menu_render_shortcode' );
	function ew_menu_render_shortcode( $atts ) {
		$instance['title']           = $atts['title'];
		$instance['menu']            = $atts['menu'];
		$instance['container']       = $atts['container'];
		$instance['container_id']    = $atts['container_id'];
		$instance['container_class'] = $atts['container_class'];
		$instance['menu_id']         = $atts['menu_id'];
		$instance['menu_class']      = $atts['menu_class'];
		$instance['depth']           = $atts['depth'];
		$instance['before']          = $atts['before'];
		$instance['after']           = $atts['after'];
		$instance['link_before']     = $atts['link_before'];
		$instance['link_after']      = $atts['link_after'];
		$instance['fallback_cb']     = $atts['fallback_cb'];
		$instance['is_block']        = $atts['is_block'];

		$ew_menu = new EW_Menus();

		return $ew_menu->shortcode( $instance );
	}
endif;


if ( ! function_exists( 'ew_menu_list' ) ) :
	/**
	 * Get nav menus for select option via custom REST API!
	 *
	 * @return array|null Array of nav menus object with label and value pair, * or null if none.
	 */
	function ew_menu_list() {
		$menus = wp_get_nav_menus();

		$menu_list = array();

		foreach ( $menus as $menu ) {
			$object        = new stdClass();
			$object->label = $menu->name;
			$object->value = $menu->term_id;
			$menu_list[]   = $object;
		}

		return $menu_list;
	}

	add_action(
		'rest_api_init',
		function () {
			register_rest_route(
				'ew-rest/v1',
				'ew-menu-list',
				array(
					'methods'             => 'GET',
					'callback'            => 'ew_menu_list',
					'permission_callback' => '__return_true', // for public use
				)
			);
		}
	);
endif;
