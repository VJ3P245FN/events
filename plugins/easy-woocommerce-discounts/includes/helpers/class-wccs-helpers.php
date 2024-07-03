<?php
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WCCS_Helpers {

	/**
	 * Checking a version against WooCommerce version.
	 *
	 * @since  1.1.0
	 *
	 * @param  string $version
	 * @param  string $operator
	 *
	 * @return boolean
	 */
	public static function wc_version_check( $version = '3.0', $operator = '>=' ) {
		return version_compare( WC_VERSION, $version, $operator );
	}

	/**
	 * Wrapper for wc_get_logger function.
	 *
	 * @since  1.1.0
	 *
	 * @return WC_Logger
	 */
	public static function wc_get_logger() {
		return self::wc_version_check() ? wc_get_logger() : new WC_Logger();
	}

	/**
	 * Format a price range for display.
	 * Wrapper method for WooCommerce wc_format_price_range function.
	 *
	 * @since  1.1.0
	 *
	 * @param  string $from Price from.
	 * @param  string $to   Price to.
	 *
	 * @return string
	 */
	public static function wc_format_price_range( $from, $to ) {
		if ( self::wc_version_check() ) {
			return wc_format_price_range( $from, $to );
		}

		/* translators: 1: price from 2: price to */
		$price = sprintf( _x( '%1$s &ndash; %2$s', 'Price range: from-to', 'woocommerce' ), is_numeric( $from ) ? wc_price( $from ) : $from, is_numeric( $to ) ? wc_price( $to ) : $to );
		return apply_filters( 'woocommerce_format_price_range', $price, $from, $to );
	}

	/**
	 * Checking is product in given include and exclude products.
	 *
	 * @since  1.1.0
	 *
	 * @param  array   $include
	 * @param  array   $exclude
	 * @param  integer $product_id
	 * @param  integer $variation_id
	 *
	 * @return boolean
	 */
	public static function is_product_in_items( $include, $exclude, $product_id, $variation_id = 0 ) {
		if ( empty( $include ) && empty( $exclude ) ) {
			return false;
		}

		if ( isset( $include['all_products'] ) || isset( $include['all_categories'] ) ) {
			if ( ! empty( $exclude ) ) {
				if ( isset( $exclude[ $product_id ] ) || ( 0 < (int) $variation_id && isset( $exclude[ $variation_id ] ) ) ) {
					return false;
				}
			}
			return true;
		} elseif ( isset( $include[ $product_id ] ) || ( 0 < (int) $variation_id && isset( $include[ $variation_id ] ) ) ) {
			if ( ! empty( $exclude ) ) {
				if ( isset( $exclude[ $product_id ] ) || ( 0 < (int) $variation_id && isset( $exclude[ $variation_id ] ) ) ) {
					return false;
				}
			}
			return true;
		} elseif ( empty( $include ) && ! empty( $exclude ) ) {
			if ( ! isset( $exclude[ $product_id ] ) && ( 0 >= (int) $variation_id || ! isset( $exclude[ $variation_id ] ) ) ) {
				return true;
			}
			return false;
		}

		return false;
	}

	/**
	 * Getting term hierarchy name.
	 *
	 * @since  2.0.0
	 *
	 * @param  int|WP_Term|object $term_id
	 * @param  string             $taxonomy
	 * @param  string             $separator
	 * @param  boolean            $nicename
	 * @param  array              $visited
	 *
	 * @return string
	 */
	public static function get_term_hierarchy_name( $term_id, $taxonomy, $separator = '/', $nicename = false, $visited = array() ) {
		$chain = '';
		$term = get_term( $term_id, $taxonomy );

		if ( is_wp_error( $term ) ) {
			return '';
		}

		$name = $term->name;
		if ( $nicename ) {
			$name = $term->slug;
		}

		if ( $term->parent && ( $term->parent != $term->term_id ) && ! in_array( $term->parent, $visited ) ) {
			$visited[] = $term->parent;
			$chain     .= self::get_term_hierarchy_name( $term->parent, $taxonomy, $separator, $nicename, $visited );
		}

		$chain .= $name . $separator;

		return $chain;
	}

	/**
	 * Get rounding precision for internal WC calculations.
	 * Will increase the precision of wc_get_price_decimals by 2 decimals, unless WC_ROUNDING_PRECISION is set to a higher number.
	 *
	 * @since  2.2.2
	 *
	 * @return int
	 */
	public static function wc_get_rounding_precision() {
		if ( function_exists( 'wc_get_rounding_precision' ) ) {
			return wc_get_rounding_precision();
		}

		$precision = wc_get_price_decimals() + 2;
		if ( absint( WC_ROUNDING_PRECISION ) > $precision ) {
			$precision = absint( WC_ROUNDING_PRECISION );
		}
		return $precision;
	}

	/**
	 * Add precision to a number and return a number.
	 *
	 * @since  2.2.2
	 *
	 * @param  float $value Number to add precision to.
	 * @param  bool  $round If should round after adding precision.
	 *
	 * @return int|float
	 */
	public static function wc_add_number_precision( $value, $round = true ) {
		if ( function_exists( 'wc_add_number_precision' ) ) {
			return wc_add_number_precision( $value, $round );
		}

		$cent_precision = pow( 10, wc_get_price_decimals() );
		$value          = $value * $cent_precision;
		return $round ? round( $value, self::wc_get_rounding_precision() - wc_get_price_decimals() ) : $value;
	}

	/**
	 * Remove precision from a number and return a float.
	 *
	 * @since  2.2.2
	 *
	 * @param  float $value Number to add precision to.
	 * @return float
	 */
	public static function wc_remove_number_precision( $value ) {
		if ( function_exists( 'wc_remove_number_precision' ) ) {
			return wc_remove_number_precision( $value );
		}

		$cent_precision = pow( 10, wc_get_price_decimals() );
		return $value / $cent_precision;
	}

	/**
	 * Add precision to an array of number and return an array of int.
	 *
	 * @since  2.2.2
	 *
	 * @param  array $value Number to add precision to.
	 * @param  bool  $round Should we round after adding precision?.
	 *
	 * @return int|array
	 */
	public static function wc_add_number_precision_deep( $value, $round = true ) {
		if ( function_exists( 'wc_add_number_precision_deep' ) ) {
			return wc_add_number_precision_deep( $value, $round );
		}

		if ( ! is_array( $value ) ) {
			return self::wc_add_number_precision( $value, $round );
		}

		foreach ( $value as $key => $sub_value ) {
			$value[ $key ] = self::wc_add_number_precision_deep( $sub_value, $round );
		}

		return $value;
	}

	/**
	 * Remove precision from an array of number and return an array of int.
	 *
	 * @since  5.2.0
	 * @param  array $value Number to add precision to.
	 * @return int|array
	 */
	public static function wc_remove_number_precision_deep( $value ) {
		if ( function_exists( 'wc_remove_number_precision_deep' ) ) {
			return wc_remove_number_precision_deep( $value );
		}

		if ( ! is_array( $value ) ) {
			return self::wc_remove_number_precision( $value );
		}

		foreach ( $value as $key => $sub_value ) {
			$value[ $key ] = self::wc_remove_number_precision_deep( $sub_value );
		}

		return $value;
	}

	/**
	 * Returns true if the request is a non-legacy REST API request.
	 *
	 * Legacy REST requests should still run some extra code for backwards compatibility.
	 *
	 * @todo: replace this function once core WP function is available: https://core.trac.wordpress.org/ticket/42061.
	 *
	 * @return bool
	 */
	public static function wc_is_rest_api_request() {
		if ( is_callable( array( WC(), 'is_rest_api_request' ) ) ) {
			return WC()->is_rest_api_request();
		}

		if ( empty( $_SERVER['REQUEST_URI'] ) ) {
			return false;
		}

		$rest_prefix         = trailingslashit( rest_get_url_prefix() );
		$is_rest_api_request = ( false !== strpos( $_SERVER['REQUEST_URI'], $rest_prefix ) ); // phpcs:disable WordPress.Security.ValidatedSanitizedInput.MissingUnslash, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

		return apply_filters( 'woocommerce_is_rest_api_request', $is_rest_api_request );
	}

	/**
	 * Round a number using the built-in `round` function, but unless the value to round is numeric
	 * (a number or a string that can be parsed as a number), apply 'floatval' first to it
	 * (so it will convert it to 0 in most cases).
	 *
	 * This is needed because in PHP 7 applying `round` to a non-numeric value returns 0,
	 * but in PHP 8 it throws an error. Specifically, in WooCommerce we have a few places where
	 * round('') is often executed.
	 *
	 * @since 5.2.0
	 *
	 * @param mixed $val The value to round.
	 * @param int   $precision The optional number of decimal digits to round to.
	 * @param int   $mode A constant to specify the mode in which rounding occurs.
	 *
	 * @return float The value rounded to the given precision as a float, or the supplied default value.
	 */
	public static function round( $val, $precision = 0, $mode = PHP_ROUND_HALF_UP ) {
		if ( ! is_numeric( $val ) ) {
			$val = floatval( $val );
		}
		return round( $val, $precision, $mode );
	}

	/**
	 * Maybe exchange price with multicurrency plugins.
	 *
	 * @param mixed  $price
	 * @param string $type
	 *
	 * @return mixed
	 */
	public static function maybe_exchange_price( $price, $type = 'product' ) {
		if ( empty( $price ) ) {
			return $price;
		}

		return apply_filters( 'wccs_maybe_exchange_price', $price, $type );
	}

	public static function should_change_display_price() {
		if ( 'none' === WCCS()->settings->get_setting( 'change_display_price', 'simple' ) ) {
			return false;
		}
		$simples = WCCS()->pricing->get_simple_pricings();
		return ! empty( $simples );
	}

	public static function should_change_display_price_html() {
		if ( 'simple' !== WCCS()->settings->get_setting( 'change_display_price', 'simple' ) ) {
			return false;
		}
		$simples = WCCS()->pricing->get_simple_pricings();
		return ! empty( $simples );
	}

	public static function maybe_get_exact_item_id( $id, $type = 'product' ) {
		if ( ! is_numeric( $id ) || 0 >= $id ) {
			return absint( $id );
		}

		return apply_filters( 'wccs_exact_item_id', absint( $id ), $type );
	}

	public static function maybe_get_exact_category_id( $id ) {
		return self::maybe_get_exact_item_id( $id, 'product_cat' );
	}

	public static function maybe_get_exact_tag_id( $id ) {
		return self::maybe_get_exact_item_id( $id, 'product_tag' );
	}

	public static function maybe_get_exact_product( $product ) {
		if ( ! $product ) {
			return $product;
		}

		if ( $product instanceof WC_Product ) {
			return apply_filters( 'wccs_exact_product', $product );
		} elseif ( is_numeric( $product ) ) {
			return self::maybe_get_exact_item_id( $product, 'product' );
		}

		return $product;
	}

	public static function is_product_page() {
		if ( is_product() ) {
			return true;
		}

		global $post;
		return ! empty( $post->post_content ) && false !== strpos( $post->post_content, '[product_page' );
	}

	public static function register_polyfills( $react = false ) {
		static $registered;
		if ( $registered ) {
			return;
		}

		global $wp_version;

		$handles = array(
			'wp-i18n'      => array( '6.0', array() ),
			'wp-hooks'     => array( '6.0', array() ),
			'wp-api-fetch' => array( '6.0', array() ),
			'moment'       => array( '2.29.4', array() ),
			'lodash'       => array( '4.17.21', array() ),
		);
		if ( $react ) {
			$handles['react']     = array( '17.0.2', array() );
			$handles['react-dom'] = array( '17.0.2', array( 'react' ) );
		}

		foreach ( $handles as $handle => $value ) {
			if ( ! version_compare( $wp_version, '5.9', '>=' ) && in_array( $handle, array( 'react', 'react-dom' ) ) ) {
				wp_deregister_script( $handle );
			}

			if ( ! wp_script_is( $handle, 'registered' ) ) {
				wp_register_script(
					$handle,
					plugins_url( 'admin/js/vendor/' . $handle . '.js', WCCS_PLUGIN_FILE ),
					$value[1],
					$value[0],
					true
				);
			}
		}

		$registered = true;
	}

	public static function get_review() {
		return get_option( 'asnp_ewd_review', array() );
	}

	public static function set_review( $review ) {
		return update_option( 'asnp_ewd_review', $review );
	}

	public static function maybe_show_review() {
		$review = self::get_review();
		if ( isset( $review['dismissed'] ) ) {
			return false;
		}

		if ( 0 >= WCCS()->conditions->count() ) {
			return false;
		}

		$schedule = strtotime( '+7 days' );
		if ( empty( $review['schedule'] ) ) {
			$review['schedule'] = $schedule;
			self::set_review( $review );
		} else {
			$schedule = (int) $review['schedule'];
		}

		if ( empty( $schedule ) || time() < $schedule ) {
			return false;
		}

		return true;
	}

	public static function get_ch() {
		return get_option( 'asnp_ewd_ch', array() );
	}

	public static function set_ch( $ch ) {
		return update_option( 'asnp_ewd_ch', $ch );
	}

	public static function maybe_show_ch() {
		$ch = self::get_ch();
		if ( isset( $ch['dismissed'] ) ) {
			return false;
		}

		if ( 0 >= WCCS()->conditions->count() ) {
			return false;
		}

		$schedule = strtotime( '+60 days' );
		if ( empty( $ch['schedule'] ) ) {
			$ch['schedule'] = $schedule;
			self::set_ch( $ch );
		} else {
			$schedule = (int) $ch['schedule'];
		}

		if ( empty( $schedule ) || time() < $schedule ) {
			return false;
		}

		if ( ! empty( $ch['time'] ) ) {
			if ( time() - $ch['time'] < DAY_IN_SECONDS * 30 ) {
				return false;
			}
		}

		$ch['time'] = time();
		self::set_ch( $ch );

		return true;
	}

}
