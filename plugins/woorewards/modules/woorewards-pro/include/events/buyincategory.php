<?php

namespace LWS\WOOREWARDS\PRO\Events;

// don't call the file directly
if (!defined('ABSPATH')) exit();

/** Earn points when a bought product belong to a specified category. */
class BuyInCategory extends \LWS\WOOREWARDS\Abstracts\Event
implements \LWS\WOOREWARDS\PRO\Events\I_CartPreview
{
	use \LWS\WOOREWARDS\PRO\Events\T_ExcludedProducts;
	use \LWS\WOOREWARDS\PRO\Events\T_Order;
	use \LWS\WOOREWARDS\PRO\Events\T_SponsorshipOrigin;

	protected $qtyMultiply       = false;
	protected $minProductCount   = 1;
	protected $productCategories = array();
	protected $eventPriority     = 50;

	public function isMaxTriggersAllowed()
	{
		return true;
	}

	function getInformation()
	{
		return array_merge(parent::getInformation(), array(
			'icon'  => 'lws-icon-shopping-tag',
			'short' => __("The customer will earn points for buying products from specific categories.", 'woorewards-pro'),
			'help'  => __("The number of points given is fixed for every product.", 'woorewards-pro'),
		));
	}

	function getDescription($context = 'backend')
	{
		$categories = $this->getProductCategories();
		$msg = '';
		if ($this->getMinProductCount() > 1)
		{
			$msg = _n('Buy at least %2$d products in category %1$s', 'Buy at least %2$d products in categories %1$s', count($categories), 'woorewards-pro');
			return sprintf($msg, $this->getProductCategoriesNames($categories, $context), $this->getMinProductCount());
		}
		else
		{
			$msg = _n("Buy products in category %s", "Buy products in categories %s", count($categories), 'woorewards-pro');
			return sprintf($msg, $this->getProductCategoriesNames($categories, $context));
		}
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

	/** add help about how it works */
	function getForm($context = 'editlist')
	{
		$prefix = $this->getDataKeyPrefix();
		$form = parent::getForm($context);

		// just hidden since we do not want to reset the value on save
		$noPri = (\get_option('lws_woorewards_show_loading_order_and_priority') ? '' : ' style="display: none;"');
		$label = __("Priority", 'woorewards-pro');
		$tooltip = __("Customer orders will run by ascending priority value.", 'woorewards-pro');
		$str = <<<EOT
		<div class='field-help'{$noPri}>$tooltip</div>
		<div class='lws-$context-opt-title label'{$noPri}>$label<div class='bt-field-help'>?</div></div>
		<div class='lws-$context-opt-input value'{$noPri}>
			<input type='text' id='{$prefix}event_priority' name='{$prefix}event_priority' placeholder='10' size='5' />
		</div>
EOT;
		$phb0 = $this->getFieldsetPlaceholder(false, 0);
		$form = str_replace($phb0, $str . $phb0, $form);

		$form .= $this->getFieldsetBegin(2, __("Options", 'woorewards-pro'));
		// multiply by quantity
		$label   = _x("Quantity Multiplier", "Buy In Category Event", 'woorewards-pro');
		if ($context == 'achievements')
		{
			$tooltip = __("If checked, action will be counted once per product in the cart meeting the conditions. Otherwise, only once per order.", 'woorewards-pro');
		}
		else
		{
			$tooltip = __("If checked, points will be earned for each product in the cart meeting the conditions. Otherwise, points will be earned only once per order", 'woorewards-pro');
		}
		$toggle = \LWS\Adminpanel\Pages\Field\Checkbox::compose($prefix . 'qty_multiply', array(
			'id'      => $prefix . 'qty_multiply',
			'layout'  => 'toggle',
			'checked' => $this->isQtyMultiply() ? 'checked' : ''
		));

		$form .= "<div class='field-help'>$tooltip</div>";
		$form .= "<div class='lws-$context-opt-title label'>$label<div class='bt-field-help'>?</div></div>";
		$form .= "<div class='lws-$context-opt-input value'>{$toggle}</div>";

		// value
		$label = _x("Minimum product count", "Coupon Unlockable", 'woorewards-pro');
		$value = \esc_attr($this->getMinProductCount());
		$form .= "<div class='lws-$context-opt-title label'>$label</div>";
		$form .= "<div class='lws-$context-opt-input value'><input type='text' id='{$prefix}product_count' name='{$prefix}product_count' value='$value' placeholder='1' /></div>";

		$form .= $this->getFieldsetEnd(2);

		$form .= $this->getFieldsetBegin(3, __("Product categories", 'woorewards-pro'));

		// The product category
		$label   = _x("Categories", "Buy In Category Event", 'woorewards-pro');
		$form .= "<div class='lws-$context-opt-title label'>$label</div>";
		$form .= "<div class='lws-$context-opt-input value'>";
		$form .= \LWS\Adminpanel\Pages\Field\LacChecklist::compose($prefix . 'product_cat', array(
			'comprehensive' => true,
			'predefined' => 'taxonomy',
			'spec' => array('taxonomy' => 'product_cat'),
			'value' => $this->getProductCategories()
		));
		$form .= "</div>";
		$form .= $this->getFieldsetEnd(3);
		$form =  $this->filterForm($form, $prefix, $context, 2);
		return $this->filterSponsorshipForm($form, $prefix, $context, 10);
	}

	function getData()
	{
		$prefix = $this->getDataKeyPrefix();
		$data = parent::getData();
		$data[$prefix . 'product_cat'] = base64_encode(json_encode($this->getProductCategories()));
		$data[$prefix . 'product_count'] = $this->getMinProductCount();
		$data[$prefix . 'qty_multiply'] = $this->isQtyMultiply() ? 'on' : '';
		$data[$prefix . 'event_priority'] = $this->getEventPriority();
		$data = $this->filterSponsorshipData($data, $prefix);
		return $this->filterData($data, $prefix);
	}

	function submit($form = array(), $source = 'editlist')
	{
		$prefix = $this->getDataKeyPrefix();
		$values = \apply_filters('lws_adminpanel_arg_parse', array(
			'post'     => ($source == 'post'),
			'values'   => $form,
			'format'   => array(
				$prefix . 'product_cat' => array('D'),
				$prefix . 'product_count' => 'D',
				$prefix . 'qty_multiply' => 's',
				$prefix . 'event_priority'   => 'd',
			),
			'defaults' => array(
				$prefix . 'product_count' => '1',
				$prefix . 'qty_multiply' => '',
				$prefix . 'event_priority'   => $this->getEventPriority(),
			),
			'required' => array(
				$prefix . 'product_cat' => true
			),
			'labels'   => array(
				$prefix . 'product_cat'   => __("Product category", 'woorewards-pro'),
				$prefix . 'product_count' => __("Minimum product count", 'woorewards-pro'),
				$prefix . 'qty_multiply' => __("Quantity Multiplier", 'woorewards-pro'),
				$prefix . 'event_priority'   => __("Event Priority", 'woorewards-pro'),
			)
		));
		if (!(isset($values['valid']) && $values['valid']))
			return isset($values['error']) ? $values['error'] : false;

		$valid = parent::submit($form, $source);
		if ($valid === true)
			$valid = $this->optSponsorshipSubmit($prefix, $form, $source);
		if ($valid === true && ($valid = $this->optSubmit($prefix, $form, $source)) === true)
		{
			$this->setProductCategories($values['values'][$prefix . 'product_cat']);
			$this->setMinProductCount($values['values'][$prefix . 'product_count']);
			$this->setQtyMultiply($values['values'][$prefix . 'qty_multiply']);
			$this->setEventPriority($values['values'][$prefix . 'event_priority']);
		}

		return $valid;
	}

	/** @return bool */
	function isQtyMultiply()
	{
		return $this->qtyMultiply;
	}

	public function setQtyMultiply($yes = false)
	{
		$this->qtyMultiply = boolval($yes);
		return $this;
	}

	/** @return int */
	function getMinProductCount()
	{
		return $this->minProductCount;
	}

	function setMinProductCount($n)
	{
		$this->minProductCount = max(1, intval($n));
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

	protected function _fromPost(\WP_Post $post)
	{
		$this->setProductCategories(\get_post_meta($post->ID, 'wre_event_product_cat', true));
		$this->setMinProductCount(\get_post_meta($post->ID, 'wre_event_min_product_count', true));
		$this->setQtyMultiply(\get_post_meta($post->ID, 'wre_event_qty_multiply', true));
		$this->setEventPriority($this->getSinglePostMeta($post->ID, 'wre_event_priority', $this->getEventPriority()));
		$this->optSponsorshipFromPost($post);
		$this->optFromPost($post);
		return $this;
	}

	protected function _save($id)
	{
		\update_post_meta($id, 'wre_event_product_cat', $this->getProductCategories());
		\update_post_meta($id, 'wre_event_min_product_count', $this->getMinProductCount());
		\update_post_meta($id, 'wre_event_qty_multiply', $this->isQtyMultiply() ? 'on' : '');
		\update_post_meta($id, 'wre_event_priority', $this->getEventPriority());
		$this->optSponsorshipSave($id);
		$this->optSave($id);
		return $this;
	}

	/** @return string a human readable type for UI */
	public function getDisplayType()
	{
		return _x("Buy in category", "getDisplayType", 'woorewards-pro');
	}

	function getEventPriority()
	{
		return $this->eventPriority;
	}

	public function setEventPriority($priority)
	{
		$this->eventPriority = \intval($priority);
		return $this;
	}

	/** Add hook to grab events and add points. */
	protected function _install()
	{
		\add_filter('lws_woorewards_wc_order_done_' . $this->getPoolName(), array($this, 'orderDone'), $this->getEventPriority());
	}

	function orderDone($order)
	{
		if (!$this->acceptOrder($order->order))
			return $order;
		if (!$this->isValidOriginByOrder($order->order))
			return $order;
		if(!$this->isValidCurrency($order->order))
			return $order;
		if (empty($categories = $this->getProductCategories()))
			return $order;
		$userId = $this->getPointsRecipient($order->order);
		if (!$userId)
			return $order;

		$noEarning = $this->getExclusionFromOrder($order->order);
		$floor = $this->getMinProductCount();
		$boughtInCat = 0;
		foreach ($order->items as $item)
		{
			$product = \LWS\WOOREWARDS\PRO\Conveniences::instance()->getProductFromOrderItem($order->order, $item->item);
			if ($product && $this->isProductInCategory($product, $categories))
			{
				$qty = $item->item->get_quantity();
				$qty = $this->useExclusion($noEarning, $product, $qty);
				$boughtInCat += $qty;
			}
		}
		if ($boughtInCat < $floor)
			return $order;

		//Take quantity ordered into account or not
		if ($pointsCount = \apply_filters('trigger_' . $this->getType(), $this->isQtyMultiply() ? $boughtInCat : 1, $this, $order->order)) {
			$this->addPoint($this->getGainInfo(array(
				'user'  => $userId,
				'order' => $order->order,
			), $order), $this->getPointsReason($order->order, $categories), $pointsCount);
		}
		return $order;
	}

	/** @param $info (array) */
	protected function getGainInfo($info, $order)
	{
		return $info;
	}

	/** @param $order (\WC_Order)
	 * @return (int) user ID */
	function getPointsRecipient($order)
	{
		return \LWS\Adminpanel\Tools\Conveniences::getCustomerId(false, $order);
	}

	function getPointsReason($order, $categories)
	{
		return \LWS\WOOREWARDS\Core\Trace::byOrder($order)
			->setProvider($order->get_customer_id('edit'))
			->setReason(
				array(
					(1 == count($categories) ? "Product bought in category %s" : "Product bought in categories %s"),
					$this->getProductCategoriesNames($categories, 'raw')
				),
				'woorewards-pro'
			);
	}

	/** Never call, only to have poedit/wpml able to extract the sentance. */
	private function poeditDeclare()
	{
		__("Product bought in category %s", 'woorewards-pro');
		__("Product bought in categories %s", 'woorewards-pro');
	}

	private function isProductInCategory($product, $whiteList, $taxonomy = 'product_cat')
	{
		$product_cats = \wc_get_product_cat_ids($product->get_id());
		if (!empty($parentId = $product->get_parent_id()))
			$product_cats = array_merge($product_cats, \wc_get_product_cat_ids($parentId));

		// If we find an item with a cat in our allowed cat list, the product is valid.
		return !empty(array_intersect($product_cats, $whiteList));
	}

	/**	Event categories, used to filter out events from pool options.
	 *	@return array with category_id => category_label. */
	public function getCategories()
	{
		return array_merge(parent::getCategories(), array(
			'woocommerce' => __("WooCommerce", 'woorewards-pro'),
			'order'  => __("Order", 'woorewards-pro')
		));
	}

	function getPointsForProduct(\WC_Product $product)
	{
		if (!$this->acceptOrigin('checkout'))
			return 0;
		if (!\WC()->cart)
			return 0;
		if (!$this->acceptCart(\WC()->cart))
			return 0;
		if (!$this->isValidCurrentSponsorship())
			return 0;
		if(!$this->isValidCurrency())
			return 0;

		if ($this->getMinProductCount() == 1 && !empty($categories = $this->getProductCategories()))
		{
			if ($this->isProductInCategory($product, $categories)) {
				return (int)$this->getFinalGain(1, array(
					'user'  => \LWS\Adminpanel\Tools\Conveniences::getCustomer(\wp_get_current_user(), \WC()->cart),
					'order' => \WC()->cart,
				), true);
			}
		}
		return 0;
	}

	protected function shapeGain($value)
	{
		if ($this->isQtyMultiply()) {
			if ('=' == substr($value, 0, 1)) {
				$value = sprintf(_x("[%s] / item", "Points per item", 'woorewards-pro'), $value);
			} elseif (\is_numeric($value) && $value > 0) {
				$value = sprintf(
					_x("%s / item", "Points per item", 'woorewards-pro'),
					\LWS_WooRewards::formatPointsWithSymbol($value, $this->getPoolName())
				);
			}
		}
		return parent::shapeGain($value);
	}

	function getPointsForCart(\WC_Cart $cart)
	{
		if (!$this->acceptOrigin('checkout'))
			return 0;
		if (!$this->acceptCart($cart))
			return 0;
		if (!$this->isValidCurrentSponsorship())
			return 0;
		if (!$this->isValidCurrency())
			return 0;

		if (!empty($categories = $this->getProductCategories()))
		{
			$noEarning = $this->getExclusionFromCart($cart);
			$floor = $this->getMinProductCount();
			$boughtInCat = 0;
			foreach ($cart->get_cart() as $item)
			{
				if (isset($item['product_id']) && !empty($product = \wc_get_product($item['product_id'])))
				{
					if ($this->isProductInCategory($product, $categories))
					{
						$qty = isset($item['quantity']) ? intval($item['quantity']) : 1;
						$qty = $this->useExclusion($noEarning, $product, $qty);
						$boughtInCat += $qty;
					}
				}
			}
			if ($boughtInCat >= $floor)
			{
				return $this->getFinalGain(
					$this->isQtyMultiply() ? $boughtInCat : 1,
					array(
						'user'  => \LWS\Adminpanel\Tools\Conveniences::getCustomer(\wp_get_current_user(), $cart),
						'order' => $cart,
					),
					true
				);
			}
		}
		return 0;
	}

	function getPointsForOrder(\WC_Order $order)
	{
		if (!$this->acceptOrder($order))
			return 0;
		if (!$this->isValidOriginByOrder($order))
			return 0;
		if(!$this->isValidCurrency($order))
			return 0;

		if (!empty($categories = $this->getProductCategories()))
		{
			$noEarning = $this->getExclusionFromOrder($order);
			$floor = $this->getMinProductCount();
			$boughtInCat = 0;
			foreach ($order->get_items() as $item)
			{
				if (isset($item['product_id']) && !empty($product = \wc_get_product($item['product_id'])))
				{
					if ($this->isProductInCategory($product, $categories))
					{
						$qty = isset($item['quantity']) ? intval($item['quantity']) : 1;
						$qty = $this->useExclusion($noEarning, $product, $qty);
						$boughtInCat += $qty;
					}
				}
			}
			if ($boughtInCat >= $floor)
			{
				return $this->getFinalGain(
					$this->isQtyMultiply() ? $boughtInCat : 1,
					array(
						'user'  => \LWS\Adminpanel\Tools\Conveniences::getCustomer(false, $order),
						'order' => $order,
					),
					true
				);
			}
		}
		return 0;
	}
}