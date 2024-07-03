<?php

namespace LWS\WOOREWARDS\PRO\Ui;

// don't call the file directly
if (!defined('ABSPATH')) exit();

/** Add a leaderboard authorization field. */
class LeaderboardAuth
{
	static function register()
	{
		if( \get_option('lws_woorewards_enable_leaderboard') )
		{
			$me = new self();
			\add_action('woocommerce_edit_account_form', array($me, 'myaccountDetailForm'));
			\add_action('woocommerce_save_account_details', array($me, 'myaccountDetailSave'));
		}
	}

	function myaccountDetailForm()
	{
		$value = 0;
		$values = \get_user_meta(\get_current_user_id(), 'wr_user_leadeboard_auth', false);
		if( $values )
			$value = ('yes' != reset($values) ? 1 : 0);

		\woocommerce_form_field('wr_user_leadeboard_auth', array(
			'type'  => 'checkbox',
			'class' => array('form-row-wide'),
			'label_class'   => array('woocommerce-form__label woocommerce-form__label-for-checkbox checkbox'),
			'input_class'   => array('woocommerce-form__input woocommerce-form__input-checkbox input-checkbox'),
			'label' => __("Don't appear on the leaderboard", 'woorewards-pro'),
			'clear' => true,
		), $value);
	}

	// Save checkbox field value for My Account > Account details
	function myaccountDetailSave($userId)
	{
		$value = (isset($_POST['wr_user_leadeboard_auth']) && $_POST['wr_user_leadeboard_auth']) ? 'no' : 'yes';
		\update_user_meta($userId, 'wr_user_leadeboard_auth', $value);
	}
}
