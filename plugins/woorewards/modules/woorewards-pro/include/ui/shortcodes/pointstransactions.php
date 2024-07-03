<?php
namespace LWS\WOOREWARDS\PRO\Ui\Shortcodes;

// don't call the file directly
if( !defined( 'ABSPATH' ) ) exit();

class PointsTransactions
{
	protected $dateFormat = 'Y-m-d';

	public static function install()
	{
		$me = new self();
		\add_shortcode('wr_transactional_points_expiration', array($me, 'pointsExpiry'));

		/** Admin */
		\add_filter('lws_woorewards_points_shortcodes', array($me, 'admin'));
		// Scripts
		\add_action('wp_enqueue_scripts', array($me, 'registerScripts'));
	}

	function registerScripts()
	{
		\wp_register_style('wr-transactional-points-expiration', LWS_WOOREWARDS_PRO_CSS . '/shortcodes/transactional-points-expiration.min.css', array(), LWS_WOOREWARDS_PRO_VERSION);
	}

	protected function enqueueScripts()
	{
		\wp_enqueue_style('wr-transactional-points-expiration');
	}

	public function admin($fields)
	{
		$fields['transaction'] = array(
			'id' => 'lws_woorewards_sc_transactionalpointsexpiration',
			'title' => __("Transactional Points expiration", 'woorewards-pro'),
			'type' => 'shortcode',
			'extra' => array(
				'shortcode' => '[wr_transactional_points_expiration system="set the name of your system here" force="true" columns=""]',
				'description' =>  __("This shortcode shows to customers when their points will expire.", 'woorewards-pro'),
				'options' => array(
					array(
						'option' => 'system',
						'desc' => __("The points and rewards system you want to display. You can find this value in <strong>MyRewards → Points and Rewards</strong>, in the <b>Shortcode Attribute</b> column.", 'woorewards-pro'),
					),
					array(
						'option' => 'force',
						'desc' => __("(Optional) If set, the points will be shown even if the user currently doesn’t have access to the points and rewards system.", 'woorewards-pro'),
					),
					array(
						'option' => 'columns',
						'desc' => sprintf(
							__('(Optional) The list of columns to display in: %1$s. Defaults is %2$s.', 'woorewards-pro'),
							'<i>system, points, expiry, reason, date</i>',
							'<i>points, expiry</i>'
						),
					),
					array(
						'option' => 'titles',
						'desc' => __('(Optional) The list of columns titles. If not provided, default titles are used. Set it empty for no header at all.', 'woorewards-pro'),
					),
					array(
						'option' => 'tiles',
						'desc' => sprintf(
							__('(Optional) This replaces the simple table by a tiles display. Value should be %1$s / %2$s. Default is %3$s.', 'woorewards-pro'),
							'<i>yes</i>',
							'<i>no</i>',
							'<i>tiles="no"</i>'
						),
					),
				),
				'flags' => array('current_user_id'),
			)
		);
		return $fields;
	}

	/** Show a table with history of points and expiration date.
	 * [wr_points_expiry system='' columns='']
	 * Optional arguments: @see \LWS\WOOREWARDS\PRO\Conveniences::getPoolsByArgs */
	public function pointsExpiry($atts=array(), $content='')
	{
		$atts = \LWS\Adminpanel\Tools\Conveniences::sanitizeAttr(\wp_parse_args($atts, array(
			'system' => '', 'columns' => false, 'tiles' => false
		)));
		$userId = (int)\apply_filters('lws_woorewards_shortcode_current_user_id', \get_current_user_id(), $atts, 'wr_transactional_points_expiration');
		if ($userId) {
			$pools = \apply_filters('lws_woorewards_get_pools_by_args', false, $atts);
			//~ if (!$pools)
				//~ $pools = \LWS_WooRewards_Pro::getLoadedPools();

			if ($pools && $pools->count()) {
				$logs = $this->getPointLogs($pools, $userId);
				if ($logs->logs) {
					$this->dateFormat = \get_option('date_format', 'Y-m-d');
					$atts['columns'] = $this->parseColumns($atts['columns'], $atts);
					$this->enqueueScripts();
					if (\LWS\Adminpanel\Tools\Conveniences::argIsTrue($atts['tiles']))
						$content = $this->getTiles($logs, $atts);
					else
						$content = $this->getTable($logs, $atts);
				}
			}
		}
		return $content;
	}

	protected function getTable($logs, $atts)
	{
		$content = '<table class="wr-table wr-transactional-points-expiration">';
		if (!isset($atts['titles']) || $atts['titles']) {
			$content .= '<thead><tr>';
			foreach ($atts['columns'] as $c => $label) {
				$content .= "<th class='wr-tpe-{$c}'>{$label}</th>";
			}
			$content .= '</tr></thead>';
		}

		$content .= '<tbody>';
		foreach ($logs->logs as $log) {
			$cells = $this->getCells($log, $atts['columns'], $logs);
			$content .= '<tr>';
			foreach ($cells as $c => $value) {
				$content .= sprintf('<td class="wr-tpe-%s">%s</td>', \esc_attr($c), $value);
			}
			$content .= '</tr>';
		}
		$content .= '</tbody></table>';
		return $content;
	}

	protected function getTiles($logs, $atts)
	{
		$content = '<div class="wr-transactional-points-expiration">';
		$head = (!isset($atts['titles']) || $atts['titles']);

		foreach ($logs->logs as $log) {
			$content .= '<table class="wr-table wr-tpe-tile"><tbody>';
			$cells = $this->getCells($log, $atts['columns'], $logs);

			foreach ($cells as $c => $value) {
				$content .= '<tr>';
				if ($head) {
					$content .= sprintf('<th class="wr-tpe-%s">%s</th>', \esc_attr($c), $atts['columns'][$c]);
				}
				$content .= sprintf('<td class="wr-tpe-%s">%s</td>', \esc_attr($c), $value);
				$content .= '</tr>';
			}
			$content .= '</tbody></table>';
		}
		$content .= '</div>';
		return $content;
	}

	protected function getCells($log, $columns, $data)
	{
		$cells = array();
		foreach ($columns as $c => $label) {
			switch($c) {
				case 'points':
					$cells[$c] = sprintf('<div style="white-space: nowrap;">%s</div>', $this->getFormattedPoints($log, $data->pools));
					break;
				case 'expiry':
					$d = $this->getExpirationDate($log, $data->periods);
					$cells[$c] = $d ? \date_i18n($this->dateFormat, $d->getTimestamp() + $d->getOffset()) : _x('Never', 'points transactional expiry', 'woorewards-pro');
					break;
				case 'reason':
					$reason = \maybe_unserialize($log->commentar);
					$cells[$c] = ($reason && \is_array($reason)) ? \LWS\WOOREWARDS\Core\Trace::reasonToString($reason, true) : $reason;
					break;
				case 'date':
					$cells[$c] = \date_i18n($this->dateFormat, $log->mvt_date->getTimestamp() + $log->mvt_date->getOffset());
					break;
				case 'system':
					$cells[$c] = isset($data->names[$log->stack]) ? $data->names[$log->stack] : '&nbsp;';
					break;
				default:
					$cells[$c] = \apply_filters('wr_transactional_points_expiration_custom_cell', '&nbsp;', $log, $c);
				}
		}
		return $cells;
	}

	protected function getFormattedPoints($log, $pools)
	{
		$name = isset($pools[$log->stack]) ? reset($pools[$log->stack])->getName() : false;
		return \LWS_WooRewards::formatPointsWithSymbol($log->points_moved, $name);
	}

	/** @return false|\DateTime */
	protected function getExpirationDate($log, $periods)
	{
		$date = false;
		if (!isset($periods[$log->stack]))
			return false;

		$tz = \wp_timezone();
		foreach ($periods[$log->stack] as $period) {
			if (!($period && \is_array($period)) || $period['period']->isNull())
				continue;

			$expire = false;
			if ($period['date']) {
				$expire = clone $period['date'];
				$expire->setTimeZone($tz);
			} else {
				$ref = clone $log->mvt_date;
				$expire = $ref->add($period['period']->toInterval());
			}

			if ($expire && (!$date || $date > $expire))
				$date = $expire;
		}
		return $date;
	}

	protected function getPointLogs($pools, $userId)
	{
		$result = (object)array(
			'pools'  => array(),
			'periods' => array(),
			'names'   => array(),
			'logs'    => false,
		);
		// get pool/stack information
		foreach ($pools->asArray() as $pool) {
			$transac = $pool->getOption('transactional_expiry');
			if ($transac && !$transac['period']->isNull()) {
				$sId = $pool->getStackId();
				$pId = $pool->getId();
				$result->periods[$sId][$pId] = $transac;
				$result->pools[$sId][$pId] = $pool;
				if (!(isset($result->names[$sId]) && $result->names[$sId]))
					$result->names[$sId] = $pool->getOption('display_title');
			}
		}
		if (!$result->pools)
			return $result;

		// get not expired points
		global $wpdb;
		$floors = $this->getFloors($userId, $result->periods);
		$stacks = array(
			'condition' => 'OR',
		);
		foreach ($floors as $stack => $floor) {
			if ($floor) {
				$stacks[] = array(
					'condition' => 'AND',
					sprintf("`stack` = '%s'", \esc_sql($stack)),
					sprintf("`mvt_date` > '%s'", $floor->format('Y-m-d H:i:s')),
				);
			} else {
				$stacks[] = sprintf("`stack` = '%s'", \esc_sql($stack));
			}
		}
		$query = \LWS\Adminpanel\Tools\Request::from($wpdb->lwsWooRewardsHistoric);
		$query->select('*');
		$query->where(array(
			sprintf('`blog_id` = %d', (int)\get_current_blog_id()),
			sprintf('`user_id` = %d', (int)$userId),
			'`points_moved` IS NOT NULL',
			'`points_moved` > 0', // gain
			$stacks,
		));
		$query->order(array('mvt_date ASC, id ASC'));

		$result->logs = $query->getResults();
		if ($result->logs) {
			$tz = \wp_timezone();
			foreach ($result->logs as &$log) {
				$log->mvt_date = \date_create($log->mvt_date);
				$log->mvt_date->setTimeZone($tz);
			}
			$result->logs = $this->filterConsumed($result->logs, $floors, $userId);
		}
		if (!$result->logs)
			$result->logs = array();
		return $result;
	}

	protected function getFloors($userId, $periods)
	{
		$floors = array();
		if (!$periods) {
			return $floors;
		} else {
			foreach ($periods as $stack => $local) {
				$floors[$stack] = false;
			}
		}

		// look for expiry check exact date in logs
		$userId = (int)$userId;
		$stacks = \implode("', '", \array_map('\esc_sql', \array_keys($periods)));
		global $wpdb;
		$sql = <<<EOT
SELECT `stack`, MAX(mvt_date) as `floor` FROM {$wpdb->lwsWooRewardsHistoric}
WHERE `origin` LIKE 'trans_expiry_%' AND `user_id`={$userId}
AND `stack` IN ('{$stacks}')
GROUP BY `stack`
EOT;
		$expiries = $wpdb->get_results($sql, OBJECT_K); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPressDotOrg.sniffs.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQL.NotPrepared
		if ($expiries) {
			foreach ($expiries as $stack => $date) {
				$floors[$stack] = \date_create($date->floor);
			}
		}

		// go back by expiry period to get floor
		foreach ($periods as $stack => $local) {
			$floor = false;
			foreach ($local as $poolId => $period) {
				if ($period['date']) {
					$ref = clone($period['date']); // theorically next planned expiry
				} elseif ($floors[$stack]) {
					$ref = clone($floors[$stack]); // last expiry trigger
				} else {
					$ref = \date_create()->setTime(0,0); // today, this morning
				}
				$date = $ref->sub($period['period']->toInterval());
				if (!$floor || $floor < $date)
					$floor = $date;
			}
			$floors[$stack] = $floor;
		}

		return $floors;
	}

	/** @param $logs (array) order by date ASC. */
	protected function filterConsumed($logs, $floors, $userId)
	{
		// first, read used points since
		global $wpdb;
		$stacks = array(
			'condition' => 'OR',
		);
		foreach ($floors as $stack => $floor) {
			if ($floor) {
				$stacks[] = array(
					'condition' => 'AND',
					sprintf("`stack` = '%s'", \esc_sql($stack)),
					sprintf("`mvt_date` > '%s'", $floor->format('Y-m-d H:i:s')),
				);
			} else {
				$stacks[] = sprintf("`stack` = '%s'", \esc_sql($stack));
			}
		}
		$query = \LWS\Adminpanel\Tools\Request::from($wpdb->lwsWooRewardsHistoric);
		$query->select('`stack`, -SUM(`points_moved`) as `used`');
		$query->group('`stack`');
		$query->where(array(
			sprintf('`blog_id` = %d', (int)\get_current_blog_id()),
			sprintf('`user_id` = %d', (int)$userId),
			'`points_moved` IS NOT NULL',
			'`points_moved` < 0', // used
			"`origin` NOT LIKE 'trans_expiry_%'",
			$stacks,
		));

		$used = $query->getResults(OBJECT_K);
		if (!$used)
			return $logs;

		foreach ($floors as $stack => $floor) {
			if (!isset($used[$stack]))
				$used[$stack] = (object)array('stack' => $stack, 'used' => 0);
		}

		// from last, go up removing row until all consuming is done
		foreach (array_keys($logs) as $index) {
			$row =& $logs[$index];
			$points = $used[$row->stack]->used;
			if ($points > 0) {
				$rest = $row->points_moved - $points;
				if ($rest > 0) {
					$row->points_moved = $rest; // partially consumed
					$used[$row->stack]->used = 0;
				} else {
					$used[$row->stack]->used -= $row->points_moved;
					$logs[$index] = false; // totally consumed
				}
			}
		}

		// remove used rows
		return array_filter($logs);
	}

	protected function parseColumns($columns, $atts=array())
	{
		if (!$columns) {
			$columns = array('points', 'expiry');
		} else {
			$columns = array_map('trim', explode(',', $columns));
			$columns = array_map('strtolower', $columns);
		}
		$labels = array();
		foreach ($columns as $c) {
			switch($c) {
				case 'points':
					$labels['points'] = _x('Points', 'points transactional expiry', 'woorewards-pro');
					break;
				case 'expiry':
					$labels['expiry'] = _x('Expiry', 'points transactional expiry', 'woorewards-pro');
					break;
				case 'reason':
					$labels['reason'] = _x('Reason', 'points transactional expiry', 'woorewards-pro');
					break;
				case 'date':
					$labels['date'] = _x('Obtained', 'points transactional expiry', 'woorewards-pro');
					break;
				case 'system':
					$labels['system'] = _x('Loyalty System', 'points transactional expiry', 'woorewards-pro');
					break;
				default:
					$labels[$c] = $c;
					break;
			}
		}
		if (!$labels) {
			$labels = array(
				'points' => _x('Points', 'points transactional expiry', 'woorewards-pro'),
				'expiry' => _x('Expiry', 'points transactional expiry', 'woorewards-pro'),
			);
		} elseif (isset($atts['titles']) && $atts['titles']) {
			$titles = \array_map('trim', explode(',', $atts['titles']));
			foreach ($labels as $c => $label) {
				$labels[$c] = $titles ? \array_shift($titles) : '';
			}
		}
		return $labels;
	}
}