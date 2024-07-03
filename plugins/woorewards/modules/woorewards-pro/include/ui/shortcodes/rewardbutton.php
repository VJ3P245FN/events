<?php

namespace LWS\WOOREWARDS\PRO\Ui\Shortcodes;

// don't call the file directly
if (!defined('ABSPATH')) exit();

class RewardButton
{
	public static function install()
	{
		$me = new self();
		\add_shortcode('wr_reward_button', array($me, 'shortcode'));
		\add_filter('lws_woorewards_advanced_shortcodes', array($me, 'admin'));
		// Scripts
		\add_action('wp_enqueue_scripts', array($me, 'registerScripts'));
	}

	function registerScripts()
	{
		\wp_register_script('wr-reward-button', LWS_WOOREWARDS_PRO_JS . '/shortcodes/reward-redeem-security.js', array('jquery'), LWS_WOOREWARDS_PRO_VERSION);
	}

	protected function enqueueScripts()
	{
		\wp_enqueue_script('wr-reward-button');
	}

	/** Get the shortcode admin */
	public function admin($fields)
	{
		$fields['rewardbutton'] = array(
			'id' => 'lws_woorewards_reward_button',
			'title' => __("Reward Button", 'woorewards-pro'),
			'type' => 'shortcode',
			'extra' => array(
				'shortcode' => '[wr_reward_button reward=""] Button text [/wr_reward_button]',
				'description' =>  __("Use this shortcode to show a button to your customers they can use to unlock a specific reward", 'woorewards-pro'),
				'options' => array(
					'reward' => array(
						'option' => 'reward',
						'desc'   => __("The id of the reward to unlock. You can find this information if you place your mouse over a reward in your points and rewards system", 'woorewards-pro'),
						'example' => '[wr_reward_button reward="123"]Unlock this awesome reward[/wr_reward_button]'
					),
					'applyreward' => array(
						'option' => 'applyreward',
						'desc' => __("(Optional) If set, the reward will be automatically applied to the cart in addition to its unlock. Only applies for coupon type rewards", 'woorewards-pro'),
						'example' => '[wr_reward_button reward="123" applyreward="true"]Unlock &amp; Apply[/wr_reward_button]'
					),
					'redirection' => array(
						'option' => 'redirection',
						'desc' => __("(Optional) If set, customers will be redirected to the set url. Otherwise, customers will either be redirected to the cart page (if the rewards has to be applied) or to the current page", 'woorewards-pro'),
						'example' => '[wr_reward_button reward="123" redirection="https://mytestwebsite.com/cart/"]Unlock[/wr_reward_button]'
					),
					'alt' => array(
						'option' => 'alt',
						'desc' => __("(Optional) Alternative text if the reward cannot be redeemed for any reason", 'woorewards-pro'),
						'example' => '[wr_reward_button reward="123" alt="Sorry"]Unlock[/wr_reward_button]'
					),
				),
			)
		);
		return $fields;
	}

	/** Display content only if a special requirement is met
	 * [wr_reward_button reward=""]The button text or content, shortcodes allowed[/wr_reward_button]
	 * * reward: (int) The unlockable ID to give.
	 * * alt: secret arg, alternative content if we cannot show the button for any reason.
	 */
	public function shortcode($atts = array(), $content = '')
	{
		$atts = \LWS\Adminpanel\Tools\Conveniences::sanitizeAttr(\shortcode_atts(array(
			'reward'      => 0,
			'applyreward' => '',
			'redirection' => false,
			'alt'         => false,
		), $atts, 'wr_reward_button'));

		$purchasable = false;
		$user = \wp_get_current_user();
		$atts['reward'] = \intval($atts['reward']);

		if ($user && $user->ID && $atts['reward']) {
			$unlockable = $this->getReward($atts['reward']);
			if ($unlockable) {
				$pool = $unlockable->getOrLoadPool();
				if ($pool) {
					$purchasable = $pool->isPurchasable($unlockable, false, $user->ID);
				}
			}
		}

		if ($purchasable) {
			$this->enqueueScripts();
			$apply = \LWS\Adminpanel\Tools\Conveniences::argIsTrue($atts['applyreward']) && $unlockable->isAutoApplicable();
			$url = false;
			if (false !== $atts['redirection']) {
				$url = $atts['redirection'] ? $atts['redirection'] : false;
			} elseif ($apply && \function_exists('\wc_get_cart_url')) {
				$url = \wc_get_cart_url();
			}

			$href = \esc_attr(\LWS\WOOREWARDS\PRO\Core\RewardClaim::addUrlUnlockArgs($url, $unlockable, $user, $apply));
			$content = \do_shortcode($content);
			return "<div class='button lws-reward-redeem' data-href='{$href}'>{$content}</div>";
		} else {
			$atts['alt'] = \do_shortcode(false !== $atts['alt'] ? $atts['alt'] : $content);
			return "<div class='button disabled lws-reward-button' href='#' disabled='disabled'>{$atts['alt']}</div>";
		}
	}

	/** Get Unlockable from already loaded Pools.
	 *	No use to load any other since it will not be purchasable. */
	protected function getReward($id)
	{
		foreach (\LWS_WooRewards_Pro::getLoadedPools()->asArray() as $pool) {
			$unlockable = $pool->getUnlockables()->find($id);
			if ($unlockable)
				return $unlockable;
		}
	}
}
