<?php

namespace LWS\WOOREWARDS\PRO\Ui\Shortcodes;

// don't call the file directly
if (!defined('ABSPATH')) exit();

class AvailableRewards
{
	public static function install()
	{
		$me = new self();
		// Shortcode
		\add_shortcode('wr_available_rewards', array($me, 'shortcode'));
		// Admin
		\add_filter('lws_woorewards_rewards_shortcodes', array($me, 'admin'), 5);
		// Scripts
		\add_action('wp_enqueue_scripts', array($me, 'registerScripts'));
	}

	function registerScripts()
	{
		\wp_register_style('wr-available-rewards', LWS_WOOREWARDS_PRO_CSS . '/shortcodes/available-rewards.min.css', array(), LWS_WOOREWARDS_PRO_VERSION);
		\wp_register_script('wr-available-rewards', LWS_WOOREWARDS_PRO_JS . '/shortcodes/reward-redeem-security.js', array('jquery'), LWS_WOOREWARDS_PRO_VERSION);
	}

	protected function enqueueScripts()
	{
		\wp_enqueue_style('wr-available-rewards');
		\wp_enqueue_script('wr-available-rewards');
	}

	/** Get the shortcode admin */
	public function admin($fields)
	{
		$fields['availablerewards'] = array(
			'id' => 'lws_woorewards_available_rewards',
			'title' => __("Available Rewards", 'woorewards-pro'),
			'type' => 'shortcode',
			'extra' => array(
				'shortcode' => '[wr_available_rewards]',
				'description' =>  __("Use this shortcode to show to your customers the rewards they can unlock with their points as well as the possibility to unlock them.", 'woorewards-pro') . "<br/>" .
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
								'option' => 'vertical',
								'desc'   => __("Default value. Elements are displayed on top of another", 'woorewards-pro'),
							),
							array(
								'option' => 'horizontal',
								'desc'   => __("Elements are displayed in row", 'woorewards-pro'),
							),
							array(
								'option' => 'grid',
								'desc'   => __("Elements are displayed in a responsive grid", 'woorewards-pro'),
							),
							array(
								'option' => 'none',
								'desc'   => __("Simple text without stylable elements", 'woorewards-pro'),
							),
						),
						'example' => '[wr_available_rewards layout="grid"]'
					),
					'element' => array(
						'option' => 'element',
						'desc' => __("(Optional) Select how a reward element is displayed. 3 possible values :", 'woorewards-pro'),
						'options' => array(
							array(
								'option' => 'line',
								'desc'   => __("Default value. Horizontal display in stylable elements", 'woorewards-pro'),
							),
							array(
								'option' => 'tile',
								'desc'   => __("Stylable tile with a background color", 'woorewards-pro'),
							),
							array(
								'option' => 'none',
								'desc'   => __("Simple text without stylable elements", 'woorewards-pro'),
							),
						),
						'example' => '[wr_available_rewards element="tile"]'
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
								'desc'   => __("Only the points numeric value is displayed", 'woorewards-pro'),
							),
						),
						'example' => '[wr_available_rewards display="simple"]'
					),
					'showname' => array(
						'option' => 'showname',
						'desc' => __("(Optional) If set, will display the points and rewards system name for each reward", 'woorewards-pro'),
						'example' => '[wr_available_rewards showname="true"]'
					),
					'applyreward' => array(
						'option' => 'applyreward',
						'desc' => __("(Optional) If set, the reward will be automatically applied to the cart in addition to its unlock. Only applies for coupon type rewards", 'woorewards-pro'),
						'example' => '[wr_available_rewards applyreward="true"]'
					),
					'applyonly' => array(
						'option' => 'applyonly',
						'desc' => __("(Optional) If set, only rewards that can be applied on the cart are displayed", 'woorewards-pro'),
						'example' => '[wr_available_rewards applyonly="true"]'
					),
					'redirection' => array(
						'option' => 'redirection',
						'desc' => __("(Optional) If set, customers will be redirected to the set url. Otherwise, customers will either be redirected to the cart page (if the rewards has to be applied) or to the current page", 'woorewards-pro'),
						'example' => '[wr_available_rewards redirection="https://mytestwebsite.com/cart/"]'
					),
					'available' => array(
						'option' => 'available',
						'desc' => __("(Optional, default is 'true') If set, customers will see rewards they are able to unlock", 'woorewards-pro'),
						'example' => '[wr_available_rewards available="true"]'
					),
					'unavailable' => array(
						'option' => 'unavailable',
						'desc' => __("(Optional, default is 'false') If set, customers will also see rewards they don't have enough points to unlock, without the possibility to unlock them", 'woorewards-pro'),
						'example' => '[wr_available_rewards unavailable="true"]'
					),
					'buttonlabel' => array(
						'option' => 'buttonlabel',
						'desc' => __("(Optional) Set this to change the unlock button's label.", 'woorewards-pro'),
						'example' => '[wr_available_rewards buttonlabel="Redeem"]'
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
						'example' => '[wr_available_rewards imagesize="thumbnail"]'
					),
				),
			)
		);
		return $fields;
	}

	/** Shows redeemable rewards
	 * [wr_available_rewards systems='poolname1, poolname2']
	 * @param $atts array :
	 * * system 		→ Default: ''
	 * 					  	  The points and rewards systems for which the rewards points are displayed. If empty, show all active systems
	 * 					  	  One value or several ones, comma separated
	 * * layout 		→ Default: 'vertical'
	 * 					  	  Defines the presentation of the wrapper.
	 * 					  	  4 possible values : grid, vertical, horizontal, none.
	 * * element 		→ Default: 'line'
	 * 					  	  Defines the presentation of the elements.
	 * 					  	  3 possible values : tile, line, none.
	 * * display		→ Default: 'formatted'
	 * 					  	  'simple'    → only the points numeric value is displayed.
	 * 					  	  'formatted' → points are formatted with the points currency/name.
	 * * showname		→ Default: false
	 * 					  	  Shows the name of the points and rewards system if set
	 * * applyreward	→ Default: false
	 * 					  	  Allow the reward to be automatically applied on the cart
	 * * applyonly		→ Default: false
	 * 					  	  If set, only rewards that can be applied on the cart are displayed
	 * * redirection	→ Default: ''
	 * 					  	  If set, customers are redirected to the set url
	 * * available	    → Default: 'true'
	 * 					  	  If set, customers also see available rewards
	 * * unavailable	→ Default: 'false'
	 * 					  	  If set, customers also see unavailable rewards
	 * * buttonlabel	→ Default: ''
	 * 					  	  Set the button label
	 * * imagesize		→ Default: 'lws_wr_thumbnail'
	 * 					  	  Set the reward image's size
	 */
	public function shortcode($atts = array(), $content = null)
	{
		$user = wp_get_current_user();
		if ($user && $user->ID) {
			$atts = \LWS\Adminpanel\Tools\Conveniences::sanitizeAttr(\wp_parse_args($atts, array(
				'system'      => '',
				'layout'      => 'vertical',
				'element'     => 'line',
				'display'     => 'formatted',
				'showname'    => false,
				'applyreward' => false,
				'applyonly'   => false,
				'redirection' => false,
				'available'   => true,
				'unavailable' => false,
				'buttonlabel' => '',
				'imagesize'   => 'lws_wr_thumbnail',
			)));
			// Basic verifications
			if (!$atts['system']) {
				$atts['showall'] = true;
			}
			$atts['showname'] = \LWS\Adminpanel\Tools\Conveniences::argIsTrue($atts['showname']);

			// Get the data
			$unlockables = array();
			$pools = \apply_filters('lws_woorewards_get_pools_by_args', false, $atts);
			if ($pools && $pools->count()) {
				if (\LWS\Adminpanel\Tools\Conveniences::argIsTrue($atts['available'])) {
					$unlockables = \LWS\WOOREWARDS\PRO\Conveniences::instance()->getUserUnlockables($user->ID, 'avail', $pools);
				}
				if (\LWS\Adminpanel\Tools\Conveniences::argIsTrue($atts['unavailable'])) {
					$unlockables = \array_merge($unlockables, \LWS\WOOREWARDS\PRO\Conveniences::instance()->getUserUnlockables($user->ID, 'unavail', $pools));
				}
			}
			if ($unlockables) {
				return $this->getContent($atts, $unlockables, $user);
			}
		}
		return \do_shortcode($content);
	}

	protected function getContent($atts, $unlockables, $user)
	{
		// prepare
		$applyonly = \LWS\Adminpanel\Tools\Conveniences::argIsTrue($atts['applyonly']);
		$texts = array(
			'system'  => __("System", 'woorewards-pro'),
			'cost'    => __("Reward cost", 'woorewards-pro'),
			'balance' => __("Your balance", 'woorewards-pro')
		);
		$elements = '';

		// build rows
		foreach ($unlockables as $unlockable) {
			if ($applyonly && !$unlockable->isAutoApplicable()) {
				continue;
			}
			// row values
			$title  = $unlockable->getTitle();
			$descr  = $unlockable->getCustomDescription();
			$pool   = $unlockable->getPool();
			$pts    = $pool->getPoints($user->ID);
			$cost   = ($atts['display'] == 'formatted' ? \LWS_WooRewards::formatPointsWithSymbol($unlockable->getUserCost($user->ID), $pool->getName()) : $unlockable->getUserCost($user->ID));
			$points = ($atts['display'] == 'formatted' ? \LWS_WooRewards::formatPointsWithSymbol($pts, $pool->getName()) : $pts);
			$purchasable = $unlockable->isPurchasable($pts, $user->ID);

			if ($atts['element'] == 'tile' || $atts['element'] == 'line') {
				// variable dom
				if ($purchasable) {
					if ($unlockable->isAutoApplicable() && \LWS\Adminpanel\Tools\Conveniences::argIsTrue($atts['applyreward'])) {
						$btText = __("Unlock and Apply", 'woorewards-pro');
						if (false !== $atts['redirection'])
							$url = ($atts['redirection'] ? $atts['redirection'] : false);
						else
							$url = (\function_exists('\wc_get_cart_url') ? \wc_get_cart_url() : false);
						$btUrl = esc_attr(\LWS\WOOREWARDS\PRO\Core\RewardClaim::addUrlUnlockArgs($url, $unlockable, $user, true));
					} else {
						$btText = __("Unlock", 'woorewards-pro');
						$url = ($atts['redirection'] ? $atts['redirection'] : false);
						$btUrl = esc_attr(\LWS\WOOREWARDS\PRO\Core\RewardClaim::addUrlUnlockArgs($url, $unlockable, $user));
					}
				} else {
					if ($unlockable->isAutoApplicable() && \LWS\Adminpanel\Tools\Conveniences::argIsTrue($atts['applyreward'])) {
						$btText = __("Unlock and Apply", 'woorewards-pro');
					} else {
						$btText = __("Unlock", 'woorewards-pro');
					}
				}
				if ($atts['buttonlabel'] != '') {
					$btText = $atts['buttonlabel'];
				}
				$img = (($img = $unlockable->getThumbnailImage($this->getVerifiedSize($atts['imagesize']))) ? "<div class='reward-img'>{$img}</div>" : '');
				$parent = '';
				if ($atts['showname']) {
					$parent = sprintf(
						"<div class='att system'><div class='title'>%s</div><div class='value'>%s</div></div>",
						$texts['system'],
						$pool->getOption('display_title')
					);
				}
				$availClass = ($purchasable ? 'available' : 'unavailable');
				$btnAttrs = ($purchasable ? "class='button lws-reward-redeem' data-href='{$btUrl}'" : "class='button'");

				$elements .= <<<EOT
	<div class='item {$availClass} {$atts['element']}'>{$img}
		<div class='reward-info'>
			<div class='reward-title'>{$title}</div>
			<div class='reward-descr'>{$descr}</div>
		</div>
		<div class='reward-atts'>{$parent}
			<div class='att cost'>
				<div class='title'>{$texts['cost']}</div>
				<div class='value'>{$cost}</div>
			</div>
			<div class='att balance'>
				<div class='title'>{$texts['balance']}</div>
				<div class='value'>{$points}</div>
			</div>
		</div>
		<div class='apply-button'>
			<div {$btnAttrs}>$btText</div>
		</div>
	</div>
EOT;
			} else {
				// raw
				$elements .= $title . '  ' . $descr . '  ' . $cost;
			}
		}

		$this->enqueueScripts();
		switch (\strtolower(\substr($atts['layout'], 0, 3))) {
			case 'gri':
				return "<div class='wr-available-rewards wr-shortcode-grid'>$elements</div>";
			case 'hor':
				return "<div class='wr-available-rewards wr-shortcode-hflex'>$elements</div>";
			case 'ver':
				return "<div class='wr-available-rewards wr-shortcode-vflex'>$elements</div>";
			default:
				return $elements;
		}
	}

	private function getVerifiedSize($size)
	{
		return \LWS\Adminpanel\Tools\MediaHelper::getVerifiedMediaSize($size, 'lws_wr_thumbnail');
	}

}
