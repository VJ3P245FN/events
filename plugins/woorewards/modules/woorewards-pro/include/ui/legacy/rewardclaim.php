<?php

namespace LWS\WOOREWARDS\PRO\Ui\Legacy;

// don't call the file directly
if (!defined('ABSPATH')) exit();

/** Popup that shows a popup when  */
class RewardClaim
{
	private $stygen = false;

	public static function register()
	{
		// Stygen
		\add_filter('lws_adminpanel_stygen_content_get_lws_reward_claim', function($content){
			$me = new \LWS\WOOREWARDS\PRO\Ui\Legacy\RewardClaim();
			return $me->getTemplate();
		});
		// Scripts
		\add_action('wp_enqueue_scripts', function(){
			\wp_register_style('lws-wr-rewardclaim-style', LWS_WOOREWARDS_PRO_CSS . '/templates/rewardclaim.css?stygen=lws_woorewards_lws_reward_claim', array('lws-icons'), LWS_WOOREWARDS_PRO_VERSION);
			\wp_register_script('lws-wr-rewardclaim', LWS_WOOREWARDS_PRO_JS . '/legacy/rewardclaim.js', array('jquery', 'jquery-ui-core'), LWS_WOOREWARDS_PRO_VERSION, true);
		});
	}

	function enqueueScripts()
	{
		\wp_enqueue_style('lws-icons');
		\wp_enqueue_style('lws-wr-rewardclaim-style');
		\wp_enqueue_script('lws-wr-rewardclaim');
	}

	function getTemplate()
	{
		$this->stygen = true;
		$notice = array(
			'title' => 'The Unlocked Reward Title',
			'message' => 'The desscription of the unlocked reward',
		);
		$pool = \LWS\WOOREWARDS\Collections\Pools::instanciate()->create('dummy')->last();

		$coupon = new \LWS\WOOREWARDS\PRO\Unlockables\Coupon();
		$coupon->setInPercent(false);
		$coupon->setValue('10');
		$coupon->setTitle('The Cat Reward');
		$coupon->setDescription('This is not a real reward - But it looks cool anyway');
		$coupon->dummyImg = \esc_attr(LWS_WOOREWARDS_PRO_IMG . '/cat.png');
		$pool->addUnlockable($coupon, '50');
		$unlockables[] = $coupon;
		$coupon = new \LWS\WOOREWARDS\PRO\Unlockables\Coupon();
		$coupon->setInPercent(true);
		$coupon->setValue('5');
		$coupon->setTitle('The New Woo Reward');
		$coupon->setDescription('This is not a real reward - But it looks cool too');
		$coupon->dummyImg = \esc_attr(LWS_WOOREWARDS_PRO_IMG . '/horse.png');
		$pool->addUnlockable($coupon, '40');
		$unlockables[] = $coupon;
		$content = $this->getPopup($notice, $unlockables);
		$this->stygen = false;
		return $content;
	}

	/** @param $notice array('title'=>'', 'message'=>'')
	 *	@param $unlockables : additional rewards that can still be unlocked
	 *	@param $popupId : additional id that will provoke popup animation
	 *	@return (string) html div */
	public function getPopup($notice, $unlockables = false, $popupId = '')
	{
		$this->enqueueScripts();
		$demo = $this->stygen;
		$title = \lws_get_option('lws_woorewards_wc_reward_claim_title', __("New reward unlocked !", 'woorewards-pro'));
		$header = \lws_get_option('lws_woorewards_wc_reward_claim_header', __("You've just unlocked the following reward :", 'woorewards-pro'));
		$stitle = \lws_get_option('lws_woorewards_wc_reward_claim_stitle', __("Other rewards are waiting for you", 'woorewards-pro'));
		$notice = \wp_parse_args($notice, array('title' => '', 'message' => ''));

		if (!$this->stygen) {
			$title = \apply_filters('wpml_translate_single_string', $title, 'Widgets', "WooRewards - Reward Claim Popup - Title");
			$header = \apply_filters('wpml_translate_single_string', $header, 'Widgets', "WooRewards - Reward Claim Popup - Header");
			$stitle = \apply_filters('wpml_translate_single_string', $stitle, 'Widgets', "WooRewards - Reward Claim Popup - Subtitle");
		}

		$orcontent = '';
		if ($unlockables && \get_option('lws_wr_rewardclaim_notice_with_rest', 'on')) {
			$orcontent .= <<<EOT
			<div class='lwss_selectable lws-woorewards-reward-claim-others' data-type='Unlockable rewards'>
				<div class='lwss_selectable lwss_modify lws-wr-reward-claim-stitle' data-id='lws_woorewards_wc_reward_claim_stitle' data-type='Second Title'>
					<span class='lwss_modify_content'>{$stitle}</span>
				</div>
EOT;
			foreach ($unlockables as $unlockable) {
				$pool = $unlockable->getPool();
				$pName = $pool->getName();
				$pTitle = $pool->getOption('display_title');
				$userId = \get_current_user_id();
				$user = \get_user_by('ID', $userId);
				$points = $demo ? 254 : $pool->getPoints($userId);
				if (!($pointName = apply_filters('lws_woorewards_point_symbol_translation', false, 2, $pName)))
					$pointName = __('Points', 'woorewards-pro');
				$u = array(
					'img'   => $unlockable->getThumbnailImage(),
					'title' => $unlockable->getTitle(),
					'descr' => $unlockable->getCustomDescription(),
					'cost'  => $unlockable->getUserCost($userId, 'front'),
				);
				if (!$u['img'] && $demo && isset($unlockable->dummyImg))
					$u['img'] = "<img class='lws-wr-thumbnail lws-wr-unlockable-thumbnail' src='{$unlockable->dummyImg}'/>";

				$unlockLink = esc_attr(\LWS\WOOREWARDS\PRO\Core\RewardClaim::addUrlUnlockArgs(
					\LWS\WOOREWARDS\PRO\Conveniences::instance()->getUrlTarget($demo),
					$unlockable,
					$user
				));
				$labels = array(
					'lsystem' 		=> __("Loyalty System", 'woorewards-pro'),
					'ypoints' 		=> sprintf(__("Your %s", 'woorewards-pro'), $pointName),
					'unlock' 		=> __("Unlock", 'woorewards-pro'),
					'cost'	 		=> sprintf(__("%s cost", 'woorewards-pro'), $pointName)
				);

				$orcontent .= <<<EOT
			<div class='lwss_selectable lws-woorewards-reward-claim-other' data-type='Unlockable reward'>
				<div class='lwss_selectable lws-woorewards-reward-claim-other-thumb' data-type='Unlockable thumbnail'>{$u['img']}</div>
				<div class='lwss_selectable lws-woorewards-reward-claim-other-cont' data-type='Unlockable details'>
					<div class='lwss_selectable lws-woorewards-reward-claim-other-title' data-type='Unlockable Title'>{$u['title']}</div>
					<div class='lwss_selectable lws-woorewards-reward-claim-other-desc' data-type='Unlockable Description'>{$u['descr']}</div>
				</div>
				<div class='lwss_selectable lws-woorewards-reward-claim-other-info' data-type='Unlockable Informations'><table class='lwss_selectable lws-woorewards-reward-claim-other-table' data-type='Information table'>
					<tr><th class='lwss_selectable lws-woorewards-reward-claim-other-th' data-type='Information header'>{$labels['lsystem']}</th><td>{$pTitle}</td></tr>
					<tr><th class='lwss_selectable lws-woorewards-reward-claim-other-th' data-type='Information header'>{$labels['ypoints']}</th><td>{$points}</td></tr>
					<tr><th class='lwss_selectable lws-woorewards-reward-claim-other-th' data-type='Information header'>{$labels['cost']}</th><td>{$u['cost']}</td></tr>
				</table></div>
				<div class='lwss_selectable lws-woorewards-reward-claim-other-unlock' data-type='Unlockable Action'>
					<button class='lwss_selectable lws-woorewards-reward-claim-other-button' data-type='Unlock Button' data-href="{$unlockLink}">{$labels['unlock']}</button>
				</div>
			</div>
EOT;
			}
		}

		return <<<EOT
			<div id='{$popupId}' class='lwss_selectable lws-woorewards-reward-claim-cont' data-type='Main Container'>
				<div class='lws-wr-reward-claim-titleline'>
					<div class='lwss_selectable lwss_modify lws-wr-reward-claim-title' data-id='lws_woorewards_wc_reward_claim_title' data-type='Title'>
						<span class='lwss_modify_content'>{$title}</span>
					</div>
					<div class='lwss_selectable lws-wr-reward-claim-close lws-icon lws-icon-cross' data-type='Close Button'></div>
				</div>
				<div class='lwss_selectable lwss_modify lws-wr-reward-claim-header' data-id='lws_woorewards_wc_reward_claim_header' data-type='Header'>
					<span class='lwss_modify_content'>{$header}</span>
				</div>
				<div class='lwss_selectable lws-wr-reward-claimed' data-type='Unlocked reward'>
					<div class='lwss_selectable lws-wr-reward-claimed-title' data-type='Reward Title'>{$notice['title']}</div>
					<div class='lwss_selectable lws-wr-reward-claimed-desc' data-type='Reward Description'>{$notice['message']}</div>
				</div>
				$orcontent
			</div>
EOT;
	}
}
