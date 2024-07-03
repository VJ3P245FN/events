<?php
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WCCS_Customer {

	public $customer;

	public function __construct( WP_User $customer ) {
		$this->customer = $customer;
	}

	public function __get( $key ) {
		if ( property_exists( $this, $key ) ) {
			return $this->$key;
		} else {
			return $this->customer->$key;
		}
	}

	public function __call( $name, $arguments ) {
		if ( method_exists( $this, $name ) ) {
			return call_user_func_array( array( $this, $name ), $arguments );
		} elseif ( is_callable( array( $this->customer, $name ) ) ) {
			return call_user_func_array( array( $this->customer, $name ), $arguments );
		}
	}

}
