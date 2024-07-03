<?php
namespace LWS\WOOREWARDS\PRO\Unlockables;

// don't call the file directly
if( !defined( 'ABSPATH' ) ) exit();

require_once LWS_WOOREWARDS_PRO_INCLUDES . '/core/usertitle.php';

/**
 * Assign a rode to a user.
 * MyRewards roles (created by this unlockable) are added.
 * WordPress (or third party) roles are set (replace any existant). */
class Role extends \LWS\WOOREWARDS\Abstracts\Unlockable
{
	const PREFIX = 'lws_wr_';
	private $roleId = '';

	function getInformation()
	{
		return array_merge(parent::getInformation(), array(
			'icon'  => 'lws-icon-users',
			'short' => __("The customer will receive a new user role.", 'woorewards-pro'),
			'help'  => __("You can create a new user role or assign an existing one.", 'woorewards-pro'),
		));
	}

	function getData($min=false)
	{
		$prefix = $this->getDataKeyPrefix();
		$data = parent::getData();
		$data[$prefix.'role'] = $this->getRoleId();
		return $data;
	}

	function getForm($context='editlist')
	{
		$prefix = $this->getDataKeyPrefix();
		$form = parent::getForm($context);
		$form .= $this->getFieldsetBegin(2, __("Role", 'woorewards-pro'), 'col50');

		if( $role = $this->getRoleId() )
			$role = $this->createRole($role);

		// The role
		$label   = _x("Role", "event form", 'woorewards-pro');
		$tooltip = __("Pick an existant role or set a new role name.", 'woorewards-pro');
		$form .= "<div class='field-help'>$tooltip</div>";
		$form .= "<div class='lws-$context-opt-title label bold'>$label<div class='bt-field-help'>?</div></div>";
		$form .= "<div class='lws-$context-opt-input lws-lac-select-role'>";
		$form .= \LWS\Adminpanel\Pages\Field\LacSelect::compose($prefix.'role', array(
			'ajax' => 'lws_adminpanel_get_roles',
			'allownew' => 'on',
			'id' => $prefix . 'role_input'
		));
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
				$prefix.'role' => 'S',
			),
			'labels'   => array(
				$prefix.'role' => __("Role", 'woorewards-pro'),
			)
		));
		if( !(isset($values['valid']) && $values['valid']) )
			return isset($values['error']) ? $values['error'] : false;

		$valid = parent::submit($form, $source);
		if( $valid === true )
		{
			$role = $this->createRole($values['values'][$prefix.'role']);
			$this->setRoleId($role);
		}
		return $valid;
	}

	public function getRoleName()
	{
		$role = $this->getRoleId();
		if( $role )
		{
			$names = \wp_roles()->get_names();
			if( isset($names[$role]) )
				return \translate_user_role($names[$role]);
		}
		return $role;
	}

	/** @return string */
	public function getRoleId()
	{
		return $this->roleId;
	}

	public function setRoleId($role)
	{
		$this->roleId = $role;
		return $this;
	}

	public function setTestValues()
	{
		return $this;
	}

	protected function _fromPost(\WP_Post $post)
	{
		$this->setRoleId(\get_post_meta($post->ID, 'woorewards_role_id', true));
		return $this;
	}

	protected function _save($id)
	{
		\update_post_meta($id, 'woorewards_role_id', $this->getRoleId());
		return $this;
	}

	/** MyRewards roles are added, WordPress (or third party) roles are set.
	 * @return string|bool
	 */
	public function createReward(\WP_User $user, $demo=false)
	{
		if( $role = $this->getRoleId() )
		{
			if( !$demo && $user && $user->ID )
			{
				if( is_array($user->roles) && in_array($role, $user->roles) )
					return false; // user already got that role

				\LWS_WooRewards_Pro::isRoleChangeLocked();
				$this->removeOurRoles($user);
				$role = $this->createRole($role);

				if ((0 === strpos($role, self::PREFIX)) || $this->isProtectedRole($user)) {
					// if WR custom role or protected one, only add the role, not replace
					$user->add_role($role);
				} else {
					// if current is third party or WP role, replace it
					$oldRole = \get_user_meta($user->ID, 'lws_woorewards_user_role_backup', true);
					if( !$oldRole )
						\update_user_meta($user->ID, 'lws_woorewards_user_role_backup', $user->roles);
					$user->set_role($role);
				}
				\LWS_WooRewards_Pro::isRoleChangeLocked(true);
			}
		}
		return $role;
	}

	/** @return bool if user current role is proteced
	 *	as for administrator */
	function isProtectedRole(\WP_User $user)
	{
		$protecteds = \array_map('strtolower', \apply_filters('lws_woorewards_protected_roles', array('administrator')));
		foreach ($user->roles as $role) {
			if (\in_array(\strtolower($role), $protecteds))
				return true;
		}
		return false;
	}

	/** If the role does not exists, create it.
	 * $name is prefixed for creation (but search is done without prefix)
	 * @return string */
	function createRole($name)
	{
		if( !\get_role($name) )
		{
			$key = self::PREFIX . $name;
			\add_role($key, $name);
			return $key;
		}
		return $name;
	}

	function removeOurRoles($user)
	{
		$ours = array();
		foreach( $user->roles as $role )
		{
			if( 0 === strpos($role, self::PREFIX) )
				$ours[] = $role;
		}

		foreach( $ours as $role )
			$user->remove_role($role);
	}

	public function getDisplayType()
	{
		return _x("Assign a role", "getDisplayType", 'woorewards-pro');
	}

	/**	Provided to be overriden.
	 *	@param $context usage of text. Default is 'backend' for admin, expect 'frontend' for customer.
	 *	@return (string) what this does. */
	function getDescription($context='backend')
	{
		$str = sprintf(__("Assign role '%s'", 'woorewards-pro'), $this->getRoleName());
		if ('backend' == $context) {
			if (0 === \strpos($this->getRoleId(), self::PREFIX))
				$str .= __(" (append role)", 'woorewards-pro');
			else
				$str .= __(" (replace role)", 'woorewards-pro');
		}
		return $str;
	}

	/** A badge can only be purchased once.
	 * @return (bool) if user already has role. */
	public function noMorePurchase($userId)
	{
		if( !\is_admin() && !(defined('DOING_AJAX') && DOING_AJAX) )
		{
			$role = $this->getRoleId();
			if( $role && $userId )
			{
				$user = \get_user_by('ID', $userId);
				if( $user && is_array($user->roles) && in_array($role, $user->roles) )
					return true;
			}
		}
		return false;
	}

	public function isPurchasable($points=PHP_INT_MAX, $userId=null)
	{
		$purchasable = parent::isPurchasable($points, $userId);
		if( $purchasable && !\is_admin() && !(defined('DOING_AJAX') && DOING_AJAX) )
		{
			if( !($role = $this->getRoleId()) )
				$purchasable = false;
			else if( $purchasable && $userId )
			{
				$user = \get_user_by('ID', $userId);
				if( $user && is_array($user->roles) && in_array($role, $user->roles) )
					$purchasable = false;
			}
		}
		return $purchasable;
	}

	/**	Event categories, used to filter out events from pool options.
	 *	@return array with category_id => category_label. */
	public function getCategories()
	{
		return array_merge(parent::getCategories(), array(
			'sponsorship' => _x("Referee", "unlockable category", 'woorewards-pro'),
			'role' => __("Role", 'woorewards-pro'),
			'wp_user'   => __("User", 'woorewards-pro'),
		));
	}
}