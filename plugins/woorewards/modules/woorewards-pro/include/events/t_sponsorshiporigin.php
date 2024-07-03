<?php
namespace LWS\WOOREWARDS\PRO\Events;

// don't call the file directly
if( !defined( 'ABSPATH' ) ) exit();

/** Manage common feature for Events about order. */
trait T_SponsorshipOrigin
{
	use \LWS\WOOREWARDS\Events\T_SponsorshipOrigin;

	public function getAvailableOrigins()
	{
		$origins = array(
			array('value' => 'sponsor',   'label' => __("Referral email", 'woorewards-pro')),
			array('value' => 'referral',  'label' => __("Referral link", 'woorewards-pro')),
			array('value' => 'socials',   'label' => __("Any social network", 'woorewards-pro')),
			array('value' => 'socialnet', 'label' => __("Specific social network", 'woorewards-pro'), 'group' => \LWS\WOOREWARDS\PRO\Core\Socials::instance()->asDataSource()),
		);
		if( $this->acceptNoneValue() )
		{
			$origins = array_merge(array(array('value' => 'none', 'label' => _x("None", "Expect visitor to never register any referrer at all", 'woorewards-pro'))
			), $origins);
		}
		return \apply_filters('lws_woorewards_sponsorship_events_available_origins', $origins);
	}

	public function isValidOrigin($origin=false)
	{
		if( $origins = $this->getOrigins() ) // no origins set means no restriction
		{
			if( !$origin ) // default origin is sponsor
				$origin = 'sponsor';

			// complete generic restrictions
			if( in_array('sponsor', $origins) )
				$origins[] = 'manual';
			if( in_array('socials', $origins) )
				$origins = array_merge($origins, \LWS\WOOREWARDS\PRO\Core\Socials::instance()->getSupportedNetworks());

			if( !in_array($origin, $origins) )
				return false;
		}
		return true;
	}

	/** Provided to be overriden. Default returns false.
	 * 	Override and return true to manage a 'None' option in origin.
	 *  'None' expect visitor to never registered any sponsor in any way. */
	protected function acceptNoneValue()
	{
		return false;
	}
}
