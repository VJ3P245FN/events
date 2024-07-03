<?php

namespace LWS\WOOREWARDS\PRO\Unlockables;

// don't call the file directly
if (!defined('ABSPATH')) exit();

/** Give points to another Pool. */
class PointGenerator extends \LWS\WOOREWARDS\Abstracts\Unlockable
{
	private $otherId       = '';
	private $other         = null;
	private $gain          = 0;
	private $tryUnlock     = false;
	private $_resetLevels  = false;
	private $_resetRewards = false;

	function getInformation()
	{
		return array_merge(parent::getInformation(), array(
			'icon'  => 'lws-icon-trend-up',
			'short' => __("You can use this reward to give points on another loyalty system.", 'woorewards-pro'),
			'help'  => __("This reward should only be used if you know how to use it.", 'woorewards-pro'),
		));
	}

	function getData($min = false)
	{
		$prefix = $this->getDataKeyPrefix();
		$data = parent::getData();
		$data[$prefix . 'other']  = $this->getOtherPoolId();
		$data[$prefix . 'gain']   = $this->getGain();
		$data[$prefix . 'try']    = $this->getTryUnlock() ? 'on' : '';
		$data[$prefix . 'reslev'] = $this->getResetLevels() ? 'on' : '';
		$data[$prefix . 'resrew'] = $this->getResetRewards() ? 'on' : '';
		return $data;
	}

	function getForm($context = 'editlist')
	{
		$prefix = $this->getDataKeyPrefix();
		$form = parent::getForm($context);
		$form .= $this->getFieldsetBegin(2, __("Options", 'woorewards-pro'), 'col50');

		// Pool Id
		$label = _x("Points and Rewards System", "Point Generator", 'woorewards-pro');
		$tooltip = __("Warning: Selecting the current Points and Rewards System could leed to infinite loop.", 'woorewards-pro');
		$form .= "<div class='field-help'>$tooltip</div>";
		$form .= "<div class='lws-$context-opt-title label'>$label<div class='bt-field-help'>?</div></div>";
		$form .= "<div class='lws-$context-opt-input value'>";
		$form .= "<input id='{$prefix}other' name='{$prefix}other' class='lac_select' data-ajax='lws_woorewards_pool_list' data-mode='research'>";
		$form .= "</div>";

		// Reset levels
		$label = _x("Reset levels", "Point Generator", 'woorewards-pro');
		$tooltip = __("If the selected Points and Rewards System is a Leveling system, allows to earn the levels again. This action does not remove any reward.", 'woorewards-pro');
		$toggle = \LWS\Adminpanel\Pages\Field\Checkbox::compose($prefix . 'reslev', array(
			'id'      => $prefix . 'reslev',
			'layout'  => 'toggle',
		));
		$form .= "<div class='field-help'>$tooltip</div>";
		$form .= "<div class='lws-$context-opt-title label'>$label<div class='bt-field-help'>?</div></div>";
		$form .= "<div class='lws-$context-opt-input value'>$toggle</div>";

		// Reset rewards
		$label = _x("Reset rewards", "Point Generator", 'woorewards-pro');
		$tooltip = __("Before giving points, all rewards in the system, owned by the customer, are confiscated.", 'woorewards-pro');
		$toggle = \LWS\Adminpanel\Pages\Field\Checkbox::compose($prefix . 'resrew', array(
			'id'      => $prefix . 'reslev',
			'layout'  => 'toggle',
		));
		$form .= "<div class='field-help'>$tooltip</div>";
		$form .= "<div class='lws-$context-opt-title label'>$label<div class='bt-field-help'>?</div></div>";
		$form .= "<div class='lws-$context-opt-input value'>$toggle</div>";

		// Gain
		$label = _x("Points to add", "Point Generator", 'woorewards-pro');
		$tooltip = sprintf(__("Expects an integer or an expression starting by %s", 'woorewards-pro'), '<b>=</b>');
		$form .= "<div class='field-help'>$tooltip</div>";
		$form .= "<div class='lws-$context-opt-title label'>$label<div class='bt-field-help'>?</div></div>";
		$form .= "<div class='lws-$context-opt-input value'>";
		$form .= "<input type='text' id='{$prefix}gain' name='{$prefix}gain' placeholder='0' />";
		$form .= "</div>";

		// Try Unlock
		$label = _x("Check for Rewards", "Point Generator", 'woorewards-pro');
		$tooltip = __("Check if rewards become available in the Points and Rewards System. Override the «Cannot be unlocked by user» option", 'woorewards-pro');
		$toggle = \LWS\Adminpanel\Pages\Field\Checkbox::compose($prefix . 'try', array(
			'id'      => $prefix . 'try',
			'layout'  => 'toggle',
		));
		$form .= "<div class='field-help'>$tooltip</div>";
		$form .= "<div class='lws-$context-opt-title label'>$label<div class='bt-field-help'>?</div></div>";
		$form .= "<div class='lws-$context-opt-input value'>$toggle</div>";

		$form .= $this->getFieldsetEnd(2);
		return $form;
	}

	function submit($form = array(), $source = 'editlist')
	{
		$prefix = $this->getDataKeyPrefix();
		$values = \apply_filters('lws_adminpanel_arg_parse', array(
			'post'     => ($source == 'post'),
			'values'   => $form,
			'format'   => array(
				$prefix . 'other'  => 'D',
				$prefix . 'gain'   => '=d',
				$prefix . 'try'    => 't',
				$prefix . 'reslev' => 't',
				$prefix . 'resrew' => 't',
			),
			'defaults' => array(
				$prefix . 'gain'   => '0',
				$prefix . 'try'    => '',
				$prefix . 'reslev' => '',
				$prefix . 'resrew' => '',
			),
			'labels'   => array(
				$prefix . 'other'  => __("Loyalty System", 'woorewards-pro'),
				$prefix . 'gain'   => __("Given Points", 'woorewards-pro'),
				$prefix . 'try'    => __("Check for Rewards", 'woorewards-pro'),
				$prefix . 'reslev' => __("Reset Levels", 'woorewards-pro'),
				$prefix . 'resrew' => __("Reset Rewards", 'woorewards-pro'),
			)
		));
		if (!(isset($values['valid']) && $values['valid']))
			return isset($values['error']) ? $values['error'] : false;

		$valid = parent::submit($form, $source);
		if ($valid === true)
		{
			$this->setOtherPoolId($values['values'][$prefix . 'other']);
			$this->setGain($values['values'][$prefix . 'gain']);
			$this->setTryUnlock($values['values'][$prefix . 'try']);
			$this->setResetLevels($values['values'][$prefix . 'reslev']);
			$this->setResetRewards($values['values'][$prefix . 'resrew']);
		}
		return $valid;
	}

	/** @return string */
	public function getOtherPoolId()
	{
		return $this->otherId;
	}

	/** @return \LWS\WOOREWARDS\PRO\Core\Pool */
	public function getOtherPool()
	{
		if (null === $this->other)
		{
			if ($id = $this->getOtherPoolId())
				$this->other = \LWS\WOOREWARDS\PRO\Core\Pool::getOrLoad($id);
			else
				$this->other = false;
		}
		return $this->other;
	}

	public function setOtherPoolId($otherId = false)
	{
		$this->other   = null;
		$this->otherId = $otherId;
		return $this;
	}

	/** @return int|string */
	public function getGain()
	{
		return $this->gain;
	}

	public function setGain($gain = 0)
	{
		$this->gain = \trim($gain);
		return $this;
	}

	public function getTryUnlock()
	{
		return $this->tryUnlock;
	}

	public function setTryUnlock($try = false)
	{
		$this->tryUnlock = \boolval($try);
		return $this;
	}

	public function getResetLevels()
	{
		return $this->_resetLevels;
	}

	public function setResetLevels($reset = false)
	{
		$this->resetLevels = \boolval($reset);
		return $this;
	}

	public function getResetRewards()
	{
		return $this->_resetRewards;
	}

	public function setResetRewards($reset = false)
	{
		$this->resetRewards = \boolval($reset);
		return $this;
	}

	public function setTestValues()
	{
		$this->setGain(42);
		$pools = \LWS_WooRewards_Pro::getLoadedPools();
		if ($pools->count() > 0)
			$this->setOtherPoolId($pools->first()->getId());
		return $this;
	}

	protected function _fromPost(\WP_Post $post)
	{
		$this->setOtherPoolId(\get_post_meta($post->ID, 'woorewards_other_pool', true));
		$this->setGain(\get_post_meta($post->ID, 'woorewards_point_gain', true));
		$this->setTryUnlock(\get_post_meta($post->ID, 'woorewards_try_unlock', true));
		$this->setResetLevels(\get_post_meta($post->ID, 'woorewards_reset_levels', true));
		$this->setResetRewards(\get_post_meta($post->ID, 'woorewards_reset_rewards', true));
		return $this;
	}

	protected function _save($id)
	{
		\update_post_meta($id, 'woorewards_other_pool', $this->getOtherPoolId());
		\update_post_meta($id, 'woorewards_point_gain', $this->getGain());
		\update_post_meta($id, 'woorewards_try_unlock', $this->getTryUnlock() ? 'on' : '');
		\update_post_meta($id, 'woorewards_reset_levels', $this->getResetLevels() ? 'on' : '');
		\update_post_meta($id, 'woorewards_reset_rewards', $this->getResetRewards() ? 'on' : '');
		return $this;
	}

	public function createReward(\WP_User $user, $demo = false)
	{
		if (!$demo)
		{
			if ($pool = $this->getOtherPool())
			{
				if ($this->getResetLevels() && \LWS\WOOREWARDS\Core\Pool::T_LEVELLING == $pool->getOption('type'))
					$this->resetLevels($pool, $user->ID);
				if ($this->getResetRewards())
					$this->resetRewards($pool, $user->ID);

				if ($pool->userCan($user))
				{
					$reason = \LWS\WOOREWARDS\Core\Trace::byOrigin($this->getId())
						->setReason($this->getTitle(), 'woorewards-pro');

					$options = array(
						'user' => $user,
						'unlockable' => $this,
					);
					$gain = \apply_filters('lws_adminpanel_expression', $this->getGain(), $options);
					if ($gain < 0)
						$pool->usePoints($user->ID, abs($gain), $reason);
					else
						$pool->addPoints($user->ID, $gain, $reason);

					if ($this->getTryUnlock())
						$pool->tryUnlock($user, true);
				}
				else
					return false;
			}
			else
				return false;
		} else {
			// demo
			$gain = \random_int(12, 42);
		}
		return array(
			'other' => $this->getOtherPoolId(),
			'gain'  => $gain,
		);
	}

	private function resetLevels(&$pool, $userId)
	{
		global $wpdb;
		$ids = array_map(function ($u)
		{
			return \intval($u->getId());
		}, $pool->getUnlockables()->asArray());
		$ids = implode("','", $ids);
		$sql = "DELETE FROM {$wpdb->usermeta} WHERE user_id=%d AND meta_key='lws-loyalty-done-steps' AND meta_value IN ('{$ids}')";
		$wpdb->query($wpdb->prepare($sql, $userId));
	}

	private function resetRewards(&$pool, $userId)
	{
		$c = new \LWS\WOOREWARDS\PRO\Core\Confiscator();
		$c->setByPool($pool);
		$c->setUserFilter(array($userId));
		$c->revoke();
	}

	public function getDisplayType()
	{
		return _x("Generate Points", "getDisplayType", 'woorewards-pro');
	}

	/**	Provided to be overriden.
	 *	@param $context usage of text. Default is 'backend' for admin, expect 'frontend' for customer.
	 *	@return (string) what this does. */
	function getDescription($context = 'backend')
	{
		$pool = $this->getOtherPool();
		$gain = $this->getGain();
		if (\substr($gain, 0, 1) == '=') {
			$pts = ('[' . \trim(\substr($gain, 1)) . ']');
		} else {
			$pts = \absint($gain);
			if ($context != 'raw')
				$pts = \LWS_WooRewards::formatPointsWithSymbol($pts, $pool ? $pool->getName() : '');
		}
		$name = $pool ? $pool->getOption($context != 'raw' ? 'display_title' : 'title') : '{unknown}';

		$str = array();
		if ($this->getResetLevels() && $pool && \LWS\WOOREWARDS\Core\Pool::T_LEVELLING == $pool->getOption('type')) {
			$str[] = sprintf(__("Reset Levels in %s.", 'woorewards-pro'), $name);
		}

		if ($this->getResetRewards()) {
			$str[] = sprintf(__("Confiscate Rewards in %s.", 'woorewards-pro'), $name);
		}

		if ($gain)
		{
			if ($gain < 0)
				$str[] = sprintf("Remove %s from %s.", $pts, $name);
			else
				$str[] = sprintf("Add %s to %s.", $pts, $name);
		}

		if ($this->getTryUnlock())
		{
			if ($str)
				$str[] = __("Then check for Rewards", 'woorewards-pro');
			else
				$str[] = sprintf(__("Check for Rewards in %s", 'woorewards-pro'), $name);
		}

		if (!$str)
			$str[] = __("Do nothing", 'woorewards-pro');
		return implode('', $str);
	}

	/**	Event categories, used to filter out events from pool options.
	 *	@return array with category_id => category_label. */
	public function getCategories()
	{
		return array_merge(parent::getCategories(), array(
			'miscellaneous'   => __("User", 'woorewards-pro'),
		));
	}
}
