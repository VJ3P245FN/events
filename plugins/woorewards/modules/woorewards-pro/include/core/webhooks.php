<?php
namespace LWS\WOOREWARDS\PRO\Core;

// don't call the file directly
if( !defined( 'ABSPATH' ) ) exit();

/** Register REST API routes. */
class WebHooks
{
	const PREFIX = 'lwswebhooks/';
	const VERSION = 'v1';

	static function getNamespace($route='')
	{
		if ($route)
			$route = ('/' . \ltrim($route, '/'));
		return (static::PREFIX . static::VERSION . $route);
	}

	static function getToken()
	{
		$token = \get_option('lws_woorewards_wh_fb_token');
		if (!$token){
			$token = \wp_generate_password(16, false, false);
			\update_option('lws_woorewards_wh_fb_token', $token);
		}
		return $token;
	}

	static function install()
	{
		\add_action('rest_api_init', array(new self(), 'register'));
	}

	function register()
	{
		\register_rest_route(
			self::getNamespace(),
			'/facebook',
			array(
				'methods'  => 'GET',
				'callback' => array($this, 'facebookSubscription'),
				'permission_callback' => '__return_true',
			)
		);

		\register_rest_route(
			self::getNamespace(),
			'/facebook',
			array(
				'methods'  => 'POST',
				'callback' => array($this, 'facebookEvent'),
				'permission_callback' => '__return_true',
			)
		);
	}

	/** Facebook Webhook subscribe call api with method GET. */
	function facebookSubscription($data)
	{
		\update_option('lws_woorewards_wh_fb_last_event_date', \date('Y-m-d H:i:s'));
		\update_option('lws_woorewards_wh_fb_last_event_post', \json_encode($data->get_json_params(),  JSON_PARTIAL_OUTPUT_ON_ERROR|JSON_PRETTY_PRINT));

		// should be 'hub.mode' but WP seems to convert dot to underscore
		if ($data->has_param('hub_mode') && $data->has_param('hub_verify_token')) {
			// Checks the mode and token sent is correct
			if ('subscribe' == $data['hub_mode'] && self::getToken() == $data['hub_verify_token']) {
				// Responds with the challenge token from the request
				\update_option('lws_woorewards_wh_fb_last_subscription', \date('Y-m-d H:i:s'));
				// facebook requires plaintext output
				\http_response_code(200);
				\header("Content-Type: text/plain");
				echo $data['hub_challenge'];
				\do_action('lws_woorewards_wh_facebook_subscribe');
				exit;
			}
			else {
				// Responds with '403 Forbidden' if verify tokens do not match
				\http_response_code(403);
				exit;
			}
		}

		\http_response_code(404);
		exit;
	}

	/** Accept subscription to 'feed' event.
	 *	Grab 'like' changes on pages. */
	function facebookEvent($data)
	{
		\update_option('lws_woorewards_wh_fb_last_event_date', \date('Y-m-d H:i:s'));
		\update_option('lws_woorewards_wh_fb_last_event_post', \json_encode($data->get_json_params(),  JSON_PARTIAL_OUTPUT_ON_ERROR|JSON_PRETTY_PRINT));

		if ($data->has_param('object')) {
			$object = $data['object'];
			// Checks this is an event from a page subscription
			if ('page'==$object) {
				// Iterates over each entry - there may be multiple if batched
				foreach ($data['entry'] as $entry) {
					if (isset($entry['changes'])) {
						foreach($entry['changes'] as $change) {
							if ('feed' == $change['field']) {
								// Describes nearly all changes to a Page's feed, such as Posts, shares, likes, etc.
								$value = $change['value'];
								$event = $value['item'];
								if (\in_array($event, array('reaction', 'comment'))) {
									// log for setting validation purpose
									if ('reaction' == $event) {
										if (isset($value['comment_id']) && $value['comment_id']) {
											// don't care about like of comments
											continue;
										}
										\update_option('lws_woorewards_wh_fb_event_verify', array('date'=>\date('Y-m-d H:i:s'), 'app_id'=>\get_option('lws_woorewards_wh_fb_app_id')));
									}
									// is an account linked ?
									$userId = $this->getUserIdFromFacebook($value['from']);
									if ($userId) {
										// react on fresh activity, see also 'edit' or 'remove'
										$action = isset($value['verb']) ? $value['verb'] : '';
										if ('add' == $action) {
											// register it
											list($done, $id) = $this->saveEvent(
												$userId,
												'facebook', // network slug
												$event, // 'reaction' or 'comment'
												$value['post_id'], // origin
												$value['from']['id'], // remote user id
												$value
											);
											if ($done)
												\do_action('lws_woorewards_wh_facebook_event_'.$event, $userId, $value, $id);
										}
									}
								}
							}
						}
					}
				}
			}
		}
	}

	/**	Thy following in order until find a user.
	 * 1. look for a validated fb user ID
	 * 2. a fb user name that match (then real user ID is linked and name is forgotten)
	 * 3. if allowed, shallow match against site display name.
	 *
	 *	Since FB do not return real user ID but an obfuscated app-user-id (app relative user id),
	 *	But feed returns the real user ID (so inconsistent),
	 *	we use fb user name as a bridge to link them all the first time.
	 *
	 * 	It leads to unreliable match (since names are not unique) until the first user recorded event. */
	function getUserIdFromFacebook($from)
	{
		global $wpdb;
		$sql = <<<EOT
SELECT `user_id`
FROM {$wpdb->usermeta}
WHERE meta_key = 'lws_woorewards_facebook_user_id'
AND meta_value = %s
EOT;
		$userId = $wpdb->get_var($wpdb->prepare($sql, $from['id']));
		if ($userId)
			return $userId;

		// most recent login first
		$sql = <<<EOT
SELECT user_id, umeta_id
FROM {$wpdb->usermeta}
WHERE meta_key = 'lws_woorewards_facebook_user_name'
AND meta_value = %s
ORDER BY umeta_id DESC
EOT;
		$row = $wpdb->get_row($wpdb->prepare($sql, $from['name']));
		if ($row) {
			$userId = $row->user_id;
			// save ID for reliable later usage
			\update_user_meta($userId, 'lws_woorewards_facebook_user_id', $from['id']);
			// do not use name anymore
			$wpdb->update($wpdb->usermeta, array(
				'meta_key' => 'lws_woorewards_facebook_used_user_name',
			), array(
				'umeta_id' => $row->umeta_id,
			));
			return $userId;
		}

		if (\get_option('lws_woorewards_wh_fb_recognition_by_name')) {
		$sql = <<<EOT
SELECT `ID`
FROM {$wpdb->users}
WHERE display_name = %s
EOT;
			$userId = $wpdb->get_var($wpdb->prepare($sql, $from['name']));
			if ($userId)
				return $userId;
		}

		return false;
	}

	/** @return array(bool, int) first entry is false if event is not saved (failure or duplication)
	 *	second entry is the db table id.
	 *	Do not log twice for same n-uplet {network, event, origin}
	 *	Where event usually is [comment, reaction...],
	 * 	network could be facebook, instagram...
	 *	and origin will be the post_id, the event occured on.
	 * */
	function saveEvent($userID, $network, $event, $origin, $remoteUserId=false, $value=false)
	{
		global $wpdb;
		$query = \LWS\Adminpanel\Tools\Request::from($wpdb->lwsWebhooksEvents);
		$query->select('id');
		$query->where(array(
			'`origin` = %s',
			'`event` = %s',
			'`network` = %s',
		));
		$query->arg(array($origin, $event, $network));
		if ($userID) {
			$query->where('`user_id` = %d')->arg($userID);
		} else if($remoteUserId) {
			$query->where('`remote_user_id` = %s')->arg($remoteUserId);
		} else
			return array(false, 0);

		$exists = $query->getVar();
		if ($exists)
			return array(false, $exists);

		$values = array(
			'user_id' => $userID,
			'network' => $network,
			'event'   => $event,
			'origin'  => $origin,
		);
		$formats = array(
			'%d',
			'%s',
			'%s',
			'%s',
		);
		if ($value) {
			$values['data'] = \serialize($value);
			$formats[] = '%s';
		}
		if ($remoteUserId) {
			$values['remote_user_id'] = $remoteUserId;
			$formats[] = '%s';
		}
		$done = $wpdb->insert($wpdb->lwsWebhooksEvents, $values, $formats);
		return array($done, $done ? $wpdb->insert_id : -1);
	}
}
