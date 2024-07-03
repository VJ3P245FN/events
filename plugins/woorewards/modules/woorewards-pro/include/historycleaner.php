<?php
namespace LWS\WOOREWARDS\PRO;

// don't call the file directly
if( !defined( 'ABSPATH' ) ) exit();

/** Conveniences Class to clean user history and rewards. */
class HistoryCleaner
{
	protected $singlePool = false;
	protected $pools = array();
	protected $userId = false;
	private $unlockables = null;
	private $events = null;

	/** @param $userId (false|int)
	 *	@param $sinlgePool (false|Pool instance) */
	function __construct($userId=false, $sinlgePool=false)
	{
		$this->userId = \intval($userId);
		if ($sinlgePool)
			$this->setPool($sinlgePool);
		else
			$this->setAllPools();
	}

	function getUserId()
	{
		return $this->userId;
	}

	function getPools()
	{
		return $this->pools;
	}

	function getSinglePool()
	{
		return $this->singlePool;
	}

	/** Clean history table.
	 *	@param $limit (false|\DateTime) clean up to this date (excluded) */
	function deleteLogs($limitDate=false)
	{
		global $wpdb;
		$request = \LWS\Adminpanel\Tools\Request::from($wpdb->lwsWooRewardsHistoric);
		if ($this->userId)
			$request->where("user_id = %d")->arg($this->userId);
		if ($this->singlePool)
			$request->where("stack = %s")->arg($this->singlePool->getStackId());
		if ($limitDate)
			$request->where("DATE(mvt_date) < DATE(%s)")->arg($limitDate->format('Y-m-d'));
		$request->delete();
	}

	function resetPoints()
	{
		global $wpdb;
		$reset = "UPDATE {$wpdb->usermeta} SET meta_value=0 WHERE ";
		if ($this->singlePool)
			$reset .= sprintf('meta_key = "%s"', \LWS\WOOREWARDS\Core\PointStack::MetaPrefix . \esc_sql($this->singlePool->getStackId()));
		else
			$reset .= sprintf('meta_key LIKE "%s%%"', \LWS\WOOREWARDS\Core\PointStack::MetaPrefix);
		$wpdb->query($reset . $this->getUserClause());
	}

	function removeRewards()
	{
		if ($this->pools) {
			$c = new \LWS\WOOREWARDS\PRO\Core\Confiscator();
			foreach ($this->pools as $p)
				$c->setByPool($p);
			if ($this->userId)
				$c->setUserFilter(array($this->userId));
			$c->revoke();
		}
	}

	function cleanOrders()
	{
		global $wpdb;
		if (\LWS\Adminpanel\Tools\Conveniences::isHPOS()) {
			$order = \LWS\Adminpanel\Tools\Request::from($wpdb->prefix . 'wc_orders_meta', 'pool');
			$order->innerJoin($wpdb->prefix . 'wc_orders', 'o', 'o.id=pool.order_id AND o.type="shop_order"');

			if ($this->userId) {
				// registered customer id or guest _billing_email
				$clause = sprintf('o.customer_id = %d', $this->userId);
				$user = \get_user_by('ID', $this->userId);
				if ($user && $user->ID) {
					$clause = sprintf('(%s OR o.billing_email = "%s")',$clause, \esc_sql($user->user_email));
				}
				$order->where($clause);
			}
		} else {
			$order = \LWS\Adminpanel\Tools\Request::from($wpdb->postmeta, 'pool');
			$order->innerJoin($wpdb->posts, 'o', 'o.ID=pool.post_id AND o.post_type="shop_order"');

			if ($this->userId) {
				// registered customer id or guest _billing_email
				$order->leftJoin($wpdb->postmeta, 'u', 'u.post_id=pool.post_id AND u.meta_key = "_customer_user"');
				$clause = sprintf('u.meta_value = %d', $this->userId);

				$user = \get_user_by('ID', $this->userId);
				if ($user && $user->ID) {
					$clause = sprintf(
						'(%s OR ((u.meta_value IS NULL OR u.meta_value="") AND m.meta_value = "%s"))',
						$clause, \esc_sql($user->user_email)
					);
					$order->leftJoin($wpdb->postmeta, 'm', 'm.post_id=pool.post_id AND m.meta_key = "_billing_email"');
				}

				$order->where($clause);
			}
		}

		// reset pool processed flag
		if ($this->singlePool) {
			$order->where(sprintf('pool.meta_key = "lws_woorewards_core_pool-%d"', (int)$this->singlePool->getId()), 'meta');
		} else {
			$order->where('pool.meta_key LIKE "lws_woorewards_core_pool-%"', 'meta');
		}
		$order->update(array('pool.meta_value' => false));

		// reset point refunding flag
		$order->where('pool.meta_key = "lws_woorewards_points_refunded"', 'meta'); // second arg allows to replace previous clause
		$order->update(array('pool.meta_value' => false));
	}

	function cleanMetas()
	{
		global $wpdb;
		$userClause = $this->getUserClause();

		if ($this->getUnlockables()) {
			// redeem counters
			$wpdb->query(sprintf(
				"UPDATE {$wpdb->usermeta} SET meta_value=0 WHERE meta_key IN (%s)",
				\implode(', ', $this->getMappedUnlockableIds('"lws_wr_redeemed_%d"'))
			) . $userClause);
		}

		if ($this->getEvents()) {
			// easteregg events
			$wpdb->query(sprintf(
				"DELETE FROM {$wpdb->usermeta} WHERE meta_key='lws_woorewards_easteregg' AND meta_value IN (%s)",
				\implode(', ', \array_keys($this->getEvents()))
			) . $userClause);
			// publish post
			if ($keys = $this->getMappedEventsIds('"lws_wre_event_post_%d"', 'lws_woorewards_pro_events_publishpost')) {
				$wpdb->query(sprintf(
					"DELETE FROM {$wpdb->usermeta} WHERE meta_key IN (%s)",
					\implode(', ', $keys)
				) . $userClause);
			}
			// publish comment
			if ($keys = $this->getMappedEventsIds('"lws_wre_event_comment_%d"', 'lws_woorewards_pro_events_postcomment')) {
				$wpdb->query(sprintf(
					"DELETE FROM {$wpdb->usermeta} WHERE meta_key IN (%s)",
					\implode(', ', $keys)
				) . $userClause);
			}
			// restricted visit
			if ($keys = $this->getMappedEventsIds('"lws_woorewards_pro_events_restrictedvisit-%d"', 'lws_woorewards_pro_events_restrictedvisit')) {
				$wpdb->query(sprintf(
					"DELETE FROM {$wpdb->usermeta} WHERE meta_key IN (%s)",
					\implode(', ', $keys)
				) . $userClause);
			}
		}
	}

	function getUnlockables($typeFilter=false)
	{
		if (null === $this->unlockables) {
			$this->unlockables = array();
			foreach ($this->pools as $p) {
				foreach ($p->getUnlockables()->asArray() as $u) {
					$this->unlockables[(int)$u->getId()] = $u;
				}
			}
		}
		if ($typeFilter) {
			return \array_filter($this->unlockables, function($u)use($typeFilter){
				return $u->getType() == $typeFilter;
			});
		} else {
			return $this->unlockables;
		}
	}

	function getEvents($typeFilter=false)
	{
		if (null === $this->events) {
			$this->events = array();
			foreach ($this->pools as $p) {
				foreach ($p->getEvents()->asArray() as $u) {
					$this->events[(int)$u->getId()] = $u;
				}
			}
		}
		if ($typeFilter) {
			return \array_filter($this->events, function($u)use($typeFilter){
				return $u->getType() == $typeFilter;
			});
		} else {
			return $this->events;
		}
	}

	private function setAllPools()
	{
		$this->pools = \LWS\WOOREWARDS\Collections\Pools::instanciate()->load()->asArray();
	}

	private function setPool(\LWS\WOOREWARDS\PRO\Core\Pool $pool)
	{
		$this->singlePool = $pool;
		$this->pools = array($pool);
	}

	/** @param $pattern (string) with a %d as id placeholder. */
	private function getMappedUnlockableIds($pattern, $typeFilter=false)
	{
		$us = $this->getUnlockables($typeFilter);
		if ($us) {
			return \array_map(function($id)use($pattern){
				return sprintf($pattern, $id);
			}, \array_keys($us));
		} else {
			return false;
		}
	}

	/** @param $pattern (string) with a %d as id placeholder. */
	private function getMappedEventsIds($pattern, $typeFilter=false)
	{
		$us = $this->getEvents($typeFilter);
		if ($us) {
			return \array_map(function($id)use($pattern){
				return sprintf($pattern, $id);
			}, \array_keys($us));
		} else {
			return false;
		}
	}

	private function getUserClause()
	{
		return ($this->userId ? sprintf(' AND user_id = %d', $this->userId) : '');
	}
}
