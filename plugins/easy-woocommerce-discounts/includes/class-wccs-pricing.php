<?php
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WCCS_Pricing {

	protected $pricings;

	protected $date_time_validator;

	protected $condition_validator;

	protected $cache;

	public $rules_filter;

	public function __construct(
		array $pricings,
		WCCS_Pricing_Condition_Validator $condition_validator = null,
		WCCS_Date_Time_Validator $date_time_validator = null,
		WCCS_Rules_Filter $rules_filter = null
	) {
		$wccs = WCCS();

		$this->pricings            = $pricings;
		$this->date_time_validator = null !== $date_time_validator ? $date_time_validator : $wccs->WCCS_Date_Time_Validator;
		$this->condition_validator = null !== $condition_validator ? $condition_validator : $wccs->WCCS_Pricing_Condition_Validator;
		$this->rules_filter        = null !== $rules_filter ? $rules_filter : new WCCS_Rules_Filter();
		$this->cache               = array(
			'simple'         => false,
			'bulk'           => false,
			'tiered'         => false,
			'purchase'       => false,
			'products_group' => false,
			'exclude'        => false,
		);
	}

	public function get_all_pricing_rules() {
		return $this->pricings;
	}

	public function get_simple_pricings() {
		if ( false !== $this->cache['simple'] ) {
			return $this->cache['simple'];
		}

		$this->cache['simple'] = array();
		if ( empty( $this->pricings ) ) {
			return $this->cache['simple'] = apply_filters( 'wccs_pricing_simples', $this->cache['simple'], $this );
		}

		foreach ( $this->pricings as $pricing ) {
			if ( 'simple' !== $pricing->mode || empty( $pricing->items ) || empty( $pricing->discount ) || floatval( $pricing->discount ) <= 0 ) {
				continue;
			} // Validating date time.
			elseif ( ! $this->date_time_validator->is_valid_date_times( $pricing->date_time, ( ! empty( $pricing->date_times_match_mode ) ? $pricing->date_times_match_mode : 'one' ) ) ) {
				continue;
			} // Validating conditions.
			elseif ( ! $this->condition_validator->is_valid_conditions( $pricing->conditions, ( ! empty( $pricing->conditions_match_mode ) ? $pricing->conditions_match_mode : 'all' ) ) ) {
				continue;
			}

			$this->cache['simple'][ $pricing->id ] = array(
				'mode'                  => 'simple',
				'apply_mode'            => ! empty( $pricing->apply_mode ) ? $pricing->apply_mode : 'all',
				'order'                 => (int) $pricing->ordering,
				'discount'              => floatval( $pricing->discount ),
				'discount_type'         => $pricing->discount_type,
				'items'                 => $pricing->items,
				'exclude_items'         => ! empty( $pricing->exclude_items ) ? $pricing->exclude_items : array(),
				'date_time'             => $pricing->date_time,
				'date_times_match_mode' => ! empty( $pricing->date_times_match_mode ) ? $pricing->date_times_match_mode : 'one',
			);
		}

		return $this->cache['simple'] = apply_filters( 'wccs_pricing_simples', $this->cache['simple'], $this );
	}

	public function get_bulk_pricings() {
		if ( false !== $this->cache['bulk'] ) {
			return $this->cache['bulk'];
		}

		$this->cache['bulk'] = array();
		if ( empty( $this->pricings ) ) {
			return $this->cache['bulk'] = apply_filters( 'wccs_pricing_bulks', $this->cache['bulk'], $this );
		}

		foreach ( $this->pricings as $pricing ) {
			if ( 'bulk' !== $pricing->mode || empty( $pricing->items ) || empty( $pricing->quantity_based_on ) || empty( $pricing->quantities ) ) {
				continue;
			} // Validating date time.
			elseif ( ! $this->date_time_validator->is_valid_date_times( $pricing->date_time, ( ! empty( $pricing->date_times_match_mode ) ? $pricing->date_times_match_mode : 'one' ) ) ) {
				continue;
			} // Validating conditions.
			elseif ( ! $this->condition_validator->is_valid_conditions( $pricing->conditions, ( ! empty( $pricing->conditions_match_mode ) ? $pricing->conditions_match_mode : 'all' ) ) ) {
				continue;
			}

			// Validating quantities.
			$valid_quantities = array();
			foreach ( $pricing->quantities as $quantity ) {
				if ( empty( $quantity['min'] ) || intval( $quantity['min'] ) < 0 || empty( $quantity['discount_type'] ) || floatval( $quantity['discount'] ) < 0 ) {
					continue;
				} elseif ( ! empty( $quantity['max'] ) && ( intval( $quantity['max'] ) < 0 || intval( $quantity['max'] ) < intval( $quantity['min'] ) ) ) {
					continue;
				}

				$valid_quantities[] = $quantity;
			}
			if ( empty( $valid_quantities ) ) {
				continue;
			}

			$this->cache['bulk'][ $pricing->id ] = array(
				'mode'                  => 'bulk',
				'apply_mode'            => ! empty( $pricing->apply_mode ) ? $pricing->apply_mode : 'all',
				'order'                 => (int) $pricing->ordering,
				'quantities'            => $valid_quantities,
				'quantity_based_on'     => $pricing->quantity_based_on,
				'items'                 => $pricing->items,
				'exclude_items'         => ! empty( $pricing->exclude_items ) ? $pricing->exclude_items : array(),
				'display_quantity'      => ! empty( $pricing->display_quantity ) ? $pricing->display_quantity : 'yes',
				'display_price'         => ! empty( $pricing->display_price ) ? $pricing->display_price : 'yes',
				'display_discount'      => ! empty( $pricing->display_discount ) ? $pricing->display_discount : 'no',
				'date_time'             => $pricing->date_time,
				'date_times_match_mode' => ! empty( $pricing->date_times_match_mode ) ? $pricing->date_times_match_mode : 'one',
			);
		}

		return $this->cache['bulk'] = apply_filters( 'wccs_pricing_bulks', $this->cache['bulk'], $this );
	}

	public function get_exclude_rules() {
		return array();
	}

	/**
	 * Is given product in excluded rules.
	 *
	 * @since  1.1.0
	 *
	 * @param  int|WC_Product $product
	 * @param  int|WC_Product $variation
	 * @param  array          $variations
	 *
	 * @return boolean
	 */
	public function is_in_exclude_rules( $product, $variation = 0, array $variations = array() ) {
		return false;
	}

	public function get_pricings( array $pricing_types = array( 'simple', 'bulk' ) ) {
		$pricings = array();

		if ( in_array( 'simple', $pricing_types ) ) {
			$pricings['simple'] = $this->get_simple_pricings();
		}

		if ( in_array( 'bulk', $pricing_types ) ) {
			$pricings['bulk'] = $this->get_bulk_pricings();
		}

		return apply_filters( 'wccs_pricing_pricings', $pricings, $pricing_types );
	}

	/**
	 * Reset cached pricings.
	 *
	 * @since  2.8.0
	 *
	 * @return void
	 */
	public function reset_cache() {
		$this->cache = array(
			'simple'         => false,
			'bulk'           => false,
			'tiered'         => false,
			'purchase'       => false,
			'products_group' => false,
			'exclude'        => false,
		);
	}

}
