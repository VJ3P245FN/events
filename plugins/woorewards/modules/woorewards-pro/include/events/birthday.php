<?php
namespace LWS\WOOREWARDS\PRO\Events;

// don't call the file directly
if( !defined( 'ABSPATH' ) ) exit();

/** Earn points each year at register date. */
class Birthday extends \LWS\WOOREWARDS\Abstracts\Event
{
	protected $birthdayMetaKey = false;
	protected $earlyTrigger = false;
	private $mkey = false;

	function getInformation()
	{
		return array_merge(parent::getInformation(), array(
			'icon'  => 'lws-icon-birthday-cake',
			'short' => __("The customer will earn points for his birthday. A security prevents users from changing their date to trigger this multiple times.", 'woorewards-pro'),
			'help'  => __("You can choose to trigger this a few days earlier to send customers a coupon they can use for their birthday", 'woorewards-pro'),
		));
	}

	function getData()
	{
		$prefix = $this->getDataKeyPrefix();
		$data = parent::getData();
		$data[$prefix.'umkey'] = $this->getBirthdayMetaKey();
		if( is_array($data[$prefix.'umkey']) )
			$data[$prefix.'umkey'] = implode(', ', $data[$prefix.'umkey']);
		$data[$prefix.'early'] = $this->getEarlyTrigger()->toString();

		if ($this->getId()) {
			$label = __('Last CRON', 'woorewards-pro');
			$lastCron = \get_post_meta($this->getId(), 'lws_woorewards_birthday_cron_last_call', true);
			if ($lastCron)
				$lastCron = \date_create($lastCron);
			$lastCron = $lastCron ? $lastCron->format('Y-m-d H:i') : '—';
			$data[$prefix.'last_cron'] = <<<EOT
<div class='lws-editlist-opt-title label'>{$label}</div>
<div class='lws-editlist-opt-input value'>{$lastCron}</div>
EOT;

			$lastCheck = \get_post_meta($this->getId(), 'lws_woorewards_birthday_cron_last_users_counts', true);
			if ($lastCheck && (3 == count($lastCheck = explode('/', $lastCheck, 3)))) {
				$d = \date_create($lastCheck[2]);
				$label = sprintf(__('Birthdays on %s', 'woorewards-pro'), $d ? $d->format('Y-m-d') : '[date unknown]');
				$lastCheck = sprintf('%d / %d', \intval($lastCheck[0]), \intval($lastCheck[1]));
				$help = __('That values only count last validated passed birthday dates. Other restriction in fidelity system (like user roles, etc.) could prevent users getting points anyway.', 'woorewards-pro');
				$data[$prefix.'last_check'] = <<<EOT
<div class='field-help'>$help</div>
<div class='lws-editlist-opt-title label'>{$label}<div class='bt-field-help'>?</div></div>
<div class='lws-editlist-opt-input value'>{$lastCheck}</div>
EOT;
			}
		}
		return $data;
	}

	function getForm($context='editlist')
	{
		$prefix = $this->getDataKeyPrefix();
		$form = parent::getForm($context);
		$form .= $this->getFieldsetBegin(2, __("Information", 'woorewards-pro'), 'col30');

		$settingsUrl = \esc_attr(\add_query_arg(
			array(
				'page' => LWS_WOOREWARDS_PAGE.'.loyalty',
				'tab' => 'general_settings'
			),
			admin_url('admin.php#lws_group_targetable_wc_birthday')
		));
		$help = sprintf(__("If you don't already have a birthday field in your customer registration form, go <a %s>here</a> to add one.", 'woorewards-pro'), " target='_blank' href='{$settingsUrl}'");
		$form .= "<div class='field-help displayed'>$help</div>";

		// early trigger
		$label = _x("Early trigger", "Coupon Unlockable", 'woorewards-pro');
		$tooltip = __("Trigger X days before birthday.", 'woorewards-pro');
		$value = $this->getEarlyTrigger()->toString();
		$form .= "<div class='field-help'>$tooltip</div>";
		$form .= "<div class='lws-$context-opt-title label'>$label<div class='bt-field-help'>?</div></div>";
		$form .= "<div class='lws-$context-opt-input value'>";
		$form .= \LWS\Adminpanel\Pages\Field\Duration::compose($prefix.'early', array('value'=>$value));
		$form .= "</div>";

		$label = _x("Birthday meta key", "Ask for user meta key", 'woorewards-pro');
		$tooltip = __("In case you use a third party registration form, you can set the database user meta key used for the birthday date.", 'woorewards-pro');
		$value = $this->getBirthdayMetaKey();
		$value = \esc_attr(is_array($value) ? implode(',', $value) : $value);
		$placeholder = $this->getDefaultBirthdayMetaKey();
		$form .= "<div class='field-help'>$tooltip</div>";
		$form .= "<div class='lws-$context-opt-title label'>$label<div class='bt-field-help'>?</div></div>";
		$form .= "<div class='lws-$context-opt-input value'><input type='text' id='{$prefix}umkey' name='{$prefix}umkey' value='$value' placeholder='{$placeholder}' /></div>";

		// informative/debug display
		$form .= "<span data-name='{$prefix}last_cron' style='display:contents !important;'></span>";
		$form .= "<span data-name='{$prefix}last_check' style='display:contents !important;'></span>";


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
				$prefix.'early' => '/(p?\d+[DYM])?/i',
				$prefix.'umkey' => 'T'
			),
			'defaults' => array(
				$prefix.'early' => '',
			),
			'labels'   => array(
				$prefix.'early' => __("Early trigger", 'woorewards-pro'),
				$prefix.'umkey' => __("Birthday meta key", 'woorewards-pro')
			)
		));
		if( !(isset($values['valid']) && $values['valid']) )
			return isset($values['error']) ? $values['error'] : false;

		$valid = parent::submit($form, $source);
		if( $valid === true )
		{
			$this->setBirthdayMetaKey($values['values'][$prefix.'umkey']);
			$this->setEarlyTrigger   ($values['values'][$prefix.'early']);
		}
		return $valid;
	}

	/** Inhereted Event already instanciated from WP_Post, $this->id is availble. It is up to you to load any extra configuration. */
	protected function _fromPost(\WP_Post $post)
	{
		$this->setBirthdayMetaKey(\get_post_meta($post->ID, 'wre_event_product_umkey', true));
		$this->setEarlyTrigger(\LWS\Adminpanel\Duration::postMeta($post->ID, 'wre_unlockable_early_trigger'));
		return $this;
	}

	/** Event already saved as WP_Post, $this->id is availble. It is up to you to save any extra configuration. */
	protected function _save($id)
	{
		\update_post_meta($id, 'wre_event_product_umkey', $this->getBirthdayMetaKey());
		$this->getEarlyTrigger()->updatePostMeta($id, 'wre_unlockable_early_trigger');
		return $this;
	}

	/** @return string a human readable type for UI */
	public function getDisplayType()
	{
		return _x("Birthday", "getDisplayType", 'woorewards-pro');
	}

	function getDescription($context='backend')
	{
		$early = $this->getEarlyTrigger();
		if( $early->isNull() )
			return __("At birthday", 'woorewards-pro');
		else
			return sprintf(__('%1$d %2$s before birthday', 'woorewards-pro'), $early->getCount(), $early->getPeriodText());
	}

	/** Add hook to grab events and add points. */
	protected function _install()
	{
		if( !empty($this->getBirthdayMetaKey()) )
			\add_action('lws_woorewards_daily_event', array($this, 'trigger'));
	}

	/** return a Duration instance */
	public function getEarlyTrigger()
	{
		if (!$this->earlyTrigger)
			$this->earlyTrigger = \LWS\Adminpanel\Duration::void();
		return $this->earlyTrigger;
	}

	/** @param $days (false|int|\LWS\Adminpanel\Duration) */
	public function setEarlyTrigger($days=false)
	{
		if( empty($days) )
			$this->earlyTrigger = \LWS\Adminpanel\Duration::void();
		else if( is_a($days, '\LWS\Adminpanel\Duration') )
			$this->earlyTrigger = $days;
		else
			$this->earlyTrigger = \LWS\Adminpanel\Duration::fromString($days);
		return $this;
	}

	/** @return string a usermeta.meta_key to store thi last rewarded birthday */
	protected function getMetaKey()
	{
		if (!$this->mkey)
			$this->mkey = $this->getType() .'-'. $this->getId();
		return $this->mkey;
	}

	public function setBirthdayMetaKey($key)
	{
		if( is_array($key) )
			$this->birthdayMetaKey = array_filter(array_map('trim', $key));
		else
		{
			$keys = explode(',', $key);
			if( count($keys) > 1 )
				$this->birthdayMetaKey = array_filter(array_map('trim', $keys));
			else
				$this->birthdayMetaKey = trim($key);
		}
	}

	protected function getDefaultBirthdayMetaKey()
	{
		return 'billing_birth_date';
	}

	/** @return (array|string) a list of usermeta.meta_key where a birthday date could be found. */
	protected function getBirthdayMetaKey()
	{
		return (false !== $this->birthdayMetaKey) ? $this->birthdayMetaKey : $this->getDefaultBirthdayMetaKey();
	}

	/** Look for all users once a day */
	function trigger()
	{
		global $wpdb;
		// for debug purpose
		\update_post_meta($this->getId(), 'lws_woorewards_birthday_cron_last_call', \date('Y-m-d H:i:s'));

		// get meta_key where last trigger was done
		$mkey = $this->getMetaKey();
		// get meta_keys where user birth date is saved
		$mbirthday = $this->getBirthdayMetaKey();
		$mbirthday = implode("','", array_map('esc_sql', is_array($mbirthday) ? $mbirthday : array($mbirthday)));
		// we can be interested by few days before the date
		$birth = "DATE(birth.meta_value)";
		$early = $this->getEarlyTrigger();
		if (!$early->isNull()) {
			$interval = $early->getSqlInterval();
			$birth = "DATE_SUB({$birth}, {$interval})";
		}

		// read never checked users, last check was at least one year before
		// and birth date (-early_days) is at least one year before (for new born cases...)
		$sql = <<<EOT
SELECT birth.user_id, MAX(DATE(birth.meta_value)) as ref
, MAX(DATE(anni.meta_value)) as saved, MAX(DATE(u.user_registered)) as registered
FROM {$wpdb->usermeta} as birth
LEFT JOIN {$wpdb->usermeta} as anni ON anni.user_id=birth.user_id AND anni.meta_key='{$mkey}'
INNER JOIN {$wpdb->users} as u ON u.ID=birth.user_id
WHERE birth.meta_key IN ('{$mbirthday}') AND birth.meta_value <> ''
AND {$birth} <= DATE_SUB(CURDATE(), INTERVAL 1 YEAR)
AND (anni.meta_value IS NULL OR DATE(anni.meta_value) <= DATE_SUB(CURDATE(), INTERVAL 1 YEAR))
GROUP BY birth.user_id
EOT;
		$users = $wpdb->get_results($sql);
		if( !is_array($users) )
			return;

		if ($users) {
			$count = 0;
			foreach( $users as $user )
			{
				$count += $this->maybeAddPointsForTheYear(
					$user->user_id,
					$user->ref, // user birth date
					$user->saved, // last time this Event gave points
					$user->registered, // user registration date
					$early->isNull() ? false : $early->toInterval()
				);
			}

			\update_post_meta($this->getId(), 'lws_woorewards_birthday_cron_last_users_counts', sprintf('%d/%d/%s', $count, count($users), \date('Y-m-d')));
		}
	}

	/** @param $userId (int) the user ID
	 *	@param $reference false|string the original date (user birth date).
	 *	@param $last false|string if set, replace the original date (last relevant date this Event gave points for).
	 *	@param $min false|string if set, do not give points before that date (usually, the user registration date).
	 *	@param $interval false|\DateInterval the points have to be triggered before the birthday. */
	protected function maybeAddPointsForTheYear($user_id, $reference, $last=false, $min=false, $interval=false)
	{
		$done = 0;
		// init vars
		$today = \date_create_immutable();
		if ($reference && \is_string($reference)) {
			$reference = \date_create_immutable($reference);
		}
		$min = $this->getUserMinDate($reference, $min);

		// re-compute the trigger date for this year from scratch and save it
		$triggerDate = $this->getTriggerDate($reference, $interval);

		// do not trust database, check last year is far enough
		if ($triggerDate < $min) {
			// save the re-computed value, be sure no shift occured and fix any wrong data
			\update_user_meta($user_id, $this->getMetaKey(), $triggerDate->format('Y-m-d'));
		} elseif ($triggerDate > $today) {
			// wait for it, but fix the value in database
			\update_user_meta($user_id, $this->getMetaKey(), $triggerDate->sub(new \DateInterval('P1Y'))->format('Y-m-d'));
		} else {
			$done = $this->addPointsForTheYear($user_id, $triggerDate, $reference->format(\get_option('date_format', 'Y-m-d')));
			// save the re-computed value, be sure no shift occured and fix any wrong data
			\update_user_meta($user_id, $this->getMetaKey(), $triggerDate->format('Y-m-d'));
		}

		return $done;
	}

	/** @param $birth_date string|\DateTimeInterface */
	private function addPointsForTheYear(int $user_id, \DateTimeInterface $date, $birth_date)
	{
		if ($date && ($points = \apply_filters('trigger_' . $this->getType(), 1, $this, $user_id, $date))) {
			$reason = \LWS\WOOREWARDS\Core\Trace::byReason(array(
				"Birthday %s",
				\is_string($birth_date) ? $birth_date : $birth_date->format(\get_option('date_format', 'Y-m-d')),
			), 'woorewards-pro');
			$this->addPoint($user_id, $reason, $points);
			return 1;
		} else {
			return 0;
		}
	}

	public function getTriggerDate($birthdate, $interval=false)
	{
		$today = \date_create_immutable();
		$triggerDate = \date_create_immutable();
		$triggerDate = $triggerDate->setDate((int)$today->format('Y'), (int)$birthdate->format('n'), (int)$birthdate->format('j'));
		$triggerDate = $triggerDate->setTime(0, 0);
		if ($interval) {
			$triggerDate = $triggerDate->sub($interval);
		}
		// in case user changed his birth date, or just get access to the pool (role restriction)
		// we only make up for a certain period of time (in case of cron issues)
		// or we skip it until next year
		$catchUp = \apply_filters('lws_woorewards_event_birthday_catch_up_period', 'P1M', $this, $birthdate);
		if ($catchUp && $triggerDate <= $today->sub(new \DateInterval($catchUp))) {
			$triggerDate = $triggerDate->add(new \DateInterval('P1Y'));
		}
		return $triggerDate;
	}

	/** never gives points before that date */
	public function getUserMinDate($birthdate, $registration)
	{
		if ($registration && \is_string($registration)) {
			$registration = \date_create_immutable($registration);
		}
		$min = $registration ? $registration->setTime(0, 0) : false;

		if (isset($this->eventCreationDate) && $this->eventCreationDate) {
			if (!$min || $this->eventCreationDate > $min) {
				if (\is_a($this->eventCreationDate, '\DateTime')) {
					$min = \DateTimeImmutable::createFromMutable($this->eventCreationDate)->setTime(0, 0);;
				} else {
					$min = $this->eventCreationDate->setTime(0, 0);;
				}
			}
		}

		if (!$min) {
			if ($birthdate && \is_string($birthdate)) {
				$birthdate = \date_create_immutable($birthdate);
			}
			if ($birthdate) {
				$min = $birthdate->sub(new \DateInterval('P1Y'))->setTime(0, 0);
			}
		}

		return $min ? $min : \date_create_immutable()->setTime(0, 0);
	}

	/** @return false|object:
	 * * next (\DateTimeImmutable) next trigger date (estimates).
	 * * min (\DateTimeImmutable) the date trigger cannot occur before (user registration, birthday or event creation).
	 * * last (false|\DateTimeImmutable) previous earning event if any (doesn't care if user can / points really given).
	 * * event (fals|int) this current id */
	function getDatesForUser(\WP_User $user)
	{
		$birthday = $this->getUserBirthDate($user->ID);
		if (!$birthday) {
			return false;
		}

		$last = \get_user_meta($user->ID, $this->getMetaKey(), true);
		if ($last) {
			$last = \date_create_immutable($last);
		}
		$registration = $user->registered ? \date_create_immutable($user->registered) : false;
		$min = $this->getUserMinDate($birthday, $registration);
		
		$early = $this->getEarlyTrigger();
		$triggerDate = $this->getTriggerDate($birthday, $early->isNull() ? false : $early->toInterval());
		$year = new \DateInterval('P1Y');

		if ($last) {
			$next = $last->setTime(0, 0)->add($year);
			if ($next < $triggerDate) $next = $triggerDate;
		} else {
			$next = $triggerDate;
		}

		while ($next < $min) {
			$next = $next->add($year);
		}

		return (object)array(
			'next'  => $next,
			'last'  => $last,
			'min'   => $min,
			'event' => $this->getId(),
			'early' => $early,
		);
	}

	public function getUserBirthDate($userId)
	{
		global $wpdb;
		$birthkey  = $this->getBirthdayMetaKey();
		$birthdate = $wpdb->get_var(sprintf(
			"SELECT MAX(meta_value) FROM {$wpdb->usermeta} WHERE user_id = %d AND meta_key IN ('%s') AND meta_value IS NOT NULL AND meta_value != ''",
			(int)$userId,
			\implode("', '", \array_map('\esc_sql', \is_array($birthkey) ? $birthkey : array($birthkey)))
		));
		if (!$birthdate) return false;
		$birthdate = \date_create_immutable($birthdate);
		if (!$birthdate) return false;
		else return $birthdate->setTime(0, 0);
	}

	/** @param $userId int
	 *	@param $birthdate string|\DateTimeImmutable the user birthday date if already read, or null.
	 *	Can be today.
	 *	@return false|\DateTimeImmutable the last theoretical trigger date */
	public function getComputedLastTriggerDate($userId, $birthdate=null)
	{
		if ($birthdate && \is_string($birthdate)) {
			$birthdate = \date_create_immutable($birthdate);
		}
		if (!$birthdate) {
			$birthdate = $this->getUserBirthDate($userId);
		}
		if (!$birthdate) {
			return false;
		}
		$birthdate = $birthdate->setTime(0, 0);
		$early = $this->getEarlyTrigger();

		return $this->getTriggerDate($birthdate, $early->isNull() ? false : $early->toInterval());
	}

	/** Update the usermeta with the last relevant trigger date.
	 *	Based on user birthday date. Does not take care of existent
	 *	except to do not give the point several times the same year.
	 *	@return false|\DateTimeImmutable the last theoretical trigger date */
	public function resetDatesForUser($userId, $update=true)
	{
		$triggerDate = $this->getComputedLastTriggerDate($userId);
		if ($triggerDate && $update) {
			// check in points logs if last earning is far enough
			if ($triggerDate > \date_create_immutable()) {
				// wait for it, but fix the value in database
				\update_user_meta($userId, $this->getMetaKey(), $triggerDate->sub(new \DateInterval('P1Y'))->format('Y-m-d'));
			} else {
				// save the re-computed value, be sure no shift occured and fix any wrong data
				\update_user_meta($userId, $this->getMetaKey(), $triggerDate->format('Y-m-d'));
			}
		}
		return $triggerDate;
	}

	/** Never call, only to have poedit/wpml able to extract the sentance. */
	private function poeditDeclare()
	{
		__("Birthday %s", 'woorewards-pro');
	}

	public function getCategories()
	{
		return array_merge(parent::getCategories(), array(
			'miscellaneous' => __("Miscellaneous", 'woorewards-pro')
		));
	}
}