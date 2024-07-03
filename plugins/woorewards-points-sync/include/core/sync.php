<?php
namespace LWS\WR_POINTS_SYNC\Core;
// don't call the file directly
if( !defined( 'ABSPATH' ) ) exit();

/** Call other website to update points. */
class Sync
{
	private $key = '';
	private $url = '';
	private $usr = '';
	private $pwd = '';
	private $query = '';
	private $stack = '';
	private $source = '';

	const DEFAULT_QUERY = '/wp-json/woorewards/v1/pointsync/sync/[reserve]/[user_email]/[points]/[action]';

	static function install()
	{
		$remotes = \get_option('lws_wr_pts_sync_remotes', array());
		if( $remotes && \is_array($remotes) )
		{
			foreach( $remotes as $key => $remote )
			{
				$me = new self($key, $remote);
				$me->hook();
			}
		}
	}

	function __construct(string $key, array $settings)
	{
		$this->key    = $key;
		$this->url    = $settings['url'];
		$this->usr    = $settings['auth'];
		$this->pwd    = $settings['pass'];
		$this->query  = $settings['query'];
		$this->stack  = $settings['stack'];
		$this->source = $settings['source'];

		if( !$this->query )
			$this->query = self::DEFAULT_QUERY;
	}

	function hook()
	{
		\add_filter('lws_woorewards_core_pool_point_add', array($this, 'listenAdd'), 10, 5);
		\add_filter('lws_woorewards_core_pool_point_set', array($this, 'listenSet'), 10, 4);
		\add_filter('lws_woorewards_core_pool_point_sub', array($this, 'listenSub'), 10, 5);
		\add_action('shutdown', array($this, 'process'));
	}

	/// (int)$value, (int)$userId, (string)$reason, (Pool)$pool, (Event)$origin
	function listenAdd($value, $userId, $reason, $pool, $origin)
	{
		if( '' == $this->source || $pool->getStackId() == $this->source )
			$this->enqueueMove('add', $value, $userId, $reason, $origin ? $origin->getId() : '');
		return $value;
	}

	/// (int)$value, (int)$userId, (string)$reason, (Pool)$pool, (Event)$origin
	function listenSub($value, $userId, $reason, $pool, $origin)
	{
		if( '' == $this->source || $pool->getStackId() == $this->source )
			$this->enqueueMove('sub', $value, $userId, $reason, $origin ? $origin->getId() : '');
		return $value;
	}

	/// (int)$value, (int)$userId, (string)$reason, (Pool)$pool
	function listenSet($value, $userId, $reason, $pool)
	{
		if( '' == $this->source || $pool->getStackId() == $this->source )
			$this->enqueueMove('set', $value, $userId, $reason, '');
		return $value;
	}

	private function getUrl($query='')
	{
		static $protocol = false;
		if( false === $protocol )
			$protocol = \apply_filters('lws_wr_points_sync_protocol', 'https://', $this->url, $this->stack, $query);
		return $protocol . rtrim($this->url, '/') . '/' . ltrim($query, '/');
	}

	private function doPlaceholders($query, $email='', $points='', $action='')
	{
		$query = \str_replace(
			array(
				'[reserve]',
				'[user_email]',
				'[points]',
				'[action]',
			),
			array(
				$this->stack,
				$email,
				$points,
				$action,
			),
			$query
		);
		return $query;
	}

	protected function enqueueMove($action, $value, $userId, $reason, $origin)
	{
		if( is_a($reason, '\LWS\WOOREWARDS\Core\Trace') )
			$reason = $reason;
		else if( is_array($reason) )
			$reason = new \LWS\WOOREWARDS\Core\Trace($reason);
		else
			$reason = \LWS\WOOREWARDS\Core\Trace::byReason($reason);

		$values = array(
			'reference' => $this->key,
			'user_id'   => $userId,
			'reason'    => $reason->reason,
			'points'    => $value,
			'action'    => $action,
			'origin'    => $origin,
		);

		global $wpdb;
		$sql = <<<EOT
INSERT INTO {$wpdb->lwsWRPointsSync}
(`reference`, `user_id`, `reason`, `points`, `action`, `origin`)
VALUES (%s, %d, %s, %d, %s, %s)
EOT;
		$affected = $wpdb->query($wpdb->prepare($sql, $values));
		if( !$affected )
			error_log("Cannot save point movement for synchronisation. Please check your database.");
	}

	/** Call remote REST API for sync points moves */
	function process()
	{
		global $wpdb;
		$sql = <<<EOT
SELECT * FROM {$wpdb->lwsWRPointsSync}
WHERE `reference` = %s
AND `status` <> 'done'
AND `sync_count` < %d
AND (`last_attempt` IS NULL OR (UNIX_TIMESTAMP(`last_attempt`) + %d) < UNIX_TIMESTAMP())
EOT;
		$maxAttempts = \max(1, (int)\get_option('lws_wr_pts_sync_max_attemps', 10));
		$minDelay = \max(60, (int)\get_option('lws_wr_pts_sync_min_delay', 180));

		$waiting = $wpdb->get_results($wpdb->prepare($sql, $this->key, $maxAttempts, $minDelay));
		if( !$waiting )
			return;

		$flag = "UPDATE {$wpdb->lwsWRPointsSync} SET `last_attempt`=CURRENT_TIMESTAMP() WHERE `id`=%d";
		foreach( $waiting as $sync )
		{
			$wpdb->query($wpdb->prepare($flag, $sync->id));

			$code = $this->send($sync);
			$success = ('s' == \substr($code, 0, 1));
			$lastDate = ($success ? 'last_success' : 'last_failure');

			$wpdb->update($wpdb->lwsWRPointsSync, array(
				'status'      => $success ? 'done' : 'failed',
				'sync_status' => $code,
				'sync_count'  => (1 + $sync->sync_count),
				 $lastDate    => \date('Y-m-d H:i:s'),
			), array(
				'id' => $sync->id,
			), array(
				'%s',
				'%s',
				'%d',
				'%s',
			), '%d');
		}
	}

	/** @return (string|false) the email or false if user does not exists */
	protected function getUserEmail($userId)
	{
		static $cache = array();
		if( isset($cache[$userId]) )
			return $cache[$userId];
		$user = \get_user_by('ID', $userId);
		if( $user && $user->ID )
			return ($cache[$userId] = $user->user_email);
		else
			return ($cache[$userId] = false);
	}

	/** @see https://stackoverflow.com/questions/23809392/what-does-phps-curlopt-userpwd-do
	 * @param $data (array) posted as field_name => value @see http_build_query() */
	function send($data)
	{
		$email = $this->getUserEmail($data->user_id);
		if( !$email )
			return 'e604'; // a user is not supposed to disappear

		$url = $this->getUrl($this->doPlaceholders($this->query, $email, $data->points, $data->action));
		$post = array(
			'source'         => \LWS_WooRewardsPointsSync::getOrCreateKeyCouple()[0],
			'transaction_id' => $data->id,
			'reason'         => $data->reason,
		);

		$ch = \curl_init();
		\curl_setopt_array($ch, array(
			CURLOPT_URL            => $url,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_CUSTOMREQUEST  => 'POST',
			CURLOPT_HTTPAUTH       => CURLAUTH_BASIC,
			CURLOPT_USERPWD        => ($this->usr . ':' . $this->pwd),
			CURLOPT_POST           => true,
			CURLOPT_POSTFIELDS     => \http_build_query($post),
		));

		$response = \curl_exec($ch);
		if( $response === false )
		{
			/// @see https://curl.se/libcurl/c/libcurl-errors.html
			$num = \curl_errno($ch);
			$err = \curl_error($ch);
			\curl_close($ch);
			error_log("Point Sync to {$url} failed with cUrl error [{$num}] {$err}");
			return ('c' . $num);
		}
		else
		{
			\curl_close($ch);
			$json = $response ? json_decode($response, true) : false;

			if( !($json && isset($json['status']) && isset($json['status_code'])) )
			{
				error_log("Remote server response to Point Sync uses a bad format.\nAPI: {$url}\nResponse: ".print_r($response, true));
				return 'e406';
			}

			$code = \strtolower($json['status_code']);
			if( 'success' == \strtolower($json['status']) && 's' != \substr($code, 0, 1) )
				$code = ('s'.$code);

			return $code;
		}

		return 'e500';
	}

	/** Connexion tester
	 *	@return bool|string success/failure or an error describing the problem. */
	function ping()
	{
		$query = '/wp-json/woorewards/v1/pointsync/ping/[reserve]';
		$url = $this->getUrl($this->doPlaceholders($query));
		$url = \apply_filters('lws_wr_points_sync_ping_url', $url, $this->url, $this->stack, $query);

		$ch = \curl_init();
		\curl_setopt_array($ch, array(
			CURLOPT_URL            => $url,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_CUSTOMREQUEST  => 'GET',
			CURLOPT_HTTPAUTH       => CURLAUTH_BASIC,
			CURLOPT_USERPWD        => ($this->usr . ':' . $this->pwd),
		));

		$response = \curl_exec($ch);
		if( $response === false )
		{
			$err = \curl_error($ch);
			\curl_close($ch);
			error_log("Ping to {$url} failed.");
			return sprintf(__("Ping to remote API failed: %s", 'wr-addon-points-sync'), $err);
		}
		else
		{
			\curl_close($ch);
			$message = array();
			$json = $response ? json_decode($response, true) : false;

			if( !($json && \is_array($json) && isset($json['sync']) && 'yes' == $json['sync']) )
			{
				$message[] = __("Ping to remote server is Ok but API response is not valid.", 'wr-addon-points-sync');
				$message[] = __("Ensure WooRewards Points Synchronizer is running on the remote website too.", 'wr-addon-points-sync');
				error_log("Ping to {$url} failed with response:\n".print_r($response, true));
			}
			else
			{
				if( !(isset($json['auth']) && 'yes' == $json['auth']) )
				{
					$message[] = __("Ping to remote server is Ok but authentification failed.", 'wr-addon-points-sync');
				}

				if( !(isset($json['pool']) && $json['pool']) )
				{
					$message[] = __("Ping to remote server is Ok but no remote Loyalty System uses the given Point Reserve.", 'wr-addon-points-sync');
				}
			}
			if( $message )
				return $message;
		}

		return true;
	}
}
