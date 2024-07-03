<?php
namespace LWS\WOOREWARDS\PRO\Events;

// don't call the file directly
if( !defined( 'ABSPATH' ) ) exit();

/** Sponsor earns points for sponsored registration. */
class SponsoredRegistration extends \LWS\WOOREWARDS\Abstracts\Event
{
	use \LWS\WOOREWARDS\PRO\Events\T_SponsorshipOrigin;

	function getInformation()
	{
		return array_merge(parent::getInformation(), array(
			'icon'  => 'lws-icon-user-plus',
			'short' => __("The customer will earn points when a person he referred registers on your website.", 'woorewards-pro'),
			'help'  => __("This method will only reward the referrer, not the referee", 'woorewards-pro'),
		));
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
		return _x("Referee registration", "getDisplayType", 'woorewards-pro');
	}

	/** Add hook to grab events and add points. */
	protected function _install()
	{
		\add_action('lws_woorewards_sponsored_registration', array($this, 'trigger'), 10, 5);
	}

	function trigger($sponsorId, $user, $oldSonpsorId=false, $origin=false, $newUser=true)
	{
		if (!$newUser) return;

		$metaKey = $this->getType() . '-' . $this->getId();
		global $wpdb;
		$exists = \intval($wpdb->get_var($wpdb->prepare(
			"SELECT COUNT(*) FROM {$wpdb->usermeta} WHERE meta_key=%s AND user_id=%d AND meta_value=%s",
			$metaKey, $sponsorId, $user->ID
		)));
		if( !$exists )
		{
			if( !$this->isValidOrigin($origin) )
				return;

			if( $points = \apply_filters('trigger_'.$this->getType(), 1, $this, $sponsorId, $user, $oldSonpsorId, $origin) )
			{
				\add_user_meta($sponsorId, $metaKey, $user->ID, false);
				$reason = \LWS\WOOREWARDS\Core\Trace::byReason(array("The referred friend %s registered", $user->user_email), 'woorewards-pro');
				$this->addPoint(array(
					'user'    => $sponsorId,
					'sponsee' => $user,
				), $reason, $points, $user->ID);
			}
		}
	}

	/** Never call, only to have poedit/wpml able to extract the sentance. */
	private function poeditDeclare()
	{
		__("The referred friend %s registered", 'woorewards-pro');
	}

	/**	Event categories, used to filter out events from pool options.
	 *	@return array with category_id => category_label. */
	public function getCategories()
	{
		return array_merge(parent::getCategories(), array(
			'sponsorship' => __("Available for referred", 'woorewards-pro')
		));
	}
}
