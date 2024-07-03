<?php
/**
 * Gateway class
 *
 * @package woo-clover-payments
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Delete the api key.
$settings                     = get_option( 'woocommerce_clover_payments_settings' );
$settings['private_key']      = '';
$settings['test_private_key'] = '';

update_option( 'woocommerce_clover_payments_settings', $settings );




