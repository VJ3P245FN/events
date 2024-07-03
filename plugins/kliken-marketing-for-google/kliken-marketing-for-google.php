<?php
/**
 * Plugin Name: AI Powered Marketing
 * Plugin URI: https://woo.kliken.com/
 * Description: Kliken's all-in-one marketing platform helps business owners reach high-intent customers, surpass your competition and realize significant growth in sales, while decreasing conversion costs.
 * Version: 1.4.1
 * Author: Kliken
 * Author URI: http://kliken.com/
 * Developer: Kliken
 * Developer URI: http://kliken.com/
 * Text Domain: kliken-marketing-for-google
 * Domain path: /languages
 *
 * WC requires at least: 5.5
 * WC tested up to: 8.0
 *
 * License: GNU General Public License v3.0
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 *
 * @package Kliken Marketing for Google
 */

defined( 'ABSPATH' ) || exit;

define( 'KK_WC_PLUGIN_FILE', __FILE__ );
define( 'KK_WC_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'KK_WC_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'KK_WC_PLUGIN_REL_PATH', basename( __DIR__ ) );
define( 'KK_WC_AFFILIATE_ID', '82E7B644-DB42-40E9-9EDF-6FD10A4BAFB3' );
define( 'KK_WC_WOOKLIKEN_BASE_URL', 'https://woo.kliken.com/' );
define( 'KK_WC_AUTH_CALLBACK_URL', 'https://app.mysite-analytics.com/WebHooks/WooCommerceAuth/' );

define( 'KK_WC_INTEGRATION_PAGE_ID', 'kk_wcintegration' );

define( 'KK_WC_ACTION_CHECK_ACCOUNT', 'kk_wc_checkaccount' );
define( 'KK_WC_ACTION_DISMISS_NOTICE', 'kk_wc_dismissnotice' );
define( 'KK_WC_ACTION_FETCH_CART_ITEMS', 'kk_wc_fetchcartitems' );
define( 'KK_WC_ACTION_SAVE_ACCOUNT', 'kk_wc_saveaccount' );

define( 'KK_WC_TRANSIENT_AUTH_REDIRECT', 'kk_wc_activation_redirect' );

define( 'KK_WC_WELCOME_MESSAGE', 'kk_wc_welcome_message' );
define( 'KK_WC_BOOTSTRAP_MESSAGE', 'kk_wc_bootstrap_message' );

require KK_WC_PLUGIN_DIR . 'vendor/autoload.php';

/**
 * Starting point. Try to initiate the main instance of the plugin.
 */
function kk_wc_plugin() {
	static $plugin;

	if ( ! isset( $plugin ) ) {
		$plugin = new \Kliken\WcPlugin\Plugin();
	}

	return $plugin;
}

// Adopt this nice method from WooCommerce.
kk_wc_plugin()->maybe_run();
