<?php

namespace LWS\WOOREWARDS\PRO\Ui\AdminScreens;
// don't call the file directly
if (!defined('ABSPATH')) exit();

class Shortcodes
{
	static function getTab()
	{
		$tab = array(
			'id' 	=> 'shortcodes',
			'title'	=>  __("Shortcodes", 'woorewards-pro'),
			'icon'	=> 'lws-icon-shortcode',
			'vertnav' => true,
			'summary' => 'shortcode',
			'groups' => array(
				'points'      => array(
					'id'      => 'points_shortcodes',
					'title'   => __('Points', 'woorewards-pro'),
					'icon'    => 'lws-icon-chart-bar-32',
					'text'	  => __("In this section, you will find shortcodes you can use to display points related information to your customers", 'woorewards-pro'),
					'extra'   => array(
						'doclink' => \LWS\WOOREWARDS\PRO\DocLinks::get('shortcodes'),
					),
					'fields'  => apply_filters('lws_woorewards_points_shortcodes', array())
				),
				'rewards'     => array(
					'id'      => 'rewards_shortcodes',
					'title'   => __('Rewards and Levels', 'woorewards-pro'),
					'icon'    => 'lws-icon-gift',
					'text'	  => __("In this section, you will find shortcodes related to rewards and levels", 'woorewards-pro'),
					'extra'   => array(
						'doclink' => \LWS\WOOREWARDS\PRO\DocLinks::get('shortcodes')
					),
					'fields'  => apply_filters('lws_woorewards_rewards_shortcodes', array()),
				),
				'woocommerce' =>  array(
					'id'      => 'woocommerce_shortcodes',
					'title'   => __('WooCommerce', 'woorewards-pro'),
					'icon'    => 'lws-icon-cart-2',
					'text'	  => __("In this section, you will find shortcodes for WooCommerce pages", 'woorewards-pro'),
					'extra'   => array(
						'doclink' => \LWS\WOOREWARDS\PRO\DocLinks::get('shortcodes')
					),
					'fields'  => apply_filters('lws_woorewards_woocommerce_shortcodes', array())
				),
				'referral'        => array(
					'id'      => 'referral_shortcodes',
					'title'   => __('Referral', 'woorewards-pro'),
					'icon'    => 'lws-icon-handshake',
					'text'	  => __("In this section, you will find shortcodes to display referral tools", 'woorewards-pro'),
					'extra'   => array(
						'doclink' => \LWS\WOOREWARDS\PRO\DocLinks::get('shortcodes')
					),
					'fields'  => apply_filters('lws_woorewards_referral_shortcodes', array())
				),
				'users'        => array(
					'id'      => 'users_shortcodes',
					'title'   => __('Users', 'woorewards-pro'),
					'icon'    => 'lws-icon-users',
					'text'	  => __("In this section, you will find shortcodes to display information to users about their status", 'woorewards-pro'),
					'extra'   => array(
						'doclink' => \LWS\WOOREWARDS\PRO\DocLinks::get('shortcodes')
					),
					'fields'  => apply_filters('lws_woorewards_users_shortcodes', array())
				),
				'badges'        => array(
					'id'      => 'badges_shortcodes',
					'title'   => __('Badges and Achievements', 'woorewards-pro'),
					'icon'    => 'lws-icon-lw_reward',
					'text'	  => __("In this section, you will find shortcodes to display information to users about their status", 'woorewards-pro'),
					'extra'   => array(
						'doclink' => \LWS\WOOREWARDS\PRO\DocLinks::get('shortcodes')
					),
					'fields'  => apply_filters('lws_woorewards_badges_shortcodes', array())
				),
				'advanced'        => array(
					'id'      => 'advanced_shortcodes',
					'title'   => __('Advanced', 'woorewards-pro'),
					'icon'    => 'lws-icon-settings-gear',
					'text'	  => __("In this section, you will find shortcodes dedicated to advanced users", 'woorewards-pro'),
					'extra'   => array(
						'doclink' => \LWS\WOOREWARDS\PRO\DocLinks::get('shortcodes')
					),
					'fields'  => apply_filters('lws_woorewards_advanced_shortcodes', array())
				),
			)
		);
		if (\get_option('lws_woorewards_enable_leaderboard')) {
			$tab['groups']['users']['fields']['leaderboard'] = self::getFieldLeaderBoard();
		}
		return $tab;
	}

	static function getFieldLeaderBoard()
	{
		return array(
			'id' => 'lws_woorewards_sc_leaderboard',
			'title' => __("Leaderboard", 'woorewards-pro'),
			'type' => 'shortcode',
			'extra' => array(
				'shortcode' => "[wr_leaderboard system='system_name' count='15']",
				'description' =>  __("This shortcode displays a leaderboard of your customers for a specific Points and Rewards System", 'woorewards-pro'),
				'options'   => array(
					array(
						'option' => 'system',
						'desc' => __("The points and rewards system for which you want to display the leaderboard. You can find this value in <strong>MyRewards → points and rewards systems</strong>, in the <b>Shortcode Attribute</b> column.", 'woorewards-pro'),
					),
					array(
						'option' => 'count',
						'desc' => __("(Optional) The number of rows displayed. Default is 15.", 'woorewards-pro'),
					),
					array(
						'option' => 'columns',
						'desc' => array(
							array(
								'tag' => 'p', 'join' => '<br/>',
								__("(Optional) The Columns to display (comma separated). <b>The order in which you specify the columns will be the grid columns order</b>.", 'woorewards-pro'),
								__(" If not specified, the leaderboard will display the rank, user nickname and points total of the users.", 'woorewards-pro'),
								__(" Here are the different options available :", 'woorewards-pro'),
							),
							array(
								'tag' => 'ul',
								array(
									"rank :",
									__("The user's rank in the leadeboard.", 'woorewards-pro'),
								), array(
									"user_nickname :",
									__("The user's display name.", 'woorewards-pro'),
								), array(
									"points :",
									__("The user's points in the points and rewards system.", 'woorewards-pro'),
								), array(
									"badges :",
									__("The badges owned by the customer. This will display the badges images and their titles on mouseover.", 'woorewards-pro'),
								), array(
									"last_badge :",
									__("The last badge earned by the customer. This will display the badge image and its title on mouseover.", 'woorewards-pro'),
								), array(
									"achievements :",
									__("The achievements unlocked by the customer. This will display the unlocked badges images and their titles on mouseover.", 'woorewards-pro'),
								), array(
									"user_title :",
									__("Displays the user title if he earned one.", 'woorewards-pro'),
								), array(
									"title_date :",
									__("Displays when the user earned his/her title.", 'woorewards-pro'),
								)
							)
						),
					),
					array(
						'option' => 'columns_headers',
						'desc' => __("(Optional) The column headers (comma separated). <b>Must be specified if you specified the columns option</b>. The headers must respect the same order than the ones of the previous option.", 'woorewards-pro'),
					),
					array(
						'option' => 'badge_ids',
						'desc' => __("(Optional) Restriction to specific badges (comma separated). By default, all badges can be displayed in the relevant columns. You can restrict that to a specific list of badges.", 'woorewards-pro'),
					),
					array(
						'option' => 'achievement_ids',
						'desc' => __("(Optional) Restriction to specific achievements (comma separated). By default, all achievements can be displayed in the relevant columns. You can restrict that to a specific list of achievements.", 'woorewards-pro'),
					),
				),
			)
		);
	}

}
