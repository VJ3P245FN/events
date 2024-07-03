<?php
namespace LWS\WOOREWARDS\PRO\Events;

// don't call the file directly
if( !defined( 'ABSPATH' ) ) exit();

/** Manage common feature for Events about order. */
trait T_Order
{
	public $wcSubscriptionSupport = null;
	public $createdVia = null;
	public $orderNumbers = null;
	public $currency = null;
	public $gateway = null;

	/** WooCommerce Subscriptions */
	function isWCS()
	{
		static $_iswcs = null;
		if( null === $_iswcs )
			$_iswcs = \class_exists('\WC_Subscription');
		return $_iswcs;
	}

	public function acceptCart(\WC_Cart $cart)
	{
		$user = \LWS\Adminpanel\Tools\Conveniences::getCustomer(\wp_get_current_user(), $cart);
		if ($user) {
			if (!$this->isOrderCountValid($user))
				return false;
		} else {
			$customer = $cart->get_customer();
			if (!$this->isOrderCountValid($customer ? $customer->get_billing_email() : false))
				return false;
		}
		if (!$this->isValidGateway($cart))
			return false;
		return true;
	}

	public function acceptOrder($order)
	{
		$customer = \LWS\Adminpanel\Tools\Conveniences::getCustomer(false, $order);
		if (!$this->isOrderCountValid($customer ? $customer : $order->get_billing_email(), $order))
			return false;

		if( !empty($value = $this->getCreatedVia()) && $value != 'all' )
		{
			if( !empty($origin = $order->get_created_via('edit')) && $value != $origin )
				return false;
		}

		if (!$this->isValidGateway($order))
			return false;

		if( $this->isWCS() )
		{
			if( !$this->acceptWCSubscriptions($order) )
				return false;
		}
		return true;
	}

	/** @param $order (\WC_Order) @return (bool) */
	public function acceptWCSubscriptions(&$order)
	{
		$accepted = true;
		$behavior = $this->getWCSubscriptionsSupport();

		if( 'no_wcs' == $behavior )
		{
			$accepted = empty(\wcs_get_subscriptions_for_order($order, array('order_type'=>'any')));
		}
		else if( 'wcs_origin' == $behavior )
		{
			$accepted = !empty(\wcs_get_subscriptions_for_order($order, array('order_type'=>array('parent', 'switch'))));
		}
		else if( 'wcs_renewal' == $behavior )
		{
			$accepted = \wcs_order_contains_renewal($order);
		}

		return $accepted;
	}

	public function getWCSubscriptionsSupport()
	{
		return ((null !== $this->wcSubscriptionSupport) && $this->wcSubscriptionSupport) ? $this->wcSubscriptionSupport : 'any';
	}

	/**	A LAC source compatible list of gateway.
	 *	@return false|array if no WC available. Or a list (maybe empty) of gateway. */
	public function getGatewaysList($withOptionAll=false)
	{
		$list = false;
		if (\function_exists('\WC') && \WC()->payment_gateways) {
			$list = array();
			foreach (\WC()->payment_gateways->get_available_payment_gateways() as $method => $gateway) {
				$list[] = array('value' => $method, 'label' => $gateway->get_method_title());
			}
			if ($withOptionAll)
				\array_unshift($list, array('value' => 'any', 'label' => __("All gateways", 'woorewards-pro')));
		}
		return $list;
	}

	public function setWCSubscriptionsSupport($behavior)
	{
		$this->wcSubscriptionSupport = $behavior;
		return $this;
	}

	public function acceptOrigin($origin)
	{
		if( !empty($value = $this->getCreatedVia()) && $value != 'all' )
		{
			if( $origin != $value )
				return false;
		}
		return true;
	}

	public function getCreatedVia()
	{
		return ((null !== $this->createdVia) && $this->createdVia) ? $this->createdVia : 'all';
	}

	public function setCreatedVia($origin)
	{
		$this->createdVia = $origin;
		return $this;
	}

	public function getOrderNumbers()
	{
		return ((null !== $this->orderNumbers) && $this->orderNumbers) ? $this->orderNumbers : '';
	}


	public function setOrderNumbers($numbers)
	{
		$this->orderNumbers = $numbers;
		return $this;
	}

	public function getCurrency()
	{
		return ((null !== $this->currency) && $this->currency) ? $this->currency : '';
	}

	public function setCurrency($currency)
	{
		$this->currency = $currency;
		return $this;
	}

	public function getGateway()
	{
		return ((null !== $this->gateway) && $this->gateway) ? $this->gateway : 'any';
	}

	public function setGateway($gateway)
	{
		$this->gateway = $gateway;
		return $this;
	}

	protected function filterData($data=array(), $prefix='')
	{
		$data[$prefix . 'currency']        = $this->getCurrency();
		$data[$prefix . 'affected_orders'] = $this->getOrderNumbers();
		$data[$prefix . 'created_via']     = $this->getCreatedVia();
		$data[$prefix . 'wcs_support']     = $this->getWCSubscriptionsSupport();
		$data[$prefix . 'gateway']         = $this->getGateway();
		return $data;
	}

	protected function filterForm($content='', $prefix='', $context='editlist', $column=2)
	{
		$str = '';

		// currency
		if(\get_option('lws_woorewards_enable_multicurrency'))
		{
			$label = __("Currency", 'woorewards-pro');
			$value = $this->getCurrency();
			$tooltip = __("If empty, all currencies will give points. To give points for a specific currency, set its 3 characters unicode here (exemple : USD, EUR ...)", 'woorewards-pro');
			$str .= "<div class='field-help'>$tooltip</div>";
			$str .= "<div class='lws-$context-opt-title label'>$label<div class='bt-field-help'>?</div></div>";
			$str .= "<div class='lws-$context-opt-input value'>";
			$str .= \LWS\Adminpanel\Pages\Field\LacSelect::compose($prefix.'currency', array(
				'maxwidth' => '300px',
				'mode'     => 'research',
				'source'   => \LWS\Adminpanel\Tools\Conveniences::getWooCommerceCurrencies(),
			));
			$str .= "</div>";
		}

		// affected orders
		$label = __("Affected orders", 'woorewards-pro');
		$value = $this->getOrderNumbers();
		$tooltip = __("If empty, all orders will give points. To give points for specific orders, enter the affected orders, comma separated (1 will give points for the customer's first order). You can also set order ranges like this : <b>2-10</b>", 'woorewards-pro');
		$str .= "<div class='field-help'>$tooltip</div>";
		$str .= "<div class='lws-$context-opt-title label'>$label<div class='bt-field-help'>?</div></div>";
		$str .= "<div class='lws-$context-opt-input value'>";
		$str .= "<input type='text' id='{$prefix}affected_orders' name='{$prefix}affected_orders' value='$value' placeholder='1,2,5-10,20-50,100' size='20' />";
		$str .= "</div>";


		// created via
		$label = __("Order created via", 'woorewards-pro');
		$tooltip = __("Restrict points earning based on order origin : Front-End, Back-End or REST API. By default, all orders give points.", 'woorewards-pro');
		$str .= "<div class='field-help'>$tooltip</div>";
		$str .= "<div class='lws-$context-opt-title label'>$label<div class='bt-field-help'>?</div></div>";
		$str .= "<div class='lws-$context-opt-input value'>";
		$str .= \LWS\Adminpanel\Pages\Field\LacSelect::compose($prefix.'created_via', array(
			'maxwidth'	=> '300px',
			'mode'	    => 'select',
			'source'    => array(
				array('value' => 'all',      'label'=>__("All origins", 'woorewards-pro')),
				array('value' => 'checkout', 'label'=>__("Front-End Checkout", 'woorewards-pro')),
				array('value' => 'admin',    'label'=>__("Back-End Administration", 'woorewards-pro')),
				array('value' => 'rest-api', 'label'=>__("REST API", 'woorewards-pro')),
			),
			'class'     => 'above'
		));
		$str .= "</div>";

		$gateways = $this->getGatewaysList(true);
		if (false !== $gateways) {
			$label   = __("Payment gateway", 'woorewards-pro');
			$tooltip = __("Earn points only if customer uses a given payment gateway.", 'woorewards-pro');
			$input   = \LWS\Adminpanel\Pages\Field\LacSelect::compose($prefix.'gateway', array(
				'maxwidth' => '300px',
				'mode'	   => 'select',
				'source'   => $gateways,
				'class'    => 'above',
				'value'    => 'any',
			));
			$str .= <<<EOT
<div class='field-help'>{$tooltip}</div>
<div class='lws-{$context}-opt-title label'>{$label}<div class='bt-field-help'>?</div></div>
<div class='lws-{$context}-opt-input value'>{$input}</div>
EOT;
		}

		if( $this->isWCS() )
		{
			// WCS
			$label = __("Order type", 'woorewards-pro');
			$tooltip = __("WooCommerce Subscriptions plugin support. You can earn points only for some kind of orders.", 'woorewards-pro');
			$str .= "<div class='field-help'>$tooltip</div>";
			$str .= "<div class='lws-$context-opt-title label'>$label<div class='bt-field-help'>?</div></div>";
			$str .= "<div class='lws-$context-opt-input value'>";
			$str .= \LWS\Adminpanel\Pages\Field\LacSelect::compose($prefix.'wcs_support', array(
				'maxwidth'	=> '300px',
				'mode'	    => 'select',
				'source'    => array(
					array('value' => 'any',         'label'=>__("All orders", 'woorewards-pro')),
					array('value' => 'wcs_renewal', 'label'=>__("Subscriptions renewals", 'woorewards-pro')),
					array('value' => 'wcs_origin',  'label'=>__("Initial Subscription", 'woorewards-pro')),
					array('value' => 'no_wcs',      'label'=>__("Not Subscription orders", 'woorewards-pro')),
				),
				'class'     => 'above'
			));
			$str .= "</div>";
		}

		$str .= $this->getFieldsetPlaceholder(false, $column);
		return str_replace($this->getFieldsetPlaceholder(false, $column), $str, $content);
	}

	/** @return bool */
	protected function optSubmit($prefix='', $form=array(), $source='editlist')
	{
		$values = \apply_filters('lws_adminpanel_arg_parse', array(
			'post'   => ($source == 'post'),
			'values' => $form,
			'format' => array(
				$prefix . 'currency'        => 't',
				$prefix . 'affected_orders' => 't',
				$prefix . 'created_via'     => 't',
				$prefix . 'wcs_support'     => 't',
				$prefix . 'gateway'         => 't',
			),
			'defaults' => array(
				$prefix . 'currency'        => '',
				$prefix . 'affected_orders' => '',
				$prefix . 'created_via'     => 'all',
				$prefix . 'wcs_support'     => 'any',
				$prefix . 'gateway'         => '',
			),
			'labels'   => array(
				$prefix . 'currency'        => __("Currency", 'woorewards-pro'),
				$prefix . 'affected_orders' => __("Order numbers or ranges", 'woorewards-pro'),
				$prefix . 'created_via'     => __("Order created via", 'woorewards-pro'),
				$prefix . 'wcs_support'     => __("Order type", 'woorewards-pro'),
				$prefix . 'gateway'         => __("Gateway", 'woorewards-pro'),
			)
		));
		if( !(isset($values['valid']) && $values['valid']) )
			return isset($values['error']) ? $values['error'] : false;

		$this->setCurrency($values['values'][$prefix . 'currency']);
		$this->setOrderNumbers($values['values'][$prefix . 'affected_orders']);
		$this->setCreatedVia($values['values'][$prefix.'created_via']);
		if( $this->isWCS() )
			$this->setWCSubscriptionsSupport($values['values'][$prefix.'wcs_support']);
		$this->setGateway($values['values'][$prefix . 'gateway']);
		return true;
	}

	protected function optFromPost(\WP_Post $post)
	{
		$this->setCurrency(\get_post_meta($post->ID, '_currency', true));
		$this->setOrderNumbers(\get_post_meta($post->ID, '_affected_orders', true));
		$this->setCreatedVia(\get_post_meta($post->ID, '_created_via', true));
		$this->setWCSubscriptionsSupport(\get_post_meta($post->ID, '_wcs_support', true));
		$this->setGateway(\get_post_meta($post->ID, '_gateway', true));
		return $this;
	}

	protected function optSave($id)
	{
		\update_post_meta($id, '_currency',        $this->getCurrency());
		\update_post_meta($id, '_affected_orders', $this->getOrderNumbers());
		\update_post_meta($id, '_created_via',     $this->getCreatedVia());
		\update_post_meta($id, '_wcs_support',     $this->getWCSubscriptionsSupport());
		\update_post_meta($id, '_gateway',         $this->getGateway());
		return $this;
	}

	protected function isOrderCountValid($user, $order = false)
	{
		$valuesToTest = $this->getOrderNumbers();
		if (!$valuesToTest)
			return true;
		if (!$user)
			return false;

		$valid = false;
		$orderCount = \LWS\WOOREWARDS\Conveniences::getOrderCount($user, $order ? $order->get_id() : false, 'event');
		$orderNumber = ($orderCount + 1);
		foreach (explode(',', $valuesToTest) as $valueToTest)
		{
			$valueToTest = trim($valueToTest);
			if (strpos($valueToTest, '-') !== false)
			{
				// Range of Values
				$range = explode("-", $valueToTest);
				if (count($range) >= 2 && is_numeric($range[0]) && is_numeric($range[1]) && intval($range[0]) < intval($range[1]))
				{
					if (intval($range[0]) <= $orderNumber && $orderNumber <= intval($range[1]))
						$valid = true;
				}
			}
			else
			{
				// Single Value
				if (is_numeric($valueToTest) && $orderNumber == intval($valueToTest))
					$valid = true;
			}
		}
		return $valid;
	}

	public function isValidCurrency($object = false)
	{
		$currencyEnabled = \lws_get_option('lws_woorewards_enable_multicurrency', false);
		if($currencyEnabled){
			if(empty($this->currency)){
				return true;
			}
			if(\is_a($object, '\WC_Order')) {
				$currency = $object->get_currency();
			} else {
				$currency = \get_woocommerce_currency();
			}
			return ($this->currency == $currency);
		}
		return true;
	}

	public function isValidGateway($object = false)
	{
		$limit = $this->getGateway(); // default is 'any'
		if ('any' == $limit)
			return true;
		if(\is_a($object, '\WC_Order')) {
			return ($limit == $object->get_payment_method());
		} else {
			return \apply_filters('lws_woorewards_pro_gateway_restriction_idle_validity', true);
		}
	}

	/**	@deprecated user \LWS\WOOREWARDS\Conveniences::getOrderCount() instead */
	protected function getOrderCount($user, $exceptOrderId = false)
	{
		return \LWS\WOOREWARDS\Conveniences::getOrderCount($user, $exceptOrderId, 'event');
	}
}