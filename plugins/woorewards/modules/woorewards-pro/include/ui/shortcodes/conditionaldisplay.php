<?php

namespace LWS\WOOREWARDS\PRO\Ui\Shortcodes;

// don't call the file directly
if (!defined('ABSPATH')) exit();

class ConditionalDisplay
{
	public static function install()
	{
		$me = new self();
		\add_shortcode('wr_conditional_display', array($me, 'shortcode'));
		\add_filter('lws_woorewards_advanced_shortcodes', array($me, 'admin'));
	}

	/** Get the shortcode admin */
	public function admin($fields)
	{
		$fields['conditional'] = array(
			'id' => 'lws_woorewards_conditional_display',
			'title' => __("Conditional Display", 'woorewards-pro'),
			'type' => 'shortcode',
			'extra' => array(
				'shortcode' => '[wr_conditional_display] The content to show [/wr_conditional_display]',
				'description' =>  __("Use this shortcode to show content to your customers only if they meet the required condition(s).", 'woorewards-pro'),
				'options' => array(
					'system' => array(
						'option' => 'system',
						'desc'   => __("The points and rewards systems for which to test the condition", 'woorewards-pro'),
					),
					'authorized' => array(
						'option' => 'authorized',
						'desc' => __("Only customers who have or don't have access to the system will see the content", 'woorewards-pro'),
						'options' => array(
							array(
								'option' => 'yes',
								'desc'   => __("Default value. Only customers who have access to the system will see the content", 'woorewards-pro'),
							),
							array(
								'option' => 'no',
								'desc'   => __("Only customers who don't have access to the system will see the content", 'woorewards-pro'),
							),
						),
						'example' => '[wr_conditional_display system="default" authorized="yes"]'
					),
					'minpoints' => array(
						'option' => 'minpoints',
						'desc' => __("(Optional) The customer must have at least this amount of points to see the content", 'woorewards-pro'),
						'example' => '[wr_conditional_display system="default" minpoints="100"]'
					),
					'maxpoints' => array(
						'option' => 'maxpoints',
						'desc' => __("(Optional) The customer must have at most this amount of points to see the content", 'woorewards-pro'),
						'example' => '[wr_conditional_display system="default" maxpoints="800"]'
					),
				),
				'flags' => array('current_user_id'),
			)
		);
		return $fields;
	}

	/** Display content only if a special requirement is met
	 * [wr_conditional_display system=""]The content to display, shortcodes allowed[/wr_conditional_display]
	 * * system 	  →	Default: ''
	 * * * The points and rewards systems for which the requirement is tested
	 * * * One value or several ones, comma separated
	 * * authorized  →	Default: 'yes'. If set to 'no', only users who don't have acces to the system will see the content
	 * * minpoints   → Default: ''. The minimum amount of points the use has to have in the system
	 * * maxpoints   → Default: ''. The maximum amount of points the use has to have in the system
	 */
	public function shortcode($atts = array(), $content = '')
	{
		$atts = \LWS\Adminpanel\Tools\Conveniences::sanitizeAttr(\wp_parse_args($atts, array(
			'minpoints'  => '',
			'maxpoints'  => '',
			'alt'        => '',
		)));
		$atts['force'] = true;
		$pools = \apply_filters('lws_woorewards_get_pools_by_args', false, $atts);
		if (!($pools && $pools->count()))
			return '';
		$pools = $pools->asArray();
		$userId = \apply_filters('lws_woorewards_shortcode_current_user_id', \get_current_user_id(), $atts, 'wr_conditional_display');

		if (isset($atts['authorized'])) {
			if (\LWS\Adminpanel\Tools\Conveniences::argIsTrue($atts['authorized'])) {
				if (!$userId)
					return $atts['alt'];
				foreach ($pools as $pool) {
					if (!$pool->userCan($userId))
						return $atts['alt'];
				}
			} elseif ($userId) {
				foreach ($pools as $pool) {
					if ($pool->userCan($userId))
						return $atts['alt'];
				}
			}
		}

		if (\strlen($atts['minpoints']) || \strlen($atts['maxpoints'])) {
			foreach ($pools as $pool) {
				$points = $pool->getPoints($userId);

				if (\strlen($atts['minpoints']) && $points < \intval($atts['minpoints']))
					return $atts['alt'];
				if (\strlen($atts['maxpoints']) && $points > \intval($atts['maxpoints']))
					return $atts['alt'];
			}
		}

		return \do_shortcode($content);
	}
}
