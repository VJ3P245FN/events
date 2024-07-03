<?php
namespace LWS\WOOREWARDS\Pro\Wizards;

// don't call the file directly
if( !defined( 'ABSPATH' ) ) exit();

/** satic class to manage activation and version updates. */
class Standard extends \LWS\WOOREWARDS\Wizards\Subwizard
{
	function init()
	{
		$this->color = '#526981';
		$this->wizard->setColor($this->color);
	}

	function getHierarchy()
	{
		// first step must be named 'ini' as the faky one from wizard.php
		return array(
			'ini',
			'met',
			'rew',
			'sum',
		);
	}

	function getStepTitle($slug)
	{
		switch($slug)
		{
			case 'ini' : return __("Standard System", 'woorewards-pro');
			case 'met' : return __("Methods to earn points", 'woorewards-pro');
			case 'rew' : return __("Rewards", 'woorewards-pro');
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
					'help'  => __("Welcome to this Wizard. This tool will help you configure your first loyalty system in less than 5 minutes", 'woorewards-pro')."<br/>".
					__("Simply follow the steps and watch the magic happen.", 'woorewards-pro'),
					'groups' => array(
						array(
							'fields'  => array(
								array(
									'id'    => 'system_title',
									'title' => __('Loyalty system name', 'woorewards-pro'),
									'type'  => 'text',
									'extra' => array(
										'placeholder' => __('Standard System', 'woorewards-pro'),
										'help' => __("Name your loyalty system. If you leave it empty, it will be named automatically", 'woorewards-pro'),
									),
								),
							)
						)
					)
				);
			case 'met':
				return array(
					'title' => $this->getStepTitle($slug),
					'help'  => __("Your customers will earn points every time they perform the actions defined here.", 'woorewards-pro')
					. "<br/>" . __("Set points for all the methods you want and ignore the ones you don't want.", 'woorewards-pro')
						. "<br/>" . __("You can change all these settings later.", 'woorewards-pro'),
					'groups' => array(
						array(
							'fields' => array(
								array(
									'id'    => 'category',
									'title' => __("Select a Category", 'woorewards-pro'),
									'type'  => 'radiogrid', // radiogrid is specific to the wizard
									'extra' => array(
										'type' => 'auto-cols',
										'columns' => 'repeat(auto-fit, minmax(120px, 1fr))',
										'source' => array(
											array('value'=>'orders'	,'icon'=>'lws-icon lws-icon-supply'	,'label'=>__("Orders", 'woorewards-pro')),
											array('value'=>'product','icon'=>'lws-icon lws-icon-barcode'		,'label'=>__("Products", 'woorewards-pro')),
											array('value'=>'rate'	,'icon'=>'lws-icon lws-icon-star-full'		,'label'=>__("Rate us !", 'woorewards-pro')),
											array('value'=>'social'	,'icon'=>'lws-icon lws-icon-b-meeting'	,'label'=>__("Talk about us !", 'woorewards-pro')),
										),
										'default' => 'orders',
										'color' => $this->color,
										'help' => __("Select a category and fill in the points in the methods you want.", 'woorewards-pro')."<br/><strong>".
										__("You can then select another category and repeat the process as many times as you want.", 'woorewards-pro')."</strong>",
									),
								)
							),
						),
						array(
							'require' => array('selector' => 'input#category', 'value'=>'orders'),
							'fields'  => array(
								array(
									'id'    => 'spent_earn',
									'title' => sprintf(__("Points for each %s spent", 'woorewards-pro'), \LWS\Adminpanel\Tools\Conveniences::isWC() ? \get_woocommerce_currency_symbol() : '?'),
									'type'  => 'text',
									'extra' => array(
										//'pattern' => "\\d*",
										'placeholder' => __('Number | Empty to ignore', 'woorewards-pro'),
									),
								),
								array(
									'id'    => 'order_earn',
									'title' => __("Points on order placed", 'woorewards-pro'),
									'type'  => 'text',
									'extra' => array(
										//'pattern' => "\\d*",
										'placeholder' => __('Number | Empty to ignore', 'woorewards-pro'),
									),
								),
								array(
									'id'    => 'first_order_earn',
									'title' => __("Extra points on first order", 'woorewards-pro'),
									'type'  => 'text',
									'extra' => array(
										//'pattern' => "\\d*",
										'placeholder' => __('Number | Empty to ignore', 'woorewards-pro'),
									),
								),
							),
						),
						array(
							'require' => array('selector' => 'input#category', 'value'=>'product'),
							'fields'  => array(
								array(
									'id'    => 'product_spec_earn',
									'title' => __("Points for specific product", 'woorewards-pro'),
									'type'  => 'text',
									'extra' => array(
										//'pattern' => "\\d*",
										'placeholder' => __('Number | Empty to ignore', 'woorewards-pro'),
										'help' => __("Customers will get points when they buy a specific product.", 'woorewards-pro'),
									),
								),
								array(
									'id'    => 'product_spec',
									'title' => __("Product to buy", 'woorewards-pro'),
									'type'  => 'lacselect',
									'extra' => array(
										'ajax' => 'lws_woorewards_wc_product_list',
									),
								),
								array(
									'id'    => 'product_cat_earn',
									'title' => __("Points on category buy", 'woorewards-pro'),
									'type'  => 'text',
									'extra' => array(
										//'pattern' => "\\d*",
										'placeholder' => __('Number | Empty to ignore', 'woorewards-pro'),
										'help' => __("Customers will get points when they buy products of the following categories", 'woorewards-pro'),
									),
								),
								array(
									'id'    => 'product_categories',
									'title' => __("Product Categories", 'woorewards-pro'),
									'type'  => 'lacchecklist',
									'extra' => array(
										'predefined' => 'taxonomy',
										'spec' => array('taxonomy' => 'product_cat'),
									)
								),
							),
						),

						array(
							'require' => array('selector' => 'input#category', 'value'=>'rate'),
							'fields'  => array(
								array(
									'id'    => 'rate_earn',
									'title' => __("Points for product review", 'woorewards-pro'),
									'type'  => 'text',
									'extra' => array(
										//'pattern' => "\\d*",
										'placeholder' => __('Number | Empty to ignore', 'woorewards-pro'),
									),
								),
								array(
									'id'    => 'comment_earn',
									'title' => __("Points for comments", 'woorewards-pro'),
									'type'  => 'text',
									'extra' => array(
										//'pattern' => "\\d*",
										'placeholder' => __('Number | Empty to ignore', 'woorewards-pro'),
									),
								),
							),
						),
						array(
							'require' => array('selector' => 'input#category', 'value'=>'social'),
							'fields'  => array(
								array(
									'id'    => 'share_earn',
									'title' => __("Points for sharing", 'woorewards-pro'),
									'type'  => 'text',
									'extra' => array(
										//'pattern' => "\\d*",
										'placeholder' => __('Number | Empty to ignore', 'woorewards-pro'),
										'help' => __("Customers will get points when they click the share button.", 'woorewards-pro'),
									),
								),
								array(
									'id'    => 'click_earn',
									'title' => __("Points on clicked link", 'woorewards-pro'),
									'type'  => 'text',
									'extra' => array(
										//'pattern' => "\\d*",
										'placeholder' => __('Number | Empty to ignore', 'woorewards-pro'),
										'help' => __("Customers will get points only when other visitors click on their shared links.", 'woorewards-pro'),
									),
								),
							),
						),
					),
				);
			case 'rew':
				return array(
					'title' => $this->getStepTitle($slug),
					'help'  => __("Select the rewards for your customers.", 'woorewards-pro')."<br/>".
					__("This wizard proposes 3 default rewards but you'll have the possibility to add other reward types afterwards.", 'woorewards-pro')."<br/>".
					__("If you don't want to set one of the rewards proposed down below, leave the 'Points Needed' input empty and it will be ignored.", 'woorewards-pro'),
					'groups' => array(
						array(
							'class' => 'horizontal',
							'columns' => '150px 1fr 1fr 1fr',
							'groups' => array(
								array(
									'title' => '&nbsp;',
									'class' => 'label-only',
									'fields' => array(
										array(
											'id' => 'title_reward_type',
											'title' => __("Reward Type", 'woorewards-pro'),
											'type' => 'custom',
										),
										array(
											'id'    => 'title_needed',
											'title' => __("Points Needed", 'woorewards-pro'),
											'type'  => 'custom',
										),
										array(
											'id'    => 'title_amount',
											'title' => __("Reward Amount", 'woorewards-pro'),
											'type'  => 'custom',
										),
									)
								),
								array(
									'title' => __("First reward", 'woorewards-pro'),
									'class' => 'border value-only',
									'fields' => array(
										array(
											'id' => 'first_reward_type',
											'type' => 'custom',
											'extra' => array(
												'content' => $this->getTitleDiv(__("Fixed discount", 'woorewards-pro'), 'lws-icon lws-icon-coins'),
												'gizmo' => true,
											)
										),
										array(
											'id'    => 'first_needed',
											'type'  => 'text',
											'extra' => array(
												//'pattern' => "\\d*",
												'placeholder' => __('Number | Empty to ignore', 'woorewards-pro'),
											),
										),
										array(
											'id'    => 'first_amount',
											'type'  => 'text',
											'extra' => array(
												//'pattern' => "\\d*",
												'placeholder' => sprintf(__("Number in %s", 'woorewards-pro'), \LWS\Adminpanel\Tools\Conveniences::isWC() ? \get_woocommerce_currency_symbol() : '?'),
											),
										),
									)
								),
								array(
									'title' => __("Second reward", 'woorewards-pro'),
									'class' => 'border value-only',
									'fields' => array(
										array(
											'id' => 'second_reward_type',
											'type' => 'custom',
											'extra' => array(
												'content' => $this->getTitleDiv(__("Percentage discount", 'woorewards-pro'), 'lws-icon lws-icon-discount'),
											)
										),
										array(
											'id'    => 'second_needed',
											'type'  => 'text',
											'extra' => array(
												//'pattern' => "\\d*",
												'placeholder' => __('Number | Empty to ignore', 'woorewards-pro'),
											),
										),
										array(
											'id'    => 'second_amount',
											'type'  => 'text',
											'extra' => array(
												//'pattern' => "\\d*",
												'placeholder' => __("Number in %", 'woorewards-pro'),
											),
										),
									)
								),
								array(
									'title' => __("Third reward", 'woorewards-pro'),
									'class' => 'border value-only',
									'fields' => array(
										array(
											'id' => 'third_reward_type',
											'type' => 'custom',
											'extra' => array(
												'content' => $this->getTitleDiv(__("Free Shipping", 'woorewards-pro'), 'lws-icon lws-icon-supply'),
											)
										),
										array(
											'id'    => 'third_needed',
											'type'  => 'text',
											'extra' => array(
												//'pattern' => "\\d*",
												'placeholder' => __('Number | Empty to ignore', 'woorewards-pro'),
											),
										),
										array(
											'id'    => 'third_amount',
											'type'  => 'custom',
											'extra' => array(
												'content' => '<div class="lws-wizard-form-item-no-input lws-icon lws-icon-ban"></div>',
											),
										),
									)
								)
							),
						),
					),
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

	function getActiveStatus($tested = '')
	{
		$data = $this->getData();
		$exists = false;
		$methods = $this->getDataValue($data,'met',false,$exists);
		foreach( $methods as $method )
		{
			if($method['order_methods']== $tested)
				return 'inactive';
		}
		return ('');
	}

	function getSummary()
	{
		$data = $this->getData();
		$exists = false;
		$currency = \LWS\Adminpanel\Tools\Conveniences::isWC() ? \get_woocommerce_currency_symbol() : '?';
		$summary = '';
		$usedData =$this->getDataValue($data,'met',false,$exists);
		$methods = reset($usedData);

		if($methods['share_earn'] && $methods['share_earn']>0 || $methods['click_earn'] && $methods['click_earn']>0){
			$url = add_query_arg('page', LWS_WOOREWARDS_PAGE.'.settings', \admin_url('admin.php'));
			$url.= "&tab=sty_widgets#lws_group_targetable_social_share";
			$value = sprintf(__("Don't forget to add the %s to your pages to allow customers to earn points", 'woorewards-pro'),"<a target='_blank' href='{$url}'>".__("Social Share widget", 'woorewards-pro')."</a>");
			$summary .= "<div class='item-help visible'><div class='icon lws-icons lws-icon-bulb'></div><div class='text'>{$value}</div></div>";
		}

		$summary .= "<div class='lws-wizard-summary-container'>";
		/* Loyalty system name */
		$usedData =$this->getDataValue($data,'ini',false,$exists);
		$system = reset($usedData);
		$summary .= "<div class='summary-title'>" . __("Loyalty System", 'woorewards-pro') . "</div>";
		$value = ($system['system_title']) ? $system['system_title'] : __("Standard System", 'woorewards-pro');
		$summary .= "<div class='lws-wizard-summary-label'>".__("Loyalty System Name", 'woorewards-pro')."</div>";
		$summary .= "<div class='lws-wizard-summary-value'>{$value}</div>";

		/* Earning methods */
		$summary .= "<div class='summary-title'>" . __("Methods to earn points", 'woorewards-pro') . "</div>";
		if($methods['spent_earn'] && $methods['spent_earn']>0){
			$value = sprintf(__(' %s points earned for each %s spent', 'woorewards-pro'),$methods['spent_earn'],$currency);
			$summary .= "<div class='lws-wizard-summary-label'>".__("Spend Money", 'woorewards-pro')."</div>";
			$summary .= "<div class='lws-wizard-summary-value'>{$value}</div>";
		}
		if($methods['order_earn'] && $methods['order_earn']>0){
			$value = sprintf(__(' %s points for each placed order', 'woorewards-pro'),$methods['order_earn']);
			$summary .= "<div class='lws-wizard-summary-label'>".__("Place an order", 'woorewards-pro')."</div>";
			$summary .= "<div class='lws-wizard-summary-value'>{$value}</div>";
		}
		if($methods['first_order_earn'] && $methods['first_order_earn']>0){
			$value = sprintf(__(' %s extra points for the first order', 'woorewards-pro'),$methods['first_order_earn']);
			$summary .= "<div class='lws-wizard-summary-label'>".__("Place a first order", 'woorewards-pro')."</div>";
			$summary .= "<div class='lws-wizard-summary-value'>{$value}</div>";
		}
		if($methods['product_spec_earn'] && $methods['product_spec_earn']>0){
			if($methods['product_spec']){
				$value = sprintf(__(' %s points for buying %s', 'woorewards-pro'),$methods['product_spec_earn'], get_the_title($methods['product_spec']) );
				$summary .= "<div class='lws-wizard-summary-label'>".__("Buy a product", 'woorewards-pro')."</div>";
				$summary .= "<div class='lws-wizard-summary-value'>{$value}</div>";
			}
		}
		if($methods['product_cat_earn'] && $methods['product_cat_earn']>0){
			if($methods['product_categories']){
				$catlist = '';
				foreach($methods['product_categories'] as $category)
				{
					$thecat = get_term_by('id', intval($category), 'product_cat')->to_array();
					$catlist .= $thecat['name'] . ' ';
				}
				$value = sprintf(__(' %s points for buying in the categories : %s', 'woorewards-pro'),$methods['product_cat_earn'], $catlist );
				$summary .= "<div class='lws-wizard-summary-label'>".__("Buy in product categories", 'woorewards-pro')."</div>";
				$summary .= "<div class='lws-wizard-summary-value'>{$value}</div>";
			}
		}
		if($methods['rate_earn'] && $methods['rate_earn']>0){
			$value = sprintf(__(' %s points on product review', 'woorewards-pro'),$methods['rate_earn']);
			$summary .= "<div class='lws-wizard-summary-label'>".__("Review a product", 'woorewards-pro')."</div>";
			$summary .= "<div class='lws-wizard-summary-value'>{$value}</div>";
		}
		if($methods['comment_earn'] && $methods['comment_earn']>0){
			$value = sprintf(__(' %s points on post or page comment', 'woorewards-pro'),$methods['comment_earn']);
			$summary .= "<div class='lws-wizard-summary-label'>".__("Comment a post", 'woorewards-pro')."</div>";
			$summary .= "<div class='lws-wizard-summary-value'>{$value}</div>";
		}
		if($methods['share_earn'] && $methods['share_earn']>0){
			$value = sprintf(__(' %s points for sharing on social media', 'woorewards-pro'),$methods['share_earn']);
			$summary .= "<div class='lws-wizard-summary-label'>".__("Share a link", 'woorewards-pro')."</div>";
			$summary .= "<div class='lws-wizard-summary-value'>{$value}</div>";
		}
		if($methods['click_earn'] && $methods['click_earn']>0){
			$value = sprintf(__(' %s points when a shared link is clicked', 'woorewards-pro'),$methods['click_earn']);
			$summary .= "<div class='lws-wizard-summary-label'>".__("Clicked shared link", 'woorewards-pro')."</div>";
			$summary .= "<div class='lws-wizard-summary-value'>{$value}</div>";
		}
		/* Rewards */
		$usedData =$this->getDataValue($data,'rew',false,$exists);
		$rewards = reset($usedData);
		$summary .= "<div class='summary-title'>" . __("Rewards", 'woorewards-pro') . "</div>";
		$rewlabels = array(
			__("First Reward", 'woorewards-pro'),
			__("Second Reward", 'woorewards-pro'),
			__("Third Reward", 'woorewards-pro'),
		);
		$count = 0;
		if($rewards['first_needed'] && $rewards['first_needed']>0 && $rewards['first_amount'] && $rewards['first_amount']>0){
			$value = sprintf(__('%s%s discount for %s points', 'woorewards-pro'),$rewards['first_amount'],$currency,$rewards['first_needed']);
			$summary .= "<div class='lws-wizard-summary-label'>".$rewlabels[$count]."</div>";
			$summary .= "<div class='lws-wizard-summary-value'>{$value}</div>";
			$count += 1 ;
		}
		if($rewards['second_needed'] && $rewards['second_needed']>0 && $rewards['second_amount'] && $rewards['second_amount']>0){
			$value = sprintf(__('%s percent discount for %s points', 'woorewards-pro'),$rewards['second_amount'],$rewards['second_needed']);
			$summary .= "<div class='lws-wizard-summary-label'>".$rewlabels[$count]."</div>";
			$summary .= "<div class='lws-wizard-summary-value'>{$value}</div>";
			$count += 1 ;
		}
		if($rewards['third_needed'] && $rewards['third_needed']>0){
			$value = sprintf(__('Free shipping for %s points', 'woorewards-pro'),$rewards['third_needed']);
			$summary .= "<div class='lws-wizard-summary-label'>".$rewlabels[$count]."</div>";
			$summary .= "<div class='lws-wizard-summary-value'>{$value}</div>";
			$count += 1 ;
		}

		$summary .= "</div>";
		return ($summary);

	}

	function getTitleDiv($title = '', $icon='')
	{
		$retour = <<<EOT
		<div class='title-button-div'>
			<div class='icon {$icon}'></div>
			<div class='text'>{$title}</div>
		</div>
EOT;

		return ($retour);
	}

	/** @return true on success. On problem returns one (string) or several (array of string) reasons that will be shown to the user. */
	function isValid($step, &$submit)
	{
		$err = array();
		if( $step == 'met' )
		{
			// $submit['category'] == 'orders'
			if( !$this->isIntGE0($submit, 'spent_earn') )
				$err[] = sprintf(__("Points for each %s spent expects numeric value greater than zero or leave blank.", 'woorewards-pro'), \LWS\Adminpanel\Tools\Conveniences::isWC() ? \get_woocommerce_currency_symbol() : '?');

			if( !$this->isIntGE0($submit, 'order_earn') )
				$err[] = __("Points on order placed expects numeric value greater than zero or leave blank.", 'woorewards-pro');

			if( !$this->isIntGE0($submit, 'first_order_earn') )
				$err[] = __("Extra points on first order expects numeric value greater than zero or leave blank.", 'woorewards-pro');

			// $submit['category'] == 'product'
			if( !$this->isIntGE0($submit, 'product_spec_earn') )
			{
				$err[] = __("Points for specific product expect numeric value or leave blank.", 'woorewards-pro');
			}
			else if( 0 < intval($submit['product_spec_earn']) )
			{
				if( !@intval(isset($submit['product_spec']) ? trim($submit['product_spec']) : '') )
					$err[] = __("Please, select a specific product or don't set points.", 'woorewards-pro');
			}

			if( !$this->isIntGE0($submit, 'product_cat_earn') )
			{
				$err[] = __("Points on category buy expect numeric value or leave blank.", 'woorewards-pro');
			}
			else if( 0 < intval($submit['product_cat_earn']) )
			{
				$categories = isset($submit['product_categories']) ? $submit['product_categories'] : false;
				if( !$categories || !is_array($categories) )
					$err[] = __("Please, select at least one product category or do not set points.", 'woorewards-pro');
			}

			// $submit['category'] == 'rate'
			if( !$this->isIntGE0($submit, 'rate_earn') )
				$err[] = __("Points for product review expects numeric value greater than zero or leave blank.", 'woorewards-pro');
			if( !$this->isIntGE0($submit, 'comment_earn') )
				$err[] = __("Points for comments expects numeric value greater than zero or leave blank.", 'woorewards-pro');

			// $submit['category'] == 'social'
			if( !$this->isIntGE0($submit, 'share_earn') )
				$err[] = __("Points for sharing expects numeric value greater than zero or leave blank.", 'woorewards-pro');
			if( !$this->isIntGE0($submit, 'click_earn') )
				$err[] = __("Points on clicked link expects numeric value greater than zero or leave blank.", 'woorewards-pro');
		}
		else if( $step == 'rew' )
		{
			if( !$this->isIntGT0($submit, 'first_needed') )
			{
				$err[] = __("Points Needed for First reward expects numeric value or leave blank.", 'woorewards-pro');
			}
			else if( 0 < intval($submit['first_needed']) )
			{
				if( !$this->isFloatGT0($submit, 'first_amount', false) )
					$err[] = __("Please, set a positive First reward amount or do not set points needed.", 'woorewards-pro');
			}

			if( !$this->isIntGT0($submit, 'second_needed') )
			{
				$err[] = __("Points Needed for Second reward expects numeric value or leave blank.", 'woorewards-pro');
			}
			else if( 0 < intval($submit['second_needed']) )
			{
				if( !$this->isFloatInRangeEI($submit, 'second_amount', 0.0, 100.0, false) )
					$err[] = __("Please, set a positive Second reward percentage between 0% and 100% or do not set points needed.", 'woorewards-pro');
			}

			if( !$this->isIntGT0($submit, 'third_needed') )
			{
				$err[] = __("Points Needed for Third reward expects numeric value or leave blank.", 'woorewards-pro');
			}
		}
		return $err ? $err : true;
	}

	/** Instanciate pools, events, unlockables, etc. */
	function submit(&$data)
	{
		if( !isset($data['data']) )
			return false;

		/* Create Pool */
		$pool = \LWS\WOOREWARDS\Collections\Pools::instanciate()->create()->last();
		$title = $this->getValue($data['data'], 'system_title', 'ini/*', __("Standard System", 'woorewards-pro'));
		$pool->setName($title);
		$pool->setOptions(array(
			'type'      => \LWS\WOOREWARDS\Core\Pool::T_STANDARD,
			'public'    => 'yes' === $this->getValue($data['data'], 'start', 'sum/*'),
			'title'     => $title,
			'whitelist' => array(\LWS\WOOREWARDS\Core\Pool::T_STANDARD),
		));

		/* Earning methods */
		if(($value = \absint($this->getValue($data['data'], 'spent_earn', 'met/*', 0))) > 0) $pool->addEvent(new \LWS\WOOREWARDS\PRO\Events\OrderAmount(),$value);
		if(($value = \absint($this->getValue($data['data'], 'order_earn', 'met/*', 0))) > 0) $pool->addEvent(new \LWS\WOOREWARDS\PRO\Events\OrderCompleted(),$value);
		if(($value = \absint($this->getValue($data['data'], 'first_order_earn', 'met/*', 0))) > 0) $pool->addEvent(new \LWS\WOOREWARDS\PRO\Events\FirstOrder(),$value);
		if(($value = \absint($this->getValue($data['data'], 'rate_earn', 'met/*', 0))) > 0) $pool->addEvent(new \LWS\WOOREWARDS\Events\ProductReview(),$value);
		if(($value = \absint($this->getValue($data['data'], 'comment_earn', 'met/*', 0))) > 0) $pool->addEvent(new \LWS\WOOREWARDS\PRO\Events\PostComment(),$value);
		if(($value = \absint($this->getValue($data['data'], 'share_earn', 'met/*', 0))) > 0) $pool->addEvent(new \LWS\WOOREWARDS\PRO\Events\SocialSharing(),$value);
		if(($value = \absint($this->getValue($data['data'], 'click_earn', 'met/*', 0))) > 0) $pool->addEvent(new \LWS\WOOREWARDS\PRO\Events\SocialBacklink(),$value);
		if(($value = \absint($this->getValue($data['data'], 'product_spec_earn', 'met/*', 0))) > 0)
		{
			$event = new \LWS\WOOREWARDS\PRO\Events\BuySpecificProduct();
			$event->setProductsIds(array($this->getValue($data['data'], 'product_spec', 'met/*', 0)));
			$pool->addEvent($event,$value);
		}
		if(($value = \absint($this->getValue($data['data'], 'product_cat_earn', 'met/*', 0))) > 0)
		{
			$event = new \LWS\WOOREWARDS\PRO\Events\BuyInCategory();
			$categories = $this->getValue($data['data'], 'product_categories', 'met/*', 0);
			$event->setProductCategories($categories);
			$pool->addEvent($event,$value);
		}

		/* Rewards */
		if(($needed = \absint($this->getValue($data['data'], 'first_needed', 'rew/*', 0))) > 0)
		{
			$coupon = new \LWS\WOOREWARDS\PRO\Unlockables\Coupon();
			$coupon->setInPercent(false);
			$coupon->setValue($this->getValue($data['data'], 'first_amount', 'rew/*', 0));
			$pool->addUnlockable($coupon, $needed);
		}
		if(($needed = \absint($this->getValue($data['data'], 'second_needed', 'rew/*', 0))) > 0)
		{
			$coupon = new \LWS\WOOREWARDS\PRO\Unlockables\Coupon();
			$coupon->setInPercent(true);
			$coupon->setValue($this->getValue($data['data'], 'second_amount', 'rew/*', 0));
			$pool->addUnlockable($coupon, $needed);
		}
		if(($needed = \absint($this->getValue($data['data'], 'third_needed', 'rew/*', 0))) > 0)
		{
			$freeShipping = new \LWS\WOOREWARDS\PRO\Unlockables\FreeShipping();
			$pool->addUnlockable($freeShipping, $needed);
		}

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
}
