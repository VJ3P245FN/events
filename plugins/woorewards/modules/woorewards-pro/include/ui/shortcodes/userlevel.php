<?php
namespace LWS\WOOREWARDS\PRO\Ui\Shortcodes;

// don't call the file directly
if( !defined( 'ABSPATH' ) ) exit();

class UserLevel
{
	public static function install()
	{
		$me = new self();
		\add_shortcode('wr_user_level', array($me, 'shortcode'));

		/** Admin */
		\add_filter('lws_woorewards_rewards_shortcodes', array($me, 'admin'), 20);
	}

	public function admin($fields)
	{
		$fields['nextlevel'] = array(
			'id' => 'lws_woorewards_sc_userlevel',
			'title' => __("User Level", 'woorewards-pro'),
			'type' => 'shortcode',
			'extra' => array(
				'shortcode' => '[wr_user_level system="set the name of your system here" nolevel="No level message"]',
				'description' =>  __("This shortcode displays the current user level in a leveling points and rewards system.", 'woorewards-pro'),
				'options' => array(
					array(
						'option' => 'system',
						'desc' => __("The points and rewards system you want to display. You can find this value in <strong>MyRewards → Points and Rewards</strong>, in the <b>Shortcode Attribute</b> column. If you don’t set this value, nothing will be displayed.", 'woorewards-pro'),
					),
					array(
						'option' => 'nolevel',
						'desc' => __("The text displayed if the user didn't reach the first level", 'woorewards-pro'),
					),
				),
				'flags' => array('current_user_id'),
			)
		);
		return $fields;
	}

	/** Displays the current user level in a leveling loyalty system
	 * [wr_user_level system='poolname' nolevel='No level message']
	 * * system the loyalty system for which to show the progress bar
	 */
	public function shortcode($atts=array(), $content='')
	{
		$atts = \wp_parse_args($atts, array('system' => '', 'nolevel' => __("No level yet", 'woorewards-pro')));
		$userId = \apply_filters('lws_woorewards_shortcode_current_user_id', \get_current_user_id(), $atts, 'wr_user_level');
		if (!$atts['system'])
			return '';
		if (!$userId)
			return '';
		$content = '';
		$title = $atts['nolevel'];
		/* */
		$pools = \apply_filters('lws_woorewards_get_pools_by_args', false, $atts);
		if ($pools && $pools->count()) {
			$pool = $pools->first();
			if($pool->getOption('type') == \LWS\WOOREWARDS\Core\Pool::T_LEVELLING)
			{
				$userPoints = $pool->getPoints($userId);
				foreach ($pool->getUnlockables()->sort()->asArray() as $item)
				{
					if ($item->getCost() <= $userPoints)
					{
						$title = $item->getGroupedTitle('view');
					}
				}
				$content = "<span class='wr-user-level-span'>". $title . "</span>";
			}
		}
		return $content;
	}

}