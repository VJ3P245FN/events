<?php

namespace LWS\WOOREWARDS\PRO;

// don't call the file directly
if (!defined('ABSPATH')) exit();

/** All Documentation Links*/
class DocLinks
{
	const FALLBACK = 'home';

	public static $doclinks = array(
		'achievements'   => "https://plugins.longwatchstudio.com/kb/badges-achievements/",
		'api'            => "https://plugins.longwatchstudio.com/kb/settings-and-permissions/",
		'badges'         => "https://plugins.longwatchstudio.com/kb/badges-achievements/",
		'birthday'       => "https://plugins.longwatchstudio.com/kb/birthdays/",
		'customers'      => "https://plugins.longwatchstudio.com/kb/customers-management/",
		'emails'         => "https://plugins.longwatchstudio.com/kb/wr-email-header-and-footer/",
		'home'           => "https://plugins.longwatchstudio.com/kbtopic/wr/",
		'points'         => "https://plugins.longwatchstudio.com/kb/spend-money/",
		'points-ex'      => "https://plugins.longwatchstudio.com/kb/points-expiration/",
		'pools'          => "https://plugins.longwatchstudio.com/kb/how-it-works/",
		'pools-as'       => "https://plugins.longwatchstudio.com/kb/combining-systems/",
		'pools-cur'      => "https://plugins.longwatchstudio.com/kb/how-it-works/#h-points-currency",
		'referral'       => "https://plugins.longwatchstudio.com/kb/referral-sponsorship/",
		'rewards'        => "https://plugins.longwatchstudio.com/kb/points-on-cart/",
		'shortcodes'     => "https://plugins.longwatchstudio.com/kb/wr-sc-points-information/",
		'social'         => "https://plugins.longwatchstudio.com/kb/wr-sc-referrals-socials/",
		'wc-account'     => "https://plugins.longwatchstudio.com/kb/wr-wc-my-account-tabs/",
		'wc-order-email' => "https://plugins.longwatchstudio.com/kb/wr-wc-order-confirmation-page-and-email/",
	);

	static function get($index=false, $escape = true)
	{
		if (!($index && isset(self::$doclinks[$index])))
			$index = self::FALLBACK;
		if ($escape)
			return \esc_attr(self::$doclinks[$index]);
		else
			return self::$doclinks[$index];
	}

	static function toFields()
	{
		$fields = array();
		$prefix = (\get_class() . ':');
		foreach (self::$doclinks as $key => $url) {
			$fields[$key] = array(
				'id'    => $prefix . $key,
				'title' => $key,
				'type'  => 'custom',
				'extra' => array(
					'gizmo'   => true,
					'content' => sprintf('<a href="%s" target="_blank">%s</a>', \esc_attr($url), $url),
				),
			);
		}
		return $fields;
	}
}