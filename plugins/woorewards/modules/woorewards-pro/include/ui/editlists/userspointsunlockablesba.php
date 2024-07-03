<?php
namespace LWS\WOOREWARDS\PRO\Ui\Editlists;

// don't call the file directly
if( !defined( 'ABSPATH' ) ) exit();

/** Bulk Action for UsersPoints Editlist.
 *	Redeem Unlockable to selected users.
 *	Respect Pool rules, redeem will fail if user does not fulfill requirements.
 *
 *	Redeem does not care about points amount.
 *	Spend no points for redeem. */
class UsersPointsUnlockablesBA extends \LWS\Adminpanel\EditList\Action
{
	private $key = 'u_redeem';

	function input()
	{
		$str = "<label for='{$this->key}' class='wr-user-unlockable-bulk redeem'>".__("Offer Reward", 'woorewards-pro')."</label>";
		$str .= " <input name='{$this->key}' class='lac_select lws-ignore-confirm' data-ajax='lws_woorewards_unlockable_list'>";
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
		$pool = false;
		if( $poolId = $u->getPoolId() )
		{
			if( $pool = \LWS\WOOREWARDS\PRO\Core\Pool::getOrLoad($poolId, false) )
				$u->setPool($pool);
		}
		if( !($pool && $pool->isBuyable()) )
			return __("The Loyalty System seems to not exist anymore or Rewards cannot be redeemed.", 'woorewards-pro');

		$reason = \LWS\WOOREWARDS\Core\Trace::byReason(array("Offer Reward %s", $u->getTitle()), 'woorewards-pro');
		$fail = \LWS\WOOREWARDS\Core\Trace::byReason(array("User does not fulfill requirement for Reward %s", $u->getTitle()), 'woorewards-pro');
		$reason->setOrigin('bulk_u_redeem');
		$fail->setOrigin('bulk_u_redeem');

		$c = 0;
		foreach( $userIds as $userId )
		{
			if( $user = \get_user_by('ID', $userId) )
			{
				if( $pool->userCan($user) )
				{
					$cost = $u->getUserCost($userId, 'edit');
					if( $pool->getOption('type') != \LWS\WOOREWARDS\Core\Pool::T_STANDARD )
					{
						$before = $pool->getPoints($userId);
						$cost = ($before < $cost ? ($cost - $before) : 0);
					}
					if( $cost > 0 )
						$pool->addPoints($userId, $cost, $reason);

					if( $pool->unlock($user, $u, true) )
						++$c;
					else if( $cost > 0 )
						$pool->usePoints($userId, $cost, $fail);
				}
			}
		}

		\do_action('lws_woorewards_bulk_action_unlockable_offered', $u, $userIds);

		$msg = _n("%d Reward given", "%d Rewards assigned", $c, 'woorewards-pro');
		\lws_admin_add_notice_once('lws-wre-users-reward-add', sprintf($msg, $c), array('level'=>'success'));
		return true;
	}

}
