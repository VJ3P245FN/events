<?php

namespace LWS\WOOREWARDS\PRO\Ui\Legacy;

// don't call the file directly
if (!defined('ABSPATH')) exit();

/** Compute a earning point estimation and insert it in the cart page. */
class CartCouponsView
{
	const POS_ASIDE  = 'cart_collaterals';
	const POS_INSIDE = 'middle_of_cart';
	const POS_AFTER  = 'on'; // bottom_of_cart
	const POS_NONE   = 'not_displayed';

	private $position = '';
	private $stygen   = false;

	static function register()
	{
		$me = new self(\lws_get_option('lws_woorewards_cart_collaterals_coupons', false));
		\add_filter('lws_adminpanel_stygen_content_get_' . 'cartcouponsview', array($me, 'template'));
		\add_shortcode('wr_cart_coupons_view', array($me, 'shortcode'));
		\add_action('wp_enqueue_scripts', array($me, 'registerScripts'));
		\add_action('admin_enqueue_scripts', array($me, 'registerScripts'));
	}

	function registerScripts()
	{
		\wp_register_style('lws_wre_cart_coupons_view', LWS_WOOREWARDS_PRO_CSS . '/templates/cartcouponsview.css?stygen=lws_wre_cart_coupons_view', array(), LWS_WOOREWARDS_PRO_VERSION);
	}

	/** style/scripts registered by \LWS\WOOREWARDS\PRO\Ui\Widgets\CouponsWidget */
	protected function enqueueScripts()
	{
		\wp_enqueue_style('lws_wre_cart_coupons_view');
		\wp_enqueue_script('woorewards-wc-coupons');
		\wp_enqueue_style('woorewards-wc-coupons');
	}

	function __construct($position = self::POS_NONE)
	{
		if ($position) {
			$this->position = $position;
			if (!empty($hook = $this->getHook($position)))
				\add_action($hook, array($this, 'display'));
		}
	}

	protected function getHook($position)
	{
		if ($position == self::POS_ASIDE)
			return 'woocommerce_cart_collaterals';
		else if ($position == self::POS_INSIDE)
			return 'woocommerce_after_cart_table';
		else if ($position == self::POS_AFTER)
			return 'woocommerce_after_cart';
		else
			return false;
	}

	function template($snippet)
	{
		$this->stygen = true;
		$coupons = array(
			(object)['post_title' => 'CODETEST1', 'post_excerpt' => _x("A fake coupon", "stygen", 'woorewards-pro')],
			(object)['post_title' => 'CODETEST2', 'post_excerpt' => _x("Another fake coupon", "stygen", 'woorewards-pro')]
		);
		$snippet = "<div class='lwss_selectable lws-wre-cartcouponsview-main woocommerce' data-type='Main Div'>";
		$snippet .= $this->getHead();
		$snippet .= $this->getContent($coupons, false, true);
		$snippet .= "</div>";
		$this->stygen = false;
		return $snippet;
	}

	/** @return false if all available coupons are already in cart.
	 * if WC is not active, return false too. */
	protected function isCouponAvailableForAdd(&$coupons)
	{
		if (!$coupons)
			return false;

		if (\LWS\Adminpanel\Tools\Conveniences::isWC()) {
			$wc = \WC();
			if (isset($wc->cart) && $wc->cart) {
				$done = array_keys($wc->cart->get_coupons());
				foreach ($coupons as $coupon) {
					if (!in_array(strtolower($coupon->post_title), $done)) {
						return true;
					}
				}
			}
		}

		return false;
	}

	function shortcode()
	{
		if (!\LWS\Adminpanel\Tools\Conveniences::isWC())
			return '';
		if (!\wc_coupons_enabled())
			return '';
		if (empty($userId = \get_current_user_id()))
			return '';
		if (empty($coupons = \LWS\WOOREWARDS\PRO\Conveniences::instance()->getCoupons($userId)))
			return '';

		// at least one coupon is available
		$hidden = $this->isCouponAvailableForAdd($coupons) ? '' : " style='display:none'";

		$wcClass = '';
		if ($this->position && $this->position == self::POS_ASIDE)
			$wcClass = " cross-sells";

		$html = "<div class='lwss_selectable lws-wre-cartcouponsview-main woocommerce $wcClass'$hidden data-type='Main Div'>";
		$html .= $this->getHead();
		$html .= $this->getContent($coupons, 'lws_woorewards_coupons');
		$html .= "</div>";
		return $html;
	}

	function display()
	{
		echo $this->shortcode();
	}

	public function getHead($id = false)
	{
		$id = empty($id) ? '' : " id='$id'";
		$stygenId = 'lws_woorewards_title_cart_coupons_view';
		$title = \lws_get_option($stygenId, _x("Available Coupons", "Table content", 'woorewards-pro'));
		if (!$this->stygen)
			$title = \apply_filters('wpml_translate_single_string', $title, 'Widgets', "WooRewards - Coupons - Title");
		$str = "<h2 class='lwss_selectable lwss_modify lws-wr-shop-coupon-title'$id data-id='{$stygenId}' data-type='Title'>";
		$str .= "<span class='lwss_modify_content'>{$title}</span>";
		$str .= "</h2>";
		return $str;
	}

	/** @param $coupons array a coupon list.
	 *	@param $tableId string DOM element id */
	public function getContent($coupons = array(), $tableId = false, $demo = false)
	{
		if (!function_exists('\wc'))
			return '';
		if (!$demo && !\wc_coupons_enabled())
			return '';
		if (!$demo && !\wc()->cart)
			return '';

		$titles = array(
			'code'			  => \esc_attr(__("Coupon Code", 'woorewards-pro')),
			'description'	=> \esc_attr(__("Description", 'woorewards-pro')),
			'apply' 		  => \esc_attr(__("Apply", 'woorewards-pro')),
		);
		if (!$this->stygen)
			$this->enqueueScripts();

		$btnText = \lws_get_option('lws_woorewards_cart_coupons_button', __("Use Coupon", 'woorewards-pro'));
		if (!$this->stygen)
			$btnText = \apply_filters('wpml_translate_single_string', $btnText, 'Widgets', "WooRewards - Coupons - Button");

		$reloadNonce = false;
		if (!$demo && !empty(\get_option('lws_woorewards_apply_coupon_by_reload', ''))) {
			$nonce = \esc_attr(urlencode(\wp_create_nonce('wr_apply_coupon')));
			$reloadNonce = " data-reload='wrac_n={$nonce}&wrac_c=%s'";
		}
		$done = (\LWS\Adminpanel\Tools\Conveniences::isWC() && !empty($tableId)) ? array_keys(\WC()->cart->get_coupons()) : array();

		$content = '';
		foreach ($coupons as $coupon) {
			$code = \esc_attr($coupon->post_title);
			$css = $demo ? '' : \esc_attr(' coupon-' . str_replace(' ', '-', strtolower($code)));
			$hidden = in_array(strtolower($coupon->post_title), $done) ? " style='display:none;'" : '';
			$content .= "<tr class='lwss_selectable lws_wr_cart_coupon_row$css' data-type='Row'$hidden>";
			$content .= "<td class='lwss_selectable lws-wr-cart-coupon-code' data-title='{$titles['code']}' data-type='Coupon code column'>{$coupon->post_title}</td>";
			$descr = \apply_filters('lws_woorewards_coupon_content', $coupon->post_excerpt, $coupon);
			$content .= "<td class='lwss_selectable lws-wr-cart-coupon-description' data-title='{$titles['description']}' data-type='Description column'>{$descr}</td>";
			if ($demo || !empty($tableId)) {
				$content .= "<td class='lwss_selectable lws-wr-cart-coupon-button' data-title='{$titles['apply']}' data-type='Button column'>";
				$attr = '';
				if ($reloadNonce)
					$attr = sprintf($reloadNonce, \esc_attr(urlencode($coupon->post_title)));
				$content .=	"<div class='lwss_selectable lwss_modify lws-cart-button lws_woorewards_add_coupon' data-id='lws_woorewards_cart_coupons_button' data-coupon='$code'{$attr} data-type='Add to cart'>";
				$content .= "<span class='lwss_modify_content'>{$btnText}</span></div></td>";
			}
			$content .= "</tr>";
		}

		if (!empty($content)) {
			$id = empty($tableId) ? '' : " id='$tableId'";
			$content = "<table class='shop_table shop_table_responsive'$id>{$content}</table>";
		}
		return $content;
	}
}
