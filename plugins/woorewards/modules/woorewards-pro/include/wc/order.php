<?php
namespace LWS\WOOREWARDS\PRO\WC;

// don't call the file directly
if( !defined( 'ABSPATH' ) ) exit();

/** Refund points at order refund. */
class Order
{
  const ACTION_REMOVE_POINTS  = 'lws_wr_remove_points';
	const ACTION_RESULT = 'lws_wr_bulk_counts';

  private $points = 0;
  private $processed = false;
  private $checked = 0;

	static function install()
	{
		$me = new self();
		$orderStatuses = \get_option('lws_woorewards_refund_on_status');
		if( $orderStatuses && is_array($orderStatuses) )
		{
			foreach( array_unique($orderStatuses) as $status )
				\add_action('woocommerce_order_status_'.$status, array($me, 'refund'), 998, 2); // priority late to let someone change amount and wc to save order

			$status = \apply_filters('lws_woorewards_order_events', array('processing', 'completed'));
			foreach (array_unique($status) as $s)
				\add_action('woocommerce_order_status_' . $s, array($me, 'unrefund'), 100, 2);
		}

		// bulk action
		$screens = array('woocommerce_page_wc-orders', 'edit-shop_order');
		foreach ($screens as $screen) {
			\add_filter('bulk_actions-' . $screen, array($me, 'addActions'), 901, 1);
			\add_filter('handle_bulk_actions-' . $screen, array($me, 'handleActions'), 10, 3);
		}
		\add_action('admin_notices', array($me, 'notice'));

		\add_filter('lws_woorewards_orderbulk_action_process_points_label', function($label) {
			return __("Process WooRewards Points", 'woorewards-pro');
		}); // rename
	}

  public function addActions($actions)
  {
		$actions[self::ACTION_REMOVE_POINTS]  = __("Remove WooRewards Points", 'woorewards-pro');
    return $actions;
  }

  public function handleActions($redirectTo, $action, $postIds)
  {
    $this->resetCounters();
    if (self::ACTION_REMOVE_POINTS === $action) {
      // as a refund past orders
      foreach ($postIds as $postId) {
        $order = \wc_get_order($postId);
        if ($order) {
          $this->refund($postId, $order);
          $this->checked++;
        }
      }

      $redirectTo = \add_query_arg(array(
      	self::ACTION_RESULT => \implode('_', array(-$this->checked, count($this->processed), $this->points)),
      ), $redirectTo);
    }
		$this->processed = false;
    return $redirectTo;
  }

  public function addProcessed($orderId)
  {
		if (false !== $this->processed)
    	$this->processed[$orderId] = true;
  }

  public function subPoints($value)
  {
		try{
    	$this->points += $value;
		} catch(\Exception $e){
			$this->points = PHP_INT_MAX ; // overflow
		}
  }

  protected function resetCounters()
  {
    $this->points    = 0;
    $this->processed = array();
    $this->checked   = 0;
  }

  public function notice()
  {
    $count_safe = isset($_REQUEST[self::ACTION_RESULT]) ? \sanitize_key($_REQUEST[self::ACTION_RESULT]) : false;
    if (false !== $count_safe) {
      $counts = \array_map('\intval', \explode('_', $count_safe));
      while (count($counts) < 3)
        $counts[] = 0;
      list($checked, $processed, $points) = $counts;

			if ($checked < 0) {
				$content = sprintf(
					__("<b>%d</b> orders verified, including <b>%d</b> reinitialized for <b>%d</b> points substracted.", 'woorewards-pro'),
					\absint($checked), $processed, $points
				);
				echo "<div class='notice notice-success lws-wr-order-bulk-action'><p>{$content}</p></div>";
			}
    }
  }

	/** in case order processed again, let be refundable again */
	function unrefund($orderId, $order)
	{
		if ($order && $order->get_meta('lws_woorewards_points_refunded', true)) {
			$order->update_meta_data('lws_woorewards_points_refunded', false);
			$order->save_meta_data();
		}
	}

	function refund($orderId, $order)
	{
		if ($order) {
			if (!$order->get_meta('lws_woorewards_points_refunded', true)) {
				$logs = \LWS\WOOREWARDS\Core\PointStack::queryTrace(array(
					'order_id' => $orderId,
					'blog_id'  => \get_current_blog_id(),
				));
				$this->addProcessed($orderId);

				global $wpdb;
				// remove order processed flag
				if (\apply_filters('lws_woorewards_unflag_refunded_order', true, $order, $logs)) {
					$del = \array_filter(\wp_list_pluck($order->get_meta_data(), 'key'), function($k) {
						$flag = 'lws_woorewards_core_pool-';
						return $flag == \substr($k, 0, \strlen($flag));
					});
					foreach ($del as $metaKey) {
						$order->delete_meta_data($metaKey);
					}
					// order meta saved below
				}

				$sort = array();
				$stacks = array();
				if ($logs) {
					// sum per user, stack, origin, origin2, blog
					foreach ($logs as $log) {
						if ($log->move) {
							$key = $this->getLogKey($log);
							if (isset($sort[$key])) {
								$sort[$key]['ids'][] = $log->trace_id;
								$sort[$key]['points'] += $log->move;
							} else {
								$sort[$key] = array(
									'ids'      => array($log->trace_id),
									'points'   => $log->move,
									'user'     => $log->user_id,
									'stack'    => $log->stack,
									'origin'   => $log->origin,
									'provider' => $log->provider_id,
									'blog'     => $log->blog_id,
								);
							}
							$stacks[$log->stack][$log->user_id] = 0;
						}
					}
				}
				// save
				$order->update_meta_data('lws_woorewards_points_refunded', array('timestamp' => \time(), 'logs' => \array_values($sort)));
				$order->save_meta_data();

				if ($sort) {
					// refund
					$collection = new \LWS\WOOREWARDS\Collections\PointStacks();
					foreach ($sort as $item) {
						$stack  = $item['stack'];
						$user   = $item['user'];
						$points = $item['points'];

						if ($points) {
							$this->subPoints($points);
							$stacks[$stack][$user] += $points;

							$reason = \LWS\WOOREWARDS\Core\trace::byOrder($order)
								->setReason(array('Refund Order #%s', $order->get_order_number()), 'woorewards-pro')
								->setOrigin($item['origin'])
								->setProvider($item['provider'])
								->setBlog($item['blog']);
							$manager = $collection->create($stack, $user);//new \LWS\WOOREWARDS\Core\PointStack($stack, $userId);
							$manager->sub($points, $reason);
						}
					}
				}

				// let a note in order
				foreach ($stacks as $stack => $value) {
					foreach ($value as $userId => $points) {
						if ($points) {
							\LWS\WOOREWARDS\Core\OrderNote::add($order, sprintf(
								__('<b>%1$s</b> removed in <i>%4$s</i> from customer <i>[%2$d]</i> since order passed to <i>%3$s</i>.', 'woorewards-pro'),
								\LWS_WooRewards::formatPointsWithSymbol($points, ''),
								$userId,
								$order->get_status(),
								$stack
							), $stack);
						}
					}
				}

				\do_action('lws_woorewards_points_refunded', $orderId, $order, $sort);
			}
		}
	}

	/** @return (string) a key built on user, stack, origin, origin2, blog. */
	private function getLogKey($log) {
		return implode('|', array(
			$log->user_id,
			$log->stack,
			$log->origin,
			$log->provider_id,
			$log->blog_id,
		));
	}

	/** Never call, only to have poedit/wpml able to extract the sentance. */
	private function poeditDeclare()
	{
		__("Refund Order #%s", 'woorewards-pro');
	}
}
