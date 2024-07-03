<?php

namespace LWS\WOOREWARDS\PRO\Unlockables;

// don't call the file directly
if (!defined('ABSPATH')) exit();

/** A free product is a usual shop coupon with:
 * * usage restricted to 1 single product.
 * * discount amount is 100%
 *
 * @note should we manage product variation?
 *
 * Create a WooCommerce Coupon. */
class FreeProduct extends \LWS\WOOREWARDS\Abstracts\Unlockable
{
	use \LWS\WOOREWARDS\PRO\Unlockables\T_DiscountOptions;

	private $productsIds  = array();
	private $autoapply    = false;
	private $permanent    = false;
	private $freeshipping = false;
	private $timeout      = null;
	private $lastCode     = '';

	function getInformation()
	{
		return array_merge(parent::getInformation(), array(
			'icon'  => 'lws-icon-present',
			'short' => __("The customer will receive a Free Product Coupon for the product of your choice.", 'woorewards-pro'),
			'help'  => __("The generated coupon can be used like any other WooCommerce coupon.", 'woorewards-pro'),
		));
	}

	static function registerFeatures()
	{
		// warn admin for reward about deleted products
		\add_action('before_delete_post', array(\get_class(), 'productDeleted'));
	}

	/** If an offered product is deleted, raise an admin notice. */
	static function productDeleted($postid)
	{
		// does a product deleted
		if (empty($product = \get_post($postid)) || $product->post_type != 'product')
			return;

		// does it belong to a rewards
		global $wpdb;
		$count = $wpdb->get_var($wpdb->prepare("SELECT COUNT(p.ID) FROM {$wpdb->posts} as p
INNER JOIN {$wpdb->postmeta} as m ON p.ID=m.post_id AND m.meta_key='wre_unlockable_product_id' AND m.meta_value=%s
INNER JOIN {$wpdb->postmeta} as t ON p.ID=t.post_id AND t.meta_key='wre_unlockable_type' AND t.meta_value='lws_woorewards_pro_unlockables_freeproduct'
WHERE post_type='lws-wre-unlockable'", $postid));

		if (!empty($count)) {
			\lws_admin_add_notice(
				'woorewards-free-product-deleted-' . $postid,
				sprintf(__("The deleted product <b>%s</b> was used by rewards.", 'woorewards-pro'), \apply_filters('the_title', $product->post_title, $postid)),
				array(
					'dismissible' => true,
					'forgettable' => true,
					'level' => 'warning',
					'once' => false
				)
			);
		}
	}

	function getData($min = false)
	{
		$prefix = $this->getDataKeyPrefix();
		$data = parent::getData();
		$data[$prefix . 'timeout'] = $this->getTimeout()->toString();
		$data[$prefix . 'product_id'] = base64_encode(json_encode($this->getProductsIds()));
		$data[$prefix . 'autoapply'] = ($this->isAutoApply() ? 'on' : '');
		$data[$prefix . 'permanent'] = ($this->isPermanent() ? 'on' : '');
		$data[$prefix . 'free_shipping'] = ($this->isFreeShipping() ? 'on' : '');
		return $this->filterData($data, $prefix, $min);
	}

	function getForm($context = 'editlist')
	{
		$prefix = $this->getDataKeyPrefix();
		$form = parent::getForm($context);
		$form .= $this->getFieldsetBegin(2, __("Coupon options", 'woorewards-pro'), "span2");

		// product
		$label = _x("Offered Product", "Coupon Unlockable", 'woorewards-pro');
		$tooltip = __("If you select multiple products, the customer will have the possibility to choose the free product in that list.", 'woorewards-pro');
		$form .= "<div class='field-help'>$tooltip</div>";
		$form .= "<div class='lws-$context-opt-title label bold'>$label<div class='bt-field-help'>?</div></div>";
		$form .= "<div class='lws-$context-opt-input value'>";
		$form .= \LWS\Adminpanel\Pages\Field\LacChecklist::compose($prefix . 'product_id', array(
			'comprehensive' => true,
			'ajax' => 'lws_woorewards_wc_products_and_variations_list',
		));
		$form .= "</div>";

		// timeout
		$label = _x("Validity period", "Coupon Unlockable", 'woorewards-pro');
		$value = $this->getTimeout()->toString();
		$form .= "<div class='lws-$context-opt-title label'>$label</div>";
		$form .= "<div class='lws-$context-opt-input value'>";
		$form .= \LWS\Adminpanel\Pages\Field\Duration::compose($prefix . 'timeout', array('value' => $value));
		$form .= "</div>";

		// permanent on/off
		$label = _x("Exclusive reward", "Free Product Unlockable", 'woorewards-pro');
		$tooltip = __("That reward will replace any previous Exclusive reward coupon reward of the same type owned by the customer.", 'woorewards-pro');
		$toggle = \LWS\Adminpanel\Pages\Field\Checkbox::compose($prefix . 'permanent', array(
			'id'      => $prefix . 'permanent',
			'layout'  => 'toggle',
		));
		$form .= "<div class='field-help'>$tooltip</div>";
		$form .= "<div class='lws-$context-opt-title label'>$label<div class='bt-field-help'>?</div></div>";
		$form .= "<div class='lws-$context-opt-input value'>$toggle</div>";

		// autoapply on/off
		$label = _x("Auto-apply on next cart", "Free Product Unlockable", 'woorewards-pro');
		$toggle = \LWS\Adminpanel\Pages\Field\Checkbox::compose($prefix . 'autoapply', array(
			'id'      => $prefix . 'autoapply',
			'layout'  => 'toggle',
			'checked' => ($this->isAutoApply() ? ' checked' : ''),
		));
		$form .= "<div class='lws-$context-opt-title label'>$label</div>";
		$form .= "<div class='lws-$context-opt-input value'>$toggle</div>";

		// free shipping
		$label = _x("Also gives free shipping", "Coupon Unlockable", 'woorewards-pro');
		$toggle = \LWS\Adminpanel\Pages\Field\Checkbox::compose($prefix . 'free_shipping', array(
			'id'      => $prefix . 'free_shipping',
			'layout'  => 'toggle',
		));
		$form .= "<div class='lws-$context-opt-title label'>$label</div>";
		$form .= "<div class='lws-$context-opt-input value'>$toggle</div>";

		$form .= $this->getFieldsetEnd(2);
		return $this->filterForm($form, $prefix, $context);
	}

	/** @return mixed */
	function submit($form = array(), $source = 'editlist')
	{
		$prefix = $this->getDataKeyPrefix();
		$values = \apply_filters('lws_adminpanel_arg_parse', array(
			'post'     => ($source == 'post'),
			'values'   => $form,
			'format'   => array(
				$prefix . 'autoapply'     => 's',
				$prefix . 'permanent'     => 's',
				$prefix . 'free_shipping' => 's',
				$prefix . 'timeout'       => '/(p?\d+[DYM])?/i',
				$prefix . 'product_id'    => array('D'),
			),
			'defaults' => array(
				$prefix . 'autoapply'     => '',
				$prefix . 'permanent'     => '',
				$prefix . 'free_shipping' => '',
				$prefix . 'timeout'       => '',
				$prefix . 'product_id'    => array(),
			),
			'labels'   => array(
				$prefix . 'autoapply'     => __("Auto-apply", 'woorewards-pro'),
				$prefix . 'permanent'     => __("Permanent", 'woorewards-pro'),
				$prefix . 'free_shipping' => __("Free Shipping", 'woorewards-pro'),
				$prefix . 'timeout'       => __("Validity period", 'woorewards-pro'),
				$prefix . 'product_id'    => __("Product", 'woorewards-pro')
			)
		));
		if (!(isset($values['valid']) && $values['valid']))
			return isset($values['error']) ? $values['error'] : false;

		if (empty($values['values'][$prefix . 'product_id'])) {
			return __("You must select at least one product to offer", 'woorewards-pro');
		}

		$valid = parent::submit($form, $source);
		if ($valid === true && ($valid = $this->optSubmit($prefix, $form, $source)) === true) {
			$this->setProductsIds($values['values'][$prefix . 'product_id']);
			$this->setAutoApply($values['values'][$prefix . 'autoapply']);
			$this->setPermanent($values['values'][$prefix . 'permanent']);
			$this->setFreeShipping($values['values'][$prefix . 'free_shipping']);
			$this->setTimeout($values['values'][$prefix . 'timeout']);
		}
		return $valid;
	}

	/** @return array */
	public function getProductsIds()
	{
		return $this->productsIds;
	}

	/** @return (false|\WC_Product) */
	public function getProduct($id)
	{
		if (\LWS\Adminpanel\Tools\Conveniences::isWC() && !empty($id)) {
			return \wc_get_product($id);
		}
		return false;
	}

	public function getProductName($id)
	{
		if (!empty($product = $this->getProduct($id)))
			return $product->get_name();
		return false;
	}

	public function getProductUrl($id)
	{
		if ($product = $this->getProduct($id))
			return \get_permalink($product->get_id());
		return false;
	}

	/** @return string html <a> */
	public function getProductLink($id)
	{
		if ($product = $this->getProduct($id)) {
			return sprintf(
				"<a target='_blank' href='%s' class='lws-woorewards-free-product-link'>%s</a>",
				\esc_attr(\get_permalink($product->get_id())),
				$product->get_name()
			);
		}
		return false;
	}

	/** @return string html <a> */
	public function getProductEditLink($id)
	{
		if ($product = $this->getProduct($id)) {
			return sprintf(
				"<a href='%s'>%s</a>",
				\esc_attr(\get_edit_post_link($product->get_id())),
				$product->get_name()
			);
		}
		return false;
	}

	public function isAutoApplicable()
	{
		return true;
	}

	public function isAutoApply()
	{
		return $this->autoapply;
	}

	public function setAutoApply($yes = false)
	{
		$this->autoapply = boolval($yes);
		return $this;
	}

	public function isPermanent()
	{
		return $this->permanent;
	}

	public function setPermanent($yes = false)
	{
		$this->permanent = boolval($yes);
		return $this;
	}

	public function isFreeShipping()
	{
		return $this->freeshipping;
	}

	public function setFreeShipping($yes = false)
	{
		$this->freeshipping = boolval($yes);
		return $this;
	}

	public function setProductsIds($ids = array())
	{
		if (!is_array($ids)) {
			if (is_numeric($ids)) {
				$ids = array($ids);
			} else {
				$ids = @json_decode(@base64_decode($ids));
			}
		}
		if (is_array($ids)) {
			$this->productsIds = array_map('absint', $ids);
		}
		return $this;
	}


	public function setTestValues()
	{
		global $wpdb;
		// pick a not free product randomly
		$this->setProductsIds($wpdb->get_var("SELECT ID FROM {$wpdb->posts} INNER JOIN {$wpdb->postmeta} ON ID=post_id AND meta_key='_regular_price' AND meta_value>0 WHERE post_type='product' ORDER BY RAND() LIMIT 0, 1"));
		$this->setTimeout(rand(5, 78) . 'D');
		return $this;
	}

	/** return a Duration instance */
	public function getTimeout()
	{
		if (null === $this->timeout)
			$this->timeout = \LWS\Adminpanel\Duration::void();
		return $this->timeout;
	}

	/** @param $days (false|int|\LWS\Adminpanel\Duration) */
	public function setTimeout($days = false)
	{
		if (empty($days))
			$this->timeout = \LWS\Adminpanel\Duration::void();
		else if (is_a($days, '\LWS\Adminpanel\Duration'))
			$this->timeout = $days;
		else
			$this->timeout = \LWS\Adminpanel\Duration::fromString($days);
		return $this;
	}

	public function getDisplayType()
	{
		return _x("Free product", "getDisplayType", 'woorewards-pro');
	}

	/**	Provided to be overriden.
	 *	@param $context usage of text. Default is 'backend' for admin, expect 'frontend' for customer.
	 *	@return (string) what this does. */
	function getDescription($context = 'backend')
	{
		return $this->getCouponDescription($context);
	}

	/**	Provided to be overriden.
	 *	@param $context usage of text. Default is 'backend' for admin, expect 'frontend' for customer.
	 *	@return (string) what this does. */
	function getCouponDescription($context = 'backend', $date = false)
	{
		$names = array();
		foreach ($this->getProductsIds() as $id) {
			if ($context == 'backend')
				$names[] = $this->getProductEditLink($id);
			else if ($context == 'raw')
				$names[] = $this->getProductName($id);
			else
				$names[] = $this->getProductLink($id);
		}
		if (count($names) > 1)
			$str = sprintf(__("Choose a free product between the following products : %s", 'woorewards-pro'), implode(', ', $names));
		else
			$str = sprintf(__("%s offered with an order", 'woorewards-pro'), implode(', ', $names));

		if (!$this->getTimeout()->isNull()) {
			$str .= ' - ';
			if ($date) {
				$str .= sprintf(
					__('valid up to %s', 'woorewards-pro'),
					\date_i18n(\get_option('date_format'), $this->getTimeout()->getEndingDate($date)->getTimestamp())
				);
			} else {
				$str .= sprintf(
					__('valid for %1$d %2$s', 'woorewards-pro'),
					$this->getTimeout()->getCount(),
					$this->getTimeout()->getPeriodText()
				);
			}
		}

		if (!empty($discount = $this->getPartialDescription($context)))
			$str .= (', ' . $discount);
		return $str;
	}

	/** use product image url by default if any but can be overrided by user */
	public function getThumbnailUrl()
	{
		$pIds = $this->getProductsIds();
		if (count($pIds) == 1 && empty($this->getThumbnail()) && !empty($product = $this->getProduct($pIds[0])) && !empty($imgId = $product->get_image_id())) {
			return \wp_get_attachment_image_url($imgId);
		} else {
			return parent::getThumbnailUrl();
		}
	}

	/** use product image by default if any but can be overrided by user */
	public function getThumbnailImage($size = 'lws_wr_thumbnail')
	{
		$pIds = $this->getProductsIds();
		if (count($pIds) == 1 && empty($this->getThumbnail()) && !empty($product = $this->getProduct($pIds[0])) && !empty($imgId = $product->get_image_id()))
			return \wp_get_attachment_image($imgId, $size, false, array('class' => 'lws-wr-thumbnail lws-wr-unlockable-thumbnail'));
		else
			return parent::getThumbnailImage($size);
	}

	protected function _fromPost(\WP_Post $post)
	{
		$this->setPermanent(\get_post_meta($post->ID, 'woorewards_permanent', true));
		$this->setAutoApply(\get_post_meta($post->ID, 'woorewards_autoapply', true));
		$this->setFreeShipping(\get_post_meta($post->ID, 'free_shipping', true));
		$this->setTimeout(\LWS\Adminpanel\Duration::postMeta($post->ID, 'wre_unlockable_timeout'));
		$this->setProductsIds(\get_post_meta($post->ID, 'wre_unlockable_product_id', true));
		$this->optFromPost($post);
		return $this;
	}

	protected function _save($id)
	{
		\update_post_meta($id, 'woorewards_permanent', $this->isPermanent() ? 'on' : '');
		\update_post_meta($id, 'woorewards_autoapply', $this->isAutoApply() ? 'on' : '');
		\update_post_meta($id, 'free_shipping', $this->isFreeShipping() ? 'on' : '');
		$this->getTimeout()->updatePostMeta($id, 'wre_unlockable_timeout');
		\update_post_meta($id, 'wre_unlockable_product_id', $this->getProductsIds());
		$this->optSave($id);
		return $this;
	}

	public function createReward(\WP_User $user, $demo = false)
	{
		if (!\LWS\Adminpanel\Tools\Conveniences::isWC())
			return false;

		if (!\is_email($user->user_email)) {
			error_log(\get_class() . "::apply - invalid email for user {$user->ID}");
			return false;
		}

		if (empty($this->getProductsIds())) {
			error_log(\get_class() . "::apply - undefined free product");
			if (!$demo)
				return false;
		}

		if ($demo)
			$code = strtoupper(__('TESTCODE', 'woorewards-pro'));
		else if (empty($code = apply_filters('lws_woorewards_new_coupon_label', '', $user, $this)))
			$code = \LWS\WOOREWARDS\Unlockables\Coupon::uniqueCode($user);

		if (false === ($coupon = $this->createShopCoupon($code, $user, $demo)))
			return false;

		$this->lastCode = $code;
		return $coupon;
	}

	/** For point movement historic purpose. Can be override to return a reason.
	 *	Last generated coupon code is consumed by this function. */
	public function getReason($context = 'backend')
	{
		if ($this->lastCode) {
			$reason = sprintf(__("Coupon code : %s", 'woorewards-pro'), $this->lastCode);
			if ($context == 'frontend')
				$reason .= '<br/>' . $this->getDescription($context);
			return $reason;
		}
		return $this->getDescription($context);
	}

	protected function createShopCoupon($code, \WP_User $user, $demo = false)
	{
		if (!$demo)
			\do_action('wpml_switch_language_for_email', $user->user_email); // switch to customer language before fixing content

		$coupon = $this->buildCouponPostData($code, $user);
		if (!$demo) {
			$coupon->save();
			if (empty($coupon->get_id())) {
				\do_action('wpml_restore_language_from_email');
				error_log("Cannot generate a shop_coupon: WC error");
				return false;
			}
			\wp_update_post(array(
				'ID' => $coupon->get_id(),
				'post_author'  => $user->ID,
				'post_content' => $this->getTitle()
			));
			\update_post_meta($coupon->get_id(), 'woorewards_freeproduct', 'yes');
			\update_post_meta($coupon->get_id(), 'reward_origin', $this->getType());
			\update_post_meta($coupon->get_id(), 'reward_origin_id', $this->getId());
			if ($this->isAutoApply())
				\update_post_meta($coupon->get_id(), 'lws_woorewards_auto_apply', 'on');
			if ($this->isPermanent()) {
				$this->setPermanentcoupon($coupon, $user, $this->getType(), $this->getPoolId());
			}
			$this->applyOnCoupon($coupon, $user, $this->getPoolId(), $demo);
			// dokan support, force default behavior for a working coupon
			\update_post_meta($coupon->get_id(), 'admin_coupons_enabled_for_vendor', 'yes');

			\do_action('wpml_restore_language_from_email');
			\do_action('woocommerce_coupon_options_save', $coupon->get_id(), $coupon);
		}
		return $coupon;
	}

	protected function buildCouponPostData($code, \WP_User $user)
	{
		$txt = $this->getCustomExcerpt($user);

		/** That filter is required to counter poorly coded plugins, that prevent data_store instanciation in fresh coupon. */
		\add_filter('woocommerce_get_shop_coupon_data', '__return_false', PHP_INT_MAX);
		$coupon = new \WC_Coupon();
		\remove_filter('woocommerce_get_shop_coupon_data', '__return_false', PHP_INT_MAX);

		$coupon->set_props(array(
			'code'                   => $code,
			'description'            => $txt,
			'discount_type'          => 'percent',
			'amount'                 => 100,
			'date_expires'           => !$this->getTimeout()->isNull() ? $this->getTimeout()->getEndingDate()->format('Y-m-d') : '',
			'usage_limit'            => $this->getUsageLimit(),
			'free_shipping'          => $this->isFreeShipping() ? true : '',
			'email_restrictions'     => array($user->user_email),
			'product_ids'            => $this->getProductsIds(),
			'limit_usage_to_x_items' => 1
		));
		return $this->filterCouponPostData($coupon, $code, $user);
	}

	/**	Event categories, used to filter out events from pool options.
	 *	@return array with category_id => category_label. */
	public function getCategories()
	{
		return array_merge(parent::getCategories(), array(
			'woocommerce' => __("WooCommerce", 'woorewards-pro'),
			'shop_coupon' => __("Coupon", 'woorewards-pro'),
			'wc_product'  => __("Product", 'woorewards-pro'),
			'sponsorship' => _x("Referee", "unlockable category", 'woorewards-pro')
		));
	}
}
