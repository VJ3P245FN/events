<?php

namespace LWS\WOOREWARDS\PRO\Ui\Legacy;

// don't call the file directly
if (!defined('ABSPATH')) exit();

/** Create an endpoint in frontpage.
 * Show customer the website achievements. */
class AchievementsEndpoint extends \LWS\WOOREWARDS\PRO\Ui\Legacy\Endpoint
{
	function __construct()
	{
		if ($this->isActive('lws_woorewards_wc_my_account_endpoint_achievements', 'on')) {
			$libPage = \lws_get_option('lws_woorewards_wc_my_account_achievements_label', __("Achievements", 'woorewards-pro'));
			parent::__construct('lws_achievements', $libPage, "WooRewards - My Account - Achievements Tab Title");
		}
		\add_filter('lws_adminpanel_themer_content_get_' . 'wc_achievements_endpoint', array($this, 'template'));
		\add_action('wp_enqueue_scripts', array($this, 'registerScripts'));
		\add_action('admin_enqueue_scripts', array($this, 'registerScripts'));
		\add_shortcode('wr_user_achievements', array($this, 'shortcode'));
	}

	/** [wr_user_achievements] */
	function shortcode($atts = array(), $content = '')
	{
		return $this->getPage();
	}

	function registerScripts()
	{
		\wp_register_style('woorewards-achievements-endpoint', LWS_WOOREWARDS_PRO_CSS . '/achievements-endpoint.css?themer=lws_wre_myaccount_achievements_view', array(), LWS_WOOREWARDS_PRO_VERSION);
	}

	protected function enqueueScripts()
	{
		\wp_enqueue_style('woorewards-achievements-endpoint');
	}

	protected function defaultLabels()
	{
		return array(
			'aoverview' => __("Achievements", 'woorewards-pro'),
			'baward' 	=> __("Badge awarded", 'woorewards-pro'),
			'aunlocked'	=> __("Achievement unlocked !", 'woorewards-pro'),
			'aprogress' => __("Current progress", 'woorewards-pro'),
		);
	}

	function template($snippet)
	{
		$achievements = array(
			array(
				'badge_thumbnail' => LWS_WOOREWARDS_PRO_IMG . '/cat.png',
				'badge_title' => 'The Cat',
				'badge_description' => "Look at me. You know I'm cute even when I break your furniture",
				'ach_title' => 'Pet the cat',
				'action' => 'Product Review',
				'occurences' => '5',
				'done' => '3',
			),
			array(
				'badge_thumbnail' => LWS_WOOREWARDS_PRO_IMG . '/horse.png',
				'badge_title' => 'The White Horse',
				'badge_description' => "Arya Stark : I'm out of this s***",
				'ach_title' => 'Flee the city',
				'action' => 'Refer a friend',
				'occurences' => '5',
				'done' => '1',
			),
			array(
				'badge_thumbnail' => LWS_WOOREWARDS_PRO_IMG . '/chthulu.png',
				'badge_title' => 'Chtulhu rules',
				'badge_description' => "You unleashed the power of Chthulu over the world",
				'ach_title' => 'Invoke Chthulu',
				'action' => 'Place an order (amount greater than 10.00)',
				'occurences' => '10',
				'done' => '15',
			),
		);
		return $this->getContent($achievements);
	}


	function getPage()
	{
		$achievements = array();
		$userId = \get_current_user_id();
		$all_achievements = \LWS_WooRewards_Pro::getLoadedAchievements();
		foreach ($all_achievements->asArray() as $one_achievement) {
			$badge = $one_achievement->getBadge();
			if ($badge) {
				$achievement['badge_thumbnail'] = $badge->getThumbnailUrl();
				$achievement['badge_title'] = $badge->getTitle();
				$achievement['badge_description'] = $badge->getMessage();
				$achievement['ach_title'] = $one_achievement->getOption('display_title');
				$achievement['action'] = $one_achievement->getEvents()->first()->getDescription('frontend');
				$achievement['occurences'] = $one_achievement->getTheReward()->getCost();
				$achievement['done'] = $one_achievement->getPoints($userId);
				$achievements[] = $achievement;
			}
		}
		return $this->getContent($achievements);
	}

	function getContent($achievements)
	{
		$this->enqueueScripts();
		$labels = $this->defaultLabels();
		$content = <<<EOT
		<div class="ae-main-container flcol">
			<div class="ae-title-container flcol">
				<div class="ae-title-line flrow">
					<div class="ae-title-text flexooa">{$labels['aoverview']}</div>
					<div class="flexiia"></div>
				</div>
			</div>
EOT;

		$content .= "<div class='ae-achievements-container'>";
		foreach ($achievements as $achievement) {
			if ($achievement['done'] >= $achievement['occurences']) {
				$width = '100';
				$extraclass = 'success';
				$owned = $labels['aunlocked'];
			} else {
				$width = intval($achievement['done']) * 100 / $achievement['occurences'];
				$extraclass = '';
				$owned = $labels['aprogress'] . ' : ' . $achievement['done'] . '/' . $achievement['occurences'];
			}

			$content .= "<div class='ae-achievement-container $extraclass'>";
			$content .= "<div class='ae-achievement-top'>";
			$content .= "<div class='ae-achievement-imgcol'><img class='ae-achievement-img' src='{$achievement['badge_thumbnail']}'/></div>";
			$content .= "<div class='ae-achievement-contentcol'>";
			$content .= "<div class='ae-achievement-title'>{$achievement['ach_title']}</div>";
			$content .= "<div class='ae-achievement-badge-line'>";
			$content .= "<div class='ae-achievement-badge-title'>{$labels['baward']} : {$achievement['badge_title']}</div>";
			$content .= "<div class='ae-achievement-badge-desc'>{$achievement['badge_description']}</div>";
			$content .= "</div></div></div>";
			$content .= "<div class='ae-achievement-bottom'>";
			$content .= "<div class='ae-achievement-action-line'>{$achievement['action']}</div>";
			$content .= "<div class='ae-achievement-progress-line'>";
			$content .= "<div class='ae-achievement-progress-leftval'>0</div>";
			$content .= "<div class='ae-achievement-progress-bar'><div class='ae-achievement-progressed-bar' style='width:{$width}%'></div></div>";
			$content .= "<div class='ae-achievement-progress-rightval'>{$achievement['occurences']}</div>";
			$content .= "</div>";
			$content .= "<div class='ae-achievement-action-line $extraclass'>{$owned}</div>";
			$content .= "</div>";
			$content .= "</div>";
		}
		$content .= "</div></div>";
		return $content;
	}
}
