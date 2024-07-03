<?php
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WCCS_Rules_Helpers {

    public static function has_conditions( array $rules, array $conditions, $check_all_conditions = false ) {
        if ( empty( $rules ) || empty( $conditions ) ) {
            return false;
        }

        foreach ( $conditions as $condition ) {
            foreach ( $rules as $rule ) {
                if ( $check_all_conditions && ! self::has_condition( $rule, $condition ) ) {
                    return false;
                } elseif ( ! $check_all_conditions && self::has_condition( $rule, $condition ) ) {
                    return true;
                }
            }
        }

        return $check_all_conditions ? true : false;
    }

    public static function has_condition( $rule, $condition ) {
        if ( empty( $rule ) || empty( $condition ) || empty( $rule->conditions ) ) {
            return false;
        }

        foreach ( $rule->conditions as $rule_condition ) {
            if ( ! empty( $rule_condition['condition'] ) && $rule_condition['condition'] == $condition ) {
                return true;
            }
        }

        return false;
    }

}
