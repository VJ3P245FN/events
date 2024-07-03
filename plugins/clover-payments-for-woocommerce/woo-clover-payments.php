<?php
/**
 * Plugin Name: Clover Payments for WooCommerce
 * Plugin URI: https://wordpress.org/plugins/clover-payments-for-woocommerce/
 * Description: Accepting payments in Woo Commerce using Clover eCommerce.
 * Version: 1.0.11
 * Requires at least: 5.9
 * Requires PHP: 7.4 or Higher
 * Author: Clover eCommerce
 * Author URI: https://www.clover.com
 * License: Clear BSD
 * License URI : https://directory.fsf.org/wiki/License:BSD-3-Clause-Clear
 * Text Domain: woo-clv-payments
 * Domain Path: /i18n/languages/
 *
 * @package woo-clover-payments
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}
/**
 * Function No WooCommerce Notice.
 */
function woo_clv_no_wc_notice() {
	?>
	<div class="error"><p><strong>
	<?php
		echo esc_html__(
			'WooCommerce Clover Payment Gateway
 : We require WooCommerce to be installed and active. You can download here',
			'woo-clv-payments'
		);
	?>
		<a href="https://woocommerce.com/" target="_blank">WooCommerce</a></strong></p></div>
	<?php

}


if ( ! function_exists( 'woo_clv_add_gateway_class' ) ) {
		/**
		 * Add Main class.
		 *
		 * @param array $gateways Gateways.
		 * @return string
		 */
	function woo_clv_add_gateway_class( $gateways ) {
		if ( class_exists( 'WooCommerce' ) ) {
			$gateways[] = 'WOO_CLV_ADMIN';
			return $gateways;
		}
	}
}

if ( ! function_exists( 'woo_clv_init_gateway' ) ) {
		/**
		 * Init.
		 */
	function woo_clv_init_gateway() {
		if ( class_exists( 'WooCommerce' ) ) {
			if ( ! class_exists( 'WOO_CLV_ADMIN' ) ) {

				load_plugin_textdomain( 'woo-clv-payments', false, dirname( plugin_basename( __FILE__ ) ) . '/i18n/languages/' );

				include_once dirname( __FILE__ ) . '/includes/helper/trait-woo-clv-apihelper.php';
				include_once dirname( __FILE__ ) . '/includes/class-woo-clv-errormapper.php';

				include_once dirname( __FILE__ ) . '/includes/class-woo-clv-gateway.php';
				include_once dirname( __FILE__ ) . '/includes/class-woo-clv-admin.php';
			}

			if ( is_admin() ) {
				include_once dirname( __FILE__ ) . '/includes/class-woo-clv-admin-capture.php';
				add_action( 'wp_ajax_wc_clv_order_capture', array( 'WOO_CLV_ADMIN_CAPTURE', 'wc_clv_order_capture' ) );
				add_action( 'wp_ajax_nopriv_wc_clv_order_capture', array( 'WOO_CLV_ADMIN_CAPTURE', 'wc_clv_order_capture' ) );
				add_filter( 'bulk_actions-edit-shop_order', array( 'WOO_CLV_ADMIN_CAPTURE', 'bulk_actions_capture_option' ) );
				add_filter( 'handle_bulk_actions-edit-shop_order', array( 'WOO_CLV_ADMIN_CAPTURE', 'handle_bulk_actions_capture' ), 10, 3 );
				add_action( 'admin_notices', array( 'WOO_CLV_ADMIN_CAPTURE', 'notice_bulk_actions_capture' ) );

            }
		}
	}
}

add_action( 'admin_init', 'woo_clv_plugin_notices' );
/**
 * To display message if WooCommerce not installed.
 */
function woo_clv_plugin_notices() {
	if ( ! class_exists( 'WooCommerce' ) ) {
			add_action( 'admin_notices', 'woo_clv_no_wc_notice' );

	}

};

//This action hook to add payment information to invoice page
add_action( 'wpo_wcpdf_after_order_data', 'wc_clv_payment_card_info_on_invoice_page', 10, 2 );

/**
 * Display payment card fields in to Admin invoice page
 */

function wc_clv_payment_card_info_on_invoice_page ($template_type, $order): void
{
    if ($template_type == 'invoice') {
        /*
            get all the meta data values we need to display payment details
        */
        $card_details = get_post_meta($order->id, '_card_details', true);
        if( isset( $card_details ) && !empty($card_details)){
            ?>
            <tr class="payment-method">
                <th></th>
                <td><?php echo $card_details; ?></td>
            </tr>
            <?php
        }
    }
}


/**
 * Display oder details on checkout page for order_pay link
 */

add_action( 'before_woocommerce_pay', 'wc_clv_order_pay_order_details' );

function wc_clv_order_pay_order_details():void {

    // ONLY RUN IF PENDING ORDER EXISTS
    if ( isset( $_GET['pay_for_order'], $_GET['key'] ) ) {

        // GET ORDER ID FROM URL BASENAME
        $order_id = intval( basename( strtok( $_SERVER["REQUEST_URI"], '?' ) ) );
        $order = wc_get_order( $order_id );
        $order_number = $order->get_order_number();
        ?>
        <p><?php printf( esc_html__( 'Your order #%s placed on %s ', 'woocommerce' ), esc_html( $order->get_order_number() ), wc_format_datetime( $order->get_date_created() ) ); ?></p>

        <?php
        // INCLUDE CUSTOMER ADDRESS TEMPLATE
        //wc_get_template( 'order/order-details-customer.php', array( 'order' => $order ) );

    }
}

    add_filter( 'woocommerce_payment_gateways', 'woo_clv_add_gateway_class' );
    add_action( 'plugins_loaded', 'woo_clv_init_gateway' );












