<?php
namespace LWS\WOOREWARDS\PRO\Events;

// don't call the file directly
if( !defined( 'ABSPATH' ) ) exit();


/** Sponsor Earns points for the first time sponsored places an order. */
class SponsoredOrder extends \LWS\WOOREWARDS\Events\SponsoredOrder
{
	use \LWS\WOOREWARDS\PRO\Events\T_SponseeTrigger;
	use \LWS\WOOREWARDS\PRO\Events\T_SponsorshipOrigin;

	protected $roles = array();
	protected $guestAllowed = false;
	protected $minAmount = 0;

	function getClassname()
	{
		return 'LWS\WOOREWARDS\Events\SponsoredOrder';
	}

	public function isMaxTriggersAllowed()
	{
		return true;
	}

	function getData()
	{
		$prefix = $this->getDataKeyPrefix();
		$data = parent::getData();
		$data[$prefix.'guest'] = $this->isGuestAllowed() ? 'on' : '';
		$data[$prefix.'min_amount'] = $this->getMinAmount();
		$data[$prefix.'roles'] = base64_encode(json_encode($this->getRoles()));
		$data = $this->filterSponseeTriggerData($data, $prefix);
		return $data;
	}

	function getForm($context='editlist')
	{
		$prefix = $this->getDataKeyPrefix();
		$form = '';

		// Allow guest order
		$label   = _x("Guest order", "Referee Order Event", 'woorewards-pro');
		$tooltip = __("By default, customer must be registered. Check that option to accept guests. Customer will be tested on billing email.", 'woorewards-pro');
		$toggle = \LWS\Adminpanel\Pages\Field\Checkbox::compose($prefix . 'guest', array(
			'id'      => $prefix . 'guest',
			'layout'  => 'toggle',
		));
		$form .= "<div class='field-help'>$tooltip</div>";
		$form .= "<div class='lws-$context-opt-title label'>$label<div class='bt-field-help'>?</div></div>";
		$form .= "<div class='lws-$context-opt-input value'>$toggle</div>";

		// Minimum order amount
		$label = _x("Minimum order amount", "Referee Order Event", 'woorewards-pro');
		$tooltip = __("Uses the Order Subtotal as reference.", 'woorewards-pro');
		$form .= "<div class='field-help'>$tooltip</div>";
		$form .= "<div class='lws-$context-opt-title label'>$label<div class='bt-field-help'>?</div></div>";
		$form .= "<div class='lws-$context-opt-input value'><input type='text' id='{$prefix}min_amount' name='{$prefix}min_amount' placeholder='5' /></div>";

		$phe2 = $this->getFieldsetEnd(2);
		$form = \str_replace($phe2, $form . $phe2, parent::getForm($context));


		// Sponsee role
		$label = _x("Sponsee role", "Referee Order Event", 'woorewards-pro');
		$tooltip = __("The ponsee needs to have at least one of the selected roles to grant points to his referral. Leave empty for no restriction.", 'woorewards-pro');
		$field = "<div class='field-help'>{$tooltip}</div>";
		$field .= "<div class='lws-{$context}-opt-title label'>{$label}<div class='bt-field-help'>?</div></div>";
		$field .= "<div class='lws-$context-opt-input value'>";
		$field .= \LWS\Adminpanel\Pages\Field\LacChecklist::compose($prefix.'roles', array(
			'ajax'          => 'lws_adminpanel_get_roles',
			'comprehensive' => true,
			'class'         => 'above',
		));
		$field .= "</div>";

		// Max Sponsee Triggers
		$field .= $this->getSponseeTriggerForm($prefix, $context);

		$phe10 = $this->getFieldsetPlaceholder(false, 10);
		return str_replace($phe10, $field . $phe10, $form);
	}

	function submit($form=array(), $source='editlist')
	{
		$prefix = $this->getDataKeyPrefix();
		$values = \apply_filters('lws_adminpanel_arg_parse', array(
			'post'     => ($source == 'post'),
			'values'   => $form,
			'format'   => array(
				$prefix . 'guest'      => 's',
				$prefix . 'min_amount' => 'f',
				$prefix . 'roles'      => array('S'),
			),
			'defaults' => array(
				$prefix . 'guest'      => '',
				$prefix . 'min_amount' => '',
				$prefix . 'roles'      => array(),
			),
			'labels'   => array(
				$prefix . 'guest'      => __("Guest order", 'woorewards-pro'),
				$prefix . 'min_amount' => __("Minimum order amount", 'woorewards-pro'),
				$prefix . 'roles'      => __("Sponsee roles restriction", 'woorewards-pro'),
			)
		));
		if( !(isset($values['valid']) && $values['valid']) )
			return isset($values['error']) ? $values['error'] : false;

		$valid = parent::submit($form, $source);
		if (true !== $valid)
			return $valid;
		$valid = $this->optSponseeTriggerSubmit($prefix, $form, $source);
		if (true !== $valid)
			return $valid;

		$this->setGuestAllowed($values['values'][$prefix.'guest']);
		$this->setMinAmount($values['values'][$prefix.'min_amount']);
		$this->setRoles($values['values'][$prefix.'roles']);
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

	/** @return float */
	function getMinAmount()
	{
		return $this->minAmount;
	}

	public function setMinAmount($amount=0)
	{
		$this->minAmount = max(0.0, floatval(str_replace(',', '.', $amount)));
		return $this;
	}

	/** @return array */
	public function getRoles()
	{
		return $this->roles;
	}

	public function setRoles($roles)
	{
		if( !is_array($roles) )
			$roles = @json_decode(@base64_decode($roles));
		if( is_array($roles) )
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
		$firstOnly = \get_post_meta($post->ID, 'wre_event_first_order_only', false); // backward compatibility, option introduced on 3.6.0
		$this->setGuestAllowed(\get_post_meta($post->ID, 'wre_event_guest', true));
		$this->setMinAmount(\get_post_meta($post->ID, 'wre_event_min_amount', true));
		$this->optSponseeTriggerFromPost($post);
		$this->setRoles(\get_post_meta($post->ID, 'wre_sponsored_roles', true));
		return $this;
	}

	/** Event already saved as WP_Post, $this->id is availble. It is up to you to save any extra configuration. */
	protected function _save($id)
	{
		parent::_save($id);
		\update_post_meta($id, 'wre_event_guest', $this->isGuestAllowed() ? 'on' : '');
		\update_post_meta($id, 'wre_event_min_amount', $this->getMinAmount());
		$this->optSponseeTriggerSave($id);
		\update_post_meta($id, 'wre_sponsored_roles', $this->getRoles());
		return $this;
	}

	function getDescription($context='backend')
	{
		$descr = parent::getDescription($context);
		if( ($min = $this->getMinAmount()) > 0.0 )
		{
			$dec = \absint(\apply_filters('wc_get_price_decimals', \get_option( 'woocommerce_price_num_decimals', 2)));
			$descr .= sprintf(__(" (amount greater than %s)", 'woorewards-pro'), \number_format_i18n($min, $dec));
		}
		return $descr;
	}

	/** @param $sponsorshipInfo unused here but convenient for parent method. */
	function orderDone($order, $sponsorshipInfo=false)
	{
		$sponsorship = new \LWS\WOOREWARDS\Core\Sponsorship();
		$this->sponsorship = $sponsorship->getUsersFromOrder($order->order, $this->isGuestAllowed());

		if( !$this->sponsorship->sponsor_id )
			return $order;
		if (!$this->isValidRoles($this->sponsorship->sponsored_id))
			return $order;
		if( $order->amount < $this->getMinAmount() )
			return $order;

		return parent::orderDone($order, $this->sponsorship);
	}
}