<?php
namespace LWS\WOOREWARDS\PRO\WC;

// don't call the file directly
if( !defined( 'ABSPATH' ) ) exit();

/** New feature on WC cart
 * * Automatically add permanent coupon to the cart.
 * * Add product associated with a free product coupon. */
class Cart
{
	public function __construct()
	{
		\add_action('woocommerce_check_cart_items', array($this, 'completeCart'), 200);
		\add_action('woocommerce_before_checkout_form', array($this, 'completeCart'));
		\add_action('wp_loaded', array($this, 'applyCoupon'), 0);

		// act just before wc
		\add_action('wp_ajax_woocommerce_apply_coupon', array($this, 'addFreeProduct'), 0);
		\add_action('wp_ajax_nopriv_woocommerce_apply_coupon', array($this, 'addFreeProduct'), 0);
		// WC AJAX can be used for frontend ajax requests.
		\add_action('wc_ajax_apply_coupon', array($this, 'addFreeProduct'), 0);

		//
		\add_action('woocommerce_applied_coupon', array($this, 'resumeAutoApply'), 0);

		\add_action('woocommerce_before_cart_totals', array($this, 'freeProductPicker'));
		\add_action('woocommerce_review_order_before_order_total', array($this, 'freeProductPicker'));
		/** provide for convenience on exotic coupon/cart solutions
		 * Echo picker popup html if cart contains any freeproduct requiring it */
		\add_action('lws_woorewards_free_product_cart_picker', array($this, 'freeProductPicker'));

		\add_filter('woocommerce_cart_totals_coupon_label', array($this, 'getCouponLabel'), 1000, 2);
		\add_filter('woocommerce_removed_coupon', array($this, 'removedCoupon'), 10, 1);
		\add_filter('wcs_bypass_coupon_removal', array($this, 'wcsBypass'), 10, 2);

		\add_action('woocommerce_remove_cart_item', array($this, 'itemRemoved'), 10, 2);

		// fix free product coupon apply to good item
		\add_filter('woocommerce_coupon_is_valid_for_product', array($this, 'isSelectedFreeProduct'), 10, 4);
	}

	/** WCS does not support not recurring coupon if cart contains subscription.
	 *	Why it works anyway in most case is magic.
	 *	But here, we force him to ignore our free product coupon.
	 *	@see WC_Subscriptions_Coupon::remove_coupons() near line 581  */
	function wcsBypass($bypass, $coupon)
	{
		if( $this->isFreeProductCoupon($coupon) )
			$bypass = true;
		return $bypass;
	}

	/** add indication about the free product if any */
	function getCouponLabel($text, $coupon)
	{
		if( $this->isFreeProductCoupon($coupon) )
		{
			$code = $coupon->get_code();
			$limit = $coupon->get_limit_usage_to_x_items('edit');
			foreach (\WC()->cart->get_cart() as $cart_item)
			{
				if (isset($cart_item['product_id']) && ($productId = $cart_item['product_id'])
				&& isset($cart_item['woorewards-freeproduct']) && $cart_item['woorewards-freeproduct'] == $code)
				{
					if (!(isset($cart_item['woorewards-idle-products']) && count($cart_item['woorewards-idle-products']) > 1))
					{
						$text .= (' ' . sprintf(__("Free %s", 'woorewards-pro'), \get_the_title($productId)));
					}
					if( 0 >= --$limit )
						break;
				}
			}
		}
		return $text;
	}

	/** Do before WC actions.
	 * If the coupon is a MyRewards Free product, add that product in cart if not already in. */
	function addFreeProduct()
	{
		if( !function_exists('\wc_coupons_enabled'))
			return;
		if( !isset($_POST['coupon_code']) || empty($_POST['coupon_code']) )
			return;
		if( !\wc_coupons_enabled() )
			return;

		$code = \wc_format_coupon_code(\sanitize_text_field(\wp_unslash($_POST['coupon_code'])));
		$coupon = new \WC_Coupon($code);

		$applied_coupons = \WC()->cart->get_applied_coupons();
		foreach ( $applied_coupons as $code ) {
			$cart_coupon = new \WC_Coupon( $code );
			if( $cart_coupon->get_individual_use() && false === apply_filters('woocommerce_apply_with_individual_use_coupon', false, $coupon, $cart_coupon, $applied_coupons) )
				return;
		}

		if ($this->isFreeProductCoupon($coupon) && $coupon->get_product_ids()) {
			if (true === $this->isFreeProductCouponAllowed($coupon, \WC()->cart))
				$this->addTheProduct($coupon);
		}
	}

	function addTheProduct($coupon)
	{
		$code = $coupon->get_code();
		foreach( \WC()->cart->get_cart() as $cart_item )
		{
			if( isset($cart_item['woorewards-freeproduct']) && $cart_item['woorewards-freeproduct'] == $code )
				return; // do not add twice for the same coupon
		}

		if (\get_option('lws_woorewards_free_product_popup_legacy') == 'on') {
			$picker = new \LWS\WOOREWARDS\PRO\Ui\Legacy\ChooseFreeProduct();
		} else {
			$picker = new \LWS\WOOREWARDS\PRO\Ui\Popups\FreeProductPopup();
		}
		$ids = $picker->getCouponProducts($coupon);

		\WC()->cart->add_to_cart(reset($ids), 1, 0, array(), array(
			'unique_key' => \md5($coupon->get_code() . microtime() . rand()),
			'woorewards-freeproduct' => $code,
			'woorewards-idle-products' => $ids,
		));
	}

	/** If cart coupon requires it, append the popup over the cart */
	function freeProductPicker()
	{
		foreach (\WC()->cart->get_cart() as $cart_item)
		{
			if (isset($cart_item['product_id']) && ($productId = $cart_item['product_id'])
			&& isset($cart_item['woorewards-freeproduct']))
			{
				if (isset($cart_item['woorewards-idle-products']) && count($cart_item['woorewards-idle-products']) > 1)
				{
					if (\WC()->cart->has_discount($cart_item['woorewards-freeproduct'])) {
						$coupon = new \WC_Coupon($cart_item['woorewards-freeproduct']);
						if ($coupon){
							if (\get_option('lws_woorewards_free_product_popup_legacy') == 'on') {
								$picker = new \LWS\WOOREWARDS\PRO\Ui\Legacy\ChooseFreeProduct;
							} else {
								$picker = new \LWS\WOOREWARDS\PRO\Ui\Popups\FreeProductPopup;
							}
							echo $picker->getFreeProductPopup('', $coupon);
						}
					}
				}
			}
		}
	}

	/**	If one of possible free product is already in cart,
	 *	we cannot be sure WC use the picked one for price computing.
	 *	From cart item, check it is the selected one. */
	function isSelectedFreeProduct($valid, $product, $coupon, $item)
	{
		if ($valid && $item && \is_array($item) && $this->isFreeProductCoupon($coupon)) {
			if (!isset($item['woorewards-freeproduct']) || $coupon->get_code() != $item['woorewards-freeproduct']) {
				$valid = false;
			}
		}
		return $valid;
	}

	function itemRemoved($cartItemKey, $cart)
	{
		if (isset($cart->cart_contents[$cartItemKey])) {
			if (isset($cart->cart_contents[$cartItemKey]['lws_wr_remove_ignored']) && 'yes' == $cart->cart_contents[$cartItemKey]['lws_wr_remove_ignored']) {
				return;
			}
			$cart->cart_contents[$cartItemKey]['lws_wr_remove_ignored'] = 'yes';
		}

		$item = $cart->get_cart_item($cartItemKey);
		if ($item && isset($item['woorewards-freeproduct'])) {
			$code = $item['woorewards-freeproduct'];
			if ($code) {
				$this->pauseAutoApply($code);
				// remove coupon only after product really removed
				\add_action('woocommerce_cart_item_removed', function($key, $cart)use($cartItemKey, $code){
					if ($cartItemKey == $key)
						$cart->remove_coupon($code);
				}, 10, 2);
			}
		}
	}

	/* If a coupon is removed and it's a free product, also remove the product */
	function removedCoupon($code)
	{
		$coupon = new \WC_Coupon($code);
		$cart = \WC()->instance()->cart;
		if( \get_post_meta($coupon->get_id(), 'woorewards_freeproduct', true) == 'yes' )
		{
			foreach( \WC()->cart->get_cart() as $cart_item )
			{
				if( isset($cart_item['woorewards-freeproduct']) && $cart_item['woorewards-freeproduct'] == $code )
				{
					$cart->remove_cart_item($cart_item['key']);
				}
			}
		}
		$this->pauseAutoApply($coupon);
	}

	protected function isFreeProductCoupon($coupon)
	{
		if( $coupon->get_discount_type() != 'percent' )
			return false;
		if( !$coupon->get_product_ids() )
			return false;
		if( \get_post_meta($coupon->get_id(), 'woorewards_freeproduct', true) != 'yes' )
			return false;
		return true;
	}

	protected function hasAutoApplyStatus($coupon, $status = 'on')
	{
		if (\is_string($coupon))
			$coupon = new \WC_Coupon($coupon);
		if ($id = $coupon->get_id()) {
			return ($status == \get_post_meta($id, 'lws_woorewards_auto_apply', true));
		} else {
			return false;
		}
	}

	/** If a coupon was removed and auto-apply is idle, resume the auto-Apply */
	public function resumeAutoApply($coupon)
	{
		if (!\is_object($coupon))
			$coupon = new \WC_Coupon($coupon);
		if ($id = $coupon->get_id()) {
			if ('idle' == \get_post_meta($id, 'lws_woorewards_auto_apply', true))
				\update_post_meta($id, 'lws_woorewards_auto_apply', 'on');
		}
	}

	protected function pauseAutoApply($coupon)
	{
		if (\is_string($coupon))
			$coupon = new \WC_Coupon($coupon);
		if ($id = $coupon->get_id()) {
			if (\get_post_meta($id, 'lws_woorewards_auto_apply', true))
				\update_post_meta($id, 'lws_woorewards_auto_apply', 'idle');
		}
	}

	protected function stopAutoApply($coupon)
	{
		if (\is_string($coupon))
			$coupon = new \WC_Coupon($coupon);
		if ($id = $coupon->get_id()) {
			if (\get_post_meta($id, 'lws_woorewards_auto_apply', true))
				\update_post_meta($id, 'lws_woorewards_auto_apply', 'off');
		}
	}

	protected function isVariableDiscountCoupon($coupon)
	{
		if( $coupon->get_discount_type() == 'percent' )
			return false;
		if( \get_post_meta($coupon->get_id(), 'reward_origin', true) != 'lws_woorewards_pro_unlockables_variablediscount' )
			return false;
		return true;
	}

	/** if $_REQUEST contains a coupon to add, we apply it on the cart */
	public function applyCoupon()
	{
		if( !function_exists('\wc_coupons_enabled'))
			return;
		if( !\wc_coupons_enabled() )
			return;
		if (!\WC()->cart)
			return;

		if( isset($_REQUEST['wrac_n']) && isset($_REQUEST['wrac_c']) && \wp_verify_nonce($_REQUEST['wrac_n'], 'wr_apply_coupon') )
		{
			if( !empty($code = \sanitize_text_field($_REQUEST['wrac_c'])) )
			{
				$stack = array_keys(\WC()->cart->get_coupons());
				if( !in_array($code, $stack) )
				{
					if( $id = \wc_get_coupon_id_by_code($code) )
					{
						$wcCoupon = new \WC_Coupon($id);
						$allowed = true;

						if ($this->isFreeProductCoupon($wcCoupon) && $wcCoupon->get_product_ids()) {
							$allowed = $this->isFreeProductCouponAllowed($wcCoupon, \WC()->cart);

							if (true === $allowed)
								$this->addTheProduct($wcCoupon);
							else if(\is_wp_error($allowed))
								\wc_add_notice($allowed->get_error_message(), 'error');
						}

						if (true === $allowed)
							\WC()->cart->apply_coupon($code);
					}

					$redirect = \remove_query_arg(array('wrac_n', 'wrac_c'));
					if( $redirect = \apply_filters('lws_woorewards_redirect_url_after_coupon_apply', $redirect) )
					{
						\wp_redirect($redirect);
						exit();
					}
				}
			}
		}
	}

	/** Automatically add any current user available auto_apply coupon to the cart */
	public function completeCart()
	{
		$user = \wp_get_current_user();
		if( !empty($user->ID) )
		{
			$stack = array_keys(\WC()->cart->get_coupons());
			foreach($this->loadCoupons($user) as $coupon)
			{
				if( !in_array($coupon->title, $stack) )
				{
					$wcCoupon = new \WC_Coupon($coupon->ID);
					$allowed = true;

					if ($this->isFreeProductCoupon($wcCoupon) && $wcCoupon->get_product_ids()) {
						if (true === $this->isFreeProductCouponAllowed($wcCoupon, \WC()->cart))
							$this->addTheProduct($wcCoupon);
						else
							$allowed = false;
					}

					if (true === $allowed && true === $this->isCouponAllowed($wcCoupon, \WC()->cart)) {
						$limit = \intval($wcCoupon->get_usage_limit('edit'));
						if ($limit && ($wcCoupon->get_usage_count('edit') + 1) >= $limit) {
							$this->stopAutoApply($wcCoupon);
						}

						\WC()->cart->apply_coupon($coupon->title);
					}
				}
			}
		}
	}

	/** Test coupon is applicable before adding attached product.
	 *	@param $coupon (\WC_Coupon)
	 *	@param $cart (\WC_Cart|\WC_Discounts)
	 *	@return true or WP_Error */
	function isFreeProductCouponAllowed($coupon, $cart)
	{
		$fake = clone $coupon;
		$fake->set_product_ids(array()); // do not test required product here
		return $this->isCouponAllowed($fake, $cart);
	}

	function isCouponAllowed($coupon, $cart)
	{
		if (!\is_a($cart, '\WC_Discounts'))
			$cart = new \WC_Discounts($cart);
		return $cart->is_coupon_valid($coupon);
	}

	/** Usable auto-applied coupons.
	 *  return array of post{ID, title} */
	private function loadCoupons($user)
	{
		if( empty($user->user_email) )
			return array();
		$todayDate = strtotime(date('Y-m-d'));

		global $wpdb;
		$sql = <<<EOT
SELECT p.ID as ID, p.post_title as title from {$wpdb->posts} as p
INNER JOIN {$wpdb->postmeta} as mail ON p.ID = mail.post_id AND mail.meta_key='customer_email'
INNER JOIN {$wpdb->postmeta} as w ON p.ID = w.post_id AND w.meta_key='lws_woorewards_auto_apply' AND w.meta_value='on'
LEFT JOIN {$wpdb->postmeta} as l ON p.ID = l.post_id AND l.meta_key='usage_limit'
LEFT JOIN {$wpdb->postmeta} as u ON p.ID = u.post_id AND u.meta_key='usage_count'
LEFT JOIN {$wpdb->postmeta} as e ON p.ID = e.post_id AND e.meta_key='expiry_date'
WHERE mail.meta_value=%s AND post_type = 'shop_coupon' AND post_status = 'publish'
AND (e.meta_value IS NULL OR e.meta_value = '' OR e.meta_value >= '{$todayDate}')
AND (u.meta_value < l.meta_value OR u.meta_value IS NULL OR l.meta_value IS NULL OR l.meta_value=0)
EOT;
		return $wpdb->get_results($wpdb->prepare($sql, serialize(array($user->user_email))));
	}
}
