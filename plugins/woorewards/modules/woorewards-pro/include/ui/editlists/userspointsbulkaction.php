<?php
namespace LWS\WOOREWARDS\PRO\Ui\Editlists;

// don't call the file directly
if( !defined( 'ABSPATH' ) ) exit();

/** Add points to a set of user. */
class UsersPointsBulkAction extends \LWS\Adminpanel\EditList\Action
{
	private $key = 'points_add';

	function input()
	{
		global $wpdb;
		$type = \LWS\WOOREWARDS\Core\Pool::POST_TYPE;
		$sql = "SELECT post_name, ID, post_title, meta_value as stack_id FROM $wpdb->posts";
		$sql .= " LEFT JOIN {$wpdb->postmeta} ON ID=post_id AND meta_key='wre_pool_point_stack'";
		$sql .= " WHERE post_type='$type' AND post_status NOT IN ('trash') ORDER BY menu_order ASC, post_title ASC";

		$pools = $wpdb->get_results($sql, OBJECT_K);

		$help = esc_attr(__("Enter '=42' to set a total of 42 points to selected users instead of adding it to current amounts.", 'woorewards-pro'));
		$options = '';
		$default = \get_option('lws_wr_default_pool_name', 'default');
		foreach( $pools as $pool )
		{
			$selected = $pool->post_name == $default ? ' selected' : '';
			$options .= "<option value='{$pool->ID}'$selected>".\apply_filters('the_title', $pool->post_title, $pool->ID)."</option>";
		}

		$str = "<label for='{$this->key}' title='$help'>" . __("Add/Subtract points", 'woorewards-pro') . "</label>";
		$str .= " <input type='text' pattern='[0-9]+' name='{$this->key}_value' size='4' class='lws-input lws-ignore-confirm'/>";
		$str .= "<select name='{$this->key}_pool' class='lac_select lws-ignore-confirm' data-mode='select'>$options</select>";
		$placeholder = esc_attr(__("Reason...", 'woorewards-pro'));
		$str .= "<input type='text' name='{$this->key}_reason' class='lws-input lws-ignore-confirm' placeholder='{$placeholder}'>";
		return $str;
	}

	function apply($user_ids)
	{
		$kvalue = $this->key . '_value';
		$kpool = $this->key . '_pool';
		$kreason = $this->key . '_reason';

		$points = isset($_POST[$kvalue]) ? trim($_POST[$kvalue]) : '';
		$set = false;
		if( substr($points, 0, 1) == '=' )
		{
			$points = substr($points, 1);
			$set = true;
		}
		if( !is_numeric($points) )
			return false;
		else if( $set )
			$points = max(0, $points);

		$poolId = isset($_POST[$kpool]) ? intval($_POST[$kpool]) : '';
		if( empty($poolId) )
			return false;

		// load the default pool
		$pools = \LWS\WOOREWARDS\Collections\Pools::instanciate()->load(array('p' => $poolId));

		if( empty($pool = $pools->get(0)) )
		{
			\lws_admin_add_notice_once('lws-wre-users-points-add', __("Cannot load the selected loyalty system.", 'woorewards-pro'), array('level'=>'error'));
			return false;
		}
		else
		{
				$count = 0;
				$reason = isset($_POST[$kreason]) ? trim($_POST[$kreason]) : '';
				if( empty($reason) )
					$reason = __("Commercial operation", 'woorewards-pro');
				$allUserCan = true;

				$points = $pool->reversePointsFormat($points);

				foreach( $user_ids as $user_id )
				{
					if( $set )
						$pool->setPoints($user_id, $points, $reason);
					else if( $points < 0 )
						$pool->usePoints($user_id, \absint($points), $reason);
					else
						$pool->addPoints($user_id, $points, $reason);

					if( !$pool->userCan($user_id) )
						$allUserCan = false;
					else
						$count += $pool->tryUnlock($user_id);
				}

				if( $set )
					$msg = _n("Points assigned to user.", "Points assigned to users.", count($user_ids), 'woorewards-pro');
				else if( $points < 0 )
					$msg = _n("Points substracted to user.", "Points substracted to users.", count($user_ids), 'woorewards-pro');
				else
					$msg = _n("Points added to user.", "Points added to users.", count($user_ids), 'woorewards-pro');

				if( $count > 0 )
					$msg .= '<br/>' . _n("A reward was generated.", "Few rewards generated.", $count, 'woorewards-pro');
				\lws_admin_add_notice_once('lws-wre-users-points-add', $msg, array('level'=>'success'));

				if( !$allUserCan )
					\lws_admin_add_notice_once('lws-wre-users-points-add-uc', __("Points changed for at least one user that doesn't have access the selected loyalty system.", 'woorewards-pro'), array('level'=>'info'));

				if( !$pool->isBuyable() )
					\lws_admin_add_notice_once('lws-wre-users-points-add-nb', __("The selected loyalty system is not active. Rewards cannot be unlocked.", 'woorewards-pro'), array('level'=>'info'));
		}
		return true;
	}
}