<?php

namespace LWS\WOOREWARDS\PRO\Ui\Endpoints;

// don't call the file directly
if (!defined('ABSPATH')) exit();

/** Add a tab to WooCommerce MyAccount page. */
class Achievements extends \LWS\Adminpanel\Pages\Endpoint
{
	protected function getDefaultOptions()
	{
		return array(
			'prefix'  => 'lws_woorewards_myaccount_achievements',
			'slug'    => 'lws_achievements',
			'title'   => __('Achievements', 'woorewards-pro'),
			'enable'  => false,
			'wpml'    => 'WooRewards - MyAccount Achievements Title',
			'content' => array($this, 'getContent'),
		);
	}

	public function getContent()
	{
		return \LWS\Adminpanel\Tools\Conveniences::array2html(
			array(
				array(
					'tag' => 'h2',
					__("Achievements", 'woorewards-pro'),
				),
				'<!-- wp:shortcode -->[lws_achievements]<!-- /wp:shortcode -->',
			)
		);
	}
}
