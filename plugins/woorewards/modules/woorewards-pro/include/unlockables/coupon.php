<?php

namespace LWS\WOOREWARDS\PRO\Unlockables;

// don't call the file directly
if (!defined('ABSPATH')) exit();

/**
 * Append attributes to free version unlockable coupon as:
 * * permanent @see WC\Cart */
class Coupon extends \LWS\WOOREWARDS\Unlockables\Coupon
{
	use \LWS\WOOREWARDS\PRO\Unlockables\T_DiscountOptions;

	private $products           = array();
	private $autoapply          = false;
	private $permanent          = false;
	private $freeshipping       = false;
	private $itemsUsageLimit    = '';
	private $excludedCategories = array();
	private $productCategories  = array();
	private $excludedProducts   = array();

	function getClassname()
	{
		return 'LWS\WOOREWARDS\Unlockables\Coupon';
	}

	/** @return bool */
	public function isAutoApply()
	{
		return $this->autoapply;
	}

	public function setAutoApply($yes = false)
	{
		$this->autoapply = boolval($yes);
		return $this;
	}

	/** @return bool */
	public function isPermanent()
	{
		return $this->permanent;
	}

	public function setPermanent($yes = false)
	{
		$this->permanent = boolval($yes);
		return $this;
	}

	/** @return bool */
	public function isFreeShipping()
	{
		return $this->freeshipping;
	}

	public function setFreeShipping($yes = false)
	{
		$this->freeshipping = boolval($yes);
		return $this;
	}

	/** @return int */
	public function getItemsUsageLimit()
	{
		return $this->itemsUsageLimit;
	}

	public function setItemsUsageLimit($limit = 0)
	{
		$this->itemsUsageLimit = \absint($limit);
		if ($this->itemsUsageLimit == 0)
			$this->itemsUsageLimit = '';
		return $this;
	}

	/** @return array */
	function getExcludedCategories()
	{
		return $this->excludedCategories;
	}

	/** @param $categories (array|string) as string, it should be a json base64 encoded array. */
	function setExcludedCategories($categories = array())
	{
		if (!is_array($categories))
			$categories = @json_decode(@base64_decode($categories));
		if (is_array($categories))
			$this->excludedCategories = $categories;
		return $this;
	}

	/** @return array */
	function getProductCategories()
	{
		return $this->productCategories;
	}

	/** @param $categories (array|string) as string, it should be a json base64 encoded array. */
	function setProductCategories($categories = array())
	{
		if (!is_array($categories))
			$categories = @json_decode(@base64_decode($categories));
		if (is_array($categories))
			$this->productCategories = $categories;
		return $this;
	}

	/** @return array */
	function getExcludedProducts()
	{
		return $this->excludedProducts;
	}

	/** @param $products (array|string) as string, it should be a json base64 encoded array. */
	function setExcludedProducts($products = array())
	{
		if (!is_array($products))
			$products = @json_decode(@base64_decode($products));
		if (is_array($products))
			$this->excludedProducts = $products;
		return $this;
	}

	/** @return array */
	function getProducts()
	{
		return $this->products;
	}

	/** @param $categories (array|string) as string, it should be a json base64 encoded array. */
	function setProducts($products = array())
	{
		if (!is_array($products))
			$products = @json_decode(@base64_decode($products));
		if (is_array($products))
			$this->products = $products;
		return $this;
	}


	function getData($min = false)
	{
		$prefix = $this->getDataKeyPrefix();
		$data = parent::getData();
		$data[$prefix . 'permanent'] = ($this->isPermanent() ? 'on' : '');
		$data[$prefix . 'autoapply'] = ($this->isAutoApply() ? 'on' : '');
		$data[$prefix . 'free_shipping'] = ($this->isFreeShipping() ? 'on' : '');
		$data[$prefix . 'limit_usage_to_x_items'] = $this->getItemsUsageLimit();
		$data[$prefix . 'product_cat'] = base64_encode(json_encode($this->getProductCategories()));
		$data[$prefix . 'exclude_cat'] = base64_encode(json_encode($this->getExcludedCategories()));
		$data[$prefix . 'products'] = base64_encode(json_encode($this->getProducts()));
		$data[$prefix . 'exclude_products'] = base64_encode(json_encode($this->getExcludedProducts()));
		return $this->filterData($data, $prefix, $min);
	}

	function getForm($context = 'editlist')
	{
		$prefix = $this->getDataKeyPrefix();
		$form = parent::getForm($context);
		$str  = '';

		// free shipping
		$label = _x("Also gives free shipping", "Coupon Unlockable", 'woorewards-pro');
		$toggle = \LWS\Adminpanel\Pages\Field\Checkbox::compose($prefix . 'free_shipping', array(
			'id'      => $prefix . 'free_shipping',
			'layout'  => 'toggle',
		));
		$str .= "<div class='lws-$context-opt-title label'>$label</div>";
		$str .= "<div class='lws-$context-opt-input value'>$toggle</div>";

		// autoapply on/off
		$label = _x("Auto-apply on next cart", "Coupon Unlockable", 'woorewards-pro');
		$toggle = \LWS\Adminpanel\Pages\Field\Checkbox::compose($prefix . 'autoapply', array(
			'id'      => $prefix . 'autoapply',
			'layout'  => 'toggle',
		));
		$str .= "<div class='lws-$context-opt-title label'>$label</div>";
		$str .= "<div class='lws-$context-opt-input value'>$toggle</div>";

		// permanent on/off
		$label = _x("Exclusive reward", "Coupon Unlockable", 'woorewards-pro');
		$tooltip = __("That reward will replace any previous Exclusive reward coupon reward of the same type owned by the customer.", 'woorewards-pro');
		$toggle = \LWS\Adminpanel\Pages\Field\Checkbox::compose($prefix . 'permanent', array(
			'id'      => $prefix . 'permanent',
			'layout'  => 'toggle',
		));
		$str .= "<div class='field-help'>$tooltip</div>";
		$str .= "<div class='lws-$context-opt-title label'>$label<div class='bt-field-help'>?</div></div>";
		$str .= "<div class='lws-$context-opt-input value'>$toggle</div>";

		// limit to X products
		$label = _x("Limit to X items", "Coupon Unlockable", 'woorewards-pro');
		$tooltip = __("If set, the coupon can only be applied on X items in the cart.", 'woorewards-pro');
		$str .= "<div class='field-help'>$tooltip</div>";
		$str .= "<div class='lws-$context-opt-title label'>$label<div class='bt-field-help'>?</div></div>";
		$str .= "<div class='lws-$context-opt-input value'><input type='text' id='{$prefix}limit_usage_to_x_items' name='{$prefix}limit_usage_to_x_items' placeholder='' pattern='\\d*(\\.|,)?\\d*' /></div>";

		$str .= $this->getFieldsetEnd(2);
		$form = str_replace($this->getFieldsetPlaceholder(false, 2), $str, $form);

		$form = $this->filterForm($form, $prefix, $context, 2);

		$form .= $this->getFieldsetBegin(3, __("Allow / Deny Product Categories", 'woorewards-pro'));

		// restriction by product category
		$label   = _x("Product categories", "Coupon Unlockable", 'woorewards-pro');
		$tooltip = __("Product categories that the coupon will be applied to, or that need to be in the cart in order for the 'Fixed cart discount' to be applied.", 'woorewards-pro');
		$form .= "<div class='field-help'>$tooltip</div>";
		$form .= "<div class='lws-$context-opt-title label'>$label<div class='bt-field-help'>?</div></div>";
		$form .= "<div class='lws-$context-opt-input value'>";
		$form .= \LWS\Adminpanel\Pages\Field\LacChecklist::compose($prefix . 'product_cat', array(
			'comprehensive' => true,
			'predefined' => 'taxonomy',
			'spec' => array('taxonomy' => 'product_cat'),
			'value' => $this->getProductCategories()
		));
		$form .= "</div>";

		// exclude product category
		$label   = _x("Excluded categories", "Coupon Unlockable", 'woorewards-pro');
		$tooltip = __("Product categories that the coupon will <b>not</b> be applied to, or that cannot be in the cart in order for the 'Fixed cart discount' to be applied.", 'woorewards-pro');
		$form .= "<div class='field-help'>$tooltip</div>";
		$form .= "<div class='lws-$context-opt-title label'>$label<div class='bt-field-help'>?</div></div>";
		$form .= "<div class='lws-$context-opt-input value'>";
		$form .= \LWS\Adminpanel\Pages\Field\LacChecklist::compose($prefix . 'exclude_cat', array(
			'comprehensive' => true,
			'predefined'    => 'taxonomy',
			'spec'          => array('taxonomy' => 'product_cat'),
			'value'         => $this->getExcludedCategories()
		));
		$form .= "</div>";
		$form .= $this->getFieldsetEnd(3);

		$form .= $this->getFieldsetBegin(4, __("Allow / Deny Products", 'woorewards-pro'));

		// restriction by products
		$label   = _x("Product(s)", "Coupon Unlockable", 'woorewards-pro');
		$tooltip = __("Product(s) to which the coupon will be applied to, or that need to be in the cart in order for the 'Fixed cart discount' to be applied.", 'woorewards-pro');
		$form .= "<div class='field-help'>$tooltip</div>";
		$form .= "<div class='lws-$context-opt-title label'>$label<div class='bt-field-help'>?</div></div>";
		$form .= "<div class='lws-$context-opt-input value'>";
		$form .= \LWS\Adminpanel\Pages\Field\LacChecklist::compose($prefix . 'products', array(
			'comprehensive' => true,
			'ajax'          => 'lws_woorewards_wc_products_and_variations_list',
		));
		$form .= "</div>";

		// exclude products
		$label   = _x("Excluded product(s)", "Coupon Unlockable", 'woorewards-pro');
		$tooltip = __("Product(s) that the coupon will <b>not</b> be applied to, or that cannot be in the cart in order for the 'Fixed cart discount' to be applied.", 'woorewards-pro');
		$form .= "<div class='field-help'>$tooltip</div>";
		$form .= "<div class='lws-$context-opt-title label'>$label<div class='bt-field-help'>?</div></div>";
		$form .= "<div class='lws-$context-opt-input value'>";
		$form .= \LWS\Adminpanel\Pages\Field\LacChecklist::compose($prefix . 'exclude_products', array(
			'comprehensive' => true,
			'class'         => 'above',
			'ajax'          => 'lws_woorewards_wc_products_and_variations_list',
		));
		$form .= "</div>";

		$form .= $this->getFieldsetEnd(4);
		return $form;
	}

	function submit($form = array(), $source = 'editlist')
	{
		$prefix = $this->getDataKeyPrefix();
		$values = \apply_filters('lws_adminpanel_arg_parse', array(
			'post'     => ($source == 'post'),
			'values'   => $form,
			'format'   => array(
				$prefix . 'autoapply'              => 's',
				$prefix . 'permanent'              => 's',
				$prefix . 'free_shipping'          => 's',
				$prefix . 'limit_usage_to_x_items' => 'd',
				$prefix . 'product_cat'            => array('D'),
				$prefix . 'exclude_cat'            => array('D'),
				$prefix . 'products'               => array('D'),
				$prefix . 'exclude_products'       => array('D')
			),
			'defaults' => array(
				$prefix . 'autoapply'              => '',
				$prefix . 'permanent'              => '',
				$prefix . 'free_shipping'          => '',
				$prefix . 'limit_usage_to_x_items' => '0',
				$prefix . 'product_cat'            => array(),
				$prefix . 'exclude_cat'            => array(),
				$prefix . 'products'               => array(),
				$prefix . 'exclude_products'       => array()
			),
			'labels'   => array(
				$prefix . 'autoapply'              => __("Auto-apply", 'woorewards-pro'),
				$prefix . 'permanent'              => __("Permanent", 'woorewards-pro'),
				$prefix . 'free_shipping'          => __("Free Shipping", 'woorewards-pro'),
				$prefix . 'limit_usage_to_x_items' => __("Limit usage to X items", 'woorewards-pro'),
				$prefix . 'product_cat'            => __("Product category", 'woorewards-pro'),
				$prefix . 'exclude_cat'            => __("Excluded category", 'woorewards-pro'),
				$prefix . 'products'               => __("Product(s)", 'woorewards-pro'),
				$prefix . 'exclude_products'       => __("Excluded product(s)", 'woorewards-pro')
			)
		));
		if (!(isset($values['valid']) && $values['valid']))
			return isset($values['error']) ? $values['error'] : false;

		$valid = parent::submit($form, $source);
		if ($valid === true && ($valid = $this->optSubmit($prefix, $form, $source)) === true) {
			$this->setPermanent($values['values'][$prefix . 'permanent']);
			$this->setAutoApply($values['values'][$prefix . 'autoapply']);
			$this->setFreeShipping($values['values'][$prefix . 'free_shipping']);
			$this->setItemsUsageLimit($values['values'][$prefix . 'limit_usage_to_x_items']);
			$this->setProductCategories($values['values'][$prefix . 'product_cat']);
			$this->setExcludedCategories($values['values'][$prefix . 'exclude_cat']);
			$this->setProducts($values['values'][$prefix . 'products']);
			$this->setExcludedProducts($values['values'][$prefix . 'exclude_products']);
		}
		return $valid;
	}

	protected function _fromPost(\WP_Post $post)
	{
		$this->setPermanent(\get_post_meta($post->ID, 'woorewards_permanent', true));
		$this->setAutoApply(\get_post_meta($post->ID, 'woorewards_autoapply', true));
		$this->setFreeShipping(\get_post_meta($post->ID, 'free_shipping', true));
		$this->setProductCategories(\get_post_meta($post->ID, 'wre_unlockable_product_cat', true));
		$this->setExcludedCategories(\get_post_meta($post->ID, 'wre_unlockable_exclude_cat', true));
		$this->setProducts(\get_post_meta($post->ID, 'wre_unlockable_products', true));
		$this->setExcludedProducts(\get_post_meta($post->ID, 'wre_unlockable_exclude_products', true));
		$this->setItemsUsageLimit(\get_post_meta($post->ID, 'limit_usage_to_x_items', true));
		$this->optFromPost($post);
		return parent::_fromPost($post);
	}

	protected function _save($id)
	{
		\update_post_meta($id, 'woorewards_permanent', $this->isPermanent() ? 'on' : '');
		\update_post_meta($id, 'woorewards_autoapply', $this->isAutoApply() ? 'on' : '');
		\update_post_meta($id, 'free_shipping', $this->isFreeShipping() ? 'on' : '');
		\update_post_meta($id, 'wre_unlockable_product_cat', $this->getProductCategories());
		\update_post_meta($id, 'wre_unlockable_exclude_cat', $this->getExcludedCategories());
		\update_post_meta($id, 'wre_unlockable_products', $this->getProducts());
		\update_post_meta($id, 'wre_unlockable_exclude_products', $this->getExcludedProducts());
		\update_post_meta($id, 'limit_usage_to_x_items', $this->getItemsUsageLimit());
		$this->optSave($id);
		return parent::_save($id);
	}

	protected function getProductCategoriesNames($categories, $context = 'backend', $sep = ', ')
	{
		$names = array();
		foreach ($categories as $cat) {
			$term = \get_term($cat, 'product_cat');
			if (!$term || is_wp_error($term)) {
				$names[] = _x("Unknown", "Unknown category", 'woorewards-pro');
			} else {
				$url = ($context == 'backend' ? \esc_attr(\get_edit_tag_link($cat, 'product_cat')) : '');
				if (!$url) {
					if ($context == 'raw')
						$names[] = $term->name;
					else
						$names[] = "<b>{$term->name}</b>";
				} else {
					$names[] = "<a href='{$url}'>{$term->name}</a>";
				}
			}
		}
		return \implode($sep, $names);
	}

	/**	Provided to be overriden.
	 *	@param $context usage of text. Default is 'backend' for admin, expect 'frontend' for customer.
	 *	@return (string) what this does. */
	function getDescription($context = 'backend')
	{
		$descr = parent::getDescription($context);
		if ($this->isPermanent()) {
			$attr = _x("permanent", "Coupon", 'woorewards-pro');
			$descr .= $context == 'edit' ? " ($attr)" : " (<i>$attr</i>)";
		}

		if (!empty($discount = $this->getPartialDescription($context)))
			$descr .= (', ' . $discount);

		$categories = $this->getProductCategories();
		if (!empty($categories)) {
			$msg = _n("Apply only on category %s", "Apply only on categories %s", count($categories), 'woorewards-pro');
			$descr .= sprintf('<br/>' . $msg, $this->getProductCategoriesNames($categories, $context));
		}
		$categories = $this->getExcludedCategories();
		if (!empty($categories)) {
			$msg = _n("Exclude category %s", "Exclude categories %s", count($categories), 'woorewards-pro');
			$descr .= sprintf('<br/>' . $msg, $this->getProductCategoriesNames($categories, $context));
		}
		return $descr;
	}

	protected function buildCouponPostData($code, \WP_User $user)
	{
		$coupon = parent::buildCouponPostData($code, $user);
		$props = array();
		$props['usage_limit'] = $this->getUsageLimit();

		if ($this->getItemsUsageLimit() > 0)
			$props['limit_usage_to_x_items'] = $this->getItemsUsageLimit();

		if ($this->isFreeShipping())
			$props['free_shipping'] = true;

		$categories = $this->getProductCategories();
		if ($categories)
			$props['product_categories'] = array_filter(array_map('intval', $categories));

		$categories = $this->getExcludedCategories();
		if ($categories)
			$props['excluded_product_categories'] = array_filter(array_map('intval', $categories));

		$products = $this->getProducts();
		if ($products)
			$props['product_ids'] = array_filter(array_map('intval', $products));

		$products = $this->getExcludedProducts();
		if ($products)
			$props['excluded_product_ids'] = array_filter(array_map('intval', $products));

		if (!empty($props))
			$coupon->set_props($props);
		return $this->filterCouponPostData($coupon, $code, $user);
	}

	protected function createShopCoupon($code, \WP_User $user, $demo = false)
	{
		$coupon = parent::createShopCoupon($code, $user, $demo);
		if (!$demo && $this->isPermanent() && $coupon && !empty($coupon->get_id())) {
			$this->setPermanentcoupon($coupon, $user, $this->getType(), $this->getPoolId());
		}
		$this->applyOnCoupon($coupon, $user, $this->getPoolId(), $demo);
		if (!$demo && $this->isAutoApply() && $coupon && !empty($coupon->get_id())) {
			\update_post_meta($coupon->get_id(), 'lws_woorewards_auto_apply', 'on');
		}
		return $coupon;
	}

	protected function grantExclCat()
	{
		return false;
	}
}