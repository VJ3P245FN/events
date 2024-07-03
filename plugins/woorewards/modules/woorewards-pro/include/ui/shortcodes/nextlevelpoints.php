<?php

namespace LWS\WOOREWARDS\PRO\Ui\Shortcodes;

// don't call the file directly
if (!defined('ABSPATH')) exit();

class NextLevelPoints
{
	public static function install()
	{
		$me = new self();
		\add_shortcode('wr_next_level_points', array($me, 'shortcode'));

		/** Admin */
		\add_filter('lws_woorewards_points_shortcodes', array($me, 'admin'), 20);
	}

	public function admin($fields)
	{
		$fields['nextlevel'] = array(
			'id' => 'lws_woorewards_sc_next_level',
			'title' => __("Points to next level", 'woorewards-pro'),
			'type' => 'shortcode',
			'extra' => array(
				'shortcode' => '[wr_next_level_points system="set the name of your system here"]',
				'description' =>  __("This shortcode displays the points needed to reach the next level/reward of a points and rewards system.", 'woorewards-pro') . "<br/>" .
				__("Use it to motivate customers to reach a higher level.", 'woorewards-pro'),
				'options' => array(
					array(
						'option' => 'system',
						'desc' => __("The points and rewards system you want to display. You can find this value in <strong>MyRewards → Points and Rewards</strong>, in the <b>Shortcode Attribute</b> column. If you don’t set this value, nothing will be displayed.", 'woorewards-pro'),
					),
					array(
						'option' => 'prefix',
						'desc' => __("(Optional) The text displayed before the points needed.", 'woorewards-pro'),
					),
					array(
						'option' => 'suffix',
						'desc' => __("(Optional) The text displayed after the points needed.", 'woorewards-pro'),
					),
					array(
						'option' => 'currency',
						'desc' => __("(Optional)  If set, the points will be displayed with the points and rewards system's currency.", 'woorewards-pro'),
					),
				),
				'flags' => array('current_user_id'),
			)
		);
		return $fields;
	}

	/** Display points needed to reach the next level of a loyalty system
	 * [wr_show_history system='poolname1,poolname2' prefix='You need' suffix='to reach the next level' currency='true']
	 * @param $atts array :
	 * * 'system' the loyalty systems for which to show the history
	 * * 'prefix' the text displayed before the points needed
	 * * 'suffix' the text displayed after the points needed
	 * * 'currency' show points with the pool currency
	 */

	public function shortcode($atts = array(), $content = '')
	{
		$userId = \apply_filters('lws_woorewards_shortcode_current_user_id', \get_current_user_id(), $atts, 'wr_next_level_points');
		if ($userId) {
			$atts = \LWS\Adminpanel\Tools\Conveniences::sanitizeAttr(\wp_parse_args($atts, array(
				'pool' => false,
				'prefix' => '',
				'suffix' => '',
				'currency' => true,
			)));
			if (!isset($atts['system']))
				$atts['system'] = $atts['pool'];

			$pools = \apply_filters('lws_woorewards_get_pools_by_args', false, $atts);
			if ($pools && $pools->count()) {
				$pool = $pools->first();
				$points = $pool->getPoints($userId);
				foreach ($pool->getUnlockables()->sort()->asArray() as $u) {
					$rest = $u->getCost() - $points;
					if ($rest > 0) {
						if (\LWS\Adminpanel\Tools\Conveniences::argIsTrue($atts['currency'])) {
							$rest = $pool->formatPoints($rest);
						}
						return ($atts['prefix'] . ' ' . $rest . ' ' . $atts['suffix']);
					}
				}
			}
		}
		return $content;
	}
}
