<?php
namespace LWS\WOOREWARDS\Pro\Wizards;

// don't call the file directly
if( !defined( 'ABSPATH' ) ) exit();

/** satic class to manage activation and version updates. */
class Double extends \LWS\WOOREWARDS\Wizards\Subwizard
{
	function init()
	{
		$this->color = '#527da3';
		$this->wizard->setColor($this->color);
	}

	function getHierarchy()
	{
		// first step must be named 'ini' as the faky one from wizard.php
		return array(
			'ini',
			'sum',
		);
	}

	function getStepTitle($slug)
	{
		switch($slug)
		{
			case 'ini' : return __("Double Points", 'woorewards-pro');
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
					'help'  => __("Welcome to this Wizard. This tool will help you do one of the following things :", 'woorewards-pro').
					"<br/><ul><li>".
					__("Let the customers earn twice the points for a limited period of time", 'woorewards-pro').
					"</li><li>".
					__("Let customers with a special role earn twice the points (no time limit)", 'woorewards-pro').
					"</li><li>".
					__("Let customers with a special role earn twice the points for a limited period of time", 'woorewards-pro').
					"</li></ul><br/>".
					__("If you want to change the amount of points, you can change it later in the loyalty system's settings", 'woorewards-pro'),
					'groups' => array(
						array(
							'fields'  => array(
								array(
									'id'    => 'loyalty_system',
									'title' => __('Loyalty System', 'woorewards-pro'),
									'type'  => 'lacselect',
									'extra' => array(
										'ajax' => 'lws_woorewards_pool_list',
										'help' => __("First, you need to choose for which loyalty system you want to double the points earned.", 'woorewards-pro'),
									),
								),
								array(
									'id'    => 'start_date',
									'title' => __('Start Date', 'woorewards-pro'),
									'type'  => 'text',
									'extra' => array(
										'type' => 'date',
										'help' => __("Customers will earn twice the points during the period set here. Don't set dates if you want the system to be permanent", 'woorewards-pro'),
									),
								),
								array(
									'id'    => 'end_date',
									'title' => __('End Date', 'woorewards-pro'),
									'type'  => 'text',
									'extra' => array(
										'type' => 'date',
									),
								),
								array(
									'id'    => 'user_role',
									'title' => __('User Role', 'woorewards-pro'),
									'type'  => 'lacselect',
									'extra' => array(
										'ajax' => 'lws_adminpanel_get_roles',
										'help' => __("Select the customers who will be able to earn twice the points. If you don't select a role, all customers will earn extra points.", 'woorewards-pro'),
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
		$summary = "<div class='lws-wizard-summary-container'>";
		$usedData =$this->getDataValue($data,'ini',false,$exists);
		$settings = reset($usedData);
		$summary .= "<div class='summary-title'>" . __("Double Points Settings", 'woorewards-pro') . "</div>";
		$summary .= "<div class='lws-wizard-summary-label'>".__("Loyalty System", 'woorewards-pro')."</div>";
		if( $pool = \LWS\WOOREWARDS\PRO\Core\Pool::getOrLoad($settings['loyalty_system'], false) )
		{
			$name = $pool->getOption('display_title');
			$summary .= "<div class='lws-wizard-summary-value'>{$name}</div>";
		}
		else
		{
				$error = __("The loyalty system has been removed. Please go back and select another one.", 'woorewards-pro');
				$summary .= "<div class='lws-wizard-summary-value lws-wizard-error'>{$error}</div>";
		}

		if( $settings['start_date'] && $settings['end_date'] )
		{
			$start = \mysql2date(\get_option('date_format'), $settings['start_date']);
			$end = \mysql2date(\get_option('date_format'), $settings['end_date']);
			$summary .= "<div class='lws-wizard-summary-label'>".__("Start Date", 'woorewards-pro')."</div>";
			$summary .= "<div class='lws-wizard-summary-value'>{$start}</div>";
			$summary .= "<div class='lws-wizard-summary-label'>".__("End Date", 'woorewards-pro')."</div>";
			$summary .= "<div class='lws-wizard-summary-value'>{$end}</div>";
		}

		if( $settings['user_role'] )
		{
			$summary .= "<div class='lws-wizard-summary-label'>".__("Role restriction", 'woorewards-pro')."</div>";
			$name = $settings['user_role'];
			global $wp_roles;
			if( $wp_roles->is_role($name) )
			{
				$name = \translate_user_role($wp_roles->get_names()[$name]);
				$summary .= "<div class='lws-wizard-summary-value'>{$name}</div>";
			}
			else
			{
				$error = __("The user role have been removed. Please go back and select another one.", 'woorewards-pro');
				$summary .= "<div class='lws-wizard-summary-value lws-wizard-error'>{$error}</div>";
			}
		}

		$summary .= "</div>";
		return ($summary);

	}

	/** @return true on success. On problem returns one (string) or several (array of string) reasons that will be shown to the user. */
	function isValid($step, &$submit)
	{
		$err = array();
		if( $step == 'ini' )
		{
			$lSys = isset($submit['loyalty_system']) ? trim($submit['loyalty_system']) : '';
			if( !$lSys )
			{
				$err[] = __("Please, select a loyalty system.", 'woorewards-pro');
			}
			$start = isset($submit['start_date']) ? trim($submit['start_date']) : '';
			$end = isset($submit['end_date']) ? trim($submit['end_date']) : '';
			if($start && !$end)
			{
				$err[] = __("If you set a start date, you need to set an end date", 'woorewards-pro');
			}
			else if(!$start && $end)
			{
				$err[] = __("If you set an end date, you need to set a start date", 'woorewards-pro');
			}
			$restriction = isset($submit['user_role']) ? trim($submit['user_role']) : '';
			if(!$restriction && (!$start || !$end))
			{
				$err[] = __("If you don't restrict the system to a specific role, you have to specify the start and end dates.", 'woorewards-pro');
			}
		}
		return $err ? $err : true;
	}

	/** Instanciate pools, events, unlockables, etc. */
	function submit(&$data)
	{
		if( !isset($data['data']) )
			return false;

		$originalPoolId = $this->getValue($data['data'], 'loyalty_system', 'ini/*');
		$pool = \LWS\WOOREWARDS\Collections\Pools::instanciate()->load(array('p'=>$originalPoolId))->last();
		$pool->detach();
		$newTitle = __('Double Points', 'woorewards-pro').' '.$pool->getOption('display_title');
		$pool->setName($newTitle);
		$pool->ensureNameUnicity();
		$pool->setOptions(array(
			'public'    => 'yes' === $this->getValue($data['data'], 'start', 'sum/*'),
			'title'     => $newTitle,
		));

		/* Set Dates */
		if(($start = $this->getValue($data['data'], 'start_date', 'ini/*')) != '' && ($end = $this->getValue($data['data'], 'end_date', 'ini/*')) != '')
		{
			$pool->setOptions(array(
				'happening'		=> true,
				'period_start'	=> $start,
				'period_end'	=> $end,
			));
		}

		/* Set Role */
		if(($role = $this->getValue($data['data'], 'user_role', 'ini/*')) != '')
			$pool->setOption('roles',$role);


		/* Remove unlockables */
		$unlockables = $pool->getUnlockables();
		while( $unlockables->count() )
		{
			$item = $unlockables->last();
			$unlockables->remove($item);
			$pool->removeUnlockable($item);
			$item->delete();
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
