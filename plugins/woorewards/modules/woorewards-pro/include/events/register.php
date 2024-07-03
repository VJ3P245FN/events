<?php
namespace LWS\WOOREWARDS\PRO\Events;

// don't call the file directly
if( !defined( 'ABSPATH' ) ) exit();

/** Earn points each time a customer denounces a friend. */
class Register extends \LWS\WOOREWARDS\Abstracts\Event
{
	use \LWS\WOOREWARDS\PRO\Events\T_SponsorshipOrigin;

	function getInformation()
	{
		return array_merge(parent::getInformation(), array(
			'icon'  => 'lws-icon-user-plus',
			'short' => __("The customer will earn points when registering on your website.", 'woorewards-pro'),
			'help'  => __("You can use this method to give points for referred registrations", 'woorewards-pro'),
		));
	}

	protected function acceptNoneValue()
	{
		return true;
	}

	function getData()
	{
		$prefix = $this->getDataKeyPrefix();
		$data = parent::getData();
		return $this->filterSponsorshipData($data, $prefix);
	}

	function getForm($context='editlist')
	{
		$prefix = $this->getDataKeyPrefix();
		$form = parent::getForm($context);
		return $this->filterSponsorshipForm($form, $prefix, $context, 2);
	}

	function submit($form=array(), $source='editlist')
	{
		$prefix = $this->getDataKeyPrefix();
		$valid = parent::submit($form, $source);
		if( $valid === true && ($valid = $this->optSponsorshipSubmit($prefix, $form, $source)) === true )
		{
		}
		return $valid;
	}

	/** Inhereted Event already instanciated from WP_Post, $this->id is availble. It is up to you to load any extra configuration. */
	protected function _fromPost(\WP_Post $post)
	{
		$this->optSponsorshipFromPost($post);
		return $this;
	}

	/** Event already saved as WP_Post, $this->id is availble. It is up to you to save any extra configuration. */
	protected function _save($id)
	{
		$this->optSponsorshipSave($id);
		return $this;
	}

	/** @return string a human readable type for UI */
	public function getDisplayType()
	{
		return _x("User Registration", "getDisplayType", 'woorewards-pro');
	}

	/** Add hook to grab events and add points. */
	protected function _install()
	{
		// sponsorship is done at 65535, hook later to be able to read any sponsorship origin.
		\add_action('user_register', array($this, 'trigger'), 999999, 1);
	}

	/** If nonce flag is already set from sponsor hook,
	 *	that method will not add more points. */
	function trigger($user_id)
	{
		$metaKey = $this->getType() . '-' . $this->getId();
		if( !\get_user_meta($user_id, $metaKey, true) )
		{
			$origin = \get_user_meta($user_id, 'lws_woorewards_sponsored_origin', true);
			if( !$origin )
				$origin = 'none';
			if( !$this->isValidOrigin($origin) )
				return;

			if( $points = \apply_filters('trigger_'.$this->getType(), 1, $this, $user_id) )
			{
				\update_user_meta($user_id, $metaKey, \date(DATE_W3C));
				$reason = \LWS\WOOREWARDS\Core\Trace::byReason(
					$origin == 'none' ? "User registered" : "User registered via referral",
					'woorewards-pro'
				);
				$this->addPoint($user_id, $reason, $points);
			}
		}
	}

	/** Never call, only to have poedit/wpml able to extract the sentance. */
	private function poeditDeclare()
	{
		__("User registered", 'woorewards-pro');
		__("User registered via referral", 'woorewards-pro');
	}

	public function getCategories()
	{
		return array_merge(parent::getCategories(), array(
			'site' => __("Website", 'woorewards-pro')
		));
	}
}
