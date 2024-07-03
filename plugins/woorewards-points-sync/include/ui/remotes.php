<?php
namespace LWS\WR_POINTS_SYNC\Ui;
// don't call the file directly
if( !defined( 'ABSPATH' ) ) exit();

/** Editlist source.
 *	List all other website to inform about points movements. */
class Remotes extends \LWS\Adminpanel\EditList\Source
{
	const ROW_ID = 'rid';
	const SLUG = 'lws-wr-remotse';

	function input()
	{
		$key = self::ROW_ID;
		$labels = array(
			'remote'   => __("Remote Site", 'wr-addon-points-sync'),
			'sharing'  => __("Sharing key", 'wr-addon-points-sync'),
			'stack'    => __("Target Point Reserve", 'wr-addon-points-sync'),
			'local'    => __("Local Settings", 'wr-addon-points-sync'),
			'observed' => __("Observed Point Reserve", 'wr-addon-points-sync'),
			'details'  => __("API", 'wr-addon-points-sync'),
			'url'      => __("URL", 'wr-addon-points-sync'),
			'auth'     => __("Auth.", 'wr-addon-points-sync'),
			'pass'     => __("Pass.", 'wr-addon-points-sync'),
			'query'    => __("Query Template", 'wr-addon-points-sync'),
			'sim_name' => __("Remote URL match the local one. You should avoid looping back on local site itself.", 'wr-addon-points-sync')
			. '<br />' . __("Please check the following box to confirm they really are separated websites", 'wr-addon-points-sync'),
		);
		$tooltips = array(
			'query' => sprintf(
				__('Available placeholders are %1$s, %2$s, %3$s and %4$s.', 'wr-addon-points-sync'),
				'[reserve]','[user_email]','[points]','[action]'
			),
		);
		$placeholder = array(
			'query' => \esc_attr(\LWS\WR_POINTS_SYNC\Core\Sync::DEFAULT_QUERY),
		);
		$myUrl = \esc_attr(\LWS_WooRewardsPointsSync::getSiteUrl());

		$sourceArgs = 'type="text"';
		if( \version_compare(LWS_WOOREWARDS_VERSION, '4.0.0', '>=') )
		{
			$sourceArgs = "class='lac_select' data-class='lws-wr-pool-pointstack' data-mode='select' data-ajax='lws_woorewards_pointstack_list'";
		}

		$str = <<<EOT
<div class='editlist-content-grid maxwidth'>
	<input type='hidden' name='{$key}'>
	<input type='hidden' name='origin'>
	<div class='fieldset'>
		<div class='title'>{$labels['remote']}</div>
		<div class='fieldset-grid'>
			<div class='label'>
				{$labels['sharing']}
			</div>
			<div class='input'>
				<input name='sharing' type='text' id='remote_sharing_key'>
			</div>
			<div class='label'>{$labels['stack']}</div>
			<div class='input'>
				<input name='stack' type='text'>
			</div>
		</div>
	</div>
	<div class='fieldset'>
		<div class='title'>{$labels['local']}</div>
		<div class='fieldset-grid'>
			<div class='label'>{$labels['observed']}</div>
			<div class='input'>
				<input name='source' {$sourceArgs}>
			</div>
		</div>
	</div>
	<div class='fieldset span2'>
		<div class='title'>{$labels['details']}</div>
		<div id='sim_name_warn' class='title' style='display: none; color: #c16310;'>
			{$labels['sim_name']}
			<input type='checkbox' name='sim_name' id='sim_name_confirm'>
			<input type='hidden' value='{$myUrl}' id='sim_name_my_url'>
		</div>
		<div class='fieldset-grid'>
			<div class='label'>{$labels['url']}</div>
			<div class='input'>
				<input name='url' type='text' size='100' id='remote_url'>
			</div>
			<div class='label'>{$labels['auth']}</div>
			<div class='input'>
				<input name='auth' type='text' id='remote_auth'>
			</div>
			<div class='label'>{$labels['pass']}</div>
			<div class='input'>
				<input name='pass' type='text' id='remote_pass'>
			</div>
			<div class='field-help'>{$tooltips['query']}</div>
			<div class='label'>{$labels['query']}<div class='bt-field-help'>?</div></div>
			<div class='input'>
				<input name='query' type='text' size='100' placeholder='{$placeholder['query']}'>
			</div>
		</div>
	</div>
</div>
EOT;
		return $str;
	}

	function labels()
	{
		return array(
			'url'    => __("Remote URL", 'wr-addon-points-sync'),
			'stack'  => __("Remote Reserve Target", 'wr-addon-points-sync'),
			'source' => __("Local Reserve Observed", 'wr-addon-points-sync'),
			'stats'  => __("Status", 'wr-addon-points-sync'),
		);
	}

	function sort($a, $b)
	{
		$c = \strcmp($a['url'], $b['url']);
		if( 0 == $c )
			$c = \strcmp($a['stack'], $b['stack']);
		return $c;
	}

	function read($limit)
	{
		$option = $this->getOption();
		\uasort($option, array($this, 'sort'));
		if( $limit )
			$option = \array_slice($option, $limit->offset, $limit->count);

		$statistics = $this->getStatistics();
		$warn = \absint(\get_option('lws_wr_pts_sync_warn_period', 7));
		if( $warn )
			$warn = \date_create()->sub(new \DateInterval("P{$warn}D"))->getTimestamp();

		foreach( $option as $key => &$opt )
		{
			$opt['origin'] = $opt[self::ROW_ID] = $key;
			$opt['stats'] = '';
			if( isset($statistics[$key]) )
			{
				$stats =& $statistics[$key];
				$success = $stats->success ? \date_create($stats->success)->getTimestamp() : 0;
				$failure = $stats->failure ? \date_create($stats->failure)->getTimestamp() : 0;

				$color = 'green';
				if( $success > $failure )
				{
					$color = 'green';
					if( $failure > $warn )
						$color = 'orange';
				}
				else
					$color = 'red';

				$style = implode(';', array(
					"background-color: {$color}",
					'width: 1em',
					'height: 1em',
					'cursor: default',
				));
				$args = implode(' ', array(
					sprintf("title='%s'", sprintf(__("Average count of attempt for a sync.: %.03f", 'wr-addon-points-sync'), $stats->attempts)),
					sprintf("data-avg-attempts='%.03f'", $stats->attempts),
					sprintf("data-last-success='%s'", $stats->success ? \date_create($stats->success)->format('Y-m-d H:i:s') : 'never'),
					sprintf("data-last-failure='%s'", $stats->failure ? \date_create($stats->failure)->format('Y-m-d H:i:s') : 'never'),
				));
				$opt['stats'] = "<div class='lws-icon-bg-circle' style='{$style};' {$args}>&nbsp;</div>";
			}
		}
		return $option;
	}

	protected function getStatistics()
	{
		$period = \absint(\get_option('lws_wr_pts_sync_stats_period', 31));
		if( $period )
		{
			$limit = sprintf(
				"WHERE DATE(`creation`) >= DATE('%s')",
				\date_create()->sub(new \DateInterval("P{$period}D"))->format('Y-m-d')
			);
		}

		global $wpdb;
		$sql = <<<EOT
SELECT `reference`, MAX(`last_success`) as `success`, MAX(`last_failure`) as `failure`, AVG(`sync_count`) as `attempts`
FROM {$wpdb->lwsWRPointsSync}
{$limit} GROUP BY `reference`
EOT;
		$stats = $wpdb->get_results($sql, OBJECT_K);
		return $stats ? $stats : array();
	}

	function write($row)
	{
		$values = \apply_filters('lws_adminpanel_arg_parse', array(
			'values'   => $row,
			'format'   => array(
				self::ROW_ID => 'k',
				'origin'     => 'k',
				'source'     => 'K',
				'stack'      => 'K',
				'url'        => 'T',
				'auth'       => 'T',
				'pass'       => 'T',
				'sharing'    => 't',
				'query'      => 't',
				'sim_name'   => 't',
			),
			'defaults' => array(
				self::ROW_ID => '',
				'origin'     => '',
				'sharing'    => '',
				'query'      => '',
				'sim_name'   => '',
			),
			'labels'   => array(
				'source'     => __("Observed Local Point Reserve", 'wr-addon-points-sync'),
				'stack'      => __("Remote Point Reserve", 'wr-addon-points-sync'),
				'url'        => __("Remote Site URL", 'wr-addon-points-sync'),
				'auth'       => __("Remote Auth. Value", 'wr-addon-points-sync'),
				'pass'       => __("Remote Pass. Value", 'wr-addon-points-sync'),
				'sharing'    => __("Sharing Key", 'wr-addon-points-sync'),
				'query'      => __("Query", 'wr-addon-points-sync'),
			)
		));
		if (!(isset($values['valid']) && $values['valid'])) {
			return isset($values['error']) ? new \WP_Error('400', $values['error']) : false;
		}
		$row = $values['values'];
		$row['url'] = \preg_replace('@^https?://@i', '', $row['url']);
		if( !$row['url'] )
			return new \WP_Error('400', __("Invalid URL (protocol part in not required)", 'wr-addon-points-sync'));

		$message = array();

		$myUrl = \LWS_WooRewardsPointsSync::getSiteUrl();
		if ((false !== strpos($myUrl, $row['url']) || false !== strpos($row['url'], $myUrl)) && !$row['sim_name']) {
			return new \WP_Error('400', __("Remote URL match the local one. You should avoid looping back on local site itself.", 'wr-addon-points-sync'));
		}

		$value = array_intersect_key($row, array(
			'source'     => true,
			'stack'      => true,
			'url'        => true,
			'auth'       => true,
			'pass'       => true,
			'sharing'    => true,
			'query'      => true,
		));
		$row[self::ROW_ID] = $row['origin'] = $this->updateOption($row[self::ROW_ID], $value);

		// ping remote but values are saved anyway
		$sync = new \LWS\WR_POINTS_SYNC\Core\Sync($row[self::ROW_ID], $value);
		$test = $sync->ping();
		if( false === $test )
		{
			$message[] = __("Failed to ping the remote website. Check URL or authentication settings.", 'wr-addon-points-sync');
			$message[] = __("Ensure WooRewards Points Synchronizer is running on the remote website too.", 'wr-addon-points-sync');
		}
		else if( true === $test )
			$message[] = __("Remote server ping succeed and settings seems Ok.", 'wr-addon-points-sync');
		else if( \is_array($test) )
			$message = \array_merge($message, $test);
		else if( \is_string($test) )
			$message[] = $test;

		return \LWS\Adminpanel\EditList\UpdateResult::ok($row, \implode("\n", $message));
	}

	function erase($row)
	{
		if( $row && isset($row[self::ROW_ID]) )
		{
			$this->updateOption($row[self::ROW_ID], false);
			global $wpdb;
			$wpdb->delete($wpdb->lwsWRPointsSync, array('reference' => $row[self::ROW_ID]));
			return true;
		}
		else
			return false;
	}

	public function total()
	{
		return count($this->getOption());
	}

	public function defaultValues()
	{
		return array(
			self::ROW_ID => '',
			'origin'     => '',
			'source'     => '',
			'stack'      => '',
			'url'        => '',
			'auth'       => '',
			'pass'       => '',
			'sharing'    => '',
			'query'      => \LWS\WR_POINTS_SYNC\Core\Sync::DEFAULT_QUERY,
		);
	}

	/** @return the new key
	 *	@param $key (string|false) if false, a new key is generated.
	 *	@param $remote (array|false) if false, the entry is remove from the option. */
	private function updateOption($key, $remote)
	{
		if( !$key )
			$key = \hash('md5', \time().print_r($remote, true)).'-'.\get_current_blog_id();

		$this->remotes = \get_option('lws_wr_pts_sync_remotes', array());
		if( !($this->remotes && \is_array($this->remotes)) )
			$this->remotes = array();

		if( false === $remote )
		{
			if( isset($this->remotes[$key]) )
				unset($this->remotes[$key]);
		}
		else
			$this->remotes[$key] = $remote;

		\update_option('lws_wr_pts_sync_remotes', $this->remotes);
		return $key;
	}

	private function getOption($key=false)
	{
		if( !isset($this->remotes) )
		{
			$this->remotes = \get_option('lws_wr_pts_sync_remotes', array());
			if( !($this->remotes && \is_array($this->remotes)) )
				$this->remotes = array();
		}
		if( false !== $key )
			return isset($this->remotes[$key]) ? $this->remotes[$key] : false;
		else
			return $this->remotes;
	}
}
