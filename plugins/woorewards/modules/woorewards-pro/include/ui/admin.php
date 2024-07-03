<?php

namespace LWS\WOOREWARDS\PRO\Ui;

// don't call the file directly
if (!defined('ABSPATH')) exit();

require_once LWS_WOOREWARDS_PRO_INCLUDES . '/ui/editlists/userspointspoolfilter.php';

/** Create the backend menu and settings pages. */
class Admin
{
	const POOL_OPTION_PREFIX = 'lws-wr-pool-option-';

	private $wcStyles = array();
	private $flush_rewrite_rules = null;

	public function __construct()
	{
		\LWS\WOOREWARDS\PRO\Ui\Editlists\UsersPointsPoolFilter::install();

		\add_action('admin_enqueue_scripts', array($this, 'scripts'));
		\add_filter('lws_woorewards_ui_settings_page_get', array($this, 'getSettingsPage'));
		\add_filter('lws_adminpanel_pages_' . LWS_WOOREWARDS_PAGE, array($this, 'managePages'));
		\add_filter('lws_woorewards_admin_pool_general_settings', array($this, 'poolGeneralSettings'), 15, 2); // priority to set after
		\add_filter('lws_woorewards_admin_userspoints_filters', array($this, 'userspointsFilters'));

		// grab woocommerce styles if any to appends them on stygen
		\add_filter('woocommerce_enqueue_styles', array($this, 'grabWCStyles'), 0);

		// Add specific support text for configuration help
		\add_filter('lws_adm_support_contents', array($this, 'addSupportText'), 10, 2);

		foreach (array('lws_woorewards_wc_achievements_endpoint_slug', 'lws_woorewards_wc_badges_endpoint_slug', 'lws_woorewards_wc_my_account_endpoint_slug') as $slugOption)
			\add_filter('pre_update_option_' . $slugOption, array($this, 'warnAboutEndpoint404'), 10, 3);

		\add_action('lws_woorewards_after_delete_all', array($this, 'deleteAllData'));
	}

	function warnAboutEndpoint404($value = true, $oldValue = false, $option = false)
	{
		if (\function_exists('\add_rewrite_endpoint')) {
			$key = $value;
			if (!$key) {
				$keys = array(
					'lws_woorewards_wc_my_account_endpoint_slug'   => 'lws_woorewards',
					'lws_woorewards_wc_badges_endpoint_slug'       => 'lws_badges',
					'lws_woorewards_wc_achievements_endpoint_slug' => 'lws_achievements',
				);
				$key = $keys[$option];
			}
			// force flush
			\add_rewrite_endpoint($key, EP_ROOT | EP_PAGES);
			if (null === $this->flush_rewrite_rules) {
				$this->flush_rewrite_rules = true;
				\add_action('shutdown', function () use ($option) {
					\flush_rewrite_rules(true);
				});
			}
		}

		if ($value != $oldValue) {
			\lws_admin_add_notice(
				'warnAboutEndpoint404',
				sprintf(
					'<p>%s</p><p>%s</p>',
					__("You just changed a slug in MyAccount tabs.", 'woorewards-pro'),
					__("Sometimes, when trying to go to the Loyalty and Rewards Tab, you will see a “404 – Page does not exist” error. This is a known WordPress permalink issue. To solve it, go to your WordPress administration and go to <strong>Settings → Permalinks</strong>. Once you’re there, scroll to the bottom of the page and click the <strong>Save Changes</strong> button. That will solve the problem.", 'woorewards-pro')
				),
				array('level' => 'info', 'dismissible' => true, 'forgettable' => true)
			);
		}
		return $value;
	}

	public function scripts($hook)
	{
		\wp_register_style('lws-wre-pro-poolssettings', LWS_WOOREWARDS_PRO_CSS . '/poolssettings.min.css', array(), LWS_WOOREWARDS_PRO_VERSION);
		$deps = array('jquery', 'lws-base64', 'lws-tools');
		\wp_register_script('lws-wre-pro-poolssettings', LWS_WOOREWARDS_PRO_JS . '/poolssettings.js', $deps, LWS_WOOREWARDS_PRO_VERSION, true);

		if (false !== strpos($hook, LWS_WOOREWARDS_PAGE)) {
			\do_action('lws_adminpanel_enqueue_lac_scripts', array('select'));

			\wp_enqueue_script('lws-radio');

			$tab = isset($_GET['tab']) ? $_GET['tab'] : '';
			if (strpos($hook, 'loyalty') !== false) {
				foreach ($deps as $dep)
					\wp_enqueue_script($dep);
				\wp_enqueue_script('lws-wre-pro-poolssettings');
				\wp_enqueue_style('lws-wre-pro-poolssettings');
				\wp_enqueue_style('lws-wre-pro-style', LWS_WOOREWARDS_PRO_CSS . '/style.css', array(), LWS_WOOREWARDS_PRO_VERSION);
			} else if (false !== strpos($hook, 'settings') && strpos($tab, 'woocommerce') !== false) {
				if (\class_exists('\WC_Frontend_Scripts')) {
					\WC_Frontend_Scripts::get_styles();
					if ($this->wcStyles) {
						foreach ($this->wcStyles as $style => $detail) {
							\wp_enqueue_style($style, $detail['src'], $detail['deps'], $detail['version'], $detail['media']);
						}
					}
				}
			} else {
				\wp_enqueue_style('lws-wre-userspointsfilters', LWS_WOOREWARDS_PRO_CSS . '/userspointsfilters.css', array(), LWS_WOOREWARDS_PRO_VERSION);
			}

			\wp_enqueue_style('lws-wre-pool-content-edit', LWS_WOOREWARDS_PRO_CSS . '/poolcontentedit.css', array(), LWS_WOOREWARDS_PRO_VERSION);
		}
	}

	function grabWCStyles($scripts)
	{
		$this->wcStyles = $scripts;
		return $scripts;
	}

	/** Reorganise pages from the free version to the pro version */
	function managePages($pages)
	{
		foreach ($pages as &$page) {
			if (isset($page['prebuild']) && $page['prebuild']) return $pages;
		}

		$proPages = array();
		if (isset($pages['wr_resume'])) {
			$proPages['wr_resume'] = $pages['wr_resume'];
			$proPages['wr_resume']['title'] = __('WooRewards', 'woorewards-pro');
		}

		$proPages['wr_customers'] = $this->getCustomerPage($pages);
		$proPages['wr_settings'] = $this->getSettingsPage();
		$proPages['wr_wizard'] = $this->getWizardPage($pages);
		$proPages['wr_appearance'] = $this->getAppearancePage($pages);
		$proPages['wr_system'] = $this->getSystemPage($pages);
		$proPages['wr_teaser'] = array(
			'id'     => 'wr_teaser',
			'title'  => __('Add-ons', 'woorewards-pro'),
			'teaser' => LWS_WOOREWARDS_UUID,
			'rights' => 'manage_options',
		);
		return $proPages;
	}

	function getCustomerPage(&$freePages)
	{
		$customerPage = $freePages['wr_customers'];

		$customerPage['description'] = __("Use this page to see your customers activity, manage their points and their rewards", 'woorewards-pro');
		return $customerPage;
	}

	function getSettingsPage($tab = false)
	{
		require_once LWS_WOOREWARDS_PRO_INCLUDES . '/ui/editlists/pools.php';
		$tabId = 'wr_loyalty';
		$title = __("Points and Rewards", 'woorewards-pro');

		$links = array('' => array('poolfilter' => ''));
		$labels = array('' => _x("All", "Points and rewards system filter", 'woorewards-pro'));
		foreach (\LWS\WOOREWARDS\PRO\Ui\Editlists\Pools::statusList() as $k => $status) {
			$links[$k] = array('poolfilter' => $k);
			$labels[$k] = $status;
		}
		$filter = new \LWS\Adminpanel\EditList\FilterSimpleLinks($links, array(), false, $labels);

		$settingsPage = array(
			'title'       => __("Settings", 'woorewards-pro'),
			'color'       => '#526981',
			'image'       => LWS_WOOREWARDS_IMG . '/r-loyalty-systems.png',
			'description' => __("This is the place where you set up your loyalty program and activate the different available features", 'woorewards-pro'),
			'rights'      => 'manage_rewards',
			'id'          => LWS_WOOREWARDS_PAGE . '.loyalty',
			'tabs'        => array(
				$tabId  => array(
					'title'  => $title,
					'id'     => $tabId,
					'icon'	 => 'lws-icon-gold-coin',
					'vertnav'=> true,
					'tabs'   => array(
						'systems' => array(
							'title'  => $title,
							'id'     => 'systems',
							'icon'	 => 'lws-icon-present',
							'groups' => \array_merge(array(
								'systems' => array(
									'id'       => 'systems',
									'title'    => __("Points and Rewards Systems", 'woorewards-pro'),
									'icon'	   => 'lws-icon-present',
									'color'    => '#016087',
									'text'     => array(
										'join' => '<br/>',
										__("Points and Rewards Systems are WooReward's core. This is how your customers <b>earn points and get rewards</b>.", 'woorewards-pro'),
										__("When adding a new system, you'll have two options :", 'woorewards-pro'),
										array(
											'tag' => 'ul',
											__("<b>Standard System</b> : Customers earn points in various ways and can spend their points to unlock rewards.", 'woorewards-pro'),
											__("<b>Leveling System</b> : Customers earn points and unlock levels and rewards as they progress. <b>In a leveling system, customers never spend their points</b>.", 'woorewards-pro'),
										),
									),
										'extra'    => array('doclink' => \LWS\WOOREWARDS\PRO\DocLinks::get('pools')),
									'editlist' => \lws_editlist(
										\LWS\WOOREWARDS\PRO\Ui\Editlists\Pools::SLUG,
										\LWS\WOOREWARDS\PRO\Ui\Editlists\Pools::ROW_ID,
										new \LWS\WOOREWARDS\PRO\Ui\Editlists\Pools(),
										\LWS\Adminpanel\EditList::DDA,
										$filter
									)
								)),
								$this->getPointsSettingsGroup()
							)
						)
					)
				)
			)
		);

		// build only the required pool page
		$pool = $this->guessCurrentPool($tabId);
		if ($pool) {
			$subtab = $pool->getTabId();
			$settingsPage['tabs'][$tabId]['tabs'][$subtab] = \apply_filters('lws_woorewards_ui_loyalty_edit_pool_tab', array(
				'title'  => $pool->getOption('display_title'),
				'id'     => $subtab,
				'hidden' => true,
				'groups' => $this->getLoyaltyGroups($pool),
				'delayedFunction' => function () use ($pool) {
					echo "<div style='width:50%;'>";
					\do_action('wpml_show_package_language_ui', $pool->getPackageWPML());
					echo "</div>";
				}
			), $pool);
		}

		require_once LWS_WOOREWARDS_PRO_INCLUDES . '/ui/adminscreens/achievements.php';
		require_once LWS_WOOREWARDS_PRO_INCLUDES . '/ui/adminscreens/generalsettings.php';
		$settingsPage['tabs']['achievements'] = \LWS\WOOREWARDS\PRO\Ui\AdminScreens\Achievements::getTab();
		$settingsPage['tabs']['settings'] = \LWS\WOOREWARDS\PRO\Ui\AdminScreens\GeneralSettings::getTab();

		return $settingsPage;
	}

	function getPointsSettingsGroup()
	{
		return array(
			'pointssettings' => array(
				'id'		=> "pointssettings",
				'title'		=> __("Points Additional Settings", 'woorewards-pro'),
				'icon'		=> 'lws-icon-gold-coin',
				'color'		=> '#34509a',
				'class'		=> 'half',
				'text'     => array(
					'join' => '<br/>',
					__("Set here additional options for points management.", 'woorewards-pro'),
					__("All options set below will apply for all your points and rewards systems", 'woorewards-pro'),
				),
				'fields'	=> array(
					'inc_taxes' => array(
						'id'    => 'lws_woorewards_order_amount_includes_taxes',
						'title' => __("Includes taxes", 'woorewards-pro'),
						'type'  => 'box',
						'extra' => array(
							'layout' => 'toggle',
							'help' => __("If checked, taxes will be included in the points earned when spending money", 'woorewards-pro'),
						)
					),
					'order_state' => array(
						'id'    => 'lws_woorewards_points_distribution_status',
						'title' => __("Order status to earn points", 'woorewards-pro'),
						'type'  => 'lacchecklist',
						'extra' => array(
							'ajax' => 'lws_adminpanel_get_order_status',
							'help' => __("Default status to get points is the processing order status.<br/>If you want to use other status instead, select them here", 'woorewards-pro'),
						)
					),
					'refund' => array(
						'id'    => 'lws_woorewards_refund_on_status',
						'title' => __("Order status to remove points", 'woorewards-pro'),
						'type'  => 'lacchecklist',
						'extra' => array(
							'ajax' => 'lws_adminpanel_get_order_status',
							'help' => __("When an order is cancelled or refunded, points can be removed from the customer. Select which order status will lead to points being removed", 'woorewards-pro'),
						)
					),
					'free_prods ' => array(
						'id'    => 'lws_woorewards_free_product_gives_points',
						'title' => __("Free products give points", 'woorewards-pro'),
						'type'  => 'box',
						'extra' => array(
							'layout' => 'toggle',
							'help'  => __("For certain earning points methods (Buy specific products, etc.), products offered through the “free products” reward are ignored in the points calculation. Toogle on this option to discard this behavior.", 'woorewards-pro'),
						)
					),
					'enable_multicurrency' => array(
						'id' => 'lws_woorewards_enable_multicurrency',
						'title' => __("Enable multi currency support", 'woorewards-pro'),
						'type' => 'box',
						'extra' => array(
							'layout' => 'toggle',
							'help'   => __("If enabled, it adds a new option in some points earning methods to limit earnings to a given currency.", 'woorewards-pro'),
						)
					),
					'show_cooldown ' => array(
						'id'    => 'lws_woorewards_show_event_cooldown',
						'title' => __("Show Points Cooldown", 'woorewards-pro'),
						'type'  => 'box',
						'extra' => array(
							'layout' => 'toggle',
							'help'  => __("If you set a cooldown on the methods to earn points, select if you want to show the cooldown to customers or not", 'woorewards-pro'),
						)
					),
				)
			),
			'pocsettings' => array(
				'id'		=> "pocsettings",
				'title'		=> __("Point Discounts Settings", 'woorewards-pro'),
				'icon'		=> 'lws-icon-coupon',
				'color'		=> '#4430aa',
				'class'		=> 'half',
				'text'	 => __("Use these options to configure how WooRewards behaves when customers use their points for immediate cart discount", 'woorewards-pro'),
				'fields' => array(
					'poc_order_status' => array(
						'id'    => 'lws_woorewards_pointdiscount_pay_order_status',
						'title' => __("Order status to consume points", 'woorewards-pro'),
						'type'  => 'lacchecklist',
						'extra' => array(
							'ajax'    => 'lws_adminpanel_get_order_status',
							'default' => array('processing', 'completed'),
						)
					),
					'poc_failure'     => array(
						'id'    => 'lws_woorewards_pointdiscount_pay_order_failure',
						'title' => __("Order status on points consumption failure", 'woorewards-pro'),
						'type'  => 'lacselect',
						'extra' => array(
							'mode'    => 'select',
							'source'  => array(
								array('value' => '_', 'label' => __("[do nothing]", 'woorewards-pro'))
							),
							'ajax'    => 'lws_adminpanel_get_order_status',
							'default' => 'failed',
							'help'    => __("Change order status if points cannot be paid after all.", 'woorewards-pro'),
						)
					),
					'convert_virtual_coupon_currency' => array(
						'id' => 'lws_woorewards_convert_virtual_coupon_currency',
						'title' => __("Force 'Points on Cart' currency switch", 'woorewards-pro'),
						'type' => 'box',
						'extra' => array(
							'layout' => 'toggle',
							'help'  => __("Some multi-currency plugins don't manage virtual coupons used by WooRewards even if it's a standard WooCommerce feature.", 'woorewards-pro')
								. '<br/>' . __("We can try to take over that missing feature.", 'woorewards-pro'),
						)
					),
				)
			),
		);
	}

	function getWizardPage(&$freePages)
	{
		$customerPage = $freePages['wr_wizard'];
		$customerPage['description'] = __("Use the wizard to set up your loyalty program in no time. Answer some questions, set some values and let the magic operate", 'woorewards-pro');

		return $customerPage;
	}

	protected function isIn($suffix, $includeResume=true)
	{
		$page = \LWS\Adminpanel\Tools\Conveniences::getCurrentAdminPage();
		if ((LWS_WOOREWARDS_PAGE . $suffix) == $page)
			return true;
		if ($includeResume && LWS_WOOREWARDS_PAGE == $page)
			return true;
		return false;
	}

	function getAppearancePage(&$freePages)
	{
		$freePages['wr_appearance']['description'] = __("Set the appearance of everything your customers will see on your website regarding your loyalty program ", 'woorewards-pro');

		if (!$this->isIn('.appearance'))
			return $freePages['wr_appearance'];

		require_once LWS_WOOREWARDS_PRO_INCLUDES . '/ui/adminscreens/shortcodes.php';
		require_once LWS_WOOREWARDS_PRO_INCLUDES . '/ui/adminscreens/woocommerce.php';
		require_once LWS_WOOREWARDS_PRO_INCLUDES . '/ui/adminscreens/popups.php';

		$appearancePage = array_merge(
			$freePages['wr_appearance'],
			array(
				'tabs'     => array(
					'woocommerce' => \LWS\WOOREWARDS\PRO\Ui\AdminScreens\WooCommerce::getTab($freePages['wr_appearance']),
					'shortcodes'  => \LWS\WOOREWARDS\PRO\Ui\AdminScreens\Shortcodes::getTab(),
					'emails'      => $this->getEmailsTab($freePages),
					'popups'      => \LWS\WOOREWARDS\PRO\Ui\AdminScreens\Popups::getTab(),
					'styling'     => \LWS\WOOREWARDS\Ui\AdminScreens\Styling::getTab(true),
				)
			)
		);

		require_once LWS_WOOREWARDS_PRO_INCLUDES . '/ui/adminscreens/legacy.php';
		$legacy = \LWS\WOOREWARDS\PRO\Ui\AdminScreens\Legacy::getTab($freePages['wr_appearance']);
		if ($legacy['groups']) {
			$appearancePage['tabs']['legacy'] = $legacy;
		}
		if (\LWS\WOOREWARDS\Conveniences::instance()->isLegacyShown('4.7.0')) {
			\LWS\WOOREWARDS\PRO\Ui\AdminScreens\Legacy::setOptionCheck();
		}
		return $appearancePage;
	}

	function getAPITab()
	{
		$restPrefix = \trailingslashit(\get_rest_url()) . \LWS\WOOREWARDS\PRO\Core\Rest::getNamespace();
		$restPrefix = "<span class='lws-group-descr-copy lws_ui_value_copy'><div class='lws-group-descr-copy-text content' tabindex='0'>{$restPrefix}</div><div class='lws-group-descr-copy-icon lws-icon lws-icon-copy copy'></div></span>";
		$apiTab = array(
			'id'	=> 'api_settings',
			'title'	=>  __("API", 'woorewards-pro'),
			'icon'	=> 'lws-icon-api',
			'groups' => array(
				'api' => array(
					'id'     => 'api',
					'icon'	=> 'lws-icon-api',
					'title'  => __("REST API", 'woorewards-pro'),
					'text'   => sprintf(__("Define MyRewards REST API settings. API endpoint will be %s", 'woorewards-pro'), $restPrefix),
					'extra'    => array('doclink' => \LWS\WOOREWARDS\PRO\DocLinks::get('api')),
					'fields' => array(
						'enabled' => array(
							'id'    => 'lws_woorewards_rest_api_enabled',
							'title' => __("Enable REST API", 'woorewards-pro'),
							'type'  => 'box',
							'extra' => array(
								'layout' => 'toggle',
							)
						),
						'wc_auth' => array(
							'id'    => 'lws_woorewards_rest_api_wc_auth',
							'title' => __("Allows authentification by WooCommerce REST API", 'woorewards-pro'),
							'type'  => 'box',
							'extra' => array(
								'default' => 'on',
								'layout' => 'toggle',
							)
						),
					)
				),
				'users' => array(
					'id'     => 'users',
					'icon'	=> 'lws-icon-users-mm',
					'title'  => __("User Permissions", 'woorewards-pro'),
					'text'   => __("Define the website users that can access the different features of the API", 'woorewards-pro'),
					'extra'    => array('doclink' => \LWS\WOOREWARDS\PRO\DocLinks::get('api')),
					'fields' => array(
						'mode' => array(
							'id'    => 'lws_woorewards_rest_api_permission_mode',
							'title' => __("Permission mode", 'woorewards-pro'),
							'type'  => 'box',
							'extra' => array(
								'id'      => 'lws_woorewards_rest_api_permission_mode',
								'layout'  => 'switch',
								'data'    => array(
									'left'       => __("User selection", 'woorewards-pro'),
									'right'      => __("Capability check", 'woorewards-pro'),
									'colorleft'  => '#22a971',
									'colorright' => '#5279b1',
								),
							),
						),
						'info' => array(
							'id'    => 'lws_woorewards_rest_api_user_info',
							'title' => __("Users allowed to read general information", 'woorewards-pro'),
							'type'  => 'lacchecklist',
							'extra' => array(
								'predefined' => 'user',
								'tooltips'   => __("The checked users can get points and rewards system list.", 'woorewards-pro'),
							),
							'require' => array('selector' => '#lws_woorewards_rest_api_permission_mode', 'value' => ''),
						),
						'read' => array(
							'id'    => 'lws_woorewards_rest_api_user_read',
							'title' => __("Users allowed to read user information", 'woorewards-pro'),
							'type'  => 'lacchecklist',
							'extra' => array(
								'predefined' => 'user',
								'tooltips'   => __("The checked users can get other users point amounts and history.", 'woorewards-pro'),
							),
							'require' => array('selector' => '#lws_woorewards_rest_api_permission_mode', 'value' => ''),
						),
						'write' => array(
							'id'    => 'lws_woorewards_rest_api_user_write',
							'title' => __("Users allowed to change user information", 'woorewards-pro'),
							'type'  => 'lacchecklist',
							'extra' => array(
								'predefined' => 'user',
								'tooltips'   => __("The checked users can add points to other users.", 'woorewards-pro'),
							),
							'require' => array('selector' => '#lws_woorewards_rest_api_permission_mode', 'value' => ''),
						),
						'cap' => array(
							'id'    => 'lws_woorewards_rest_api_info',
							'title' => __("User/role capabilies", 'woorewards-pro'),
							'type'  => 'custom',
							'extra' => array(
								'gizmo'   => true,
								'help'    => __("Add the capabilies below to users or role", 'woorewards-pro'),
								'content' => function() {return \LWS\Adminpanel\Tools\Conveniences::array2html(array('tag' => 'ul',
									array('lws_wr_read_settings',
										__("Read the Loyalty system settings, its point earning methods and rewards.", 'woorewards-pro')
									),
									array('lws_wr_read_points',
										__("Read the connected user points.", 'woorewards-pro')
									),
									array('lws_wr_read_other_points',
										__("The connected user can read the points of any user", 'woorewards-pro')
									),
									array('lws_wr_edit_points',
										__("The connected user can edit its own points and trigger rewards for him", 'woorewards-pro')
									),
									array('lws_wr_edit_other_points',
										__("The connected user can edit the points and trigger rewards of any user", 'woorewards-pro')
									),
								));},
							),
							'require' => array('selector' => '#lws_woorewards_rest_api_permission_mode', 'value' => 'on'),
						),
					)
				),
			)
		);

		return $apiTab;
	}

	function getEmailsTab(&$freePages)
	{
		$emailsTab = $freePages['wr_appearance']['tabs']['sty_mails'];
		$emailsTab['vertnav'] = true;
		return $emailsTab;
	}

	/** @return object pool instance or false. */
	protected function guessCurrentPool($tabId)
	{
		$ref = false;
		$tab = isset($_REQUEST['tab']) ? trim($_REQUEST['tab']) : '';
		$tabPrefix = $tabId . '.wr_upool_';

		if (strpos($tab, $tabPrefix) === 0) {
			$ref = substr($tab, strlen($tabPrefix));
		} else if (defined('DOING_AJAX') && DOING_AJAX && isset($_REQUEST['action']) && $_REQUEST['action'] == 'lws_adminpanel_editlist') {
			$editlist = isset($_REQUEST['id']) ? trim($_REQUEST['id']) : '';
			foreach (array('UnlockableList-', 'EventList-') as $prefix) {
				if (0 === strpos($editlist, $prefix)) {
					$ref = intval(substr($editlist, strlen($prefix)));
					break;
				}
			}
		}

		$guess = false;
		if ($ref) {
			if ($id = max(0, intval($ref)))
				$guess = \LWS\WOOREWARDS\Collections\Pools::instanciate()->load(array('p' => $id, 'deep' => true))->last();

			if (!$guess)
				$guess = \LWS\WOOREWARDS\Collections\Pools::instanciate()->load(array('name' => $ref, 'deep' => true))->last();
		}
		return $guess;
	}

	protected function getLoyaltyGroups($pool)
	{
		if (empty($pool)) {
			return array('error' => array(
				'title' => __("Loading failure", 'woorewards-pro'),
				'text'  => __("Seems the points and rewards system does not exists. Try re-activate this plugin. If that problem persists, contact your administrator.", 'woorewards-pro')
			));
		}

		$settingsText = array(
			'cast' => 'p',
			array(
				__("General Settings are used to start or stop your points and rewards system, rename it or change some other basic options.", 'woorewards-pro'),
			),
			array('tag' => 'strong', __("Don't forget to start your points and rewards system when you've finished your settings.", 'woorewards-pro'))
		);

		$earningText = array(
			'cast' => 'p',
			__("Use this section to define what actions users or customers have to perform in order to earn points in this points and rewards system.", 'woorewards-pro'),
			array('tag' => 'strong', __("You can define as many actions as you want by clicking the 'Add' button.", 'woorewards-pro')),
		);

		$group = array(
			'earning'    => array(
				'id'      => 'wr_loyalty_earning',
				'class'   => 'half',
				'title'   => __("Points", 'woorewards-pro'),
				'image'   => LWS_WOOREWARDS_IMG . '/ls-earning.png',
				'color'   => '#38bebe',
				'text'    => $earningText,
				'extra' => array('doclink' => \LWS\WOOREWARDS\PRO\DocLinks::get('points')),
				'editlist' => \lws_editlist(
					'EventList-' . $pool->getId(),
					\LWS\WOOREWARDS\Ui\Editlists\EventList::ROW_ID,
					new \LWS\WOOREWARDS\Ui\Editlists\EventList($pool),
					\LWS\Adminpanel\EditList::MDA
				)->setPageDisplay(false)->setCssClass('eventlist')->setRepeatHead(false),
			),
			'spending'   => $this->getPoolRewardsGroup($pool),
			'general'    => array(
				'id'     => 'wr_loyalty_general',
				'image'  => LWS_WOOREWARDS_IMG . '/ls-settings.png',
				'color'  => '#7958a5',
				'class'  => 'half',
				'title'  => __("General Settings", 'woorewards-pro'),
				'text'   => $settingsText,
				'extra'  => array('doclink' => \LWS\WOOREWARDS\PRO\DocLinks::get('pools')),
				'fields' => \apply_filters('lws_woorewards_admin_pool_general_settings', array(), $pool)
			),
			'expiration' => $this->getPoolPointExpirationGroup($pool),
			'pts_disp'   => $this->getPoolPointDisplayGroup($pool),
			'advanced'	 => $this->getPoolAdvancedGroup($pool),
		);

		return $group;
	}

	protected function getPoolAdvancedGroup($pool)
	{
		$asettingsText = array(
			'join' => '<br/><br/>',
			__("Advanced Settings are used to set special options to change the behavior of the points and rewards system. These options can have a serious impact on the points and rewards system.", 'woorewards-pro'),
			array(
				array('tag' => 'strong', __("Warning :", 'woorewards-pro')),
				__("Be sure of what you're doing before making modifications in this section.", 'woorewards-pro'),
			)
		);

		$group = array(
			'id'		=> 'wr_loyalty_asettings',
			'image'		=> LWS_WOOREWARDS_IMG . '/ls-asettings.png',
			'color'		=> '#6e9684',
			'class'		=> 'half',
			'title'		=> __("Advanced Settings", 'woorewards-pro'),
			'text'		=> $asettingsText,
			'extra'		=> array('doclink' => \LWS\WOOREWARDS\PRO\DocLinks::get('pools-as')),
			'fields'	=> array(
				'stackid' => array(
					'id'    => self::POOL_OPTION_PREFIX . 'stack',
					'title' => __("Point Reserve", 'woorewards-pro'),
					'type'  => 'lacselect',
					'extra' => array(
						'id'       => 'lws_wr_pool_pointstack',
						'class'    => 'lws-wr-pool-pointstack',
						'value'    => $pool->getRawStackId(),
						'ajax'     => 'lws_woorewards_pointstack_list',
						'source'   => array(
							array(
								'value' => '',
								'label' => __("Create a new reserve", 'woorewards-pro'),
								'html'  => sprintf('<i>&lt;%s&gt;</i>', __("Create a new reserve", 'woorewards-pro'))
							),
						),
						'mode' => 'select',
						'tooltips' => sprintf(
							'<span class="pointstack_help single">%s</span><span class="pointstack_help shared hidden"><span class="text">%s</span> <span class="list">&nbsp;</span></span>',
							__("This System uses its own points reserve.", 'woorewards-pro'),
							__("This System shares its Points between :", 'woorewards-pro')
						)
					)
				),
				'restricted' => array(
					'id'    => self::POOL_OPTION_PREFIX . 'roles',
					'title' => __("Allowed Roles", 'woorewards-pro'),
					'type'  => 'lacchecklist',
					'extra' => array(
						'value'    => $pool->getOption('roles'),
						'ajax'     => 'lws_adminpanel_get_roles',
						'tooltips' => __("If set, only users with at least one of the selected roles can enjoy that points and rewards system. By default, a loyalty system is available for everybody.", 'woorewards-pro')
					)
				),
				'denied' => array(
					'id'    => self::POOL_OPTION_PREFIX . 'denied_roles',
					'title' => __("Denied Roles", 'woorewards-pro'),
					'type'  => 'lacchecklist',
					'extra' => array(
						'value'    => $pool->getOption('denied_roles'),
						'ajax'     => 'lws_adminpanel_get_roles',
						'tooltips' => __("If set, users with at least one of the selected roles won't have access to the Points and Rewards System.", 'woorewards-pro')
					)
				),
				'order' => array(
					'id'    => self::POOL_OPTION_PREFIX . 'loading_order',
					'title' => __("Loading Order", 'woorewards-pro'),
					'type'  => 'text',
					'extra' => array(
						'value' => $pool->getOption('loading_order'),
						'help'  => __('Force a Points and Rewards System to be loaded/executed before another one. Greater the number is, sooner the Loyalty System is loaded.', 'woorewards-pro'),
					)
				),
			)
		);

		if (!\get_option('lws_woorewards_show_loading_order_and_priority')) {
			unset($group['fields']['order']);
		}

		if ($pool->getOption('type') == \LWS\WOOREWARDS\Core\Pool::T_LEVELLING) {
			$group['fields']['adapt'] = array(
				'id'    => self::POOL_OPTION_PREFIX . 'adapt_level',
				'title' => __("Adaptative Levels", 'woorewards-pro'),
				'type'  => 'box',
				'extra' => array(
					'id'       => self::POOL_OPTION_PREFIX . 'adapt_level',
					'checked'  => $pool->getOption('adapt_level'),
					'layout' => 'toggle',
					'tooltips' => __("If checked, customers can lose their levels and rewards when points go down. Rewards from previous levels are restored if possible.", 'woorewards-pro')
				),
				'require' => array('selector' => '#' . self::POOL_OPTION_PREFIX . 'confiscation', 'value' => ''),
			);

			$group['fields']['confiscation'] = array(
				'id'    => self::POOL_OPTION_PREFIX . 'confiscation',
				'title' => __("Lose rewards with points expiration", 'woorewards-pro'),
				'type'  => 'box',
				'extra' => array(
					'id'       => self::POOL_OPTION_PREFIX . 'confiscation',
					'checked'  => $pool->getOption('confiscation'),
					'layout' => 'toggle',
					'tooltips' => __("After a points loss due to points expiration, the customer will have to earn the rewards again. Ignored if points expiration isn’t set. Incompatible with the option above", 'woorewards-pro')
				),
				'require' => array('selector' => '#' . self::POOL_OPTION_PREFIX . 'adapt_level', 'value' => ''),
			);

			$group['fields']['clamp_level'] = array(
				'id'    => self::POOL_OPTION_PREFIX . 'clamp_level',
				'title' => __("One level at a time", 'woorewards-pro'),
				'type'  => 'box',
				'extra' => array(
					'checked' => $pool->getOption('clamp_level'),
					'default' => false,
					'layout' => 'toggle',
					'tooltips' => __("If checked, customers can't earn more points than the points needed to reach the next level in one time.", 'woorewards-pro')
				)
			);

			$group['fields']['best_unlock'] = array(
				'id'    => self::POOL_OPTION_PREFIX . 'best_unlock',
				'title' => __("Unlock Best Level <b>Only</b>", 'woorewards-pro'),
				'type'  => 'lacselect',
				'extra' => array(
					'value'    => $pool->getOption('best_unlock'),
					'source'   => array(
						'off'  => array('value' => 'off', 'label' => _x("Off", "best_unlock settings", 'woorewards-pro')),
						'on'   => array('value' => 'on', 'label' => _x("On", "best_unlock settings", 'woorewards-pro')),
					),
					'id'       => 'reward_best_unlock',
					'mode'     => 'select',
					'default'  => 'off',
					'tooltips' => \LWS\Adminpanel\Tools\Conveniences::array2html(array(
						'tag' => 'ul',
						array(
							__("Off :", 'woorewards-pro'),
							__("All levels will be unlocked, even if the user earns enough points in one time to unlock several levels.", 'woorewards-pro'),
						),
						array(
							__("On :", 'woorewards-pro'),
							__("Only the best level will be unlocked. If a user earns enough points to unlock several levels, only the highest will be unlocked", 'woorewards-pro'),
						),
					)),
				)
			);
		}
		return $group;
	}

	protected function getPoolPointExpirationGroup($pool)
	{
		$expirationText = array(
			'join' => '<br/><br/>',
			__("You have access to different points expiration methods. To get more information on those methods, don't hesitate to take a look at the dedicated documentation. Simply click the book Icon on the top right.", 'woorewards-pro'),
			array(
				array('tag' => 'strong', __("Warning :", 'woorewards-pro')),
				__("You should only use one expiration method in your points and rewards system", 'woorewards-pro'),
			)
		);

		return array(
			'id'		=> 'wr_loyalty_expiration',
			'image'		=> LWS_WOOREWARDS_IMG . '/ls-calendar.png',
			'color'		=> '#a4489a',
			'class'		=> 'half',
			'title'		=> __("Points Expiration", 'woorewards-pro'),
			'text'		=> $expirationText,
			'extra'		=> array('doclink' => \LWS\WOOREWARDS\PRO\DocLinks::get('points-ex')),
			'fields'	=> array(
				'lifetime' => array(
					'id'    => self::POOL_OPTION_PREFIX . 'point_timeout',
					'title' => __("Points expiration for inactivity", 'woorewards-pro'),
					'type'  => 'duration',
					'extra' => array(
						'value' => $pool->getOption('point_timeout')->toString(),
						'help' => __("Defines if customers lose their points after an inactivity period", 'woorewards-pro')
					)
				),
				'transactional_expiry' => array(
					'id'    => self::POOL_OPTION_PREFIX . 'transactional_expiry',
					'title' => __("Transactional Points expiration", 'woorewards-pro'),
					'type'  => 'woorewards_periodic_trigger',
					'extra' => array(
						'value' => $pool->getOption('transactional_expiry'),
						'help' => sprintf(
							__("Defines if customers lose unused points periodically. Please read the %s for settings explanation.", 'woorewards-pro'),
							sprintf(
								"<a href='%s' target='_blank'>%s</a>",
								\LWS\WOOREWARDS\PRO\DocLinks::get('points-ex'),
								__("documentation", 'woorewards-pro')
							)
						),
					)
				)
			)
		);
	}

	protected function getPoolPointDisplayGroup($pool)
	{
		$currencyText = array(
			'join' => '<br/><br/>',
			__("You can change how points are displayed to customers. You can either set a text or an image.", 'woorewards-pro'),
			array(
				array('tag' => 'strong', __("Warning :", 'woorewards-pro')),
				__("If you use multiple languages, labels won't be translated with po/mo files. However, it's possible with WPML.", 'woorewards-pro'),
			),
		);

		return array(
			'id'       => 'wr_pts_disp',
			'image'		=> LWS_WOOREWARDS_IMG . '/ls-currency.png',
			'color'		=> '#a67c52',
			'class'		=> 'half',
			'title'    => __("Points Currency", 'woorewards-pro'),
			'extra'    => array('doclink' => \LWS\WOOREWARDS\PRO\DocLinks::get('pools-cur')),
			'text' 	   => $currencyText,
			'fields'   => array(
				'point_name' => array(
					'id'    => self::POOL_OPTION_PREFIX . 'point_name_singular',
					'title' => __("Point display name", 'woorewards-pro'),
					'type'  => 'text',
					'extra' => array(
						'value' => $pool->getOption('point_name_singular'),
						'placeholder' => __("Point", 'woorewards-pro'), //\LWS_WooRewards::getPointSymbol(1),
						'tooltips' => __("Point unit shown to the user.", 'woorewards-pro'),
					)
				),
				'points_name' => array(
					'id'    => self::POOL_OPTION_PREFIX . 'point_name_plural',
					'title' => __("Points display name (plural)", 'woorewards-pro'),
					'type'  => 'text',
					'extra' => array(
						'value' => $pool->getOption('point_name_plural'),
						'placeholder' => __("Points", 'woorewards-pro'), //\LWS_WooRewards::getPointSymbol(2),
						'tooltips' => __("(Optional) The singular form is used if plural is not set.", 'woorewards-pro'),
					)
				),
				'point_sym' => array(
					'id'    => self::POOL_OPTION_PREFIX . 'symbol',
					'title' => __("Point symbol", 'woorewards-pro'),
					'type'  => 'media',
					'extra' => array(
						'value' => $pool->getOption('symbol'),
						'tooltips' => __("If you set an image, it will replace the above labels.", 'woorewards-pro'),
					)
				),
				'point_format' => array(
					'id'    => self::POOL_OPTION_PREFIX . 'point_format',
					'title' => __("Point name position", 'woorewards-pro'),
					'type'  => 'lacselect',
					'extra' => array(
						'value' => $pool->getOption('point_format'),
						'mode' => 'select',
						'source' => array(
							array('value' => '%1$s %2$s', 'label' => _x("Right", 'Point name position', 'woorewards-pro')),
							array('value' => '%2$s %1$s', 'label' => _x("Left", 'Point name position', 'woorewards-pro')),
						),
					)
				),
				'thousand_sep' => array(
					'id'    => self::POOL_OPTION_PREFIX . 'thousand_sep',
					'title' => __("Thousand Separator", 'woorewards-pro'),
					'type'  => 'text',
					'extra' => array(
						'value' => $pool->getOption('thousand_sep'),
						'tooltips' => __("(Optional) The thousand separator when displaying big numbers.", 'woorewards-pro'),
					)
				),
				'decimal_shift' => array(
					'id'    => self::POOL_OPTION_PREFIX . 'decimal_shift',
					'title' => __("Decimal Shift", 'woorewards-pro'),
					'type'  => 'text',
					'extra' => array(
						'value' => $pool->getOption('decimal_shift'),
						'help' => array(
							__("(Optional) For display purpose only.", 'woorewards-pro'),
							__("The number of digit we shift the point value to left (negative) or right (positive). It can lead to a float number representation.", 'woorewards-pro'),
							__("Eg. a shift of 2 transform 1234 points to a 12.34 value.", 'woorewards-pro'),
						),
					)
				),
			),
		);
	}

	protected function getPoolRewardsGroup($pool)
	{
		$group = array(
			'id'     => 'lws_wr_spending_system',
			'class'  => 'half',
			'image'  => LWS_WOOREWARDS_IMG . '/ls-gift.png',
			'color'  => '#526981',
			'title'  => '',
			'text'   => '',
			'extra'  => array('doclink' => \LWS\WOOREWARDS\PRO\DocLinks::get('rewards')),
			'fields' => array(),
		);

		$rewards = array(
			'id'    => 'rewards',
			'type'  => 'editlist',
			'extra' => array(
				'editlist' => \lws_editlist(
					'UnlockableList-' . $pool->getId(),
					\LWS\WOOREWARDS\Ui\Editlists\UnlockableList::ROW_ID,
					new \LWS\WOOREWARDS\Ui\Editlists\UnlockableList($pool),
					\LWS\Adminpanel\EditList::MDA
				)->setPageDisplay(false)->setGroupBy($this->getGroupByLevelSettings($pool))->setCssClass('unlockablelist')->setRepeatHead(false),
			),
		);

		if (\LWS\WOOREWARDS\Core\Pool::T_LEVELLING == $pool->getOption('type')) {
			$group['title'] = __('Levels and Rewards', 'woorewards-pro');
			$group['text'] = array(
				'join' => '<br/>',
				array(
					__("In a leveling system, you must ", 'woorewards-pro'),
					array('tag' => 'strong', __("create levels first", 'woorewards-pro')),
				),
				array(
					__("After creating a level, you can add one or more rewards to the level.", 'woorewards-pro'),
				),
				array(
					array('tag' => 'strong', __("You can define as many levels and rewards as you want", 'woorewards-pro')),
				),
			);

			$group['fields']['rewards'] = $rewards;
		} else {
			// T_STANDARD
			$group['title'] = __('Rewards', 'woorewards-pro');
			$group['text'] = array(
				__("In a standard system, you have to choose how customers will spend their points.", 'woorewards-pro'),
				array('tag' => 'strong', __("They can either spend them directly on the cart to get an immediate discount or you can setup various rewards they buy with their points.", 'woorewards-pro')),
			);

			$group['fields']['mode'] = array(
				'id'    => self::POOL_OPTION_PREFIX . 'direct_reward_mode',
				'type'  => 'box',
				'title' => __("Rewards Type", 'woorewards-pro'),
				'extra' => array(
					'id'      => 'direct_reward_mode',
					'class'   => 'lws_switch',
					'value'   => $pool->getOption('direct_reward_mode'),
					'data'    => array(
						'left' => __("Rewards", 'woorewards-pro'),
						'right' => __("Points on Cart", 'woorewards-pro'),
						'colorleft' => '#425981',
						'colorright' => '#5279b1',
					),
				)
			);

			$group['fields']['rate'] = array(
				'id'    => self::POOL_OPTION_PREFIX . 'direct_reward_point_rate',
				'type'  => 'text',
				'title' => sprintf(__("Point Value in %s", 'woorewards-pro'), \LWS\Adminpanel\Tools\Conveniences::isWC() ? \get_woocommerce_currency_symbol() : '?'),
				'extra' => array(
					'value'   => $pool->getOption('direct_reward_point_rate'),
					'help' => __("Each point spent on the cart will decrease the order total of that value", 'woorewards-pro')
				),
				'require' => array('selector' => '#direct_reward_mode', 'value' => 'on'),
			);

			$group['fields']['min_points'] = array(
				'id'    => self::POOL_OPTION_PREFIX . 'direct_reward_min_points_on_cart',
				'title' => __("Min points usage", 'woorewards-pro'),
				'type'  => 'text',
				'extra' => array(
					'value'       => $pool->getOption('direct_reward_min_points_on_cart'),
					'placeholder' => '0',
					'help'        => __("The minimum amount of points that can be used on a single cart", 'woorewards-pro'),
				),
				'require' => array('selector' => '#direct_reward_mode', 'value' => 'on'),
			);

			$group['fields']['max_points'] = array(
				'id'    => self::POOL_OPTION_PREFIX . 'direct_reward_max_points_on_cart',
				'title' => __("Max points usage", 'woorewards-pro'),
				'type'  => 'text',
				'extra' => array(
					'value'       => $pool->getOption('direct_reward_max_points_on_cart'),
					'placeholder' => '',
					'help'        => __("The maximum amount of points that can be used on a single cart", 'woorewards-pro'),
				),
				'require' => array('selector' => '#direct_reward_mode', 'value' => 'on'),
			);

			$group['fields']['max_percent'] = array(
				'id'    => self::POOL_OPTION_PREFIX . 'direct_reward_max_percent_of_cart',
				'title' => __("Max percentage of cart", 'woorewards-pro'),
				'type'  => 'text',
				'extra' => array(
					'value'       => $pool->getOption('direct_reward_max_percent_of_cart'),
					'placeholder' => '%',
					'help'        => __("The maximum amount a customer can spend in a single cart will be limited to the percentage of the payable total. Leave blank for no limit.", 'woorewards-pro'),
				),
				'require' => array('selector' => '#direct_reward_mode', 'value' => 'on'),
			);

			$group['fields']['min_grandtotal'] = array(
				'id'    => self::POOL_OPTION_PREFIX . 'direct_reward_total_floor',
				'title' => __("Lower Cart Limit", 'woorewards-pro'),
				'type'  => 'text',
				'extra' => array(
					'value'       => $pool->getOption('direct_reward_total_floor'),
					'placeholder' => \LWS\Adminpanel\Tools\Conveniences::isWC() ? \get_woocommerce_currency_symbol() : '',
					'help'        => __("If set, customers can't use their points to discount the cart below that limit. Leave empty for no limit.", 'woorewards-pro'),
				),
				'require' => array('selector' => '#direct_reward_mode', 'value' => 'on'),
			);

			$group['fields']['min_subtotal'] = array(
				'id'    => self::POOL_OPTION_PREFIX . 'direct_reward_min_subtotal',
				'title' => __("Minimum Cart Amount", 'woorewards-pro'),
				'type'  => 'text',
				'extra' => array(
					'value'       => $pool->getOption('direct_reward_min_subtotal'),
					'placeholder' => \LWS\Adminpanel\Tools\Conveniences::isWC() ? \get_woocommerce_currency_symbol() : '',
					'help'        => __("Set a minimum cart amount under which customers can't use their points on the cart. Once the cart total is above that value, customers will be able to use their points. Leave empty for no minimum.", 'woorewards-pro'),
				),
				'require' => array('selector' => '#direct_reward_mode', 'value' => 'on'),
			);

			$group['fields']['prod_cats'] = array(
				'id'    => self::POOL_OPTION_PREFIX . 'direct_reward_prod_cats',
				'title' => __("Product categories", 'woorewards-pro'),
				'type'  => 'lacchecklist',
				'extra' => array(
					'comprehensive' => true,
					'predefined'    => 'taxonomy',
					'spec'          => array('taxonomy' => 'product_cat'),
					'value'         => $pool->getOption('direct_reward_prod_cats'),
					'help'          => __("If set, customers can only use points to discount products of the selected categories.", 'woorewards-pro'),
				),
				'require' => array('selector' => '#direct_reward_mode', 'value' => 'on'),
			);

			if (\apply_filters('lwsdev_coupon_individual_use_solver_exists', false)) {
				$group['fields']['discount_cats'] = array(
					'id'    => self::POOL_OPTION_PREFIX . 'direct_reward_discount_cats',
					'title' => __("Exclusive categories", 'woorewards-pro'),
					'type'  => 'lacchecklist',
					'extra' => array(
						'comprehensive' => true,
						'ajax'          => 'lwsdev_coupon_individual_use_solver_categories',
						'value'         => $pool->getOption('direct_reward_discount_cats'),
						'help'          => __("Exclusive categories that the coupon will be applied to. Extends the <i>“Individual use only”</i> rule.", 'woorewards-pro'),
					),
					'require' => array('selector' => '#direct_reward_mode', 'value' => 'on'),
				);
			}

			if ($pool->getOption('type') != \LWS\WOOREWARDS\Core\Pool::T_LEVELLING) {
				$group['fields']['best_unlock'] = array(
					'id'    => self::POOL_OPTION_PREFIX . 'best_unlock',
					'title' => __("Automatic Rewards Redeem", 'woorewards-pro'),
					'type'  => 'lacselect',
					'extra' => array(
						'value'    => $pool->getOption('best_unlock'),
						'source'   => array(
							'off'  => array('value' => 'off', 'label' => _x("Off", "best_unlock settings", 'woorewards-pro')),
							'on'   => array('value' => 'on', 'label' => _x("Unlock best reward only", "best_unlock settings", 'woorewards-pro')),
							'loop' => array('value' => 'and_loop', 'label' => _x("Unlock best reward first", "best_unlock settings", 'woorewards-pro')),
							'raz'  => array('value' => 'use_all_points', 'label' => _x("Unlock best reward and reset points", "best_unlock settings", 'woorewards-pro')),
						),
						'id'       => 'reward_best_unlock',
						'mode'     => 'select',
						'default'  => 'off',
						'tooltips' => \LWS\Adminpanel\Tools\Conveniences::array2html(array(
							'tag' => 'ul',
							array(
								__("Off :", 'woorewards-pro'),
								__("The customer has to manually redeem the rewards. A mail is sent each time he earns enough points for at least one of them.", 'woorewards-pro'),
							),
							array(
								__("Best only :", 'woorewards-pro'),
								__("Only the most expensive reward or the highest level the user can afford will be unlocked.", 'woorewards-pro'),
							),
							array(
								__("Best first :", 'woorewards-pro'),
								__("Rewards are unlocked as long as customer can afford it, starting by the most expensive reward (The same reward can be unlocked several time).", 'woorewards-pro'),
							),
							array(
								__("Best and Reset :", 'woorewards-pro'),
								__("Same as 'Best only' but consume all customer's points.", 'woorewards-pro'),
							),
						)),
					),
					'require' => array('selector' => '#direct_reward_mode', 'value' => ''),
				);
			}

			$rewards['require'] = array('selector' => '#direct_reward_mode', 'value' => '');
			$group['fields']['rewards'] = $rewards;
		}

		return $group;
	}

	protected function getGroupByLevelSettings($pool)
	{
		$groupBy = array(
			'key'       => 'cost',
			'activated' => ($pool->getOption('type') == \LWS\WOOREWARDS\Core\Pool::T_LEVELLING),
			'add'       => __("Add level", 'woorewards-pro'),
		);
		$labels = array(
			'group_value' => __("Untitled", 'woorewards-pro'),
			'group_title' => _x("Level Title", "Level Threshold Title edit", 'woorewards-pro'),
			'group_point' => _x("Points Threshold", "edit", 'woorewards-pro'),
			'form_title'  => \esc_attr(__("Title is required.", 'woorewards-pro')),
			'form_point'  => \esc_attr(__("Cost must be a number greater than zero.", 'woorewards-pro')),
			'title_title' => _x("Level Title", "Level Threshold Title edit", 'woorewards-pro'),
			'title_point' => _x("Points Threshold", "edit", 'woorewards-pro'),
		);
		$groupBy['head'] = <<<EOT
<div class='lws-wr-levelling-node-head'>
	<div class='lws-wr-levelling-node-item cost'>
		<div class='lws-wr-levelling-node-value'><span data-name='cost'>1</span></div>
		<div class='lws-wr-levelling-node-label'>{$labels['group_point']}</div>
	</div>
	<div class='lws-wr-levelling-node-item grouped_title'>
		<div class='lws-wr-levelling-node-value'><span data-name='grouped_title'>{$labels['group_value']}</span></div>
		<div class='lws-wr-levelling-node-label'>{$labels['group_title']}</div>
	</div>
</div>
EOT;
		$groupBy['form'] = <<<EOT
<div class='lws-wr-levelling-node-form'>
	<div class='lws-wr-levelling-node-item cost'>
		<div class='lws-wr-levelling-node-value'><input name='cost' class='lws-input lws-wr-cost-input' type='text' data-pattern='^\\d*[1-9]\\d*$' data-pattern-title='{$labels['form_point']}'/></div>
		<div class='lws-wr-levelling-node-label'>{$labels['title_point']}</div>
	</div>
	<div class='lws-wr-levelling-node-item grouped_title'>
		<div class='lws-wr-levelling-node-value'><input type='text' class='lws-input lws-wr-title-input' name='grouped_title' data-pattern='[^\\s]+' data-pattern-title='{$labels['form_title']}'/></div>
		<div class='lws-wr-levelling-node-label'>{$labels['title_title']}</div>
	</div>
</div>
EOT;
		return $groupBy;
	}

	function poolGeneralSettings($fields, \LWS\WOOREWARDS\Core\Pool $pool)
	{
		if (empty($pool->getId())) {
			$fields['type'] = array(
				'id'    => self::POOL_OPTION_PREFIX . 'type',
				'type'  => 'select',
				'title' => __("Behavior", 'woorewards-pro'),
				'extra' => array(
					'id'      => 'lws-wr-pool-option-type',
					'value'   => $pool->getOption('type'),
					'notnull' => true,
					'options' => array(
						'standard'  => _x("Standard", "Pool Type/behavior", 'woorewards-pro'),
						'levelling' => _x("Levelling", "Pool Type/behavior", 'woorewards-pro')
					),
					'help' => '<ul><li>' . __("<i>Standard behavior</i>: customers can spend points to buy rewards", 'woorewards-pro')
						. '</li><li>' . __("<i>Levelling behavior</i>: rewards are automatically granted to customers since they have enough points (points are never spent).", 'woorewards-pro')
						. '</li></ul>'
				)
			);
		}
		$happening = $pool->getOption('happening') ? true : false;
		$fields['lifestyle'] = 	array(
			'id'    => self::POOL_OPTION_PREFIX . 'happening',
			'type'  => 'box',
			'title' => __("System Type", 'woorewards-pro'),
			'extra' => array(
				'id'      => 'lws_woorewards_system_type',
				'class'   => 'lws_switch',
				'checked' => $happening,
				'data'    => array(
					'left' => __("Permanent", 'woorewards-pro'),
					'right' => __("Event", 'woorewards-pro'),
					'colorleft' => '#7958a5',
					'colorright' => '#5279b1',
				),
			)
		);

		if ($pool->isDeletable()) {
			$date = $pool->getOption('period_start');
			$fields['period_start'] = array(
				'id'    => self::POOL_OPTION_PREFIX . 'period_start',
				'type'  => 'input',
				'title' => __("Start Date", 'woorewards-pro'),
				'extra' => array(
					'id'      => 'lws-wr-pool-option-period-begin',
					'type'  => 'date',
					'value' => empty($date) ? '' : $date->format('Y-m-d'),
					'help'  => __("Before that date, the points and rewards system is disabled but customer can see it.", 'woorewards-pro')
				),
				'require' => array('selector' => '#lws_woorewards_system_type', 'value' => 'on'),
			);

			if ($pool->getOption('type') != \LWS\WOOREWARDS\Core\Pool::T_LEVELLING) {
				$date = $pool->getOption('period_mid');
				$fields['period_mid'] = array(
					'id'    => self::POOL_OPTION_PREFIX . 'period_mid',
					'type'  => 'input',
					'title' => __("Point earning end", 'woorewards-pro'),
					'extra' => array(
						'id'      => 'lws-wr-pool-option-period-mid',
						'type'  => 'date',
						'value' => empty($date) ? '' : $date->format('Y-m-d'),
						'help'  => __("After that date, customers can no longer earn points. But they still can spend them for rewards.", 'woorewards-pro')
					),
					'require' => array('selector' => '#lws_woorewards_system_type', 'value' => 'on'),
				);
			}

			$date = $pool->getOption('period_end');
			$fields['period_end'] = array(
				'id'    => self::POOL_OPTION_PREFIX . 'period_end',
				'type'  => 'input',
				'title' => __("End Date", 'woorewards-pro'),
				'extra' => array(
					'id'      => 'lws-wr-pool-option-period-end',
					'type'  => 'date',
					'value' => empty($date) ? '' : $date->format('Y-m-d'),
					'help'  => __("After that date, the points and rewards system will be disabled but customer can see it. Customers keep their remaining points but cannot use them anymore.", 'woorewards-pro')
				),
				'require' => array('selector' => '#lws_woorewards_system_type', 'value' => 'on'),
			);
		}

		return $fields;
	}

	function userspointsFilters($filters)
	{
		require_once LWS_WOOREWARDS_PRO_INCLUDES . '/ui/editlists/userspointsrangefilter.php';
		require_once LWS_WOOREWARDS_PRO_INCLUDES . '/ui/editlists/userspointsactivityfilter.php';

		$filters = array_merge(array(
			'range' => new \LWS\WOOREWARDS\PRO\Ui\Editlists\UsersPointsRangeFilter('range'),
			'activity' => new \LWS\WOOREWARDS\PRO\Ui\Editlists\UsersPointsActivityFilter('activity')
		), $filters);

		if (!empty(\get_option('lws_woorewards_manage_badge_enable', 'on'))) {
			require_once LWS_WOOREWARDS_PRO_INCLUDES . '/ui/editlists/userspointsbadgefilter.php';
			require_once LWS_WOOREWARDS_PRO_INCLUDES . '/ui/editlists/userspointsbadgeassignbulkaction.php';
			require_once LWS_WOOREWARDS_PRO_INCLUDES . '/ui/editlists/userspointsbadgeremovebulkaction.php';

			$filters = array_merge(array(
				'badge' => new \LWS\WOOREWARDS\PRO\Ui\Editlists\UsersPointsBadgeFilter('badge'),
			), $filters);

			$filters['badge_add'] = new \LWS\WOOREWARDS\PRO\Ui\Editlists\UsersPointsBadgeAssignBulkAction('badge_add');
			$filters['badge_rem'] = new \LWS\WOOREWARDS\PRO\Ui\Editlists\UsersPointsBadgeRemoveBulkAction('badge_rem');
		}

		require_once LWS_WOOREWARDS_PRO_INCLUDES . '/ui/editlists/userspointsbulkaction.php';
		$filters['points_add'] = new \LWS\WOOREWARDS\PRO\Ui\Editlists\UsersPointsBulkAction('points_add');

		require_once LWS_WOOREWARDS_PRO_INCLUDES . '/ui/editlists/userspointsunlockablesba.php';
		$filters['u_redeem'] = new \LWS\WOOREWARDS\PRO\Ui\Editlists\UsersPointsUnlockablesBA('u_redeem');
		require_once LWS_WOOREWARDS_PRO_INCLUDES . '/ui/editlists/userspointsconfiscationba.php';
		$filters['u_revoke'] = new \LWS\WOOREWARDS\PRO\Ui\Editlists\UsersPointsConfiscationBA('u_revoke');

		\add_filter('lws_wre_editlist_point_amount_display', array($this, 'shapePoints'), 10, 4);

		return $filters;
	}

	function shapePoints($points, $user, $poolId, $info)
	{
		static $mode = null;
		if (null === $mode)
			$mode = \get_option('lws_woorewards_show_levels_in_editlist');

		if ($mode) {
			static $levels = array();
			if (!isset($levels[$info->post_id])) {
				// build levels first time we ask
				$levels[$info->post_id] = false;
				$pool = \LWS\WOOREWARDS\PRO\Core\Pool::getOrLoad($info->post_id, false);
				if ($pool && $pool->getOption('type') == \LWS\WOOREWARDS\Core\Pool::T_LEVELLING) {
					$tmp = array();
					foreach ($pool->getUnlockables()->sort()->asArray() as $item) {
						$tmp[\intval($item->getCost())] = $item->getGroupedTitle('view');
					}
					\ksort($tmp, SORT_NUMERIC);
					$levels[$info->post_id] = $tmp;
				}
			}
			// levelling only
			if ($levels[$info->post_id]) {
				$title = '&nbsp;';
				foreach ($levels[$info->post_id] as $p => $t) {
					if ($p > $points)
						break;
					$title = $t;
				}
				// format
				if ('both' == $mode) {
					$points = sprintf(
						_x('%1$s - %2$s', 'points - level title', 'woorewards-pro'),
						"<span class='lws-pts'>{$points}</span>",
						"<span class='lws-level'>{$title}</span>"
					);
				} elseif ('level' == $mode) {
					$points = $title;
				}
			}
		}
		return $points;
	}

	function getSystemPage(&$freePages)
	{
		if (!$this->isIn('.system'))
			return $freePages['wr_system'];

		$system = $freePages['wr_system'];

		$system['tabs']['data_management']['groups']['historydel'] = array(
			'id'    => 'historydel',
			'title' => __("Delete points history and user data", 'woorewards-pro'),
			'icon'  => 'lws-icon-delete-forever',
			'class' => 'half',
			'text'  => __("Delete a user's or all users points history for one or all points and rewards systems.", 'woorewards-pro')
				. '<br/>' . __("Use this feature with care since this action is <b>irreversible</b>.", 'woorewards-pro'),
			'fields' => array(
				'historydel_mode' => array(
					'id'    => 'historydel_and_rewards',
					'title' => __("Select a behavior", 'woorewards-pro'),
					'type'  => 'box',
					'extra' => array(
						'gizmo' => true,
						'class' => 'lws_switch lws-ignore-confirm',
						'id'    => 'historydel_and_rewards',
						'data'  => array(
							'left'       => __("Delete History Only", 'woorewards-pro'),
							'right'      => __("Delete All User Data", 'woorewards-pro'),
							'colorleft'  => '#337766',
							'colorright' => '#993322',
						),
						'help'  => array('tag' => 'ul',
							array(
								__("Delete History Only", 'woorewards-pro') . ': ',
								__("Users keep their points and rewards, only history rows are cleaned.", 'woorewards-pro'),
							),
							array(
								__("Delete All User Data", 'woorewards-pro') . ': ',
								__("This will leave users with zero points and no rewards.", 'woorewards-pro'),
							),
						),
					)
				),
				'historydel_date_end' => array(
					'id'    => 'historydel_date_end',
					'title' => __("End date", 'woorewards-pro'),
					'type'  => 'input',
					'extra' => array(
						'gizmo' => true,
						'type'  => 'date',
						'class' => 'lws-ignore-confirm',
						'help'  => __("Only history before that date will be deleted. If you don't pick a date, the whole history will be deleted.", 'woorewards-pro'),
					),
					'require' => array('selector' => '#historydel_and_rewards', 'value' => '')
				),
				'historydel_user' => array(
					'id'    => 'delete_history_user',
					'title' => __("Select a user", 'woorewards-pro'),
					'type'  => 'lacselect',
					'extra' => array(
						'gizmo' => true,
						'class' => 'lws-ignore-confirm',
						'ajax'  => 'lws_adminpanel_get_users',
						'help'  => __("If you don't select a user, all users points history will be deleted.", 'woorewards-pro'),
					),
				),
				'historydel_pool' => array(
					'id'    => 'delete_history_pool',
					'title' => __("Select a points and rewards system", 'woorewards-pro'),
					'type'  => 'lacselect',
					'extra' => array(
						'gizmo' => true,
						'class' => 'lws-ignore-confirm',
						'ajax'  => 'lws_woorewards_pool_list',
						'help'  => __("If you don't select a system, history for all systems will be deleted.", 'woorewards-pro'),
					),
				),
				'trigger_historydel' => array(
					'id'    => 'trigger_delete_points_history',
					'title' => __("Delete Points History", 'woorewards-pro'),
					'type'  => 'button',
					'extra' => array(
						'gizmo'    => true,
						'callback' => array($this, 'deleteHistoryData')
					),
				),
			)
		);

		$system['tabs']['data_management']['groups']['stackclean'] = array(
			'id'    => 'stackclean',
			'title' => __("Delete unused points reserves", 'woorewards-pro'),
			'icon'  => 'lws-icon-delete-forever',
			'class' => 'half',
			'text'  => __("Delete point reserves that do not belong to any loyalty system anymore.", 'woorewards-pro')
				. '<br/>' . __("Use this feature with care since this action is <b>irreversible</b>.", 'woorewards-pro'),
			'fields' => array(
				'stackclean' => array(
					'id'    => 'stack_to_clean',
					'title' => __("Select a reserve", 'woorewards-pro'),
					'type'  => 'lacselect',
					'extra' => array(
						'gizmo' => true,
						'class' => 'lws-ignore-confirm',
						'ajax'  => 'lws_woorewards_not_used_stacks',
						'help'  => __("Only reserves where no loyalty system can be found are listed here. Relevant user point history will be deleted too.", 'woorewards-pro'),
					),
				),
				'trigger_stackclean' => array(
					'id'    => 'trigger_stack_to_clean',
					'title' => __("Delete Points Reserve", 'woorewards-pro'),
					'type'  => 'button',
					'extra' => array(
						'gizmo'    => true,
						'callback' => array($this, 'deletePointStack')
					),
				),
			)
		);

		if (isset($system['tabs']['data_management']['groups']['delete'])) {
			// move at last pos
			$delete = $system['tabs']['data_management']['groups']['delete'];
			$delete['class'] = 'half';
			unset($system['tabs']['data_management']['groups']['delete']);
			$system['tabs']['data_management']['groups']['delete'] = $delete;
		}
		$system['tabs']['api'] = $this->getAPITab();
		return $system;
	}

	function deleteHistoryData($btnId, $data = array())
	{
		if ($btnId != 'trigger_delete_points_history') return false;

		if (!(isset($data['delhis_conf']) && \wp_verify_nonce($data['delhis_conf'], 'deleteHistoryData'))) {
			$label = __("If you really want to reset points history, check this box and click on <i>'%s'</i> again.", 'woorewards-pro');
			$label = sprintf($label, __("Delete Points History", 'woorewards-pro'));
			$warn = __("This operation is irreversible!", 'woorewards-pro');

			$nonce = \esc_attr(\wp_create_nonce('deleteHistoryData'));
			$str = <<<EOT
<p>
	<input type='checkbox' class='lws-ignore-confirm' id='delhis_conf' name='delhis_conf' value='{$nonce}' autocomplete='off'>
	<label for='delhis_conf'>{$label} <b style='color: red;'>{$warn}</b></label>
</p>
EOT;
			return $str;
		}

		// check args
		$fullDel = ('on' == $data['historydel_and_rewards']);
		$limitDate = false;
		if (!$fullDel) {
			$data['historydel_date_end'] = \trim($data['historydel_date_end']);
			$limitDate = \date_create($data['historydel_date_end']);
			if ($data['historydel_date_end'] && !$limitDate) {
				return sprintf(
					'<b style="color: #d76f00;">%s</b>',
					__("Bad date format", 'woorewards-pro')
				);
			}
		}
		$userId = \intval($data['delete_history_user']);
		$poolId = \intval($data['delete_history_pool']);
		$stack = false;
		$pool = false;
		if ($poolId) {
			$pool = \LWS\WOOREWARDS\PRO\Core\Pool::getOrLoad($poolId, false);
			if (!$pool) {
				return sprintf(
					'<b style="color: #d76f00;">%s</b>',
					__("The selected points and rewards system can't be found", 'woorewards-pro')
				);
			}
		}

		require_once LWS_WOOREWARDS_PRO_INCLUDES . '/historycleaner.php';
		$cleaner = new \LWS\WOOREWARDS\PRO\HistoryCleaner($userId, $pool);
		\do_action('lws_woorewards_user_history_before_deletion', $cleaner, $limitDate);

		try{
			global $wpdb;
			if ($fullDel) {
				// Delete History AND user data, no dates allowed
				$cleaner->removeRewards();
				$cleaner->resetPoints();
				$cleaner->cleanOrders();
				$cleaner->cleanMetas();
				\do_action('lws_woorewards_user_rewards_data_deleted', $cleaner);
			}

			// clean history
			$cleaner->deleteLogs($limitDate);
			\do_action('lws_woorewards_user_history_deleted', $cleaner, $limitDate);

			if ($fullDel)
				return __("Data was correctly deleted", 'woorewards-pro');
			else
				return __("History was correctly deleted", 'woorewards-pro');
		} catch (\Exception $e) {
			error_log($e->getMessage());
			return sprintf('<b style="color: red;">%s</b><p><i>%s</i></p>',
				__("An error occured during deletion", 'woorewards-pro'),
				$e->getMessage()
			);
		}
	}

	function deletePointStack($btnId, $data = array())
	{
		if ($btnId != 'trigger_stack_to_clean') return false;

		$stackId = isset($data['stack_to_clean']) ? \sanitize_key($data['stack_to_clean']) : false;
		if (!$stackId)
			return sprintf('<b style="color: #d76f00;">%s</b>', __("You have to select a point reserve", 'woorewards-pro'));

		if (!(isset($data['delstack_conf']) && \wp_verify_nonce($data['delstack_conf'], 'deletePointStack'))) {
			$label = __("If you really want to delete this points reserve, check this box and click on <i>'%s'</i> again.", 'woorewards-pro');
			$label = sprintf($label, __("Delete Points Reserve", 'woorewards-pro'));
			$warn = __("This operation is irreversible!", 'woorewards-pro');

			$nonce = \esc_attr(\wp_create_nonce('deletePointStack'));
			$str = <<<EOT
<p>
	<input type='checkbox' class='lws-ignore-confirm' id='delstack_conf' name='delstack_conf' value='{$nonce}' autocomplete='off'>
	<label for='delstack_conf'>{$label} <b style='color: red;'>{$warn}</b></label>
</p>
EOT;
			return $str;
		}

		global $wpdb;
		// user points
		$wpdb->delete($wpdb->usermeta, array('meta_key' => \LWS\WOOREWARDS\Core\PointStack::MetaPrefix . $stackId));
		// user history
		$wpdb->delete($wpdb->lwsWooRewardsHistoric, array('stack' => $stackId));

		return __("Reserve was correctly deleted", 'woorewards-pro');
	}

	// $support = array with 'select' and 'texts'
	function addSupportText($support, $slug)
	{
		if ('woorewards' != $slug)
			return $support;

		$support['texts']['howto'] = <<<EOT
<h2>Setup Help</h2>
<p>In order to help you, we need detailed information about what you're trying to achieve.<br/>
Please provide the following information in your request</p>
<ul>
<li>How will your customers earn points ?</li>
<li>What are the rewards you want to offer ?</li>
<li>Do you plan on using referrals ?</li>
<li>Do you plan on using leveling points and rewards systems ?</li>
<li>How do you plan to display loyalty information to your customers ?</li>
</ul>
<h2>Your request</h2>
<p>Please provide information as detailed as possible.</p>
EOT;
		return $support;
	}

	function deleteAllData()
	{
		error_log("[MyRewards-Pro] Delete everything");

		\delete_option('lws_woorewards_pro_version');

		// delete badge posts
		$badges = \get_posts(array(
			'numberposts' => -1,
			'post_type' => 'lws_badge',
			'post_status' => array('publish', 'private', 'draft', 'pending', 'future', 'trash', 'auto-draft', 'inherit'),
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
			'cache_results'  => false
		));
		foreach ($badges as $badge) {
			\wp_delete_post($badge->ID, true);
		}

		global $wpdb;
		$wpdb->query("DROP TABLE IF EXISTS `{$wpdb->base_prefix}lws_webhooks_events`");

		// achievememts
		foreach (array('lws-wre-achievement') as $post_type) {
			foreach ($wpdb->get_col("SELECT ID FROM {$wpdb->posts} WHERE post_type='{$post_type}'") as $post_id)
				\wp_delete_post($post_id, true);
		}

		// user meta
		$wpdb->query("DELETE FROM {$wpdb->usermeta} WHERE meta_key LIKE 'lws_woorewards_%' OR meta_key LIKE 'lws_wre_event_%'");
		$wpdb->query("DELETE FROM {$wpdb->usermeta} WHERE meta_key IN ('lws-loyalty-done-steps','woorewards_special_title','woorewards_special_title_position')");

		// post meta
		$wpdb->query("DELETE FROM {$wpdb->postmeta} WHERE meta_key IN ('lws_woorewards_event_points_sponsorship','woorewards_freeproduct', 'woorewards_permanent','lws_woorewards_auto_apply','woorewards_reminder_done')");

		// mails
		foreach (array('wr_new_reward', 'wr_available_unlockables', 'wr_sponsored', 'couponreminder', 'pointsreminder') as $template) {
			\delete_option('lws_mail_subject_' . $template);
			\delete_option('lws_mail_preheader_' . $template);
			\delete_option('lws_mail_title_' . $template);
			\delete_option('lws_mail_header_' . $template);
			\delete_option('lws_mail_template_' . $template);
			\delete_option('lws_mail_bcc_admin_' . $template);
		}

		// clean options
		foreach (array(
			'lws_wre_product_points_preview',
			'lws_wre_cart_points_preview',
		) as $opt) {
			\delete_option($opt);
		}
	}
}
