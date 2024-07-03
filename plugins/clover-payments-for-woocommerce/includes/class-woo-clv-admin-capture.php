<?php
/**
 * Admin capture class
 *
 * @package woo-clover-payments
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Class WOO_CLV_ADMIN_CAPTURE
 */
class WOO_CLV_ADMIN_CAPTURE {

	/**
	 * Constructor
	 */
	public function __construct() {
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
	}

	/**
	 * Load Scripts
	 */
	public function enqueue_scripts() {
		wp_register_script(
			'wc-clover-admin-settings',
			plugins_url( '../admin/js/woo-clv-admin-capture.js', __FILE__ ),
			array(
				'jquery',
				'jquery-blockui',
			),
			'1.0.0',
			true
		);
		wp_enqueue_script( 'wc-clover-admin-settings' );
		wp_localize_script(
			'wc-clover-admin-settings',
			'wc_clover_setting_params',
			array(
				'ajaxurl' => admin_url( 'admin-ajax.php' ),
				'action'  => 'wc_clv_order_capture',
			)
		);
	}

	/**  *
	 *
	 * @param array $actions  Add option bulk action.
	 * @return type
	 */
	public static function bulk_actions_capture_option( $actions ) {
		$actions['wc_capture_charge'] = __( 'Capture Charge', 'woo-clv-payments' );
		return $actions;
	}

	/**  *
	 *
	 * @param type $redirect_to Redirect after success.
	 * @param type $action Dropdown selected in bulk action.
	 * @param type $post_ids Order id.
	 * @return type
	 */
	public static function handle_bulk_actions_capture( $redirect_to, $action, $post_ids ) {
		if ( 'wc_capture_charge' !== $action ) {
			return $redirect_to;
		}

		$processed_ids = array();
		$failed_ids    = array();
		foreach ( $post_ids as $post_id ) {
			$order  = wc_get_order( $post_id );
			$obj    = new WOO_CLV_ADMIN();
			$result = $obj->process_capture( $order, true );
			if ( $result['success'] ) {
				$processed_ids[] = $post_id;
			} elseif ( $result['processed'] ) {
				$failed_ids[] = $post_id;
			}
		}

				$clover_token_once = wp_create_nonce( 'clv-wc-capture-charge-nonce' );

				$redirect = add_query_arg(
					array(
						'wc_capture_charge'           => '1',
						'clv_wc_capture_charge_nonce' => $clover_token_once,
						'processed_count'             => count( $processed_ids ),
						'processed_ids'               => implode( ',', $processed_ids ),
						'failed_count'                => count( $failed_ids ),
					),
					$redirect_to
				);

		return $redirect;
	}

	/**
	 * Display bulk action success message.
	 *
	 * @return type
	 */
	public static function notice_bulk_actions_capture() {

		if ( empty( $_REQUEST['wc_capture_charge'] ) ) {
			return;
		}
		if (
			isset( $_REQUEST['wc_capture_charge'], $_REQUEST['clv_wc_capture_charge_nonce'] )
			&& wp_verify_nonce( sanitize_key( $_REQUEST['clv_wc_capture_charge_nonce'] ), 'clv-wc-capture-charge-nonce' )
		) {
			$count = isset( $_REQUEST['processed_count'] ) ? intval( $_REQUEST['processed_count'] ) : 0;
			if ( $count ) {
				?>
						<div id="message" class="updated fade"><p><?php echo esc_html_e( 'Orders captured via Clover', 'woo-clv-payments' ) . '(' . esc_attr( $count ) . ').'; ?></p></div>
				<?php

			}

						$fcount = isset( $_REQUEST['failed_count'] ) ? intval( $_REQUEST['failed_count'] ) : 0;
			if ( $fcount ) {
				?>
						<div id="message" class="updated fade clv-error" style="border-left-color: #a00;"><p><?php echo esc_html_e( 'Orders failed to capture via Clover', 'woo-clv-payments' ) . '(' . esc_attr( $fcount ) . ').'; ?></p></div>
				<?php

			}
		}
	}

	/**
	 * Capture action.
	 */
	public static function wc_clv_order_capture() {
		if (
			isset( $_REQUEST['order_id'], $_REQUEST['order_id_nonce'] )
			&& wp_verify_nonce( sanitize_key( $_REQUEST['order_id_nonce'] ), 'order-id-nonce' )
		) {
			$order_id = sanitize_text_field( wp_unslash( $_REQUEST['order_id'] ) );
		} else {
			$order_id = '';
		}

		$order_id = isset( $order_id ) ? intval( $order_id ) : -1;
		$order    = wc_get_order( $order_id );
		$obj      = new WOO_CLV_ADMIN();
		$result   = $obj->process_capture( $order );
		wp_send_json_success( $result );
	}

}
new WOO_CLV_ADMIN_CAPTURE();

