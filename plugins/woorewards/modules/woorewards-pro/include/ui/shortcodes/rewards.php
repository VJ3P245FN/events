<?php

namespace LWS\WOOREWARDS\PRO\Ui\Shortcodes;

// don't call the file directly
if (!defined('ABSPATH')) exit();

class Rewards
{
	public static function install()
	{
		$me = new self();
		// Shortcode
		\add_shortcode('wr_rewards', array($me, 'shortcode'));
		// Admin
		\add_filter('lws_woorewards_rewards_shortcodes', array($me, 'admin'), 5);
		// Scripts
		\add_action('wp_enqueue_scripts', array($me, 'registerScripts'));
	}

	function registerScripts()
	{
		\wp_register_style('wr-rewards', LWS_WOOREWARDS_PRO_CSS . '/shortcodes/rewards.min.css', array(), LWS_WOOREWARDS_PRO_VERSION);
	}

	protected function enqueueScripts()
	{
		\wp_enqueue_style('wr-rewards');
	}

	/** Get the shortcode admin */
	public function admin($fields)
	{
		$fields['rewards'] = array(
			'id' => 'lws_woorewards_rewards',
			'title' => __("Rewards", 'woorewards-pro'),
			'type' => 'shortcode',
			'extra' => array(
				'shortcode' => '[wr_rewards]',
				'description' =>  __("Use this shortcode to show to your customers the rewards they can earn with your loyalty program.", 'woorewards-pro') . "<br/>" .
					__("Use the following options to change how the rewards are displayed.", 'woorewards-pro'),
				'options' => array(
					'system' => array(
						'option' => 'system',
						'desc'   => __("(Optional, comma separated) Select the points and rewards systems you want to show. If left empty, all active systems are displayed", 'woorewards-pro') .
							"<br/>" . __("You can find the points and rewards systems names in WooRewards → Points and Rewards", 'woorewards-pro'),
					),
					'layout' => array(
						'option' => 'layout',
						'desc' => __("(Optional) Select how reward elements are organized . 4 possible values :", 'woorewards-pro'),
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
						'example' => '[wr_rewards layout="grid"]'
					),
					'element' => array(
						'option' => 'element',
						'desc' => __("(Optional) Select how a reward element is displayed. 3 possible values :", 'woorewards-pro'),
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
						'example' => '[wr_rewards element="tile"]'
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
						'example' => '[wr_rewards display="simple"]'
					),
					'showname' => array(
						'option' => 'showname',
						'desc' => __("(Optional) If set, will display the points and rewards system name before each series of rewards", 'woorewards-pro'),
						'example' => '[wr_rewards showname="yes"]',
					),
					'force' => array(
						'option' => 'force',
						'desc' => __("(Optional) If set, possible rewards will be visible to unlogged customers", 'woorewards-pro'),
						'example' => '[wr_rewards force="yes"]',
					),
					'imagesize' => array(
						'option' => 'imagesize',
						'desc' => __("(Optional) Select the size of the reward image shown. 4 possible values :", 'woorewards-pro'),
						'options' => array(
							array(
								'option' => 'lws_wr_thumbnail',
								'desc'   => __("Default Value. Displays an image up to 96x96 pixels", 'woorewards-pro'),
							),
							array(
								'option' => 'thumbnail',
								'desc'   => __("Displays an image up to 150x150 pixels", 'woorewards-pro'),
							),
							array(
								'option' => 'medium',
								'desc'   => __("Displays an image up to 300x300 pixels", 'woorewards-pro'),
							),
							array(
								'option' => 'large',
								'desc'   => __("Displays an image up to 1024 pixels wide", 'woorewards-pro'),
							),
						),
						'example' => '[wr_rewards imagesize="thumbnail"]'
					),
				),
				'flags' => array('current_user_id'),
			)
		);
		return $fields;
	}

	/** Shows rewards
	 * [wr_rewards system='poolname1, poolname2']
	 * @param $atts array :
	 * * system 	→ Default: ''
	 * 					  The points and rewards systems for which the rewards are displayed. If empty, show all active systems
	 * 					  One value or several ones, comma separated
	 * * layout 	→ Default: 'none'
	 * 					  Defines the presentation of the wrapper.
	 * 					  4 possible values : grid, horizontal, vertical, none.
	 * * element 	→ Default: 'none'
	 * 					  Defines the presentation of the elements.
	 * 					  3 possible values : tile, line, none.
	 * * display	→ Default: 'formatted'
	 * 					  'simple'    → only the points numeric value is displayed.
	 * 					  'formatted' → points are formatted with the points currency/name.
	 * * showname	→ Default: false
	 * 					  Shows the name of the points and rewards system if set
	 * * force	→ Default: false
	 * 					  Shows for unlogged users, acces to system users cannot
	 * * imagesize		→ Default: 'lws_wr_thumbnail'
	 * 					  	  Set the reward image's size
	 */
	public function shortcode($atts = array(), $content = '')
	{
		$atts = \LWS\Adminpanel\Tools\Conveniences::sanitizeAttr(\wp_parse_args($atts, array(
			'system'   => '',
			'layout'   => 'grid',
			'element'  => 'tile',
			'display'  => 'formatted',
			'showname' => '',
			'force'    => false,
			'imagesize'   => 'lws_wr_thumbnail',
		)));
		$userId = \apply_filters('lws_woorewards_shortcode_current_user_id', \get_current_user_id(), $atts, 'wr_rewards');
		if (!($userId || $atts['force'])) {
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
				if ($pool->getOption('direct_reward_mode')) {
					continue;
				}
				if ($pool->isUnlockPrevented()) {
					continue;
				}

				$rewards = array();
				$userPoints = ($userId ? $pool->getPoints($userId) : 0);
				foreach ($pool->getUnlockables()->asArray() as $unlockable) {
					$buyable = $unlockable->isPurchasable($userPoints, $userId);
					$rewards[$unlockable->getId()] = array(
						'buyable'    => $buyable,
						'cost'       => $unlockable->getUserCost($userId),
						'unlockable' => $unlockable,
					);
				}
				if ($rewards) {
					\uasort($rewards, function($a, $b){return ($a['cost'] - $b['cost']);});
					$data[$pool->getName()] = array(
						'title'   => $pool->getOption('display_title'),
						'name'    => $pool->getName(),
						'type'    => $pool->getOption('type'),
						'rewards' => $rewards
					);
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
		switch (\strtolower(\substr($atts['layout'], 0, 3))) {
			case 'gri':
				$container = "<div class='wr-rewards wr-shortcode-grid'>%s</div>";
				break;
			case 'hor':
				$container = "<div class='wr-rewards wr-shortcode-hflex'>%s</div>";
				break;
			case 'ver':
				$container = "<div class='wr-rewards wr-shortcode-vflex'>%s</div>";
				break;
			default:
				$container = '%s';
				break;
		}
		$this->enqueueScripts();

		$lastType = false;
		$lastStandardGroup = '';
		$content = array();
		foreach ($data as $item) {
			if ($item['type'] == \LWS\WOOREWARDS\Core\Pool::T_STANDARD) {
				/** Standard Systems */
				if ($atts['showname']) {
					$content[] = "<div class='rewards-system-name'>{$item['title']}</div>";
				}
				$group = '';
				foreach ($item['rewards'] as $reward) {
					$title = $reward['unlockable']->getTitle();
					$descr = $reward['unlockable']->getCustomDescription();
					$cost = $atts['display'] == 'formatted' ? \LWS_WooRewards::formatPointsWithSymbol($reward['cost'], $item['name']) : $reward['cost'];

					if ($atts['element'] == 'tile' || $atts['element'] == 'line') {
						$class = sprintf('item %s %s', \esc_attr($atts['element']), $item['name']);
						if ($reward['buyable'])
							$class .= ' buyable';
						$img = (($img = $reward['unlockable']->getThumbnailImage($this->getVerifiedSize($atts['imagesize']))) ? "<div class='reward-img'>{$img}</div>" : '');

						$group .= <<<EOT
	<div class='{$class}'>{$img}
		<div class='reward-title'>{$title}</div>
		<div class='reward-descr'>{$descr}</div>
		<div class='reward-cost'>{$cost}</div>
	</div>
EOT;
					} else {
						// raw
						$group .= ($title . "  " . $descr . "  " . $cost);
					}
				}
				if (!$atts['showname'] && ($lastType == \LWS\WOOREWARDS\Core\Pool::T_STANDARD)) {
					// replace the last element to group all standards in one since not splitten by a title
					$lastStandardGroup .= $group;
					\array_pop($content);
				} else {
					$lastStandardGroup = $group;
				}
				$content[] = sprintf($container, $lastStandardGroup);
			} else {
				/** Leveling Systems */
				if ($atts['showname']) {
					$content[] = "<div class='rewards-system-name'>{$item['title']}</div>";
				}
				$last = null;
				$group = '';
				$level = '';
				foreach ($item['rewards'] as $reward) {
					$bclass = $reward['buyable'] ? ' buyable' : '';
					// new level
					if ($last !== $reward['cost']) {
						$last = $reward['cost'];
						if ($level)
							$group .= sprintf($container, $level);
						$level = '';
						$group .= sprintf(
							"<div class='reward-level-info%s'><div class='level-name'>%s</div><div class='level-points'>%s</div></div>",
							$bclass,
							$reward['unlockable']->getGroupedTitle('view'),
							$atts['display'] == 'formatted' ? \LWS_WooRewards::formatPointsWithSymbol($reward['cost'], $item['name']) : $reward['cost']
						);
					}

					// level reward
					$title = $reward['unlockable']->getTitle();
					$descr = $reward['unlockable']->getCustomDescription();
					if ($atts['element'] == 'tile' || $atts['element'] == 'line') {
						$class = sprintf('item %s %s', \esc_attr($atts['element']), $item['name']);
						if ($reward['buyable'])
							$class .= ' buyable';
						$img = (($img = $reward['unlockable']->getThumbnailImage($this->getVerifiedSize($atts['imagesize']))) ? "<div class='reward-img'>{$img}</div>" : '');

						$level .= <<<EOT
	<div class='{$class}'>{$img}
		<div class='reward-title'>{$title}</div>
		<div class='reward-descr'>{$descr}</div>
	</div>
EOT;
					} else {
						// raw
						$level .= ($title . "  " . $descr);
					}
				}
				// merge all
				if ($level)
					$group .= sprintf($container, $level);
				$content[] = sprintf("<div class='wr-rewards rewards-leveling-grid'>%s</div>", $group);
			}

			$lastType = $item['type'];
		}
		return implode('', $content);
	}

	private function getVerifiedSize($size)
	{
		return \LWS\Adminpanel\Tools\MediaHelper::getVerifiedMediaSize($size, 'lws_wr_thumbnail');
	}
}
