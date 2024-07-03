<?php

namespace LWS\WOOREWARDS\PRO\Ui\Legacy;

// don't call the file directly
if (!defined('ABSPATH')) exit();

/** Popup that shows a free product to choose on a list of possibilities */
class ChooseFreeProduct
{
	private $stygen = false;
	
	public static function register()
	{
		$me = new self();
		\add_filter('lws_adminpanel_stygen_content_get_' . 'free_product_template', array($me, 'template'));
		\add_action('wp_enqueue_scripts', array($me, 'enqueueScripts'));

		\add_action('wp_ajax_woorewards_free_product_switch_in_cart', array($me, 'switchCartProductFromAjax'));
		\add_action('wp_ajax_nopriv_woorewards_free_product_switch_in_cart', array($me, 'switchCartProductFromAjax'));

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

	function enqueueScripts()
	{
		\wp_enqueue_style('lws-icons');
		\wp_enqueue_style('woorewards-free-product', LWS_WOOREWARDS_PRO_CSS . '/templates/freeproduct.css?stygen=lws_woorewards_free_product_template', array(), LWS_WOOREWARDS_PRO_VERSION);
		\wp_enqueue_script('woorewards-free-product', LWS_WOOREWARDS_PRO_JS . '/legacy/freeproduct.js', array('jquery'), LWS_WOOREWARDS_PRO_VERSION, true);
	}

	public function template($snippet)
	{
		$this->stygen = true;
		$products = array(
			array(
				'id' => 10,
				'thumbnail' => '<img src="' . LWS_WOOREWARDS_PRO_IMG . '/cat.png" class="lws-wr-thumbnail lws-wr-unlockable-thumbnail" alt="" loading="lazy">',
				'title' => 'The Cat',
				'link'	=> '#'
			),
			array(
				'id' => 26,
				'thumbnail' => '<img src="' . LWS_WOOREWARDS_PRO_IMG . '/horse.png" class="lws-wr-thumbnail lws-wr-unlockable-thumbnail" alt="" loading="lazy">',
				'title' => 'The White Horse',
				'link'	=> '#'
			),
			array(
				'id' => 48,
				'thumbnail' => '<img src="' . LWS_WOOREWARDS_PRO_IMG . '/chthulu.png" class="lws-wr-thumbnail lws-wr-unlockable-thumbnail" alt="" loading="lazy">',
				'title' => 'Chtulhu rules',
				'link'	=> '#'
			),
		);
		$content = $this->getContent('TEST-COUPON', $products);
		$this->stygen = false;
		return $content;
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
		$shadow = '';
		$classes = array(
			'popup'  => '',
			'submit' => '',
			'cancel' => '',
		);
		$displaytitle = \lws_get_option('lws_free_product_popup_title', __('Select your free product', 'woorewards-pro'));
		$cancelbutton = \lws_get_option('lws_free_product_popup_cancel', __('Cancel', 'woorewards-pro'));
		$validbutton = \lws_get_option('lws_free_product_popup_validate', __('Add this product', 'woorewards-pro'));
		if (!$this->stygen) {
			$shadow = '<div class="free-product-popup-shadow lws_action_cancel"></div>';
			$classes = array(
				'popup'  => ' fixed',
				'submit' => ' lws_action_submit disabled',
				'cancel' => ' lws_action_cancel',
			);
			$displaytitle = \apply_filters('wpml_translate_single_string', $displaytitle, 'Widgets', "WooRewards Free Product - title");
			$cancelbutton = \apply_filters('wpml_translate_single_string', $cancelbutton, 'Widgets', "WooRewards Free Product - cancel button");
			$validbutton = \apply_filters('wpml_translate_single_string', $validbutton, 'Widgets', "WooRewards Free Product - validate button");
		}

		$products = '';
		foreach ($productsInfo as $product) {
			$products .= <<<EOT
			<div class="lwss_selectable product-line" data-type="Product Line">
				<div class="lwss_selectable product-thumbnail" data-type="Product Thumbnail">
					{$product['thumbnail']}
				</div>
				<a href="{$product['link']}" target="_blank" class="lwss_selectable product-link" data-type="Product Link">
					<div class="lwss_selectable product-name" data-type="Product Name">{$product['title']}</div>
				</a>
				<input type="radio" name="free_product_selection" value="{$product['id']}"/>
			</div>
EOT;
		}

		$content = <<<EOT
		<div class="lws-free-product-popup-container{$classes['popup']}" data-code="{$code}" data-ajaxurl="{$ajaxUrl}">
			<div class="lwss_selectable free-product-popup" data-type="Popup">
				<div class="lwss_selectable free-product-top-line" data-type="Top Line">
					<div class='lwss_selectable free-product-popup-title' data-editable='text' data-id='lws_free_product_popup_title' data-type='Popup title'>
						<div class='lwss_modify_content'>{$displaytitle}</div>
					</div>
					<button class="lwss_selectable close-button lws-icon-cross{$classes['cancel']}" data-type="Close Button"></button>
				</div>
				<div class="lwss_selectable products-list" data-type="Products List" id="lws_free_product_choice_dialog" data-code="{$code}">
					{$products}
				</div>
				<div class="lwss_selectable buttons-line" data-type="Buttons Line">
					<button class='lwss_selectable free-product-popup-cancel{$classes['cancel']}' data-editable='text' data-id='lws_free_product_popup_cancel' data-type='Cancel Button'>
						<div class='lwss_modify_content'>{$cancelbutton}</div>
					</button>
					<button class='lwss_selectable free-product-popup-validate{$classes['submit']}' data-editable='text' data-id='lws_free_product_popup_validate' data-type='Validate Button'>
						<div class='lwss_modify_content'>{$validbutton}</div>
					</button>
				</div>
			</div>
			{$shadow}
		</div>
EOT;
		return $content;
	}
}
