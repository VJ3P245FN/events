<?php
/**
 * Plugin Name: MyRewards Addon : Points Synchronizer
 * Description: Synchronize MyRewards Points between several sites.
 * Plugin URI: https://plugins.longwatchstudio.com/product/woorewards/
 * Author: Long Watch Studio
 * Author URI: https://longwatchstudio.com
 * Version: 1.2.0
 * Text Domain: wr-addon-points-sync
 * Domain Path: /languages/

 * Copyright (c) 2021 Long Watch Studio (email: contact@longwatchstudio.com). All rights reserved.

 */

// don't call the file directly
if( !defined( 'ABSPATH' ) ) exit();

/** That class holds the entire plugin. */
final class LWS_WooRewardsPointsSync
{

	public static function init()
	{
		static $instance = false;
		if( !$instance )
		{
			$instance = new self();
			$instance->defineConstants();
			$instance->load_plugin_textdomain();

			add_action('lws_adminpanel_register', array($instance, 'admin'));
			add_action('lws_adminpanel_plugins', array($instance, 'plugin'), 50);
			add_filter('lws-ap-release-woorewards-points-sync', function($rc){return ($rc . 'pro');});
			add_filter('plugin_action_links_'.plugin_basename( __FILE__ ), array($instance, 'extensionListActions'), 10, 2);

			if( \is_admin() && !(defined('DOING_AJAX') && DOING_AJAX) )
			{
				require_once LWS_WR_POINTS_SYNC_INCLUDES.'/updater.php';
				add_action('setup_theme', array('\LWS\WR_POINTS_SYNC\Updater', 'checkUpdate'), -100);
			}
		}
		return $instance;
	}

	public function v()
	{
		static $version = '';
		if( empty($version) ){
			if( !function_exists('get_plugin_data') ) require_once(ABSPATH . 'wp-admin/includes/plugin.php');
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
	function load_plugin_textdomain() {
		load_plugin_textdomain('wr-addon-points-sync', FALSE, basename( dirname( __FILE__ ) ) . '/languages/');
	}

	/**
	 * Define the plugin constants
	 *
	 * @return void
	 */
	private function defineConstants()
	{
		define( 'LWS_WR_POINTS_SYNC_VERSION', '1.2.0' );
		define( 'LWS_WR_POINTS_SYNC_FILE', __FILE__ );
		define( 'LWS_WR_POINTS_SYNC_DOMAIN', 'wr-addon-points-sync' );

		define( 'LWS_WR_POINTS_SYNC_PATH', dirname( LWS_WR_POINTS_SYNC_FILE ) );
		define( 'LWS_WR_POINTS_SYNC_INCLUDES', LWS_WR_POINTS_SYNC_PATH . '/include' );
		define( 'LWS_WR_POINTS_SYNC_SNIPPETS', LWS_WR_POINTS_SYNC_PATH . '/snippets' );
		define( 'LWS_WR_POINTS_SYNC_ASSETS',   LWS_WR_POINTS_SYNC_PATH . '/assets' );

		define( 'LWS_WR_POINTS_SYNC_URL', 		plugins_url( '', LWS_WR_POINTS_SYNC_FILE ) );
		define( 'LWS_WR_POINTS_SYNC_JS',  		plugins_url( '/js', LWS_WR_POINTS_SYNC_FILE ) );
		define( 'LWS_WR_POINTS_SYNC_CSS', 		plugins_url( '/styling/css', LWS_WR_POINTS_SYNC_FILE ) );
		define( 'LWS_WR_POINTS_SYNC_IMG', 		plugins_url( '/img', LWS_WR_POINTS_SYNC_FILE ) );

		global $wpdb;
		$wpdb->lwsWRPointsSync = $wpdb->prefix.'lws_wr_points_sync';
	}

	public function extensionListActions($links, $file)
	{
		if (!defined('LWS_WOOREWARDS_PAGE'))
			return $links;

		if (defined('LWS_WOOREWARDS_POINTS_SYNC_ACTIVATED') && LWS_WOOREWARDS_POINTS_SYNC_ACTIVATED)
			$label = __('Licence', 'wr-addon-points-sync');
		else
			$label = __('Add Licence Key', 'wr-addon-points-sync');
		$url = \esc_attr(\add_query_arg(array(
			'page' => LWS_WOOREWARDS_PAGE . '.system',
			'tab' => 'lic',
		), admin_url('admin.php#lws_group_targetable_addons')));
		$links[] = "<a href='{$url}'>{$label}</a>";

		if (!(defined('LWS_WOOREWARDS_POINTS_SYNC_ACTIVATED') && LWS_WOOREWARDS_POINTS_SYNC_ACTIVATED))
			return $links;

		array_unshift($links, sprintf(
			'<a href="%s">%s</a>',
			\esc_attr(add_query_arg(array('page'=>LWS_WOOREWARDS_PAGE.'.system', 'tab' => 'points-sync'), admin_url('admin.php'))),
			__('Settings')
		));
		return $links;
	}

	function admin()
	{
		require_once LWS_WR_POINTS_SYNC_INCLUDES . '/ui/admin.php';
		\LWS\WR_POINTS_SYNC\Ui\Admin::install();
	}

	public function plugin()
	{
		if (defined('LWS_WOOREWARDS_ACTIVATED') && LWS_WOOREWARDS_ACTIVATED) {
			$minVers = '4.2.5';
			if (defined('LWS_WOOREWARDS_PRO_VERSION') && \version_compare(LWS_WOOREWARDS_PRO_VERSION, $minVers, '>=')) {
				$ret = \apply_filters('lws_manager_instance', false);
				if ($ret)
					$ret->instance->add(LWS_WR_POINTS_SYNC_FILE, 'woorewards', 'woorewards-points-sync', 'LWS_WOOREWARDS_POINTS_SYNC_ACTIVATED');
				if (defined('LWS_WOOREWARDS_POINTS_SYNC_ACTIVATED') && LWS_WOOREWARDS_POINTS_SYNC_ACTIVATED)
					$this->install();

			} elseif (\is_admin()) {
				\lws_admin_add_notice_once('wr-addon-points-sync' . '-nolic', sprintf(
					__('The <i>%1$s</i> requires MyRewards Pro %2$s or higher. Please update the MyRewards plugin', 'wr-addon-points-sync'),
					\get_plugin_data(__FILE__, false)['Name'], $minVers
				), array('level' => 'warning'));
			}
		} elseif (\is_admin()) {
			\lws_admin_add_notice_once('wr-addon-points-sync' . '-nolic', sprintf(
				__("The <i>%s</i> requires MyRewards Pro with an activated license.", 'wr-addon-points-sync'),
				\get_plugin_data(__FILE__, false)['Name']
			), array('level' => 'warning'));
		}
	}

	/** autoload WooRewards core and collection classes. */
	public function autoload($class)
	{
		if( substr($class, 0, 19) == 'LWS\WR_POINTS_SYNC\\' )
		{
			$rest = substr($class, 19);
			$publicNamespaces = array(
				'Core',
			);
			$publicClasses = array(
			);

			if( in_array(explode('\\', $rest, 2)[0], $publicNamespaces) || in_array($rest, $publicClasses) )
			{
				$basename = str_replace('\\', '/', strtolower($rest));
				$filepath = LWS_WR_POINTS_SYNC_INCLUDES . '/' . $basename . '.php';
				if( file_exists($filepath) )
				{
					@include_once $filepath;
					return true;
				}
			}
		}
	}

	/** Take care WP_Post manipulation is hazardous before hook 'setup_theme' (since global $wp_rewrite is not already set) */
	private function install()
	{
		spl_autoload_register(array($this, 'autoload'));

		\LWS\WR_POINTS_SYNC\Core\API::install();
		\LWS\WR_POINTS_SYNC\Core\Sync::install();
	}

	static function getOrCreateKeyCouple()
	{
		static $couple = false;
		if( false == $couple )
		{
			$couple = \get_option('lws_wr_points_sync_key_couple');
			if( !($couple && \is_array($couple)) )
			{
				$couple = array(
					'wrk_' . substr(\hash('md5', implode(array(\get_current_blog_id(), \time(), \site_url()))), 0, 16),
					'wrs_' . \wp_generate_password(16, false),
				);
				\update_option('lws_wr_points_sync_key_couple', $couple);
			}
		}
		return $couple;
	}

	static function getSiteUrl()
	{
		$url = (defined('WP_SITEURL') && WP_SITEURL) ? WP_SITEURL : \get_site_option('siteurl');
		return \preg_replace('@^https?://@i', '', $url);
	}
}

LWS_WooRewardsPointsSync::init();
