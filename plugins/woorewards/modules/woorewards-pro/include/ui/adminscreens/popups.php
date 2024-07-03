<?php

namespace LWS\WOOREWARDS\PRO\Ui\AdminScreens;
// don't call the file directly
if (!defined('ABSPATH')) exit();

class Popups
{
	static function getTab()
	{
		$legacy = \LWS\WOOREWARDS\Conveniences::instance()->isLegacyShown('4.9.0');
		$tab = array(
			'id'	=> 'reward_popup',
			'title'	=>  __("Popups", 'woorewards-pro'),
			'icon'	=> 'lws-icon-window-add',
			'groups' => array(
				'claim'		  => self::getGroupRewardPopup($legacy),
				'freeproduct' => self::getGroupFreeProductPopup($legacy),
			),
		);
		return $tab;
	}

	protected static function getGroupRewardPopup($legacy)
	{
		$group = array(
			'id'     => 'claim',
			'icon'   => 'lws-icon-window-add',
			'title'  => __("Reward Popup", 'woorewards-pro'),
			'color'   => '#425981',
			'text'   => __("Defines the popup options when a user unlocks a reward.", 'woorewards-pro'),
			'fields' => array(),
		);

		if ($legacy) {
			$group['fields'] = self::getRewardPopupLegacyFields();
			foreach (self::getRewardPopupFields() as $index => $field) {
				$field['require'] = array('selector' => '#lws_woorewards_reward_popup_legacy', 'value' => '');
				$group['fields'][$index] = $field;
			}
		} else {
			$group['fields'] = self::getRewardPopupFields();
		}
		return $group;
	}

	protected static function getGroupFreeProductPopup($legacy)
	{
		$group = array(
			'id'     => 'freeproduct',
			'icon'   => 'lws-icon-window-add',
			'title'  => __("Free Product Popup", 'woorewards-pro'),
			'text'   => __("Defines the popup options when a customer uses a free product coupon with multiple choices.", 'woorewards-pro'),
			'color'   => '#425981',
			'fields' => array(),
		);

		if ($legacy) {
			$group['fields'] = self::getFreeProductPopupLegacyFields();
			foreach (self::getFreeProductPopupFields() as $index => $field) {
				$field['require'] = array('selector' => '#lws_woorewards_free_product_popup_legacy', 'value' => '');
				$group['fields'][$index] = $field;
			}
		} else {
			$group['fields'] = self::getFreeProductPopupFields();
		}
		return $group;
	}

	/** New reward popup fields */
	protected static function getRewardPopupFields()
	{
		return array(
			'penable' => array(
				'id'    => 'lws_wr_reward_popup_enable',
				'title' => __("Enable the reward popup", 'woorewards-pro'),
				'type'  => 'box',
				'extra' => array(
					'default' => 'on',
					'layout' => 'toggle',
				)
			),
			'ptitle' => array(
				'id'    => 'lws_wr_reward_popup_title',
				'title' => __("Popup title", 'woorewards-pro'),
				'type'  => 'text',
				'extra' => array(
					'wpml'     => "WooRewards Reward Popup - title",
					'placeholder' => __("New reward unlocked !", 'woorewards-pro'),
					'size' => '60',
				),
			),
			'cpage' => array(
				'id'    => 'lws_woorewards_reward_claim_page',
				'title' => __("Redirection page after a reward is unlocked", 'woorewards-pro'),
				'type'  => 'lacselect',
				'extra' => array(
					'predefined' => 'page',
					'tooltips' => __("When a customer redeems a reward, he will be redirected to that page.", 'woorewards-pro')
						. '<br/>' . __("If WooCommerce is activated, the default is the <b>Loyalty and Rewards</b> tab in the customer my-account frontend page. Otherwise, it is your home page", 'woorewards-pro')
				),
			),
			'premaining' => array(
				'id'    => 'lws_wr_rewardclaim_notice_with_rest',
				'title' => __("Show other available rewards", 'woorewards-pro'),
				'type'  => 'box',
				'extra' => array(
					'default' => '',
					'id'      => 'lws_wr_rewardclaim_notice_with_rest',
					'layout' => 'toggle',
					'tooltips' => __("Displays a list of other redeemable rewards inside the popup", 'woorewards-pro')
				),
			),
			'rtext' => array(
				'id'    => 'lws_wr_reward_popup_remaining_text',
				'title' => __("Remaining Label", 'woorewards-pro'),
				'type'  => 'text',
				'extra' => array(
					'wpml'     => "WooRewards Reward Popup - remaining label",
					'placeholder' => __("Other rewards are waiting for you", 'woorewards-pro'),
					'size' => '60',
				),
			),
			'layout' => array(
				'id'    => 'lws_wr_reward_popup_layout',
				'title' => __("Popup layout", 'woorewards-pro'),
				'type'  => 'radiogrid',
				'extra' => array(
					'type'    => 'big-icon',
					'default' => 'all',
					'source'  => array(
						array('value' => 'all',			 'icon' => 'lws-icon-lines',			'label' => __("All with scrollbar", 'woorewards-pro')),
						array('value' => 'grid',		 'icon' => 'lws-icon-grid-interface',	'label' => __("Grid", 'woorewards-pro')),
						array('value' => 'onebyone', 	 'icon' => 'lws-icon-1-by-1', 			'label' => __("One by One", 'woorewards-pro')),
						array('value' => 'threebythree', 'icon' => 'lws-icon-3-by-3', 			'label' => __("3 by 3", 'woorewards-pro')),
					)
				),
			),

		);
	}

	/** Legacy reward popup fields */
	protected static function getRewardPopupLegacyFields()
	{
		return array(
			'mode' => array(
				'id'    => 'lws_woorewards_reward_popup_legacy',
				'title' => __("Used Popup", 'woorewards-pro'),
				'type'  => 'box',
				'extra' => array(
					'class'   => 'lws_switch',
					'id'      => 'lws_woorewards_reward_popup_legacy',
					'default' => 'on',
					'data'    => array(
						'left'       => __("New Popup", 'woorewards-pro'),
						'right'      => __("Legacy Popup", 'woorewards-pro'),
						'colorleft'  => '#425981',
						'colorright' => '#5279b1',
					),
				)
			),
			'disable' => array(
				'id'    => 'lws_wr_rewardclaim_popup_disable',
				'title' => __("Disable the reward popup", 'woorewards-pro'),
				'type'  => 'box',
				'extra' => array(
					'default' => '',
					'layout' => 'toggle',
				),
				'require' => array(
					'selector' => '#lws_woorewards_reward_popup_legacy', 'value' => 'on'
				)

			),
			'page' => array(
				'id'    => 'lws_woorewards_reward_claim_page',
				'title' => __("Redirection page after a reward is unlocked", 'woorewards-pro'),
				'type'  => 'lacselect',
				'extra' => array(
					'predefined' => 'page',
					'tooltips' => __("When a customer clicks a reward redeem button, he will be redirected to that page.", 'woorewards-pro')
						. '<br/>' . __("If WooCommerce is activated, the default is the <b>Loyalty and Rewards</b> tab in the customer my-account frontend page. Otherwise, it is your home page", 'woorewards-pro')
				),
				'require' => array(
					'selector' => '#lws_woorewards_reward_popup_legacy', 'value' => 'on'
				)
			),
			'remaining' => array(
				'id'    => 'lws_wr_rewardclaim_notice_with_rest',
				'title' => __("Show remaining available rewards after a reward is unlocked", 'woorewards-pro'),
				'type'  => 'box',
				'extra' => array(
					'default' => 'on',
					'layout' => 'toggle',
					'tooltips' => __("When a customer clicks a reward redeem button, he will be redirected to a page with an unlock feedback.", 'woorewards-pro')
						. '<br/>' . __("That popup includes the rest of available rewards.", 'woorewards-pro')
				),
				'require' => array(
					'selector' => '#lws_woorewards_reward_popup_legacy', 'value' => 'on'
				)
			),
			'stygen' => array(
				'id' => 'lws_woorewards_lws_reward_claim',
				'type' => 'stygen',
				'extra' => array(
					'purpose' => 'filter',
					'template' => 'lws_reward_claim',
					'html' => false,
					'css' => LWS_WOOREWARDS_PRO_CSS . '/templates/rewardclaim.css',
					'subids' => array(
						'lws_woorewards_wc_reward_claim_title' => "WooRewards - Reward Claim Popup - Title",
						'lws_woorewards_wc_reward_claim_header' => "WooRewards - Reward Claim Popup - Header",
						'lws_woorewards_wc_reward_claim_stitle' => "WooRewards - Reward Claim Popup - Subtitle",
					),
					'help' =>  __("This popup will show when customers unlock a new reward.", 'woorewards-pro') . "<br/>"
						. __("It can show only the reward unlocked or also the rewards that can still be unlocked .", 'woorewards-pro')
				),
				'require' => array(
					'selector' => '#lws_woorewards_reward_popup_legacy', 'value' => 'on'
				)
			),
		);
	}

	/** New free product popup fields */
	protected static function getFreeProductPopupFields()
	{
		return array(
			'title' => array(
				'id'    => 'lws_wr_free_product_popup_title',
				'title' => __("Popup title", 'woorewards-pro'),
				'type'  => 'text',
				'extra' => array(
					'wpml'     => "WooRewards Free Product - title",
					'placeholder' => __("Choose your free product", 'woorewards-pro'),
					'size' => '60',
				),
			),
			'cancel' => array(
				'id'    => 'lws_wr_free_product_popup_cancel',
				'title' => __("Cancel button label", 'woorewards-pro'),
				'type'  => 'text',
				'extra' => array(
					'wpml'     => "WooRewards Free Product - cancel",
					'placeholder' => __("Cancel", 'woorewards-pro'),
					'size' => '40',
				),
			),
			'apply' => array(
				'id'    => 'lws_wr_free_product_popup_apply',
				'title' => __("Apply button label", 'woorewards-pro'),
				'type'  => 'text',
				'extra' => array(
					'wpml'     => "WooRewards Free Product - apply",
					'placeholder' => __("Add this product", 'woorewards-pro'),
					'size' => '40',
				),
			),
			'layout' => array(
				'id'    => 'lws_wr_free_product_popup_layout',
				'title' => __("Popup layout", 'woorewards-pro'),
				'type'  => 'radiogrid',
				'extra' => array(
					'type'    => 'big-icon',
					'default' => 'all',
					'source'  => array(
						array('value' => 'all',			 'icon' => 'lws-icon-lines',			'label' => __("All with scrollbar", 'woorewards-pro')),
						array('value' => 'grid',		 'icon' => 'lws-icon-grid-interface',	'label' => __("Grid", 'woorewards-pro')),
						array('value' => 'onebyone', 	 'icon' => 'lws-icon-1-by-1', 			'label' => __("One by One", 'woorewards-pro')),
						array('value' => 'threebythree', 'icon' => 'lws-icon-3-by-3', 			'label' => __("3 by 3", 'woorewards-pro')),
					)
				),
			),
		);
	}

	/** Legacy free product popup fields */
	protected static function getFreeProductPopupLegacyFields()
	{
		return array(
			'mode' => array(
				'id'    => 'lws_woorewards_free_product_popup_legacy',
				'title' => __("Used Popup", 'woorewards-pro'),
				'type'  => 'box',
				'extra' => array(
					'class'   => 'lws_switch',
					'id'      => 'lws_woorewards_free_product_popup_legacy',
					'default' => 'on',
					'data'    => array(
						'left'       => __("New Popup", 'woorewards-pro'),
						'right'      => __("Legacy Popup", 'woorewards-pro'),
						'colorleft'  => '#425981',
						'colorright' => '#5279b1',
					),
				)
			),
			'popup' => array(
				'id' => 'lws_woorewards_free_product_template',
				'type' => 'stygen',
				'extra' => array(
					'purpose' => 'filter',
					'template' => 'free_product_template',
					'html' => false,
					'css' => LWS_WOOREWARDS_PRO_CSS . '/templates/freeproduct.css',
					'subids' => array(
						'lws_free_product_popup_title' => "WooRewards Free Product - title",
						'lws_free_product_popup_cancel' => "WooRewards Free Product - cancel button",
						'lws_free_product_popup_validate' => "WooRewards Free Product - validate button",
					),
					'help' =>  __("This popup will show when customers use a free product coupon.", 'woorewards-pro')
				),
				'require' => array(
					'selector' => '#lws_woorewards_free_product_popup_legacy', 'value' => 'on'
				)
			),
		);
	}
}
