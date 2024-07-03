<?php
namespace LWS\WOOREWARDS\PRO;

// don't call the file directly
if( !defined( 'ABSPATH' ) ) exit();

/** satic class to manage activation and version updates. */
class Wizard extends \LWS\WOOREWARDS\Wizard
{
	/** wizard file must be lower case in wizards subdir.
	 * wizard class must implement i_wizard
	 * wizard classname must be first letter upper case (and only first letter, not camelCase) */
	protected function instanciateWizard($choice)
	{
		$instance = \apply_filters('lws_wooreward_wizard_instance', false, $this);
		if( $instance )
			return $instance;

		$choice = strtolower(\sanitize_key($choice)); // sanitize remove any ../ changedir
		$path = LWS_WOOREWARDS_PRO_INCLUDES . "/wizards/{$choice}.php";
		if( \file_exists($path) )
		{
			include_once($path);
			$classname = '\LWS\WOOREWARDS\PRO\Wizards\\'.ucfirst($choice);
			return new $classname($this);
		}
		return parent::instanciateWizard($choice);
	}

	/** @return object a i_wizard implementation instance or false if user selected none. */
	protected function getOrCreateWizard()
	{
		if (null === $this->subWizard)
		{
			$this->subWizard = false;
			$data = $this->getData();
			$exists = false;
			$choice = $this->getDataValue($data, 'wchoice', false, $exists);
			if( $exists )
			{
				$this->subWizard = $this->instanciateWizard($choice);
				if( !$this->subWizard )
				{
					error_log("Unknown wizard: ".$choice);
					$this->resetData();
				}
			}
		}
		return $this->subWizard;
	}
	protected function getHierarchy()
	{
		$hierarchy = array(
			'choice',
			'ini', /// that faky step is required to avoid 'choice' being the last, since last step display submit button instead of next button.
		);
		if( $wiz = $this->getOrCreateWizard() )
		{
			// first step must be named 'ini' too
			$hierarchy = array_merge(array('choice'), $wiz->getHierarchy());
		}
		return $hierarchy;
	}

	/** Define loylaty system choice as accessible,
	 * if pro directory contains an image with same name, replace it. */
	protected function getChoicePage($mode='')
	{
		$page = parent::getChoicePage($mode);
		foreach( $page['groups']['wchoice']['fields']['wchoice']['extra']['source'] as &$choice )
		{
			if( isset($choice['pro-only']) && boolval($choice['pro-only']) )
			{
				$choice['color'] = $choice['pro-color'];
				$choice['pro-only'] = false;
				if( isset($choice['image']) )
				{
					$img = str_replace(LWS_WOOREWARDS_IMG, LWS_WOOREWARDS_PRO_PATH.'/img', $choice['image']);
					if( file_exists($img) )
						$choice['image'] = str_replace(LWS_WOOREWARDS_IMG, LWS_WOOREWARDS_PRO_IMG, $choice['image']);
				}
			}
		}
		return \apply_filters('lws_wooreward_wizard_choice_page', $page, $this);
	}
}
