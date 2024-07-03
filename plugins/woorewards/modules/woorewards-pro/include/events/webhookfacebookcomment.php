<?php
namespace LWS\WOOREWARDS\PRO\Events;

// don't call the file directly
if( !defined( 'ABSPATH' ) ) exit();

/** Earn points for sharing on social networks.
 * Use our local sharing widget/shortcode */
class WebHookFacebookComment extends \LWS\WOOREWARDS\Abstracts\Event
{
	function getInformation()
	{
		return array_merge(parent::getInformation(), array(
			'icon'  => 'lws-icon-facebook2',
			'short' => __("The customer will earn points every time he comments one of your Facebook posts.", 'woorewards-pro'),
			'help'  => __("The customer has to link his Facebook account to your site to be able to earn points", 'woorewards-pro'),
		));
	}

	function isRuleSupportedCooldown() { return true; }

	function submit($form=array(), $source='editlist')
	{
		$valid = parent::submit($form, $source);
		if( $valid === true )
		{
			require_once LWS_WOOREWARDS_PRO_INCLUDES . '/ui/adminscreens/socials.php';
			list($verif, $confirmed) = \LWS\WOOREWARDS\PRO\Ui\AdminScreens\Socials::getVerifiedStatus('facebook');
			if (!$confirmed) {
				return __("You must finalize your Facebook settings and verify them before saving such an earning method", 'woorewards-pro');
			}
		}
		return $valid;
	}

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
		return _x("Comment your Facebook posts", "getDisplayType", 'woorewards-pro');
	}

	/** Add hook to grab events and add points. */
	protected function _install()
	{
		\add_action('lws_woorewards_wh_facebook_event_comment', array($this, 'trigger'), 10, 3);
	}

	/** @param $userId user that should earn points.
	 *	@param $value see facebook documentation
	 *	@param $dbId the wp_lws_webhooks_events table id */
	function trigger($userId, $value, $dbId)
	{
		if (!$userId)
			return;
		if (!$this->isCool($userId))
			return;

		if( $points = \apply_filters('trigger_'.$this->getType(), 1, $this, $userId, $value, $dbId) )
		{
			$reason = \LWS\WOOREWARDS\Core\Trace::byReason("Comment a Facebook post", 'woorewards-pro');
			$this->addPoint($userId, $reason, $points, $dbId);
		}
	}

	/** Never call, only to have poedit/wpml able to extract the sentance. */
	private function poeditDeclare()
	{
		__("Comment a Facebook post", 'woorewards-pro');
	}

	public function getCategories()
	{
		return array_merge(parent::getCategories(), array(
			'social' => __("Social network", 'woorewards-pro'),
			'facebook' => 'Facebook',
		));
	}
}
