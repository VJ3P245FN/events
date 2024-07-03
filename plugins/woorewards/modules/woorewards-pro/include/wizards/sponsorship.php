<?php
namespace LWS\WOOREWARDS\Pro\Wizards;

// don't call the file directly
if( !defined( 'ABSPATH' ) ) exit();

/** satic class to manage activation and version updates. */
class Sponsorship extends \LWS\WOOREWARDS\Wizards\Subwizard
{
	function init()
	{
		$this->color = '#59515c';
		$this->wizard->setColor($this->color);
	}

	function getHierarchy()
	{
		// first step must be named 'ini' as the faky one from wizard.php
		return array(
			'ini',
			'set',
			'sum',
		);
	}

	function getStepTitle($slug)
	{
		switch($slug)
		{
			case 'ini':
				return __("Referral", 'woorewards-pro');
			case 'set':
				return __("Referral Settings", 'woorewards-pro');
			case 'sum' : return __("Summary", 'woorewards-pro');
		}
		return $slug;
	}

	function getPage($slug, $mode='')
	{
		switch($slug)
		{
			case 'ini':
				return array(
					'title' => $this->getStepTitle($slug),
					'help'  => __("Welcome to this Wizard. This tool will help you configure a referral system.", 'woorewards-pro') . "<br/>" .
					__("You will set the rewards for both the referrer and the referee as well as the requirements for the referrer to earn his reward.", 'woorewards-pro'),
					'groups' => array(
						array(
							'fields'  => array(
								array(
									'id'    => 'system_title',
									'title' => __('Loyalty system name', 'woorewards-pro'),
									'type'  => 'text',
									'extra' => array(
										'placeholder' => __('Referral System', 'woorewards-pro'),
										'help' => __("Name your loyalty system. If you leave it empty, it will be named automatically", 'woorewards-pro'),
									),
								),
							)
						)
					)
				);
			case 'set':
				return array(
					'title' => $this->getStepTitle($slug),
					'help'  => __("The referral system has 2 main goals :", 'woorewards-pro') .
					"<br/><ul><li>".
						__("Give a reward to referees to encourage them to visit your website and order", 'woorewards-pro') .
					"</li><li>".
						__("Encourage you customers to refer their friends", 'woorewards-pro') .
					"</li></ul><br/>".
					__("Here, you will set some basic settings for your referral system. You can change them later with advanced options.", 'woorewards-pro'),
					'groups' => array(
						array(
							'value-columns' => '1fr 1fr',
							'fields'  => array(
								array(
									'id'    => 'event_type',
									'title' => __('Referee Action', 'woorewards-pro'),
									'type'  => 'radiogrid',
									'extra' => array(
										'type' => 'auto-cols',
										'columns' => 'repeat(auto-fit, minmax(120px, 1fr))',
										'source' => array(
											array('value'=>'register'	,'icon'=>'lws-icon lws-icon-user-plus'	,'label'=>__("Register", 'woorewards-pro')),
											array('value'=>'order'		,'icon'=>'lws-icon lws-icon-cart'		,'label'=>__("Place an order", 'woorewards-pro')),
										),
										'default' => 'register',
										'help' => __("Choose the action the referee has to perform for the referrer to earn points", 'woorewards-pro'),
									),
								),
							)
						),
						array(
							'fields' => array(
								array(
									'id'    => 'sponsor_number',
									'title' => __('How many referees', 'woorewards-pro'),
									'type'  => 'text',
									'extra' => array(
										//'pattern' => "\\d*",
										'default' => '1',
										'placeholder' => __('Number', 'woorewards-pro'),
										'help' => __("Number of people to refer before the referrer earns his reward", 'woorewards-pro'),
									),
								),
								array(
									'id'    => 'max_sponsored',
									'title' => __('Max Referrals', 'woorewards-pro'),
									'type'  => 'text',
									'extra' => array(
										//'pattern' => "\\d*",
										'default' => '10',
										'placeholder' => __('Number | Empty to ignore', 'woorewards-pro'),
										'help' => __("Maximum number of people a customer can refer", 'woorewards-pro'),
									),
								),
								array(
									'id'    => 'sponsor_amount',
									'title' => sprintf(__("Referrer Reward (%s)", 'woorewards-pro'), \LWS\Adminpanel\Tools\Conveniences::isWC() ? \get_woocommerce_currency_symbol() : '?'),
									'type'  => 'text',
									'extra' => array(
										//'pattern' => "\\d*",
										'default' => '10',
										'placeholder' => __('Number ', 'woorewards-pro'),
										'help' => __("Set the rewards for the referrer and the referee", 'woorewards-pro'),
									),
								),
								array(
									'id'    => 'sponsored_amount',
									'title' => sprintf(__("Referee Reward (%s)", 'woorewards-pro'), \LWS\Adminpanel\Tools\Conveniences::isWC() ? \get_woocommerce_currency_symbol() : '?'),
									'type'  => 'text',
									'extra' => array(
										//'pattern' => "\\d*",
										'default' => '5',
										'placeholder' => __('Number ', 'woorewards-pro'),
									),
								),
							)
						)
					)
				);
			case 'sum':
				return array(
					'title' => $this->getStepTitle($slug),
					'help'  => __("You're almost done. Check your settings below and submit if you're satisfied with the settings.", 'woorewards-pro'),
					'groups' => array(
						array(
							'fields' => array(
								array(
									'id'    => 'summary',
									'title' => __("Settings Summary", 'woorewards-pro'),
									'type'  => 'custom', // radiogrid is specific to the wizard
									'extra' => array(
										'content' => $this->getSummary(),
									),
								),
								array(
									'id'    => 'start',
									'title' => __("Start the program ?", 'woorewards-pro'),
									'type'  => 'radiogrid', // radiogrid is specific to the wizard
									'extra' => array(
										'type' => 'auto-cols',
										'columns' => 'repeat(auto-fit, minmax(120px, 1fr))',
										'source' => array(
											array('value'=>'yes','label'=>__("Yes", 'woorewards-pro')),
											array('value'=>'no'	,'label'=>__("No", 'woorewards-pro')),
										),
										'default' => 'yes',
										'help' => __("Do you want to start your loyalty system at the end of this wizard ? If you select No, you'll have to start it manually later.", 'woorewards-pro'),									),
								)
							),
						),
					)
				);
			default:
				return array();

		}
	}

	/** @return true on success. On problem returns one (string) or several (array of string) reasons that will be shown to the user. */
	function isValid($step, &$submit)
	{
		$err = array();
		if( $step == 'set' )
		{
			if( !isset($submit['event_type']) || !in_array($submit['event_type'], array('register', 'order')) )
			$err[] = __("Please, select a 'Referee Action'.", 'woorewards-pro');

			if( !$this->isIntGT0($submit, 'sponsor_number', false) )
			$err[] = __("'How many referred' expects numeric value greater than zero.", 'woorewards-pro');

			if( !$this->isIntGE0($submit, 'max_sponsored') )
			$err[] = __("'Max Referrals' expects numeric value greater than zero or leave blank.", 'woorewards-pro');

			if( !$this->isFloatGT0($submit, 'sponsor_amount', false) )
			$err[] = __("'Referrer Reward' expects amount greater than zero or leave blank.", 'woorewards-pro');

			if( !$this->isFloatGT0($submit, 'sponsored_amount', false) )
			$err[] = __("'Referee Rewards' expects amount greater than zero or leave blank.", 'woorewards-pro');
		}
		return $err ? $err : true;
	}

	function getSummary()
	{
		$data = $this->getData();
		$exists = false;

		$url = add_query_arg('page', LWS_WOOREWARDS_PAGE.'.settings', \admin_url('admin.php'));
		$url.= "&tab=sponsorship#lws_group_targetable_sponsor_widget_style";
		$value = sprintf(__("Don't forget to add the %s to your pages to allow customers to sponsor their friends", 'woorewards-pro'), "<a target='_blank' href='{$url}'>" . __("Referral widget", 'woorewards-pro') . "</a>");
		$summary = "<div class='item-help visible'><div class='icon lws-icons lws-icon-bulb'></div><div class='text'>{$value}</div></div>";

		$summary .= "<div class='lws-wizard-summary-container'>";
		/* Loyalty system name */
		$summary .= "<div class='summary-title'>" . __("Referral", 'woorewards-pro') . "</div>";
		$title = $this->getValue($data['data'], 'system_title', 'ini/*', __("Referral System", 'woorewards-pro'));
		$summary .= "<div class='lws-wizard-summary-label'>".__("Loyalty System", 'woorewards-pro')."</div>";
		$summary .= "<div class='lws-wizard-summary-value'>{$title}</div>";
		$summary .= "<div class='summary-title'>" . __("Referral Settings", 'woorewards-pro') . "</div>";

		$value = $this->getValue($data['data'], 'sponsor_amount', 'set/*');
		$summary .= "<div class='lws-wizard-summary-label'>" . sprintf(__("Referrer Reward (%s)", 'woorewards-pro'), \LWS\Adminpanel\Tools\Conveniences::isWC() ? \get_woocommerce_currency_symbol() : '?') . "</div>";
		$summary .= "<div class='lws-wizard-summary-value'>{$value}</div>";

		$summary .= "<div class='lws-wizard-summary-label'>" . __("Referee Action to perform", 'woorewards-pro') . "</div>";
		$action = $this->getValue($data['data'], 'event_type', 'set/*');
		if( 'register' === $action )
		{
			$value = __("Referee registers", 'woorewards-pro');
		}
		else if( 'order' === $action )
		{
			$value = __("Referee places an order", 'woorewards-pro');
		}
		$summary .= "<div class='lws-wizard-summary-value'>{$value}</div>";

		if(($value = \absint($this->getValue($data['data'], 'max_sponsored', 'set/*', 0))) > 0)
		{
			$summary .= "<div class='lws-wizard-summary-label'>" . __("Max Referrals", 'woorewards-pro') . "</div>";
			$summary .= "<div class='lws-wizard-summary-value'>{$value}</div>";
		}

		$summary .= "<div class='summary-title'>" . __("Referee Settings", 'woorewards-pro') . "</div>";
		$value = $this->getValue($data['data'], 'sponsored_amount', 'set/*');
		$summary .= "<div class='lws-wizard-summary-label'>" . sprintf(__("Referee Reward (%s)", 'woorewards-pro'), \LWS\Adminpanel\Tools\Conveniences::isWC() ? \get_woocommerce_currency_symbol() : '?') . "</div>";
		$summary .= "<div class='lws-wizard-summary-value'>{$value}</div>";

		$summary .= "</div>";
		return ($summary);

	}


	/** Instanciate pools, events, unlockables, etc. */
	function submit(&$data)
	{
		if( !isset($data['data']) )
			return false;

		/* Create Pool */
		$pool = \LWS\WOOREWARDS\Collections\Pools::instanciate()->create()->last();
		$title = $this->getValue($data['data'], 'system_title', 'ini/*', __("Referral System", 'woorewards-pro'));
		$pool->setName($title);
		$pool->ensureNameUnicity();
		$pool->setOptions(array(
			'type'      => \LWS\WOOREWARDS\Core\Pool::T_STANDARD,
			'public'    => 'yes' === $this->getValue($data['data'], 'start', 'sum/*'),
			'title'     => $title,
			'whitelist' => array(\LWS\WOOREWARDS\Core\Pool::T_STANDARD),
		));

		/* Earning method */
		$action = $this->getValue($data['data'], 'event_type', 'set/*');
		if( 'register' === $action )
		{
			$pool->addEvent(new \LWS\WOOREWARDS\PRO\Events\SponsoredRegistration(),'1');
		}
		else if( 'order' === $action )
		{
			$event = new \LWS\WOOREWARDS\Events\SponsoredOrder();
			$event->setFirstOrderOnly(true);
			$pool->addEvent($event, '1');
		}

		/* Reward */
		if(($needed = \absint($this->getValue($data['data'], 'sponsor_number', 'set/*', 0))) > 0)
		{
			$coupon = new \LWS\WOOREWARDS\PRO\Unlockables\Coupon();
			$coupon->setInPercent(false);
			$coupon->setValue($this->getValue($data['data'], 'sponsor_amount', 'set/*', 0));
			$pool->addUnlockable($coupon, $needed);
		}

		/* Sponsored */
		\update_option('lws_woorewards_event_enabled_sponsorship',true);
		if(($max = \absint($this->getValue($data['data'], 'max_sponsored', 'set/*', 0))) > 0)
		{
			\update_option('lws_woorewards_event_enabled_sponsorship',$max);
		}

		/* Sponsored Reward */
		$coupon = $this->getOrCreateSponsoredReward();
		$coupon->setInPercent(false);
		$coupon->setValue($this->getValue($data['data'], 'sponsored_amount', 'set/*', 0));
		$dummy = \LWS\WOOREWARDS\Collections\Pools::instanciate()->create('dummy')->last();
		$coupon->save($dummy);
		\update_post_meta($coupon->getId(), 'wre_sponsored_reward', 'yes');

		/* Save Loyalty System and Redirect */
		$pool->save();
		if( !$pool->getId() )
			return false;
		else
		{
			return \add_query_arg(array('page'=>LWS_WOOREWARDS_PAGE.'.loyalty', 'tab'=>'wr_loyalty.'.$pool->getTabId()), admin_url('admin.php'));
			//return \add_query_arg('page', LWS_WOOREWARDS_PAGE.'.loyalty', \admin_url('admin.php'));
		}
	}

	/** Load any Sponsored Reward if exists.
	 *	Delete it if type changed, then create a new instance.
	 *	Return cleaned existant if same type or create a new one if none exists yet.
	 *	@return \LWS\WOOREWARDS\PRO\Unlockables\Coupon instance,
	 *	It is up to you to set values and save it. */
	private function getOrCreateSponsoredReward()
	{
		$coupon = new \LWS\WOOREWARDS\PRO\Unlockables\Coupon();
		$unlockables = \LWS\WOOREWARDS\Collections\Unlockables::instanciate()->load(array(
			'meta_query'  => array(
				array(
					'key'     => 'wre_sponsored_reward',
					'value'   => 'yes',
					'compare' => 'LIKE'
				)
			)
		))->asArray();

		if( $unlockables )
		{
			for( $i=0 ; $i<count($unlockables) ; ++$i )
			{
				if( $coupon->getType() === $unlockables[$i]->getType() )
				{
					$coupon->id = $unlockables[$i]->getId(); // reuse same id
					unset($unlockables[$i]);
					break;
				}
			}
			foreach( $unlockables as $doomed )
				$doomed->delete();
		}
		return $coupon;
	}
}
