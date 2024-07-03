<?php
namespace LWS\WOOREWARDS\PRO\Mails;

// don't call the file directly
if( !defined( 'ABSPATH' ) ) exit();

class CouponReminder
{
	protected $template = 'couponreminder';

	public function __construct()
	{
		\add_action('lws_woorewards_daily_event', array($this , 'remind'));

		\add_filter( 'lws_woorewards_mails', array($this, 'addTemplate'), 40 );// priority: mail order in settings
		\add_filter( 'lws_mail_settings_' . $this->template, array($this, 'settings'));
		\add_filter( 'lws_mail_body_' . $this->template, array($this, 'body'), 10, 3);
	}

	public function addTemplate($arr)
	{
		$arr[] = $this->template;
		return $arr;
	}

	public function settings( $settings )
	{
		$settings['domain']        = 'woorewards';
		$settings['settings']      = 'Rewards Expiration Reminder';
		$settings['settings_name'] = __("Rewards Expiration Reminder", 'woorewards-pro');
		$settings['about']         = __("Inform a user about coupon that will expire.", 'woorewards-pro');
		$settings['subject']       = __("Your coupon will soon expire", 'woorewards-pro');
		$settings['title']         = __("Your coupon will soon expire", 'woorewards-pro');
		$settings['header']        = __("Here is the list of coupon that will soon expire", 'woorewards-pro');
		$settings['footer']        = __("Powered by MyRewards", 'woorewards-pro');
		$settings['doclink']       = \LWS\WOOREWARDS\PRO\DocLinks::get('emails');
		$settings['icon']          = 'lws-icon-coupon';
		$settings['css_file_url']  = LWS_WOOREWARDS_PRO_CSS . '/mails/couponreminder.css';
		$settings['fields']['remind'] = array(
			'id' => 'lws_woorewards_reminder_days',
			'title' => __("Days before coupons expiry to send the reminder email.", 'woorewards-pro'),
			'type' => 'text',
			'extra' => array(
				'help' => __("The reminder system will send emails to your customers to warn them about the deadline of their coupons.", 'woorewards-pro')
				. '</br/>'
				. __("Set an empty or 0 value if you don't want to send reminder emails.", 'woorewards-pro')
				. '</br/>'
				. __("You can set several comma separated values to send several deadline warning mails", 'woorewards-pro'),
			)
		);
		return $settings;
	}

	public function body( $html, $data, $settings )
	{
		if( !empty($html) )
			return $html;
		if( !\LWS\Adminpanel\Tools\Conveniences::isWC() )
			return __("That feature requires WooCommerce.", 'woorewards-pro');

		if( $demo = \is_wp_error($data) )
			$data = $this->placeholders();

		$html .= "<tr><td class='lws-reminder-middle-cell'><div class='lwss_selectable lws-reminder-table' data-type='Reminder Table'>";
		$rewardcount = 0;
		foreach($data as $reward )
		{
			$li = \apply_filters('lws_woorewards_reward_reminder_custom_type_mail_content', false, $reward, $settings, $demo);
			if($rewardcount>0)
				$html .= "<div class='lwss_selectable lws-reminder-horizontal-sep' data-type='Horizontal Separator'></div>";
			$html .= !empty($li) ? $li : $this->getDefault($reward, $settings, $demo);
			$rewardcount++;
		}

		$html .= "</div>";
		return $html;
	}

	protected function getDefault($data, $settings, $demo=false)
	{
		$values = array(
			'code'   => $data['post_title'],
			'title'  => $data['post_content'],
			'detail' => $data['post_excerpt']
		);

		$expire = '';
		if( isset($data['expiry_date']) && !empty($data['expiry_date']) )
		{
			$expire = \date_i18n(\get_option('date_format'), (int)$data['expiry_date']);
			$expire = sprintf(__("Expires on %s",'woorewards-pro'), $expire);
			$expire = "<div class='lwss_selectable lws-reminder-expiry' data-type='Reward Expiration'>$expire</div>";
		}

		return <<<EOT
<div class='lwss_selectable lws-reminder-cell' data-type='Reward Cell'>
	<div class='lwss_selectable lws-reminder-code' data-type='Reward Code'>{$values['code']}</div>
	<div class='lwss_selectable lws-reminder-title' data-type='Reward Title'>{$values['title']}</div>
	<div class='lwss_selectable lws-reminder-desc' data-type='Reward Description'>{$values['detail']}</div>
	$expire
</div>
EOT;
	}

	/** @return array junk values for test purpose. */
	protected function placeholders()
	{
		$unlockables = \LWS\WOOREWARDS\Collections\Unlockables::instanciate();
		for( $i=0 ; $i<4 ; ++$i )
			$unlockables->create('lws_woorewards_unlockables_coupon');
		$user = \wp_get_current_user();

		$a = array();
		foreach( $unlockables->asArray() as $unlockable )
		{
			$unlockable->setTestValues();
			$reward = $unlockable->createReward($user, true);

			$a[] = array(
				'customer_email' => $user->user_email,
				'type' => $unlockable->getType(),
				'post_title'   => $reward ? $reward->get_code() : 'TEST_CODE',
				'post_content' => $unlockable->getTitle(),
				'post_excerpt' => $reward ? $reward->get_description() : 'lorem ipsum...',
				'expiry_date'  => ($reward && $reward->get_date_expires('edit')) ? $reward->get_date_expires('edit')->getOffsetTimestamp() : ''
			);
		}
		return $a;
	}

	// the trigger that will be launched daily
	public function remind()
	{
		@set_time_limit(0);
		$delays = array_map('\intval', explode(',', \get_option('lws_woorewards_reminder_days','')));
		$delays = array_filter($delays, function($i){return $i>0;});
		\rsort($delays, SORT_NUMERIC); // DESC
		while( !empty($delays) )
		{
			$maxDelay = array_shift($delays);
			$minDelay = empty($delays) ? 0 : reset($delays);

			$rewards = array();
			foreach( $this->getCoupons($maxDelay, $minDelay, count($delays)) as $coupon )
			{
				$coupon['customer_email'] = unserialize($coupon['customer_email']);
				if( is_array($coupon['customer_email']) && count($coupon['customer_email']) == 1 )
				{
					$coupon['customer_email'] = $coupon['customer_email'][0];

					if( !empty($rewards) && $rewards[0]['customer_email'] != $coupon['customer_email'] )
					{
						// flush
						$this->send($rewards);
						$rewards = array();
					}
					$rewards[] = $coupon;
				}
			}
			$this->send($rewards);
		}
	}

	protected function send($rewards)
	{
		if( !empty($rewards) )
		{
			$date = \date_create()->getTimestamp();
			foreach( $rewards as $reward )
				\update_post_meta($reward['ID'], 'woorewards_reminder_done', $date);
			\do_action('lws_mail_send', $rewards[0]['customer_email'], 'couponreminder', $rewards);
		}
	}

	/** get the expired coupon list order by user
	 * @param $maxDelay (int) max day count until expiration from today
	 * @param $minDelay (int) min day count until expiration from today (default is zero)
	 * @param $index (int|string) optional, append into each row as 'remind_index'.
	 * @return array a list of coupon order by customer_email */
	public function getCoupons($maxDelay, $minDelay=0, $index=0)
	{
		global $wpdb;
		$index = \esc_attr($index);
		$today = (new \DateTime())->setTime(0, 0);
		$dateMin = (new \DateTime())->setTime(0, 0)->add(new \DateInterval("P{$minDelay}D"));
		$dateMax = (new \DateTime())->setTime(0, 0)->add(new \DateInterval("P{$maxDelay}D"));
		$tsMin = $dateMin->getTimestamp();
		$tsMax = $dateMax->getTimestamp();
		$maxDelayToSec = $maxDelay * 86400; // days * {24h in seconds}

		$query = <<<EOT
SELECT p.ID as ID, post_title, post_content, post_excerpt, m.meta_value AS customer_email,
d.meta_value AS type, e.meta_value AS expiry_date, '{$index}' as remind_index
FROM {$wpdb->posts} as p
INNER JOIN {$wpdb->postmeta} as m ON p.ID = m.post_id AND m.meta_key='customer_email' AND m.meta_value <> ''
LEFT JOIN {$wpdb->postmeta} as l ON p.ID = l.post_id AND l.meta_key='usage_limit'
LEFT JOIN {$wpdb->postmeta} as u ON p.ID = u.post_id AND u.meta_key='usage_count'
LEFT JOIN {$wpdb->postmeta} as e ON p.ID = e.post_id AND e.meta_key='date_expires'
LEFT JOIN {$wpdb->postmeta} as d ON p.ID = d.post_id AND d.meta_key='reward_origin'
LEFT JOIN {$wpdb->postmeta} as done ON p.ID = done.post_id AND done.meta_key='woorewards_reminder_done'
WHERE post_type = 'shop_coupon' AND post_status = 'publish'
AND (u.meta_value < l.meta_value OR u.meta_value IS NULL OR l.meta_value IS NULL)
AND e.meta_value <> '' AND e.meta_value > '{$tsMin}' AND e.meta_value <= '{$tsMax}'
AND (done.meta_value IS NULL OR ($maxDelayToSec + done.meta_value) < e.meta_value)
ORDER BY m.meta_value
EOT;

		return $wpdb->get_results($query, ARRAY_A);
	}

}
