<?php
namespace LWS\WOOREWARDS\PRO\Ui\Legacy;

// don't call the file directly
if( !defined( 'ABSPATH' ) ) exit();

class CouponsWidget extends \LWS\WOOREWARDS\Ui\Widget
{
	private $stygen = false;

	public static function install()
	{
		self::register(get_class());
		$me = new self(false);
		\add_shortcode('wr_shop_coupons', array($me, 'shortcode'));
		\add_filter('lws_adminpanel_stygen_content_get_'.'wc_shop_coupon', array($me, 'template'));
		\add_filter('lws_woorewards_coupon_content', array($me, 'getCouponContent'), 10, 2);
		\add_action('wp_enqueue_scripts', array($me, 'registerScripts'));
		\add_action('admin_enqueue_scripts', array($me, 'registerScripts'));
	}

	function registerScripts()
    {
		\wp_register_script('woorewards-wc-coupons', LWS_WOOREWARDS_PRO_JS . '/coupons.js', array('jquery'), LWS_WOOREWARDS_PRO_VERSION, true);
		\wp_register_style('woorewards-wc-coupons', LWS_WOOREWARDS_PRO_CSS.'/templates/coupons.css?stygen=lws_woorewards_wc_coupons_template', array(), LWS_WOOREWARDS_PRO_VERSION);
	}

	protected function enqueueScripts()
	{
		\wp_enqueue_script('woorewards-wc-coupons');
		\wp_enqueue_style('woorewards-wc-coupons');
	}

	/** Will be instanciated by WordPress at need */
	public function __construct($asWidget=true)
	{
		if( $asWidget )
		{
			parent::__construct(
				'lws_woorewards_wc_coupons',
				__("MyRewards Coupons", 'woorewards-pro'),
				array(
					'description' => __("Display WooCommerce Coupons owned by the customer.", 'woorewards-pro')
				)
			);
		}
	}

	/** ensure all required fields exist. */
	function update( $new_instance, $old_instance )
	{
		return \wp_parse_args(
			array_merge($old_instance, $new_instance),
			$this->defaultArgs()
		);
	}

	/** Widget parameters (admin) */
	public function form($instance)
	{
		$instance = \wp_parse_args($instance, $this->defaultArgs());

		// title
		$this->eFormFieldText(
			$this->get_field_id('title'),
			__("Title", 'woorewards-pro'),
			$this->get_field_name('title'),
			\esc_attr($instance['title']),
			\esc_attr(_x("Available Coupons", "frontend widget", 'woorewards-pro'))
		);
		// header
		$this->eFormFieldText(
			$this->get_field_id('header'),
			__("Header", 'woorewards-pro'),
			$this->get_field_name('header'),
			\esc_attr($instance['header']),
			\esc_attr(_x("Available Coupons", "frontend widget", 'woorewards-pro'))
		);
	}

	protected function defaultArgs()
	{
		return array(
			'title'  => '',
			'header'  => ''
		);
	}

	/**	Display the widget,
	 *	@see https://developer.wordpress.org/reference/classes/wp_widget/
	 * 	display parameters in $args
	 *	get option from $instance */
	public function widget($args, $instance)
	{
		$begin = "<div class='lws-wr-shop-coupon-cont'>";
		$end = "</div>";
		if( !empty($userId = \get_current_user_id()) )
		{
			echo $args['before_widget'];
			echo $args['before_title'];
			echo \apply_filters('widget_title', empty($instance['title']) ? _x("Available Coupons", "frontend widget", 'woorewards-pro') : $instance['title'], $instance);
			echo $args['after_title'];
			if( $coupons = \LWS\WOOREWARDS\PRO\Conveniences::instance()->getCoupons($userId) )
			{
				echo ($begin . $this->getHead($instance) . $this->getContent($coupons) . $end);
			}
			else
			{
				$emptyContent = \get_option('lws_wooreward_wc_coupons_empty', '');
				echo empty($emptyContent) ? __("No coupon.", 'woorewards-pro') : $emptyContent;
			}
			echo $args['after_widget'];
		}
	}

	function template($snippet)
	{
		$this->stygen = true;
		$coupons = array(
			(object)['post_title' => 'CODETEST1', 'post_excerpt' => _x("A fake coupon", "stygen", 'woorewards-pro')],
			(object)['post_title' => 'CODETEST2', 'post_excerpt' => _x("Another fake coupon", "stygen", 'woorewards-pro').' - '._x("valid for 7 days", "stygen", 'woorewards-pro')]
		);
		$begin = "<div class='lws-wr-shop-coupon-cont'>";
		$end = "</div>";
		$content = ($begin . $this->getHead($this->defaultArgs()) . $this->getContent($coupons, false, true) . $end);
		$this->stygen = false;
		return $content;
	}



	public function shortcode($atts=array(), $content='')
	{
		$atts = \wp_parse_args($atts, $this->defaultArgs());
		$begin = "<div class='lws-wr-shop-coupon-cont'>";
		$end = "</div>";
		if( empty($userId = \get_current_user_id()) )
			return $begin . \lws_get_option('lws_wooreward_wc_coupons_nouser',__("Please log in to see the coupons you have", 'woorewards-pro')) .$end;
		if( empty($coupons = \LWS\WOOREWARDS\PRO\Conveniences::instance()->getCoupons($userId)) )
			return $content;
		return $begin . $this->getHead($atts) . $this->getContent($coupons) . $end;
	}

	/** @param $coupons array a coupon list.
	 *	@param $tableId string DOM element id */
	public function getContent($coupons = array(), $tableId=false, $demo=false)
	{
		$content = '';
		foreach( $coupons as $coupon )
		{
			$code = \esc_attr($coupon->post_title);
			$content .= "<tr class='lwss_selectable lws-wr-shop-coupon-row' data-type='Row'>";
			$content .= "<td class='lwss_selectable lws-wr-shop-coupon-code' data-type='Coupon'>{$code}</td>";
			$descr = \apply_filters('lws_woorewards_coupon_content', $coupon->post_excerpt, $coupon);
			$content .= "<td class='lwss_selectable lws-wr-shop-coupon-description' data-type='Description'>{$descr}</td>";
			$content .= "</tr>";
		}

		if( !empty($content) )
		{
			$this->enqueueScripts();
			$content = "<table class='lwss_selectable lws-wr-shop-coupon-table' data-type='Table'>{$content}</table>";
		}
		return $content;
	}

	public function getHead($atts=array(), $id=false)
	{
		$id = empty($id) ? '' : " id='$id'";
		if( empty($atts['header']) )
			$atts['header'] = \lws_get_option('lws_woorewards_wc_coupons_template_head', __("Available Coupons", 'woorewards-pro'));
		if( !$this->stygen )
			$atts['header'] = \apply_filters('wpml_translate_single_string', $atts['header'], 'Widgets', "WooRewards - Coupons Widget - Header");
		return "<div class='lwss_selectable lwss_modify lws-wr-shop-coupon-head'$id data-id='lws_woorewards_wc_coupons_template_head' data-type='Header'><span class='lwss_modify_content'>{$atts['header']}</span></div>";
	}

	/** Backward compatibility: v2 does not set a proper text
	 * v3 add a meta 'reward_origin' to let us use the post_content.
	 * @brief Rewrite shop_coupon text on-the-fly.
	 * @param $content (string) the coupon content for display.
	 * @param $coupon the origin post. */
	function getCouponContent($content, $coupon)
	{
		$value = false;

		if( !empty($coupon) && isset($coupon->discount_type) && empty(\get_post_meta($coupon->ID, 'reward_origin', true)) )
		{
			if( $coupon->discount_type == 'fixed_cart' )
			{
				$value = \wc_price($coupon->coupon_amount);
				$content = sprintf(__("%s discount", 'woorewards-pro'), $value);
				if( \wc_prices_include_tax() )
				{
					$content .= (' <i>('. _x("including tax", "fixed cart coupon", 'woorewards-pro') . ')</i>');
				}
			}
			else if( $coupon->discount_type == 'percent' )
			{
				$value = trim(trim(\number_format_i18n($coupon->coupon_amount, 2), '0'), '.,') . '%';
				$content = sprintf(__("%s discount", 'woorewards-pro'), $value);
				if( !empty($coupon->product_ids) )
				{
					if( $coupon->coupon_amount == '100' )
						$content = __("Free product", 'woorewards-pro');
					foreach( explode(',', $coupon->product_ids) as $id )
					{
						$link = sprintf("<a target='_blank' href='%s'>%s</a>", \esc_attr(\get_permalink($id)), \get_the_title($id));
						if( $coupon->coupon_amount == '100' )
							$content .= (' ' . $link);
						else
							$content .= sprintf(__(" on %s", 'woorewards-pro'), $link);
					}
				}
			}
		}

		if( $value !== false && isset($coupon->expiry_date) && !empty($coupon->expiry_date) )
		{
			$content .= '<br/>';
			$content .= sprintf(
				__("Expiry on %s", 'woorewards-pro'),
				\date_i18n(\get_option('date_format'), $coupon->expiry_date)
			);
		}
		return $content;
	}
}
