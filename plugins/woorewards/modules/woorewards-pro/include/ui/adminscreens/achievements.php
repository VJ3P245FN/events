<?php
namespace LWS\WOOREWARDS\PRO\Ui\AdminScreens;
// don't call the file directly
if (!defined('ABSPATH')) exit();

class Achievements
{
	static function getTab()
	{
		require_once LWS_WOOREWARDS_PRO_INCLUDES . '/ui/editlists/achievements.php';
		$editlist = \lws_editlist(
			\LWS\WOOREWARDS\PRO\Ui\Editlists\Achievements::SLUG,
			\LWS\WOOREWARDS\PRO\Ui\Editlists\Achievements::ROW_ID,
			new \LWS\WOOREWARDS\PRO\Ui\Editlists\Achievements(),
			\LWS\Adminpanel\EditList::MDA,
			\apply_filters('lws_woorewards_achievements_list_filters', array())
		);

		$tab = array(
			'id'	=> 'ba_settings',
			'title'	=>  __("Achievements", 'woorewards-pro'),
			'icon'	=> 'lws-icon-trophy',
			'vertnav' => true,
			'groups' => \array_merge(
				array(
					'ba_features' => array(
						'id' 	=> 'ba_features',
						'icon'	=> 'lws-icon-settings-gear',
						'class'	=> 'half',
						'title'	=> __("Badges & Achievements Features", 'woorewards-pro'),
						'text' 	=> __("Enable badges and achievements to add all their specific features to MyRewards.", 'woorewards-pro') .
							__("Achievements can be used to earn badges. Badges can be used to unlock rewards.", 'woorewards-pro'),
						'extra' => array('doclink' => \LWS\WOOREWARDS\PRO\DocLinks::get('badges')),
						'fields' => array(
							'badge-enable' => array(
								'id'    => 'lws_woorewards_manage_badge_enable',
								'title' => __("Enable Badge & Achievements", 'woorewards-pro'),
								'type'  => 'box',
								'extra' => array(
									'default' => 'on',
									'layout' => 'toggle',
									'help' => __("Enable/disable badge menu, rewards, earning methods and achievements system.", 'woorewards-pro')
								)
							)
						)
					),
					'badges' => array(
						'id' 	=> 'badges',
						'icon'	=> 'lws-icon-cockade',
						'class'	=> 'half',
						'title'	=> __("Badges Management", 'woorewards-pro'),
						'text' 	=> __("Badges are a special kind of wordpress posts. For that reason, they're handled in their own interface.", 'woorewards-pro') .
							__("Please follow the link below to add, edit or delete badges.", 'woorewards-pro'),
						'extra' => array('doclink' => \LWS\WOOREWARDS\PRO\DocLinks::get('badges')),
						'fields' => array(
							'badge-link' => array(
								'id'    => 'lws_woorewards_badge_link',
								'title' => __("Badges Management", 'woorewards-pro'),
								'type'  => 'custom',
								'extra' => array(
									'gizmo'   => true,
									'content' => sprintf("<a href='%s' target='_blank'>%s</a>", \esc_attr(\admin_url('edit.php?post_type=lws_badge')), __("Manage your badges", 'woorewards-pro')),
									'help'    => __("You can see and edit the badges by clicking the link down below.", 'woorewards-pro'),
								)
							)
						)
					),
					'achievements' => array(
						'id'    => 'achievements',
						'title' => __("Achievements", 'woorewards-pro'),
						'icon'	=> 'lws-icon-trophy',
						'editlist' => $editlist,
						'text' => __("After creating some badges, you can set up achievements that customers need to achieve in order to earn the badges.", 'woorewards-pro'),
						'extra'    => array('doclink' => \LWS\WOOREWARDS\PRO\DocLinks::get('achievements')),
						'function' => function ()
						{
							\wp_enqueue_style('lws-wr-achievements', LWS_WOOREWARDS_PRO_CSS . '/editlists/achievements.min.css', array(), LWS_WOOREWARDS_PRO_VERSION);
						}
					),
				),
				self::getAppearances()
			)
		);
		return $tab;
	}

	static public function getAppearances()
	{
		require_once LWS_WOOREWARDS_PRO_INCLUDES . '/ui/editlists/rarity.php';
		return array(
			'badge' => array(
				'id'     => 'badge',
				'icon'   => 'lws-icon-window-add',
				'title'  => __("Badge Popup", 'woorewards-pro'),
				'text'   => __("Style the popup that will be displayed to customers when they earn a new badge.", 'woorewards-pro'),
				'extra'  => array('doclink' => \LWS\WOOREWARDS\PRO\DocLinks::get('badges')),
				'fields' => array(
					'enable' => array(
						'id'    => 'lws_woorewards_achievement_popup_enabled',
						'title' => __("Enabled", 'woorewards-pro'),
						'type'  => 'box',
						'extra' => array(
							'help'    => __("Show or not a popup when an achievement is fulfilled.", 'woorewards-pro'),
							'default' => 'on',
						)
					),
					'stygen' => array(
						'id'    => 'lws_wr_badge_style',
						'title' => '',
						'type'  => 'stygen',
						'extra' => array(
							'purpose'  => 'action',
							'template' => 'lws_wr_badge_style',
							'html'     => false,
							'css'      => LWS_WOOREWARDS_PRO_CSS . '/templates/badge.css',
						)
					)
				)
			),
			'rarity' => array(
				'id'       => 'lws_woorewards_rarity_levels',
				'icon'     => 'lws-icon-g-chart',
				'title'    => __("Badges Rarity Levels", 'woorewards-pro'),
				'text'     => __("Define the rarity levels of Badges.", 'woorewards-pro') . "<br/>" .
					__("The percentage value is the max percentage of users owning the badge to get the corresponding rarity.", 'woorewards-pro'),
				'extra'    => array('doclink' => \LWS\WOOREWARDS\PRO\DocLinks::get('badges')),
				'editlist' => \lws_editlist(
					'Rarity',
					\LWS\WOOREWARDS\PRO\Ui\Editlists\BadgeRarity::ROW_ID,
					new \LWS\WOOREWARDS\PRO\Ui\Editlists\BadgeRarity(),
					\LWS\Adminpanel\EditList::MDA
				)->setPageDisplay(false)->setCssClass('lws-rarity-editlist')->setRepeatHead(false),
				'function' => function () {
					\wp_enqueue_style('lws-wre-pro-srarity', LWS_WOOREWARDS_PRO_CSS . '/rarity.css', array('lws-admin-controls'), LWS_WOOREWARDS_PRO_VERSION);
				}
			),
		);
	}
}