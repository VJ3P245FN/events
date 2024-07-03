<?php

namespace LWS\WOOREWARDS\PRO\Unlockables;

// don't call the file directly
if (!defined('ABSPATH')) exit();

/** A free shipping reward.
 * Create a WooCommerce Coupon. */
class FreeShipping extends \LWS\WOOREWARDS\Abstracts\Unlockable
{
	use \LWS\WOOREWARDS\PRO\Unlockables\T_DiscountOptions;

	private $lastCode  = '';
	private $timeout   = null;
	private $permanent = false;
	private $autoapply = false;

	function getInformation()
	{
		return array_merge(parent::getInformation(), array(
			'icon'  => 'lws-icon-delivery',
			'short' => __("The customer will receive a WooCommerce Coupon, giving him a free shipping on an order.", 'woorewards-pro'),
			'help'  => __("You need to allow Free Shipping Coupons in WooCommerce settings for this reward to work.", 'woorewards-pro'),
		));
	}

	function getData($min = false)
	{
		$prefix = $this->getDataKeyPrefix();
		$data = parent::getData();
		$data[$prefix . 'permanent'] = ($this->isPermanent() ? 'on' : '');
		$data[$prefix . 'autoapply'] = ($this->isAutoApply() ? 'on' : '');
		$data[$prefix . 'timeout'] = $this->getTimeout()->toString();
		return $this->filterData($data, $prefix, $min);
	}

	function getForm($context = 'editlist')
	{
		$prefix = $this->getDataKeyPrefix();
		$form = parent::getForm($context);
		$form .= $this->getFieldsetBegin(2, __("Coupon options", 'woorewards-pro'));

		// timeout
		$label = _x("Validity period", "Coupon Unlockable", 'woorewards-pro');
		$value = $this->getTimeout()->toString();
		$form .= "<div class='lws-$context-opt-title label'>$label</div>";
		$form .= "<div class='lws-$context-opt-input value'>";
		$form .= \LWS\Adminpanel\Pages\Field\Duration::compose($prefix . 'timeout', array('value' => $value));
		$form .= "</div>";

		// permanent on/off
		$label = _x("Exclusive reward", "Coupon Unlockable", 'woorewards-pro');
		$tooltip = __("That reward will replace any previous Exclusive reward coupon reward of the same type owned by the customer.", 'woorewards-pro');
		$toggle = \LWS\Adminpanel\Pages\Field\Checkbox::compose($prefix . 'permanent', array(
			'id'      => $prefix . 'permanent',
			'layout'  => 'toggle',
		));
		$form .= "<div class='field-help'>$tooltip</div>";
		$form .= "<div class='lws-$context-opt-title label'>$label<div class='bt-field-help'>?</div></div>";
		$form .= "<div class='lws-$context-opt-input value'>$toggle</div>";

		// autoapply on/off
		$label = _x("Auto-apply on next cart", "Coupon Unlockable", 'woorewards-pro');
		$toggle = \LWS\Adminpanel\Pages\Field\Checkbox::compose($prefix . 'autoapply', array(
			'id'      => $prefix . 'autoapply',
			'layout'  => 'toggle',
		));
		$form .= "<div class='lws-$context-opt-title label'>$label</div>";
		$form .= "<div class='lws-$context-opt-input value'>$toggle</div>";

		$form .= $this->getFieldsetEnd(2);
		return $this->filterForm($form, $prefix, $context);
	}

	function submit($form = array(), $source = 'editlist')
	{
		$prefix = $this->getDataKeyPrefix();
		$values = \apply_filters('lws_adminpanel_arg_parse', array(
			'post'     => ($source == 'post'),
			'values'   => $form,
			'format'   => array(
				$prefix . 'permanent' => 's',
				$prefix . 'autoapply' => 's',
				$prefix . 'timeout' => '/(p?\d+[DYM])?/i'
			),
			'defaults' => array(
				$prefix . 'autoapply' => '',
				$prefix . 'permanent' => '',
				$prefix . 'timeout' => ''
			),
			'labels'   => array(
				$prefix . 'autoapply'   => __("Auto-apply", 'woorewards-pro'),
				$prefix . 'permanent'   => __("Permanent", 'woorewards-pro'),
				$prefix . 'timeout' => __("Validity period", 'woorewards-pro')
			)
		));
		if (!(isset($values['valid']) && $values['valid']))
			return isset($values['error']) ? $values['error'] : false;

		$valid = parent::submit($form, $source);
		if ($valid === true && ($valid = $this->optSubmit($prefix, $form, $source)) === true)
		{
			$this->setPermanent($values['values'][$prefix . 'permanent']);
			$this->setAutoApply($values['values'][$prefix . 'autoapply']);
			$this->setTimeout($values['values'][$prefix . 'timeout']);
		}
		return $valid;
	}

	public function setTestValues()
	{
		$this->setTimeout(rand(5, 78) . 'D');
		return $this;
	}

	public function isPermanent()
	{
		return $this->permanent;
	}

	public function setPermanent($yes = false)
	{
		$this->permanent = boolval($yes);
		return $this;
	}

	/** @return \LWS\Adminpanel\Duration a Duration instance */
	public function getTimeout()
	{
		if (null === $this->timeout)
			$this->timeout = \LWS\Adminpanel\Duration::void();
		return $this->timeout;
	}

	/** @param $days (false|int|\LWS\Adminpanel\Duration) */
	public function setTimeout($days = false)
	{
		if (empty($days))
			$this->timeout = \LWS\Adminpanel\Duration::void();
		else if (is_a($days, '\LWS\Adminpanel\Duration'))
			$this->timeout = $days;
		else
			$this->timeout = \LWS\Adminpanel\Duration::fromString($days);
		return $this;
	}

	public function getDisplayType()
	{
		return _x("Free shipping", "getDisplayType", 'woorewards-pro');
	}

	public function isAutoApply()
	{
		return $this->autoapply;
	}

	public function setAutoApply($yes = false)
	{
		$this->autoapply = boolval($yes);
		return $this;
	}

	/**	Provided to be overriden.
	 *	@param $context usage of text. Default is 'backend' for admin, expect 'frontend' for customer.
	 *	@return (string) what this does. */
	function getDescription($context = 'backend')
	{
		return $this->getCouponDescription($context);
	}

	/**	Provided to be overriden.
	 *	@param $context usage of text. Default is 'backend' for admin, expect 'frontend' for customer.
	 *	@return (string) what this does. */
	function getCouponDescription($context = 'backend', $date = false)
	{
		$str = _x("Free shipping", "public description", 'woorewards-pro');

		if (!$this->getTimeout()->isNull())
		{
			$str .= ' - ';
			if ($date)
			{
				$str .= sprintf(
					__('valid up to %s', 'woorewards-pro'),
					\date_i18n(\get_option('date_format'), $this->getTimeout()->getEndingDate($date)->getTimestamp())
				);
			}
			else
			{
				$str .= sprintf(
					__('valid for %1$d %2$s', 'woorewards-pro'),
					$this->getTimeout()->getCount(),
					$this->getTimeout()->getPeriodText()
				);
			}
		}

		if ($this->isPermanent())
		{
			$attr = _x("permanent", "Coupon", 'woorewards-pro');
			$str .= $context == 'edit' ? " ($attr)" : " (<i>$attr</i>)";
		}

		if (!empty($discount = $this->getPartialDescription($context)))
			$str .= (', ' . $discount);
		return $str;
	}

	protected function _fromPost(\WP_Post $post)
	{
		$this->setAutoApply(\get_post_meta($post->ID, 'woorewards_autoapply', true));
		$this->setPermanent(\get_post_meta($post->ID, 'woorewards_permanent', true));
		$this->setTimeout(\LWS\Adminpanel\Duration::postMeta($post->ID, 'wre_unlockable_timeout'));
		$this->optFromPost($post);
		return $this;
	}

	protected function _save($id)
	{
		\update_post_meta($id, 'woorewards_autoapply', $this->isAutoApply() ? 'on' : '');
		\update_post_meta($id, 'woorewards_permanent', $this->isPermanent() ? 'on' : '');
		$this->getTimeout()->updatePostMeta($id, 'wre_unlockable_timeout');
		$this->optSave($id);
		return $this;
	}

	public function createReward(\WP_User $user, $demo = false)
	{
		if (!\LWS\Adminpanel\Tools\Conveniences::isWC())
			return false;

		if (!\is_email($user->user_email))
		{
			error_log(\get_class() . "::apply - invalid email for user {$user->ID}");
			return false;
		}

		if ($demo)
			$code = strtoupper(__('TESTCODE', 'woorewards-pro'));
		else if (empty($code = apply_filters('lws_woorewards_new_coupon_label', '', $user, $this)))
			$code = \LWS\WOOREWARDS\Unlockables\Coupon::uniqueCode($user);

		if (false === ($coupon = $this->createShopCoupon($code, $user, $demo)))
			return false;

		$this->lastCode = $code;
		return $coupon;
	}

	/** For point movement historic purpose. Can be override to return a reason.
	 *	Last generated coupon code is consumed by this function. */
	public function getReason($context = 'backend')
	{
		if ($this->lastCode)
		{
			$reason = sprintf(__("Coupon code : %s", 'woorewards-pro'), $this->lastCode);
			if ($context == 'frontend')
				$reason .= '<br/>' . $this->getDescription($context);
			return $reason;
		}
		return $this->getDescription($context);
	}

	protected function createShopCoupon($code, \WP_User $user, $demo = false)
	{
		if (!$demo)
			\do_action('wpml_switch_language_for_email', $user->user_email); // switch to customer language before fixing content

		$coupon = $this->buildCouponPostData($code, $user);
		if (!$demo)
		{
			$coupon->save();
			if (empty($coupon->get_id()))
			{
				\do_action('wpml_restore_language_from_email');
				error_log("Cannot generate a shop_coupon: WC error");
				error_log(print_r($coupon, true));
				return false;
			}

			\wp_update_post(array(
				'ID' => $coupon->get_id(),
				'post_author'  => $user->ID,
				'post_content' => $this->getTitle()
			));
			\update_post_meta($coupon->get_id(), 'reward_origin', $this->getType());
			\update_post_meta($coupon->get_id(), 'reward_origin_id', $this->getId());
			if ($this->isPermanent())
				$this->setPermanentcoupon($coupon, $user, $this->getType(), $this->getPoolId());
			$this->applyOnCoupon($coupon, $user, $this->getPoolId(), $demo);
			if ($this->isAutoApply())
				\update_post_meta($coupon->get_id(), 'lws_woorewards_auto_apply', 'on');

			// dokan support, force default behavior for a working coupon
			\update_post_meta($coupon->get_id(), 'admin_coupons_enabled_for_vendor', 'yes');

			\do_action('wpml_restore_language_from_email');
			\do_action('woocommerce_coupon_options_save', $coupon->get_id(), $coupon);
		}
		return $coupon;
	}

	protected function buildCouponPostData($code, \WP_User $user)
	{
		$txt = $this->getCustomExcerpt($user);

		/** That filter is required to counter poorly coded plugins, that prevent data_store instanciation in fresh coupon. */
		\add_filter('woocommerce_get_shop_coupon_data', '__return_false', PHP_INT_MAX);
		$coupon = new \WC_Coupon();
		\remove_filter('woocommerce_get_shop_coupon_data', '__return_false', PHP_INT_MAX);

		$coupon->set_props(array(
			'code'                   => $code,
			'description'            => $txt,
			'discount_type'          => 'fixed_cart',
			'amount'                 => 0,
			'date_expires'           => !$this->getTimeout()->isNull() ? $this->getTimeout()->getEndingDate()->format('Y-m-d') : '',
			'usage_limit'            => $this->getUsageLimit(),
			'email_restrictions'     => array($user->user_email),
			'free_shipping'          => true,
		));
		return $this->filterCouponPostData($coupon, $code, $user);
	}

	public function isAutoApplicable()
	{
		return true;
	}

	/**	Event categories, used to filter out events from pool options.
	 *	@return array with category_id => category_label. */
	public function getCategories()
	{
		return array_merge(parent::getCategories(), array(
			'woocommerce' => __("WooCommerce", 'woorewards-pro'),
			'shop_coupon' => __("Coupon", 'woorewards-pro'),
			'sponsorship' => _x("Referee", "unlockable category", 'woorewards-pro')
		));
	}
}
