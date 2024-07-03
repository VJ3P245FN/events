<?php

namespace LWS\WOOREWARDS\PRO\Ui\Shortcodes;

// don't call the file directly
if (!defined('ABSPATH')) exit();

/** Only show content under conditions */
class IsInCart
{
	const SHORTCODE = 'wr_is_in_cart';

	public static function install()
	{
		$me = new self();
		\add_shortcode(self::SHORTCODE, array($me, 'shortcode'));
		\add_filter('lws_woorewards_advanced_shortcodes', array($me, 'admin'), 200);
	}

	/** Get the shortcode admin */
	public function admin($fields)
	{
		$fields[self::SHORTCODE] = array(
			'id' => self::SHORTCODE,
			'title' => __("Cart Conditional", 'woorewards-pro'),
			'type' => 'shortcode',
			'extra' => array(
				'shortcode' => \sprintf(
					'[%s coupons="true"]%s[/%s]',
					self::SHORTCODE,
					__("A text only visible under conditions.", 'woorewards-pro'),
					self::SHORTCODE
				),
				'description' => array(
					__("Use this shortcode to show content to your customers only if they meet the required condition(s).", 'woorewards-pro'),
					__("Test cart content.", 'woorewards-pro')
				),
				'options' => array(
					'coupons' => array(
						'option' => 'coupons',
						'desc' => __("Is coupons in the cart.", 'woorewards-pro'),
					),
					'coupon_codes' => array(
						'option' => 'coupon_codes',
						'desc' => __("Test coupon codes with a RegEx.", 'woorewards-pro'),
					),
					'products' => array(
						'option' => 'products',
						'desc' => __("Get a comma separated list of product IDs.", 'woorewards-pro'),
					),
					'cats' => array(
						'option' => 'cats',
						'desc' => __("Get a comma separated list of product category IDs (not slugs).", 'woorewards-pro'),
					),
					'tags' => array(
						'option' => 'tags',
						'desc' => __("Get a comma separated list of product tag IDs (not slugs).", 'woorewards-pro'),
					),
					'guest' => array(
						'option' => 'guest',
						'desc' => __("Is current user is guest or logged in user.", 'woorewards-pro'),
					),
					'not' => array(
						'option'  => 'not',
						'desc'    => __("Set it to reverse the condition. Display content if conditions are not met.", 'woorewards-pro'),
						'example' => \sprintf(
							'[%s not="true" coupons="true"]%s[/%s]',
							self::SHORTCODE,
							__("Only visible if no coupons in cart.", 'woorewards-pro'),
							self::SHORTCODE
						),
					),
				)
			)
		);
		return $fields;
	}

	public function shortcode($atts = array(), $content = '')
	{
		if (!\WC()->cart) return '';
		$atts = \LWS\Adminpanel\Tools\Conveniences::sanitizeAttr(\wp_parse_args($atts, array(
			'coupons'      => false,
			'coupon_codes' => false,
			'products'     => false,
			'cats'         => false,
			'tags'         => false,
			'guest'        => false,
			'not'          => false,
		)));
		$atts['not'] = \LWS\Adminpanel\Tools\Conveniences::argIsTrue($atts['not']);
		$products = false;
		$coupons = false;

		if (false !== $atts['guest']) {
			$atts['guest'] = \LWS\Adminpanel\Tools\Conveniences::argIsTrue($atts['guest']);
			if ($atts['not']) $atts['guest'] = !$atts['guest'];

			if (\get_current_user_id()) {
				if ($atts['guest']) return ''; // need visitor, there is logged in
			} else {
				if (!$atts['guest']) return ''; // need logged in, it is visitor
			}
		}

		if (false !== $atts['coupons']) {
			$atts['coupons'] = \LWS\Adminpanel\Tools\Conveniences::argIsTrue($atts['coupons']);
			if ($atts['not']) $atts['coupons'] = !$atts['coupons'];
			if (false === $coupons) $coupons = \WC()->cart->get_applied_coupons();

			if ($coupons) {
				if (!$atts['coupons']) return ''; // need no coupons, there is
			} else {
				if ($atts['coupons']) return ''; // need coupons, there is not
			}
		}

		if (false !== $atts['coupon_codes']) {
			if (false === $coupons) $coupons = \WC()->cart->get_applied_coupons();
			$codes = \array_filter($coupons, function($c) use ($atts) {
				return \preg_match($atts['coupon_codes'], $c);
			});
			if ($codes) {
				if ($atts['not']) return ''; // need no coupons, there is
			} else {
				if (!$atts['not']) return ''; // need coupons, there is not
			}
		}

		if (false !== $atts['products']) {
			if (false === $products) $products = $this->getCartProducts();
			$ids = \LWS\Adminpanel\Tools\Conveniences::sanitizeAttr($atts['products'], true);
			$test = \array_intersect(\array_keys($products), \array_map('\intval', $ids));

			if ($test) {
				if ($atts['not']) return ''; // need no item, there is
			} else {
				if (!$atts['not']) return ''; // need item, there is not
			}
		}

		if (false !== $atts['cats']) {
			if (false === $products) $products = $this->getCartProducts();
			$ids = \LWS\Adminpanel\Tools\Conveniences::sanitizeAttr($atts['cats'], true);
			$test = array();
			foreach ($products as $p) {foreach ((array)$p->get_category_ids() as $c) $test[$c] = $c;}
			$test = \array_intersect($test, \array_map('\intval', $ids));

			if ($test) {
				if ($atts['not']) return ''; // need no item, there is
			} else {
				if (!$atts['not']) return ''; // need item, there is not
			}
		}

		if (false !== $atts['tags']) {
			if (false === $products) $products = $this->getCartProducts();
			$ids = \LWS\Adminpanel\Tools\Conveniences::sanitizeAttr($atts['tags'], true);
			foreach ($products as $p) {foreach ((array)$p->get_tag_ids() as $c) $test[$c] = $c;}
			$test = \array_intersect($test, \array_map('\intval', $ids));

			if ($test) {
				if ($atts['not']) return ''; // need no item, there is
			} else {
				if (!$atts['not']) return ''; // need item, there is not
			}
		}

		return \do_shortcode($content);
	}

	/** @return array int:id => \WC_Product */
	private function getCartProducts()
	{
		$products = array();
		foreach (\WC()->cart->get_cart() as $item) {
			if (!isset($item['product_id'])) continue;
			$product = \wc_get_product($item['product_id']);
			if (!$product) continue;
			$products[$product->get_id()] = $product;
		}
		return $products;
	}
}
