<?php

namespace LWS\WOOREWARDS\PRO\Wizards;

// don't call the file directly
if (!defined('ABSPATH')) exit();

/** satic class to manage activation and version updates. */
class Event extends \LWS\WOOREWARDS\Wizards\Subwizard
{
	function init()
	{
		$this->wizard->setColor('#ff9a4c');
	}

	function getHierarchy()
	{
		// first step must be named 'ini' as the faky one from wizard.php
		return array(
			'ini',
			array(
				array(
					'condition' => array(
						'field' => 'choice_event',
						'value' => 'blackfriday',
					),
					'bf_set',
					'bf_sum',
				),
				array(
					'condition' => array(
						'field' => 'choice_event',
						'value' => 'christmas',
					),
					'ch_set',
					'ch_sum',
				),
				array(
					'condition' => array(
						'field' => 'choice_event',
						'value' => 'easter',
					),
					'ea_set',
					'ea_sum',
				),
			),
		);
	}

	function getStepTitle($slug)
	{
		switch ($slug) {
			case 'ini':
				return __("Event Selection", 'woorewards-pro');
			case 'bf_set':
				return __("Black Friday / Cyber Monday", 'woorewards-pro');
			case 'bf_sum':
				return __("Summary", 'woorewards-pro');
			case 'ch_set':
				return __("Christmas advent calendar", 'woorewards-pro');
			case 'ch_sum':
				return __("Summary", 'woorewards-pro');
			case 'ea_set':
				return __("Easter egg image hunt", 'woorewards-pro');
			case 'ea_sum':
				return __("Summary", 'woorewards-pro');
		}
		return $slug;
	}

	/** @return true on success. On problem returns one (string) or several (array of string) reasons that will be shown to the user. */
	function isValid($step, &$submit)
	{
		$err = array();
		if( $step == 'ini' )
		{
			if( !isset($submit['choice_event']) || !in_array($submit['choice_event'], array('blackfriday', 'christmas', 'easter')) )
				$err[] = __("The chosen event is unknown.", 'woorewards-pro');
		}
		else if( $step == 'bf_set' )
		{
			if( !isset($submit['bf_event']) || !in_array($submit['bf_event'], array('blackfriday', 'cybermonday')) )
				$err[] = __("The chosen day is unknown.", 'woorewards-pro');

			if( !$this->isIntGT0($submit, 'bf_page') )
				$err[] = __("Select a valid page or leave blank.", 'woorewards-pro');

			if( !$this->isFloatInRangeEI($submit, 'bf_reward_amount', 0.0, 100.0, false) )
				$err[] = __("'Reward Amount' expects a percentage between 0% and 100%.", 'woorewards-pro');
		}
		else if( $step == 'ch_set' )
		{
			if( !$this->isFloatGT0($submit, 'value_1', false) )
				$err[] = __("'Reward after 4 visits' expects numeric amount greater than zero.", 'woorewards-pro');

			if( !$this->isFloatInRangeEI($submit, 'value_3', 0.0, 100.0, false) )
				$err[] = __("'Reward after 12 visits' expects a percentage between 0% and 100%.", 'woorewards-pro');

			if( !$this->isFloatGT0($submit, 'value_5', false) )
				$err[] = __("'Reward after 20 visits' expects numeric amount greater than zero.", 'woorewards-pro');

			if( !$this->isIntGT0($submit, 'value_6', false) )
				$err[] = __("'Reward after 24 visits' expects a valid and purchasable product.", 'woorewards-pro');
		}
		else if( $step == 'ea_set' )
		{
			if( !$this->isIntInRangeII($submit, 'ea_count', 1, 6, false) )
				$err[] = __("'Number of images' expects a number between 1 and 6.", 'woorewards-pro');

			$dStart = $dEnd = false;

			if( !isset($submit['ea_start']) || !$submit['ea_start'] || !($dStart = \date_create($submit['ea_start'])) )
				$err[] = __("A valid 'Start Date' is expected.", 'woorewards-pro');

			if( !isset($submit['ea_end']) || !$submit['ea_end'] || !($dEnd = \date_create($submit['ea_end'])) )
				$err[] = __("A valid 'End Date' is expected.", 'woorewards-pro');

			if( $dStart && $dEnd )
			{
				if( $dStart > $dEnd )
				{
					$tmp = $submit['ea_end'];
					$submit['ea_end'] = $submit['ea_start'];
					$submit['ea_start'] = $tmp;
				}
				if( \date_create($submit['ea_end']) < \date_create() )
					$err[] = __("You have set a 'End Date' that is already outdated.", 'woorewards-pro');
			}

			if( !$this->isFloatInRangeEI($submit, 'ea_reward_amount', 0.0, 100.0, false) )
				$err[] = __("'Reward Amount' expects a percentage between 0% and 100%.", 'woorewards-pro');
		}
		return $err ? $err : true;
	}

	function getPage($slug, $mode = '')
	{
		switch ($slug) {
			case 'ini':
				return array(
					'title' => $this->getStepTitle($slug),
					'help'  => __("In this wizard, you can select one of the predefined scenarios for the following events :", 'woorewards-pro') .
						"<br/><ul><li>" .
						"<strong>" . __("Black Friday or Cyber Monday : ", 'woorewards-pro') . "</strong>" .
						__("An instant discount valid for a few days when customers visit the website", 'woorewards-pro') .
						"</li><li>" .
						"<strong>" . __("Christmas : ", 'woorewards-pro') . "</strong>" .
						__("An advent calendar system. Customers earn points for daily visits and earn rewards every 4 days.", 'woorewards-pro') .
						"</li><li>" .
						"<strong>" . __("Easter : ", 'woorewards-pro') . "</strong>" .
						__("A picture hunt on your website. Customers earn a reward if they find all pictures.", 'woorewards-pro') .
						"</li></ul>" .
						__("You can change the settings later in the loyalty system.", 'woorewards-pro'),
					'groups' => array(
						array(
							'fields'  => array(
								array(
									'id'    => 'choice_event',
									'title' => __('Select an event', 'woorewards-pro'),
									'type'  => 'radiogrid',
									'extra' => array(
										'type' => 'auto-cols',
										'columns' => 'repeat(auto-fit, minmax(120px, 1fr))',
										'source' => array(
											array('value' => 'blackfriday', 'label' => __("Black Friday", 'woorewards-pro')),
											array('value' => 'christmas', 'label' => __("Christmas", 'woorewards-pro')),
											array('value' => 'easter', 'label' => __("Easter", 'woorewards-pro')),
										),
										'default' => "blackfriday",
									),
								),
							),
						),
					)
				);
			case 'bf_set':
				return array(
					'title' => $this->getStepTitle($slug),
					'help'  => __("This wizard will give a one time discount to customers who visit your website.", 'woorewards-pro') . "<br/>" .
						__("If you select the Black Friday option, they will get 4 days to visit the website.", 'woorewards-pro') . "<br/>" .
						__("If you select the Cyber Monday option, they will only get one day.", 'woorewards-pro'),
					'groups' => array(
						array(
							'fields'  => array(
								array(
									'id'    => 'bf_event',
									'title' => __('Select an Event', 'woorewards-pro'),
									'type'  => 'radiogrid',
									'extra' => array(
										'type' => 'auto-cols',
										'columns' => 'repeat(auto-fit, minmax(120px, 1fr))',
										'source' => array(
											array('value' => 'blackfriday', 'label' => __("Black Friday", 'woorewards-pro')),
											array('value' => 'cybermonday', 'label' => __("Cyber Monday", 'woorewards-pro')),
										),
										'default' => "blackfriday",
									),
								),
								array(
									'id'    => 'bf_page',
									'title' => __('Page to visit', 'woorewards-pro'),
									'type'  => 'lacselect',
									'extra' => array(
										'predefined' => 'page',
										'help' => __("Select a page to visit to earn the discount.", 'woorewards-pro') . "<br/>" .
											__("If you don't set one, a simple visit on the website will do.", 'woorewards-pro'),
									),
								),
								array(
									'id'    => 'bf_reward_amount',
									'title' => __("Reward Amount (%)", 'woorewards-pro'),
									'type'  => 'text',
									'extra' => array(
										//'pattern' => "\\d*",
										'placeholder' => __("Number in %", 'woorewards-pro'),
									),
								),
							),
						),
					)
				);
			case 'bf_sum':
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
										'content' => $this->getBfSummary(),
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
			case 'ch_set':
				return array(
					'title' => $this->getStepTitle($slug),
					'help'  => __("In this wizard, customers will earn points by visiting your website everyday.", 'woorewards-pro') . "<br/>" .
						__("Every 4 visits, they will earn a new reward.", 'woorewards-pro') . "<br/>" .
						__("If they visit your website every day from 1 to 24 december, they will get a free product.", 'woorewards-pro'),
					'groups' => array(
						array(
							'class' => 'horizontal',
							'columns' => '200px 1fr 1fr',
							'groups' => array(
								array(
									'title' => '&nbsp;',
									'class' => 'label-only',
									'fields' => array(
										array(
											'id'    => 'title_4',
											'title' => __("Reward after 4 visits", 'woorewards-pro'),
											'type' => 'custom',
										),
										array(
											'id'    => 'title_8',
											'title' => __("Reward after 8 visits", 'woorewards-pro'),
											'type' => 'custom',
										),
										array(
											'id'    => 'title_12',
											'title' => __("Reward after 12 visits", 'woorewards-pro'),
											'type' => 'custom',
										),
										array(
											'id'    => 'title_16',
											'title' => __("Reward after 16 visits", 'woorewards-pro'),
											'type' => 'custom',
										),
										array(
											'id'    => 'title_20',
											'title' => __("Reward after 20 visits", 'woorewards-pro'),
											'type' => 'custom',
										),
										array(
											'id'    => 'title_24',
											'title' => __("Reward after 24 visits", 'woorewards-pro'),
											'type' => 'custom',
										),
									)
								),
								array(
									'title' => __("Reward Type", 'woorewards-pro'),
									'class' => 'value-only',
									'fields' => array(
										array(
											'id' => 'type_1',
											'type' => 'custom',
											'extra' => array(
												'content' => $this->getTitleDiv(__("Fixed Discount", 'woorewards-pro'), "#3fa9f5"),
												'gizmo' => true,
											)
										),
										array(
											'id' => 'type_2',
											'type' => 'custom',
											'extra' => array(
												'content' => $this->getTitleDiv(__("User Title", 'woorewards-pro'), "#6f89d5"),
												'gizmo' => true,
											)
										),
										array(
											'id' => 'type_3',
											'type' => 'custom',
											'extra' => array(
												'content' => $this->getTitleDiv(__("Percent Discount", 'woorewards-pro'), "#8040b0"),
												'gizmo' => true,
											)
										),
										array(
											'id' => 'type_4',
											'type' => 'custom',
											'extra' => array(
												'content' => $this->getTitleDiv(__("Free Shipping", 'woorewards-pro'), "#a02090"),
												'gizmo' => true,
											)
										),
										array(
											'id' => 'type_5',
											'type' => 'custom',
											'extra' => array(
												'content' => $this->getTitleDiv(__("Fixed Discount", 'woorewards-pro'), "#c01d50"),
												'gizmo' => true,
											)
										),
										array(
											'id' => 'type_6',
											'type' => 'custom',
											'extra' => array(
												'content' => $this->getTitleDiv(__("Free Product", 'woorewards-pro'), "#dd1d25"),
												'gizmo' => true,
											)
										),
									)
								),
								array(
									'title' => __("Reward Value", 'woorewards-pro'),
									'class' => 'value-only',
									'fields' => array(
										array(
											'id' => 'value_1',
											'type' => 'text',
											'extra' => array(
												'placeholder' => sprintf(__("Number in %s", 'woorewards-pro'), \LWS\Adminpanel\Tools\Conveniences::isWC() ? \get_woocommerce_currency_symbol() : '?'),
											)
										),
										array(
											'id' => 'value_2',
											'type' => 'text',
											'extra' => array(
												'placeholder' => __("Christmas Lover", 'woorewards-pro'),
											)
										),
										array(
											'id' => 'value_3',
											'type' => 'text',
											'extra' => array(
												'placeholder' => __("Number in %", 'woorewards-pro'),
											)
										),
										array(
											'id' => 'value_4',
											'type' => 'custom',
											'extra' => array(
												'content' => "<div class='lws-wizard-form-item-value' style='height:36px'>&nbsp;</div>",
											)
										),
										array(
											'id' => 'value_5',
											'type' => 'text',
											'extra' => array(
												'placeholder' => sprintf(__("Number in %s", 'woorewards-pro'), \LWS\Adminpanel\Tools\Conveniences::isWC() ? \get_woocommerce_currency_symbol() : '?'),
											)
										),
										array(
											'id' => 'value_6',
											'type' => 'lacselect',
											'extra' => array(
												'ajax' => 'lws_woorewards_wc_product_list',
											)
										),
									)
								),
							)
						)
					)
				);
			case 'ch_sum':
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
										'content' => $this->getChSummary(),
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
			case 'ea_set':
				$start = \date_create();
				$start->setDate($start->format('Y'), 4, 1);
				if ($start < \date_create()) $start->setDate($start->format('Y') + 1, 4, 1);
				$end = \date_create();
				$end->setDate($end->format('Y'), 4, 30);
				if ($end < \date_create()) $end->setDate($end->format('Y') + 1, 4, 30);
				return array(
					'title' => $this->getStepTitle($slug),
					'help'  => __("In this wizard, you will organize a picture hunt on your wesite with easter related images.", 'woorewards-pro') . "<br/>" .
						"<strong>" . __("At the end of the wizard, don't forget to use the images shortcodes on your website.", 'woorewards-pro') . "</strong><br/>" .
						__("You will be redirected after the wizard is finished.", 'woorewards-pro'),
					'groups' => array(
						array(
							'fields'  => array(
								array(
									'id'    => 'ea_count',
									'title' => __('Number of images', 'woorewards-pro'),
									'type'  => 'radiogrid',
									'extra' => array(
										'type' => 'auto-cols',
										'columns' => 'repeat(auto-fit, minmax(120px, 1fr))',
										'source' => array(
											array('value' => '1', 'label' => "1"),
											array('value' => '2', 'label' => "2"),
											array('value' => '3', 'label' => "3"),
											array('value' => '4', 'label' => "4"),
											array('value' => '5', 'label' => "5"),
											array('value' => '6', 'label' => "6"),
										),
										'default' => "3",
									),
								),
								array(
									'id'    => 'ea_start',
									'title' => __("Start Date", 'woorewards-pro'),
									'type'  => 'text',
									'extra' => array(
										'type' => "date",
										'default' => $start->format('Y-m-d'),
									),
								),
								array(
									'id'    => 'ea_end',
									'title' => __("End Date", 'woorewards-pro'),
									'type'  => 'text',
									'extra' => array(
										'type' => "date",
										'default' => $end->format('Y-m-d'),
									),
								),
								array(
									'id'    => 'ea_reward_amount',
									'title' => __("Reward Amount (%)", 'woorewards-pro'),
									'type'  => 'text',
									'extra' => array(
										//'pattern' => "\\d*",
										'placeholder' => __("Number in %", 'woorewards-pro'),
									),
								),
							),
						),
					)
				);
			case 'ea_sum':
				return array(
					'title' => $this->getStepTitle($slug),
					'help'  => __("You're almost done. Check your settings below and submit if you're satisfied with them.", 'woorewards-pro')."<br/>".
					"<strong>" . __("Don't forget to use the images shortcodes on your website.", 'woorewards-pro') . "</strong>",
					'groups' => array(
						array(
							'fields' => array(
								array(
									'id'    => 'summary',
									'title' => __("Settings Summary", 'woorewards-pro'),
									'type'  => 'custom', // radiogrid is specific to the wizard
									'extra' => array(
										'content' => $this->getEaSummary(),
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

	function getTitleDiv($title, $color)
	{
		$retour = <<<EOT
		<div class='title-button-div' style='background-color:{$color};'>
			<div class='text'>{$title}</div>
		</div>
EOT;

		return ($retour);
	}

	function getBfSummary()
	{
		$data = $this->getData();
		$summary = "<div class='lws-wizard-summary-container'>";
		$summary .= "<div class='summary-title'>" . __("Black Friday / Cyber Monday Settings", 'woorewards-pro') . "</div>";
		if ($this->getValue($data['data'], 'bf_event', 'bf_set/*') == "blackfriday") {
			$validity = '4';
		} else {
			$validity = '1';
		}

		if (($pageId = $this->getValue($data['data'], 'bf_page', 'bf_set/*')) != "") {
			$page = \get_post($pageId);
			$value = sprintf(__("Visit the %s page", 'woorewards-pro'), $page->post_title);
		} else {
			$value = __("Visit the website", 'woorewards-pro');
		}
		$summary .= "<div class='lws-wizard-summary-label'>" . __("Action to perform", 'woorewards-pro') . "</div>";
		$summary .= "<div class='lws-wizard-summary-value'>{$value}</div>";

		$amount = $this->getValue($data['data'], 'bf_reward_amount', 'bf_set/*');
		$value = sprintf(__("A %s%% discount coupon, valid for %s days.", 'woorewards-pro'), $amount, $validity);
		$summary .= "<div class='lws-wizard-summary-label'>" . __("Reward", 'woorewards-pro') . "</div>";
		$summary .= "<div class='lws-wizard-summary-value'>{$value}</div>";


		$summary .= "</div>";
		return ($summary);
	}

	function getChSummary()
	{
		$data = $this->getData();
		$summary = "<div class='lws-wizard-summary-container'>";
		$summary .= "<div class='summary-title'>" . __("Christmas Advent Calendar Settings", 'woorewards-pro') . "</div>";
		$value = sprintf(__("Christmas %s", 'woorewards-pro'), \date("Y"));
		$summary .= "<div class='lws-wizard-summary-label'>" . __("Loyalty System Name", 'woorewards-pro') . "</div>";
		$summary .= "<div class='lws-wizard-summary-value'>{$value}</div>";
		$value = __("Visit the website", 'woorewards-pro');
		$summary .= "<div class='lws-wizard-summary-label'>" . __("Action to perform", 'woorewards-pro') . "</div>";
		$summary .= "<div class='lws-wizard-summary-value'>{$value}</div>";

		$value = sprintf(__("%s%s Discount", 'woorewards-pro'), $this->getValue($data['data'], 'value_1', 'ch_set/*'),\LWS\Adminpanel\Tools\Conveniences::isWC() ? \get_woocommerce_currency_symbol() : '?');
		$summary .= "<div class='lws-wizard-summary-label'>" . __("Reward after 4 days : ", 'woorewards-pro') . "</div>";
		$summary .= "<div class='lws-wizard-summary-value'>{$value}</div>";

		$value = sprintf(__("User Title - %s", 'woorewards-pro'), $this->getValue($data['data'], 'value_2', 'ch_set/*'));
		$summary .= "<div class='lws-wizard-summary-label'>" . __("Reward after 8 days : ", 'woorewards-pro') . "</div>";
		$summary .= "<div class='lws-wizard-summary-value'>{$value}</div>";

		$value = sprintf(__("%s%% Discount", 'woorewards-pro'), $this->getValue($data['data'], 'value_3', 'ch_set/*'));
		$summary .= "<div class='lws-wizard-summary-label'>" . __("Reward after 12 days : ", 'woorewards-pro') . "</div>";
		$summary .= "<div class='lws-wizard-summary-value'>{$value}</div>";

		$value = __("Free Shipping Coupon", 'woorewards-pro');
		$summary .= "<div class='lws-wizard-summary-label'>" . __("Reward after 16 days : ", 'woorewards-pro') . "</div>";
		$summary .= "<div class='lws-wizard-summary-value'>{$value}</div>";

		$value = sprintf(__("%s%s Discount", 'woorewards-pro'), $this->getValue($data['data'], 'value_5', 'ch_set/*'),\LWS\Adminpanel\Tools\Conveniences::isWC() ? \get_woocommerce_currency_symbol() : '?');
		$summary .= "<div class='lws-wizard-summary-label'>" . __("Reward after 20 days : ", 'woorewards-pro') . "</div>";
		$summary .= "<div class='lws-wizard-summary-value'>{$value}</div>";

		$value = sprintf(__("Free Product - %s", 'woorewards-pro'), get_the_title($this->getValue($data['data'], 'value_6', 'ch_set/*')));
		$summary .= "<div class='lws-wizard-summary-label'>" . __("Reward after 24 days : ", 'woorewards-pro') . "</div>";
		$summary .= "<div class='lws-wizard-summary-value'>{$value}</div>";

		$summary .= "</div>";
		return ($summary);
	}

	function getEaSummary()
	{
		$data = $this->getData();
		$summary = "<div class='lws-wizard-summary-container'>";
		$summary .= "<div class='summary-title'>" . __("Easter Egg Image Hunt Settings", 'woorewards-pro') . "</div>";
		$nbimages = $this->getValue($data['data'], 'ea_count', 'ea_set/*');
		for ($i = 1; $i <= $nbimages; $i++) {
			$value = "<img class='lws-wizard-small-image' src='" . LWS_WOOREWARDS_PRO_IMG . "/easter_{$i}.png" . "'/>";
			$summary .= "<div class='lws-wizard-summary-label'>" . sprintf(__("Image to hunt n°%s", 'woorewards-pro'), $i) . "</div>";
			$summary .= "<div class='lws-wizard-summary-value'>{$value}</div>";
		}

		if ($this->getValue($data['data'], 'ea_start', 'ea_set/*') && $this->getValue($data['data'], 'ea_end', 'ea_set/*')) {
			$start = \mysql2date(\get_option('date_format'), $this->getValue($data['data'], 'ea_start', 'ea_set/*'));
			$end = \mysql2date(\get_option('date_format'), $this->getValue($data['data'], 'ea_end', 'ea_set/*'));
			$summary .= "<div class='lws-wizard-summary-label'>" . __("Start Date", 'woorewards-pro') . "</div>";
			$summary .= "<div class='lws-wizard-summary-value'>{$start}</div>";
			$summary .= "<div class='lws-wizard-summary-label'>" . __("End Date", 'woorewards-pro') . "</div>";
			$summary .= "<div class='lws-wizard-summary-value'>{$end}</div>";
		}

		$amount = $this->getValue($data['data'], 'ea_reward_amount', 'ea_set/*');
		$value = sprintf(__("A %s%% discount coupon, valid for 30 days.", 'woorewards-pro'), $amount);
		$summary .= "<div class='lws-wizard-summary-label'>" . __("Reward", 'woorewards-pro') . "</div>";
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
		$eventType = $this->getValue($data['data'], 'choice_event', 'ini/*');
		$now = \date_create();

		if($eventType == "blackfriday")
		{
			$blackfriday = \date_create('fourth thursday of november ' . $now->format('Y'))->add(new \DateInterval('P1D'));
			if( $blackfriday < $now )
				$blackfriday = \date_create('fourth thursday of november ' . ($now->format('Y')+1))->add(new \DateInterval('P1D'));
			$cybermonday = \date_create('fourth thursday of november ' . $now->format('Y'))->add(new \DateInterval('P4D'));
			if( $cybermonday < $now )
				$cybermonday = \date_create('fourth thursday of november ' . ($now->format('Y')+1))->add(new \DateInterval('P4D'));

			/* Pool Details */
			if ($this->getValue($data['data'], 'bf_event', 'bf_set/*') == "blackfriday")
			{
				/// Black Friday
				$title = sprintf(__("Black Friday %s", 'woorewards-pro'), $blackfriday->format("Y"));
				$validity = '4';
				$pool->setName($title);
				$pool->ensureNameUnicity();
				$pool->setOptions(array(
					'type'      => \LWS\WOOREWARDS\Core\Pool::T_LEVELLING,
					'public'    => 'yes' === $this->getValue($data['data'], 'start', 'bf_sum/*'),
					'title'     => $title,
					'whitelist' => array(\LWS\WOOREWARDS\Core\Pool::T_LEVELLING),
					'happening'		=> true,
					'period_start'	=> $blackfriday,
					'period_end'	=> $cybermonday,
					));
			}
			else
			{
				/// Cyber Monday
				$title = sprintf(__("Cyber Monday %s", 'woorewards-pro'), $cybermonday->format("Y"));
				$validity = '4';
				$pool->setName($title);
				$pool->ensureNameUnicity();
				$pool->setOptions(array(
					'type'      => \LWS\WOOREWARDS\Core\Pool::T_LEVELLING,
					'public'    => 'yes' === $this->getValue($data['data'], 'start', 'bf_sum/*'),
					'title'     => $title,
					'whitelist' => array(\LWS\WOOREWARDS\Core\Pool::T_LEVELLING),
					'happening'		=> true,
					'period_start'	=> $cybermonday,
					'period_end'	=> $cybermonday,
				));
			}

			/* Event */
			if (($pageId = $this->getValue($data['data'], 'bf_page', 'bf_set/*')) != "") {
				$event = new \LWS\WOOREWARDS\PRO\Events\RestrictedVisit();
				$event->setPageIds(array($pageId));
				$pool->addEvent($event,'1');
			} else {
				$event = new \LWS\WOOREWARDS\PRO\Events\Visit();
				$pool->addEvent($event,'1');
			}

			/* Reward */
			$coupon = new \LWS\WOOREWARDS\PRO\Unlockables\Coupon();
			$coupon->setInPercent(true);
			$coupon->setTimeout($validity);
			$coupon->setGroupedTitle($title);
			$coupon->setValue($this->getValue($data['data'], 'bf_reward_amount', 'bf_set/*', 0));
			$pool->addUnlockable($coupon, '1');


		}
		else if($eventType == "christmas")
		{
			/// Christmas
			$christmasStart = \date_create();
			$christmasEnd = \date_create();
			$christmasStart->setDate($christmasStart->format('Y'), 12, 1);
			$christmasEnd->setDate($christmasEnd->format('Y'), 12, 24);
			if ($christmasStart < \date_create())
			{
				$christmasStart->setDate($christmasStart->format('Y') + 1, 12, 1);
				$christmasEnd->setDate($christmasEnd->format('Y')+1, 12, 24);
			}

			/* Pool Details */
			$title = sprintf(__("Christmas %s", 'woorewards-pro'), $christmasStart->format("Y"));
			$pool->setName($title);
			$pool->ensureNameUnicity();
			$pool->setOptions(array(
				'type'      => \LWS\WOOREWARDS\Core\Pool::T_LEVELLING,
				'public'    => 'yes' === $this->getValue($data['data'], 'start', 'ch_sum/*'),
				'title'     => $title,
				'whitelist' => array(\LWS\WOOREWARDS\Core\Pool::T_LEVELLING),
				'happening'		=> true,
				'period_start'	=> $christmasStart,
				'period_end'	=> $christmasEnd,
			));

			/* Event */
			$event = new \LWS\WOOREWARDS\PRO\Events\Visit();
			$pool->addEvent($event,'1');

			/* Rewards */

			/* 4 Days */
			$coupon = new \LWS\WOOREWARDS\PRO\Unlockables\Coupon();
			$coupon->setInPercent(false);
			$coupon->setGroupedTitle(__("4 visits", 'woorewards-pro'));
			$coupon->setValue($this->getValue($data['data'], 'value_1', 'ch_set/*'));
			$pool->addUnlockable($coupon, '4');

			/* 8 Days */
			$userTitle = new \LWS\WOOREWARDS\PRO\Unlockables\UserTitle();
			$userTitle->setGroupedTitle(__("8 visits", 'woorewards-pro'));
			$userTitle->setUserTitle($this->getValue($data['data'], 'value_2', 'ch_set/*', __("Christmas Lover", 'woorewards-pro')));
			$pool->addUnlockable($userTitle, '8');

			/* 12 Days */
			$coupon = new \LWS\WOOREWARDS\PRO\Unlockables\Coupon();
			$coupon->setInPercent(true);
			$coupon->setGroupedTitle(__("12 visits", 'woorewards-pro'));
			$coupon->setValue($this->getValue($data['data'], 'value_3', 'ch_set/*', 0));
			$pool->addUnlockable($coupon, '12');

			/* 16 Days */
			$freeShipping = new \LWS\WOOREWARDS\PRO\Unlockables\FreeShipping();
			$freeShipping->setGroupedTitle(__("16 visits", 'woorewards-pro'));
			$pool->addUnlockable($freeShipping, '16');

			/* 20 Days */
			$coupon = new \LWS\WOOREWARDS\PRO\Unlockables\Coupon();
			$coupon->setInPercent(false);
			$coupon->setGroupedTitle(__("20 visits", 'woorewards-pro'));
			$coupon->setValue($this->getValue($data['data'], 'value_5', 'ch_set/*', 0));
			$pool->addUnlockable($coupon, '20');

			/* 24 Days */
			$prod = new \LWS\WOOREWARDS\PRO\Unlockables\FreeProduct();
			$prod->setGroupedTitle(__("24 visits", 'woorewards-pro'));
			$prod->setProductsIds(array($this->getValue($data['data'], 'value_6', 'ch_set/*', 0)));
			$pool->addUnlockable($prod, '24');


		}
		else if($eventType == "easter")
		{
			/// Easter
			$start = $this->getValue($data['data'], 'ea_start', 'ea_set/*');
			$end = $this->getValue($data['data'], 'ea_end', 'ea_set/*');
			$titleYear = \date_create(); $titleYear->setTimestamp(strtotime($start));
			$title = sprintf(__("Easter %s", 'woorewards-pro'), $titleYear->format("Y"));
			$pool->setName($title);
			$pool->ensureNameUnicity();
			$pool->setOptions(array(
				'type'      => \LWS\WOOREWARDS\Core\Pool::T_STANDARD,
				'public'    => 'yes' === $this->getValue($data['data'], 'start', 'ea_sum/*'),
				'title'     => $title,
				'whitelist' => array(\LWS\WOOREWARDS\Core\Pool::T_STANDARD),
				'happening'		=> true,
				'period_start'	=> $start,
				'period_end'	=> $end,
			));

			/* Events */
			$nbimages = $this->getValue($data['data'], 'ea_count', 'ea_set/*');
			for ($i = 1; $i <= $nbimages; $i++) {
				$event = new \LWS\WOOREWARDS\PRO\Events\EasterEgg();
				$imageId = $this->uploadImage(LWS_WOOREWARDS_PRO_IMG."/easter_{$i}.png", "easter_{$i}.png");
				$event->setImage($imageId);
				$pool->addEvent($event,'1');
			}

			/* Reward */
			$coupon = new \LWS\WOOREWARDS\PRO\Unlockables\Coupon();
			$coupon->setInPercent(true);
			$coupon->setValue($this->getValue($data['data'], 'ea_reward_amount', 'ea_set/*', 0));
			$pool->addUnlockable($coupon, $nbimages);

		}

		$pool->save();
		if( !$pool->getId() )
			return false;
		else
		{
			return \add_query_arg(array('page'=>LWS_WOOREWARDS_PAGE.'.loyalty', 'tab'=>'wr_loyalty.'.$pool->getTabId()), admin_url('admin.php'));
			//return \add_query_arg('page', LWS_WOOREWARDS_PAGE.'.loyalty', \admin_url('admin.php'));
		}
	}

	function uploadImage($path,$filename)
	{
		require_once(ABSPATH . 'wp-admin/includes/image.php');

		$now = \date_create();
		$subdir = $now->format('Y') . '/' . $now->format('m');
		$uploadDir = \wp_upload_dir()['basedir'] . '/' . $subdir;
		$uploadFile = $uploadDir . '/' . $filename;

		if( !file_exists($uploadDir) )
		{
			if( false === @mkdir($uploadDir, 0777, true) )
			{
				error_log("Cannot create directory in ./uploads");
				return false;
			}
		}

		if( !@copy($path, $uploadFile) )
		{
			error_log("Cannot copy image from $path to $uploadFile");
			return false;
		}

		$wp_filetype = \wp_check_filetype($filename, null );
		$attachment = array(
				'post_mime_type' => $wp_filetype['type'],
				'post_title' => sanitize_file_name($filename),
				'post_content' => '',
				'post_status' => 'inherit'
		);
		$attach_id = \wp_insert_attachment( $attachment, $uploadFile);
		$attach_data = \wp_generate_attachment_metadata( $attach_id, $uploadFile );
		\wp_update_attachment_metadata( $attach_id, $attach_data );
		return $attach_id;
	}

}
