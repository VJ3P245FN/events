<?php
namespace LWS\WOOREWARDS\PRO\Events;

// don't call the file directly
if( !defined( 'ABSPATH' ) ) exit();

/** Earn points each time a user got a badge. */
class Badge extends \LWS\WOOREWARDS\Abstracts\Event
{
	protected $badgeIds = array();

	function getInformation()
	{
		return array_merge(parent::getInformation(), array(
			'icon'  => 'lws-icon-lw_reward',
			'short' => __("The customer will earn points when he earns one or more badges.", 'woorewards-pro'),
			'help'  => __("Combine this method with Achievements to reward customers when they unlock an achievement", 'woorewards-pro'),
		));
	}

	function getData()
	{
		$prefix = $this->getDataKeyPrefix();
		$data = parent::getData();
		$data[$prefix.'badge'] = base64_encode(json_encode($this->getBadgeIds()));
		return $data;
	}

	function getForm($context='editlist')
	{
		$prefix = $this->getDataKeyPrefix();
		$form = parent::getForm($context);
		$form .= $this->getFieldsetBegin(2, __("Badges", 'woorewards-pro'));

		// The badges
		if( $context == 'achievements' ) {
			$tooltip = __("Action will be counted only when all selected badges will be owned by the customer.", 'woorewards-pro');
		} else {
			$tooltip = __("Points will be earned only when all selected badges will be owned by the customer", 'woorewards-pro');
		}
		$label   = _x("Badges", "event form", 'woorewards-pro');
		$form .= "<div class='field-help'>$tooltip</div>";
		$form .= "<div class='lws-$context-opt-title label'>$label<div class='bt-field-help'>?</div></div>";
		$form .= "<div class='lws-$context-opt-input lws-lac-select-badge'>";
		$form .= \LWS\Adminpanel\Pages\Field\LacChecklist::compose($prefix.'badge', array(
			'ajax' => 'lws_woorewards_badge_list',
			'value' => $this->getBadgeIds()
		));
		$form .= "</div>";

		$form .= $this->getFieldsetEnd(2);
		return $form;
	}

	function submit($form=array(), $source='editlist')
	{
		$prefix = $this->getDataKeyPrefix();
		$values = \apply_filters('lws_adminpanel_arg_parse', array(
			'post'     => ($source == 'post'),
			'values'   => $form,
			'format'   => array(
				$prefix.'badge' => array('D'),
			),
			'defaults' => array(
			),
			'labels'   => array(
				$prefix.'badge' => __("Badges", 'woorewards-pro'),
			)
		));
		if( !(isset($values['valid']) && $values['valid']) )
			return isset($values['error']) ? $values['error'] : false;

		if( !(isset($values['values'][$prefix.'badge']) && $values['values'][$prefix.'badge']) )
			return __("Select at least one badge", 'woorewards-pro');

		$valid = parent::submit($form, $source);
		if( $valid === true )
		{
			$this->setBadgeIds($values['values'][$prefix.'badge']);
		}
		return $valid;
	}

	public function getBadges()
	{
		$badges = array();
		foreach($this->getBadgeIds() as $badgeId)
		{
			$badge = new \LWS\WOOREWARDS\PRO\Core\Badge($badgeId);
			if( $badge->isValid() )
				$badges[] = $badge;
		}
		return $badges;
	}

	public function getBadgeIds()
	{
		return $this->badgeIds;
	}

	public function setBadgeIds($badgeIds = array())
	{
		if( !is_array($badgeIds) )
			$badgeIds = @json_decode(@base64_decode($badgeIds));
		if( is_array($badgeIds) )
			$this->badgeIds = $badgeIds;
		return $this;
	}

	/** Inhereted Event already instanciated from WP_Post, $this->id is availble. It is up to you to load any extra configuration. */
	protected function _fromPost(\WP_Post $post)
	{
		$this->setBadgeIds(\get_post_meta($post->ID, 'woorewards_badge_id', true));
		return $this;
	}

	/** Event already saved as WP_Post, $this->id is availble. It is up to you to save any extra configuration. */
	protected function _save($id)
	{
		\update_post_meta($id, 'woorewards_badge_id', $this->getBadgeIds());
		return $this;
	}

	/** @return string a human readable type for UI */
	public function getDisplayType()
	{
		return _x("User unlocked badges", "getDisplayType", 'woorewards-pro');
	}

	/** Add hook to grab events and add points. */
	protected function _install()
	{
		if( $this->getBadgeIds() )
		{
			\add_action('lws_wooreward_badge_assigned', array($this, 'trigger'), 10, 2);
		}
	}

	/** trigger event if user has all the badges */
	function trigger($user_id, $badge)
	{
		if( in_array($badge->getId(), $this->getBadgeIds()) && !empty($badges = $this->getBadges()) )
		{
			foreach( $badges as $badge )
			{
				if( !$badge->ownedBy($user_id) )
					return;
			}

			if( $points = \apply_filters('trigger_'.$this->getType(), 1, $this, $user_id, $badge) )
			{
				$names = $this->getBadgeNames($badges, 'raw', _x(", ", "badge name separator", 'woorewards-pro'));
				$reason = \LWS\WOOREWARDS\Core\Trace::byReason(array(
					(1==count($badges) ? "Badge %s unlocked" : "Badges %s unlocked"),
					$names
				), 'woorewards-pro');
				$this->addPoint($user_id, $reason, $points);
			}
		}
	}

	/** Never call, only to have poedit/wpml able to extract the sentance. */
	private function poeditDeclare()
	{
		__("Badge %s unlocked", 'woorewards-pro');
		__("Badges %s unlocked", 'woorewards-pro');
	}

	function getDescription($context='backend')
	{
		$badges = $this->getBadges();
		$names = $this->getBadgeNames($badges, $context, _x(", ", "badge name separator", 'woorewards-pro'));
		return sprintf(_n("User got the badge %s", "User got the badges %s", count($badges), 'woorewards-pro'), $names);
	}

	protected function getBadgeNames($badges, $context, $sep=', ')
	{
		$names = array();
		foreach($badges as $badge)
		{
			$name = $badge->getTitle();
			if( $context != 'raw' )
			{
				if( $context == 'backend' )
				{
					$url = $badge->getEditLink(true);
					$name = "<a href='{$url}'>{$name}</a>";
				}
				else
					$name = "<b>{$name}</b>";
				$name .= $badge->getThumbnailImage();
			}
			$names[] = $name;
		}
		return implode($sep, $names);
	}

	public function getCategories()
	{
		return array_merge(parent::getCategories(), array(
			'badge' => __("Badge", 'woorewards-pro'),
			'miscellaneous' => __("Miscellaneous", 'woorewards-pro'),
			'wp_user'   => __("User", 'woorewards-pro'),
		));
	}
}