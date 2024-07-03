<?php
namespace LWS\WOOREWARDS\PRO\Unlockables;

// don't call the file directly
if( !defined( 'ABSPATH' ) ) exit();

require_once LWS_WOOREWARDS_PRO_INCLUDES . '/core/usertitle.php';

/**
 * Assign a badge to a user. */
class Badge extends \LWS\WOOREWARDS\Abstracts\Unlockable
{
	private $badgeId          = '';
	private $removedBagdesIds = array();

	function getInformation()
	{
		return array_merge(parent::getInformation(), array(
			'icon'  => 'lws-icon-cockade',
			'short' => __("The customer will receive a badge and see a badge animation on the website.", 'woorewards-pro'),
			'help'  => __("Badges can also be used as a method to earn points.", 'woorewards-pro'),
		));
	}

	function getData($min=false)
	{
		$prefix = $this->getDataKeyPrefix();
		$data = parent::getData();
		$data[$prefix.'badge'] = $this->getBadgeId();
		$data[$prefix.'badges_removed'] = base64_encode(json_encode($this->getRemovedBadgesIds()));
		return $data;
	}

	function getForm($context='editlist')
	{
		$prefix = $this->getDataKeyPrefix();
		$form = parent::getForm($context);
		$form .= $this->getFieldsetBegin(2, __("Badge", 'woorewards-pro'));

		// The badge
		$label   = _x("Badge", "event form", 'woorewards-pro');
		$form .= "<div class='lws-$context-opt-title label bold'>$label</div>";
		$form .= "<div class='lws-$context-opt-input lws-lac-select-badge'>";
		$form .= \LWS\Adminpanel\Pages\Field\LacSelect::compose($prefix.'badge', array(
			'ajax' => 'lws_woorewards_badge_list',
			'value' => $this->getBadgeId(),
			'maxwidth' => '600px',
		));
		$form .= "</div>";

		// Badges to remove
		$label   = _x("Badges to remove", "Badge Unlockable", 'woorewards-pro');
		$form .= "<div class='lws-$context-opt-title label'>$label</div>";
		$form .= "<div class='lws-$context-opt-input value'>";
		$form .= \LWS\Adminpanel\Pages\Field\LacChecklist::compose($prefix.'badges_removed', array(
			'ajax' => 'lws_woorewards_badge_list',
			'value' => $this->getRemovedBadgesIds()
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
				$prefix.'badge' => 'D',
				$prefix.'badges_removed' => array('D'),
			),
			'defaults' => array(
				$prefix.'badges_removed' => array()
			),
			'labels'   => array(
				$prefix.'badge' => __("Badge", 'woorewards-pro'),
				$prefix.'badges_removed' => __("Badges to remove", 'woorewards-pro'),
			)
		));
		if( !(isset($values['valid']) && $values['valid']) )
			return isset($values['error']) ? $values['error'] : false;

		$valid = parent::submit($form, $source);
		if( $valid === true )
		{
			$this->setBadgeId($values['values'][$prefix.'badge']);
			$this->setRemovedBadgesIds($values['values'][$prefix.'badges_removed']);
		}
		return $valid;
	}

	/** @return false|\LWS\WOOREWARDS\PRO\Core\Badge */
	public function getBadge()
	{
		$badge = new \LWS\WOOREWARDS\PRO\Core\Badge($this->getBadgeId());
		return $badge->isValid() ? $badge : false;
	}

	/** @return int|string */
	public function getBadgeId()
	{
		return $this->badgeId;
	}

	public function setBadgeId($badge)
	{
		$this->badgeId = $badge;
		return $this;
	}

	/** @return array */
	function getRemovedBadgesIds()
	{
		return $this->removedBagdesIds;
	}

	/** @param $categories (array|string) as string, it should be a json base64 encoded array. */
	function setRemovedBadgesIds($badges=array())
	{
		if( !is_array($badges) )
			$badges = @json_decode(@base64_decode($badges));
		if( is_array($badges) )
			$this->removedBagdesIds = $badges;
		return $this;
	}

	public function setTestValues()
	{
		return $this;
	}

	protected function _fromPost(\WP_Post $post)
	{
		$this->setBadgeId(\get_post_meta($post->ID, 'woorewards_badge_id', true));
		$this->setRemovedBadgesIds(\get_post_meta($post->ID, 'woorewards_badges_removed', true));
		return $this;
	}

	protected function _save($id)
	{
		\update_post_meta($id, 'woorewards_badge_id', $this->getBadgeId());
		\update_post_meta($id, 'woorewards_badges_removed', $this->getRemovedBadgesIds());
		return $this;
	}

	public function createReward(\WP_User $user, $demo=false)
	{
		if( !($user && $user->ID) )
			return false;
		if( !$demo )
		{
			// First, remove other badges, it can be the same that will be given after.
			if( $toRemove = $this->getRemovedBadgesIds() )
				$this->removeBadges($user->ID, $toRemove, false);

			if( $badge = $this->getBadge() )
			{
				if( false === $badge->assignTo($user->ID, $this->getId()) )
					return false; // user already got that badge
			}
		}
		return $badge;
	}

	/** @param $toRemove (array of int) the badge ids to remove from $userId.
	 *	@param $filterMeOut (bool) if true, omit getBadgeId() if in $toRemove array. */
	protected function removeBadges($userId, $toRemove = array(), $filterMeOut=true)
	{
		$exclude = $filterMeOut ? $this->getBadgeId() : 0;
		foreach($toRemove as $badgeId)
		{
			if( $exclude != $badgeId )
			{
				$badge = new \LWS\WOOREWARDS\PRO\Core\Badge($badgeId);
				$badge->removeFrom($userId);
			}
		}
	}

	public function getDisplayType()
	{
		return _x("Assign a badge", "getDisplayType", 'woorewards-pro');
	}

	/** use product image by default if any but can be override by user */
	public function getThumbnailUrl()
	{
		if( empty($this->getThumbnail()) && !empty($badge = $this->getBadge()) && !empty($img = $badge->getThumbnailUrl()) )
			return $img;
		else
			return parent::getThumbnailUrl();
	}

	/** use product image by default if any but can be override by user */
	public function getThumbnailImage($size='lws_wr_thumbnail')
	{
		if( empty($this->getThumbnail()) && !empty($badge = $this->getBadge()) && !empty($img = $badge->getThumbnailImage($size)) )
			return $img;
		else
			return parent::getThumbnailImage($size);
	}

	/**	Provided to be overriden.
	 *	@param $context usage of text. Default is 'backend' for admin, expect 'frontend' for customer.
	 *	@return (string) what this does. */
	function getDescription($context='backend')
	{
		$badge = $this->getBadge();
		$name = $badge ? $badge->getTitle() : __('[unknown]', 'woorewards-pro');

		$str = '';
		if( $context != 'raw' )
		{
			$url = false;
			if( $context == 'backend' && $badge )
				$url = $badge->getEditLink(true);

			$name = $url ? "<a href='{$url}'>{$name}</a>" : "<b>{$name}</b>";
			$str .= sprintf(_x("Assign badge %s", 'pretty text', 'woorewards-pro'), $name);
		}
		else
			$str .= sprintf(_x("Assign badge '%s'", 'raw text', 'woorewards-pro'), $name);

		return $str;
	}

	/** A badge can only be purchased once.
	 * @return (bool) if user already owned the badge. */
	public function noMorePurchase($userId)
	{
		if( !\is_admin() && !(defined('DOING_AJAX') && DOING_AJAX) )
		{
			$badge = $this->getBadge();
			if( $badge && $userId && $badge->ownedBy($userId) )
				return true;
		}
		return false;
	}

	public function isPurchasable($points=PHP_INT_MAX, $userId=null)
	{
		$purchasable = parent::isPurchasable($points, $userId);
		if( $purchasable && !\is_admin() && !(defined('DOING_AJAX') && DOING_AJAX) )
		{
			if (!($badge = $this->getBadge())) {
				$purchasable = false;
			} elseif ($userId) {
				$rem = $this->getRemovedBadgesIds();
				if (!($rem && \in_array($badge->getId(), $rem)) && $badge->ownedBy($userId))
					$purchasable = false;
			}
		}
		return $purchasable;
	}

	/**	Event categories, used to filter out events from pool options.
	 *	@return array with category_id => category_label. */
	public function getCategories()
	{
		return array_merge(parent::getCategories(), array(
			'badge' => __("Badge", 'woorewards-pro'),
			'achievements' => __("Achievements", 'woorewards-pro'),
			'wp_user'   => __("User", 'woorewards-pro'),
		));
	}
}