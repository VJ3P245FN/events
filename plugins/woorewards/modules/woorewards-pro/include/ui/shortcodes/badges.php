<?php
namespace LWS\WOOREWARDS\PRO\Ui\ShortCodes;

// don't call the file directly
if( !defined( 'ABSPATH' ) ) exit();

/** Provide a widget to display badges
 * Can be used as a Widget or a Shortcode [lws_badges]. */
class Badges
{
	private $stygen = false;

	public static function install()
	{
		$me = new self();
		\add_shortcode('lws_badges', array($me, 'shortcode'));
		\add_filter('lws_adminpanel_stygen_content_get_'.'badges_template', array($me, 'template'));
		\add_action('wp_enqueue_scripts', array($me, 'registerScripts'));
		\add_action('admin_enqueue_scripts', array($me, 'registerScripts'));
		\add_filter('lws_woorewards_badges_shortcodes', array($me, 'admin'));
	}

	public function admin($fields)
	{
		$fields['badges'] = array(
			'id'    => 'lws_woorewards_sc_badges',
			'title' => __("Badges", 'woorewards-pro'),
			'type'  => 'shortcode',
			'extra' => array(
				'shortcode'   => '[lws_badges]',
				'description' =>  __("This shortcode shows badges to customers.", 'woorewards-pro'),
				'flags'       => array('current_user_id'),
				'options'     => array(
					array(
						'option' => 'header',
						'desc'   => __("The text displayed before the badges.", 'woorewards-pro'),
					),
					array(
						'option' => 'display',
						'desc'   => __("Select if you want to show all existing badges (all), only the ones owned by the customer (owned) or not (lack)", 'woorewards-pro'),
					),
				),
			)
		);
		$fields['badgesstyle'] = array(
			'id'    => 'lws_woorewards_badges_template',
			'type'  => 'stygen',
			'extra' => array(
				'purpose'  => 'filter',
				'template' => 'badges_template',
				'html'     => false,
				'css'      => LWS_WOOREWARDS_PRO_CSS . '/templates/badges.css',
				'subids'   => array(
					'lws_woorewards_badges_widget_message' => "WooRewards - Badges - Title",
				),
			)
		);
		return $fields;
	}

	function registerScripts()
	{
		\wp_register_style('woorewards-badges-widget', LWS_WOOREWARDS_PRO_CSS.'/templates/badges.css?stygen=lws_woorewards_badges_template', array(), LWS_WOOREWARDS_PRO_VERSION);
	}

	protected function enqueueScripts()
	{
		\wp_enqueue_style('woorewards-badges-widget');
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
		$badges = array(
			array(
				'slug'          => '',
				'thumbnail'     => LWS_WOOREWARDS_PRO_IMG.'/cat.png',
				'title'         => 'The Cat',
				'description'   => "Look at me. You know I'm cute even when I break your furniture",
				'unlockDate'    => false,
				'rarityPercent' => '54.3',
				'rarityLabel'   => 'Common',
			),
			array(
				'slug'          => '',
				'thumbnail'     => LWS_WOOREWARDS_PRO_IMG.'/horse.png',
				'title'         => 'The White Horse',
				'description'   => "Arya Stark : I'm out of this s***",
				'unlockDate'    => false,
				'rarityPercent' => '9.6',
				'rarityLabel'   => 'Epic',
			),
			array(
				'slug'          => '',
				'thumbnail'     => LWS_WOOREWARDS_PRO_IMG.'/chthulu.png',
				'title'         => 'Chtulhu rules',
				'description'   => "You unleashed the power of Chthulu over the world",
				'unlockDate'    => \date_create(),
				'rarityPercent' => '1.2',
				'rarityLabel'   => 'Legendary',
			),
		);
		$content = $this->shortcode($atts, $badges);
		$this->stygen = false;
		return $content;
	}

	public function shortcode($atts=array(), $badges='')
	{
		$atts = \LWS\Adminpanel\Tools\Conveniences::sanitizeAttr(\wp_parse_args($atts, $this->defaultArgs()));
		if(!($badges && \is_array($badges)))
			$badges = $this->getbadges($atts);
		// first letter is enough (all | owned)
		$display = \strtolower(\substr($atts['display'], 0, 1));

		$this->enqueueScripts();
		$labels = array(
			'rarity' => __("Rarity", 'woorewards-pro'),
			'unlock' => __("Unlock Date", 'woorewards-pro'),
		);

		if (false === $atts['header'] || ($this->stygen && !$atts['header']))
			$atts['header'] = \lws_get_option('lws_woorewards_badges_widget_message', _x("Here is the list of badges available on this website", "frontend widget", 'woorewards-pro'));
		if( !$this->stygen )
			$atts['header'] = \apply_filters('wpml_translate_single_string', $atts['header'], 'Widgets', "WooRewards - Badges - Title");

		$content = '';
		foreach ($badges as $badge) {
			if ('o' == $display) {
				if (!$badge['unlockDate']) continue; // need a date
			} elseif ('l' == $display) {
				if ($badge['unlockDate']) continue; // need no date
			} elseif ('a' != $display) {
				continue; //  unknown value
			}
			$owned = ($badge['unlockDate'] ? array('lws-owned-badge-container', 'Owned Badge') : array('lws-badge-container', 'Badge'));
			$unlock = ($badge['unlockDate'] ? sprintf("<div class='lwss_selectable lws-badge-date' data-type='Unlock Date'>%s : %s</div>", $labels['unlock'], $badge['unlockDate']->format("Y-m-d H:i:s")) : '');
			$css = $badge['slug'];
			if ($css)
				$css = (' lws-badge-' . esc_attr($css));
			$content .= <<<EOT
	<div class='lwss_selectable {$owned[0]}{$css}' data-type='{$owned[1]}'>
		<div class='.lwss_selectable lws-badge-imgcol' data-type='Image'><img class='lws-badge-img' src='{$badge['thumbnail']}'/></div>
		<div class='.lwss_selectable lws-badge-contentcol' data-type='Content'>
			<div class='.lwss_selectable lws-badge-title' data-type='Title'>{$badge['title']}</div>
			<div class='.lwss_selectable lws-badge-text' data-type='Description'>{$badge['description']}</div>
			<div class='.lwss_selectable lws-badge-extraInfo' data-type='Extra Information'>
				<div class='.lwss_selectable lws-badge-rarity' data-type='Rarity'>{$badge['rarityLabel']} - {$badge['rarityPercent']}%</div>
				{$unlock}
			</div>
		</div>
	</div>
EOT;
		}

		if ($atts['header'] || $this->stygen) {
			$atts['header'] = <<<EOT
		<div class='lwss_selectable lwss_modify lws-wr-badges-header' data-id='lws_woorewards_badges_widget_message' data-type='Header'>
			<span class='lwss_modify_content'>{$atts['header']}</span>
		</div>
EOT;
		}

		return <<<EOT
	<div class='lwss_selectable lws-woorewards-badges-cont' data-type='Main Container'>
		{$atts['header']}
		<div class='lwss_selectable lws-badges-container' data-type='Badges Container'>
			{$content}
		</div>
	</div>
EOT;
	}

	private function getbadges($atts=array())
	{
		$userId = \apply_filters('lws_woorewards_shortcode_current_user_id', \get_current_user_id(), $atts, 'lws_badges');
		$badges = array();
		foreach (\LWS\WOOREWARDS\PRO\Core\Badge::loadBy('', true) as $badge) {
			$rarity_info = $badge->getBadgeRarity();

			$badges[] = array(
				'slug'          => $badge->getSlug(),
				'thumbnail'     => $badge->getThumbnailUrl(),
				'title'         => $badge->getTitle(),
				'description'   => $badge->getMessage(),
				'unlockDate'    => $badge->ownedBy($userId),
				'rarityPercent' => $rarity_info['percentage'],
				'rarityLabel'   => $rarity_info['rarity'],
			);
		}
		return $badges;
	}
}
