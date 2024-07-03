<?php
namespace LWS\WOOREWARDS\PRO\Ui;

// don't call the file directly
if( !defined( 'ABSPATH' ) ) exit();

/** Add a column to Users screen.
 *	Show user activity relative to social networks. */
class SocialsStats
{
	static function install()
	{
		$me = new self();
		\add_filter('manage_users_columns', array($me, 'getHeader'), 200);
		\add_filter('manage_users_custom_column', array($me, 'getValue'), 10, 3);
	}

	function getHeader($column)
	{
		$column['lws_webhooks'] = __("Social Networks", 'woorewards-pro');
		return $column;
	}

	function getValue($val, $column_name, $userId)
	{
		if ('lws_webhooks' != $column_name)
			return $val;
		if ($val)
			return $val;
		else
			$val = '';

		$remoteId = \get_user_meta($userId, 'lws_woorewards_facebook_user_id', true);
		$remoteName = false;
		if (!$remoteId)
			$remoteName = \get_user_meta($userId, 'lws_woorewards_facebook_user_name', true);

		global $wpdb;
		$sql = <<<EOT
SELECT COUNT(*) FROM {$wpdb->lwsWebhooksEvents}
WHERE `user_id`=%d
AND `network`='facebook'
AND `event`='reaction'
EOT;
		$likes = $wpdb->get_var($wpdb->prepare($sql, $userId));

		if ($remoteId)
			$val .= sprintf('<span class="confirmed" data-fbid="%s" title="Linked account">facebook</span>', \esc_attr($remoteId));
		elseif ($remoteName)
			$val .= sprintf('<span class="idle" data-fbname="%s" title="Connected account">facebook</span>', \esc_attr($remoteName));
		elseif ($likes)
			$val .= '<i class="guessed" title="User\'s name matched">facebook</i>';

		if ($likes)
			$val .= sprintf(' <span class="like-count">(<span class="value">%d</span> likes)</span>', $likes);
		return "<span class='lws-webhooks social-stats'>{$val}</span>";
	}
}
