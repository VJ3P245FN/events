<?php
namespace LWS\WOOREWARDS\PRO;

// don't call the file directly
if( !defined( 'ABSPATH' ) ) exit();

/** Conveniences Class for shared functions*/
class Conveniences
{
	private $urlTarget = null;

	public static function install()
	{
		$me =& self::instance();
		\add_filter('lws_woorewards_get_pools_by_args', array($me, 'getPoolsByArgs'), 10, 2); // Higher proprity than WooRewards Standard
		\add_filter('lws_woorewards_get_pools_by_stack', array($me, 'getPoolsByStack'), 10, 2);
		\add_filter('lws_adminpanel_expression_placeholder', array($me, 'expressionPlaceholders'), 10, 4);
	}

	/** @return Conveniences singleton instance */
	static function &instance()
	{
		static $_inst = false;
		if( false === $_inst )
			$_inst = new self();
		return $_inst;
	}

	/** prevent outside instanciation */
	protected function __construct()
	{}

	/** @return array of object [pool_name, pool_id, stack_id, pool_type] with pool_name as array keys. */
	function getPoolsInfo()
	{
		static $buffer = false;
		if( false === $buffer )
		{
			$postTypes = \apply_filters('lws_woorewards_available_post_types', array('lws-wre-pool', 'lws-wre-achievement'));
			$postTypes = implode("','", array_map('\esc_sql', $postTypes));

			global $wpdb;
			$sql = <<<EOT
SELECT p.post_name as pool_name, p.ID as pool_id, m.meta_value as stack_id, t.meta_value as pool_type
FROM {$wpdb->posts} as p
LEFT JOIN {$wpdb->postmeta} as t ON t.post_id=p.ID AND t.meta_key='wre_pool_type'
LEFT JOIN {$wpdb->postmeta} as m ON m.post_id=p.ID AND m.meta_key='wre_pool_point_stack'
WHERE p.post_type IN ('{$postTypes}')
ORDER BY p.ID ASC
EOT;
			$buffer = $wpdb->get_results($sql, OBJECT_K);
			if( !$buffer ) // sql error
				$buffer = array();
		}
		return $buffer;
	}

	/** @param $ref (string|int) the pool name or pool id.
	 * @return (string) the stackid */
	function getStackOfPool($ref)
	{
		$byPoolName = $this->getPoolsInfo();
		if( isset($byPoolName[$ref]) )
			return $byPoolName[$ref]->stack_id;
		if( \is_numeric($ref) )
		{
			static $byPoolId = false;
			if( false === $byPoolId )
				$byPoolId = \array_column($byPoolName, 'stack_id', 'pool_id');
			if( isset($byPoolId[$ref]) )
				return $byPoolId[$ref];
		}
		return false;
	}

	/** @param $ref (string|int) the pool name or pool id.
	 * @return (string) the type @see \LWS\WOOREWARDS\Core\Pool::T_STANDARD @see \LWS\WOOREWARDS\Core\Pool::T_LEVELLING */
	function getTypeOfPool($ref)
	{
		$byPoolName = $this->getPoolsInfo();
		if( isset($byPoolName[$ref]) )
			return $byPoolName[$ref]->pool_type;
		if( \is_numeric($ref) )
		{
			static $byPoolId = false;
			if( false === $byPoolId )
				$byPoolId = \array_column($byPoolName, 'pool_type', 'pool_id');
			if( isset($byPoolId[$ref]) )
				return $byPoolId[$ref];
		}
		return false;
	}

	public function getPoolsByStack($pools, $stackId)
	{
		return \LWS_WooRewards_Pro::getLoadedPools()->filter(function($p)use($stackId){
			return $p->getStackId() == $stackId;
		});
	}

	/** returns a collection of pools depending on the sent parameters
	 *	@return \LWS\WOOREWARDS\Collections\Pools
	 * 	@param $pools (false|\LWS\WOOREWARDS\Collections\Pools)
	 *	@param $atts (array)
	 *	* system = loyalty system (or several loyalty systems, comma separated)
	 *	* shared = show shared pools
	 *  * force  = don't check if user has rights on the pool
	*/
	public function getPoolsByArgs($pools, $atts, $userId = false)
	{
		$atts = \LWS\Adminpanel\Tools\Conveniences::sanitizeAttr(\wp_parse_args($atts, array(
			'system'  => '',
			'shared'  => '',
			'force'   => '',
			'showall' => '',
		)));
		$atts['showall'] = \LWS\Adminpanel\Tools\Conveniences::argIsTrue($atts['showall']);
		// No pools specified, fallback to the prefab
		if (!($atts['system'] || $atts['showall'])) {
			return $pools;
		}
		/** Get current user if not specified */
		if (false === $userId) {
			$userId = \get_current_user_id();
		}

		/** Get a list of all pools */
		if ($atts['showall']) {
			$pools = \LWS_WooRewards_Pro::getBuyablePools();
			if (!$atts['force'])
				$pools = $pools->filterByUserCan($userId);
		} elseif ($atts['shared']) {
			$stackId = $this->getStackOfPool($atts['system']);
			if ($stackId) {
				$pools = \LWS_WooRewards_Pro::getBuyablePools()->filterByStackId($stackId);
				if (!$atts['force'])
					$pools = $pools->filterByUserCan($userId);
			} else {
				$pools = \LWS\WOOREWARDS\Collections\Pools::instanciate();
			}
		} else {
			$pools = \LWS\WOOREWARDS\Collections\Pools::instanciate();
			$poolList = array_map('trim', explode(',', $atts['system']));
			foreach ($poolList as $item) {
				$pool = \LWS\WOOREWARDS\PRO\Core\Pool::getOrLoad($item, true);
				if ($pool && $pool->isBuyable() && ($atts['force'] || $pool->userCan($userId)))
					$pools->add($pool);
			}
		}
		return $pools;
	}

	/* Get Available WooCommerce coupons for the provided user */
	public function getCoupons($userId)
	{
		$user = \get_user_by('ID', $userId);
		if( empty($user->user_email) )
			return array();
		$todayDate = strtotime(date('Y-m-d'));
		global $wpdb;
		$query = <<<EOT
SELECT p.ID, p.post_content, p.post_title, p.post_excerpt, e.meta_value AS expiry_date
FROM {$wpdb->posts} as p
INNER JOIN {$wpdb->postmeta} as m ON p.ID = m.post_id AND m.meta_key='customer_email'
LEFT JOIN {$wpdb->postmeta} as l ON p.ID = l.post_id AND l.meta_key='usage_limit'
LEFT JOIN {$wpdb->postmeta} as u ON p.ID = u.post_id AND u.meta_key='usage_count'
LEFT JOIN {$wpdb->postmeta} as e ON p.ID = e.post_id AND e.meta_key='date_expires'
WHERE m.meta_value=%s AND post_type = 'shop_coupon' AND post_status = 'publish'
AND (e.meta_value is NULL OR e.meta_value = '' OR e.meta_value >= '{$todayDate}')
AND (u.meta_value < l.meta_value OR u.meta_value IS NULL OR l.meta_value IS NULL OR l.meta_value=0)
EOT;
		$result = $wpdb->get_results($wpdb->prepare($query, serialize(array($user->user_email))), OBJECT_K);
		if( empty($result) )
			return $result;
		$ids = implode(",", array_map('intval', array_keys($result)));
		$sub = $wpdb->get_results(<<<EOT
SELECT p.ID, v.meta_value AS coupon_amount, o.meta_value AS product_ids, w.meta_value AS discount_type
FROM {$wpdb->posts} as p
LEFT JOIN {$wpdb->postmeta} as w ON p.ID = w.post_id AND w.meta_key='discount_type'
LEFT JOIN {$wpdb->postmeta} as v ON p.ID = v.post_id AND v.meta_key='coupon_amount'
LEFT JOIN {$wpdb->postmeta} as o ON p.ID = o.post_id AND o.meta_key='product_ids'
WHERE p.ID IN ({$ids})
EOT
		, OBJECT_K);

		foreach( $sub as $id => $info )
		{
			foreach( $info as $k => $v )
			$result[$id]->$k = $v;
		}
		return $result;
	}

	/** Get a list of unlockables for a given user
	 * $auth = 'all'|null : Provide all unlockables
	 * $auth = 'avail'|true : Provide only available unlockables
	 * $auth = 'unavail'|false : Provide only unavailable unlockables
	 * @param $pools (\LWS\WOOREWARDS\Abstracts\Collection) if not set, look from @see \LWS_WooRewards_Pro::getBuyablePools()
	 * */
	public function getUserUnlockables($userId, $auth = null, $pools = false)
	{
		if (!$pools) {
			$pools = \LWS_WooRewards_Pro::getBuyablePools();
		}
		$unlockables = \LWS\WOOREWARDS\Collections\Unlockables::instanciate();
		if (\is_string($auth)) {
			$auth = substr($auth, 0, 2);
			if ('av' == $auth) $auth = true;
			elseif ('un' == $auth) $auth = false;
			else $auth = null;
		}
		foreach ($pools->asArray() as $pool)
		{
			if($pool->userCan($userId))
			{
				$type = $pool->getOption('type');
				if($type != \LWS\WOOREWARDS\Core\Pool::T_LEVELLING && !$pool->getOption('direct_reward_mode'))
				{
					$userPoints = $auth==='all' ? PHP_INT_MAX : $pool->getPoints($userId);
					foreach( $pool->getUnlockables()->asArray() as $item )
					{
						if (null === $auth || $auth === (bool)$item->isPurchasable($userPoints, $userId)) {
							$unlockables->add($item, $item->getId());
						}
					}
				}
			}
		}
		return $unlockables->sort()->asArray();
	}

	/* Get the Url page for redeeming rewards */
	public function getUrlTarget($demo=false)
	{
		if( $demo )
		{
			return '#';
		}
		else
		{
			if( null === $this->urlTarget )
			{
				if (!empty($page = get_option('lws_woorewards_reward_claim_page', ''))) {
					$this->urlTarget = \get_permalink($page);
				} else if (\LWS\Adminpanel\Tools\Conveniences::isWC() && \get_option('lws_woorewards_wc_my_account_endpont_loyalty', 'on')) {
					$this->urlTarget = \LWS_WooRewards_Pro::getEndpointUrl('lws_woorewards');
				} else {
					global $wp;
					$this->urlTarget = \add_query_arg($wp->query_vars, \home_url());
				}
			}
			return $this->urlTarget;
		}
	}

	/** Compute a hue (for HSL color) from an arbitrary index value.
	 *	Hue is build by dichotomy on hue wheel
	 *	@param $index (int) greater or equal than zero
	 *	@return (int) between 0 and 360 */
	function indexToHue($index)
	{
		$hue = 0;
		$wheel = 180;
		while( $index && $wheel >= 1.0 )
		{
			$part = ($index % 2);
			$hue += $wheel * $part;
			$index -= $part;
			$index = \floor($index / 2);
			$wheel /= 2.0;
		}
		return $hue;
	}

	/**	@param $hue (int) from 0 ot 360
	 *	@param $lightness (int) from 0 ot 100
	 *	@param $saturation (int) from 0 ot 100
	 *	@return (string) CSS compliant HSL color string. */
	function getHSL($hue=0, $lightness=100, $saturation=50, $addSemicolon=false)
	{
		$hsl = sprintf('hsl(%d, %d%%, %d%%)', \intval($hue) % 360, \intval($lightness), \intval($saturation));
		if( $addSemicolon )
			$hsl .= ';';
		return $hsl;
	}

	/** @see getHSL @see indexToHue */
	function getHSLFromIndex($index, $lightness=100, $saturation=50, $addSemicolon=false)
	{
		return $this->getHSL($this->indexToHue($index), $lightness, $saturation, $addSemicolon);
	}

	/** @return \WC_Product|false */
	function getProductFromOrderItem(&$order, &$item)
	{
		if( \method_exists($item, 'get_product') )
			return $item->get_product();
		else
			return $order->get_product_from_item($item);
	}

	function getSponsorshipOrigins()
	{
		static $origins = false;
		if( false === $origins ) {
			$origins = array(
				array('value' => ''         , 'label' => __("Email", 'woorewards-pro')),
				array('value' => 'sponsor'  , 'label' => __("Email", 'woorewards-pro')),
				array('value' => 'referral' , 'label' => __("Referral Link", 'woorewards-pro')),
				array('value' => 'manual'   , 'label' => __("Manual", 'woorewards-pro')),
				array('value' => 'facebook' , 'label' => __("Facebook", 'woorewards-pro')),
				array('value' => 'twitter'  , 'label' => __("Twitter", 'woorewards-pro')),
				array('value' => 'pinterest', 'label' => __("Pinterest", 'woorewards-pro')),
				array('value' => 'linkedin' , 'label' => __("LinkedIn", 'woorewards-pro')),
				array('value' => 'whatsapp' , 'label' => __("Whatsapp", 'woorewards-pro')),
				array('value' => 'mewe'     , 'label' => __("MeWe", 'woorewards-pro')),
			);
		}
		return \apply_filters('lws_woorewards_sponsorship_origins', $origins);
	}

	/** Support for new placeholders in Expression
	 *	- `points:stack_id` return point amount from given user point reserve.
	 *	- `badge:xx` return 1 or 0 if user own the given badge (replace xx by its id).
	 *	- `role:xx` return 1 or 0 if user own the given role (replace xx by a slug).
	 *  */
	function expressionPlaceholders($value, $placeholder, $options, $testMode=false)
	{
		global $wpdb;
		$lower = strtolower($placeholder);

		// point amount
		if ('points:' == substr($lower, 0, 7)) {
			$stackId = \trim(substr($placeholder, 7));
			if (!$stackId)
				throw new \Exception(sprintf(_x("Placeholder `%s` expects a Point reserve", 'expression', 'woorewards-pro'), $placeholder));
			if ($testMode)
				return 1;

			$userId = (($options['user'] && \is_object($options['user'])) ? $options['user']->ID : $options['user']);
			$stack = \LWS\WOOREWARDS\Collections\PointStacks::instanciate()->create($stackId, $userId);
			return $stack->get();
		}

		// referee count
		if ('referees' === $lower) {
			$userId = (int)(($options['user'] && \is_object($options['user'])) ? $options['user']->ID : $options['user']);
			return (int)$wpdb->get_var($wpdb->prepare(
				"SELECT COUNT(user_id) FROM {$wpdb->usermeta} WHERE meta_key='lws_woorewards_sponsored_by' AND meta_value=%s", $userId
			));
		}

		// points as discount
		if ('pointsoncart' == substr($lower, 0, 12)) {
			$sysSlug = \trim(substr($placeholder, 13)); // after :
			if ($testMode)
				return 1;

			$order = (\is_object($options['order']) ? $options['order'] : \wc_get_order($options['order']));
			if (!$order) return 0;

			if (\is_a($order, '\WC_Order')) {
				return \array_sum(\array_column(\array_filter(\array_map(function($item)use($sysSlug) {
					$meta = $item->get_meta('wr_discount_data');
					if (!$meta) return false;
					if ($sysSlug && $meta['pool_name'] != $sysSlug) return false;
					return $meta;
				}, (array)$order->get_coupons())), 'points'));
			} elseif (\is_a($order, '\WC_Cart')) {
				return \array_sum(\array_column(\array_filter(\array_map(function($coupon)use($sysSlug) {
					if (!isset($coupon->wr_discount_data)) return false;
					if ($sysSlug && $coupon->wr_discount_data['pool_name'] != $sysSlug) return false;
					return $coupon->wr_discount_data;
				}, (array)$order->get_coupons())), 'points'));
			}
		}

		// badge own
		if ('badge:' == substr($lower, 0, 6)) {
			$badgeId = \intval(\trim(substr($placeholder, 6)));
			$badge = $badgeId ? (new \LWS\WOOREWARDS\PRO\Core\Badge($badgeId, $testMode)) : false;

			if (!($badge && (!$testMode || $badge->isValid())))
			throw new \Exception(sprintf(_x("Placeholder `%s` expects a valid Badge Id", 'expression', 'woorewards-pro'), $placeholder));
			if ($testMode)
				return 1;

			$userId = (($options['user'] && \is_object($options['user'])) ? $options['user']->ID : $options['user']);
			return $badge->ownedBy($userId) ? 1 : 0;
		}

		// have role
		if ('role:' == substr($lower, 0, 5)) {
			$role = \trim(substr($placeholder, 5));
			if (!$role)
				throw new \Exception(sprintf(_x("Placeholder `%s` expects a role", 'expression', 'woorewards-pro'), $placeholder));
			if ($testMode)
				return 1;

			if (!$options['user'])
				return 0;
			$user = (\is_object($options['user']) ? $options['user'] : \get_user_by('ID', $options['user']));
			if (!($user && $user->ID))
				return 0;

			return \in_array($role, $user->roles) ? 1 : 0;
		}

		// order
		if ('order:' == substr($lower, 0, 6)) {
			$property = \trim(substr($placeholder, 6));
			if (!$property)
				throw new \Exception(sprintf(_x("Placeholder `%s` expects a property", 'expression', 'woorewards-pro'), $placeholder));
			if (!\function_exists('\wc_get_order'))
				throw new \Exception(sprintf(_x("WooCommerce not installed", 'expression', 'woorewards-pro'), $placeholder));
			if ($testMode) {
				if ($this->getOrderProperty(strtolower($property), false, true))
					return 1;
				else
					throw new \Exception(sprintf(_x("Placeholder `%s`, unknown property", 'expression', 'woorewards-pro'), $placeholder));
			}

			if (!$options['order'])
				return 0;
			$order = (\is_object($options['order']) ? $options['order'] : \wc_get_order($options['order']));
			if (!$order)
				return 0;

			return $this->getOrderProperty(strtolower($property), $order);
		}

		// uses {coupons} for count of all coupons in order
		// use {coupons:/^test_/} for count of coupon with code starting with test_ (RegEx pattern)
		if ('coupons' === substr($lower, 0, 7)) {
			if (!$options['order']) return 0;
			$order = (\is_object($options['order']) ? $options['order'] : \wc_get_order($options['order']));
			if (!$order) return 0;
			$codes = array();
			if (\is_a($order, '\WC_Order')) {
				$codes = $order->get_coupon_codes();
			} elseif (\is_a($order, '\WC_Cart')) {
				$codes = $order->get_applied_coupons();
			}
			if (!$codes) return 0;

			$parts = \explode(':', $placeholder, 2);
			if (count($parts) > 1 && $parts[1]) { // second part is regex
				$codes = \array_filter($codes, function($c)use($parts){
					return \preg_match($parts[1], $c);
				});
			}
			// want only a yes/no: =min@ 1, {coupons}
			return count($codes);
		}

		// use {products_cat:42,65} for count of product in categories with term_id IN 42, 65 list
		if ('products_cat:' === substr($lower, 0, 13)) {
			$property = \trim(substr($placeholder, 13));
			if (!$property) throw new \Exception(sprintf(_x("Placeholder `%s` expects a property", 'expression', 'woorewards-pro'), $placeholder));
			$items = $this->getProductsByCatFromOrder($options, $property);
			if (!$items) return 0;
			// want only a yes/no: =min@ 1, {products_cat}
			return \array_sum(\array_map(array($this, 'getItemQuantity'), $items));
		}

		// use {products_tag:42,65} for count of product using tags with term_id IN 42, 65 list
		if ('products_tag:' === substr($lower, 0, 13)) {
			$property = \trim(substr($placeholder, 13));
			if (!$property) throw new \Exception(sprintf(_x("Placeholder `%s` expects a property", 'expression', 'woorewards-pro'), $placeholder));
			$items = $this->getProductsByTagFromOrder($options, $property);
			if (!$items) return 0;
			// want only a yes/no: =min@ 1, {products_tag}
			return \array_sum(\array_map(array($this, 'getItemQuantity'), $items));
		}

		// uses {products} for count of all product items in order
		// use {products:42,65} for count of product with with id IN 42, 65 list
		if ('products' === substr($lower, 0, 8)) {
			$parts = \explode(':', $placeholder, 2);
			$items = $this->getProductsFromOrder($options, (count($parts) > 1) ? $parts[1] : false);
			if (!$items) return 0;
			// want only a yes/no: =min@ 1, {products}
			return \array_sum(\array_map(array($this, 'getItemQuantity'), $items));
		}

		// use {products_cat:42,65} for sum of all product prices in categories with term_id IN 42, 65 list
		if ('prices_cat:' === substr($lower, 0, 11)) {
			$property = \trim(substr($placeholder, 11));
			if (!$property) throw new \Exception(sprintf(_x("Placeholder `%s` expects a property", 'expression', 'woorewards-pro'), $placeholder));
			$items = $this->getProductsByCatFromOrder($options, $property);
			if (!$items) return 0;
			return $this->getProductItemPrices($items);
		}

		// use {products_tag:42,65} for sum of all product prices using tags with term_id IN 42, 65 list
		if ('prices_tag:' === substr($lower, 0, 11)) {
			$property = \trim(substr($placeholder, 11));
			if (!$property) throw new \Exception(sprintf(_x("Placeholder `%s` expects a property", 'expression', 'woorewards-pro'), $placeholder));
			$items = $this->getProductsByTagFromOrder($options, $property);
			if (!$items) return 0;
			return $this->getProductItemPrices($items);
		}

		// uses {products} for sum of all product prices in order
		// use {products:42,65} for count of product with with id IN 42, 65 list
		if ('prices' === substr($lower, 0, 6)) {
			$parts = \explode(':', $placeholder, 2);
			$items = $this->getProductsFromOrder($options, (count($parts) > 1) ? $parts[1] : false);
			if (!$items) return 0;
			return $this->getProductItemPrices($items);
		}

		// use {products_cat:42,65} for sum of all product prices in categories with term_id IN 42, 65 list
		if ('subprices_cat:' === substr($lower, 0, 14)) {
			$property = \trim(substr($placeholder, 14));
			if (!$property) throw new \Exception(sprintf(_x("Placeholder `%s` expects a property", 'expression', 'woorewards-pro'), $placeholder));
			$items = $this->getProductsByCatFromOrder($options, $property);
			if (!$items) return 0;
			return $this->getProductItemSubPrices($items);
		}

		// use {products_tag:42,65} for sum of all product prices using tags with term_id IN 42, 65 list
		if ('subprices_tag:' === substr($lower, 0, 14)) {
			$property = \trim(substr($placeholder, 14));
			if (!$property) throw new \Exception(sprintf(_x("Placeholder `%s` expects a property", 'expression', 'woorewards-pro'), $placeholder));
			$items = $this->getProductsByTagFromOrder($options, $property);
			if (!$items) return 0;
			return $this->getProductItemSubPrices($items);
		}

		// uses {products} for sum of all product prices in order
		// use {products:42,65} for count of product with with id IN 42, 65 list
		if ('subprices' === substr($lower, 0, 9)) {
			$parts = \explode(':', $placeholder, 2);
			$items = $this->getProductsFromOrder($options, (count($parts) > 1) ? $parts[1] : false);
			if (!$items) return 0;
			return $this->getProductItemSubPrices($items);
		}

		// referral info
		if ('referral:' == substr($lower, 0, 9)) {
			$property = \trim(substr($placeholder, 9));
			if (!$property) throw new \Exception(sprintf(_x("Placeholder `%s` expects a property", 'expression', 'woorewards-pro'), $placeholder));
			$sponsorship = new \LWS\WOOREWARDS\Core\Sponsorship();
			$sponsorId = false;
			$order = false;

			if (isset($options['order']) && $options['order']) {
				$order = (\is_object($options['order']) ? $options['order'] : \wc_get_order($options['order']));
			}
			if ($order && \is_a($order, '\WC_Order')) {
				$info = $sponsorship->getUsersFromOrder($order, true);
				if ($info) $sponsorId = $info->sponsor_id;
			} else {
				$userId = (($options['user'] && \is_object($options['user'])) ? $options['user']->ID : $options['user']);
				$sponsorId = $sponsorship->getSponsorIdFor($userId);
			}
			if (!$sponsorId) return 0;

			return $this->expressionPlaceholders($value, $property, \array_merge($options, array('user' => $sponsorId)), $testMode);
		}

		return $value;
	}

	/** get item line quantity form order or cart item
	 * @return int */
	function getItemQuantity($item)
	{
		if (\is_array($item)) {
			return isset($item['quantity']) ? (int)$item['quantity'] : 1;
		} else {
			return $item->get_quantity();
		}
	}

	/** Extract totals from a list of cart or order items */
	function getProductItemPrices($items)
	{
		$sum = \array_sum(\array_map(function($i){
			if (\is_array($i)) {
				return $i['line_total'];
			} else {
				return $i->get_total();
			}
		}, $items));
		if (\get_option('lws_woorewards_order_amount_includes_taxes', '')) {// inc_tax
			$sum += \array_sum(\array_map(function($i){
				if (\is_array($i)) {
					return $i['line_tax'];
				} else {
					return $i->get_total_tax();
				}
			}, $items));
		}
		return $sum;
	}

	/** Extract sub totals from a list of cart or order items */
	function getProductItemSubPrices($items)
	{
		$sum = \array_sum(\array_map(function($i){
			if (\is_array($i)) {
				return $i['line_subtotal'];
			} else {
				return $i->get_subtotal();
			}
		}, $items));
		if (\get_option('lws_woorewards_order_amount_includes_taxes', '')) {// inc_tax
			$sum += \array_sum(\array_map(function($i){
				if (\is_array($i)) {
					return $i['line_subtotal_tax'];
				} else {
					return $i->get_subtotal_tax();
				}
			}, $items));
		}
		return $sum;
	}

	/** Extract product items from order.
	 * @param $order array|\WC_Order|\WC_Cart|int
	 * @param $property array|string|false if string, explode as comma separated IDs
	 * property is a list of product_id.
	 * If no property, return all product items.
	 * @return array of Order items (or array if from cart) */
	function getProductsFromOrder($order, $property=false)
	{
		if (\is_array($order)) {
			if (!isset($order['order'])) return array();
			$order = $order['order'];
		}
		if (!$order) return array();
		if (!\is_object($order)) {
			$order = \wc_get_order($order);
			if (!$order) return array();
		}

		$item = array();
		if (\is_a($order, '\WC_Order')) {
			$items = \array_filter($order->get_items(), function($item) {return \is_a($item, '\WC_Order_Item_Product');});
		} elseif (\is_a($order, '\WC_Cart')) {
			$items = \array_filter($order->get_cart(), function($item) {return isset($item['product_id']) && $item['product_id'];});
		}
		if (!$items) return array();

		if ($property) {
			if (\is_string($property)) $property = explode(',', $property);
			$property = \array_map('\intval', $property);
			$items = \array_filter($items, function($i)use($property){
				if (\is_array($i)) {
					if (isset($i['variation_id']) && $i['variation_id'] && \in_array($i['variation_id'], $property)) return true;
					else return \in_array($i['product_id'], $property);
				} else {
					return \in_array($i->get_product_id(), $property);
				}
			});
		}
		return $items;
	}

	/** Extract product items from order.
	 * @param $order array|\WC_Order|\WC_Cart|int
	 * @param $property array|string if string, explode as comma separated IDs
	 * property is a list of term_id
	 * @return array of Order items (or array if from cart) */
	function getProductsByCatFromOrder($order, $property=false)
	{
		$items = $this->getProductsFromOrder($order, false);
		if (!$items) return array();

		if (\is_string($property)) $property = explode(',', $property);
		$property = \array_map('\intval', $property);
		return \array_filter($items, function($i)use($property){
			if (\is_array($i)) {
				$p = \LWS\WOOREWARDS\PRO\Conveniences::getProductFromCartItem($i);
				return $p && \array_intersect($p->get_category_ids(), $property);
			} else {
				$p = $i->get_product();
				return $p && \array_intersect($p->get_category_ids(), $property);
			}
		});
	}

	/** Extract product items from order.
	 * @param $order array|\WC_Order|\WC_Cart|int
	 * @param $property array|string if string, explode as comma separated IDs
	 * property is a list of term_id
	 * @return array of Order items (or array if from cart) */
	function getProductsByTagFromOrder($order, $property)
	{
		$items = $this->getProductsFromOrder($order, false);
		if (!$items) return array();

		if (\is_string($property)) $property = explode(',', $property);
		$property = \array_map('\intval', $property);
		return \array_filter($items, function($i)use($property){
			if (\is_array($i)) {
				$p = \LWS\WOOREWARDS\PRO\Conveniences::getProductFromCartItem($i);
				return $p && \array_intersect($p->get_tag_ids(), $property);
			} else {
				$p = $i->get_product();
				return $p && \array_intersect($p->get_tag_ids(), $property);
			}
		});
	}

	function isTaxIncluded()
	{
		static $taxIncluded = null;
		if (null === $taxIncluded)
			$taxIncluded = (bool)\get_option('lws_woorewards_order_amount_includes_taxes', '');
		return $taxIncluded;
	}

	/** $order WC_Order|WC_Cart
	 *	$test if true, only check if $property is supported */
	function getOrderProperty($property, $order, $test=false)
	{
		if ($test) {
			return \in_array($property, array(
				'vat',
				'total_vat_exc',
				'total_vat_inc',
				'total',
				'subtotal',
				'discount',
				'discount_tax',
				'fees',
				'shipping',
				'shipping_tax',
				'onsale',
				'regular',
				'currency',
				'time_created',
				'time_paid',
			));
		}

		if (\is_a($order, '\WC_Order')) {
			switch (strtolower($property)) {
				case 'currency':
					return $order->get_currency();
				case 'time_created':
					return ($d = $order->get_date_created()) ? $d->getTimestamp() : 0;
				case 'time_paid':
					return ($d = $order->get_date_paid()) ? $d->getTimestamp() : 0;
				case 'vat':
					return $order->get_total_tax();
				case 'total_vat_exc':
					return ($order->get_total() - $order->get_total_tax());
				case 'total_vat_inc':
				case 'total':
					return $order->get_total();
				case 'subtotal':
					return $order->get_subtotal();
				case 'discount':
					return $order->get_total_discount();
				case 'discount_tax':
					return $order->get_discount_tax();
				case 'fees':
					return $order->get_total_fees();
				case 'shipping':
					return \floatval($order->get_shipping_total());
				case 'shipping_tax':
					return \floatval($order->get_shipping_tax());
				case 'onsale':
					$sum = 0.0;
					foreach ($order->get_items() as $item) {
						if (\is_a($item, '\WC_Order_Item_Product')) {
							$product = $item->get_product();
							if ($product && $product->is_on_sale()) {
								$sum += \floatval($order->get_line_subtotal($item, $this->isTaxIncluded(), false));
							}
						}
					}
					return $sum;
				case 'regular':
					$sum = 0.0;
					foreach ($order->get_items() as $item) {
						if (\is_a($item, '\WC_Order_Item_Product')) {
							$product = $item->get_product();
							if ($product && !$product->is_on_sale()) {
								$sum += \floatval($order->get_line_subtotal($item, $this->isTaxIncluded(), false));
							}
						}
					}
					return $sum;
				default:
					throw new \Exception(sprintf(_x("Placeholder `%s`, unknown property", 'expression', 'woorewards-pro'), $property));
			}
		} elseif (\is_a($order, '\WC_Cart')) {
			switch (strtolower($property)) {
				case 'vat':
					return $order->get_total_tax();
				case 'total_vat_exc':
					return ($order->get_total('edit') - $order->get_total_tax());
				case 'total_vat_inc':
				case 'total':
					return $order->get_total('edit');
				case 'subtotal':
					return $order->get_subtotal();
				case 'discount':
					return $order->get_discount_total();
				case 'fees':
					return $order->get_fee_total();
				case 'shipping':
					return \floatval($order->get_shipping_total());
				case 'onsale':
					$sum = 0.0;
					foreach ($order->get_cart() as $item) {
						$isVar = (isset($item['variation_id']) && $item['variation_id']);
						$pId = $isVar ? $item['variation_id'] : (isset($item['product_id']) ? $item['product_id'] : false);
						if ($pId && ($product = \wc_get_product($pId))) {
							if ($product->is_on_sale()) {
								$qty = isset($item['quantity']) ? intval($item['quantity']) : 1;
								if ($this->isTaxIncluded())
									$sum += floatval(\wc_get_price_including_tax($product)) * $qty;
								else
									$sum += floatval(\wc_get_price_excluding_tax($product)) * $qty;
							}
						}
					}
					return $sum;
				case 'regular':
					$sum = 0.0;
					foreach ($order->get_cart() as $item) {
						$isVar = (isset($item['variation_id']) && $item['variation_id']);
						$pId = $isVar ? $item['variation_id'] : (isset($item['product_id']) ? $item['product_id'] : false);
						if ($pId && ($product = \wc_get_product($pId))) {
							if (!$product->is_on_sale()) {
								$qty = isset($item['quantity']) ? intval($item['quantity']) : 1;
								if ($this->isTaxIncluded())
									$sum += floatval(\wc_get_price_including_tax($product)) * $qty;
								else
									$sum += floatval(\wc_get_price_excluding_tax($product)) * $qty;
							}
						}
					}
					return $sum;
				default:
					throw new \Exception(sprintf(_x("Placeholder `%s`, unknown property", 'expression', 'woorewards-pro'), $property));
			}
		}
	}

	/** @return false|\WC_Product the variation if any */
	public static function getProductFromCartItem($item)
	{
		if (isset($item['variation_id']) && $item['variation_id']) {
			return \wc_get_product($item['variation_id']);
		} elseif (isset($item['product_id']) && $item['product_id']) {
			return \wc_get_product($item['product_id']);
		} else {
			return false;
		}
	}
}
