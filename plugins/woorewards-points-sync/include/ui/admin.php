<?php
namespace LWS\WR_POINTS_SYNC\Ui;
// don't call the file directly
if( !defined( 'ABSPATH' ) ) exit();

/** Create the backend menu and settings pages. */
class Admin
{
	static function install()
	{
		if( \defined('LWS_WOOREWARDS_PAGE') && defined('LWS_WOOREWARDS_POINTS_SYNC_ACTIVATED') && LWS_WOOREWARDS_POINTS_SYNC_ACTIVATED )
		{
			$me = new self();
			\add_filter('lws_adminpanel_pages_' . LWS_WOOREWARDS_PAGE, array($me, 'addPage'), 101);
			\add_action('admin_enqueue_scripts', array($me , 'scripts'), 20); // after wr enqueue
		}
	}

	protected function getCurrentPage()
	{
		if( isset($_REQUEST['page']) && ($current = \sanitize_text_field($_REQUEST['page'])) )
			return $current;
		if( isset($_REQUEST['option_page']) && ($current = \sanitize_text_field($_REQUEST['option_page'])) )
			return $current;
		return false;
	}

	/** If WooRewards is V4 or more, insert a new tab in System page.
	 *	For V3, create a new subpage. */
	public function addPage($pages)
	{
		$this->v3support = !isset($pages['wr_system']);
		if( $this->v3support )
		{
			$pages['wr_system'] = array(
				'id'       => LWS_WOOREWARDS_PAGE.'.system',
				'title'    => __("WooRewards", 'wr-addon-points-sync'),
				'subtitle' => __("Points Sync.", 'wr-addon-points-sync'),
				'rights'   => 'manage_rewards',
				'tabs'     => array(),
			);
		}

		$page = $this->getCurrentPage();
		// in WR <= v3 editlist must be declared anyway
		if ($this->v3support || 0 === \strpos($page, LWS_WOOREWARDS_PAGE)) {
			$pages['wr_system']['tabs']['points-sync'] = array(
				'id'      => 'points-sync',
				'title'   => __("Points Sync.", 'wr-addon-points-sync'),
			);
			if (!$this->v3support) {
				$pages['wr_system']['tabs']['points-sync']['icon'] = 'lws-icon-api';
			}

			if( $this->v3support || (LWS_WOOREWARDS_PAGE.'.system') == $page )
			{
				if( isset($_GET['tab']) && 'points-sync' == $_GET['tab'] )
					$this->checkConfig();

				$pages['wr_system']['tabs']['points-sync']['groups']  = array(
					'disclamer' => array(
						'id'    => 'disclaimer',
						'title' => __("Disclaimer", 'wr-addon-points-sync'),
						'text'  => __("Be advised that you need to set an exception for this website IP on remote hosts firewalls.", 'wr-addon-points-sync'),
						'extra' => array('doclink' => 'https://plugins.longwatchstudio.com/docs/woorewards-4/add-ons/points-synchronization/'),
						'fields' => array(
							array(
								'id'    => '',
								'type'  => 'help',
								'extra' => array(
									'help' => "This solution is a synchronisation plugin that must be installed alongside WooRewards on all websites.<br/>
The main feature is to synchronize points movements between different websites.<br/><br/>
On all WooRewards websites, you should set up a similar points and rewards system.<br/>
<b>Rewards have to be the same on all websites so that customers don’t see the difference.</b><br/>
Methods to earn points however can be different from one website to another if need be.<br/><br/>
<b>Mails have to be configured on only one website so that customers don’t receive multiple emails when they unlock a reward or a level.</b><br/><br/>
If points expire, they will need to be configured on each website as they won’t be synchronized.<br/><br/>
This synchronization plugin will synchronize all points movements except the ones done by the plugin itself and the points expiration.<br/>
If the administrator decides to add/subtract points to a user on any website of the network, all other websites will be updated.",
								),
							),
						)
					),
					'local'    => $this->getLocalGroup(),
					'remotes'  => $this->getRemoteGroup(),
					'advanced' => $this->getAdvancedSettings(),
				);

				if( !$this->v3support )
				{
					$pages['wr_system']['tabs']['points-sync']['groups']['disclamer']['icon'] = 'lws-icon-billboard';
					$pages['wr_system']['tabs']['points-sync']['groups']['local']['icon'] = 'lws-icon-cloud-download-93';
					$pages['wr_system']['tabs']['points-sync']['groups']['remotes']['icon'] = 'lws-icon-migration';
					$pages['wr_system']['tabs']['points-sync']['groups']['advanced']['icon'] = 'lws-icon-plug-2';
				}
			}
		}
		return $pages;
	}

	public function scripts($hook)
	{
		if( 'woorewards_page_woorewards.system' == $hook )
		{
			\wp_enqueue_style('lws-wre-pro-poolssettings');
			\wp_enqueue_script('lws_wr_pts_sync_remotes', LWS_WR_POINTS_SYNC_JS.'/remotes.js', array('jquery'), LWS_WR_POINTS_SYNC_VERSION);
		}
	}

	protected function checkConfig()
	{
		$options = array(
			'forgettable' => true,
			'dismissible' => true,
			'level'       => 'warning',
		);
		if( !(\defined('LWS_WOOREWARDS_ACTIVATED') && LWS_WOOREWARDS_ACTIVATED) )
			\lws_admin_add_notice_once('lws_wr_pts_sync_check_wr', __("<b>WooRewards Points Synchronizer</b> requires <b>WooRewards <i>Premium</i></b> to be installed and activated.", 'wr-addon-points-sync'), $options);
		if( !\function_exists('curl_version') )
			\lws_admin_add_notice_once('lws_wr_pts_sync_check_curl', __("<b>WooRewards Points Synchronizer</b> requires <b>cUrl</b> PHP extension to be installed and enabled on the server.", 'wr-addon-points-sync'), $options);
		if( !is_ssl() )
			\lws_admin_add_notice_once('lws_wr_pts_sync_check_ssl', __("<b>WooRewards Points Synchronizer</b> recommands using SSL for your website configuration.", 'wr-addon-points-sync'), $options);
	}

	protected function getLocalGroup()
	{
		$keyCouple = \LWS_WooRewardsPointsSync::getOrCreateKeyCouple();
		$homeUrl = \LWS_WooRewardsPointsSync::getSiteUrl();
		$share = ($homeUrl . '|' . implode(':', $keyCouple));

		$group = array(
			'id'     => 'local',
			'title'  => __("Local site", 'wr-addon-points-sync'),
			'text'   => implode('<br/>', array(
				sprintf(
					__("Copy/Paste this sharing key %s to set up the connection with other websites.", 'wr-addon-points-sync'),
					\apply_filters('lws_format_copypast', $share)
				),
				__("Each synced website must be set in all other websites' Remote sites list.", 'wr-addon-points-sync'),
			)),
			'extra'  => array('doclink' => 'https://plugins.longwatchstudio.com/docs/woorewards-4/add-ons/points-synchronization/'),
			'fields' => array(
				'url' => array(
					'id' => '',
					'title' => __("Your local URL", 'wr-addon-points-sync'),
					'type'  => 'text',
					'extra' => array('gizmo' => true, 'readonly' => true, 'value' => $homeUrl)
				),
				'usr' => array(
					'id' => '',
					'title' => __("Your Secret Auth", 'wr-addon-points-sync'),
					'type'  => 'text',
					'extra' => array('gizmo' => true, 'readonly' => true, 'value' => $keyCouple[0])
				),
				'pwd' => array(
					'id' => '',
					'title' => __("Your Secret Pass", 'wr-addon-points-sync'),
					'type'  => 'text',
					'extra' => array('gizmo' => true, 'readonly' => true, 'value' => $keyCouple[1])
				),
			)
		);
		return $group;
	}

	protected function getRemoteGroup()
	{
		require_once LWS_WR_POINTS_SYNC_INCLUDES . '/ui/remotes.php';

		$group = array(
			'id'       => 'remotes',
			'title'    => __("Remote sites", 'wr-addon-points-sync'),
			'text'	   => __("Set a list of all websites for which you want to synchronize this website's points with.", 'wr-addon-points-sync'),
			'extra'    => array('doclink' => 'https://plugins.longwatchstudio.com/docs/woorewards-4/add-ons/points-synchronization/'),
			'editlist' => \lws_editlist(
				\LWS\WR_POINTS_SYNC\Ui\Remotes::SLUG,
				\LWS\WR_POINTS_SYNC\Ui\Remotes::ROW_ID,
				new \LWS\WR_POINTS_SYNC\Ui\Remotes(),
				\LWS\Adminpanel\EditList::ALL
			)->setPageDisplay(false)->setRepeatHead(false),
		);
		return $group;
	}

	protected function getAdvancedSettings()
	{
		$group = array(
			'id'     => 'advanced',
			'title'  => __("Advanced Settings", 'wr-addon-points-sync'),
			'text'   => __("If points movement synchronization fails for any reason, API calls will be repeated later until successful or max attempts is reached.", 'wr-addon-points-sync'),
			'extra'  => array('doclink' => 'https://plugins.longwatchstudio.com/docs/woorewards-4/add-ons/points-synchronization/'),
			'fields' => array(
				'max_attemps' => array(
					'id'    => 'lws_wr_pts_sync_max_attemps',
					'type'  => 'text',
					'title' => __("Max sync. attempt", 'wr-addon-points-sync'),
					'extra' => array(
						'pattern' => '\d+',
						'default' => '10',
					)
				),
				'min_delay' => array(
					'id'    => 'lws_wr_pts_sync_min_delay',
					'type'  => 'text',
					'title' => __("Delay between new attempt (in seconds)", 'wr-addon-points-sync'),
					'extra' => array(
						'pattern' => '\d+',
						'default' => '180',
					)
				),
				'stats_period' => array(
					'id'    => 'lws_wr_pts_sync_stats_period',
					'type'  => 'text',
					'title' => __("Period covered by statistics (in days)", 'wr-addon-points-sync'),
					'extra' => array(
						'pattern' => '\d+',
						'default' => '31',
						'help'    => __("It means synchronization error over than X days will be ignored. It only affects Status column in the above list.", 'wr-addon-points-sync'),
					)
				),
				'warn_period' => array(
					'id'    => 'lws_wr_pts_sync_warn_period',
					'type'  => 'text',
					'title' => __("Warning Period (in days)", 'wr-addon-points-sync'),
					'extra' => array(
						'pattern' => '\d+',
						'default' => '7',
						'help'    => __("Number of day since the last error before a synchronization point turn Green again. It only affects Status column in the above list.", 'wr-addon-points-sync'),
					)
				),
			)
		);
		return $group;
	}
}
