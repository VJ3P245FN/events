<?php

namespace LWS\WOOREWARDS\PRO\Ui\Endpoints;

// don't call the file directly
if (!defined('ABSPATH')) exit();

/** Add a tab to WooCommerce MyAccount page. */
class Badges extends \LWS\Adminpanel\Pages\Endpoint
{
	protected function getDefaultOptions()
	{
		return array(
			'prefix'  => 'lws_woorewards_myaccount_badges',
			'slug'    => 'lws_badges',
			'title'   => __('Badges', 'woorewards-pro'),
			'enable'  => false,
			'wpml'    => 'WooRewards - MyAccount Badges Title',
			'content' => array($this, 'getContent'),
		);
	}

	public function getContent()
	{
		return \LWS\Adminpanel\Tools\Conveniences::array2html(
			array(
				array(
					'tag' => 'h2',
					__("My Badges", 'woorewards-pro'),
				),
				'<!-- wp:shortcode -->[lws_badges]<!-- /wp:shortcode -->',
			)
		);
	}
}
