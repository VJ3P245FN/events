<?php

namespace LWS\WOOREWARDS\PRO\Ui\Popups;

// don't call the file directly
if (!defined('ABSPATH')) exit();

/** Popup that shows when a customer unlocks a reward */
class RewardPopup
{
	public static function register()
	{
		// Scripts
		\add_action('wp_enqueue_scripts', function(){
			\wp_register_style('wr-reward-popup', LWS_WOOREWARDS_PRO_CSS . '/popups/reward.min.css', array('lws-icons', 'lws-popup'), LWS_WOOREWARDS_PRO_VERSION);
			\wp_register_script('wr-reward-popup', LWS_WOOREWARDS_PRO_JS . '/popups/reward.js', array('jquery', 'jquery-ui-core', 'lws-popup'), LWS_WOOREWARDS_PRO_VERSION, true);
		});
	}

	function enqueueScripts()
	{
		\wp_enqueue_style('lws-icons');
		\wp_enqueue_style('lws-popup');
		\wp_enqueue_script('lws-popup');
		\wp_enqueue_style('wr-reward-popup');
		\wp_enqueue_script('wr-reward-popup');
	}

	/** @param $notice array('title'=>'', 'message'=>'')
	 *	@param $unlockables : additional rewards that can still be unlocked
	 *	@param $popupId : additional id that will provoke popup animation
	 *	@return (string) html div */
	public function getPopup($notice, $unlockables = false, $popupId = '')
	{
		$this->enqueueScripts();
		$title = \lws_get_option('lws_wr_reward_popup_title', __("New reward unlocked !", 'woorewards-pro'));
		$stitle = \lws_get_option('lws_wr_reward_popup_remaining_text', __("Other rewards are waiting for you", 'woorewards-pro'));
		$title = \apply_filters('wpml_translate_single_string', $title, 'Popups', "WooRewards Reward Popup - title");
		$stitle = \apply_filters('wpml_translate_single_string', $stitle, 'Popups', "WooRewards Reward Popup - remaining label");
		$notice = \wp_parse_args($notice, array('title' => '', 'message' => ''));
		$layout = \get_option('lws_wr_reward_popup_layout', 'all');

		$orcontent = '';
		if ($unlockables && \get_option('lws_wr_rewardclaim_notice_with_rest', 'on')) {
			$orcontent .= <<<EOT
			<div class="lws-popup-stitle">{$stitle}</div>
			<div class="lws-popup-content {$layout}">
				<div class="content-up lws-icon-up-arrow hidden"></div>
				<div class="lws-popup-items">
EOT;
			foreach ($unlockables as $unlockable) {
				$pool = $unlockable->getPool();
				$pName = $pool->getName();
				$userId = \get_current_user_id();
				$user = \get_user_by('ID', $userId);
				$points = $pool->getPoints($userId);
				if (!($pointName = apply_filters('lws_woorewards_point_symbol_translation', false, 2, $pName)))
					$pointName = __('Points', 'woorewards-pro');
				$u = array(
					'img'   => $unlockable->getThumbnailImage(),
					'title' => $unlockable->getTitle(),
					'descr' => $unlockable->getCustomDescription(),
					'cost'  => $unlockable->getUserCost($userId, 'front'),
				);

				$unlockLink = esc_attr(\LWS\WOOREWARDS\PRO\Core\RewardClaim::addUrlUnlockArgs(
					\LWS\WOOREWARDS\PRO\Conveniences::instance()->getUrlTarget(),
					$unlockable,
					$user
				));
				$labels = array(
					'ypoints' 		=> sprintf(__("Your %s", 'woorewards-pro'), $pointName),
					'unlock' 		=> __("Unlock", 'woorewards-pro'),
					'cost'	 		=> sprintf(__("%s cost", 'woorewards-pro'), $pointName)
				);

				$orcontent .= <<<EOT
			<div class='lws-popup-item reward'>
				<div class='reward-thumbnail'>{$u['img']}</div>
				<div class='reward-details'>
					<div class='title'>{$u['title']}</div>
					<div class='desc'>{$u['descr']}</div>
				</div>
				<div class='reward-points-info'>
					<div class='points-label'>{$labels['ypoints']}</div>
					<div class='points-value'>{$points}</div>
					<div class='cost-label'>{$labels['cost']}</div>
					<div class='cost-value'>{$u['cost']}</div>
				</div>
				<div class='reward-unlock'>
					<button class='unlock-button wr_unlock_reward' data-href="{$unlockLink}">{$labels['unlock']}</button>
				</div>
			</div>
EOT;
			}
			$orcontent .= <<<EOT
				</div>
				<div class="content-down lws-icon-down-arrow hidden"></div>
			</div>
EOT;
		}

		$rows = '';
		if ('threebythree' == $layout) {
			$rows = sprintf(' data-rows="%d"', \max(2, \intval(\apply_filters('lws_woorewards_reward_popup_threebythree', 3))));
		}

		return <<<EOT
			<div id='{$popupId}' class='lws_popup lws-popup lws-shadow wr_reward_popup' data-layout="{$layout}"{$rows}>
				<div class="lws-window">
					<div class="lws-popup-close lws-icon-cross"></div>
					<div class="lws-popup-title">{$title}</div>
					<div class='unlocked-reward'>
						<div class='title'>{$notice['title']}</div>
						<div class='desc'>{$notice['message']}</div>
					</div>
					$orcontent
				</div>
			</div>
EOT;
	}
}
