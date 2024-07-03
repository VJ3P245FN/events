<?php

namespace LWS\WOOREWARDS\PRO\Events;

// don't call the file directly
if (!defined('ABSPATH')) exit();


/** Earn points for each money spend on an order. */
class OrderAmount extends \LWS\WOOREWARDS\Events\OrderAmount
implements \LWS\WOOREWARDS\PRO\Events\I_CartPreview
{
	use \LWS\WOOREWARDS\PRO\Events\T_ExcludedProducts;
	use \LWS\WOOREWARDS\PRO\Events\T_Order;
	use \LWS\WOOREWARDS\PRO\Events\T_SponsorshipOrigin;

	protected $minAmount                 = 0;
	protected $onsales                   = '';
	protected $productExcludedCategories = array();
	protected $productCategories         = array();

	public function isMaxTriggersAllowed()
	{
		return true;
	}

	function getDescription($context = 'backend')
	{
		$descr = parent::getDescription($context);
		if (($min = $this->getMinAmount()) > 0.0)
		{
			$dec = \absint(\apply_filters('wc_get_price_decimals', \get_option('woocommerce_price_num_decimals', 2)));
			$descr .= sprintf(__(" (amount greater than %s)", 'woorewards-pro'), \number_format_i18n($min, $dec));
		}

		if ($this->getAfterDiscount())
		{
			$descr .= (' ' . _x("(after discount)", "Earning method description", 'woorewards-pro'));
		}
		else
		{
			$categories = $this->getProductCategories();
			if (!empty($categories))
			{
				$msg = _n("Limited to category %s", "Limited to categories %s", count($categories), 'woorewards-pro');
				$descr .= sprintf('<br/>' . $msg, $this->getProductCategoriesNames($categories, $context));
			}
			$negCat = $this->getProductExcludedCategories();
			if (!empty($negCat))
			{
				$msg = _n("Exclude category %s", "Exclude categories %s", count($negCat), 'woorewards-pro');
				$descr .= sprintf('<br/>' . $msg, $this->getProductCategoriesNames($negCat, $context));
			}
		}

		if ($this->getShipping())
		{
			$descr .= '<br/>';
			$descr .= __("Include shipping", 'woorewards-pro');
		}

		if ($this->getFees())
		{
			$descr .= '<br/>';
			$descr .= __("Include fees", 'woorewards-pro');
		}
		return $descr;
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

	function getForm($context = 'editlist')
	{
		$prefix = $this->getDataKeyPrefix();
		$form = '';
		$phb2 = $this->getFieldsetPlaceholder(false, 2);

		// shipping included
		$label   = _x("Include shipping amount", "Order Amount Event", 'woorewards-pro');
		$toggle = \LWS\Adminpanel\Pages\Field\Checkbox::compose($prefix . 'include_shipping', array(
		//	'id'      => $prefix . 'include_shipping',
			'layout'  => 'toggle',
		));
		$form .= "<div class='lws-$context-opt-title label'>$label</div>";
		$form .= "<div class='lws-$context-opt-input value'>{$toggle}</div>";

		// fees included
		$label   = _x("Include fees amount", "Order Amount Event", 'woorewards-pro');
		$toggle = \LWS\Adminpanel\Pages\Field\Checkbox::compose($prefix . 'include_fees', array(
		//	'id'      => $prefix . 'include_fees',
			'layout'  => 'toggle',
		));
		$form .= "<div class='lws-$context-opt-title label'>$label</div>";
		$form .= "<div class='lws-$context-opt-input value'>{$toggle}</div>";

		// threshold effect applied
		$label   = _x("Threshold effect", "Order Amount Event", 'woorewards-pro');
		$tooltip = __("If checked, customers will earn points for each multiple of this amount. If unchecked, points earned will be rounded to the closest value.", 'woorewards-pro');
		$toggle = \LWS\Adminpanel\Pages\Field\Checkbox::compose($prefix . 'threshold_effect', array(
			'id'      => $prefix . 'threshold_effect',
			'layout'  => 'toggle',
		));
		$form .= "<div class='field-help'>$tooltip</div>";
		$form .= "<div class='lws-$context-opt-title label'>$label<div class='bt-field-help'>?</div></div>";
		$form .= "<div class='lws-$context-opt-input value'>{$toggle}</div>";

		// Minimum order amount
		$label = _x("Minimum order amount", "Order Amount Event", 'woorewards-pro');
		$tooltip = __("Uses the Order Subtotal as reference.", 'woorewards-pro');
		$form .= "<div class='field-help'>$tooltip</div>";
		$form .= "<div class='lws-$context-opt-title label'>$label<div class='bt-field-help'>?</div></div>";
		$form .= "<div class='lws-$context-opt-input value'><input type='text' id='{$prefix}min_amount' name='{$prefix}min_amount' placeholder='5' /></div>";

		$form .= $this->getFieldsetEnd(2);
		$form .= $this->getFieldsetBegin(3, __("Allow / Deny Categories", 'woorewards-pro'), 'span2 lws_woorewards_orderamount_after_discount_relative');

		// restriction by product category
		$label   = _x("Allowed categories", "Order Amount Event", 'woorewards-pro');
		if ($context == 'achievements')
			$tooltip = __("If left empty, all bought products are taken into account. If you set one or more categories, only the products which belong to these categories will be used.", 'woorewards-pro');
		else
			$tooltip = __("If left empty, all bought products can give loyalty points. If you set one or more categories, only the products which belong to these categories will award points.", 'woorewards-pro');
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

		// restriction by product category blacklist
		$label   = _x("Denied categories", "Order Amount Event", 'woorewards-pro');
		if ($context == 'achievements')
			$tooltip = __("If left empty, all bought products are taken into account. If you set one or more categories, the products which belong to these categories will <b>not</b> be used.", 'woorewards-pro');
		else
			$tooltip = __("If left empty, all bought products can give loyalty points. If you set one or more categories, the products which belong to these categories will <b>not</b> award points.", 'woorewards-pro');
		$form .= "<div class='field-help'>$tooltip</div>";
		$form .= "<div class='lws-$context-opt-title label'>$label<div class='bt-field-help'>?</div></div>";
		$form .= "<div class='lws-$context-opt-input value'>";
		$form .= \LWS\Adminpanel\Pages\Field\LacChecklist::compose($prefix . 'product_neg_cat', array(
			'comprehensive' => true,
			'predefined' => 'taxonomy',
			'spec' => array('taxonomy' => 'product_cat'),
			'value' => $this->getProductCategories()
		));
		$form .= "</div>";

		$label   = __("On-sale", 'woorewards-pro');
		$tooltip = __("Give points only for on-sale products, those who are not on-sale or all of them.", 'woorewards-pro');
		$input   = \LWS\Adminpanel\Pages\Field\LacSelect::compose($prefix.'onsale', array(
			'maxwidth' => '300px',
			'mode'	   => 'select',
			'class'    => 'above',
			'source'   => array(
				array('value' => '',        'label' => __("No restriction", 'woorewards-pro')),
				array('value' => 'regular', 'label' => __("Regular price only", 'woorewards-pro')),
				array('value' => 'onsale',  'label' => __("On-sale price only", 'woorewards-pro')),
			),
		));
		$form .= <<<EOT
<div class='field-help'>{$tooltip}</div>
<div class='lws-{$context}-opt-title label'>{$label}<div class='bt-field-help'>?</div></div>
<div class='lws-{$context}-opt-input value'>{$input}</div>
EOT;

		$form .= $this->getFieldsetPlaceholder(false, 3);
		$form = \str_replace($this->getFieldsetPlaceholder(false, 2), $form, parent::getForm($context));
		return $this->filterForm($form, $prefix, $context);
	}

	function getData()
	{
		$prefix = $this->getDataKeyPrefix();
		$data = parent::getData();
		$data[$prefix . 'product_cat'] = base64_encode(json_encode($this->getProductCategories()));
		$data[$prefix . 'product_neg_cat'] = base64_encode(json_encode($this->getProductExcludedCategories()));
		$data[$prefix . 'threshold_effect'] = $this->getThresholdEffect() ? 'on' : '';
		$data[$prefix . 'min_amount'] = $this->getMinAmount();
		$data[$prefix . 'onsale'] = $this->getOnSaleStatus();
		return $this->filterData($data, $prefix);
	}

	function submit($form = array(), $source = 'editlist')
	{
		$prefix = $this->getDataKeyPrefix();
		$values = \apply_filters('lws_adminpanel_arg_parse', array(
			'post'     => ($source == 'post'),
			'values'   => $form,
			'format'   => array(
				$prefix . 'product_cat'      => array('D'),
				$prefix . 'product_neg_cat'  => array('D'),
				$prefix . 'threshold_effect' => 's',
				$prefix . 'min_amount'       => 'f',
				$prefix . 'onsale'           => 'k',
			),
			'defaults' => array(
				$prefix . 'product_cat'      => array(),
				$prefix . 'product_neg_cat'  => array(),
				$prefix . 'threshold_effect' => '',
				$prefix . 'onsale'           => '',
			),
			'labels'   => array(
				$prefix . 'product_cat'     => __("Product category", 'woorewards-pro'),
				$prefix . 'product_neg_cat' => __("Excluded category", 'woorewards-pro'),
				$prefix . 'min_amount'      => __("Minimum order amount", 'woorewards-pro'),
				$prefix . 'onsale'          => __("Product category", 'woorewards-pro'),
			)
		));
		if (!(isset($values['valid']) && $values['valid']))
			return isset($values['error']) ? $values['error'] : false;

		$valid = parent::submit($form, $source);
		if ($valid === true)
			$valid = $this->optSubmit($prefix, $form, $source);
		if ($valid === true)
		{
			$this->setThresholdEffect(boolval($values['values'][$prefix . 'threshold_effect']));
			$this->setProductCategories($values['values'][$prefix . 'product_cat']);
			$this->setProductExcludedCategories($values['values'][$prefix . 'product_neg_cat']);
			$this->setMinAmount($values['values'][$prefix . 'min_amount']);
			$this->setOnSaleStatus($values['values'][$prefix . 'onsale']);
		}
		return $valid;
	}

	function setOnSaleStatus($status)
	{
		$this->onsales = $status;
	}

	/** @return string */
	function getOnSaleStatus()
	{
		return $this->onsales;
	}

	/** @return bool */
	function isOnsaleStatusAllowed($product)
	{
		$status = $this->getOnSaleStatus();
		if ($status)
			return ('onsale' == $status) == $product->is_on_sale();
		else
			return true;
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
	function getProductExcludedCategories()
	{
		return $this->productExcludedCategories;
	}

	/** @param $categories (array|string) as string, it should be a json base64 encoded array. */
	function setProductExcludedCategories($categories = array())
	{
		if (!is_array($categories))
			$categories = @json_decode(@base64_decode($categories));
		if (is_array($categories))
			$this->productExcludedCategories = $categories;
		return $this;
	}

	/** @return float */
	function getMinAmount()
	{
		return $this->minAmount;
	}

	public function setMinAmount($amount = 0)
	{
		$this->minAmount = max(0.0, floatval(str_replace(',', '.', $amount)));
		return $this;
	}

	protected function _fromPost(\WP_Post $post)
	{
		$this->setProductCategories(\get_post_meta($post->ID, 'wre_event_product_cat', true));
		$this->setProductExcludedCategories(\get_post_meta($post->ID, 'wre_event_product_neg_cat', true));
		$this->setMinAmount(\get_post_meta($post->ID, 'wre_event_min_amount', true));
		$this->setOnSaleStatus(\get_post_meta($post->ID, 'wre_event_onsale', true));
		$this->optFromPost($post);
		return parent::_fromPost($post);
	}

	protected function _save($id)
	{
		\update_post_meta($id, 'wre_event_product_cat', $this->getProductCategories());
		\update_post_meta($id, 'wre_event_product_neg_cat', $this->getProductExcludedCategories());
		\update_post_meta($id, 'wre_event_min_amount', $this->getMinAmount());
		\update_post_meta($id, 'wre_event_onsale', $this->getOnSaleStatus());
		$this->optSave($id);
		return parent::_save($id);
	}

	function getClassname()
	{
		return 'LWS\WOOREWARDS\Events\OrderAmount';
	}

	protected function shapeGain($value)
	{
		$amount = $this->getDenominator();
		$currency = $this->getCurrency();
		if(\LWS\Adminpanel\Tools\Conveniences::isWC()){
			if($currency){
				$amount = \wc_price($amount, array('currency' => $currency));
			}else{
				$amount = \wc_price($amount);
			}
		} else {
			$amount = \number_format_i18n($amount, 2);
		}

		if ('=' == substr($value, 0, 1)) {
			$value = sprintf(_x('[%1$s] / %2$s', "Point per money spent", 'woorewards-pro'), $value, $amount);
		} elseif (\is_numeric($value) && $value > 0) {
			$points = \LWS_WooRewards::formatPointsWithSymbol($value, $this->getPoolName());
			$value = sprintf(_x('%1$s / %2$s', "Point per money spent", 'woorewards-pro'), $points, $amount);
		} else {
			$value = \str_replace('[spent]', $amount, $value);
		}
		return \LWS\WOOREWARDS\Abstracts\Event::shapeGain($value);
	}

	/** override */
	function orderDone($order)
	{
		if (!$this->acceptOrder($order->order))
			return $order;
		if(!$this->isValidCurrency($order->order))
			return $order;

		if ($order->amount < $this->getMinAmount())
			return $order;

		return parent::orderDone($order);
	}

	/** override to take care of order content: product categories
	 * order amount is sum of accepted product prices.
	 *
	 * Shipping is still added whatever category or not
	 * @return float */
	function getOrderAmount(&$order, $round = true)
	{
		$amount = 0;
		$categories = $this->getProductCategories();
		$exclude = $this->getProductExcludedCategories();
		$noEarning = $this->getExclusionFromOrder($order->order);

		$detailed = ($categories || $exclude || $noEarning || $this->getOnSaleStatus());
		$detailed = \apply_filters('lws_woorewards_orderamount_total_detailed_computing', $detailed, $this);
		if (!$detailed)
		{
			$amount = parent::getOrderAmount($order, false);
		}
		else
		{
			foreach ($order->items as $item)
			{
				$pId = $item->item->get_product_id();
				$product = \LWS\WOOREWARDS\PRO\Conveniences::instance()->getProductFromOrderItem($order->order, $item->item);
				if ($product)
				{
					$incProd = true;
					if (!empty($exclude) && $this->isProductInCategory($product, $exclude)) {
						$incProd = false;
					} elseif (!empty($categories) && !$this->isProductInCategory($product, $categories)) {
						$incProd = false;
					} elseif (!$this->isOnsaleStatusAllowed($product)) {
						$incProd = false;
					}
					if (!\apply_filters('lws_woorewards_orderamount_total_detailed_includes_product', $incProd, $product, $item, $this))
						continue;
				}

				$oriQty = $qty = $item->item->get_quantity();
				$qty = $this->useExclusion($noEarning, $product ? $product : $pId, $qty);
				if ($qty > 0)
				{
					$lineAmount = 0;
					if ($this->getAfterDiscount()) {
						$lineAmount   += $item->item->get_total();
						if ($order->inc_tax)
							$lineAmount += $item->item->get_total_tax();
					} else {
						$lineAmount   += $item->item->get_subtotal();
						if ($order->inc_tax)
							$lineAmount += $item->item->get_subtotal_tax();
					}
					if ($oriQty != $qty) {
						$lineAmount *= ($oriQty ? ($qty / $oriQty) : 0);
					}
					$amount += $lineAmount;
				}
			}

			if ($this->getShipping())
			{
				$amount += floatval($order->order->get_shipping_total('edit'));
				if ($order->inc_tax)
					$amount += floatval($order->order->get_shipping_tax('edit'));
			}

			if ($this->getFees())
			{
				$amount += floatval($order->order->get_total_fees());
			}
		}

		$amount = \apply_filters('lws_woorewards_event_order_get_amount', $amount, $order, $this);
		if ($amount < $this->getMinAmount())
			$amount = 0;
		return $round ? $this->roundPrice($amount) : $amount;
	}

	private function isProductInCategory($product, $whiteList, $taxonomy = 'product_cat')
	{
		$product_cats = \wc_get_product_cat_ids($product->get_id());
		if (!empty($parentId = $product->get_parent_id()))
			$product_cats = array_merge($product_cats, \wc_get_product_cat_ids($parentId));

		// If we find an item with a cat in our allowed cat list, the product is valid.
		return !empty(array_intersect($product_cats, $whiteList));
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
		if (!$this->isValidCurrency())
			return 0;

		$valid = true;
		if (!empty($categories = $this->getProductCategories()) && !$this->isProductInCategory($product, $categories))
			$valid = false;
		if (!empty($exclude = $this->getProductExcludedCategories()) && $this->isProductInCategory($product, $exclude))
			$valid = false;
		if (!$this->isOnsaleStatusAllowed($product))
			$valid = false;

		$price = 0;
		if (\apply_filters('lws_woorewards_orderamount_is_points_for_product', $valid, $product, $this))
		{
			if (count($product->get_children()) > 1)
			{
				$prices = array();
				foreach ($product->get_children() as $varId)
				{
					if ($variation = wc_get_product($varId))
					{
						// Hide out of stock variations if 'Hide out of stock items from the catalog' is checked.
						if (!$variation || !$variation->exists() || ('yes' === \get_option('woocommerce_hide_out_of_stock_items') && !$variation->is_in_stock()))
							continue;

						if (\method_exists($variation, 'variation_is_visible'))
						{
							// Filter 'woocommerce_hide_invisible_variations' to optionally hide invisible variations (disabled variations and variations with empty price).
							if (\apply_filters('woocommerce_hide_invisible_variations', true, $product->get_id(), $variation) && !$variation->variation_is_visible())
								continue;
						}

						if (!empty(\get_option('lws_woorewards_order_amount_includes_taxes', '')))
							$prices[] = floatval(\wc_get_price_including_tax($variation));
						else
							$prices[] = floatval(\wc_get_price_excluding_tax($variation));
					}
				}
				if (!empty($prices))
				{
					$min = min($prices);
					$max = max($prices);
					$price = ($min != $max) ? array($min, $max) : $max;
				}
			}
			else
			{
				if (!empty(\get_option('lws_woorewards_order_amount_includes_taxes', '')))
					$price = floatval(\wc_get_price_including_tax($product));
				else
					$price = floatval(\wc_get_price_excluding_tax($product));
			}
		}

		if (is_array($price))
		{
			$mul = $this->getFinalGain(1, array(
				'user'  => \LWS\Adminpanel\Tools\Conveniences::getCustomer(\wp_get_current_user(), \WC()->cart),
				'order' => \WC()->cart,
			), true);
			foreach ($price as &$p)
			{
				$p = $this->roundPrice($p);
				$p = (int)round($this->getPointsForAmount($p) * (float)$mul, 0, \apply_filters('lws_woorewards_event_points_round_mode', PHP_ROUND_HALF_UP, $this));
			}
			return $price;
		}
		else
		{
			$price = $this->roundPrice($price);
			return (int)$this->getFinalGain($this->getPointsForAmount($price), array(
				'user'  => \LWS\Adminpanel\Tools\Conveniences::getCustomer(\wp_get_current_user(), \WC()->cart),
				'order' => \WC()->cart,
			), true);
		}
	}

	function getPointsForCart(\WC_Cart $cart)
	{
		if (!$this->acceptOrigin('checkout'))
			return 0;
		if (!$this->acceptCart($cart))
			return 0;
		if (!$this->isValidCurrentSponsorship())
			return 0;
		if(!$this->isValidCurrency())
			return 0;

		$inc_tax = !empty(\get_option('lws_woorewards_order_amount_includes_taxes', ''));
		$amount = 0;

		$categories = $this->getProductCategories();
		$exclude = $this->getProductExcludedCategories();
		$noEarning = $this->getExclusionFromCart($cart);

		$detailed = ($categories || $exclude || $noEarning || $this->getOnSaleStatus());
		$detailed = \apply_filters('lws_woorewards_orderamount_total_detailed_computing', $detailed, $this);
		if (!$detailed)
		{
			$amount = floatval($cart->get_subtotal());
			if ($inc_tax)
				$amount += floatval($cart->get_subtotal_tax());

			if ($this->getShipping()) {
				$amount += floatval($cart->get_shipping_total());
				if ($inc_tax)
					$amount += floatval($cart->get_shipping_tax());
			}

			if ($this->getFees()) {
				$amount += floatval($cart->get_fee_total());
				$amount += floatval($cart->get_fee_tax()); // order only have a single total
			}

			if ($this->getAfterDiscount()) {
				$amount -= $cart->get_discount_total();
				if ($inc_tax)
					$amount -= $cart->get_discount_tax();
			}
		} else {
			foreach ($cart->get_cart() as $item) {
				$isVar = (isset($item['variation_id']) && $item['variation_id']);
				$pId = $isVar ? $item['variation_id'] : (isset($item['product_id']) ? $item['product_id'] : false);
				if ($pId && !empty($product = \wc_get_product($pId)))
				{
					$incProd = true;
					if (!empty($exclude) && $this->isProductInCategory($product, $exclude)) {
						$incProd = false;
					} elseif (!empty($categories) && !$this->isProductInCategory($product, $categories)) {
						$incProd = false;
					} elseif (!$this->isOnsaleStatusAllowed($product)) {
						$incProd = false;
					}
					if (!\apply_filters('lws_woorewards_orderamount_total_detailed_includes_product', $incProd, $product, $item, $this))
						continue;
				}

				$oriQty = $qty = isset($item['quantity']) ? intval($item['quantity']) : 1;
				$qty = $this->useExclusion($noEarning, $product ? $product : $pId, $qty);
				if ($qty > 0)
				{
					$lineAmount = 0;
					if ($this->getAfterDiscount()) {
						$lineAmount += $item['line_total'];
						if ($inc_tax)
							$lineAmount += $item['line_tax'];
					} else {
						$lineAmount += $item['line_subtotal'];
						if ($inc_tax)
							$lineAmount += $item['line_subtotal_tax'];
					}
					if ($oriQty != $qty) {
						$lineAmount *= ($oriQty ? ($qty / $oriQty) : 0);
					}
					$amount += $lineAmount;
				}
			}

			if ($this->getShipping()) {
				$amount += floatval($cart->get_shipping_total());
				if ($inc_tax)
					$amount += floatval($cart->get_shipping_tax());
			}

			if ($this->getFees()) {
				$amount += floatval($cart->get_fee_total());
				$amount += floatval($cart->get_fee_tax()); // order only have a single total
			}
		}
		if ($amount < $this->getMinAmount())
			$amount = 0;
		$amount = $this->roundPrice($amount);

		return (int)$this->getFinalGain(
			$this->getPointsForAmount($amount),
			array(
				'user'  => \LWS\Adminpanel\Tools\Conveniences::getCustomer(\wp_get_current_user(), $cart),
				'order' => $cart,
			),
			true
		);
	}

	function getPointsForOrder(\WC_Order $order)
	{
		if (!$this->acceptOrder($order))
			return 0;
		if (!$this->isValidOriginByOrder($order))
			return 0;
		if(!$this->isValidCurrency($order))
			return 0;

		// build same struct than pool do
		$parsed = \LWS\WOOREWARDS\PRO\Core\Pool::parseOrder($order->get_id(), $order);
		$amount = $this->getOrderAmount($parsed, true);

		// money to points
		return (int)$this->getFinalGain(
			$this->getPointsForAmount($amount),
			array(
				'user'  => \LWS\Adminpanel\Tools\Conveniences::getCustomer(false, $order),
				'order' => $order,
			),
			true
		);
	}
}