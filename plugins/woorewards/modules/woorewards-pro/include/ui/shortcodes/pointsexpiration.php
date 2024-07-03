<?php

namespace LWS\WOOREWARDS\PRO\Ui\Shortcodes;

// don't call the file directly
if (!defined('ABSPATH')) exit();

/** Shortcode to show when points expire for a user on a specified points and rewards system */
class PointsExpiration
{
	public static function install()
	{
		$me = new self();
		\add_shortcode('wr_points_expiration', array($me, 'shortcode'));

		/** Admin */
		\add_filter('lws_woorewards_points_shortcodes', array($me, 'admin'));
	}

	public function admin($fields)
	{
		$fields['pointsexpiration'] = array(
			'id' => 'lws_woorewards_sc_pointsexpiration',
			'title' => __("Points Expiration", 'woorewards-pro'),
			'type' => 'shortcode',
			'extra' => array(
				'shortcode' => '[wr_points_expiration]',
				'description' =>  __("This shortcode displays the date where user points will expire. It only works for inactivity points expiration.", 'woorewards-pro'),
				'options' => array(
					array(
						'option' => 'system',
						'desc' => __("The points and rewards system you want to display. You can find this value in <strong>MyRewards → Points and Rewards</strong>, in the <b>Shortcode Attribute</b> column. If you don’t set this value, <b>all</b> systems will be displayed.", 'woorewards-pro'),
					),
					array(
						'option' => 'force',
						'desc' => __("(Optional) If set, the points will be shown even if the user currently doesn’t have access to the points and rewards system.", 'woorewards-pro'),
					),
					array(
						'option' => 'format',
						'desc' => __("Set the date format to display the date. Set this to 'days' to display a number of days instead of a date", 'woorewards-pro'),
					),
					array(
						'option' => 'raw',
						'desc' => __("Defines if the date is put in stylable elements or not.", 'woorewards-pro'),
					),
				),
				'flags' => array('current_user_id'),
			)
		);
		return $fields;
	}

	public function shortcode($atts = array(), $content = '')
	{
		$atts = \LWS\Adminpanel\Tools\Conveniences::sanitizeAttr(\wp_parse_args($atts, array(
			'system' => '', 'format' => \get_option('date_format'), 'raw' => 'no'
		)));
		$userId = \apply_filters('lws_woorewards_shortcode_current_user_id', \get_current_user_id(), $atts, 'wr_points_expiration');
		if (!$atts['system'])
			return '';
		if (!$userId)
			return '';

		$pools = \apply_filters('lws_woorewards_get_pools_by_args', false, $atts);
		if ($pools && $pools->count()) {
			$pool = $pools->first();
			$delay = $pool->getOption('point_timeout');

			if ($delay->isNull()) {
				return '';
			} else {
				$lastDate = $this->getLastMovement($pool->getStackId(), $userId);
				if ($lastDate) {
					$expirationDate = $delay->getEndingDate($lastDate);

					if ($atts['format'] == 'days') {
						$content = $expirationDate->diff(\date_create('now', \wp_timezone()), true)->format('%a');
					} else {
						$content = \date_i18n($atts['format'], $expirationDate->getTimestamp());
					}
					if (!\LWS\Adminpanel\Tools\Conveniences::argIsTrue($atts['raw'])) {
						$content = "<span class='lws-points-expiration'>" . $content . "</span>";
					}
				}
			}
		}
		return $content;
	}

	/** @return \DateTime or false */
	private function getLastMovement($stackId, $userId)
	{
		global $wpdb;
		$request = \LWS\Adminpanel\Tools\Request::from($wpdb->lwsWooRewardsHistoric);
		$request->select('MAX(mvt_date)');
		$request->where(array(
			"user_id = %d",
			"stack = %s",
		))->arg(array(
			\intval($userId),
			$stackId,
		));
		$date = $request->getVar();
		return $date ? \date_create($date, \wp_timezone()) : false;
	}
}
