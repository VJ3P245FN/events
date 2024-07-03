<?php
namespace LWS\WOOREWARDS\PRO\Unlockables;

// don't call the file directly
if( !defined( 'ABSPATH' ) ) exit();

require_once LWS_WOOREWARDS_PRO_INCLUDES . '/core/usertitle.php';

/**
 * Create a WooCommerce Coupon. */
class UserTitle extends \LWS\WOOREWARDS\Abstracts\Unlockable
{
	private $userTitle         = '';
	private $userTitlePosition = 'right';
	private $lastUserName      = '';

	function getInformation()
	{
		return array_merge(parent::getInformation(), array(
			'icon'  => 'lws-icon-user-plus',
			'short' => __("The customer will receive a new title. This title can be displayed on various locations", 'woorewards-pro'),
			'help'  => __("MyRewards provides a shortcode to display the user name and title.", 'woorewards-pro'),
		));
	}

	function getData($min=false)
	{
		$prefix = $this->getDataKeyPrefix();
		$data = parent::getData();
		$data[$prefix.'utitle'] = $this->getUserTitle();
		$data[$prefix.'utpos'] = $this->getPosition();
		return $data;
	}

	function getForm($context='editlist')
	{
		$prefix = $this->getDataKeyPrefix();
		$form = parent::getForm($context);
		$form .= $this->getFieldsetBegin(2, __("Customer's Title", 'woorewards-pro'), 'col50');

		// title
		$label = _x("Customer's Title", "User's title", 'woorewards-pro');
		$form .= "<div class='lws-$context-opt-title label'>$label</div>";
		$form .= "<div class='lws-$context-opt-input value'><input type='text' id='{$prefix}value' name='{$prefix}utitle' /></div>";

		// position
		$label = array(
			0 => _x("Position", "User's title position", 'woorewards-pro'),
			'l' => _x("Before name", "User's title position", 'woorewards-pro'),
			'r' => _x("After name", "User's title position", 'woorewards-pro'),
		);
		$value = array(
			'l' => $this->getPosition() == 'left' ? ' checked' : '',
			'r' => $this->getPosition() != 'left' ? ' checked' : ''
		);
		$form .= "<div class='lws-$context-opt-title label'>{$label[0]}</div>";
		$form .= "<div class='lws-$context-opt-input value'>";
		$form .= "<label><input type='radio' id='{$prefix}utpos' name='{$prefix}utpos' value='left'{$value['l']} />{$label['l']}</label>";
		$form .= "<label><input type='radio' name='{$prefix}utpos' value='right'{$value['r']} />{$label['r']}</label>";
		$form .= "</div>";

		$form .= $this->getFieldsetEnd(2);
		return $form;
	}

	function submit($form=array(), $source='editlist')
	{
		$prefix = $this->getDataKeyPrefix();
		$values = \apply_filters('lws_adminpanel_arg_parse', array(
			'post'     => ($source == 'post'),
			'values'   => $form,
			'format'   => array(
				$prefix.'utpos' => '/(right|left)/i',
				$prefix.'utitle' => 'S'
			),
			'defaults' => array(
				$prefix.'utpos' => 'right'
			),
			'labels'   => array(
				$prefix.'utpos'   => __("Title position", 'woorewards-pro'),
				$prefix.'utitle' => __("Customer title", 'woorewards-pro')
			)
		));
		if( !(isset($values['valid']) && $values['valid']) )
			return isset($values['error']) ? $values['error'] : false;

		$valid = parent::submit($form, $source);
		if( $valid === true )
		{
			$this->setUserTitle($values['values'][$prefix.'utitle']);
			$this->setPosition ($values['values'][$prefix.'utpos']);
		}
		return $valid;
	}

	/** @return string */
	public function getUserTitle()
	{
		return $this->userTitle;
	}

	public function setUserTitle($userTitle='')
	{
		$this->userTitle = $userTitle;
		return $this;
	}

	/** @return string */
	public function getPosition()
	{
		return $this->userTitlePosition;
	}

	public function setPosition($position='right')
	{
		if( strtolower(substr($position, 0, 1)) == 'l' )
			$this->userTitlePosition = 'left';
		else
			$this->userTitlePosition = 'right';
		return $this;
	}

	public function setTestValues()
	{
		$this->setUserTitle(__(": The Tester", 'woorewards-pro'));
		if( !empty($user = \wp_get_current_user()) )
		{
			$title = $this->getUserTitle();
			$this->lastUserName = sprintf(\LWS\WOOREWARDS\PRO\Core\UserTitle::getPlaceholder($this->getPosition()), $user->display_name, $title);
		}
		return $this;
	}

	protected function _fromPost(\WP_Post $post)
	{
		$this->setUserTitle(\get_post_meta($post->ID, 'woorewards_special_title', true));
		$this->setPosition(\get_post_meta($post->ID, 'woorewards_special_title_position', true));
		return $this;
	}

	protected function _save($id)
	{
		\update_post_meta($id, 'woorewards_special_title', $this->getUserTitle());
		\update_post_meta($id, 'woorewards_special_title_position', $this->getPosition());

		if( $this->userTitle )
			\do_action('wpml_register_single_string', 'WooRewards User Title', "WooRewards User Title", $this->userTitle);
		return $this;
	}

	public function createReward(\WP_User $user, $demo=false)
	{
		if( !$demo )
		{
			\update_user_meta($user->ID, 'woorewards_special_title', $this->getUserTitle());
			\update_user_meta($user->ID, 'woorewards_special_title_position', $this->getPosition());
		}

		$this->lastUserName = \LWS\WOOREWARDS\PRO\Core\UserTitle::getDisplayName($user, false, 'reason');
		return array(
			'user_title' => $this->getUserTitle(),
			'user_title_position' => $this->getPosition()
		);
	}

	public function getDisplayType()
	{
		return _x("User title", "getDisplayType", 'woorewards-pro');
	}

	/**	Provided to be overriden.
	 *	@param $context usage of text. Default is 'backend' for admin, expect 'frontend' for customer.
	 *	@return (string) what this does. */
	function getDescription($context='backend')
	{
		if( $this->lastUserName )
			$name = $this->lastUserName;
		else
		{
			if( $context != 'backend' && !empty($user = \wp_get_current_user()) )
				$demo = $user->display_name;
			else
				$demo = _x("YourName", "A default name for demo", 'woorewards-pro');
			$title = $this->getUserTitle();
			$name = sprintf(\LWS\WOOREWARDS\PRO\Core\UserTitle::getPlaceholder($this->getPosition()), $demo, $title);
		}
		return sprintf(__("Be known as <b>%s</b>", 'woorewards-pro'), $name);
	}

	/** For point movement historic purpose. Can be override to return a reason.
	 *	Last generated coupon code is consumed by this function. */
	public function getReason($context='backend')
	{
		if( $this->lastUserName )
			return sprintf(__("The user becomes %s", 'woorewards-pro'), $this->lastUserName);
		else
			return $this->getDescription($context);
	}

	/**	Event categories, used to filter out events from pool options.
	 *	@return array with category_id => category_label. */
	public function getCategories()
	{
		return array(
			\LWS\WOOREWARDS\Core\Pool::T_LEVELLING => __("Levelling", 'woorewards-pro'),
			'wordpress' => __("WordPress", 'woorewards-pro'),
			'wp_user'   => __("User", 'woorewards-pro')
		);
	}
}
