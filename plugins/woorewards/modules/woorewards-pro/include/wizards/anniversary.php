<?php

namespace LWS\WOOREWARDS\Pro\Wizards;

// don't call the file directly
if (!defined('ABSPATH')) exit();

/** satic class to manage activation and version updates. */
class Anniversary extends \LWS\WOOREWARDS\Wizards\Subwizard
{
	function init()
	{
		$this->color = '#821d47';
		$this->wizard->setColor($this->color);
	}

	function getHierarchy()
	{
		// first step must be named 'ini' as the faky one from wizard.php
		return array(
			'set',
			'sum',
		);
	}

	function getStepTitle($slug)
	{
		switch ($slug) {
			case 'set':
				return __("Customer Birthday or Registration Anniversary", 'woorewards-pro');
			case 'sum':
				return __("Summary", 'woorewards-pro');
		}
		return $slug;
	}

	function getPage($slug, $mode = '')
	{
		switch ($slug) {
			case 'set':
				return array(
					'title' => $this->getStepTitle($slug),
					'help'  => __("Welcome to this Wizard. This tool will help you do one of the following things :", 'woorewards-pro') .
						"<br/><ul><li>" .
						__("Reward customers for their birthday (requires more settings and GDPR compliance. Details explained below).", 'woorewards-pro') .
						"</li><li>" .
						__("Reward customers on their registration anniversary.", 'woorewards-pro') .
						"</li></ul><br/>" .
						__("You can change the settings later in the loyalty system.", 'woorewards-pro'),
					'groups' => array(
						array(
							'value-columns' => '1fr 1fr',
							'fields'  => array(
								array(
									'id'    => 'type',
									'title' => __('Occasion to celebrate', 'woorewards-pro'),
									'type'  => 'radiogrid',
									'extra' => array(
										'type' => 'auto-cols',
										'columns' => 'repeat(auto-fit, minmax(120px, 1fr))',
										'source' => array(
											array('value' => 'birthday', 'icon' => 'lws-icon lws-icon-birthday-cake', 'label' => __("Customer's Birthday", 'woorewards-pro')),
											array('value' => 'anniversary', 'icon' => 'lws-icon lws-icon-calendar', 'label' => __("Registration Anniversary", 'woorewards-pro')),
										),
										'default' => 'birthday',
									),
								),
							),
						),
						array(
							'require' => array('selector' => 'input#type', 'value' => 'birthday'),
							'value-columns' => '1fr 1fr',
							'fields'  => array(
								array(
									'id'    => 'add_birthday_field',
									'title' => __("Add Birthday Field ?", 'woorewards-pro'),
									'type'  => 'radiogrid', // radiogrid is specific to the wizard
									'extra' => array(
										'type' => 'auto-cols',
										'columns' => 'repeat(auto-fit, minmax(120px, 1fr))',
										'source' => array(
											array('value' => 'yes', 'label' => __("Yes", 'woorewards-pro')),
											array('value' => 'no', 'label' => __("No", 'woorewards-pro')),
										),
										'default' => 'yes',
										'help' => __("Do you want to add a Birthday entry field in the various registrations forms (Checkout, My Account ...) ?", 'woorewards-pro')."<br/>".
										sprintf(__("If you sell in the EU, you have to comply with %s", 'woorewards-pro'), "<a href='https://plugins.longwatchstudio.com/docs/woorewards/frequently-asked-questions/is-woorewards-gdpr-compliant/' target='_blank'>".__("GDPR Regulation", 'woorewards-pro')."</a>"),
									),
								),
								array(
									'id'    => 'days_before',
									'title' => __("Before birthday delay", 'woorewards-pro'),
									'type'  => 'text',
									'extra' => array(
										'pattern' => "\\d*",
										'placeholder' => __("Number | Empty to ignore", 'woorewards-pro'),
										'help' => __("If you set a value, the customer will receive an email with his reward X days before his birthday. If you don't, they will receive the reward for his birthday.", 'woorewards-pro'),
									),
								),
							),
						),
						array(
							'fields' => array(
								array(
									'id'    => 'reward_amount',
									'title' => sprintf(__("Reward Amount (%s)", 'woorewards-pro'), \LWS\Adminpanel\Tools\Conveniences::isWC() ? \get_woocommerce_currency_symbol() : '?'),
									'type'  => 'text',
									'extra' => array(
										'pattern' => "\\d*",
										'placeholder' => sprintf(__("Number in %s", 'woorewards-pro'), \LWS\Adminpanel\Tools\Conveniences::isWC() ? \get_woocommerce_currency_symbol() : '?'),
									),
								),
							)
							),
						array(
							'value-columns' => 'auto',
							'fields' => array(
								array(
									'id'    => 'message',
									'title' => __("Reward Message", 'woorewards-pro'),
									'type'  => 'text',
									'extra' => array(
										'placeholder' => __("Congratulations ! You just got a new reward", 'woorewards-pro'),
										'help' => __("Set a message that will be displayed on the email sent to customers with the reward.", 'woorewards-pro'),
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
											array('value' => 'yes', 'label' => __("Yes", 'woorewards-pro')),
											array('value' => 'no', 'label' => __("No", 'woorewards-pro')),
										),
										'default' => 'yes',
										'help' => __("Do you want to start your loyalty system at the end of this wizard ? If you select No, you'll have to start it manually later.", 'woorewards-pro'),
									),
								)
							),
						),
					)
				);
			default:
				return array();
		}
	}

	function getSummary()
	{
		$data = $this->getData();
		$exists = false;
		$summary = "<div class='lws-wizard-summary-container'>";
		/* Loyalty system name */
		$usedData = $this->getDataValue($data, 'set', false, $exists);
		$settings = reset($usedData);
		if ($settings['type'] == "birthday")
		{
			$summary .= "<div class='summary-title'>" . __("Customer's Birthday Settings", 'woorewards-pro') . "</div>";
		} else {
			$summary .= "<div class='summary-title'>" . __("Registration Anniversary Settings", 'woorewards-pro') . "</div>";
		}
		if ($settings['type'] == "birthday" && $settings['add_birthday_field']) {
			$summary .= "<div class='lws-wizard-summary-label'>" . __("Add Birthday Field ?", 'woorewards-pro') . "</div>";
			$summary .= "<div class='lws-wizard-summary-value'>{$settings['add_birthday_field']}</div>";
		}
		$validity = 2;
		if ($settings['days_before'] && $settings['days_before']>0) {
			$validity+=2;
			$summary .= "<div class='lws-wizard-summary-label'>" . __("Before event delay", 'woorewards-pro') . "</div>";
			$summary .= "<div class='lws-wizard-summary-value'>{$settings['days_before']}</div>";
		}
		$value = sprintf(__("%s%s discount, valid for %s days", 'woorewards-pro'),$settings['reward_amount'],\LWS\Adminpanel\Tools\Conveniences::isWC() ? \get_woocommerce_currency_symbol() : '?',$validity);
		$summary .= "<div class='lws-wizard-summary-label'>" . __("Reward", 'woorewards-pro') . "</div>";
		$summary .= "<div class='lws-wizard-summary-value'>{$value}</div>";

		$message = $settings['message'] ? $settings['message'] : __("Congratulations ! You just got a new reward", 'woorewards-pro');
		$summary .= "<div class='lws-wizard-summary-label'>" . __("Reward Message", 'woorewards-pro') . "</div>";
		$summary .= "<div class='lws-wizard-summary-value'>{$message}</div>";

		$summary .= "</div>";
		return ($summary);
	}

	/** @return true on success. On problem returns one (string) or several (array of string) reasons that will be shown to the user. */
	function isValid($step, &$submit)
	{
		$err = array();
		if ($step == 'set')
		{
            $amount = isset($submit['reward_amount']) ? @floatval($submit['reward_amount']) : 0.0;
			if ($amount <= 0.0)
			{
                $err[] = __("Please, set a positive reward amount", 'woorewards-pro');
            }
        }
		return $err ? $err : true;
	}

	/** Instanciate pools, events, unlockables, etc. */
	function submit(&$data)
	{
		if( !isset($data['data']) )
			return false;

		/* Earning methods & Pool */
		$pool = \LWS\WOOREWARDS\Collections\Pools::instanciate()->create()->last();
		$delay = $this->getValue($data['data'], 'days_before', 'set/*',0);
		$eventType = $this->getValue($data['data'], 'type', 'set/*');
		if( 'birthday' === $eventType )
		{
			$title = __("Customer's Birthday", 'woorewards-pro');
			if($this->getValue($data['data'], 'add_birthday_field', 'set/*'))
			{
				\update_option('lws_woorewards_registration_birthday_field','on');
				\update_option('lws_woorewards_myaccount_register_birthday_field','on');
				\update_option('lws_woorewards_myaccount_detail_birthday_field','on');
			}
			$event = new \LWS\WOOREWARDS\PRO\Events\Birthday();
			$event->setBirthdayMetaKey('billing_birth_date');
			$event->setEarlyTrigger($delay);
		}
		else if( 'anniversary' === $eventType )
		{
			$title = __("Registration Anniversary", 'woorewards-pro');
			$event = new \LWS\WOOREWARDS\PRO\Events\Anniversary();
		}
		$pool->addEvent($event,1);

		$pool->setName($title);
		$pool->setOptions(array(
			'type'      => \LWS\WOOREWARDS\Core\Pool::T_STANDARD,
			'public'    => 'yes' === $this->getValue($data['data'], 'start', 'sum/*'),
			'title'     => $title,
			'whitelist' => array(\LWS\WOOREWARDS\Core\Pool::T_STANDARD),
		));

		/* Reward */

		/* The coupon is valid for 2 days from the anniversary/birthday date. If a delay is set before birthday, we take it into account */
		$timeout = $delay + 2;

		if(($amount = \absint($this->getValue($data['data'], 'reward_amount', 'set/*', 0))) > 0)
		{
			$coupon = new \LWS\WOOREWARDS\PRO\Unlockables\Coupon();
			$coupon->setInPercent(false);
			$coupon->setValue($amount);
			$coupon->setTimeout($timeout);
			if(($message = $this->getValue($data['data'], 'message', 'set/*')) != '') $coupon->setDescription($message);
			$pool->addUnlockable($coupon, 1);
		}

		/* Save Loyalty System and Redirect */

		$pool->save();
		if( !$pool->getId() )
			return false;
		else
		{
			return \add_query_arg(array('page'=>LWS_WOOREWARDS_PAGE.'.loyalty', 'tab'=>'wr_loyalty.'.$pool->getTabId()), admin_url('admin.php'));
			//return \add_query_arg('page', LWS_WOOREWARDS_PAGE.'.loyalty', \admin_url('admin.php'));
			//return \add_query_arg('page', LWS_WOOREWARDS_PAGE.'.loyalty', \admin_url('admin.php'));
		}


	}
}
