<?php

namespace LWS\WOOREWARDS\PRO\Ui\Popups;

// don't call the file directly
if (!defined('ABSPATH')) exit();

/** Popup that shows a free product to choose on a list of possibilities */
class FreeProductPopup
{
	public static function register()
	{
		$me = new self();
		\add_action('wp_enqueue_scripts', array($me, 'enqueueScripts'));

		\add_action('wp_ajax_woorewards_free_product_switch_in_cart', array($me, 'switchCartProductFromAjax'));
		\add_action('wp_ajax_nopriv_woorewards_free_product_switch_in_cart', array($me, 'switchCartProductFromAjax'));

		/** provided only for exotic coupon-apply solution, not used yet
		 *	@param $content (string), @param $coupon )WC_Coupon instance)
		 *	@return (string) popup html */
		\add_filter('lws_woorewards_free_product_picker', array($me, 'getFreeProductPopup'), 10, 2);
	}

	function enqueueScripts()
	{
		\wp_enqueue_style('lws-icons');
		\wp_enqueue_style('lws-popup');
		\wp_enqueue_script('lws-popup');
		\wp_enqueue_style('wr-freeproduct-popup', LWS_WOOREWARDS_PRO_CSS . '/popups/freeproduct.min.css', array('lws-icons', 'lws-popup'), LWS_WOOREWARDS_PRO_VERSION);
		\wp_enqueue_script('wr-freeproduct-popup', LWS_WOOREWARDS_PRO_JS . '/popups/freeproduct.js', array('jquery', 'lws-popup'), LWS_WOOREWARDS_PRO_VERSION, true);
	}


	/** Ajax response */
	function switchCartProductFromAjax()
	{
		$code = isset($_GET['code']) ? \sanitize_text_field($_GET['code']) : false;
		if (!$code)
			\wp_send_json(array('error' => __("Coupon code is missing", 'woorewards-pro')));
		if (!\WC()->cart)
			\wp_send_json(array('error' => __("Cannot access to cart", 'woorewards-pro')));

		// no product means abort free product
		$productId = isset($_GET['product']) ? \intval($_GET['product']) : false;

		$itemKey = false;
		foreach (\WC()->cart->get_cart() as $cart_item) {
			if (isset($cart_item['woorewards-freeproduct']) && $cart_item['woorewards-freeproduct'] == $code) {
				$itemKey = $cart_item['key'];
			}
		}

		if ($productId) {
			\WC()->cart->add_to_cart($productId, 1, 0, array(), array(
				'unique_key' => \md5($code . microtime() . rand()),
				'woorewards-freeproduct' => $code,
			));
		} else {
			\WC()->cart->remove_coupon($code);
		}

		// remove the previous product
		if ($itemKey) {
			if (isset(\WC()->cart->cart_contents[$itemKey]))
				\WC()->cart->cart_contents[$itemKey]['lws_wr_remove_ignored'] = 'yes';
			\WC()->cart->remove_cart_item($itemKey);
		}

		\wp_send_json(array('success' => true));
	}

	/** $return array of products id like [id => id].
	 *	Apply translation on product to have unique products relative to
	 *	user current language. */
	function getCouponProducts($coupon)
	{
		$ids = array();
		foreach ($coupon->get_product_ids() as $id) {
			$tr = \apply_filters('wpml_object_id', $id, 'product', true);
			$ids[$tr] = $tr;
		}
		return $ids;
	}

	public function getFreeProductPopup($content, $coupon)
	{
		$ids = $this->getCouponProducts($coupon);
		$products = array();
		foreach ($ids as $id) {
			$product = \wc_get_product($id);
			if (!$product)
				continue;

			$imgId = $product->get_image_id();
			$thumbnail = $imgId ? \wp_get_attachment_image($imgId, 'lws_wr_thumbnail', false, array('class' => 'lws-wr-thumbnail lws-wr-unlockable-thumbnail')) : '';
			$products[] = array(
				'id'        => $id,
				'thumbnail' => $thumbnail,
				'title'     => $product->get_name(),
				'link'      => get_permalink($id),
			);
		}
		$content = $this->getContent($coupon->get_code(), $products);
		return $content;
	}

	protected function getContent($code, $productsInfo)
	{
		$ajaxUrl = \esc_attr(\admin_url('/admin-ajax.php'));
		$displaytitle = \lws_get_option('lws_wr_free_product_popup_title', __('Select your free product', 'woorewards-pro'));
		$cancelbutton = \lws_get_option('lws_wr_free_product_popup_cancel', __('Cancel', 'woorewards-pro'));
		$applybutton = \lws_get_option('lws_wr_free_product_popup_apply', __('Add this product', 'woorewards-pro'));
		$displaytitle = \apply_filters('wpml_translate_single_string', $displaytitle, 'Popups', "WooRewards Free Product - title");
		$cancelbutton = \apply_filters('wpml_translate_single_string', $cancelbutton, 'Popups', "WooRewards Free Product - cancel button");
		$applybutton = \apply_filters('wpml_translate_single_string', $applybutton, 'Popups', "WooRewards Free Product - apply button");
		$layout = \get_option('lws_wr_free_product_popup_layout', 'all');

		$products = '';
		foreach ($productsInfo as $product) {
			$thumbnail = '';
			if ($product['thumbnail']) {
				$thumbnail = "<div class='product-thumbnail'>{$product['thumbnail']}</div>";
			}
			$products .= <<<EOT
			<div class="lws-popup-item product">
				<input type="radio" name="free_product_selection" value="{$product['id']}" id="free-product-{$product['id']}"/>
				<label class="product-button" for="free-product-{$product['id']}">
					$thumbnail
					<div class="product-name">{$product['title']}</div>
				</label>
			</div>
EOT;
		}

		$rows = '';
		if ('threebythree' == $layout) {
			$rows = sprintf(' data-rows="%d"', \max(2, \intval(\apply_filters('lws_woorewards_free_product_popup_threebythree', 3))));
		}

		$content = <<<EOT
		<div class="lws_popup wr_free_product_popup lws-popup lws-shadow" id="wr_free_product" data-layout="{$layout}"{$rows} data-code="{$code}" data-ajaxurl="{$ajaxUrl}">
			<div class="lws-window">
				<div class="lws-popup-close lws-icon-cross"></div>
				<div class="lws-popup-title">{$displaytitle}</div>
				<div class="lws-popup-content {$layout}" id="wr_free_product_choice_dialog" data-code="{$code}">
					<div class="content-up lws-icon-up-arrow hidden"></div>
					<div class="lws-popup-items">{$products}</div>
					<div class="content-down lws-icon-down-arrow hidden"></div>
				</div>
				<div class="lws-popup-buttons">
					<div class="lws-popup-button cancel wr_free_product_popup_cancel">{$cancelbutton}</div>
					<div class="lws-popup-button apply wr_free_product_popup_apply disabled">{$applybutton}</div>
				</div>
			</div>
		</div>
EOT;
		return $content;
	}
}