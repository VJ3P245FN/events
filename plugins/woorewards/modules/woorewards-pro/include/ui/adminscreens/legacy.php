<?php

namespace LWS\WOOREWARDS\PRO\Ui\AdminScreens;
// don't call the file directly
if (!defined('ABSPATH')) exit();

class Legacy
{
	static function getTab(&$page)
	{
		$tab = array(
			'id'      => 'legacy_appearance',
			'title'   => __("Legacy", 'woorewards-pro'),
			'icon'    => 'lws-icon-components',
			'vertnav' => true,
			'groups'  => array(),
		);

		if (\LWS\WOOREWARDS\Conveniences::instance()->isLegacyShown('4.9.8')) {
			$tab['groups']['sp_referral_widget']   = self::getGroupReferralWidget();
			$tab['groups']['productpointspreview'] = self::getGroupProductPointsPreview();
			$tab['groups']['shoppointspreview']    = self::getGroupShopPointsPreview();
		}

		if (\LWS\WOOREWARDS\Conveniences::instance()->isLegacyShown('4.8.0')) {
			$tab['groups']['cartpointspreview'] = self::getGroupCartPointsPreview();
		}

		$shortcodes = array();
		if (\LWS\WOOREWARDS\Conveniences::instance()->isLegacyShown('4.7.0')) {
			$tab['groups']['cartcouponsview'] = self::getCartCouponsView();
			$tab['groups']['pointsoncart']    = $page['tabs']['legacy']['groups']['pointsoncart'];
			$tab['groups']['showpoints']      = $page['tabs']['legacy']['groups']['showpoints'];
			$tab['groups']['coupons']         = self::getGroupCoupons();
			$tab['groups']['stdrewards']      = self::getGroupStandardRewards();
			$tab['groups']['levrewards']      = self::getGroupLevelingRewards();
			$tab['groups']['events']          = self::getGroupEvents();
			$tab['groups']['badges']          = self::getGroupBadges();
			$tab['groups']['achievements']    = self::getGroupAchievements();
			$shortcodes                       = self::getShortcodesFields($shortcodes);
		}

		$shortcodes = \apply_filters('lws_woorewards_legacy_shortcodes', $shortcodes);
		$shortGroup = self::getOrCreateShorcodeGroup($page);
		if ($shortcodes || $shortGroup['fields']) {
			$shortGroup['fields'] = array_merge($shortGroup['fields'], $shortcodes);
			$tab['groups']['shortcodes'] = $shortGroup;
		}

		$legacyText = sprintf('<strong>%s</strong>',
			__("This is a legacy option and will stop being supported at some point. Please use other provided tools and shortcodes instead", 'woorewards-pro')
		);
		foreach ($tab['groups'] as &$group) {
			if (isset($group['text'])) {
				if (\is_array($group['text']))
					\array_unshift($group['text'], $legacyText);
				else
					$group['text'] = ($legacyText . '</br/>' . $group['text']);
			}
		}
		return $tab;
	}

	protected static function getCartCouponsView()
	{
		return array(
			'id'     => 'cartcouponsview',
			'icon'   => 'lws-icon-coupon',
			'title'  => __("Cart Coupons", 'woorewards-pro'),
			'text'   => __("Show to the customer his available coupons. That block stay hidden if customer doesn't have coupons.", 'woorewards-pro'),
			'fields' => array(
				array(
					'id'    => 'lws_woorewards_apply_coupon_by_reload',
					'title' => __("Reload page to apply coupon", 'woorewards-pro'),
					'type'  => 'box',
					'extra' => array(
						'class'    => 'lws_checkbox',
						'tooltips' => __("Using a custom cart widget can prevent the default javascript behavior. In that case, check that option to force a page reload when customer apply a coupon.", 'woorewards-pro'),
					),
				),
				array(
					'id'    => 'lws_woorewards_cart_collaterals_coupons', // legacy id: coupon view position
					'title' => __("Location", 'woorewards-pro'),
					'type'  => 'lacselect',
					'extra' => array(
						'maxwidth' => '400px',
						'default'  => 'not_displayed',
						'mode'     => 'select',
						'notnull'  => true,
						'source'   => array(
							array('value' => 'not_displayed', 'label' => __("Not displayed at all", 'woorewards-pro')),
							array('value' => 'middle_of_cart', 'label' => __("Between products and totals", 'woorewards-pro')),
							array('value' => 'cart_collaterals', 'label' => __("Left of cart totals", 'woorewards-pro')),
							array('value' => 'on', 'label' => __("Bottom of the cart page", 'woorewards-pro')),
						)
					)
				),
				array(
					'id'    => 'lws_wre_cart_coupons_view',
					'type'  => 'stygen',
					'extra' => array(
						'purpose'  => 'filter',
						'template' => 'cartcouponsview',
						'html'     => false,
						'css'      => LWS_WOOREWARDS_PRO_CSS . '/templates/cartcouponsview.css',
						'subids'   => array(
							'lws_woorewards_title_cart_coupons_view' => "WooRewards - Coupons - Title",
							'lws_woorewards_cart_coupons_button' => "WooRewards - Coupons - Button",
						)
					)
				),
			)
		);
	}

	protected static function getGroupCoupons()
	{
		return array(
			'id'     => 'coupons',
			'title'  => __('Owned Coupons', 'woorewards-pro'),
			'icon'   => 'lws-icon-coupon',
			'text'   => __("In this Widget, customers can see the WooCommerce coupons they own.", 'woorewards-pro'),
			'fields' => array(
				'clunconnected' => array(
					'id'    => 'lws_wooreward_wc_coupons_nouser',
					'title' => __("Text displayed if user not connected", 'woorewards-pro'),
					'type'  => 'text',
					'extra' => array(
						'size'        => '50',
						'placeholder' => __("Please log in to see the coupons you have", 'woorewards-pro'),
					)
				),
				'ifemptycoupon' => array(
					'id'    => 'lws_wooreward_wc_coupons_empty',
					'title' => __("Text displayed if no coupon available", 'woorewards-pro'),
					'type'  => 'text',
					'extra' => array(
						'size'        => '50',
						'placeholder' => __("No coupon available", 'woorewards-pro'),
					)
				),
				'couponslist' => array(
					'id'    => 'lws_woorewards_wc_coupons_template',
					'type'  => 'stygen',
					'extra' => array(
						'purpose'  => 'filter',
						'template' => 'wc_shop_coupon',
						'html'   => false,
						'css'    => LWS_WOOREWARDS_PRO_CSS . '/templates/coupons.css',
						'subids' => array('lws_woorewards_wc_coupons_template_head' => "WooRewards - Coupons Widget - Header")
					)
				),
			)
		);
	}

	protected static function getGroupStandardRewards()
	{
		return array(
			'id'     => 'stdrewards',
			'title'  => __('Standard System Rewards', 'woorewards-pro'),
			'icon'   => 'lws-icon-present',
			'text'   => array(
				'join' => '<br/>',
				__("In this Widget, customers can see the Rewards they can unlock in a Standard points and rewards system.", 'woorewards-pro'),
				sprintf(__("If you change the 'Reward Cost' text, use %s to display the reward cost (eg : 100 Points)", 'woorewards-pro'), "<span style='font-weight:bold;color:#366'>[rw_cost]</span>"),
				sprintf(__("If you change the 'Need More Points' text, use %s to display the reward cost and %s to display the points still needed", 'woorewards-pro'), "<span style='font-weight:bold;color:#366'>[rw_cost]</span>", "<span style='font-weight:bold;color:#366'>[rw_more]</span>"),
			),
			'extra'  => array('doclink' => \LWS\WOOREWARDS\PRO\DocLinks::get('rewards')),
			'fields' => array(
				'stdusegrid' => array(
					'id'    => 'lws_woorewards_rewards_use_grid',
					'title' => __("Use grid display instead of table display", 'woorewards-pro'),
					'type'  => 'box',
					'extra' => array(
						'default' => 'on',
						'class'   => 'lws_checkbox',
						'help'    => __("Until MyRewards 3.4, this widget used html tables to display rewards.", 'woorewards-pro') . "<br/>"
						. __("If you've set up the widget before that version, checking that box will force you to style it again", 'woorewards-pro'),
					)
				),
				'stdimagesize' => array(
					'id'    => 'lws_woorewards_rewards_image_size',
					'title' => __("Image Size", 'woorewards-pro'),
					'type'  => 'lacselect',
					'extra' => array(
						'id'       => 'lws_woorewards_rewards_image_size',
						'maxwidth' => '500px',
						'ajax'     => 'lws_adminpanel_get_media_sizes',
						'mode'     => 'select',
						'help'     => __("Default size should be lws_wr_thumbnail. If you change css to enlarge the images, you should set a larger default image size preventing you from getting blurred images.", 'woorewards-pro'),
					)
				),
				'stdrewards' => array(
					'id'    => 'lws_woorewards_rewards_template',
					'type'  => 'stygen',
					'extra' => array(
						'purpose'  => 'filter',
						'template' => 'rewards_template',
						'html'     => false,
						'css'      => LWS_WOOREWARDS_PRO_CSS . '/templates/' . (empty(\get_option('lws_woorewards_rewards_use_grid', 'on')) ? 'rewards.css' : 'gridrewards.css'),
						'subids'   => array(
							'lws_woorewards_rewards_widget_unlock' => "WooRewards - Rewards Widget - Unlock Button",
							'lws_woorewards_rewards_widget_locked' => "WooRewards - Rewards Widget - Locked Button",
							'lws_woorewards_rewards_widget_cost' => "WooRewards - Rewards Widget - Reward Cost",
							'lws_woorewards_rewards_widget_more' => "WooRewards - Rewards Widget - More Points Needed",
						),
					)
				),
			)
		);
	}

	protected static function getGroupLevelingRewards()
	{
		return array(
			'id'     => 'levrewards',
			'title'  => __('Leveling System Rewards', 'woorewards-pro'),
			'icon'   => 'lws-icon-g-chart',
			'text'   => __("In this Widget, customers can see the Rewards they can win in a Levelling points and rewards system.", 'woorewards-pro'),
			'extra'  => array('doclink' => \LWS\WOOREWARDS\PRO\DocLinks::get('rewards')),
			'fields' => array(
				'levrewards' => array(
					'id'    => 'lws_woorewards_loyalties_template',
					'type'  => 'stygen',
					'extra' => array(
						'purpose'  => 'filter',
						'template' => 'loyalties_template',
						'html'     => false,
						'css'      => LWS_WOOREWARDS_PRO_CSS . '/templates/loyalties.css',
						'help'     => __("In this Widget, customers can see the Rewards they can win in a Levelling points and rewards system.", 'woorewards-pro') . "<br/>"
					)
				),
			)
		);
	}

	protected static function getGroupEvents()
	{
		return array(
			'id'     => 'events',
			'title'  => __('Earning Points', 'woorewards-pro'),
			'icon'   => 'lws-icon-trend-up',
			'text'   => __("In this Widget, customers can see what they need to do in order to earn points", 'woorewards-pro'),
			'extra'  => array('doclink' => \LWS\WOOREWARDS\PRO\DocLinks::get('points')),
			'fields' => array(
				'events' => array(
					'id'    => 'lws_woorewards_events_template',
					'type'  => 'stygen',
					'extra' => array(
						'purpose'  => 'filter',
						'template' => 'events_template',
						'html'     => false,
						'css'      => LWS_WOOREWARDS_PRO_CSS . '/templates/events.css',
						'subids'   => array(
							'lws_woorewards_events_widget_message' => "WooRewards - Earning methods - Header",
							'lws_woorewards_events_widget_text' => "WooRewards - Earning methods - Description",
						),
					)
				),
			)
		);
	}

	protected static function getGroupBadges()
	{
		return array(
			'id'     => 'badges',
			'icon'   => 'lws-icon-cockade',
			'title'  => __("Badges", 'woorewards-pro'),
			'text'   => __("In this Widget, customers can see the badges available and the ones they own", 'woorewards-pro'),
			'extra'  => array('doclink' => \LWS\WOOREWARDS\PRO\DocLinks::get('badges')),
			'fields' => array(
				'badges' => array(
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
				),
			)
		);
	}

	protected static function getGroupAchievements()
	{
		return array(
			'id'     => 'achievements',
			'icon'   => 'lws-icon-trophy',
			'title'  => __("Achievements", 'woorewards-pro'),
			'text'   => __("In this Widget, customers can see the achievements and their progress", 'woorewards-pro'),
			'extra'  => array('doclink' => \LWS\WOOREWARDS\PRO\DocLinks::get('achievements')),
			'fields' => array(
				'achievements' => array(
					'id'   => 'lws_woorewards_achievements_template',
					'type' => 'stygen',
					'extra' => array(
						'purpose'  => 'filter',
						'template' => 'achievements_template',
						'html'     => false,
						'css'      => LWS_WOOREWARDS_PRO_CSS . '/templates/achievements.css',
						'subids'   => array(
							'lws_woorewards_achievements_widget_message' => "WooRewards - Achievements - Title",
						),
					)
				),
			)
		);
	}

	protected static function getGroupCartPointsPreview()
	{
		return array(
			'id'     => 'cartpointspreview',
			'icon'   => 'lws-icon-cart-2',
			'title'  => __("Cart Page Preview", 'woorewards-pro'),
			'text'   => __("Show points that a customer could earn with his current cart. That block stay hidden if customer does not earn points.", 'woorewards-pro'),
			'fields' => array(
				array(
					'id'    => 'lws_woorewards_cart_potential_position',
					'title' => __("Location", 'woorewards-pro'),
					'type'  => 'lacselect',
					'extra' => array(
						'maxwidth' => '400px',
						'default'  => 'not_displayed',
						'mode'     => 'select',
						'notnull'  => true,
						'source'   => array(
							array('value' => 'not_displayed', 'label' => __("Not displayed at all", 'woorewards-pro')),
							array('value' => 'middle_of_cart', 'label' => __("Between products and totals", 'woorewards-pro')),
							array('value' => 'cart_collaterals', 'label' => __("Left of cart totals", 'woorewards-pro')),
							array('value' => 'bottom_of_cart', 'label' => __("Bottom of the cart page", 'woorewards-pro')),
						)
					)
				),
				array(
					'id'    => 'lws_woorewards_cpp_show_detail',
					'title' => __("Show Detail", 'woorewards-pro'),
					'type'  => 'box',
					'extra' => array(
						'class'    => 'lws_checkbox',
						'tooltips' => __("Check this option if you want to show the methods to earn points detail", 'woorewards-pro'),
					)
				),
				array(
					'id'    => 'lws_woorewards_cpp_unlogged_text',
					'title' => __("Text for unlogged customers", 'woorewards-pro'),
					'type'  => 'text',
					'extra' => array(
						'size'     => '50',
						'wpml'     => "WooRewards - Cart Points Preview - Unlogged Text",
						'tooltips' => __("Fill this if you want to show a text for unlogged customers", 'woorewards-pro'),
					)
				),
				array(
					'id'    => 'lws_woorewards_cpp_show_unlogged',
					'title' => __("Show for unlogged customers", 'woorewards-pro'),
					'type'  => 'box',
					'extra' => array(
						'class'    => 'lws_checkbox',
						'tooltips' => __("Check this option if you want to show potentially earned points to unlogged customers", 'woorewards-pro'),
					)
				),
				array(
					'id'    => 'lws_woorewards_cart_potential_pool',
					'title' => __("Points and rewards systems", 'woorewards-pro'),
					'type'  => 'lacchecklist',
					'extra' => array(
						'ajax'     => 'lws_woorewards_pool_list',
						'tooltips' => __("If you select several systems, they will be displayed separately, one after the other.", 'woorewards-pro'),
					)
				),
				array(
					'id'    => 'lws_wre_cart_points_preview',
					'type'  => 'stygen',
					'extra' => array(
						'purpose'  => 'filter',
						'template' => 'cartpointspreview',
						'html'     => false,
						'css'      => LWS_WOOREWARDS_PRO_CSS . '/templates/cartpointspreview.css',
						'subids'   => array(
							'lws_woorewards_title_cpp' => "WooRewards - Cart Point Preview - Title",
						)
					)
				)
			)
		);
	}

	static protected function getOrCreateShorcodeGroup($page)
	{
		$shortcodes = false;
		if (isset($page['tabs']['legacy']) && isset($page['tabs']['legacy']['groups']['shortcodes'])) {
			$shortcodes = $page['tabs']['legacy']['groups']['shortcodes'];
		}
		if (!$shortcodes) {
			$shortcodes = array(
				'id'     => 'shortcodes',
				'title'  => __('Shortcodes', 'woorewards-pro'),
				'icon'   => 'lws-icon-shortcode',
				'text'   => __("These shortcodes are deprecated and are kept here for compatibility. Try to replace them with other shortcodes", 'woorewards-pro'),
				'fields' => array(),
			);
		}
		return $shortcodes;
	}

	static public function getShortcodesFields($fields)
	{
		$fields['user_loyalties'] = array(
			'id'    => 'lws_woorewards_sc_user_loyalties',
			'title' => __("Loyalty and Rewards", 'woorewards-pro'),
			'type'  => 'shortcode',
			'extra' => array(
				'shortcode'   => '[wr_user_loyalties]',
				'description' =>  __("This shortcode shows all loyalty and rewards information visible in WooCommerce's 'My Account' page.", 'woorewards-pro'),
			)
		);
		$fields['cart_coupons'] = array(
			'id'    => 'lws_woorewards_sc_cart_coupons',
			'title' => __("Cart Coupons", 'woorewards-pro'),
			'type'  => 'shortcode',
			'extra' => array(
				'shortcode'   => '[wr_cart_coupons_view]',
				'description' =>  __("This shortcode shows coupons owned by the user and proposes a button to apply them on the cart.", 'woorewards-pro'),
			)
		);
		$fields['showpoints'] = array(
			'id'    => 'lws_woorewards_sc_show_points',
			'title' => __("Display Points", 'woorewards-pro'),
			'type'  => 'shortcode',
			'extra' => array(
				'shortcode'   => '[wr_show_points system="set the name of your system here" force="true" title="your title" more_details_url="more details button url"]',
				'description' =>  __("This shortcode shows to customers the points they have on a points and rewards system.", 'woorewards-pro'),
				'flags'       => array('current_user_id'),
				'style_url'   => \esc_attr(\add_query_arg(array('page' => LWS_WOOREWARDS_PAGE . '.appearance', 'tab' => 'sty_widgets'), \admin_url('admin.php'))) . '#lws_group_targetable_showpoints',
				'options'     => array(
					array(
						'option' => 'system',
						'desc'   => __("The points and rewards system you want to display. You can find this value in <strong>MyRewards → Points and Rewards</strong>, in the <b>Shortcode Attribute</b> column. If you don’t set this value, nothing will be displayed.", 'woorewards-pro'),
					),
					array(
						'option' => 'force',
						'desc'   => __("(Optional) If set, the points will be shown even if the user currently doesn’t have access to the points and rewards system.", 'woorewards-pro'),
					),
					array(
						'option' => 'title',
						'desc'   => __("(Optional) The text displayed before the points.", 'woorewards-pro'),
					),
					array(
						'option' => 'more_details_url',
						'desc'   => __("(Optional) An url linking to a page with more details on the points and rewards systems.", 'woorewards-pro'),
					),
					array(
						'option' => 'show_currency',
						'desc'   => __("(Optional) If set, the number of points displayed will show the points currency.", 'woorewards-pro'),
					),
				),
			)
		);
		$fields['coupons'] = array(
			'id'    => 'lws_woorewards_sc_shop_coupons',
			'title' => __("Owned Coupons", 'woorewards-pro'),
			'type'  => 'shortcode',
			'extra' => array(
				'shortcode'   => '[wr_shop_coupons header=”Here is a list of your coupons”]',
				'description' =>  __("This shortcode shows to customers the woocommerce coupons they currently have.", 'woorewards-pro'),
				'style_url'   => \esc_attr(\add_query_arg(array('page' => LWS_WOOREWARDS_PAGE . '.appearance', 'tab' => 'sty_widgets'), \admin_url('admin.php'))) . '#lws_group_targetable_coupons',
				'options'     => array(
					array(
						'option' => 'header',
						'desc'   => __("The text displayed before the coupons.", 'woorewards-pro'),
					),
				),
			)
		);
		$fields['showrewards'] = array(
			'id' => 'lws_woorewards_sc_show_rewards',
			'title' => __("Rewards", 'woorewards-pro'),
			'type' => 'shortcode',
			'extra' => array(
				'shortcode' => '[wr_show_rewards system="set the name of your system here" shared="true" force="true" title="your title" granted="all|only|excluded"]',
				'description' =>  __("This shortcode shows to customers the rewards they can earn in a points and rewards system.", 'woorewards-pro'),
				'options'   => array(
					array(
						'option' => 'system',
						'desc' => __("The points and rewards system you want to display. You can find this value in <strong>MyRewards → Points and Rewards</strong>, in the <b>Shortcode Attribute</b> column. If you don’t set this value, nothing will be displayed.", 'woorewards-pro'),
					),
					array(
						'option' => 'title',
						'desc' => __("The text displayed before the rewards.", 'woorewards-pro'),
					),
					array(
						'option' => 'shared',
						'desc' => __("If systems share the same points pool, you can show the rewards of all shared systems together. <strong>Warning</strong> : This only works if systems use the same type (Standard or Leveling).", 'woorewards-pro'),
					),
					array(
						'option' => 'force',
						'desc' => __("If set, the points will be shown even if the user currently doesn’t have access to the points and rewards system.", 'woorewards-pro'),
					),
					array(
						'option' => 'granted',
						'desc' => array(
							__("Select the rewards you want to show to customers : ", 'woorewards-pro'),
							array('tag' => 'ul',
								array('all',
									__("All rewards of the selected points and rewards system", 'woorewards-pro'),
								),
								array('only',
									__("Only for logged users – Show rewards they can unlock with their points", 'woorewards-pro'),
								),
								array('excluded',
									__("Only for logged users – Show rewards for which users don’t have enough points", 'woorewards-pro'),
								),
							),
						),
					),
				),
			)
		);
		$fields['earning'] = array(
			'id'    => 'lws_woorewards_sc_earning_points',
			'title' => __("Actions to earn points", 'woorewards-pro'),
			'type'  => 'shortcode',
			'extra' => array(
				'shortcode'   => '[wr_events system="set the name of your system here" shared="true" force="true" header="your header" text="your custom text"]',
				'description' =>  __("This shortcode shows to customers the actions to perform in order to earn points.", 'woorewards-pro'),
				'options'     => array(
					array(
						'option' => 'system',
						'desc'   => __("The points and rewards system you want to display. You can find this value in <strong>MyRewards → Points and Rewards</strong>, in the <b>Shortcode Attribute</b> column. If you don’t set this value, nothing will be displayed.", 'woorewards-pro'),
					),
					array(
						'option' => 'shared',
						'desc'   => __("If systems share the same points pool, you can show the rewards of all shared systems together. <strong>Warning</strong> : This only works if systems use the same type (Standard or Leveling).", 'woorewards-pro'),
					),
					array(
						'option' => 'force',
						'desc'   => __("If set, the points will be shown even if the user currently doesn’t have access to the points and rewards system.", 'woorewards-pro'),
					),
					array(
						'option' => 'header',
						'desc'   => __(" The text displayed over the earning methods list.", 'woorewards-pro'),
					),
					array(
						'option' => 'text',
						'desc'   => __("This text is set to explain to customers what the information displayed is about.", 'woorewards-pro'),
					),
				),
			)
		);
		return $fields;
	}

	static public function getGroupReferralWidget()
	{
		return array(
			'id' => 'referral',
			'icon'	=> 'lws-icon-url',
			'title' => __("Referral Link", 'woorewards-pro'),
			'text' => __("In this Widget, customers get a referral link they can share.", 'woorewards-pro'),
			'fields' => array(
				'display' => array(
					'id'    => 'lws_woorewards_sponsorship_link_display',
					'title' => __("Default Display", 'woorewards-pro'),
					'type'  => 'lacselect',
					'extra' => array(
						'mode' => 'select',
						'source' => array(
							array('value' => 'link',	'label' => __('Url Link', 'woorewards-pro')),
							array('value' => 'qrcode',	'label' => __('QR Code', 'woorewards-pro')),
							array('value' => 'both',	'label' => __('Both', 'woorewards-pro')),
						),
					)
				),
				'page' => array(
					'id'    => 'lws_woorewards_sponsorship_link_page',
					'title' => __("Destination Page", 'woorewards-pro'),
					'type'  => 'lacselect',
					'extra' => array(
						'help' => __("Select the default destination of the referral link. If left empty, it will redirect to the same page it's placed", 'woorewards-pro'),
						'predefined' => 'page',
					)
				),
				'tinify' => array(
					'id'    => 'lws_woorewards_sponsorship_tinify_enabled',
					'title' => __("Try to shorten the referral URL", 'woorewards-pro'),
					'type'  => 'box',
					'extra' => array(
						'help' => __('Disable that feature if you encounter plugin conflicts or redirection problems. Disable that feature makes bigger and less readable QR codes.', 'woorewards-pro'),
						'class' => 'lws_checkbox',
						'default' => '',
						'id' => 'lws_woorewards_sponsorship_tinify_enabled',
					)
				),
				'tiny' => array(
					'id'    => 'lws_woorewards_sponsorship_short_url',
					'title' => __("Alternative Short Site URL", 'woorewards-pro'),
					'type'  => 'text',
					'extra' => array(
						'help' => __('To make the QR-Code as simple as possible, you can specify a shorter version of your site URL here that will be used as base for the image generation.', 'woorewards-pro'),
						'placeholder' => \site_url(),
					),
					'require' => array('selector' => '#lws_woorewards_sponsorship_tinify_enabled', 'value' => 'on'),
				),
				array(
					'id' => 'lws_woorewards_referral_template',
					'type' => 'stygen',
					'extra' => array(
						'purpose' => 'filter',
						'template' => 'wr_referral',
						'html' => false,
						'css' => LWS_WOOREWARDS_PRO_CSS . '/templates/referral.css',
						'subids' => array(
							'lws_woorewards_referral_widget_message' => "WooRewards - Referral Widget - Header",
						)
					)
				)
			)
		);
	}

	protected static function getGroupProductPointsPreview()
	{
		return array(
			'id' => 'productpointspreview',
			'icon'	=> 'lws-icon-barcode',
			'title' => __("Product Page Preview", 'woorewards-pro'),
			'text' => __("Shows points that a customer could earn purchasing a given product. That block stay hidden if the product produces no points.", 'woorewards-pro'),
			'extra'    => array('doclink' => 'https://plugins.longwatchstudio.com/docs/woorewards-4/woocommerce-integration/product-earned-points/'),
			'fields' => array(
				array(
					'id' => 'lws_woorewards_product_potential_position',
					'title' => __("Location", 'woorewards-pro'),
					'type'  => 'lacselect',
					'extra' => array(
						'maxwidth' => '400px',
						'default'  => 'not_displayed',
						'mode'     => 'select',
						'notnull'  => true,
						'source'   => array(
							array('value' => 'not_displayed', 'label' => __("Not displayed at all", 'woorewards-pro')),
							array('value' => 'before_summary', 'label' => __("Before product summary", 'woorewards-pro')),
							array('value' => 'inside_summary', 'label' => __("Inside product summary", 'woorewards-pro')),
							array('value' => 'after_form', 'label' => __("After product form", 'woorewards-pro')),
							array('value' => 'after_summary', 'label' => __("After product summary", 'woorewards-pro')),
						)
					)
				),
				array(
					'id' => 'lws_woorewards_ppp_unlogged_text',
					'title' => __("Text for unlogged customers", 'woorewards-pro'),
					'type' => 'text',
					'extra' => array(
						'size' => '50',
						'wpml' => "WooRewards - Product Points Preview - Unlogged Text",
						'tooltips' => __("Fill this if you want to show a text for unlogged customers", 'woorewards-pro'),
					)
				),
				array(
					'id' => 'lws_woorewards_ppp_show_unlogged',
					'title' => __("Show for unlogged customers", 'woorewards-pro'),
					'type' => 'box',
					'extra' => array(
						'default' => 'on',
						'layout' => 'toggle',
						'tooltips' => __("Check this option if you want to show potentially earned points to unlogged customers", 'woorewards-pro'),
					)
				),
				array(
					'id' => 'lws_woorewards_product_potential_pool',
					'title' => __("Points and rewards systems", 'woorewards-pro'),
					'type' => 'lacchecklist',
					'extra' => array(
						'ajax' => 'lws_woorewards_pool_list',
						'tooltips' => __("If you select several systems, they will be displayed separately, one after the other.", 'woorewards-pro'),
					)
				),
				array(
					'id' => 'lws_wre_product_points_preview',
					'type' => 'stygen',
					'extra' => array(
						'purpose' => 'filter',
						'template' => 'productpointspreview',
						'html' => false,
						'css' => LWS_WOOREWARDS_PRO_CSS . '/templates/productpointspreview.css',
						'subids' => array(
							'lws_woorewards_label_ppp' => "WooRewards - Product Points Preview - Title",
						)
					)
				)
			)
		);
	}

	protected static function getGroupShopPointsPreview()
	{
		return array(
			'id' => 'shoppointspreview',
			'icon'	=> 'lws-icon-shopping-tag',
			'title' => __("Shop Page Preview", 'woorewards-pro'),
			'text' => __("Shows points that a customer could earn purchasing products on a products list page. That block stay hidden if customers can't earn points with products.", 'woorewards-pro'),
			'fields' => array(
				array(
					'id' => 'lws_woorewards_product_loop_points_preview',
					'title' => __("Enable", 'woorewards-pro'),
					'type' => 'box',
					'extra' => array(
						'default' => '',
						'layout' => 'toggle',
						'tooltips' => __("In Shop page, points preview is appended for each item in the loop. Warning ! It can be a heavy process if your lists shows many products.", 'woorewards-pro'),
					)
				),
				array(
					'id' => 'lws_woorewards_product_loop_points_preview_pattern',
					'title' => __("Pattern", 'woorewards-pro'),
					'type' => 'text',
					'extra' => array(
						'placeholder' => __("Earn [points] in [system]", 'woorewards-pro'),
						'tooltips' => sprintf(
							__('In the preview text, shortcodes %1$s and %2$s will be replaced by the points amount and Points and Rewards System title.', 'woorewards-pro'),
							'<b>[points]</b>',
							'<b>[system]</b>'
						),
						'wpml' => "WooRewards - Product loop - Points Preview pattern",
					)
				),
				array(
					'id' => 'lws_woorewards_product_loop_preview_pools',
					'title' => __("Points and rewards systems", 'woorewards-pro'),
					'type' => 'lacchecklist',
					'extra' => array(
						'ajax' => 'lws_woorewards_pool_list',
						'tooltips' => __("If you select several systems, they will be displayed separately, one after another.", 'woorewards-pro'),
					)
				),
			)
		);
	}

	/**	Check legacy switcher:
	 *	Turn off the deactivated way (legacy or custom page). */
	static public function setOptionCheck()
	{
		$options = array(
			'lws_woorewards_wc_my_account_mode' => array(
				'lws_woorewards_myaccount_loyalty_enable', // 0: custom
				'lws_woorewards_wc_my_account_endpont_loyalty', // 1: legacy
			),
			'lws_woorewards_wc_badges_account_mode' => array(
				'lws_woorewards_myaccount_badges_enable',
				'lws_woorewards_wc_my_account_endpoint_badges',
			),
			'lws_woorewards_wc_achievements_account_mode' => array(
				'lws_woorewards_myaccount_achievements_enable',
				'lws_woorewards_wc_my_account_endpoint_achievements',
			),
		);
		foreach ($options as $switcher => $options) {
			// hook triggered even if option dont changed
			\add_filter("pre_update_option_{$switcher}", function($value, $old, $opt) use($options){
				// ON means legacy: deactivate custom page
				$option = $options[$value ? 0 : 1];
				\update_option($option, ''); // turn Off
				// prevent later override by faking no change
				\add_filter("pre_update_option_{$option}", function($value, $old, $opt){
					return $old;
				}, 10, 3);
				// let's go
				return $value;
			}, 10, 3);
		}
	}
}
