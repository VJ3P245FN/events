<?php

defined( 'ABSPATH' ) || exit;

class WCCS_Rest_Ch extends WCCS_Rest_Base_Controller {

	protected $rest_base = 'ch';

	public function register_routes() {
        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base,
            array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'ch' ),
					'permission_callback' => array( $this, 'create_item_permissions_check' ),
				),
            )
		);
	}

	public function ch( $request ) {
		try {
			if ( empty( $request['action'] ) ) {
				throw new Exception( __( 'Action is required.', 'easy-woocommerce-discounts' ) );
			}

			switch ( $request['action'] ) {
				case 'later':
					return $this->later( $request );
					break;

				case 'dismiss':
					return $this->dismiss( $request );
					break;
			}

			throw new Exception( __( 'Action not found.', 'easy-woocommerce-discounts' ) );
		} catch ( Exception $e ) {
			return new WP_Error( 'asnp_ewd_rest_ch_error', $e->getMessage(), array( 'status' => 400 ) );
		}
	}

	protected function later() {
		$ch = WCCS_Helpers::get_ch();
		$ch['schedule'] = strtotime( '+30 days' );
		WCCS_Helpers::set_ch( $ch );

		return rest_ensure_response( array( 'success' => 1 ) );
	}

	protected function dismiss() {
		$ch = WCCS_Helpers::get_ch();
		$ch['dismissed'] = true;
		WCCS_Helpers::set_ch( $ch );

		return rest_ensure_response( array( 'success' => 1 ) );
	}

}
