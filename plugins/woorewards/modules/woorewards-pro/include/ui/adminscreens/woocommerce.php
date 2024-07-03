<?php
namespace LWS\WOOREWARDS\PRO\Ui\AdminScreens;
// don't call the file directly
if (!defined('ABSPATH')) exit();

class WooCommerce
{
	static function getTab(&$page)
	{
		$legacy = \LWS\WOOREWARDS\Conveniences::instance()->isLegacyShown('4.7.0');
		return array(
			'id'	=> 'wc_settings',
			'title'	=>  __("WooCommerce", 'woorewards-pro'),
			'icon'	=> 'lws-icon-cart-2',
			'vertnav' => true,
			'groups' => array(
				'myaccountlrview'           => self::getGroupMyAccountLoyalty($legacy),
				'cart'                      => $page['tabs']['woocommerce']['groups']['cart'],
				'checkout'                  => $page['tabs']['woocommerce']['groups']['checkout'],
				'productpointspreview'      => self::getGroupProductPointsPreview(),
				'shoppointspreview'         => self::getGroupShopPointsPreview(),
				'orderpoints'               => self::getGroupOrderPoints(),
				'myaccountbadgesview'       => self::getGroupMyAccountBadges($legacy),
				'myaccountachievementsview' => self::getGroupMyAccountAchievements($legacy),
			),
		);
	}

	/** @see getGroupMyAccountLoyalty  legacy mode*/
	protected static function getLoyaltyLegacyFields()
	{
		return array(
			array(
				'id'    => 'lws_woorewards_wc_my_account_mode',
				'title' => __("Display Mode", 'woorewards-pro'),
				'type'  => 'box',
				'extra' => array(
					'layout' => 'switch',
					'id'      => 'lws_woorewards_wc_my_account_mode',
					'default' => 'on',
					'data'    => array(
						'left'       => __("Custom Page", 'woorewards-pro'),
						'right'      => __("Prebuilt Page", 'woorewards-pro'),
						'colorleft'  => '#22a971',
						'colorright' => '#5279b1',
					),
				)
			),
			array(
				'id'    => 'lws_woorewards_wc_my_account_endpont_loyalty',
				'title' => __("Display the Loyalty and Rewards tab.", 'woorewards-pro'),
				'type'  => 'box',
				'extra' => array(
					'layout' => 'toggle',
					'default' => 'on'
				),
				'require' => array('selector' => '#lws_woorewards_wc_my_account_mode', 'value' => 'on')
			),
			array(
				'id'    => 'lws_woorewards_wc_my_account_lar_label',
				'title' => __("Loyalty and Rewards Tab Title", 'woorewards-pro'),
				'type'  => 'text',
				'extra' => array(
					'size'        => '50',
					'placeholder' => __('Loyalty and Rewards', 'woorewards-pro'),
					'wpml'        => "WooRewards - My Account - Loyalty and Rewards Tab Title",
				),
				'require' => array('selector' => '#lws_woorewards_wc_my_account_mode', 'value' => 'on')
			),
			array(
				'id'    => 'lws_woorewards_wc_my_account_endpoint_slug',
				'title' => __("Endpoint Slug", 'woorewards-pro'),
				'type'  => 'text',
				'extra' => array(
					'size'        => '50',
					'placeholder' => 'lws_woorewards',
				),
				'require' => array('selector' => '#lws_woorewards_wc_my_account_mode', 'value' => 'on')
			),
			array(
				'id'    => 'lws_woorewards_wc_my_account_lar_options',
				'title' => __("Elements to display", 'woorewards-pro'),
				'type'  => 'checkgrid', // radiogrid is specific to the wizard
				'extra' => array(
					'source' => array(
						array('value' => 'coupons', 'active' => 'yes', 'label' => __("Available Coupons", 'woorewards-pro')),
						array('value' => 'rewards', 'active' => 'yes', 'label' => __("Unlockable Rewards", 'woorewards-pro')),
						array('value' => 'systems', 'active' => 'yes', 'label' => __("Points and rewards systems Details", 'woorewards-pro')),
						array('value' => 'history', 'active' => 'yes', 'label' => __("Customer Points History", 'woorewards-pro')),
						array('value' => 'sponsoremail', 'active' => '', 'label' => __("Mailing Referral", 'woorewards-pro')),
						array('value' => 'sponsorlink', 'active' => '', 'label' => __("Referral Link", 'woorewards-pro')),
					),
					'dragndrop' => 'yes',
					'help' => sprintf('%s<br/><strong>%s</strong>',
						__("Select the elements you want to display on the loyalty and rewards tab.", 'woorewards-pro'),
						__("You can rearrange the elements in the order you want by using drag and drop.", 'woorewards-pro')
					),
				),
				'require' => array('selector' => '#lws_woorewards_wc_my_account_mode', 'value' => 'on')
			),
			array(
				'id'    => 'lws_woorewards_wc_my_account_systems_list',
				'title' => __("Points and rewards systems", 'woorewards-pro'),
				'type'  => 'lacchecklist',
				'extra' => array(
					'ajax' => 'lws_woorewards_pool_list',
					'help' => __("Select the points and rewards systems you want to display", 'woorewards-pro'),
				),
				'require' => array('selector' => '#lws_woorewards_wc_my_account_mode', 'value' => 'on')
			),
			'expanded_display' => array(
				'id'    => 'lws_woorewards_wc_myaccount_expanded',
				'title' => __("Expanded display", 'woorewards-pro'),
				'type'  => 'box',
				'extra' => array(
					'class' => 'lws_checkbox',
					'help'  => __("Disables the accordion feature on the endpoint and expands all sections", 'woorewards-pro'),
				),
				'require' => array('selector' => '#lws_woorewards_wc_my_account_mode', 'value' => 'on')
			),
			'leveling_bar' => array(
				'id'    => 'lws_woorewards_wc_myaccount_levelbars',
				'title' => __("Leveling Progress Bar", 'woorewards-pro'),
				'type'  => 'box',
				'extra' => array(
					'class' => 'lws_checkbox',
					'help'  => __("Displays a 'Current Progress' bar for leveling systems", 'woorewards-pro'),
				),
				'require' => array('selector' => '#lws_woorewards_wc_my_account_mode', 'value' => 'on')
			),
			array(
				'id'    => 'lws_wre_myaccount_lar_view',
				'type'  => 'themer',
				'extra' => array(
					'template' => 'wc_loyalty_and_rewards',
					'css'      => LWS_WOOREWARDS_PRO_CSS . '/loyalty-and-rewards.css',
					'prefix'   => '--wr-lar-'
				),
				'require' => array('selector' => '#lws_woorewards_wc_my_account_mode', 'value' => 'on')
			)
		);
	}

	/** @see getGroupMyAccountBadges legacy mode */
	protected static function getBadgesLegacyFields()
	{
		return array(
			array(
				'id'    => 'lws_woorewards_wc_badges_account_mode',
				'title' => __("Display Mode", 'woorewards-pro'),
				'type'  => 'box',
				'extra' => array(
					'class'   => 'lws_switch',
					'id'      => 'lws_woorewards_wc_badges_account_mode',
					'default' => 'on',
					'data'    => array(
						'left'       => __("Custom Page", 'woorewards-pro'),
						'right'      => __("Prebuilt Page", 'woorewards-pro'),
						'colorleft'  => '#22a971',
						'colorright' => '#5279b1',
					),
				)
			),
			array(
				'id'    => 'lws_woorewards_wc_my_account_endpoint_badges',
				'title' => __("Display the Badges tab.", 'woorewards-pro'),
				'type'  => 'box',
				'extra' => array(
					'layout' => 'toggle',
					'default' => 'on'
				),
				'require' => array(
					'selector' => '#lws_woorewards_wc_badges_account_mode', 'value' => 'on'
				)
			),
			array(
				'id'    => 'lws_woorewards_wc_my_account_badges_label',
				'title' => __("Badges Tab Title", 'woorewards-pro'),
				'type'  => 'text',
				'extra' => array(
					'size'        => '50',
					'placeholder' => __('My Badges', 'woorewards-pro'),
					'wpml'        => "WooRewards - My Account - Badges Tab Title",
				),
				'require' => array(
					'selector' => '#lws_woorewards_wc_badges_account_mode', 'value' => 'on'
				)

			),
			array(
				'id'    => 'lws_woorewards_wc_badges_endpoint_slug',
				'title' => __("Endpoint Slug", 'woorewards-pro'),
				'type'  => 'text',
				'extra' => array(
					'size'        => '50',
					'placeholder' => 'lws_badges',
				),
				'require' => array(
					'selector' => '#lws_woorewards_wc_badges_account_mode', 'value' => 'on'
				)
			),
			array(
				'id'    => 'lws_wre_myaccount_badges_view',
				'type'  => 'themer',
				'extra' => array(
					'template' => 'wc_badges_endpoint',
					'css'      => LWS_WOOREWARDS_PRO_CSS . '/badges-endpoint.css',
					'prefix'   => '--wr-badges-'
				),
				'require' => array(
					'selector' => '#lws_woorewards_wc_badges_account_mode', 'value' => 'on'
				)
			)
		);
	}

	/** @see getGroupMyAccountAchievements legacy mode */
	protected static function getAchievementsLegacyFields()
	{
		return array(
			array(
				'id'    => 'lws_woorewards_wc_achievements_account_mode',
				'title' => __("Display Mode", 'woorewards-pro'),
				'type'  => 'box',
				'extra' => array(
					'class'   => 'lws_switch',
					'id'      => 'lws_woorewards_wc_achievements_account_mode',
					'default' => 'on',
					'data'    => array(
						'left'       => __("Custom Page", 'woorewards-pro'),
						'right'      => __("Prebuilt Page", 'woorewards-pro'),
						'colorleft'  => '#22a971',
						'colorright' => '#5279b1',
					),
				),
			),
			array(
				'id'    => 'lws_woorewards_wc_my_account_endpoint_achievements',
				'title' => __("Display the Achievements tab.", 'woorewards-pro'),
				'type'  => 'box',
				'extra' => array(
					'layout' => 'toggle',
					'default' => 'on'
				),
				'require' => array(
					'selector' => '#lws_woorewards_wc_achievements_account_mode', 'value' => 'on'
				)
			),
			array(
				'id'    => 'lws_woorewards_wc_my_account_achievements_label',
				'title' => __("Achievements Tab Title", 'woorewards-pro'),
				'type'  => 'text',
				'extra' => array(
					'size'        => '50',
					'placeholder' => __('Achievements', 'woorewards-pro'),
					'wpml'        => "WooRewards - My Account - Achievements Tab Title",
				),
				'require' => array(
					'selector' => '#lws_woorewards_wc_achievements_account_mode', 'value' => 'on'
				)
			),
			array(
				'id'    => 'lws_woorewards_wc_achievements_endpoint_slug',
				'title' => __("Endpoint Slug", 'woorewards-pro'),
				'type'  => 'text',
				'extra' => array(
					'size'        => '50',
					'placeholder' => 'lws_achievements',
				),
				'require' => array(
					'selector' => '#lws_woorewards_wc_achievements_account_mode', 'value' => 'on'
				)
			),
			array(
				'id'    => 'lws_wre_myaccount_achievements_view',
				'type'  => 'themer',
				'extra' => array(
					'template' => 'wc_achievements_endpoint',
					'css'      => LWS_WOOREWARDS_PRO_CSS . '/achievements-endpoint.css',
					'prefix'   => '--wr-achievements-'
				),
				'require' => array(
					'selector' => '#lws_woorewards_wc_achievements_account_mode', 'value' => 'on'
				)
			)
		);
	}

	protected static function getGroupMyAccountLoyalty($legacy)
	{
		$group = array(
			'id'     => 'myaccountlarview',
			'icon'   => 'lws-icon-users',
			'color'  => '#425981',
			'title'  => __("My Account - Loyalty", 'woorewards-pro'),
			'text'   => __("Show to the customer all loyalty and rewards information in a dedicated 'Loyalty and Rewards' Tab inside WooCommerce's My Account.", 'woorewards-pro'),
			'extra'  => array('doclink' => \LWS\WOOREWARDS\PRO\DocLinks::get('wc-account')),
			'fields' => array(),
		);

		if ($legacy) {
			$group['fields'] = self::getLoyaltyLegacyFields();
			foreach (\LWS\WOOREWARDS\PRO\Ui\Endpoints\Loyalty::getAdminFields() as $index => $field) {
				$field['require'] = array('selector' => '#lws_woorewards_wc_my_account_mode', 'value' => '');
				$group['fields'][$index] = $field;
			}
		} else {
			$group['fields'] = \LWS\WOOREWARDS\PRO\Ui\Endpoints\Loyalty::getAdminFields();
		}
		return $group;
	}

	protected static function getGroupMyAccountBadges($legacy)
	{
		$group = array(
			'id' => 'myaccountbadgesview',
			'icon'	=> 'lws-icon-cockade',
			'color' => '#425981',
			'title' => __("My Account - Badges", 'woorewards-pro'),
			'text' => __("Show to the customer all badges he owns in a 'Badges' Tab inside WooCommerce's My Account.", 'woorewards-pro'),
			'extra'    => array('doclink' => \LWS\WOOREWARDS\PRO\DocLinks::get('wc-account')),
		);

		if ($legacy) {
			$group['fields'] = self::getBadgesLegacyFields();
			foreach (\LWS\WOOREWARDS\PRO\Ui\Endpoints\Badges::getAdminFields() as $index => $field) {
				$field['require'] = array('selector' => '#lws_woorewards_wc_badges_account_mode', 'value' => '');
				$group['fields'][$index] = $field;
			}
		} else {
			$group['fields'] = \LWS\WOOREWARDS\PRO\Ui\Endpoints\Badges::getAdminFields();
		}
		return $group;
	}

	protected static function getGroupMyAccountAchievements($legacy)
	{
		$group = array(
			'id'    => 'myaccountachievementsview',
			'icon'	=> 'lws-icon-trophy',
			'color' => '#425981',
			'title' => __("My Account - Achievements", 'woorewards-pro'),
			'text'  => __("Show to the customer all possible achievements in a 'Achievements' Tab inside WooCommerce's My Account.", 'woorewards-pro'),
			'extra' => array('doclink' => \LWS\WOOREWARDS\PRO\DocLinks::get('wc-account')),
		);

		if ($legacy) {
			$group['fields'] = self::getAchievementsLegacyFields();
			foreach (\LWS\WOOREWARDS\PRO\Ui\Endpoints\Achievements::getAdminFields() as $index => $field) {
				$field['require'] = array('selector' => '#lws_woorewards_wc_achievements_account_mode', 'value' => '');
				$group['fields'][$index] = $field;
			}
		} else {
			$group['fields'] = \LWS\WOOREWARDS\PRO\Ui\Endpoints\Achievements::getAdminFields();
		}
		return $group;
	}

	protected static function getGroupProductPointsPreview()
	{
		return array(
			'id'    => 'productpointspreview',
			'icon'	=> 'lws-icon-barcode',
			'color' => '#425981',
			'title' => __("Product Page Preview", 'woorewards-pro'),
			'text'  => array(
				array(
					'join' => '<br/>',
					__("Show to your customers how many points they can earn when purchasing a product", 'woorewards-pro'),
					__("You can set a different content for regular products and for variable products", 'woorewards-pro'),
					__("Use the following options to display specific information :", 'woorewards-pro'),
				),
				array(
					'tag' => 'ul',
					array('[wr_product_points] : ', __("Displays the amount of points earned for purchasing a regular product", 'woorewards-pro')),
					array('[wr_product_points value="min"] : ', __("Displays the minimum amount of points earned for purchasing a variable product", 'woorewards-pro')),
					array('[wr_product_points value="max"] : ', __("Displays the maximum amount of points earned for purchasing a variable product", 'woorewards-pro')),
				),
				array(
					'tag' => 'strong',
					__("In addition, there are several options you can use to customize the display :", 'woorewards-pro')
				),
				array(
					'tag' => 'ul',
					array('system : ', __("Select for which system you want to show points (example : [wr_product_points system='default'])", 'woorewards-pro')),
					array('showcurrency : ', __("Set this option to display the points currency next to the points amount (example : [wr_product_points showcurrency='true'])", 'woorewards-pro')),
				),
			),
			'fields' => array(
				'layout' => array(
					'id'    => 'lws_woorewards_product_preview_position',
					'title' => __("Display Location", 'woorewards-pro'),
					'type'  => 'radiogrid',
					'extra' => array(
						'type'    => 'big-icon',
						'columns' => 'repeat(auto-fit, minmax(160px, 1fr))',
						'default' => 'none',
						'source'  => array(
							array('value' => 'none',                                      'image' => LWS_WOOREWARDS_PRO_IMG . '/admin/none.png',							'label' => __("Not Displayed", 'woorewards-pro')),
							array('value' => 'woocommerce_before_single_product_summary', 'image' => LWS_WOOREWARDS_PRO_IMG . '/admin/before_single_product_summary.png',	'label' => __("Top of Product Summary", 'woorewards-pro')),
							array('value' => 'woocommerce_single_product_summary',        'image' => LWS_WOOREWARDS_PRO_IMG . '/admin/single_product_summary.png',		'label' => __("Before Product Summary", 'woorewards-pro')),
							array('value' => 'woocommerce_before_add_to_cart_form',       'image' => LWS_WOOREWARDS_PRO_IMG . '/admin/before_add_to_cart_form.png',		'label' => __("After Short Description", 'woorewards-pro')),
							array('value' => 'woocommerce_before_add_to_cart_quantity',   'image' => LWS_WOOREWARDS_PRO_IMG . '/admin/before_add_to_cart_quantity.png',	'label' => __("Before Quantity", 'woorewards-pro')),
							array('value' => 'woocommerce_after_add_to_cart_button',      'image' => LWS_WOOREWARDS_PRO_IMG . '/admin/after_add_to_cart_button.png',		'label' => __("After Add to Cart", 'woorewards-pro')),
							array('value' => 'woocommerce_after_single_product_summary',  'image' => LWS_WOOREWARDS_PRO_IMG . '/admin/after_single_product_summary.png',	'label' => __("After Product Summary", 'woorewards-pro')),
						)
					),
				),
				'regular' => array(
					'id' => 'lws_woorewards_product_preview_regular',
					'type' => 'wpeditor',
					'title' => __("Regular Products Content", 'woorewards-pro'),
					'extra' => array(
						'editor_height' => 30,
						'wpml'    => "WooRewards - Products Points Preview - Regular Product Content",
					)
				),
				'variable' => array(
					'id' => 'lws_woorewards_product_preview_variable',
					'type' => 'wpeditor',
					'title' => __("Variable Products Content", 'woorewards-pro'),
					'extra' => array(
						'editor_height' => 30,
						'wpml'    => "WooRewards - Products Points Preview - Variable Product Content",
					)
				),
			)
		);
	}

	protected static function getGroupShopPointsPreview()
	{
		return array(
			'id'    => 'shoppointspreview',
			'icon'	=> 'lws-icon-tags-stack',
			'color' => '#425981',
			'title' => __("Shop Page Preview", 'woorewards-pro'),
			'text'  => array(
				array(
					'join' => '<br/>',
					__("Show to your customers how many points they can earn when purchasing products", 'woorewards-pro'),
					__("You can set a different content for regular products and for variable products", 'woorewards-pro'),
					__("Use the following options to display specific information :", 'woorewards-pro'),
				),
				array(
					'tag' => 'ul',
					array('[wr_product_points] : ', __("Displays the amount of points earned for purchasing a regular product", 'woorewards-pro')),
					array('[wr_product_points value="min"] : ', __("Displays the minimum amount of points earned for purchasing a variable product", 'woorewards-pro')),
					array('[wr_product_points value="max"] : ', __("Displays the maximum amount of points earned for purchasing a variable product", 'woorewards-pro')),
				),
				array(
					'tag' => 'strong',
					__("In addition, there are several options you can use to customize the display :", 'woorewards-pro')
				),
				array(
					'tag' => 'ul',
					array('system : ', __("Select for which system you want to show points (example : [wr_product_points system='default'])", 'woorewards-pro')),
					array('showcurrency : ', __("Set this option to display the points currency next to the points amount (example : [wr_product_points showcurrency='true'])", 'woorewards-pro')),
				),
			),
			'fields' => array(
				'layout' => array(
					'id'    => 'lws_woorewards_archive_product_preview_position',
					'title' => __("Display Location", 'woorewards-pro'),
					'type'  => 'radiogrid',
					'extra' => array(
						'type'    => 'big-icon',
						'columns' => 'repeat(auto-fit, minmax(160px, 1fr))',
						'default' => 'none',
						'source'  => array(
							array('value' => 'none',                                   'image' => LWS_WOOREWARDS_PRO_IMG . '/admin/archive_none.png',               'label' => __("Not Displayed", 'woorewards-pro')),
							array('value' => 'woocommerce_before_shop_loop_item',      'image' => LWS_WOOREWARDS_PRO_IMG . '/admin/before_shop_loop_item.png',      'label' => __("Before Image", 'woorewards-pro')),
							array('value' => 'woocommerce_shop_loop_item_title',       'image' => LWS_WOOREWARDS_PRO_IMG . '/admin/shop_loop_item_title.png',       'label' => __("After Description", 'woorewards-pro')),
							array('value' => 'woocommerce_after_shop_loop_item_title', 'image' => LWS_WOOREWARDS_PRO_IMG . '/admin/after_shop_loop_item_title.png',	'label' => __("After Price", 'woorewards-pro')),
							array('value' => 'woocommerce_after_shop_loop_item',       'image' => LWS_WOOREWARDS_PRO_IMG . '/admin/after_shop_loop_item.png',       'label' => __("Below Add to Cart", 'woorewards-pro')),
						)
					),
				),
				'regular' => array(
					'id' => 'lws_woorewards_archive_product_preview_regular',
					'type' => 'wpeditor',
					'title' => __("Regular Products Content", 'woorewards-pro'),
					'extra' => array(
						'editor_height' => 30,
						'wpml'    => "WooRewards - Shop Points Preview - Regular Product Content",
					)
				),
				'variable' => array(
					'id' => 'lws_woorewards_archive_product_preview_variable',
					'type' => 'wpeditor',
					'title' => __("Variable Products Content", 'woorewards-pro'),
					'extra' => array(
						'editor_height' => 30,
						'wpml'    => "WooRewards - Shop Points Preview - Variable Product Content",
					)
				),
			)
		);
	}

	protected static function getGroupOrderPoints()
	{
		return array(
			'id' => 'orderpoints',
			'icon'	=> 'lws-icon-letter',
			'color' => '#425981',
			'title' => __("Order Points Information", 'woorewards-pro'),
			'text' => array(
				array('join' => '<br/>',
					__("Set a message for customers when they place a new order", 'woorewards-pro'),
					__("Use the following options to display specific information on the email :", 'woorewards-pro'),
				),
				array('tag' => 'ul',
					array('[wr_wc_order_points] : ', __("displays all information about points earned in the current points and rewards system.", 'woorewards-pro')),
					array('[order_points] : ', __("displays the points earned for this order in the current points and rewards systems.", 'woorewards-pro')),
					array('[points_name] : ', __("displays the name of the points in the current points and rewards systems.", 'woorewards-pro')),
					array('[system_name] : ', __("displays the title of the current points and rewards systems.", 'woorewards-pro')),
					array('[points_balance] : ', __("displays the user's points balance in the current points and rewards systems.", 'woorewards-pro')),
				),
				array('tag' => 'strong',
					__("If multiple points and rewards systems gave points with the order, the text will be repeated for each system.", 'woorewards-pro')
				),
			),
			'extra'    => array('doclink' => \LWS\WOOREWARDS\PRO\DocLinks::get('wc-order-email')),
			'fields' => array(
				array(
					'id' => 'lws_woorewards_wc_new_order_enable',
					'title' => __("Enable Email Message", 'woorewards-pro'),
					'type' => 'box',
					'extra' => array(
						'default' => '',
						'layout' => 'toggle',
						'tooltips' => __("Check this option if you want to show a message in new order emails", 'woorewards-pro'),
					)
				),
				array(
					'id' => 'lws_woorewards_wc_details_order_enable',
					'title' => __("Enable Order Details Message", 'woorewards-pro'),
					'type' => 'box',
					'extra' => array(
						'default' => '',
						'layout' => 'toggle',
						'tooltips' => __("Check this option if you want to show a message in the Order details, in Customer My Account page", 'woorewards-pro'),
					)
				),
				array(
					'id' => 'lws_woorewards_wc_new_order_pools',
					'title' => __("Points and rewards systems", 'woorewards-pro'),
					'type' => 'lacchecklist',
					'extra' => array(
						'ajax' => 'lws_woorewards_pool_list',
						'tooltips' => __("If you select several systems, they will be displayed separately, one after the other when using the shortcode.", 'woorewards-pro'),
					)
				),
				array(
					'id' => 'lws_woorewards_wc_new_order_content',
					'type' => 'wpeditor',
					'title' => __("Email HTML text", 'woorewards-pro'),
					'extra' => array(
						'editor_height' => 30,
						'default' => __("With this order, you will earn [wr_wc_order_points]", 'woorewards-pro'),
						'wpml'    => "WooRewards - New Order Email Message - Earning Points",
					)
				),
				array(
					'id' => 'lws_woorewards_wc_thanks_order_enable',
					'title' => __("Enable Thanks Page Message", 'woorewards-pro'),
					'type' => 'box',
					'extra' => array(
						'default' => '',
						'layout' => 'toggle',
						'tooltips' => __("Check this option if you want to show a message in the Thank you page after Order validation", 'woorewards-pro'),
					)
				),
				array(
					'id' => 'lws_woorewards_wc_new_order_content_plaintext',
					'type' => 'textarea',
					'title' => __("Thanks plain text", 'woorewards-pro'),
					'extra' => array(
						'rows'    => 2,
						'default' => __("With this order, you will earn [wr_wc_order_points]", 'woorewards-pro'),
						'wpml'    => "WooRewards - New Order Thanks Message - Earning Points",
						'help'    => __("WooCommerce does not suport HTML here", 'woorewards-pro'),
					)
				),
			)
		);
	}
}
