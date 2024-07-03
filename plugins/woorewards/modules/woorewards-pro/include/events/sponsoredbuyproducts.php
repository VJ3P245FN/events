<?php

namespace LWS\WOOREWARDS\PRO\Events;

// don't call the file directly
if (!defined('ABSPATH')) exit();


/** Sponsor earns points for each money spend by Sponsored on an order.
 *	Extends usual order amount to only change point destination. */
class SponsoredBuyProducts extends \LWS\WOOREWARDS\PRO\Events\BuySpecificProduct
{
	use \LWS\WOOREWARDS\PRO\Events\T_SponseeTrigger;

	protected $roles = array();
	protected $guestAllowed = false;

	function getInformation()
	{
		return array_merge(parent::getInformation(), array(
			'icon'  => 'lws-icon-barcode',
			'short' => __("The customer will earn points when a referee buys specific products.", 'woorewards-pro'),
			'help'  => __("This method will only reward the referrer, not the referee", 'woorewards-pro'),
		));
	}

	function getClassname()
	{
		return \get_class($this);
	}

	public function getDisplayType()
	{
		return _x("Referee buys products", "getDisplayType", 'woorewards-pro');
	}

	function getForm($context = 'editlist')
	{
		$prefix = $this->getDataKeyPrefix();
		$form = ($placeholder = $this->getFieldsetPlaceholder(true, 2));

		// Allow guest order
		$label   = _x("Guest order", "Referee Order Event", 'woorewards-pro');
		$tooltip = __("By default, customer must be registered. Check that option to accept guests. Customer will be tested on billing email.", 'woorewards-pro');
		$toggle = \LWS\Adminpanel\Pages\Field\Checkbox::compose($prefix . 'guest', array(
			'id'      => $prefix . 'guest',
			'layout'  => 'toggle',
			'checked' => ($this->isGuestAllowed() ? ' checked' : '')
		));
		$form .= "<div class='field-help'>$tooltip</div>";
		$form .= "<div class='lws-$context-opt-title label'>$label<div class='bt-field-help'>?</div></div>";
		$form .= "<div class='lws-$context-opt-input value'>$toggle</div>";

		$form = str_replace($placeholder, $form, parent::getForm($context));

		$placeholder = $this->getFieldsetPlaceholder(false, 10);

		// Sponsee role
		$label = _x("Sponsee roles", "Referee Order Event", 'woorewards-pro');
		$tooltip = __("The sponsee needs to have at least one of the selected roles to grant points to his referral. Leave empty for no restriction.", 'woorewards-pro');
		$field = "<div class='field-help'>{$tooltip}</div>";
		$field .= "<div class='lws-{$context}-opt-title label'>{$label}<div class='bt-field-help'>?</div></div>";
		$field .= "<div class='lws-$context-opt-input value'>";
		$field .= \LWS\Adminpanel\Pages\Field\LacChecklist::compose($prefix . 'roles', array(
			'ajax'          => 'lws_adminpanel_get_roles',
			'comprehensive' => true,
			'class'         => 'above',
		));
		$field .= "</div>";

		// Max Sponsee Triggers
		$field .= $this->getSponseeTriggerForm($prefix, $context);

		return str_replace($placeholder, $field . $placeholder, $form);
	}

	function getData()
	{
		$prefix = $this->getDataKeyPrefix();
		$data = parent::getData();
		$data[$prefix . 'guest'] = $this->isGuestAllowed() ? 'on' : '';
		$data[$prefix . 'roles'] = base64_encode(json_encode($this->getRoles()));
		$data = $this->filterSponseeTriggerData($data, $prefix);
		return $data;
	}

	function submit($form = array(), $source = 'editlist')
	{
		$prefix = $this->getDataKeyPrefix();
		$values = \apply_filters('lws_adminpanel_arg_parse', array(
			'post'     => ($source == 'post'),
			'values'   => $form,
			'format'   => array(
				$prefix . 'guest' => 's',
				$prefix . 'roles' => array('S'),
			),
			'defaults' => array(
				$prefix . 'guest' => '',
				$prefix . 'roles' => array(),
			),
			'labels'   => array(
				$prefix . 'guest' => __("Guest order", 'woorewards-pro'),
				$prefix . 'roles' => __("Sponsee roles restriction", 'woorewards-pro'),
			)
		));
		if (!(isset($values['valid']) && $values['valid']))
			return isset($values['error']) ? $values['error'] : false;

		$valid = parent::submit($form, $source);
		if (true !== $valid)
			return $valid;
		$valid = $this->optSponseeTriggerSubmit($prefix, $form, $source);
		if (true !== $valid)
			return $valid;

		$this->setGuestAllowed($values['values'][$prefix . 'guest']);
		$this->setRoles($values['values'][$prefix . 'roles']);
		return $valid;
	}

	public function setGuestAllowed($yes)
	{
		$this->guestAllowed = boolval($yes);
		return $this;
	}

	/** @return bool */
	function isGuestAllowed()
	{
		return $this->guestAllowed;
	}

	/** @return array */
	public function getRoles()
	{
		return $this->roles;
	}

	public function setRoles($roles)
	{
		if (!is_array($roles))
			$roles = @json_decode(@base64_decode($roles));
		if (is_array($roles))
			$this->roles = $roles;
		return $this;
	}

	public function isValidRoles($user)
	{
		$roles = $this->getRoles();
		if (!$roles)
			return true;

		if ($user && !\is_object($user)) {
			$user = \get_user_by('ID', $user);
		}
		if ($user && $user->ID) {
			return !empty(\array_intersect($user->roles, $roles));
		}
		return false;
	}

	/** Inhereted Event already instanciated from WP_Post, $this->id is availble. It is up to you to load any extra configuration. */
	protected function _fromPost(\WP_Post $post)
	{
		parent::_fromPost($post);
		$this->setGuestAllowed(\get_post_meta($post->ID, 'wre_event_guest', true));
		$this->setRoles(\get_post_meta($post->ID, 'wre_sponsored_roles', true));
		$this->optSponseeTriggerFromPost($post);
		return $this;
	}

	/** Event already saved as WP_Post, $this->id is availble. It is up to you to save any extra configuration. */
	protected function _save($id)
	{
		parent::_save($id);
		\update_post_meta($id, 'wre_event_guest', $this->isGuestAllowed() ? 'on' : '');
		\update_post_meta($id, 'wre_sponsored_roles', $this->getRoles());
		$this->optSponseeTriggerSave($id);
		return $this;
	}

	/** override */
	function orderDone($order)
	{
		$sponsorship = new \LWS\WOOREWARDS\Core\Sponsorship();
		$this->sponsorship = $sponsorship->getUsersFromOrder($order->order, $this->isGuestAllowed());

		if (!$this->sponsorship->sponsor_id)
			return $order;
		if (!$this->isValidRoles($this->sponsorship->sponsored_id))
			return $order;
		if (!$this->acceptOrder($order->order))
			return $order;
		if (!$this->isValidCurrency($order->order))
			return $order;
		return parent::orderDone($order);
	}

	protected function isTheFirst(&$order)
	{
		$c = \LWS\WOOREWARDS\Conveniences::getOrderCount(
			$this->sponsorship->sponsored_id,
			$order->order->get_id(),
			'sponsorship',
			$this->sponsorship->sponsored_email
		);
		return 0 == $c;
	}

	/** @param $order (\WC_Order)
	 * @return (int) user ID */
	function getPointsRecipient($order)
	{
		if ($this->sponsorship && $this->sponsorship->sponsor_id)
			return $this->sponsorship->sponsor_id;
		else
			return false;
	}

	protected function getGainInfo($info, $order)
	{
		if ($this->sponsorship)
			$info['sponsee'] = $this->sponsorship->sponsored_id;
		return $info;
	}

	/** @param $order (\WC_Order)
	 * @return (\LWS\WOOREWARDS\Core\Trace) a reason for history */
	function getPointsReason($order, $name)
	{
		if ($name) {
			$reason = array('Referred friend %1$s bought %2$s on order %3$s', $order->get_billing_email(), implode(', ', $name), $order->get_order_number());
		} else {
			$reason = array('Referred friend %1$s bought products on order #%2$s', $order->get_billing_email(), $order->get_order_number());
		}
		return \LWS\WOOREWARDS\Core\Trace::byOrder($order)
			->setProvider($order->get_customer_id('edit'))
			->setReason($reason, 'woorewards-pro');
	}

	/* The sponsor and sponsored will never see that value, so it should always return 0 */
	function getPointsForProduct(\WC_Product $product)
	{
		return 0;
	}

	/** Never call, only to have poedit/wpml able to extract the sentance. */
	private function poeditDeclare()
	{
		__('Referred friend %1$s bought %2$s on order %3$s', 'woorewards-pro');
		__('Referred friend %1$s bought products on order #%2$s', 'woorewards-pro');
	}

	/**	Event categories, used to filter out events from pool options.
	 *	@return array with category_id => category_label. */
	public function getCategories()
	{
		return array(
			\LWS\WOOREWARDS\Core\Pool::T_STANDARD  => __("Standard", 'woorewards-pro'),
			\LWS\WOOREWARDS\Core\Pool::T_LEVELLING => __("Levelling", 'woorewards-pro'),
			'achievement' => __("Achievement", 'woorewards-pro'),
			'custom'      => __("Events", 'woorewards-pro'),
			'woocommerce' => __("WooCommerce", 'woorewards-pro'),
			'sponsorship' => __("Available for referred", 'woorewards-pro')
		);
	}
}