<?php
namespace LWS\WOOREWARDS\PRO\Events;

// don't call the file directly
if( !defined( 'ABSPATH' ) ) exit();

/** Earn points each year at register date. */
class Anniversary extends \LWS\WOOREWARDS\Abstracts\Event
{
	private $mkey = false;

	function getInformation()
	{
		return array_merge(parent::getInformation(), array(
			'icon'  => 'lws-icon-birthday-cake',
			'short' => __("The customer will earn points for every registration anniversary.", 'woorewards-pro'),
			'help'  => __("Don't use this method to give points on registration", 'woorewards-pro'),
		));
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
		return _x("Registration's anniversary", "getDisplayType", 'woorewards-pro');
	}

	/** Add hook to grab events and add points. */
	protected function _install()
	{
		\add_action('lws_woorewards_daily_event', array($this, 'trigger'));
	}

	/** @return string a usermeta.meta_key to store thi last rewarded anniversary */
	protected function getMetaKey()
	{
		if( !$this->mkey )
			$this->mkey = $this->getType() . '-' . $this->getId();
		return $this->mkey;
	}

	/** Look for all users once a day */
	function trigger()
	{
		$mkey = $this->getMetaKey();
		global $wpdb;
		$sql = <<<EOT
SELECT ID, DATE(user_registered) as ref, DATE(meta_value) as saved FROM {$wpdb->users}
LEFT JOIN {$wpdb->usermeta} ON user_id=ID AND meta_key='{$mkey}'
WHERE DATE_ADD(DATE(user_registered), INTERVAL 1 YEAR) <= CURDATE()
AND (meta_value IS NULL OR DATE_ADD(DATE(meta_value), INTERVAL 1 YEAR) <= CURDATE())
EOT;
		if ($this->eventCreationDate)
			$sql .= sprintf("\nAND TIMESTAMP(user_registered) >= TIMESTAMP('%s')", $this->eventCreationDate->format('Y-m-d H-i-s'));
		$users = $wpdb->get_results($sql);
		if( !is_array($users) )
			return;

		foreach( $users as $user )
			$this->addPointsPerYear($user->ID, $user->ref, $user->saved);
	}

	/** Starting one year after max(reference, last), add points for each year up to today.
	 *	Assume any $last is the same day as reference, only year should change.
	 *	@param $reference the original date.
	 *	@param $last if set, replace the original date. */
	protected function addPointsPerYear($user_id, $reference, $last=false)
	{
		static $today = false;
		if( !$today )
			$today = \date_create();
		if( !empty($last) )
			$reference = $last;
		if( !\is_a($reference, 'DateTime') )
			$reference = \date_create($reference);
		$reference->setTime(0, 0);
		$year = new \DateInterval('P1Y');

		$date = false;
		while( $reference->add($year) <= $today )
		{
			$date = $reference->format('Y-m-d');
		}

		if( !empty($date) && ($points = \apply_filters('trigger_'.$this->getType(), 1, $this, $user_id, $date)) )
		{
			\update_user_meta($user_id, $this->getMetaKey(), $date);
			$reason = \LWS\WOOREWARDS\Core\Trace::byReason(array("Anniversary (registration) %s", $date), 'woorewards-pro');
			$this->addPoint($user_id, $reason, $points);
		}
	}

	/** Never call, only to have poedit/wpml able to extract the sentance. */
	private function poeditDeclare()
	{
		__("Anniversary (registration) %s", 'woorewards-pro');
	}

	public function getCategories()
	{
		return array_merge(parent::getCategories(), array(
			'miscellaneous' => __("Miscellaneous", 'woorewards-pro')
		));
	}
}