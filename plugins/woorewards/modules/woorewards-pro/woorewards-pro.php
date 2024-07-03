<?php

/**
 * Plugin Name: MyRewards Pro
 * Description: Loyalty and Rewards system for WooCommerce.
 * Plugin URI: https://plugins.longwatchstudio.com
 * Author: Long Watch Studio
 * Author URI: https://longwatchstudio.com
 * Version: 5.3.1
 * Text Domain: woorewards-pro
 * WC requires at least: 7.1.0
 * WC tested up to: 8.6
 *
 * Copyright (c) 2022 Long Watch Studio (email: plugins@longwatchstudio.com). All rights reserved.
 *
 */

// don't call the file directly
if (!defined('ABSPATH')) exit();

/**
 * @class LWS_WooRewards_Pro The class that holds the entire plugin
 */
final class LWS_WooRewards_Pro
{
	private static $loadedPools = false;
	private static $achievements = false;
	private static $delayedMailStack = array();

	public static function init()
	{
		static $instance = false;
		if (!$instance) {
			$instance = new self();
			$instance->defineConstants();
			\add_action('plugins_loaded', array($instance, 'load_plugin_textdomain'));

			\add_action('before_woocommerce_init', function() { // HPOS support
				if (\class_exists(\Automattic\WooCommerce\Utilities\FeaturesUtil::class)) {
					$dir = \substr(__FILE__, \strlen(WP_PLUGIN_DIR));
					if (false === \strpos($dir, 'modules')) { // standalone execution
						\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
					}
				}
			});

			add_action('lws_adminpanel_plugins', array($instance, 'plugin'));
			add_filter('plugin_row_meta', array($instance, 'addLicenceLink'), 10, 4);
			add_filter('lws-ap-release-woorewards', function ($rc) {
				return ($rc . 'pro');
			});
			add_filter('lws_woorewards_redirect_after_install_url', function () {
				return array(
					'page' => LWS_WOOREWARDS_PAGE . '.system',
					'tab' => 'lic',
				);
			});

			$instance->earlyInstall();
		}
		return $instance;
	}

	public function addLicenceLink($links, $file, $data, $status)
	{
		if ('woorewards' != strtolower(dirname($file)))
			return $links;

		if (defined('LWS_WOOREWARDS_ACTIVATED') && LWS_WOOREWARDS_ACTIVATED)
			$label = __('Licence', 'woorewards-pro');
		else
			$label = __('Add Licence Key', 'woorewards-pro');

		$url = \esc_attr(\add_query_arg(array(
			'page' => LWS_WOOREWARDS_PAGE . '.system',
			'tab' => 'lic',
		), admin_url('admin.php')));

		$links[] = "<a href='{$url}'>{$label}</a>";
		return $links;
	}

	public function v()
	{
		static $version = '';
		if (empty($version)) {
			if (!function_exists('get_plugin_data')) require_once(ABSPATH . 'wp-admin/includes/plugin.php');
			$data = \get_plugin_data(__FILE__, false);
			$version = (isset($data['Version']) ? $data['Version'] : '0');
		}
		return $version;
	}

	/** Load translation file
	 * If called via a hook like this
	 * @code
	 * add_action( 'plugins_loaded', array($instance,'load_plugin_textdomain'), 1 );
	 * @endcode
	 * Take care no text is translated before. */
	function load_plugin_textdomain()
	{
		load_plugin_textdomain('woorewards-pro', FALSE, substr(dirname(__FILE__), strlen(WP_PLUGIN_DIR)) . '/languages/');
	}

	/**
	 * Define the plugin constants
	 *
	 * @return void
	 */
	private function defineConstants()
	{
		define('LWS_WOOREWARDS_PRO_VERSION', '5.3.1');
		define('LWS_WOOREWARDS_PRO_FILE', __FILE__);

		define('LWS_WOOREWARDS_PRO_PATH', dirname(LWS_WOOREWARDS_PRO_FILE));
		define('LWS_WOOREWARDS_PRO_INCLUDES', LWS_WOOREWARDS_PRO_PATH . '/include');
		define('LWS_WOOREWARDS_PRO_SNIPPETS', LWS_WOOREWARDS_PRO_PATH . '/snippets');
		define('LWS_WOOREWARDS_PRO_ASSETS', LWS_WOOREWARDS_PRO_PATH . '/assets');

		define('LWS_WOOREWARDS_PRO_URL', plugins_url('', LWS_WOOREWARDS_PRO_FILE));
		define('LWS_WOOREWARDS_PRO_IMG', plugins_url('/img', LWS_WOOREWARDS_PRO_FILE));
		define('LWS_WOOREWARDS_PRO_JS', plugins_url('/js', LWS_WOOREWARDS_PRO_FILE));
		define('LWS_WOOREWARDS_PRO_CSS', plugins_url('/styling/css', LWS_WOOREWARDS_PRO_FILE));
		define('LWS_WOOREWARDS_PRO_DOMAIN', 'woorewards-pro');

		global $wpdb;
		$wpdb->lwsWooRewardsBadges = $wpdb->prefix . 'lws_wr_userbadge';
		$wpdb->lwsWebhooksEvents = ($wpdb->base_prefix . 'lws_webhooks_events');
	}

	public function plugin()
	{
		$ret = \apply_filters('lws_manager_instance', false);
		if ($ret && defined('LWS_WOOREWARDS_FILE'))
			$ret->instance->install(LWS_WOOREWARDS_FILE, LWS_WOOREWARDS_PAGE, LWS_WOOREWARDS_UUID, 'LWS_WOOREWARDS_ACTIVATED', 'woorewards.system');
		if (defined('LWS_WOOREWARDS_ACTIVATED') && LWS_WOOREWARDS_ACTIVATED)
			$this->install();
	}

	/** autoload WooRewards core and collection classes. */
	public function autoload($class)
	{
		if (substr($class, 0, 19) == 'LWS\WOOREWARDS\PRO\\') {
			$rest = substr($class, 19);
			$publicNamespaces = array(
				'Collections', 'Core', 'Unlockables', 'Events'
			);
			$publicClasses = array(
				'Conveniences',
				'Wizard',
			);

			if (in_array(explode('\\', $rest, 2)[0], $publicNamespaces) || in_array($rest, $publicClasses)) {
				$basename = str_replace('\\', '/', strtolower($rest));
				$filepath = LWS_WOOREWARDS_PRO_INCLUDES . '/' . $basename . '.php';
				@include_once $filepath;
				return true;
			}
		}
	}

	function filterShowcase($posts)
	{
		for ($i = 0; $i < count($posts); ++$i) {
			if ($posts[$i]->slug == 'woorewards')
				unset($posts[$i]);
		}
		return array_values($posts);
	}

	/** not sure about valid pro licence yet. */
	private function earlyInstall()
	{
		if (\is_admin() && !(defined('DOING_AJAX') && DOING_AJAX)) {
			require_once LWS_WOOREWARDS_PRO_INCLUDES . '/updater.php';
			add_action('setup_theme', array('\LWS\WOOREWARDS\PRO\Updater', 'checkUpdate'), -99); // after free update
		}

		$pageId = 'woorewards.achievement';
		\add_filter('lws_adminpanel_plugin_version_' . $pageId, function ($v) {
			return LWS_WOOREWARDS_PRO_VERSION;
		});
		\add_filter('lws_woorewards_lic_page_ids', function ($pageIds) use ($pageId) {
			$pageIds[] = $pageId;
			return $pageIds;
		});

		\add_filter('lws_woorewards_endpoint_slug', array($this, 'getEndpointSlug'), 20);

		\add_action('lws_manager_license_active', function($slug){
			if ('woorewards' === $slug) {
				\lws_admin_delete_notice('lws-wre-pool-nothing-loaded');
			}
		}, 10, 1);
	}

	static function installPools()
	{
		self::$loadedPools = \LWS\WOOREWARDS\Collections\Pools::instanciate()->load(array(
			'post_status' => array('publish', 'private')
		))->install();
		\do_action('lws_woorewards_pools_loaded', self::$loadedPools);

		self::$achievements = \LWS\WOOREWARDS\PRO\Collections\Achievements::instanciate();
		if (!empty(\get_option('lws_woorewards_manage_badge_enable', 'on'))) {
			self::$achievements->load(array(
				'post_status' => array('publish', 'private')
			))->install();
			\do_action('lws_woorewards_achievements_loaded', self::$achievements);
		}
	}

	/* @see \LWS\WOOREWARDS\PRO\Core\Achievement */
	static function getLoadedAchievements()
	{
		if (self::$achievements !== false) {
			return self::$achievements;
		} else {
			error_log(__FUNCTION__ . " called too soon. Wait after 'setup_theme' hook or use the action 'lws_woorewards_achievements_loaded'.");
			return \LWS\WOOREWARDS\PRO\Collections\Achievements::instanciate();
		}
	}

	/* active < buyable < loaded @see \LWS\WOOREWARDS\PRO\Core\Pool */
	static function getLoadedPools()
	{
		if (self::$loadedPools !== false) {
			return self::$loadedPools;
		} else {
			error_log(__FUNCTION__ . " called too soon. Wait after 'setup_theme' hook or use the action 'lws_woorewards_pools_loaded'.");
			return \LWS\WOOREWARDS\Collections\Pools::instanciate();
		}
	}

	/* active < buyable < loaded @see \LWS\WOOREWARDS\PRO\Core\Pool */
	static function getBuyablePools()
	{
		static $buyablePools = false;
		if ($buyablePools !== false) {
			return $buyablePools;
		} else if (self::$loadedPools !== false) {
			$buyablePools = self::$loadedPools->filter(function ($item) {
				return $item->isBuyable();
			});
			return $buyablePools;
		} else {
			error_log(__FUNCTION__ . " called too soon. Wait after 'setup_theme' hook or use the action 'lws_woorewards_pools_loaded'.");
			if (defined('LWS_DEBUG') && LWS_DEBUG)
				error_log(print_r(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS), true));
			return \LWS\WOOREWARDS\Collections\Pools::instanciate();
		}
	}

	/* active < buyable < loaded @see \LWS\WOOREWARDS\PRO\Core\Pool */
	static function getActivePools()
	{
		static $activePools = false;
		if ($activePools !== false) {
			return $activePools;
		} else if (self::$loadedPools !== false) {
			$activePools = self::$loadedPools->filter(function ($item) {
				return $item->isActive();
			});
			return $activePools;
		} else {
			error_log(__FUNCTION__ . " called too soon. Wait after 'setup_theme' hook or use the action 'lws_woorewards_pools_loaded'.");
			return \LWS\WOOREWARDS\Collections\Pools::instanciate();
		}
	}

	private function install()
	{
		spl_autoload_register(array($this, 'autoload'));
		add_action('plugins_loaded', function () {
			require_once LWS_WOOREWARDS_PRO_INCLUDES . '/registration.php';
		});
		\add_filter('lws_woorewards_ignored_order_status_for_count', function($status) {
			return (array)\get_option('lws_woorewards_refund_on_status', array());
		}, 10, 1);

		// override the default pool
		require_once LWS_WOOREWARDS_PRO_INCLUDES . '/core/pool.php';
		\LWS\WOOREWARDS\Collections\Pools::register('\LWS\WOOREWARDS\PRO\Core\Pool');
		// allow options
		\LWS\WOOREWARDS\Abstracts\Unlockable::$maxRedeemAllowed = true;

		add_filter('lws_adminpanel_wizards', function ($wizards) {
			$wizards[LWS_WOOREWARDS_PAGE] = '\LWS\WOOREWARDS\PRO\Wizard';
			return $wizards;
		}, 20); // after free register

		require_once LWS_WOOREWARDS_PRO_INCLUDES . '/ui/fields/periodictrigger.php';
		\LWS\WOOREWARDS\PRO\Ui\Fields\PeriodicTrigger::install();

		require_once LWS_WOOREWARDS_PRO_INCLUDES . '/ui/admin.php';
		new \LWS\WOOREWARDS\PRO\Ui\Admin();
		require_once LWS_WOOREWARDS_PRO_INCLUDES . '/ui/editlists/badges.php';
		new \LWS\WOOREWARDS\PRO\Ui\Editlists\Badges();

		require_once LWS_WOOREWARDS_PRO_INCLUDES . '/core/rewardclaim.php';
		new \LWS\WOOREWARDS\PRO\Core\RewardClaim();
		require_once LWS_WOOREWARDS_PRO_INCLUDES . '/core/ajax.php';
		new \LWS\WOOREWARDS\PRO\Core\Ajax();
		require_once LWS_WOOREWARDS_PRO_INCLUDES . '/core/rest.php';
		\LWS\WOOREWARDS\PRO\Core\Rest::registerRoutes();

		require_once LWS_WOOREWARDS_PRO_INCLUDES . '/core/usertitle.php';
		new \LWS\WOOREWARDS\PRO\Core\UserTitle();
		require_once LWS_WOOREWARDS_PRO_INCLUDES . '/wc/cart.php';
		new \LWS\WOOREWARDS\PRO\WC\Cart();
		require_once LWS_WOOREWARDS_PRO_INCLUDES . '/wc/order.php';
		\LWS\WOOREWARDS\PRO\WC\Order::install();

		require_once LWS_WOOREWARDS_PRO_INCLUDES . '/conveniences.php';
		\LWS\WOOREWARDS\PRO\Conveniences::install();
		require_once LWS_WOOREWARDS_PRO_INCLUDES . '/doclinks.php';

		require_once LWS_WOOREWARDS_PRO_INCLUDES . '/unlockables/freeproduct.php';
		\LWS\WOOREWARDS\PRO\Unlockables\FreeProduct::registerFeatures();

		/** Widgets */
		require_once LWS_WOOREWARDS_PRO_INCLUDES . '/ui/widgets/sponsorwidget.php';
		\LWS\WOOREWARDS\PRO\Ui\Widgets\SponsorWidget::install();
		require_once LWS_WOOREWARDS_PRO_INCLUDES . '/ui/widgets/socialshare.php';
		\LWS\WOOREWARDS\PRO\Ui\Widgets\SocialShareWidget::install();

		/** Shortcodes */
		require_once LWS_WOOREWARDS_PRO_INCLUDES . '/ui/shortcodes/easteregg.php';
		\LWS\WOOREWARDS\PRO\Ui\ShortCodes\EasterEgg::install();
		require_once LWS_WOOREWARDS_PRO_INCLUDES . '/ui/shortcodes/earnpoints.php';
		\LWS\WOOREWARDS\PRO\Ui\ShortCodes\EarnPoints::install();
		require_once LWS_WOOREWARDS_PRO_INCLUDES . '/ui/shortcodes/rewards.php';
		\LWS\WOOREWARDS\PRO\Ui\ShortCodes\Rewards::install();
		require_once LWS_WOOREWARDS_PRO_INCLUDES . '/ui/shortcodes/availablerewards.php';
		\LWS\WOOREWARDS\PRO\Ui\ShortCodes\AvailableRewards::install();
		require_once LWS_WOOREWARDS_PRO_INCLUDES . '/ui/shortcodes/nextlevelpoints.php';
		\LWS\WOOREWARDS\PRO\Ui\ShortCodes\NextLevelPoints::install();
		require_once LWS_WOOREWARDS_PRO_INCLUDES . '/ui/shortcodes/username.php';
		\LWS\WOOREWARDS\PRO\Ui\ShortCodes\UserName::install();
		require_once LWS_WOOREWARDS_PRO_INCLUDES . '/ui/shortcodes/progressbar.php';
		\LWS\WOOREWARDS\PRO\Ui\ShortCodes\ProgressBar::install();
		require_once LWS_WOOREWARDS_PRO_INCLUDES . '/ui/shortcodes/userlevel.php';
		\LWS\WOOREWARDS\PRO\Ui\ShortCodes\UserLevel::install();
		require_once LWS_WOOREWARDS_PRO_INCLUDES . '/ui/shortcodes/leaderboard.php';
		\LWS\WOOREWARDS\PRO\Ui\ShortCodes\Leaderboard::install();
		require_once LWS_WOOREWARDS_PRO_INCLUDES . '/ui/shortcodes/pointsexpiration.php';
		\LWS\WOOREWARDS\PRO\Ui\ShortCodes\PointsExpiration::install();
		require_once LWS_WOOREWARDS_PRO_INCLUDES . '/ui/shortcodes/pointstransactions.php';
		\LWS\WOOREWARDS\PRO\Ui\ShortCodes\PointsTransactions::install();
		require_once LWS_WOOREWARDS_PRO_INCLUDES . '/ui/shortcodes/nextlevelpoints.php';
		\LWS\WOOREWARDS\PRO\Ui\ShortCodes\NextLevelPoints::install();
		require_once LWS_WOOREWARDS_PRO_INCLUDES . '/ui/shortcodes/badges.php';
		\LWS\WOOREWARDS\PRO\Ui\ShortCodes\Badges::install();
		require_once LWS_WOOREWARDS_PRO_INCLUDES . '/ui/shortcodes/achievements.php';
		\LWS\WOOREWARDS\PRO\Ui\ShortCodes\Achievements::install();
		require_once LWS_WOOREWARDS_PRO_INCLUDES . '/ui/shortcodes/conditionaldisplay.php';
		\LWS\WOOREWARDS\PRO\Ui\ShortCodes\ConditionalDisplay::install();
		require_once LWS_WOOREWARDS_PRO_INCLUDES . '/ui/shortcodes/isincart.php';
		\LWS\WOOREWARDS\PRO\Ui\ShortCodes\IsInCart::install();
		require_once LWS_WOOREWARDS_PRO_INCLUDES . '/ui/shortcodes/rewardbutton.php';
		\LWS\WOOREWARDS\PRO\Ui\ShortCodes\RewardButton::install();
		require_once LWS_WOOREWARDS_PRO_INCLUDES . '/ui/shortcodes/productpointspreview.php';
		\LWS\WOOREWARDS\PRO\Ui\ShortCodes\ProductPointsPreview::install();
		require_once LWS_WOOREWARDS_PRO_INCLUDES . '/ui/shortcodes/orderpointspreview.php';
		\LWS\WOOREWARDS\PRO\Ui\ShortCodes\OrderPointsPreview::install();

		/** Endpoints */
		require_once LWS_WOOREWARDS_PRO_INCLUDES . '/ui/endpoints/loyalty.php';
		\LWS\WOOREWARDS\PRO\Ui\Endpoints\Loyalty::install();
		require_once LWS_WOOREWARDS_PRO_INCLUDES . '/ui/endpoints/achievements.php';
		\LWS\WOOREWARDS\PRO\Ui\Endpoints\Achievements::install();
		require_once LWS_WOOREWARDS_PRO_INCLUDES . '/ui/endpoints/badges.php';
		\LWS\WOOREWARDS\PRO\Ui\Endpoints\Badges::install();

		/** Legacy */
		if (\LWS\WOOREWARDS\Conveniences::instance()->isLegacyShown('4.7.0')) {
			\add_action('setup_theme', array($this, 'addEndpoints'));
			require_once LWS_WOOREWARDS_PRO_INCLUDES . '/ui/legacy/rewardswidget.php';
			\LWS\WOOREWARDS\PRO\Ui\Legacy\RewardsWidget::install();
			require_once LWS_WOOREWARDS_PRO_INCLUDES . '/ui/legacy/couponswidget.php';
			\LWS\WOOREWARDS\PRO\Ui\Legacy\CouponsWidget::install();
			require_once LWS_WOOREWARDS_PRO_INCLUDES . '/ui/legacy/eventswidget.php';
			\LWS\WOOREWARDS\PRO\Ui\Legacy\EventsWidget::install();
			require_once LWS_WOOREWARDS_PRO_INCLUDES . '/ui/legacy/cartcouponsview.php';
			\LWS\WOOREWARDS\PRO\Ui\Legacy\CartCouponsView::register();
			require_once LWS_WOOREWARDS_PRO_INCLUDES . '/ui/legacy/badgeswidget.php';
			\LWS\WOOREWARDS\PRO\Ui\Legacy\BadgesWidget::install();
			require_once LWS_WOOREWARDS_PRO_INCLUDES . '/ui/legacy/achievementswidget.php';
			\LWS\WOOREWARDS\PRO\Ui\Legacy\AchievementsWidget::install();
		}
		if (\LWS\WOOREWARDS\Conveniences::instance()->isLegacyShown('4.8.0')) {
			require_once LWS_WOOREWARDS_PRO_INCLUDES . '/ui/legacy/cartpointspreview.php';
			\LWS\WOOREWARDS\PRO\Ui\Legacy\CartPointsPreview::register();
		}
		if (\LWS\WOOREWARDS\Conveniences::instance()->isLegacyShown('4.9.0')) {
			require_once LWS_WOOREWARDS_PRO_INCLUDES . '/ui/legacy/choosefreeproduct.php';
			\LWS\WOOREWARDS\PRO\Ui\Legacy\ChooseFreeProduct::register();
			require_once LWS_WOOREWARDS_PRO_INCLUDES . '/ui/legacy/rewardclaim.php';
			\LWS\WOOREWARDS\PRO\Ui\Legacy\RewardClaim::register();
		}
		if (\LWS\WOOREWARDS\Conveniences::instance()->isLegacyShown('4.9.8')) {
			require_once LWS_WOOREWARDS_PRO_INCLUDES . '/ui/legacy/referralwidget.php';
			\LWS\WOOREWARDS\PRO\Ui\Legacy\ReferralWidget::install();
			require_once LWS_WOOREWARDS_PRO_INCLUDES . '/ui/legacy/productpointspreview.php';
			\LWS\WOOREWARDS\PRO\Ui\Legacy\ProductPointsPreview::register();
		}

		/** WooCommerce */
		require_once LWS_WOOREWARDS_PRO_INCLUDES . '/ui/woocommerce/birthdayfield.php';
		\LWS\WOOREWARDS\PRO\Ui\BirthdayField::register();
		require_once LWS_WOOREWARDS_PRO_INCLUDES . '/ui/woocommerce/leaderboardauth.php';
		\LWS\WOOREWARDS\PRO\Ui\LeaderboardAuth::register();
		require_once LWS_WOOREWARDS_PRO_INCLUDES . '/ui/woocommerce/orderpointinformation.php';
		\LWS\WOOREWARDS\PRO\Ui\OrderPointInformation::register();
		require_once LWS_WOOREWARDS_PRO_INCLUDES . '/ui/woocommerce/productcontent.php';
		\LWS\WOOREWARDS\PRO\Ui\Woocommerce\ProductContent::install();

		/** Popups */
		require_once LWS_WOOREWARDS_PRO_INCLUDES . '/ui/popups/freeproductpopup.php';
		\LWS\WOOREWARDS\PRO\Ui\Popups\FreeProductPopup::register();
		require_once LWS_WOOREWARDS_PRO_INCLUDES . '/ui/popups/rewardpopup.php';
		\LWS\WOOREWARDS\PRO\Ui\Popups\RewardPopup::register();


		require_once LWS_WOOREWARDS_PRO_INCLUDES . '/pointdiscount.php';
		\LWS\WOOREWARDS\PRO\PointDiscount::register();

		if (!\get_option('lws_woorewards_facebook_settings_hidden')) {
			\LWS\WOOREWARDS\PRO\Core\WebHooks::install();
			require_once LWS_WOOREWARDS_PRO_INCLUDES . '/ui/socialsstats.php';
			\LWS\WOOREWARDS\PRO\Ui\SocialsStats::install();
			require_once LWS_WOOREWARDS_PRO_INCLUDES . '/ui/widgets/socialnetworkconnect.php';
			\LWS\WOOREWARDS\PRO\Ui\Widgets\SocialNetworkConnect::install();
		}

		if (!remove_action('setup_theme', array('LWS_WooRewards', 'installPool')))
			error_log('Cannot remove LWS_WooRewards::installPool');
		\add_action('setup_theme', array(get_class(), 'installPools'));

		\add_filter('lws_woorewards_displaypoints_detail_url', array($this, 'displayPointsDetailUrl'), 10, 4);

		$this->setupEmails();

		\add_action('shutdown', array($this, 'sendDelayedMail'));
		\add_action('init', array($this, 'registerPostTypes'));
		\add_action('set_user_role', array($this, 'roleChanged'), 9999, 3);
		\add_filter('lws_woorewards_point_symbol_translation', array($this, 'poolSymbol'), 10, 3);
		\add_filter('lws_woorewards_point_with_symbol_format', array($this, 'formatPointWithSymbol'), 10, 4);
		\add_filter('lws_woorewards_point_format', array($this, 'formatPoint'), 10, 3);

		\do_action('lws_woorewards_pro_init');
	}

	function addEndpoints()
	{
		require_once LWS_WOOREWARDS_PRO_INCLUDES . '/ui/legacy/endpoint.php';
		require_once LWS_WOOREWARDS_PRO_INCLUDES . '/ui/legacy/loyaltyendpoint.php';
		new \LWS\WOOREWARDS\PRO\Ui\Legacy\LoyaltyEndpoint();
		require_once LWS_WOOREWARDS_PRO_INCLUDES . '/ui/legacy/badgesendpoint.php';
		new \LWS\WOOREWARDS\PRO\Ui\Legacy\BadgesEndpoint();
		require_once LWS_WOOREWARDS_PRO_INCLUDES . '/ui/legacy/achievementsendpoint.php';
		new \LWS\WOOREWARDS\PRO\Ui\Legacy\AchievementsEndpoint();
	}

	function enqueueSymbolStyle()
	{
		\wp_enqueue_style('lws-wr-point-symbol');
	}

	function formatPointWithSymbol($text, $points, $sym, $poolName)
	{
		$pool = false;
		if ($poolName) {
			if (\is_a($poolName, '\LWS\WOOREWARDS\PRO\Core\Pool')) {
				$pool = $poolName;
			} else {
				$pool = \LWS\WOOREWARDS\PRO\Core\Pool::getOrLoad($poolName, false);
			}
		}
		if ($pool) {
			$text = $pool->formatPoints($points, true);
		}
		return $text;
	}

	static public function formatPointWithUnit($points, $poolName)
	{
		$text = $points;
		$pool = false;
		if ($poolName) {
			if (\is_a($poolName, '\LWS\WOOREWARDS\PRO\Core\Pool')) {
				$pool = $poolName;
			} else {
				$pool = \LWS\WOOREWARDS\PRO\Core\Pool::getOrLoad($poolName, false);
			}
		}
		if ($pool) {
			$text = $pool->formatPoints($points, true, true);
		}
		return \apply_filters('lws_woorewards_point_with_unit_format', $text, $points, $poolName, $pool);
	}

	static public function formatPoint($text, $points, $poolName)
	{
		$pool = false;
		if ($poolName) {
			if (\is_a($poolName, '\LWS\WOOREWARDS\PRO\Core\Pool')) {
				$pool = $poolName;
			} else {
				$pool = \LWS\WOOREWARDS\PRO\Core\Pool::getOrLoad($poolName, false);
			}
		}
		if ($pool) {
			$text = $pool->formatPoints($points, false);
		}
		return $text;
	}

	function poolSymbol($sym, $count, $poolName)
	{
		if (!$sym) {
			$pool = false;
			if ($poolName) {
				if (\is_a($poolName, '\LWS\WOOREWARDS\PRO\Core\Pool')) {
					$pool = $poolName;
				} else {
					$pool = \LWS\WOOREWARDS\PRO\Core\Pool::getOrLoad($poolName, false);
				}
			}
			if ($pool) {
				$value = $pool->getPointSymbol($count);
				if ($value)
					$sym = $value;
			}
		}
		return $sym;
	}

	/** @return bool if locked and lock for the nexts. */
	static function isRoleChangeLocked($releaseLock = false)
	{
		static $locked = false;
		$old = $locked;
		if ($releaseLock)
			$old = $locked = false;
		else
			$locked = true;
		return $old;
	}

	/** If role changed from elsewhere, restore the WooRewards roles. */
	function roleChanged($userId, $role, $old_roles)
	{
		if (!self::isRoleChangeLocked()) {
			$user = false;
			$prefix = \LWS\WOOREWARDS\PRO\Unlockables\Role::PREFIX;
			if (0 !== strpos($role, $prefix)) {
				foreach ($old_roles as $old_role) {
					if (0 === strpos($old_role, $prefix)) {
						if (!$user)
							$user = \get_user_by('ID', $userId);
						if ($user)
							$user->add_role($old_role);
					}
				}
			}

			self::isRoleChangeLocked(true);
		}
	}

	function registerPostTypes()
	{
		\register_post_type('lws_custom_reward', array(
			'show_in_rest' => true,
			'labels' => array(
				'name' => __("Custom rewards", 'woorewards-pro'),
				'singular_name' => __("Custom reward", 'woorewards-pro')
			),
			'supports' => array('title', 'custom-fields'),
		));
		\LWS\WOOREWARDS\PRO\Core\Badge::registerPostType();
		\register_post_type('lws-wre-achievement', array(
			'show_in_rest' => true,
			'hierarchical' => true,
			'labels' => array(
				'name' => __("Achievements", 'woorewards-pro'),
				'singular_name' => __("Achievement", 'woorewards-pro')
			),
			'supports' => array('title', 'custom-fields'),
		));
	}

	/** Add (redirection to my-account/woorewards) button in displayPoints widget. */
	function displayPointsDetailUrl($url, $poolname, $pointstotal, $lws_stygen)
	{
		if (\LWS\Adminpanel\Tools\Conveniences::isWC())
			return \LWS_WooRewards_Pro::getEndpointUrl('lws_woorewards');
		else
			return $url;
	}

	/** Register Email templates */
	protected function setupEmails()
	{
		require_once LWS_WOOREWARDS_PRO_INCLUDES . '/mails/newreward.php';
		new \LWS\WOOREWARDS\PRO\Mails\NewReward();
		require_once LWS_WOOREWARDS_PRO_INCLUDES . '/mails/achieved.php';
		new \LWS\WOOREWARDS\PRO\Mails\Achieved();
		require_once LWS_WOOREWARDS_PRO_INCLUDES . '/mails/availableunlockables.php';
		new \LWS\WOOREWARDS\PRO\Mails\AvailableUnlockables();
		require_once LWS_WOOREWARDS_PRO_INCLUDES . '/mails/sponsored.php';
		new \LWS\WOOREWARDS\PRO\Mails\Sponsored();
		require_once LWS_WOOREWARDS_PRO_INCLUDES . '/mails/couponreminder.php';
		new \LWS\WOOREWARDS\PRO\Mails\CouponReminder();
		require_once LWS_WOOREWARDS_PRO_INCLUDES . '/mails/pointsreminder.php';
		new \LWS\WOOREWARDS\PRO\Mails\PointsReminder();
	}

	/** The n-uplet {$ref, $email, $template} is used for unicity.
	 * whatever the data, a new delayed mail with same n-uplet overrides any previous email. */
	static function delayedMail($ref, $email, $template, $data)
	{
		self::$delayedMailStack[$template][$ref][$email] = $data;
	}

	function sendDelayedMail()
	{
		foreach (self::$delayedMailStack as $template => $refs) {
			foreach ($refs as $ref => $emails) {
				foreach ($emails as $email => $data) {
					\do_action('lws_mail_send', $email, $template, $data);
				}
			}
		}
	}

	/** Gets the URL for an endpoint, which varies depending on permalink settings.
	 * If no WC, return home url.
	 * @return string url
	 * @param $endpoint Endpoint slug.
	 * @param $value Query param value.
	 * @param $permalink Permalink; if false, we use \wc_get_page_permalink('myaccount').
	 * */
	static function getEndpointUrl($endpoint, $value = '', $permalink = false)
	{
		if (function_exists('\wc_get_endpoint_url')) {
			if (false === $permalink)
				$permalink = \wc_get_page_permalink('myaccount');
			$endpoint = \apply_filters('lws_woorewards_endpoint_slug', $endpoint);
			return \wc_get_endpoint_url($endpoint, $value, $permalink);
		}
		return \home_url();
	}

	function getEndpointSlug($slug)
	{
		$slugs = array(
			'lws_woorewards_wc_achievements_endpoint_slug' => 'lws_achievements',
			'lws_woorewards_wc_badges_endpoint_slug'       => 'lws_badges',
			'lws_woorewards_wc_my_account_endpoint_slug'   => 'lws_woorewards',
		);
		foreach ($slugs as $option => $value) {
			if ($slug == $value) {
				$slug = \lws_get_option($option, $value);
				break;
			}
		}
		return $slug;
	}
}

LWS_WooRewards_Pro::init(); {
	if (\file_exists($asset = (dirname(__FILE__) . '/../lws-manager/lws-manager.php')))
		include_once $asset;
}