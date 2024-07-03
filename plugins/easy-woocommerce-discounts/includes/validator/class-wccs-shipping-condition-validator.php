<?php
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WCCS_Shipping_Condition_Validator extends WCCS_Condition_Validator {

    public function is_valid_conditions( array $conditions, $match_mode = 'all', array $package = array() ) {
        if ( empty( $conditions ) ) {
			return true;
		}

		$this->init_cart();

		// New structure conditions that supports OR conditions too.
		if ( is_array( $conditions[0] ) && ! isset( $conditions[0]['condition'] ) ) {
			$empty = true;
			foreach ( $conditions as $group ) {
				if ( empty( $group ) ) {
					continue;
				}

				$empty = false;
				$valid = true;
				foreach ( $group as $condition ) {
					if ( ! $this->is_valid( $condition, $package ) ) {
						$valid = false;
						break;
					}
				}
				if ( $valid ) {
					return true;
				}
			}
			return $empty;
		}

		foreach ( $conditions as $condition ) {
			if ( 'one' === $match_mode && $this->is_valid( $condition, $package ) ) {
				return true;
			} elseif ( 'all' === $match_mode && ! $this->is_valid( $condition, $package ) ) {
				return false;
			}
		}

		return 'all' === $match_mode;
    }

    public function is_valid( array $condition, array $package = array() ) {
        if ( empty( $condition ) ) {
			return false;
		}

		$is_valid = false;
		if ( is_callable( array( $this, $condition['condition'] ) ) ) {
            $is_valid = call_user_func_array( array( $this, $condition['condition'] ), array( $condition, $package ) );
		}

		return apply_filters( 'wccs_shipping_condition_validator_is_valid_' . $condition['condition'], $is_valid, $condition );
	}

}
