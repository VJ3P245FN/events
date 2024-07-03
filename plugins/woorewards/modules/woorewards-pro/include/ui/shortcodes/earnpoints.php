<?php

namespace LWS\WOOREWARDS\PRO\Ui\Shortcodes;

// don't call the file directly
if (!defined('ABSPATH')) exit();

class EarnPoints
{
	public static function install()
	{
		$me = new self();
		// Shortcode
		\add_shortcode('wr_earn_points', array($me, 'shortcode'));
		// Admin
		\add_filter('lws_woorewards_points_shortcodes', array($me, 'admin'), 5);
		// Scripts
		\add_action('wp_enqueue_scripts', array($me, 'registerScripts'));
	}

	function registerScripts()
	{
		\wp_register_style('wr-earn-points', LWS_WOOREWARDS_PRO_CSS . '/shortcodes/earn-points.min.css', array(), LWS_WOOREWARDS_PRO_VERSION);
	}

	protected function enqueueScripts()
	{
		\wp_enqueue_style('wr-earn-points');
	}

	/** Get the shortcode admin */
	public function admin($fields)
	{
		$fields['earnpoints'] = array(
			'id' => 'lws_woorewards_earn_points',
			'title' => __("Earn Points", 'woorewards-pro'),
			'type' => 'shortcode',
			'extra' => array(
				'shortcode' => '[wr_earn_points]',
				'description' =>  __("Use this shortcode to show to your customers how they can earn loyalty points.", 'woorewards-pro') . "<br/>" .
					__("Use the following options to change how the balance is displayed.", 'woorewards-pro'),
				'options' => array(
					'system' => array(
						'option' => 'system',
						'desc'   => __("(Optional, comma separated) Select the points and rewards systems you want to show. If left empty, all active systems are displayed", 'woorewards-pro') .
							"<br/>" . __("You can find the points and rewards systems names in WooRewards → Points and Rewards", 'woorewards-pro'),
					),
					'layout' => array(
						'option' => 'layout',
						'desc' => __("(Optional) Select how methods to earn points elements are organized . 4 possible values :", 'woorewards-pro'),
						'options' => array(
							array(
								'option' => 'none',
								'desc'   => __("Default value. Simple text without stylable elements", 'woorewards-pro'),
							),
							array(
								'option' => 'grid',
								'desc'   => __("Elements are displayed in a responsive grid", 'woorewards-pro'),
							),
							array(
								'option' => 'horizontal',
								'desc'   => __("Elements are displayed in row", 'woorewards-pro'),
							),
							array(
								'option' => 'vertical',
								'desc'   => __("Elements are displayed on top of another", 'woorewards-pro'),
							),
						),
						'example' => '[wr_earn_points layout="grid"]'
					),
					'element' => array(
						'option' => 'element',
						'desc' => __("(Optional) Select how a method to earn points element is displayed. 3 possible values :", 'woorewards-pro'),
						'options' => array(
							array(
								'option' => 'none',
								'desc'   => __("Default value. Simple text without stylable elements", 'woorewards-pro'),
							),
							array(
								'option' => 'tile',
								'desc'   => __("Stylable tile with a background color", 'woorewards-pro'),
							),
							array(
								'option' => 'line',
								'desc'   => __("Horizontal display in stylable elements", 'woorewards-pro'),
							),
						),
						'example' => '[wr_earn_points element="tile"]'
					),
					'display' => array(
						'option' => 'display',
						'desc' => __("(Optional) Select how the points are displayed. 2 possible values :", 'woorewards-pro'),
						'options' => array(
							array(
								'option' => 'formatted',
								'desc'   => __("Default. Points are formatted with the points currency/name", 'woorewards-pro'),
							),
							array(
								'option' => 'simple',
								'desc'   => __("Only the points balance numeric value is displayed", 'woorewards-pro'),
							),
						),
						'example' => '[wr_earn_points display="simple"]'
					),
					'showname' => array(
						'option' => 'showname',
						'desc' => __("(Optional) If set, will display the points and rewards system name for each method to ear points", 'woorewards-pro'),
						'example' => '[wr_earn_points showname="yes"]',
					),
					'showunlogged' => array(
						'option' => 'showunlogged',
						'desc' => __("(Optional) If set, methods to earn points will be visible to unlogged customers", 'woorewards-pro'),
						'example' => '[wr_earn_points showunlogged="yes"]',
					),
				),
				'flags' => array('current_user_id'),
			)
		);
		return $fields;
	}

	/** Shows methods to earn points
	 * [wr_earn_points systems='poolname1, poolname2']
	 * @param $atts array :
	 * * 'system' 	→ Default: ''
	 * 					  The points and rewards systems for which the methods to earn points are displayed. If empty, show all active systems
	 * 					  One value or several ones, comma separated
	 * * 'layout' 	→ Default: 'none'
	 * 					  Defines the presentation of the wrapper.
	 * 					  4 possible values : grid, horizontal, vertical, none.
	 * * 'element' 	→ Default: 'none'
	 * 					  Defines the presentation of the elements.
	 * 					  3 possible values : tile, line, none.
	 * * 'display'	→ Default: 'formatted'
	 * 					  'simple'    → only the points numeric value is displayed.
	 * 					  'formatted' → points are formatted with the points currency/name.
	 * * 'showname'	→ Default: false
	 * 					  Shows the name of the points and rewards system if set
	 * * 'showunlogged'	→ Default: false
	 * 					  Shows for unlogged users
	 */
	public function shortcode($atts = array(), $content = '')
	{
		$atts = \LWS\Adminpanel\Tools\Conveniences::sanitizeAttr(\wp_parse_args($atts, array(
			'system' => '', 'layout' => 'none', 'element' => 'none', 'display' => 'formatted', 'showname' => '', 'showunlogged' => ''
		)));
		$userId = \apply_filters('lws_woorewards_shortcode_current_user_id', \get_current_user_id(), $atts, 'wr_points_balance');
		if (!$userId && !$atts['showunlogged']) {
			return \do_shortcode($content);
		}
		// Basic verifications
		if (!$atts['system']) {
			$atts['showall'] = true;
		}

		// Get the data
		$pools = \apply_filters('lws_woorewards_get_pools_by_args', false, $atts);

		if ($pools && $pools->count()) {
			$data = array();
			foreach ($pools->asArray() as $pool) {
				foreach ($pool->getEvents()->asArray() as $item) {
					if (!\method_exists($item, 'isValidCurrency') || $item->isValidCurrency()) {
						$info = array(
							'title'  => $pool->getOption('display_title'),
							'desc'   => $item->getTitle(),
							'points' => $item->getGainForDisplay(),
							'name'   => $item->getPoolName(),
						);
						if (!$info['desc']) {
							$info['desc'] = $item->getDescription('frontend');
						}
						$data[] = $info;
					}
				}
			}
			if ($data) {
				if (!\strlen("{$atts['showname']}"))
					$atts['showname'] = (count($data) != 1);
				else
					$atts['showname'] = \LWS\Adminpanel\Tools\Conveniences::argIsTrue($atts['showname']);
				return $this->getContent($atts, $data);
			}
		}
		return \do_shortcode($content);
	}

	protected function getContent($atts, $data)
	{
		$elements = '';
		foreach ($data as $item) {
			if ($atts['display'] == 'formatted' && \is_numeric($item['points'])) {
				$item['points'] = \LWS_WooRewards::formatPointsWithSymbol($item['points'], $item['name']);
			}

			if ($atts['element'] == 'tile' || $atts['element'] == 'line') {
				$title = ($atts['showname'] ? "<div class='system-name'>{$item['title']}</div>" : '');
				$elements .= <<<EOT
	<div class='item {$atts['element']} {$item['name']}'>
		<div class='method-name'>{$item['desc']}</div>
		<div class='points-earned'>{$item['points']}</div>{$title}
	</div>
EOT;
			} else {
				// raw
				if ($atts['showname']) {
					$elements .= sprintf(
						_x("%s : %s (%s)", '[wr_earn_points] raw display: desc, points, pool', 'woorewards-pro'),
						$item['desc'], $item['points'], $item['title']
					);
				} else {
					$elements .= sprintf(
						_x("%s : %s", '[wr_earn_points] raw display: desc, points', 'woorewards-pro'),
						$item['desc'], $item['points']
					);
				}
			}
		}

		$this->enqueueScripts();
		switch (\strtolower(\substr($atts['layout'], 0, 3))) {
			case 'gri':
				return "<div class='wr-earn-points wr-shortcode-grid'>{$elements}</div>";
			case 'hor':
				return "<div class='wr-earn-points wr-shortcode-hflex'>{$elements}</div>";
			case 'ver':
				return "<div class='wr-earn-points wr-shortcode-vflex'>{$elements}</div>";
			default:
				return $elements;
		}
	}
}
