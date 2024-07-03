<?php

/**
 * Custom Archive Widget
 *
 * @package Essential_Widgets
 */


/**
 * Custom Archive Widget
 */
if ( ! class_exists( 'EW_Archives' ) ) :
	class EW_Archives extends WP_Widget {


		/**
		 * Holds widget settings defaults, populated in constructor.
		 *
		 * @var array
		 */
		protected $defaults;

		public function __construct() {
			// Set up defaults.
			$this->defaults = array(
				'title'           => esc_attr__( 'Archives', 'essential-widgets' ),
				'limit'           => 10,
				'type'            => 'monthly',
				'post_type'       => 'post',
				'order'           => 'DESC',
				'format'          => 'html',
				'before'          => '',
				'after'           => '',
				'show_post_count' => false,
			);

			$widget_ops = array(
				'classname'   => 'essential-widgets widget_archive ew-archive ewarchive',
				'description' => esc_html__( 'Displays a list of categories', 'essential-widgets' ),
			);

			$control_ops = array(
				'id_base' => 'ew-archive',
			);

			parent::__construct(
				'ew-archive', // Base ID
				__( 'EW: Archives', 'essential-widgets' ), // Name
				$widget_ops,
				$control_ops
			);
		}

		public function form( $instance ) {
			// Merge the user-selected arguments with the defaults.
			$instance = wp_parse_args( (array) $instance, $this->defaults );

			// Get post types.
			$post_types = get_post_types( array( 'name' => 'post' ), 'objects' );
			$post_types = array_merge(
				$post_types,
				get_post_types(
					array(
						'publicly_queryable' => true,
						'_builtin'           => false,
					),
					'objects'
				)
			);

			// Create an array of archive types.
			$type = array(
				'alpha'      => esc_attr__( 'Alphabetical', 'essential-widgets' ),
				'daily'      => esc_attr__( 'Daily', 'essential-widgets' ),
				'monthly'    => esc_attr__( 'Monthly', 'essential-widgets' ),
				'postbypost' => esc_attr__( 'Post By Post', 'essential-widgets' ),
				'weekly'     => esc_attr__( 'Weekly', 'essential-widgets' ),
				'yearly'     => esc_attr__( 'Yearly', 'essential-widgets' ),
			);

			// Create an array of order options.
			$order = array(
				'ASC'  => esc_attr__( 'Ascending', 'essential-widgets' ),
				'DESC' => esc_attr__( 'Descending', 'essential-widgets' ),
			);

			// Create an array of archive formats.
			$format = array(
				'custom' => esc_attr__( 'Plain', 'essential-widgets' ),
				'html'   => esc_attr__( 'List', 'essential-widgets' ),
				'option' => esc_attr__( 'Dropdown', 'essential-widgets' ),
			); ?>

			<p>
				<label>
					<?php esc_html_e( 'Title:', 'essential-widgets' ); ?>
					<input type="text" class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'title' ) ); ?>" value="<?php echo esc_attr( $instance['title'] ); ?>" placeholder="<?php echo esc_attr( $this->defaults['title'] ); ?>" />
				</label>
			</p>

			<p>
				<label>
					<?php esc_html_e( 'Limit:', 'essential-widgets' ); ?></label>
				<input type="number" class="widefat" size="5" min="0" name="<?php echo esc_attr( $this->get_field_name( 'limit' ) ); ?>" value="<?php echo esc_attr( $instance['limit'] ); ?>" placeholder="10" />
				</label>
			</p>

			<p>
				<label>
					<?php esc_html_e( 'Type:', 'essential-widgets' ); ?>

					<select class="widefat" name="<?php echo esc_attr( $this->get_field_name( 'type' ) ); ?>">

						<?php foreach ( $type as $option_value => $option_label ) : ?>

							<option value="<?php echo esc_attr( $option_value ); ?>" <?php selected( $instance['type'], $option_value ); ?>><?php echo esc_html( $option_label ); ?></option>

						<?php endforeach; ?>

					</select>
				</label>
			</p>

			<p>
				<label>
					<?php esc_html_e( 'Post Type:', 'essential-widgets' ); ?>

					<select class="widefat" name="<?php echo esc_attr( $this->get_field_name( 'post_type' ) ); ?>">

						<?php foreach ( $post_types as $post_type ) : ?>

							<option value="<?php echo esc_attr( $post_type->name ); ?>" <?php selected( $instance['post_type'], $post_type->name ); ?>><?php echo esc_html( $post_type->labels->singular_name ); ?></option>

						<?php endforeach; ?>

					</select>
				</label>
			</p>

			<p>
				<label>
					<?php esc_html_e( 'Order:', 'essential-widgets' ); ?>

					<select class="widefat" name="<?php echo esc_attr( $this->get_field_name( 'order' ) ); ?>">

						<?php foreach ( $order as $option_value => $option_label ) : ?>

							<option value="<?php echo esc_attr( $option_value ); ?>" <?php selected( $instance['order'], $option_value ); ?>><?php echo esc_html( $option_label ); ?></option>

						<?php endforeach; ?>

					</select>
				</label>
			</p>

			<p>
				<label>
					<?php esc_html_e( 'Display as:', 'essential-widgets' ); ?></label>

				<select class="widefat" name="<?php echo esc_attr( $this->get_field_name( 'format' ) ); ?>">

					<?php foreach ( $format as $option_value => $option_label ) : ?>

						<option value="<?php echo esc_attr( $option_value ); ?>" <?php selected( $instance['format'], $option_value ); ?>><?php echo esc_html( $option_label ); ?></option>

					<?php endforeach; ?>

				</select>
				</label>
			</p>

			<p>
				<label>
					<?php esc_html_e( 'Before:', 'essential-widgets' ); ?>
					<input type="text" class="widefat" name="<?php echo esc_attr( $this->get_field_name( 'before' ) ); ?>" value="<?php echo esc_attr( $instance['before'] ); ?>" />
				</label>
			</p>

			<p>
				<label>
					<?php esc_html_e( 'After:', 'essential-widgets' ); ?>
					<input type="text" class="widefat" name="<?php echo esc_attr( $this->get_field_name( 'after' ) ); ?>" value="<?php echo esc_attr( $instance['after'] ); ?>" />
				</label>
			</p>

			<p>
				<label>
					<input type="checkbox" <?php checked( $instance['show_post_count'], true ); ?> name="<?php echo esc_attr( $this->get_field_name( 'show_post_count' ) ); ?>" />
					<?php esc_html_e( 'Show post count?', 'essential-widgets' ); ?>
				</label>
			</p>
			<?php
		}

		public function update( $new_instance, $old_instance ) {
			// Sanitize title.
			$instance['title'] = sanitize_text_field( $new_instance['title'] );

			// Sanitize key.
			$instance['post_type'] = sanitize_key( $new_instance['post_type'] );

			// Whitelist options.
			$type   = array( 'alpha', 'daily', 'monthly', 'postbypost', 'weekly', 'yearly' );
			$order  = array( 'ASC', 'DESC' );
			$format = array( 'custom', 'html', 'option' );

			$instance['type']   = in_array( $new_instance['type'], $type ) ? $new_instance['type'] : 'monthly';
			$instance['order']  = in_array( $new_instance['order'], $order ) ? $new_instance['order'] : 'DESC';
			$instance['format'] = in_array( $new_instance['format'], $format ) ? $new_instance['format'] : 'html';

			// Integers.
			$instance['limit'] = intval( $new_instance['limit'] );
			$instance['limit'] = 0 === $instance['limit'] ? '' : $instance['limit'];

			// Text boxes. Make sure user can use 'unfiltered_html'.
			$instance['before'] = current_user_can( 'unfiltered_html' ) ? $new_instance['before'] : wp_kses_post( $new_instance['before'] );
			$instance['after']  = current_user_can( 'unfiltered_html' ) ? $new_instance['after'] : wp_kses_post( $new_instance['after'] );

			// Checkboxes.
			$instance['show_post_count'] = isset( $new_instance['show_post_count'] ) ? 1 : 0;

			// Return sanitized options.
			return $instance;
		}

		public function widget( $args, $instance ) {
			// Set the $args for wp_get_archives() to the $instance array.
			$instance = wp_parse_args( $instance, $this->defaults );

			// Output the sidebar's $before_widget wrapper.
			echo $args['before_widget'];

			// If a title was input by the user, display it.
			if ( ! empty( $instance['title'] ) ) {
				echo $args['before_title'] . apply_filters( 'widget_title', $instance['title'], $instance, $this->id_base ) . $args['after_title'];
			}

			echo $this->shortcode( $instance );

			// Close the sidebar's widget wrapper.
			echo $args['after_widget'];
		}

		public function shortcode( $atts ) {
			// Overwrite the $echo argument and set it to false.
			$atts['echo'] = false;
			$class        = '';

			// Get the archives list.
			$archives = str_replace( array( "\r", "\n", "\t" ), '', wp_get_archives( $atts ) );

			$archives = str_replace( '</a>&nbsp;(', '</a> <span>(', $archives );
			$archives = str_replace( ')', ')</span>', $archives );
			// return $archives;

			$dropdown_id = esc_attr( uniqid( 'wp-block-archives-' ) );

			$ew_archives = '';

			// Only show this title in block element and not on widget.
			if ( isset( $atts['is_block'] ) && true === $atts['is_block'] && !empty( $atts['title'] ) ) {
				$ew_archives .= '<h2 class="ew-archive-block-title">' . $atts['title'] . '</h2>';
			}

			// If the archives should be shown in a <select> drop-down.
			if ( 'option' == $atts['format'] ) {
				$class .= ' wp-block-archives-dropdown';

				switch ( $atts['type'] ) {
					case 'yearly':
						$label = __( 'Select Year' );
						break;
					case 'monthly':
						$label = __( 'Select Month' );
						break;
					case 'daily':
						$label = __( 'Select Day' );
						break;
					case 'weekly':
						$label = __( 'Select Week' );
						break;
					default:
						$label = __( 'Select Post' );
						break;
				}

				$label = esc_html( $label );

				$block_content = '<label class="screen-reader-text" for="' . $dropdown_id . '">' . $atts['title'] . '</label>
		<select id="' . $dropdown_id . '" name="archive-dropdown" onchange="document.location.href=this.options[this.selectedIndex].value;">
		<option value="">' . $label . '</option>' . $archives . '</select>';
				// return 'hello';
				$ew_archives .= sprintf(
					'<div class="%1$s">%2$s</div>',
					esc_attr( $class ),
					$block_content
				);

				return $ew_archives;

				// Output the <select> element and each <option>.
				/*
				 $ew_archive .= '<select name="archive-dropdown" onchange=\'document.location.href=this.options[this.selectedIndex].value;\'>';
				$ew_archive .= '<option value="">' . $option_title . '</option>';
				$ew_archive .= $archives;
				$ew_archive .= '</select>'; */
			}

			// If the format should be an unordered list.
			elseif ( 'html' == $atts['format'] ) {
				$ew_archives .= '<ul class="ew-archives">' . $archives . '</ul><!-- .xoxo .archives -->';
			}

			// All other formats.
			elseif ( 'custom' == $atts['format'] ) {
				$ew_archives .= $archives;
			}

			return $ew_archives;
		}
	} // end Archive_Widget class
endif;

if ( ! function_exists( 'ew_archives_register' ) ) :
	/**
	 * Intiate Archive_Widget Class.
	 *
	 * @since 1.0.0
	 */
	function ew_archives_register() {
		register_widget( 'EW_Archives' );
	}
	add_action( 'widgets_init', 'ew_archives_register' );
endif;
