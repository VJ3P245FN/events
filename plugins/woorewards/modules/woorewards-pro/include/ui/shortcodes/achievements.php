<?php
namespace LWS\WOOREWARDS\PRO\Ui\ShortCodes;

// don't call the file directly
if( !defined( 'ABSPATH' ) ) exit();

class Achievements
{
	private $stygen = false;

	public static function install()
	{
		$me = new self();
		\add_shortcode('lws_achievements', array($me, 'shortcode'));
		\add_filter('lws_adminpanel_stygen_content_get_'.'achievements_template', array($me, 'template'));
		\add_action('wp_enqueue_scripts', array($me, 'registerScripts'));
		\add_action('admin_enqueue_scripts', array($me, 'registerScripts'));
		\add_filter('lws_woorewards_badges_shortcodes', array($me, 'admin'));
	}

	public function admin($fields)
	{
		$fields['achievements'] = array(
			'id'    => 'lws_woorewards_sc_achievements',
			'title' => __("Achievements", 'woorewards-pro'),
			'type'  => 'shortcode',
			'extra' => array(
				'shortcode'   => '[lws_achievements]',
				'description' =>  __("This shortcode shows existing achievements.", 'woorewards-pro'),
				'flags'       => array('current_user_id'),
				'options'     => array(
					array(
						'option' => 'header',
						'desc'   => __("The text displayed before the achievements.", 'woorewards-pro'),
					),
					array(
						'option' => 'display',
						'desc'   => __("Select if you want to show all existing achievements (all), only the ones unlocked by the customer (owned) or not (lack)", 'woorewards-pro'),
					),
				),
			)
		);
		$fields['achievementsstyle'] = array(
			'id'    => 'lws_woorewards_achievements_template',
			'type'  => 'stygen',
			'extra' => array(
				'purpose'  => 'filter',
				'template' => 'achievements_template',
				'html'   => false,
				'css'    => LWS_WOOREWARDS_PRO_CSS . '/templates/achievements.css',
				'subids' => array(
					'lws_woorewards_achievements_widget_message' => "WooRewards - Achievements - Title",
				),
			)
		);
		return $fields;
	}

	function registerScripts()
	{
		\wp_register_style('woorewards-achievements-widget', LWS_WOOREWARDS_PRO_CSS.'/templates/achievements.css?stygen=lws_woorewards_achievements_template', array(), LWS_WOOREWARDS_PRO_VERSION);
	}

	protected function enqueueScripts()
	{
		\wp_enqueue_style('woorewards-achievements-widget');
	}

	public function defaultArgs()
	{
		return array(
			'title'  => '',
			'header' => false,
			'display'=> 'all',
		);
	}

	public function template($snippet)
	{
		$this->stygen = true;
		$atts = $this->defaultArgs();
		$achievements = array(
			array(
				'badge_slug'        => '',
				'badge_thumbnail'   => LWS_WOOREWARDS_PRO_IMG.'/cat.png',
				'badge_title'       => 'The Cat',
				'badge_description' => "Look at me. You know I'm cute even when I break your furniture",
				'ach_title'         => 'Pet the cat',
				'action'            => 'Product Review',
				'occurences'        => '5',
				'done'              => '3',
			),
			array(
				'badge_slug'        => '',
				'badge_thumbnail'   => LWS_WOOREWARDS_PRO_IMG.'/horse.png',
				'badge_title'       => 'The White Horse',
				'badge_description' => "Arya Stark : I'm out of this s***",
				'ach_title'         => 'Flee the city',
				'action'            => 'Refer a friend',
				'occurences'        => '5',
				'done'              => '1',
			),
			array(
				'badge_slug'        => '',
				'badge_thumbnail'   => LWS_WOOREWARDS_PRO_IMG.'/chthulu.png',
				'badge_title'       => 'Chtulhu rules',
				'badge_description' => "You unleashed the power of Chthulu over the world",
				'ach_title'         => 'Invoke Chthulu',
				'action'            => 'Place an order (amount greater than 10.00)',
				'occurences'        => '10',
				'done'              => '15',
			),
		);
		$content = $this->shortcode($atts, $achievements);
		$this->stygen = false;
		return $content;
	}

	public function shortcode($atts=array(), $achievements='')
	{
		$atts = \LWS\Adminpanel\Tools\Conveniences::sanitizeAttr(\wp_parse_args($atts, $this->defaultArgs()));
		if(!($achievements && \is_array($achievements)))
			$achievements = $this->getAchievements($atts);

		$this->enqueueScripts();
		$labels = array(
			'baward' 	  => __("achievement awarded", 'woorewards-pro'),
			'aunlocked'	=> __("Achievement unlocked !", 'woorewards-pro'),
			'aprogress' => __("Current progress", 'woorewards-pro'),
		);

		if (false === $atts['header'] || ($this->stygen && !$atts['header']))
			$atts['header'] = \lws_get_option('lws_woorewards_achievements_widget_message', _x("Here is the list of achievements available on this website", "frontend widget", 'woorewards-pro'));
		if( !$this->stygen )
			$atts['header'] = \apply_filters('wpml_translate_single_string', $atts['header'], 'Widgets', "WooRewards - Achievements - Title");

		$content = '';
		foreach( $achievements as $achievement )
		{
			if($achievement['done'] >= $achievement['occurences']){
				$width = '100';
				$extraclass = ' success';
				$owned = $labels['aunlocked'];
			}else{
				$width = (\intval($achievement['done']) * 100 / $achievement['occurences']);
				$extraclass = '';
				$owned = sprintf(
					_x('%s : %d/%d', '[lws_achievement] progress : x/y', 'woorewards-pro'),
					$labels['aprogress'], $achievement['done'], $achievement['occurences']
				);
			}
			if ($achievement['badge_slug'])
				$extraclass .= (' lws-badge-' . \esc_attr($achievement['badge_slug']));
			$content .= <<<EOT
	<div class='lwss_selectable lws-achievement-container{$extraclass}' data-type='Achievement Box'>
		<div class='lwss_selectable lws-achievement-top' data-type='Top part'>
			<div class='lwss_selectable lws-achievement-imgcol' data-type='Thumbnail'>
				<img class='lws-achievement-img' src='{$achievement['badge_thumbnail']}'/>
			</div>
			<div class='lwss_selectable lws-achievement-contentcol' data-type='Achievement Content'>
				<div class='lwss_selectable lws-achievement-title' data-type='Achivement title'>{$achievement['ach_title']}</div>
				<div class='lwss_selectable lws-achievement-achievement-line' data-type='Badge Data'>
					<div class='lwss_selectable lws-achievement-achievement-title' data-type='Badge title'>{$labels['baward']} : {$achievement['badge_title']}</div>
					<div class='lwss_selectable lws-achievement-achievement-desc' data-type='Badge description'>{$achievement['badge_description']}</div>
				</div>
			</div>
		</div>
		<div class='lwss_selectable lws-achievement-bottom' data-type='Bottom part'>
			<div class='lwss_selectable lws-achievement-action-line' data-type='Text Line'>{$achievement['action']}</div>
			<div class='lwss_selectable lws-achievement-progress-line' data-type='Progress Line'>
				<div class='lwss_selectable lws-achievement-progress-leftval' data-type='Left Value'>0</div>
				<div class='lwss_selectable lws-achievement-progress-bar' data-type='Progress bar Background'><div class='lwss_selectable lws-achievement-progressed-bar' style='width:{$width}%' data-type='Progress bar foreground'></div></div>
				<div class='lwss_selectable lws-achievement-progress-rightval' data-type='Right value'>{$achievement['occurences']}</div>
			</div>
			<div class='lwss_selectable lws-achievement-action-progtext{$extraclass}' data-type='Progress Text'>{$owned}</div>
		</div>
	</div>
EOT;
		}

		if ($atts['header'] || $this->stygen) {
			$atts['header'] = <<<EOT
				<div class='lwss_selectable lwss_modify lws-wr-achievements-header' data-id='lws_woorewards_achievements_widget_message' data-type='Header'>
					<span class='lwss_modify_content'>{$atts['header']}</span>
				</div>
EOT;
		}

		return <<<EOT
			<div class='lwss_selectable lws-woorewards-achievements-cont' data-type='Main Container'>
				{$atts['header']}
				<div class='lwss_selectable lws-achievements-container' data-type='Achievements Container'>
					{$content}
				</div>
			</div>
EOT;
	}

	private function getAchievements($atts=array())
	{
		$achievements = array();
		$userId = \apply_filters('lws_woorewards_shortcode_current_user_id', \get_current_user_id(), $atts, 'lws_achievements');
		// first letter is enough (all | owned)
		$display = \strtolower(\substr($atts['display'], 0, 1));

		foreach (\LWS_WooRewards_Pro::getLoadedAchievements()->asArray() as $achievement) {
			$badge = $achievement->getBadge();
			if($badge){
				$cost = $achievement->getTheReward()->getCost();
				$done = $achievement->getPoints($userId);
				if ('o' == $display) {
					if ($done < $cost) continue; // need score enough
				} elseif ('l' == $display) {
					if ($done >= $cost) continue; // need not reach the score
				} elseif ('a' != $display) {
					continue; //  unknown value
				}
				$achievements[] = array(
					'badge_slug'        => $badge->getSlug(),
					'badge_thumbnail'   => $badge->getThumbnailUrl(),
					'badge_title'       => $badge->getTitle(),
					'badge_description' => $badge->getMessage(),
					'ach_title'         => $achievement->getOption('display_title'),
					'action'            => $achievement->getEvents()->first()->getDescription('frontend'),
					'occurences'        => $cost,
					'done'              => $done,
				);
			}
		}
		return $achievements;
	}

}
