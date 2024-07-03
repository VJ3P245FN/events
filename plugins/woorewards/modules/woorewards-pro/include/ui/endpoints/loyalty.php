<?php
namespace LWS\WOOREWARDS\PRO\Ui\Endpoints;

// don't call the file directly
if (!defined('ABSPATH')) exit();

/** Add a tab to WooCommerce MyAccount page. */
class Loyalty extends \LWS\Adminpanel\Pages\Endpoint
{
	protected function getDefaultOptions()
	{
		return array(
			'prefix'  => 'lws_woorewards_myaccount_loyalty',
			'slug'    => 'lws_woorewards',
			'title'   => __('Loyalty and Rewards', 'woorewards-pro'),
			'enable'  => !\LWS\WOOREWARDS\Conveniences::instance()->isLegacyShown('4.7.0'),
			'wpml'    => 'WooRewards - MyAccount title',
			'content' => array($this, 'getContent'),
		);
	}

	public function getContent()
	{
		return \LWS\Adminpanel\Tools\Conveniences::array2html(
			array(
				array(
					'tag' => 'h2',
					__("Your Points Balance", 'woorewards-pro'),
				),
				'<!-- wp:shortcode -->[wr_points_balance layout="grid" element="tile"]<!-- /wp:shortcode -->',
				array(
					'tag' => 'h2',
					__("Available Rewards", 'woorewards-pro'),
				),
				'<!-- wp:shortcode -->[wr_available_rewards layout="vertical" element="line" applyreward="true"]<!-- /wp:shortcode -->',
				array(
					'tag' => 'h2',
					__("How to earn points", 'woorewards-pro'),
				),
				'<!-- wp:shortcode -->[wr_earn_points layout="grid" element="tile"]<!-- /wp:shortcode -->',
				array(
					'tag' => 'h2',
					__("Rewards you can earn", 'woorewards-pro'),
				),
				'<!-- wp:shortcode -->[wr_rewards layout="grid" element="tile"]<!-- /wp:shortcode -->',
			)
		);
	}
}
