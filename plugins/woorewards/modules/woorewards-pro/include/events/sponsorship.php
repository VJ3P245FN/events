<?php
namespace LWS\WOOREWARDS\PRO\Events;

// don't call the file directly
if( !defined( 'ABSPATH' ) ) exit();

/** Earn points each time a customer denounces a friend. */
class Sponsorship extends \LWS\WOOREWARDS\Abstracts\Event
{
	function getInformation()
	{
		return array_merge(parent::getInformation(), array(
			'icon'  => 'lws-icon-b-meeting',
			'short' => __("The customer will receive points for sending a referral by email.", 'woorewards-pro'),
			'help'  => __("This method will only reward the referrer, not the referee", 'woorewards-pro'),
		));
	}

	function isRuleSupportedCooldown() { return true; }

	/** Inhereted Event already instanciated from WP_Post, $this->id is availble. It is up to you to load any extra configuration. */
	protected function _fromPost(\WP_Post $post)
	{
		return $this;
	}

	/** Event already saved as WP_Post, $this->id is availble. It is up to you to save any extra configuration. */
	protected function _save($id)
	{
		return $this;
	}

	/** @return string a human readable type for UI */
	public function getDisplayType()
	{
		return _x("Send a referral email", "getDisplayType", 'woorewards-pro');
	}

	/** Add hook to grab events and add points. */
	protected function _install()
	{
		\add_action('lws_woorewards_sponsorship_done', array($this, 'trigger'), 10, 2);
	}

	function trigger($sponsor, $sponsored)
	{
		if (!$this->isCool($sponsor->ID))
			return;

		if( $points = \apply_filters('trigger_'.$this->getType(), 1, $this, $sponsor, $sponsored) )
		{
			$reason = \LWS\WOOREWARDS\Core\Trace::byReason(array("Customer refers %s", $sponsored), 'woorewards-pro');
			$this->addPoint($sponsor->ID, $reason, $points);
		}
	}

	/** Never call, only to have poedit/wpml able to extract the sentance. */
	private function poeditDeclare()
	{
		__("Customer refers %s", 'woorewards-pro');
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
