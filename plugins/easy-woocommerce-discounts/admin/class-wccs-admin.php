<?php
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The admin-specific functionality of the plugin.
 *
 * @link       taher.atashbar@gmail.com
 * @since      1.0.0
 *
 * @package    WC_Conditions
 * @subpackage WC_Conditions/admin
 */

/**
 * The admin-specific functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the admin-specific stylesheet and JavaScript.
 *
 * @package    WC_Conditions
 * @subpackage WC_Conditions/admin
 * @author     Taher Atashbar <taher.atashbar@gmail.com>
 */
class WCCS_Admin {

	/**
	 * The ID of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $plugin_name    The ID of this plugin.
	 */
	private $plugin_name;

	/**
	 * The version of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $version    The current version of this plugin.
	 */
	private $version;

	/**
	 * The loader that's responsible for maintaining and registering all hooks that power
	 * the plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      WCCS_Loader    $loader    Maintains and registers all hooks for the plugin.
	 */
	private $loader;

	/**
	 * Service container of the plugin.
	 *
	 * @since 1.0.0
	 *
	 * @var   WCCS_Service_Manager
	 */
	private $services;

	/**
	 * Initialize the class and set its properties.
	 *
	 * @since 1.0.0
	 * @param string      $plugin_name The name of this plugin.
	 * @param string      $version     The version of this plugin.
	 * @param WCCS_Loader $loader
	 */
	public function __construct( $plugin_name, $version, WCCS_Loader $loader, WCCS_Service_Manager $services ) {
		$this->plugin_name = $plugin_name;
		$this->version     = $version;
		$this->loader      = $loader;
		$this->services    = $services;

		$this->load_dependencies();
		$this->black_friday();
	}

	/**
	 * Load dependencies required in admin area.
	 *
	 * @since  1.0.0
	 * @return void
	 */
	protected function load_dependencies() {
		/**
		 * The class responsible for Ajax operations.
		 */
		require_once plugin_dir_path( __FILE__ ) . 'class-wccs-admin-ajax.php';
		/**
		 * The controller class of admin area.
		 */
		require_once plugin_dir_path( __FILE__ ) . 'class-wccs-admin-controller.php';
		/**
		 * The class responsible for outputting html elements in pages.
		 */
		require_once plugin_dir_path( __FILE__ ) . 'class-wccs-admin-html-element.php';
		/**
		 * The class responsible for creating all admin menus of the plugin.
		 */
		require_once plugin_dir_path( __FILE__ ) . 'class-wccs-admin-menu.php';
		/**
		 * The class responsible for admin assets.
		 */
		require_once plugin_dir_path( __FILE__ ) . 'class-wccs-admin-assets.php';
		/**
		 * The class responsible for showing admin notices.
		 */
		require_once plugin_dir_path( __FILE__ ) . 'class-wccs-admin-notices.php';

		require_once plugin_dir_path( __FILE__ ) . 'class-wccs-admin-select-data-provider.php';
		require_once dirname( __FILE__ ) . '/class-wccs-admin-conditions-hooks.php';
		require_once dirname( __FILE__ ) . '/class-wccs-admin-order-hooks.php';
	}

	/**
	 * Register all of the hooks related to the admin area functionality
	 * of the plugin.
	 *
	 * @since    1.0.0
	 * @access   public
	 */
	public function define_hooks() {
		// Menu hooks.
		$menu = new WCCS_Admin_Menu( $this->loader );
		// Admin notices.
		WCCS()->WCCS_Admin_Notices->init();
		// Ajax Operations.
		new WCCS_Admin_Ajax( $this->loader );
		// Admin Assets.
		$admin_assets = new WCCS_Admin_Assets( $this->loader, $menu );
		$admin_assets->init_hooks();

		$conditions_hooks = new WCCS_Admin_Conditions_Hooks( $this->loader );
		$conditions_hooks->enable_hooks();

		$order_hooks = new WCCS_Admin_Order_Hooks( $this->loader );
		$order_hooks->enable_hooks();

		// Cache Clear Hooks.
		WCCS()->WCCS_Clear_Cache->enable_hooks();

		// Plugin links.
		$this->loader->add_filter( 'plugin_row_meta', $this, 'plugin_row_meta_links', 10, 2 );
	}

	/**
	 * Plugin row meta links
	 * This function adds additional links below the plugin in admin plugins page.
	 *
	 * @since  1.0.0
	 * @param  array  $links 	The array having default links for the plugin.
	 * @param  string $file 	The name of the plugin file.
	 * @return array  $links 	Plugin default links and specific links.
	 */
	public function plugin_row_meta_links( $links, $file ) {
		if ( false !== strpos( $file, 'easy-woocommerce-discounts.php' ) ) {
			$plugin_links = array(
				'<a href="https://www.asanaplugins.com/product/advanced-woocommerce-dynamic-pricing-discounts/?utm_source=easy-woocommerce-discounts-free&utm_campaign=easy-woocommerce-discounts&utm_medium=link" target="_blank" onMouseOver="this.style.color=\'#55ce5a\'" onMouseOut="this.style.color=\'#39b54a\'" style="color: #39b54a; font-weight: bold;">' . esc_html__( 'Go Pro', 'easy-woocommerce-discounts' ) . '</a>',
			);
			$links = array_merge( $links, $plugin_links );
		}

		return $links;
	}

	protected function black_friday() {
		$name = 'wccs_black_friday_' . date( 'Y' );
		if ( (int) get_option( $name . '_added' ) ) {
			// Is Black Friday expired.
			if ( time() > strtotime( date( 'Y' ) . '-11-30' ) ) {
				WC_Admin_Notices::remove_notice( $name );
				delete_option( $name . '_added' );
			}
			return;
		}

		if ( WC_Admin_Notices::has_notice( $name ) ) {
			return;
		}

		// Is Black Friday applicable.
		if (
			time() < strtotime( date( 'Y' ) . '-11-20' ) ||
			time() > strtotime( date( 'Y' ) . '-11-30' )
		) {
			return;
		}

		WC_Admin_Notices::add_custom_notice(
			$name,
			'<p>' . __( '<strong>Black Friday Exclusive:</strong> SAVE up to 50% & access to <strong>Discount Rules and Dynamic Pricing Pro</strong> features.', 'easy-woocommerce-discounts' ) . '<a class="button button-primary" style="margin-left: 10px; background: #5614d5; border-color: #5614d5;" target="_blank" href="https://asanaplugins.com/product/woocommerce-dynamic-pricing-and-discounts-plugin/?utm_source=easy-woocommerce-discounts-free&utm_campaign=black-friday&utm_medium=link">' . __('Grab The Offer', 'easy-woocommerce-discounts') . '</a></p>'
		);

		update_option( $name . '_added', 1 );
	}

}
