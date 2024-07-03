<?php
namespace LWS\WOOREWARDS\Pro\Wizards;

// don't call the file directly
if( !defined( 'ABSPATH' ) ) exit();

/** satic class to manage activation and version updates. */
class Leveling extends \LWS\WOOREWARDS\Wizards\Subwizard
{
	function init()
	{
		$this->color = '#00a0ac';
		$this->wizard->setColor($this->color);
	}

	function getHierarchy()
	{
		// first step must be named 'ini' as the faky one from wizard.php
		return array(
			'ini',
			'met',
			'lev',
			'sum',
		);
	}

	function getStepTitle($slug)
	{
		switch($slug)
		{
			case 'ini' : return __("Leveling System", 'woorewards-pro');
			case 'met' : return __("Methods to earn points", 'woorewards-pro');
			case 'lev' : return __("Levels and Rewards", 'woorewards-pro');
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
					'help'  => __("Welcome to this Wizard. This tool will help you configure your first leveling system.", 'woorewards-pro')."<br/>".
					__("In a leveling system, customers keep earning points. When they have enough points, they unlock levels and all rewards attached to it", 'woorewards-pro')."<br/><br/>".
					"<strong>".__("In this system, you will create a bronze/silver/gold system and customers will earn permanent discounts and special titles.", 'woorewards-pro')."</strong>",
					'groups' => array(
						array(
							'fields'  => array(
								array(
									'id'    => 'system_title',
									'title' => __('Loyalty system name', 'woorewards-pro'),
									'type'  => 'text',
									'extra' => array(
										'placeholder' => $this->getStepTitle('ini'),
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
					'help'  => __("Your customers will earn points every time they perform the actions defined here.", 'woorewards-pro')."<br/>".
					__("Set points for all the methods you want and ignore the ones you don't want. You can change all these settings later.", 'woorewards-pro'),
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
										'pattern' => "\\d*",
										'placeholder' => __('Number | Empty to ignore', 'woorewards-pro'),
									),
								),
								array(
									'id'    => 'order_earn',
									'title' => sprintf(__("Points on order placed", 'woorewards-pro'), \LWS\Adminpanel\Tools\Conveniences::isWC() ? \get_woocommerce_currency_symbol() : '?'),
									'type'  => 'text',
									'extra' => array(
										'pattern' => "\\d*",
										'placeholder' => __('Number | Empty to ignore', 'woorewards-pro'),
									),
								),
								array(
									'id'    => 'first_order_earn',
									'title' => sprintf(__("Extra points on first order", 'woorewards-pro'), \LWS\Adminpanel\Tools\Conveniences::isWC() ? \get_woocommerce_currency_symbol() : '?'),
									'type'  => 'text',
									'extra' => array(
										'pattern' => "\\d*",
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
									'title' => sprintf(__("Points for specific product", 'woorewards-pro'), \LWS\Adminpanel\Tools\Conveniences::isWC() ? \get_woocommerce_currency_symbol() : '?'),
									'type'  => 'text',
									'extra' => array(
										'pattern' => "\\d*",
										'placeholder' => __('Number | Empty to ignore', 'woorewards-pro'),
										'help' => __("Customers will get points when they buy a specific product.", 'woorewards-pro'),
									),
								),
								array(
									'id'    => 'product_spec',
									'title' => sprintf(__("Product to buy", 'woorewards-pro'), \LWS\Adminpanel\Tools\Conveniences::isWC() ? \get_woocommerce_currency_symbol() : '?'),
									'type'  => 'lacselect',
									'extra' => array(
										'ajax' => 'lws_woorewards_wc_product_list',
									),
								),
								array(
									'id'    => 'product_cat_earn',
									'title' => sprintf(__("Points on category buy", 'woorewards-pro'), \LWS\Adminpanel\Tools\Conveniences::isWC() ? \get_woocommerce_currency_symbol() : '?'),
									'type'  => 'text',
									'extra' => array(
										'pattern' => "\\d*",
										'placeholder' => __('Number | Empty to ignore', 'woorewards-pro'),
										'help' => __("Customers will get points when they buy products of the following categories", 'woorewards-pro'),
									),
								),
								array(
									'id'    => 'product_categories',
									'title' => sprintf(__("Product Categories", 'woorewards-pro'), \LWS\Adminpanel\Tools\Conveniences::isWC() ? \get_woocommerce_currency_symbol() : '?'),
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
									'title' => sprintf(__("Points for product review", 'woorewards-pro'), \LWS\Adminpanel\Tools\Conveniences::isWC() ? \get_woocommerce_currency_symbol() : '?'),
									'type'  => 'text',
									'extra' => array(
										'pattern' => "\\d*",
										'placeholder' => __('Number | Empty to ignore', 'woorewards-pro'),
									),
								),
								array(
									'id'    => 'comment_earn',
									'title' => sprintf(__("Points for comments", 'woorewards-pro'), \LWS\Adminpanel\Tools\Conveniences::isWC() ? \get_woocommerce_currency_symbol() : '?'),
									'type'  => 'text',
									'extra' => array(
										'pattern' => "\\d*",
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
									'title' => sprintf(__("Points for sharing", 'woorewards-pro'), \LWS\Adminpanel\Tools\Conveniences::isWC() ? \get_woocommerce_currency_symbol() : '?'),
									'type'  => 'text',
									'extra' => array(
										'pattern' => "\\d*",
										'placeholder' => __('Number | Empty to ignore', 'woorewards-pro'),
										'help' => __("Customers will get points when they click the share button.", 'woorewards-pro'),
									),
								),
								array(
									'id'    => 'click_earn',
									'title' => sprintf(__("Points on clicked link", 'woorewards-pro'), \LWS\Adminpanel\Tools\Conveniences::isWC() ? \get_woocommerce_currency_symbol() : '?'),
									'type'  => 'text',
									'extra' => array(
										'pattern' => "\\d*",
										'placeholder' => __('Number | Empty to ignore', 'woorewards-pro'),
										'help' => __("Customers will get points only when other visitors click on their shared links.", 'woorewards-pro'),
									),
								),
							),
						),
					),
				);
			case 'lev':
				return array(
					'title' => $this->getStepTitle($slug),
					'help'  => __("For each level, customers will receive 2 rewards, a title and a permanent discount.", 'woorewards-pro')."<br/>".
					__("When customers reach a new level, rewards replace the rewards from the previous level. They're not cumulative", 'woorewards-pro')."<br/>".
					__("The values proposed by default will be set if you don't enter your own values", 'woorewards-pro')."<br/>".
					__("Remember that you can change all these settings later", 'woorewards-pro'),
					'groups' => array(
						array(
							'class' => 'horizontal',
							'columns' => '250px 210px 210px 210px',
							'groups' => array(
								array(
									'title' => '&nbsp;',
									'class' => 'label-only',
									'fields' => array(
										array(
											'id' => 'title_level',
											'title' => __("Level Name", 'woorewards-pro'),
											'type' => 'custom',
										),
										array(
											'id'    => 'title_needed',
											'title' => __("Points to reach the level", 'woorewards-pro'),
											'type'  => 'custom',
										),
										array(
											'id'    => 'title_amount',
											'title' => __("Permanent discount % amount", 'woorewards-pro'),
											'type'  => 'custom',
										),
										array(
											'id'    => 'title_title',
											'title' => __("Customer title", 'woorewards-pro'),
											'type'  => 'custom',
										),
									)
								),
								array(
									'title' => __("First level", 'woorewards-pro'),
									'class' => 'border value-only',
									'fields' => array(
										array(
											'id' => 'first_level_name',
											'type' => 'custom',
											'extra' => array(
												'content' => $this->getTitleDiv(__("Bronze Level", 'woorewards-pro'),"#8a4000"),
												'gizmo' => true,
											)
										),
										array(
											'id'    => 'first_needed',
											'type'  => 'text',
											'extra' => array(
												'default' => '100',
												'pattern' => "\\d*",
												'placeholder' => __('Number', 'woorewards-pro'),
											),
										),
										array(
											'id'    => 'first_amount',
											'type'  => 'text',
											'extra' => array(
												'default' => '2',
												'pattern' => "\\d*",
												'placeholder' => __('Number in % ', 'woorewards-pro'),
											),
										),
										array(
											'id'    => 'first_title',
											'type'  => 'text',
											'extra' => array(
												'default' => __("Bronze Member", 'woorewards-pro'),
												'placeholder' => __('Title Name', 'woorewards-pro'),
											),
										),
									)
								),
								array(
									'title' => __("Second level", 'woorewards-pro'),
									'class' => 'border value-only',
									'fields' => array(
										array(
											'id' => 'second_level_name',
											'type' => 'custom',
											'extra' => array(
												'content' => $this->getTitleDiv(__("Silver Level", 'woorewards-pro'),"#88878e"),
												'gizmo' => true,
											)
										),
										array(
											'id'    => 'second_needed',
											'type'  => 'text',
											'extra' => array(
												'default' => '300',
												'pattern' => "\\d*",
												'placeholder' => __('Number', 'woorewards-pro'),
											),
										),
										array(
											'id'    => 'second_amount',
											'type'  => 'text',
											'extra' => array(
												'default' => '5',
												'pattern' => "\\d*",
												'placeholder' => __('Number in % ', 'woorewards-pro'),
											),
										),
										array(
											'id'    => 'second_title',
											'type'  => 'text',
											'extra' => array(
												'default' => __("Silver Member", 'woorewards-pro'),
												'placeholder' => __('Title Name', 'woorewards-pro'),
											),
										),
									)
								),
								array(
									'title' => __("Third level", 'woorewards-pro'),
									'class' => 'border value-only',
									'fields' => array(
										array(
											'id' => 'third_level_name',
											'type' => 'custom',
											'extra' => array(
												'content' => $this->getTitleDiv(__("Gold Level", 'woorewards-pro'),"#d4af37"),
												'gizmo' => true,
											)
										),
										array(
											'id'    => 'third_needed',
											'type'  => 'text',
											'extra' => array(
												'default' => '500',
												'pattern' => "\\d*",
												'placeholder' => __('Number', 'woorewards-pro'),
											),
										),
										array(
											'id'    => 'third_amount',
											'type'  => 'text',
											'extra' => array(
												'default' => '10',
												'pattern' => "\\d*",
												'placeholder' => __('Number in % ', 'woorewards-pro'),
											),
										),
										array(
											'id'    => 'third_title',
											'type'  => 'text',
											'extra' => array(
												'default' => __("Gold Member", 'woorewards-pro'),
												'placeholder' => __('Title Name', 'woorewards-pro'),
											),
										),
									)
								),
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
										'help' => __("Do you want to start your loyalty system at the end of this wizard ? If you select No, you'll have to start it manually later.", 'woorewards-pro'),
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
		$value = ($system['system_title']) ? $system['system_title'] : $this->getStepTitle('ini');
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
		$usedData =$this->getDataValue($data,'lev',false,$exists);
		$levels = reset($usedData);
		$summary .= "<div class='summary-title'>" . __("Bronze Level", 'woorewards-pro') . "</div>";
		$summary .= "<div class='lws-wizard-summary-label'>".__("Points to reach the level", 'woorewards-pro')."</div>";
		$summary .= "<div class='lws-wizard-summary-value'>{$levels['first_needed']}</div>";
		$summary .= "<div class='lws-wizard-summary-label'>".__("Permanent discount % amount", 'woorewards-pro')."</div>";
		$summary .= "<div class='lws-wizard-summary-value'>{$levels['first_amount']}</div>";
		$summary .= "<div class='lws-wizard-summary-label'>".__("Customer title", 'woorewards-pro')."</div>";
		$summary .= "<div class='lws-wizard-summary-value'>{$levels['first_title']}</div>";

		$summary .= "<div class='summary-title'>" . __("Silver Level", 'woorewards-pro') . "</div>";
		$summary .= "<div class='lws-wizard-summary-label'>".__("Points to reach the level", 'woorewards-pro')."</div>";
		$summary .= "<div class='lws-wizard-summary-value'>{$levels['second_needed']}</div>";
		$summary .= "<div class='lws-wizard-summary-label'>".__("Permanent discount % amount", 'woorewards-pro')."</div>";
		$summary .= "<div class='lws-wizard-summary-value'>{$levels['second_amount']}</div>";
		$summary .= "<div class='lws-wizard-summary-label'>".__("Customer title", 'woorewards-pro')."</div>";
		$summary .= "<div class='lws-wizard-summary-value'>{$levels['second_title']}</div>";

		$summary .= "<div class='summary-title'>" . __("Gold Level", 'woorewards-pro') . "</div>";
		$summary .= "<div class='lws-wizard-summary-label'>".__("Points to reach the level", 'woorewards-pro')."</div>";
		$summary .= "<div class='lws-wizard-summary-value'>{$levels['third_needed']}</div>";
		$summary .= "<div class='lws-wizard-summary-label'>".__("Permanent discount % amount", 'woorewards-pro')."</div>";
		$summary .= "<div class='lws-wizard-summary-value'>{$levels['third_amount']}</div>";
		$summary .= "<div class='lws-wizard-summary-label'>".__("Customer title", 'woorewards-pro')."</div>";
		$summary .= "<div class='lws-wizard-summary-value'>{$levels['third_title']}</div>";

		$summary .= "</div>";
		return $summary;
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

	/** @return true on success. On problem returns one (string) or several (array of string) reasons that will be shown to the user. */
	function isValid($step, &$submit)
	{
		$err = array();
		if( $step == 'met' )
		{
			$metCat = isset($submit['category']) ? trim($submit['category']) : '';
			if( !$metCat )
			{
				$err[] = __("Please, select a method category.", 'woorewards-pro');
			}
			else if( $metCat == 'product' )
			{
				$prodPts = isset($submit['product_spec_earn']) ? trim($submit['product_spec_earn']) : '';
				if( strlen($prodPts) && !is_numeric($prodPts) )
				{
					$err[] = __("Points for specific product expect numeric value or leave blank.", 'woorewards-pro');
				}
				else if( 0 < intval($prodPts) )
				{
					if( !@intval(isset($submit['product_spec']) ? trim($submit['product_spec']) : '') )
						$err[] = __("Please, select a specific product or don't set points.", 'woorewards-pro');
				}

				$catPts = isset($submit['product_cat_earn']) ? trim($submit['product_cat_earn']) : '';
				if( strlen($catPts) && !is_numeric($catPts) )
				{
					$err[] = __("Points on category buy expect numeric value or leave blank.", 'woorewards-pro');
				}
				else if( 0 < intval($catPts) )
				{
					$categories = isset($submit['product_categories']) ? $submit['product_categories'] : false;
					if( !$categories || !is_array($categories) )
						$err[] = __("Please, select at least one product category or do not set points.", 'woorewards-pro');
				}
			}
		}
		else if( $step == 'lev' )
		{
			$pts = isset($submit['first_needed']) ? trim($submit['first_needed']) : '';
			if( strlen($pts) && intval($pts) <= 0 )
			{
				$err[] = __("Points Needed for first level expects numeric value or leave blank.", 'woorewards-pro');
			}
			else if( 0 < intval($pts) )
			{
				$amount = isset($submit['first_amount']) ? @floatval($submit['first_amount']) : 0.0;
				if( $amount <= 0.0 )
					$err[] = __("Please, set a positive first reward percentage or do not set points needed.", 'woorewards-pro');
			}
			$title = isset($submit['first_title']) ? trim($submit['first_title']) : '';
			if(!$title)
			{
				$err[] = __("You need to set a user title for the first level. You can remove the title later.", 'woorewards-pro');
			}
			$pts = isset($submit['second_needed']) ? trim($submit['second_needed']) : '';
			if( strlen($pts) && intval($pts) <= 0 )
			{
				$err[] = __("Points Needed for second level expects numeric value or leave blank.", 'woorewards-pro');
			}
			else if( 0 < intval($pts) )
			{
				$amount = isset($submit['second_amount']) ? @floatval($submit['second_amount']) : 0.0;
				if( $amount <= 0.0 )
					$err[] = __("Please, set a positive second reward percentage or do not set points needed.", 'woorewards-pro');
			}
			$title = isset($submit['second_title']) ? trim($submit['second_title']) : '';
			if(!$title)
			{
				$err[] = __("You need to set a user title for the second level. You can remove the title later.", 'woorewards-pro');
			}

			$pts = isset($submit['third_needed']) ? trim($submit['third_needed']) : '';
			if( strlen($pts) && intval($pts) <= 0 )
			{
				$err[] = __("Points Needed for third level expects numeric value or leave blank.", 'woorewards-pro');
			}
			else if( 0 < intval($pts) )
			{
				$amount = isset($submit['third_amount']) ? @floatval($submit['third_amount']) : 0.0;
				if( $amount <= 0.0 )
					$err[] = __("Please, set a positive third reward percentage or do not set points needed.", 'woorewards-pro');
			}
			$title = isset($submit['third_title']) ? trim($submit['third_title']) : '';
			if(!$title)
			{
				$err[] = __("You need to set a user title for the third level. You can remove the title later.", 'woorewards-pro');
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
		$title = $this->getValue($data['data'], 'system_title', 'ini/*', $this->getStepTitle('ini'));
		$pool->setName($title);
		$pool->setOptions(array(
			'type'      => \LWS\WOOREWARDS\Core\Pool::T_LEVELLING,
			'public'    => 'yes' === $this->getValue($data['data'], 'start', 'sum/*'),
			'title'     => $title,
			'whitelist' => array(\LWS\WOOREWARDS\Core\Pool::T_LEVELLING),
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

		/* Levels and Rewards */
		if(($needed = \absint($this->getValue($data['data'], 'first_needed', 'lev/*', 0))) > 0)
		{
			$coupon = new \LWS\WOOREWARDS\PRO\Unlockables\Coupon();
			$coupon->setInPercent(true);
			$coupon->setPermanent(true);
			$coupon->setGroupedTitle(__("Bronze Level", 'woorewards-pro'));
			$coupon->setValue($this->getValue($data['data'], 'first_amount', 'lev/*', 0));
			$pool->addUnlockable($coupon, $needed);
		}
		if(($title = $this->getValue($data['data'], 'first_title', 'lev/*')) != "" && ($needed = \absint($this->getValue($data['data'], 'first_needed', 'lev/*', 0))) > 0)
		{
			$userTitle = new \LWS\WOOREWARDS\PRO\Unlockables\UserTitle();
			$userTitle->setGroupedTitle(__("Bronze Level", 'woorewards-pro'));
			$userTitle->setUserTitle($title);
			$pool->addUnlockable($userTitle, $needed);
		}

		if(($needed = \absint($this->getValue($data['data'], 'second_needed', 'lev/*', 0))) > 0)
		{
			$coupon = new \LWS\WOOREWARDS\PRO\Unlockables\Coupon();
			$coupon->setInPercent(true);
			$coupon->setPermanent(true);
			$coupon->setGroupedTitle(__("Silver Level", 'woorewards-pro'));
			$coupon->setValue($this->getValue($data['data'], 'second_amount', 'lev/*', 0));
			$pool->addUnlockable($coupon, $needed);
		}
		if(($title = $this->getValue($data['data'], 'second_title', 'lev/*')) != "" && ($needed = \absint($this->getValue($data['data'], 'second_needed', 'lev/*', 0))) > 0)
		{
			$userTitle = new \LWS\WOOREWARDS\PRO\Unlockables\UserTitle();
			$userTitle->setGroupedTitle(__("Silver Level", 'woorewards-pro'));
			$userTitle->setUserTitle($title);
			$pool->addUnlockable($userTitle, $needed);
		}

		if(($needed = \absint($this->getValue($data['data'], 'third_needed', 'lev/*', 0))) > 0)
		{
			$coupon = new \LWS\WOOREWARDS\PRO\Unlockables\Coupon();
			$coupon->setInPercent(true);
			$coupon->setPermanent(true);
			$coupon->setGroupedTitle(__("Gold Level", 'woorewards-pro'));
			$coupon->setValue($this->getValue($data['data'], 'third_amount', 'lev/*', 0));
			$pool->addUnlockable($coupon, $needed);
		}
		if(($title = $this->getValue($data['data'], 'third_title', 'lev/*')) != "" && ($needed = \absint($this->getValue($data['data'], 'third_needed', 'lev/*', 0))) > 0)
		{
			$userTitle = new \LWS\WOOREWARDS\PRO\Unlockables\UserTitle();
			$userTitle->setGroupedTitle(__("Gold Level", 'woorewards-pro'));
			$userTitle->setUserTitle($title);
			$pool->addUnlockable($userTitle, $needed);
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
