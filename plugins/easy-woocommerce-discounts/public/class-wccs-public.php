<?php
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The public-facing functionality of the plugin.
 *
 * @link       taher.atashbar@gmail.com
 * @since      1.0.0
 *
 * @package    WC_Conditions
 * @subpackage WC_Conditions/public
 */

/**
 * The public-facing functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the admin-specific stylesheet and JavaScript.
 *
 * @package    WC_Conditions
 * @subpackage WC_Conditions/public
 * @author     Taher Atashbar <taher.atashbar@gmail.com>
 */
class WCCS_Public {

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
	 * @param string      $plugin_name The name of the plugin.
	 * @param string      $version     The version of this plugin.
	 * @param WCCS_Loader $loader
	 */
	public function __construct( $plugin_name, $version, WCCS_Loader $loader, WCCS_Service_Manager $services ) {
		$this->plugin_name = $plugin_name;
		$this->version     = $version;
		$this->loader      = $loader;
		$this->services    = $services;

		$this->load_dependencies();
	}

	/**
	 * Load dependencies required in admin area.
	 *
	 * @since  1.0.0
	 * @return void
	 */
	protected function load_dependencies() {
		/**
		 * The controller class of public area.
		 */
		require_once plugin_dir_path( __FILE__ ) . 'class-wccs-public-controller.php';
		require_once plugin_dir_path( __FILE__ ) . 'class-wccs-public-products-list.php';
		require_once plugin_dir_path( __FILE__ ) . 'class-wccs-public-cart-discount-hooks.php';
		require_once plugin_dir_path( __FILE__ ) . 'class-wccs-public-pricing-hooks.php';
		require_once plugin_dir_path( __FILE__ ) . 'class-wccs-public-cart-item-pricing.php';
		require_once plugin_dir_path( __FILE__ ) . 'class-wccs-public-product-pricing.php';
		require_once plugin_dir_path( __FILE__ ) . 'class-wccs-public-shipping-hooks.php';
		require_once plugin_dir_path( __FILE__ ) . 'class-wccs-public-total-discounts-hooks.php';

		// Shortcodes.
		require_once plugin_dir_path( __FILE__ ) . 'shortcodes/class-wccs-shortcode-products-list.php';
		require_once plugin_dir_path( __FILE__ ) . 'shortcodes/class-wccs-shortcode-bulk-table.php';
		require_once plugin_dir_path( __FILE__ ) . 'shortcodes/class-wccs-shortcode-sale-flash.php';
	}

	/**
	 * Register all of the hooks related to the admin area functionality
	 * of the plugin.
	 *
	 * @since    1.0.0
	 * @access   public
	 */
	public function define_hooks() {
		if ( WCCS()->is_request( 'frontend' ) ) {
			$this->loader->add_action( 'wp_enqueue_scripts', $this, 'enqueue_styles' );
			$this->loader->add_action( 'wp_enqueue_scripts', $this, 'enqueue_scripts' );

			$pricing_hooks = new WCCS_Public_Pricing_Hooks( $this->loader );
			$pricing_hooks->init();

			$this->services->set( 'WCCS_Public_Cart_Discount_Hooks', new WCCS_Public_Cart_Discount_Hooks( $this->loader ) );
			$this->services->set( 'WCCS_Public_Pricing_Hooks', $pricing_hooks );

			if ( (int) WCCS()->settings->get_setting( 'display_total_discounts', 0 ) ) {
				$this->services->set( 'WCCS_Public_Total_Discounts_Hooks', new WCCS_Public_Total_Discounts_Hooks( $this->loader ) );
			}

			// Shortcodes.
			$this->loader->add_shortcode( 'wccs_products_list', new WCCS_Shortcode_Products_List(), 'output' );
			$this->loader->add_shortcode( 'wccs_bulk_table', new WCCS_Shortcode_Bulk_Table(), 'output' );
			$this->loader->add_shortcode( 'wccs_sale_flash', new WCCS_Shortcode_Sale_Flash(), 'output' );
		}

		$this->services->set( 'WCCS_Public_Shipping_Hooks', new WCCS_Public_Shipping_Hooks( $this->loader ) );
	}

	/**
	 * Register the stylesheets for the public-facing side of the site.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_styles() {
		$suffix = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';

		if ( WCCS_Helpers::is_product_page() || is_cart() ) {
			wp_enqueue_style( 'wccs-public', plugin_dir_url( __FILE__ ) . 'css/wccs-public' . $suffix . '.css' );
		}
	}

	/**
	 * Register the JavaScript for the public-facing side of the site.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_scripts() {
		global $post;

		$suffix = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';

		if ( WCCS_Helpers::is_product_page() ) {
			wp_enqueue_script( 'wccs-product-pricing', plugin_dir_url( __FILE__ ) . 'js/wccs-product-pricing' . $suffix . '.js', array( 'jquery' ), $this->version, true );
			wp_localize_script(
				'wccs-product-pricing',
				'wccs_product_pricing_params',
				apply_filters(
					'wccs_product_pricing_params',
					array(
						'ajaxurl'    => admin_url( 'admin-ajax.php' ),
						'nonce'      => wp_create_nonce( 'wccs_single_product_nonce' ),
						'product_id' => $post->ID,
					)
				)
			);
		}
	}

}
