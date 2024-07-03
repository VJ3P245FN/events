<?php
namespace LWS\WR_POINTS_SYNC\Core;
// don't call the file directly
if( !defined( 'ABSPATH' ) ) exit();

/** Listen for points updates from remote websites. */
class API
{
	const PREFIX = 'woorewards/';
	const VERSION = 'v1';

	static function getNamespace()
	{
		return static::PREFIX . static::VERSION;
	}

	static function install()
	{
		$me = new self();
		\add_action('rest_api_init', array($me, 'registerRoutes'));
	}

	function registerRoutes()
	{
		$regex = array(
			'stack'  => '[a-zA-Z0-9_-]+',
			'email'  => '\S+\@\S+',
			'points' => '[0-9]+',
			'action' => 'add|sub|set',
		);

		\register_rest_route(
			self::getNamespace(),
			sprintf('/pointsync/sync/(?P<reserve>%s)/(?P<email>%s)/(?P<points>%s)/(?P<action>%s)', $regex['stack'], $regex['email'], $regex['points'], $regex['action']),
			array(
				'methods'  => 'POST',
				'callback' => array($this, 'getSync'),
				'permission_callback' => array($this, 'hasPermission'),
				'args'     => array(
					'reserve' => array(
						'required' => true,
						'sanitize_callback' => 'sanitize_key',
						'validate_callback' => function($param, $request, $key) {return true;},
					),
					'email' => array(
						'required' => true,
						'sanitize_callback' => 'sanitize_email',
						'validate_callback' => function($param, $request, $key) {return \is_email($param);},
					),
					'points' => array(
						'required' => true,
						'sanitize_callback' => 'absint',
						'validate_callback' => function($param, $request, $key) {return true;},
					),
					'action' => array(
						'required' => true,
						'sanitize_callback' => function($param, $request, $key) {return \strtolower($param);},
						'validate_callback' => function($param, $request, $key) {return \in_array($param, array('add', 'sub', 'set'));},
					),
				)
			)
		);

		\register_rest_route(
			self::getNamespace(),
			sprintf('/pointsync/ping/(?P<reserve>%s)', $regex['stack']),
			array(
				'methods'  => 'GET',
				'callback' => array($this, 'getPing'),
				'permission_callback' => '__return_true',
				'args'     => array(
					'reserve' => array(
						'required' => true,
						'validate_callback' => function($param, $request, $key) {return true;}
					),
				)
			)
		);
	}

	function getSync($data)
	{
		$origin2 = isset($data['transaction_id']) ? \absint($data['transaction_id']) : '';

		$user = \get_user_by('email', $data['email']);
		if( !($user && $user->ID) )
		{
			return array('status' => 'error', 'status_code' => 'e404', 'transaction_id' => $origin2, 'message' => __("User not found", 'wr-addon-points-sync'));
		}
		$pools = \LWS_WooRewards_Pro::getBuyablePools()->filterByStackId($data['reserve']);
		if( !$pools->count() )
		{
			return array('status' => 'error', 'status_code' => 'e410', 'transaction_id' => $origin2, 'message' => __("No active Loyalty System attached to this Point Reserve", 'wr-addon-points-sync'));
		}

		$stack = \LWS\WOOREWARDS\Collections\PointStacks::instanciate()->create(
			$data['reserve'],
			$user->ID,
		);

		$action  = $data['action'];
		$origin  = isset($data['source']) ? \sanitize_key($data['source']) : 'wrk_?';
		$reason  = isset($data['reason']) ? \sanitize_text_field($data['reason']) : '';
		if( !$reason )
			$reason = __("Point Synchronization", 'wr-addon-points-sync');

		$code = 's200';
		if( !$this->isTransactionExists($origin, $origin2) )
		{
			$tryUnlock = true;
			if( 'set' == $action )
			{
				$stack->set($data['points'], $reason, $origin, $origin2);
			}
			else if( 0 != $data['points'] )
			{
				if( 'add' == $action )
				{
					$stack->add($data['points'], $reason, true, $origin, $origin2);
				}
				else if( 'sub' == $action )
				{
					$stack->sub($data['points'], $reason, true, $origin, $origin2);
				}
			}
			else
				$tryUnlock = false;

			if( $tryUnlock )
			{
				// answer first, try unlock next
				\add_action('shutdown', function()use($pools, $user){
					// trigger only the first is enougth to manage all sharings
					$pools->first()->tryUnlock($user);
				});
			}
		}
		else
			$code = 's208';

		return array(
			'status'         => 'success',
			'status_code'    => $code,
			'message'        => 'Points synchronized',
			'transaction_id' => $origin2,
			'points'         => $stack->get(),
		);
	}

	/**	Same sync should not be processed twice
	 *	@return bool */
	function isTransactionExists($source, $transactionId)
	{
		global $wpdb;
		$sql = <<<EOT
SELECT COUNT(id) FROM {$wpdb->lwsWooRewardsHistoric}
WHERE origin = %s AND origin2 = %d AND blog_id = %d
EOT;
		$traces = $wpdb->get_var($wpdb->prepare($sql, $source, $transactionId, \get_current_blog_id()));
		return $traces > 0;
	}

	function getPing($data)
	{
		$response = array(
			'sync' => 'yes',
			'auth' => $this->hasPermission() ? 'yes' : 'no',
			'pool' => '',
		);
		if( $info = \LWS\WOOREWARDS\PRO\Conveniences::instance()->getPoolsInfo() )
		{
			foreach( $info as $pool )
			{
				if( $pool->stack_id == $data['reserve'] )
				{
					$response['pool'] = $pool->pool_name;
					break;
				}
			}
		}
		return $response;
	}

	function hasPermission()
	{
		// basic auth, hope server in https
		if( !(isset($_SERVER['PHP_AUTH_USER']) && $_SERVER['PHP_AUTH_USER']) )
			return false;
		if( !(isset($_SERVER['PHP_AUTH_PW']) && $_SERVER['PHP_AUTH_PW']) )
			return false;
		$couple = \LWS_WooRewardsPointsSync::getOrCreateKeyCouple();
		if( $_SERVER['PHP_AUTH_USER'] != $couple[0] )
			return false;
		if( $_SERVER['PHP_AUTH_PW'] != $couple[1] )
			return false;

		return true;
	}
}
