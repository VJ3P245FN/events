<?php

namespace LWS\WOOREWARDS\PRO\Events;

// don't call the file directly
if (!defined('ABSPATH')) exit();

/** Earn points when a specified product is bought. */
class BuySpecificProduct extends \LWS\WOOREWARDS\Abstracts\Event
implements \LWS\WOOREWARDS\PRO\Events\I_CartPreview
{
	use \LWS\WOOREWARDS\PRO\Events\T_ExcludedProducts;
	use \LWS\WOOREWARDS\PRO\Events\T_Order;
	use \LWS\WOOREWARDS\PRO\Events\T_SponsorshipOrigin;

	protected $eventPriority = 40;
	protected $productsIds   = array();
	protected $qtyMultiply   = false;

	public function isMaxTriggersAllowed()
	{
		return true;
	}

	function getInformation()
	{
		return array_merge(parent::getInformation(), array(
			'icon'  => 'lws-icon-barcode',
			'short' => __("The customer earns points when he buys a specified product.", 'woorewards-pro'),
		));
	}

	function getDescription($context = 'backend')
	{
		$names = array();
		foreach ($this->getProductsIds() as $id)
		{
			if( $context == 'backend' )
				$names[] = $this->getProductEditLink($id);
			else if( $context == 'raw' )
				$names[] = $this->getProductName($id);
			else
				$names[] = $this->getProductLink($id);
		}
		if( count($names) > 1 )
			return sprintf(__("Buy products %s", 'woorewards-pro'), implode(', ', $names));
		else
			return sprintf(__("Buy product %s", 'woorewards-pro'), implode(', ', $names));
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

		$form .= $this->getFieldsetBegin(2, __("Product(s) to buy", 'woorewards-pro'));

		// product
		$label = _x("Product(s)", "Coupon Unlockable", 'woorewards-pro');
		$form .= "<div class='lws-$context-opt-title label'>$label</div>";
		$form .= "<div class='lws-$context-opt-input value'>";
		$form .= \LWS\Adminpanel\Pages\Field\LacChecklist::compose($prefix . 'product_ids', array(
			'comprehensive' => true,
			'ajax' => 'lws_woorewards_wc_products_and_variations_list',
		));
		$form .= "</div>";

		// multiply by quantity
		$label   = _x("Quantity Multiplier", "Buy Specific Product Event", 'woorewards-pro');
		if ($context == 'achievements')
			$tooltip = __("If checked, action will be counted once per bought product. Otherwise, only once per order containing the product.", 'woorewards-pro');
		else
			$tooltip = __("If checked, points will be earned for each product in the cart meeting the conditions. Otherwise, points will be earned only once per order", 'woorewards-pro');
		$toggle = \LWS\Adminpanel\Pages\Field\Checkbox::compose($prefix . 'qty_multiply', array(
			'id'      => $prefix . 'qty_multiply',
			'layout'  => 'toggle',
			'checked' => $this->isQtyMultiply() ? 'checked' : ''
		));
		$form .= "<div class='field-help'>$tooltip</div>";
		$form .= "<div class='lws-$context-opt-title label'>$label<div class='bt-field-help'>?</div></div>";
		$form .= "<div class='lws-$context-opt-input value'>$toggle</div>";

		$form .= $this->getFieldsetEnd(2);
		$form =  $this->filterForm($form, $prefix, $context);
		return $this->filterSponsorshipForm($form, $prefix, $context, 10);
	}

	function getData()
	{
		$prefix = $this->getDataKeyPrefix();
		$data = parent::getData();
		$data[$prefix . 'product_ids'] = \base64_encode(\json_encode($this->getProductsIds()));
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
				$prefix . 'product_ids'    => array('D'),
				$prefix . 'qty_multiply'   => 's',
				$prefix . 'event_priority' => 'd',
			),
			'defaults' => array(
				$prefix.'product_ids'      => array(),
				$prefix . 'qty_multiply'   => '',
				$prefix . 'event_priority' => $this->getEventPriority(),
			),
			'labels'   => array(
				$prefix . 'product_ids'    => __("Product(s)", 'woorewards-pro'),
				$prefix . 'qty_multiply'   => __("Quantity Multiplier", 'woorewards-pro'),
				$prefix . 'event_priority' => __("Event Priority", 'woorewards-pro'),
			)
		));
		if (!(isset($values['valid']) && $values['valid']))
			return isset($values['error']) ? $values['error'] : false;

		$valid = parent::submit($form, $source);
		if ($valid === true)
			$valid = $this->optSponsorshipSubmit($prefix, $form, $source);
		if ($valid === true && ($valid = $this->optSubmit($prefix, $form, $source)) === true)
		{
			$this->setProductsIds($values['values'][$prefix . 'product_ids']);
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

	/** @return array */
	public function getProductsIds()
	{
		return $this->productsIds;
	}

	/** @return (false|\WC_Product) */
	public function getProduct($id)
	{
		if (\LWS\Adminpanel\Tools\Conveniences::isWC() && $id)
			return \wc_get_product($id);
		return false;
	}

	/** @return false|string */
	public function getProductName($id)
	{
		if ($product = $this->getProduct($id))
			return $product->get_name();
		return false;
	}

	/** @return false|string */
	public function getProductUrl($id)
	{
		if ($product = $this->getProduct($id))
			return \get_permalink($product->get_id());
		return false;
	}

	/** @return string html <a> */
	public function getProductLink($id)
	{
		if ($product = $this->getProduct($id))
		{
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
		if ($product = $this->getProduct($id))
		{
			// use a variation Id leads get_edit_post_link() to return current page url.
			$postId = $product->is_type('variation') ? $product->get_parent_id() : $product->get_id();
			return sprintf(
				"<a href='%s'>%s</a>",
				\esc_attr(\get_edit_post_link($postId)),
				$product->get_name()
			);
		}
		return false;
	}

	public function setProductsIds($ids = array())
	{
		if (!is_array($ids))
		{
			if (is_numeric($ids)){
				$ids = array($ids);
			}else{
				$ids = @json_decode(@base64_decode($ids));
			}
		}
		if (is_array($ids))
		{
			$this->productsIds = array_map('absint', $ids);
		}
		return $this;
	}

	protected function _fromPost(\WP_Post $post)
	{
		$this->setProductsIds(\get_post_meta($post->ID, 'wre_event_product_id', true));
		$this->setQtyMultiply(\get_post_meta($post->ID, 'wre_event_qty_multiply', true));
		$this->setEventPriority($this->getSinglePostMeta($post->ID, 'wre_event_priority', $this->getEventPriority()));
		$this->optSponsorshipFromPost($post);
		$this->optFromPost($post);
		return $this;
	}

	protected function _save($id)
	{
		\update_post_meta($id, 'wre_event_product_id', $this->getProductsIds());
		\update_post_meta($id, 'wre_event_qty_multiply', $this->isQtyMultiply() ? 'on' : '');
		\update_post_meta($id, 'wre_event_priority', $this->getEventPriority());
		$this->optSponsorshipSave($id);
		$this->optSave($id);
		return $this;
	}

	/** @return string a human readable type for UI */
	public function getDisplayType()
	{
		return _x("Buy specific products", "getDisplayType", 'woorewards-pro');
	}

	/** @return int */
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
		$userId = $this->getPointsRecipient($order->order);
		if (!$userId)
			return $order;

		$pIds = $this->getProductsIdsWithTranslated();
		if (!$pIds)
			return $order;

		$noEarning = $this->getExclusionFromOrder($order->order);
		$name = array();
		$boughtCount = 0;
		foreach ($order->items as $item)
		{
			$product = \LWS\WOOREWARDS\PRO\Conveniences::instance()->getProductFromOrderItem($order->order, $item->item);
			if( $product && $this->isProductInList($product, $pIds) )
			{
				$qty = $item->item->get_quantity();
				$qty = $this->useExclusion($noEarning, $product, $qty);
				if ($qty > 0)
				{
					$name[] = $product->get_name();
					$boughtCount += $qty;
				}
			}
		}

		if ($boughtCount > 0)
		{
			$pointsCount = ($this->isQtyMultiply() ? $boughtCount : 1);
			if ($pointsCount = \apply_filters('trigger_' . $this->getType(), $pointsCount, $this, $order->order)) {
				$this->addPoint($this->getGainInfo(array(
					'user'  => $userId,
					'order' => $order->order,
				), $order), $this->getPointsReason($order->order, $name), $pointsCount);
			}
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

	function getPointsReason($order, $name)
	{
		if ($name) {
			$reason = array('Bought a %1$s on order %2$s', implode(', ', $name), $order->get_order_number());
		} else {
			$reason = array('Bought specific products on order %s', $order->get_order_number());
		}
		return \LWS\WOOREWARDS\Core\Trace::byOrder($order)
			->setProvider($order->get_customer_id('edit'))
			->setReason($reason, 'woorewards-pro');
	}

	/** Never call, only to have poedit/wpml able to extract the sentance. */
	private function poeditDeclare()
	{
		__('Bought specific products on order %s', 'woorewards-pro');
		__('Bought a %1$s on order %2$s', 'woorewards-pro');
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

	function getProductsIdsWithTranslated()
	{
		$pIds = $this->getProductsIds();
		if (!$pIds)
			return array();

		$ids = $pIds;
		foreach($pIds as $id)
		{
			$tr = \apply_filters('wpml_object_id', $id, 'product', true);
			if ($tr != $id)
				$ids[] = $tr;
		}
		return $ids;
	}

	function isProductInList($product, $pIds=false)
	{
		if( false === $pIds )
			$pIds = $this->getProductsIdsWithTranslated();
		if( !$pIds )
			return false;

		$id = $product->get_id();
		if (\in_array($id, $pIds))
			return true;
		$tr = \apply_filters('wpml_object_id', $id, 'product', true);
		if ($tr != $id && \in_array($tr, $pIds))
			return true;

		if ($product->is_type('variation'))
		{
			$id = $product->get_parent_id();
			if (\in_array($id, $pIds))
				return true;
			$tr = \apply_filters('wpml_object_id', $id, 'product', true);
			if ($tr != $id && \in_array($tr, $pIds))
				return true;
		}

		return false;
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

		if ($this->isProductInList($product)) {
			return $this->getFinalGain(1, array(
				'user'  => \LWS\Adminpanel\Tools\Conveniences::getCustomer(\wp_get_current_user(), \WC()->cart),
				'order' => \WC()->cart,
			), true);
		}

		if ($product->is_type('variable')) {
			$pIds = $this->getProductsIdsWithTranslated();
			if( !$pIds ) {
				return 0;
			}
			$found=false;
			$notall=false;
			foreach($product->get_children() as $childId){
				if (\in_array($childId, $pIds)) {
					$found=true;
				} else {
					$tr = \apply_filters('wpml_object_id', $childId, 'product', true);
					if ($tr != $childId && \in_array($tr, $pIds)){
						$found=true;
					} else {
						$notall = true;
					}
				}
			}
			if(!$found){
				return 0;
			}
			if($notall) {
				return array(0, $this->getFinalGain(1, array(
					'user'  => \LWS\Adminpanel\Tools\Conveniences::getCustomer(\wp_get_current_user(), \WC()->cart),
					'order' => \WC()->cart,
				), true));
			}
			return (int)$this->getFinalGain(1, array(
				'user'  => \LWS\Adminpanel\Tools\Conveniences::getCustomer(\wp_get_current_user(), \WC()->cart),
				'order' => \WC()->cart,
			), true);
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

		$pIds = $this->getProductsIdsWithTranslated();
		if (!$pIds)
			return 0;

		$noEarning = $this->getExclusionFromCart($cart);
		$inCartQty = 0;

		foreach ($cart->get_cart() as $item)
		{
			$product = false;
			if (isset($item['variation_id']) && $item['variation_id'])
				$product = \wc_get_product($item['variation_id']);
			if (!$product && isset($item['product_id']) && $item['product_id'])
				$product = \wc_get_product($item['product_id']);

			if ($product)
			{
				if ($this->isProductInList($product, $pIds))
				{
					$qty = isset($item['quantity']) ? intval($item['quantity']) : 1;
					$qty = $this->useExclusion($noEarning, $product, $qty);
					if ($qty > 0)
						$inCartQty += $qty;
				}
			}
		}

		if ($inCartQty > 0) {
			return $this->getFinalGain(
				$this->isQtyMultiply() ? $inCartQty : 1,
				array(
					'user'  => \LWS\Adminpanel\Tools\Conveniences::getCustomer(\wp_get_current_user(), $cart),
					'order' => $cart,
				),
				true
			);
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

		$pIds = $this->getProductsIdsWithTranslated();
		if (!$pIds)
			return 0;

		$noEarning = $this->getExclusionFromOrder($order);
		$inOrderQty = 0;

		foreach ($order->get_items() as $item)
		{
			$product = \LWS\WOOREWARDS\PRO\Conveniences::instance()->getProductFromOrderItem($order, $item);
			if ($product && $this->isProductInList($product, $pIds))
			{
				$qty = $item->get_quantity();
				$qty = $this->useExclusion($noEarning, $product, $qty);
				if ($qty > 0)
					$inOrderQty += $qty;
			}
		}

		if ($inOrderQty > 0) {
			return $this->getFinalGain(
				$this->isQtyMultiply() ? $inOrderQty : 1,
				array(
					'user'  => \LWS\Adminpanel\Tools\Conveniences::getCustomer(false, $order),
					'order' => $order,
				),
				true
			);
		}
		return 0;
	}
}