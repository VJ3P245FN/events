<?php

namespace LWS\WOOREWARDS\PRO\Ui\Legacy;

// don't call the file directly
if (!defined('ABSPATH')) exit();

/** Provide a widget to let display rewards.
 * Can be used as a Widget, a Shortcode [lws_rewards] or a Guttenberg block (soon).
 * Rewards can be filtered by pool
 * For a looged in user, we can filter only the unlockable ones. */
class RewardsWidget extends \LWS\WOOREWARDS\Ui\Widget
{
	private $stygen = false;

	public static function install()
	{
		self::register(\get_class());
		$me = new self(false);
		\add_shortcode('wr_show_rewards', array($me, 'showRewards'));
		\add_filter('lws_adminpanel_stygen_content_get_' . 'rewards_template', array($me, 'stygenRewards'));
		\add_filter('lws_adminpanel_stygen_content_get_' . 'loyalties_template', array($me, 'stygenLeveling'));
		\add_action('wp_enqueue_scripts', array($me, 'registerScripts'));
		\add_action('admin_enqueue_scripts', array($me, 'registerScripts'));
	}

	function registerScripts()
	{
		\wp_register_style('woorewards-standard-table-rewards', LWS_WOOREWARDS_PRO_CSS . '/templates/rewards.css?stygen=lws_woorewards_rewards_template', array(), LWS_WOOREWARDS_PRO_VERSION);
		\wp_register_style('woorewards-standard-grid-rewards', LWS_WOOREWARDS_PRO_CSS . '/templates/gridrewards.css?stygen=lws_woorewards_rewards_template', array(), LWS_WOOREWARDS_PRO_VERSION);
		\wp_register_style('woorewards-leveling-rewards', LWS_WOOREWARDS_PRO_CSS . '/templates/loyalties.css?stygen=lws_woorewards_loyalties_template', array(), LWS_WOOREWARDS_PRO_VERSION);
		\wp_register_script('woorewards-rewardswidget', LWS_WOOREWARDS_PRO_JS . '/rewardswidget.js', array('jquery'), LWS_WOOREWARDS_PRO_VERSION);
	}

	/** Will be instanciated by WordPress at need */
	public function __construct($asWidget = true)
	{
		if ($asWidget) {
			parent::__construct(
				'lws_woorewards_rewardlistwidget',
				__("MyRewards Reward list", 'woorewards-pro'),
				array(
					'description' => __("Display the rewards awaiting for your customers.", 'woorewards-pro')
				)
			);
		}
	}

	/** ensure all required fields exist. */
	function update($new_instance, $old_instance)
	{
		$new_instance = $this->parseArgs($new_instance, true);
		return $new_instance;
	}

	protected function defaultArgs()
	{
		return array(
			'title'		=> '',
			'granted'	=> 'all',
			'url'		=> '',
			'source'	=> 'widget'
		);
	}

	/** Handle RetroCompatibility */
	protected function parseArgs($instance, $withPoolArgs = false)
	{
		$instance = \wp_parse_args($instance, $this->defaultArgs());
		if (!isset($instance['system'])) {
			if (isset($instance['pool_name']))
				$instance['system'] = $instance['pool_name'];
			else if (isset($instance['pool']))
				$instance['system'] = $instance['pool'];
		}
		if (!isset($instance['shared']) && isset($instance['granted']) && $instance['granted'] == 'shared') {
			// Handle the previous misuse of "granted"
			$instance['shared'] = true;
		}
		if ($withPoolArgs)
			$instance = \array_merge(array('system' => '', 'shared' => '', 'force' => ''), $instance);
		return $instance;
	}

	/**	Display the widget,
	 *	@see https://developer.wordpress.org/reference/classes/wp_widget/
	 * 	display parameters in $args
	 *	get option from $instance */
	public function widget($args, $instance)
	{
		$atts = $this->parseArgs($instance);
		$user = \wp_get_current_user();
		$data = $this->getUnlockables($atts, $user);
		if ($data && $data['all_direct'])
			return;

		echo $args['before_widget'];
		echo $args['before_title'];
		echo \apply_filters('widget_title', empty($instance['title']) ? _x("Rewards", "frontend widget", 'woorewards-pro') : $instance['title'], $instance);
		echo $args['after_title'];

		if (!($data && $data['rewards'])) {
			echo __("No reward available", 'woorewards-pro');
		} else {
			echo $this->getContent($atts, $data, $user);
		}

		echo $args['after_widget'];
	}


	/** Widget parameters (admin) */
	public function form($instance)
	{
		$instance = $this->parseArgs($instance, true);

		// title
		$this->eFormFieldText(
			$this->get_field_id('title'),
			__("Title", 'woorewards-pro'),
			$this->get_field_name('title'),
			\esc_attr($instance['title']),
			\esc_attr(_x("Rewards", "frontend widget", 'woorewards-pro'))
		);

		$options = array();
		foreach (\LWS\WOOREWARDS\Collections\Pools::instanciate()->load(array('deep' => false))->asArray() as $pool) {
			$options[$pool->getId()] = $pool->getOption('display_title');
		}

		// pool
		$this->eformFieldSelect(
			$this->get_field_id('system'),
			__("Select a Loyalty System", 'woorewards-pro'),
			$this->get_field_name('system'),
			$options,
			isset($instance['system']) ? $instance['system'] : ''
		);

		// behavior
		$this->eFormFieldSelect(
			$this->get_field_id('granted'),
			__("Select Rewards to Show", 'woorewards-pro'),
			$this->get_field_name('granted'),
			array(
				'all'      => __("All", 'woorewards-pro'),
				'only'     => __("Unlockables only", 'woorewards-pro'),
				'excluded' => __("Not unlockables only", 'woorewards-pro'),
			),
			$instance['granted']
		);

		// shared systems
		$this->eformFieldCheckbox(
			$this->get_field_id('shared'),
			__("Show Rewards from shared systems", 'woorewards-pro'),
			$this->get_field_name('shared'),
			\esc_attr($instance['shared'])
		);

		// force display
		$this->eformFieldCheckbox(
			$this->get_field_id('force'),
			__("Force display for all users", 'woorewards-pro'),
			$this->get_field_name('force'),
			\esc_attr($instance['force'])
		);

		// url
		$this->eFormFieldText(
			$this->get_field_id('url'),
			__("Redirect URL (Optional)", 'woorewards-pro'),
			$this->get_field_name('url'),
			\esc_attr($instance['url'])
		);
	}

	/** @brief shortcode [wr_show_rewards]
	 *	Display a stylable reward list presentation.
	 *	usage: All attributes are optionnal
	 *	@code
	 *	[wr_show_rewards system="<pool_name|pool_id>" title="<my Own Title>" shared="<shared>" force="<force>" granted="<only|excluded|all>"]
	 *	@endcode */
	public function showRewards($atts = array(), $content = '')
	{
		if (!\is_array($atts))
			$atts = array();
		$atts['source'] = 'shortcode';
		$atts = $this->parseArgs($atts);
		$user = \wp_get_current_user();
		$data = $this->getUnlockables($atts, $user);
		if (!($data && $data['rewards']))
			return $content;
		if ($data['all_direct'])
			return '';
		return $this->getContent($atts, $data, $user);
	}

	public function getContent($atts, $data, $user)
	{
		$type = $data['type'];
		$content = "<div class='lwss_selectable lws-main-conteneur {$type}' data-type='Main Border'>";
		if ($atts['title'] && $atts['source'] == 'shortcode') {
			$content .= "<h2 class='lwss_selectable lws-rl-title {$type}' data-type='Title'>{$atts['title']}</h2>";
		}

		if ($data['type'] == \LWS\WOOREWARDS\Core\Pool::T_STANDARD) {
			$content .= $this->getStandardRewards($data, $user, $atts);
		} else {
			$content .= $this->getLevelingRewards($data, $user, $atts);
		}
		$content .= "</div>";
		return $content;
	}

	private function getUnlockables($atts, $user)
	{
		// look for the appropriate pools depending on args
		$pools = \apply_filters('lws_woorewards_get_pools_by_args', false, $atts);
		if (!($pools && $pools->count()))
			return false;

		$type = false;
		if (isset($atts['system']))
			$type = \LWS\WOOREWARDS\PRO\Conveniences::instance()->getTypeOfPool($atts['system']);
		if (!$type)
			$type = $pools->first()->getOption('type');
		$userPoints = 0;
		$unlockables = \LWS\WOOREWARDS\Collections\Unlockables::instanciate();
		$rewards = array();

		$directMode = true;
		$pools = $pools->filterByType($type);
		foreach ($pools->asArray() as $pool) {
			if ($pool->getOption('direct_reward_mode'))
				continue;
			$directMode = false;

			$prevented = $pool->isUnlockPrevented();
			$userPoints = $pool->getPoints($user->ID);
			foreach ($pool->getUnlockables()->asArray() as $item) {
				$buyable = $item->isPurchasable($userPoints, $user->ID);
				$toAdd = true;
				if ('only' == $atts['granted'])
					$toAdd = $buyable;
				else if ('excluded' == $atts['granted'])
					$toAdd = !$buyable;

				if ($toAdd) {
					$rewards[$item->getId()] = array(
						'buyable' 	 => $buyable,
						'prevented'	 => $prevented,
						'cost'			 => $item->getUserCost($user->ID),
						'unlockable' => $item,
						'userPoints' => $userPoints,
					);
					$unlockables->add($item, $item->getId());
				}
			}
		}

		$data = array(
			'type'       => $type,
			'userPoints' => $userPoints,
			'rewards'    => array(),
			'all_direct' => $pools->count() && $directMode,
		);
		// get sorted rewards
		foreach ($unlockables->sort()->asArray() as &$u)
			$data['rewards'][] = $rewards[$u->getId()];
		return $data;
	}

	/**
	 * Checks if size option still exists preventing of unknown size to be used
	 * If option size isn't available anymore return default value lws_wr_thumbnail
	 * @return string verified size key or default lws_wr_thumbnail
	 */
	private function getVerifiedSize()
	{
		$size = \get_option('lws_woorewards_rewards_image_size', 'lws_wr_thumbnail');
		return \LWS\Adminpanel\Tools\MediaHelper::getVerifiedMediaSize($size, 'lws_wr_thumbnail');
	}

	private function getStandardRewards($data, $user, $atts = array())
	{
		$isGrid = \get_option('lws_woorewards_rewards_use_grid', 'on');
		$isGrid ? \wp_enqueue_style('woorewards-standard-grid-rewards') : \wp_enqueue_style('woorewards-standard-table-rewards');

		\wp_enqueue_script('woorewards-rewardswidget');

		$content = '';
		foreach ($data['rewards'] as $reward) {
			if (empty($img = $reward['unlockable']->getThumbnailImage($this->getVerifiedSize())) && $this->stygen) {
				$img = "<div class='lws-reward-thumbnail lws-icon lws-icon-image'></div>";
			}
			if ($isGrid) {
				$content .= "<div class='lwss_selectable lws-rewards-reward' data-type='Reward Grid'>";
				$content .= "<div class='lwss_selectable lws-rewards-cell-img' data-type='Rewards Image'>$img</div>";
			} else {
				$content .= "<tr><td class='lwss_selectable lws-rewards-cell-img' data-type='Rewards Image'>$img";
				$content .= "</td><td class='lwss_selectable lws-rewards-cell-left' data-type='Rewards Cell' width='100%'>";
			}
			$content .= "<div class='lwss_selectable lws-reward-name' data-type='Reward Name'>" . $reward['unlockable']->getTitle() . "</div>";
			$content .= "<div class='lwss_selectable lws-reward-desc' data-type='Reward Description'>" . $reward['unlockable']->getCustomDescription() . "</div>";
			if ($user && $reward['unlockable']->noMorePurchase($user->ID)) {
				$info = __("Already unlocked.", 'woorewards-pro');
				$content .= "<div class='lwss_selectable lws-reward-cost' data-type='Reward Cost'>{$info}</div>";
			} else if (empty($user) || $reward['buyable']) {
				$cost = \lws_get_option('lws_woorewards_rewards_widget_cost', __("This reward is worth [rw_cost]", 'woorewards-pro'));
				if (!$this->stygen) {
					$cost = \apply_filters('wpml_translate_single_string', $cost, 'Widgets', "WooRewards - Rewards Widget - Reward Cost");
					$cost = str_replace('[rw_cost]', \LWS_WooRewards::formatPointsWithSymbol($reward['unlockable']->getUserCost($user->ID, 'front'), $reward['unlockable']->getPoolName()), $cost);
				}
				$content .= "<div class='lwss_selectable lwss_modify lws-reward-cost' data-id='lws_woorewards_rewards_widget_cost' data-type='Reward Cost'>";
				$content .= "<span class='lwss_modify_content'>{$cost}</span></div>";
			} else {
				$cost = \lws_get_option('lws_woorewards_rewards_widget_more', __("This reward is worth [rw_cost], you need [rw_more] more", 'woorewards-pro'));
				if (!$this->stygen) {
					$cost = \apply_filters('wpml_translate_single_string', $cost, 'Widgets', "WooRewards - Rewards Widget - More Points Needed");
					$cost = str_replace('[rw_cost]', \LWS_WooRewards::formatPointsWithSymbol($reward['unlockable']->getUserCost($user->ID, 'front'), $reward['unlockable']->getPoolName()), $cost);
					$cost = str_replace('[rw_more]', \LWS_WooRewards::formatPointsWithSymbol($reward['cost'] - $reward['userPoints'], $reward['unlockable']->getPoolName()), $cost);
				}
				$content .= "<div class='lwss_selectable lwss_modify lws-reward-more' data-id='lws_woorewards_rewards_widget_more' data-type='Need More points'>";
				$content .= "<span class='lwss_modify_content'>{$cost}</span></div>";
			}
			$content .= $isGrid ? "<div class='lwss_selectable lws-rewards-cell-unlock' data-type='Unlock Container'>" : "</td><td class='lwss_selectable lws-rewards-cell-right' data-type='Rewards Cell'>";

			// redeem button
			if ($reward['buyable'] && !$reward['prevented']) {
				$btn = \lws_get_option('lws_woorewards_rewards_widget_unlock', __("Unlock", 'woorewards-pro'));
				if (!$this->stygen) {
					if (!$atts['url'])
						$atts['url'] = \LWS\WOOREWARDS\PRO\Conveniences::instance()->getUrlTarget($this->stygen);
					$href = esc_attr(\LWS\WOOREWARDS\PRO\Core\RewardClaim::addUrlUnlockArgs($atts['url'], $reward['unlockable'], $user));
					$content .= "<button data-href='$href' class='lwss_selectable lws-reward-redeem' data-type='Unlock button'>{$btn}</button>";
				} else {
					$content .= "<div class='lwss_selectable lwss_modify lws-reward-redeem' data-id='lws_woorewards_rewards_widget_unlock' data-type='Unlock button'>";
					$content .= "<span class='lwss_modify_content'>{$btn}</span></div>";
				}
			} else {
				$btn = \lws_get_option('lws_woorewards_rewards_widget_locked', __("Locked", 'woorewards-pro'));
				if (!$this->stygen) {
					$btn = \apply_filters('wpml_translate_single_string', $btn, 'Widgets', "WooRewards - Rewards Widget - Locked Button");
				}
				$content .= "<div class='lwss_selectable lwss_modify lws-reward-redeem-not' data-id='lws_woorewards_rewards_widget_locked' data-type='Unlock Unavailable'>";
				$content .= "<span class='lwss_modify_content'>{$btn}</span></div>";
			}
			$content .= $isGrid ? "</div></div>" : "</td></tr>";
		}
		if (!empty($content)) {
			if ($isGrid) {
				$content = "<div class='lwss_selectable lws-sub-conteneur standard' data-type='Rewards Grid'>$content</div>";
			} else {
				$content = "<table class='lwss_selectable lws-sub-conteneur standard' data-type='Rewards Table'><tbody>$content</tbody></table>";
			}
		}
		return $content;
	}

	private function getLevelingRewards($data, $user, $atts = array())
	{
		\wp_enqueue_style('woorewards-leveling-rewards');
		$done = array();
		if ($user && $user->ID) {
			$done = \get_user_meta($user->ID, 'lws-loyalty-done-steps', false);
		}

		$first = true;
		$content = '';
		$cost = -9999;
		foreach ($data['rewards'] as $reward) {
			$owned = in_array($reward['unlockable']->getId(), $done);
			if ($this->stygen) {
				$owned = $reward['cost'] <= $reward['userPoints'];
			}
			$css = $owned ? 'lws-reward-owned' : 'lws-reward-pending';
			$cssItem = ($owned || $reward['unlockable']->noMorePurchase($user->ID)) ? 'lws-reward-owned' : 'lws-reward-pending';
			$datatype = $owned ? 'Owned' : 'Pending';
			if ($reward['cost'] != $cost) {
				if (!$first)
					$content .= "</div><div class='lwss_selectable lws-ly' data-type='Reward Bloc'>";
				$first = false;
				$cost = $reward['cost'];
				$content .= "<div class='lwss_selectable lws-ly-title $css' data-type='$datatype Title Line'>";
				$content .= "<div class='lwss_selectable lws-ly-name' data-type='Loyalty Name'>" . $reward['unlockable']->getGroupedTitle('view') . "</div>";
				$content .= "<div class='lwss_selectable lws-ly-points' data-type='Points'>" . \LWS_WooRewards::formatPointsWithSymbol($reward['unlockable']->getUserCost($user->ID, 'front'), $reward['unlockable']->getPoolName()) . "</div>";
				$content .= "</div>";
			}
			if (empty($img = $reward['unlockable']->getThumbnailImage()) && $this->stygen)
				$img = "<div class='lws-ly-thumbnail lws-icon lws-icon-image'></div>";
			$content .= "<div class='lwss_selectable lws-ly-content $cssItem' data-type='$datatype Loyalty Reward'>";
			$content .= "<div class='lwss_selectable lws-ly-img' data-type='Reward Image'>$img</div><div class='lwss_selectable lws-ly-descr' data-type='Reward Descrition'>";
			$content .= "<div class='lwss_selectable lws-ly-rtitle' data-type='Reward Title'>" . $reward['unlockable']->getTitle() . "</div>";
			$content .= "<div class='lwss_selectable lws-ly-rdetail' data-type='Reward Detail'>" . $reward['unlockable']->getCustomDescription() . "</div>";
			$content .= "</div></div>";
		}
		if (!empty($content)) {
			$content = "<div class='lwss_selectable lws-ly' data-type='Reward Bloc'>$content</div>";
		}
		return $content;
	}

	// Get Sample Data for Stygen
	function stygenRewards($snippet)
	{
		$this->stygen = true;
		$opts = array(
			'type'          => \LWS\WOOREWARDS\Core\Pool::T_STANDARD,
			'point_timeout' => '6M',
		);
		$atts = array(
			'title'         => __("Example points and rewards system", 'woorewards-pro'),
			'source'		=> 'shortcode'
		);
		$start = \date_create()->sub(new \DateInterval('P7D'));
		$opts['period_start'] = $start->format('Y-m-d');
		$opts['period_mid']   = $start->add(new \DateInterval('P1M'))->format('Y-m-d');
		$opts['period_end']   = $start->add(new \DateInterval('P1M'))->format('Y-m-d');

		$snippet = $this->getContent($this->parseArgs($atts), $this->getFakeUnlockables($opts), \wp_get_current_user());
		$this->stygen = false;
		return $snippet;
	}

	function stygenLeveling($snippet)
	{
		$this->stygen = true;
		$opts = array(
			'type'         => \LWS\WOOREWARDS\Core\Pool::T_LEVELLING,
		);
		$atts = array(
			'title'         => __("Leveling points and rewards system", 'woorewards-pro'),
			'source'		=> 'shortcode'
		);
		$start = \date_create()->sub(new \DateInterval('P4D'));
		$opts['period_start'] = $start->format('Y-m-d');
		$opts['period_end']   = $start->add(new \DateInterval('P72D'))->format('Y-m-d');

		$snippet = $this->getContent($this->parseArgs($atts), $this->getFakeUnlockables($opts), \wp_get_current_user());
		$this->stygen = false;
		return $snippet;
	}

	protected function getFakeUnlockables($options = array())
	{
		$fakeData = array();
		$pool = \LWS\WOOREWARDS\Collections\Pools::instanciate()->create('demo')->last();
		$pool->setOptions($options);
		$examples = \LWS\WOOREWARDS\Collections\Unlockables::instanciate()->create()->byCategory(false, array($pool->getOption('type')));
		$cost = $min = random_int(0, 128);
		$index = $pool->getOption('type') == \LWS\WOOREWARDS\Core\Pool::T_LEVELLING ? 0 : -1;

		$examples->apply(function ($item) use (&$cost, &$pool, &$index) {
			if (\method_exists($item, 'setTestValues'))
				$item->setTestValues();
			$item->setTitle($item->getTitle() . __(" (EXAMPLE)", 'woorewards-pro'));
			if ($index < 0 || ($index % 2) == 0)
				$cost += 10;
			if ($index >= 0)
				$item->setGroupedTitle(sprintf(__("Example level %d", 'woorewards-pro'), ++$index));
			$pool->addUnlockable($item, $cost);
		});

		$demoPoints = $min + 42;
		if ($examples->count() > 1) {
			$demoPoints = $cost - 8;
		}

		$fakeData['type'] = $pool->getOption('type');
		$fakeData['userPoints'] = $demoPoints;
		foreach ($examples->asArray() as $item) {
			$fakeData['rewards'][] = array(
				'buyable'    => $item->isPurchasable($demoPoints, \get_current_user_id()),
				'prevented'  => false,
				'cost'       => $item->getCost(),
				'unlockable' => $item,
				'userPoints' => $demoPoints,
			);
		}
		return $fakeData;
	}
}
