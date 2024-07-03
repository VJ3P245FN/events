<?php
namespace LWS\WOOREWARDS\PRO\Ui\Editlists;

// don't call the file directly
if( !defined( 'ABSPATH' ) ) exit();

/** Bulk Action for UsersPoints Editlist.
 *	Confiscate Unlockable from selected users.
 *
 *	Does not refund points. */
class UsersPointsConfiscationBA extends \LWS\Adminpanel\EditList\Action
{
	private $key = 'u_revoke';

	function input()
	{
		$str = "<label for='{$this->key}' class='wr-user-unlockable-bulk revoke'>".__("Confiscate Reward", 'woorewards-pro')."</label>";
		$str .= " <input name='{$this->key}' class='lac_select lws-ignore-confirm' data-ajax='lws_woorewards_unlockable_list'/>";
		return $str;
	}

	function apply($userIds)
	{
		$uId = isset($_POST[$this->key]) ? \absint($_POST[$this->key]) : '';
		if( !$uId	)
			return __("Please select a Reward", 'woorewards-pro');

		$u = \LWS\WOOREWARDS\Collections\Unlockables::instanciate()->load(array('p'=>$uId))->last();
		if( !$u )
			return __("The Reward seems to not exist anymore", 'woorewards-pro');

		if( 'lws_woorewards_pro_unlockables_pointgenerator' == $u->getType() )
			return __("Due to its type, this Reward cannot be confiscated", 'woorewards-pro');

		if( $poolId = $u->getPoolId() )
		{
			if( $pool = \LWS\WOOREWARDS\PRO\Core\Pool::getOrLoad($poolId, false) )
				$u->setPool($pool);
		}

		$c = new \LWS\WOOREWARDS\PRO\Core\Confiscator();
		$c->addRef($u);
		$c->setUserFilter($userIds);
		$c->revoke();
		\do_action('lws_woorewards_bulk_action_unlockable_revoked', $u, $userIds);

		$msg = __("Rewards confiscated done", 'woorewards-pro');
		\lws_admin_add_notice_once('lws-wre-users-rewards-rem', $msg, array('level'=>'success'));
		return true;
	}

}
