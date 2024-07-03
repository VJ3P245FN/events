<?php
namespace LWS\WOOREWARDS\PRO\Unlockables;

// don't call the file directly
if( !defined( 'ABSPATH' ) ) exit();

/** A shop_coupon using all available point to compute the amount at redeem time.
 *
 * Create a WooCommerce Coupon. */
class VariableDiscount extends \LWS\WOOREWARDS\Abstracts\Unlockable
{
	use \LWS\WOOREWARDS\PRO\Unlockables\T_DiscountOptions;

	private $value      = 0.01;
	private $autoapply  = false;
	private $timeout    = null;
	private $lastAmount = false;
	private $lastCode   = false;

	protected function isUsageLimitEnabled()
	{
		return false;
	}

	function getInformation()
	{
		return array_merge(parent::getInformation(), array(
			'icon'  => 'lws-icon-coupon',
			'short' => __("The customer will receive a coupon. The discount amount will depend on the number of points spent on the reward.", 'woorewards-pro'),
			'help'  => __("The generated coupon can be used like any other WooCommerce coupon.", 'woorewards-pro'),
		));
	}

	function getData($min=false)
	{
		$prefix = $this->getDataKeyPrefix();
		$data = parent::getData();
		$data[$prefix.'value'] = $this->getValue();
		$data[$prefix.'autoapply'] = ($this->isAutoApply() ? 'on' : '');
		$data[$prefix.'timeout'] = $this->getTimeout()->toString();
		return $this->filterData($data, $prefix, $min);
	}

	function getForm($context='editlist')
	{
		$prefix = $this->getDataKeyPrefix();
		$form = parent::getForm($context);

		// change meaning of basic cost
		$pattern = "/(<label[^>]*for='{$prefix}cost'[^>]*>)(.*)(<\\/label>)/iU";
		$form = preg_replace_callback($pattern, function($match){
			return $match[1] . _x("Mininum points", "Unlockable cost", 'woorewards-pro') . $match[3];
		}, $form);

		$form .= $this->getFieldsetBegin(2, __("Coupon options", 'woorewards-pro'));

		// value
		$label = _x("Amount per Point", "Coupon Unlockable", 'woorewards-pro');
		$currency = \LWS\Adminpanel\Tools\Conveniences::isWC() ? \get_woocommerce_currency_symbol() : '$';
		$points = \LWS_WooRewards::getPointSymbol(1, $this->getPoolName());
		$value = empty($this->getValue()) ? '' : \esc_attr($this->getValue());
		$form .= "<div class='lws-$context-opt-title label'>$label ($currency/$points)</div>";
		$form .= "<div class='lws-$context-opt-input value'><input type='text' id='{$prefix}value' name='{$prefix}value' value='$value' placeholder='0.01' pattern='\\d*(\\.|,)?\\d*' /></div>";

		// autoapply on/off
		$label = _x("Auto-apply on next cart", "VariableDiscount Unlockable", 'woorewards-pro');
		$toggle = \LWS\Adminpanel\Pages\Field\Checkbox::compose($prefix . 'autoapply', array(
			'id'      => $prefix . 'autoapply',
			'layout'  => 'toggle',
			'checked' => ($this->isAutoApply() ? ' checked' : '')
		));
		$form .= "<div class='lws-$context-opt-title label'>$label</div>";
		$form .= "<div class='lws-$context-opt-input value'>$toggle</div>";

		// timeout
		$label = _x("Validity period", "Variable Discount Unlockable", 'woorewards-pro');
		$value = $this->getTimeout()->toString();
		$form .= "<div class='lws-$context-opt-title label'>$label</div>";
		$form .= "<div class='lws-$context-opt-input value'>";
		$form .= \LWS\Adminpanel\Pages\Field\Duration::compose($prefix.'timeout', array('value'=>$value));
		$form .= "</div>";

		$form .= $this->getFieldsetEnd(2);
		return $this->filterForm($form, $prefix, $context);
	}

	function submit($form=array(), $source='editlist')
	{
		$prefix = $this->getDataKeyPrefix();
		$values = \apply_filters('lws_adminpanel_arg_parse', array(
			'post'     => ($source == 'post'),
			'values'   => $form,
			'format'   => array(
				$prefix.'autoapply' => 's',
				$prefix.'value'   => 'F',
				$prefix.'timeout' => '/(p?\d+[DYM])?/i'
			),
			'defaults' => array(
				$prefix.'autoapply' => '',
				$prefix.'timeout' => ''
			),
			'labels'   => array(
				$prefix.'autoapply'   => __("Auto-apply", 'woorewards-pro'),
				$prefix.'value'   => __("Coupon amount", 'woorewards-pro'),
				$prefix.'timeout' => __("Validity period", 'woorewards-pro')
			)
		));
		if( !(isset($values['valid']) && $values['valid']) )
			return isset($values['error']) ? $values['error'] : false;

		$valid = parent::submit($form, $source);
		if( $valid === true && ($valid = $this->optSubmit($prefix, $form, $source)) === true )
		{
			$this->setValue    ($values['values'][$prefix.'value']);
			$this->setAutoApply($values['values'][$prefix.'autoapply']);
			$this->setTimeout  ($values['values'][$prefix.'timeout']);
		}
		return $valid;
	}

	/** @return float */
	public function getValue()
	{
		return $this->value;
	}

	public function setValue($value=0.0)
	{
		$this->value = floatval(str_replace(',', '.', $value));
		return $this;
	}

	public function setTestValues()
	{
		$this->setValue(rand(200, 500)/100.0);
		$this->setTimeout(rand(5, 78).'D');
		$this->setOrderMinimumAmount(rand(50, 100)/100.0);
		return $this;
	}

	public function isAutoApply()
	{
		return $this->autoapply;
	}

	public function setAutoApply($yes=false)
	{
		$this->autoapply = boolval($yes);
		return $this;
	}

	/** @return \LWS\Adminpanel\Duration instance */
	public function getTimeout()
	{
		if( null === $this->timeout )
			$this->timeout = \LWS\Adminpanel\Duration::void();
		return $this->timeout;
	}

	/** @param $days (false|int|\LWS\Adminpanel\Duration) */
	public function setTimeout($days=false)
	{
		if( empty($days) )
			$this->timeout = \LWS\Adminpanel\Duration::void();
		else if( is_a($days, '\LWS\Adminpanel\Duration') )
			$this->timeout = $days;
		else
			$this->timeout = \LWS\Adminpanel\Duration::fromString($days);
		return $this;
	}

	public function getDisplayType()
	{
		return _x("Variable discount", "getDisplayType", 'woorewards-pro');
	}

	/**	Provided to be overriden.
	 *	@param $context usage of text. Default is 'backend' for admin, expect 'frontend' for customer.
	 *	@return (string) what this does. */
	function getDescription($context='backend')
	{
		return $this->getCouponDescription($context);
	}

	/**	Provided to be overriden.
	 *	@param $context usage of text. Default is 'backend' for admin, expect 'frontend' for customer.
	 *	@return (string) what this does. */
	function getCouponDescription($context='backend', $date=false)
	{
		$amount = (false !== $this->lastAmount) ? $this->lastAmount : $this->getCouponAmount(\get_current_user_id());
		$value = (\LWS\Adminpanel\Tools\Conveniences::isWC() && $context != 'edit') ? \wc_price($amount) : \number_format_i18n($amount, 2);
		$value = \LWS\WOOREWARDS\Unlockables\Coupon::getPriceTaxStatus($value);

		$txt = $this->isAutoApply() ? __("%s discount on your next order", 'woorewards-pro') : __("%s discount on an order", 'woorewards-pro');
		$str = sprintf($txt, $value);

		if( !$this->getTimeout()->isNull() )
		{
			$str .= ' - ';
			if( $date )
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

		if( !empty($discount = $this->getPartialDescription($context)) )
			$str .= (', ' . $discount);
		return $str;
	}

	protected function _fromPost(\WP_Post $post)
	{
		$this->setValue(\get_post_meta($post->ID, 'wre_unlockable_value', true));
		$this->setAutoApply(\get_post_meta($post->ID, 'woorewards_autoapply', true));
		$this->setTimeout(\LWS\Adminpanel\Duration::postMeta($post->ID, 'wre_unlockable_timeout'));
		$this->optFromPost($post);
		return $this;
	}

	protected function _save($id)
	{
		\update_post_meta($id, 'wre_unlockable_value', $this->getValue());
		\update_post_meta($id, 'woorewards_autoapply', $this->isAutoApply() ? 'on' : '');
		$this->getTimeout()->updatePostMeta($id, 'wre_unlockable_timeout');
		$this->optSave($id);
		return $this;
	}

	/** Multiplier is registered by Pool, it is applied to the points generated by the event. */
	public function getCost($context='edit')
	{
		$cost = parent::getCost($context);
		if( $context == 'front' || $context == 'pay' )
		{
			if( is_numeric($cost) && $this->getPool() && !empty($userId = \get_current_user_id()) )
				$cost = max($cost, $this->getPool()->getPoints($userId));
		}

		if( $context == 'view' || $context == 'front' )
			$cost .= "<sup>+</sup>";
		return $cost;
	}

	public function getUserCost($userId, $context='pay')
	{
		$cost = parent::getCost($context);
		if( is_numeric($cost) && $this->getPool() && !empty($userId) )
			$cost = max($cost, $this->getPool()->getPoints($userId));
		return intval($cost);
	}

	public function getCouponAmount($userId)
	{
		return $this->getValue() * $this->getUserCost($userId);
	}

	public function createReward(\WP_User $user, $demo=false)
	{
		if( !\LWS\Adminpanel\Tools\Conveniences::isWC() )
			return false;

		if( !\is_email($user->user_email) )
		{
			error_log(\get_class()."::apply - invalid email for user {$user->ID}");
			return false;
		}

		if( $demo )
			$code = strtoupper(__('TESTCODE', 'woorewards-pro'));
		else if( empty($code = apply_filters('lws_woorewards_new_coupon_label', '', $user, $this)) )
			$code = \LWS\WOOREWARDS\Unlockables\Coupon::uniqueCode($user);

		$this->lastAmount = $this->getCouponAmount($user->ID);
		$this->lastCode = $code;
		if( false === ($coupon = $this->createShopCoupon($code, $user, $demo)) )
		{
			$this->lastAmount = false;
			$this->lastCode   = false;
			return false;
		}

		return $coupon;
	}

	/** For point movement historic purpose. Can be override to return a reason.
	 *	Last generated coupon code is consumed by this function. */
	public function getReason($context='backend')
	{
		if( $this->lastCode ){
			$reason = sprintf(__("Coupon code : %s", 'woorewards-pro'), $this->lastCode);
			if( $context == 'frontend' )
				$reason .= '<br/>' . $this->getDescription($context);
			return $reason;
		}
		return $this->getDescription($context);
	}

	protected function createShopCoupon($code, \WP_User $user, $demo=false)
	{
		if( !$demo )
			\do_action('wpml_switch_language_for_email', $user->user_email); // switch to customer language before fixing content

		$coupon = $this->buildCouponPostData($code, $user);
		if( !$demo )
		{
			$coupon->save();
			if( empty($coupon->get_id()) )
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
			if( $this->isAutoApply() )
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
			'amount'                 => $this->getCouponAmount($user->ID),
			'date_expires'           => !$this->getTimeout()->isNull() ? $this->getTimeout()->getEndingDate()->format('Y-m-d') : '',
			'usage_limit'            => 1,
			'email_restrictions'     => array($user->user_email)
		));
		return $this->filterCouponPostData($coupon, $code, $user);
	}

	protected function replaceShortcodes($txt, $user)
	{
		$expiry = !$this->getTimeout()->isNull() ? $this->getTimeout()->getEndingDate() : false;
		$txt = $this->expiryInText($txt, $expiry);
		$amount = $this->getCouponAmount($user->ID);
		$value = \LWS\Adminpanel\Tools\Conveniences::isWC() ? \wc_price($amount) : \number_format_i18n(\floatval($amount), 2);
		$txt = \str_replace('[amount]', $value, $txt);

		$minAmount = $this->getOrderMinimumAmount();
		if( strlen($minAmount) && $this->isRelativeOrderMinimumAmount() )
			$minAmount = \floatval($minAmount) + \floatval($amount);
		else
			$minAmount = \floatval($minAmount);
		$minAmount = \LWS\Adminpanel\Tools\Conveniences::isWC() ? \wc_price($minAmount) : \number_format_i18n($minAmount, 2);
		$txt = \str_replace('[min_amount]', $minAmount, $txt);
		return $txt;
	}

	public function getCustomDescription($fallback=true)
	{
		$descr = parent::getCustomDescription($fallback);
		return $this->replaceShortcodes($descr, \wp_get_current_user());

	}

	protected function getCustomExcerpt($user)
	{
		$txt = $this->getCouponExcerpt();
		if( !empty($txt) )
		{
			$txt = $this->replaceShortcodes($txt, $user);
		}
		else
		{
			$txt = $this->getCustomDescription(false);
			if( empty($txt) )
				$txt = $this->getCouponDescription('frontend', \date_create());
		}
		return $txt;
	}

	public function isAutoApplicable()
	{
		return true;
	}

	/**	Event categories, used to filter out events from pool options.
	 *	@return array with category_id => category_label. */
	public function getCategories()
	{
		return array(
			\LWS\WOOREWARDS\Core\Pool::T_STANDARD  => __("Standard", 'woorewards-pro'),
			'woocommerce' => __("WooCommerce", 'woorewards-pro'),
			'shop_coupon' => __("Coupon", 'woorewards-pro')
		);
	}
}
