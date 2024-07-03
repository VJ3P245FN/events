<?php
namespace LWS\WOOREWARDS\PRO\Ui\Shortcodes;

// don't call the file directly
if( !defined( 'ABSPATH' ) ) exit();

class UserName
{
	public static function install()
	{
		$me = new self();
		\add_shortcode('wr_user_name', array($me, 'shortcode'));
		\add_shortcode('wr_nickname', array($me, 'nickname'));

		/** Admin */
		\add_filter('lws_woorewards_users_shortcodes', array($me, 'admin'), 20);
	}

	public function admin($fields)
	{
		$fields['username'] = array(
			'id' => 'lws_woorewards_sc_username',
			'title' => __("User Name and Title", 'woorewards-pro'),
			'type' => 'shortcode',
			'extra' => array(
				'shortcode' => '[wr_user_name title="yes" raw="no"]',
				'description' =>  __("This shortcode displays the user name and title", 'woorewards-pro'),
				'options' => array(
					array(
						'option' => 'title',
						'desc' => __("Shows the title if user unlocked a title reward.", 'woorewards-pro'),
					),
					array(
						'option' => 'raw',
						'desc' => __("Defines if the name and title are put in stylable elements or not.", 'woorewards-pro'),
					),
				),
				'flags' => array('current_user_id'),
			)
		);
		$fields['nickname'] = array(
			'id' => 'lws_woorewards_sc_username',
			'title' => __("User Nickname and Title", 'woorewards-pro'),
			'type' => 'shortcode',
			'extra' => array(
				'shortcode' => '[wr_nickname title="yes" raw="no"]',
				'description' =>  __("This shortcode displays the user nickname (if any) and title", 'woorewards-pro'),
				'options' => array(
					array(
						'option' => 'title',
						'desc' => __("Shows the title if user unlocked a title reward.", 'woorewards-pro'),
					),
					array(
						'option' => 'raw',
						'desc' => __("Defines if the nickname and title are put in stylable elements or not.", 'woorewards-pro'),
					),
				),
				'flags' => array('current_user_id'),
			)
		);
		return $fields;
	}


	/** Show user name and title
	 * [wr_user_name title='yes' raw='no']
	 * * title (bool) default=yes, shows the title if user owns this reward
	 * * raw (bool) default=no, defines if the name and title are put in divs or not.
	 */
	public function shortcode($atts=array(), $content='')
	{
		$atts = \wp_parse_args($atts, array('title' => 'yes', 'raw' => ''));
		$userId = \apply_filters('lws_woorewards_shortcode_current_user_id', \get_current_user_id(), $atts, 'wr_user_name');
		$user = $userId ? \get_user_by('ID', $userId) : false;
		if( $user && $user->ID )
		{
			$content = $user->display_name ? $user->display_name : ($user->user_nicename ? $user->user_nicename : $user->user_login);
			$raw = \LWS\Adminpanel\Tools\Conveniences::argIsTrue($atts['raw']);
			if( \LWS\Adminpanel\Tools\Conveniences::argIsTrue($atts['title']) )
				$content = \LWS\WOOREWARDS\PRO\Core\UserTitle::getDisplayName($user, $content, $raw ? '' : 'display');
			if( !$raw )
				$content = sprintf("<span class='wr-name-display'>%s</span>", $content);
		}
		return $content;
	}

	/** shortcode [user_nickname] */
	public function nickname($atts=array(), $content='')
	{
		$atts = \shortcode_atts(array('user_id'=>false, 'title' => 'yes', 'raw' => ''), $atts, 'wr_nickname');
		$userId = $atts['user_id'] ? $atts['user_id'] : \apply_filters('lws_woorewards_shortcode_current_user_id', \get_current_user_id(), $atts, 'wr_nickname');
		$user = $userId ? \get_user_by('ID', $userId) : false;
		if ($user) {
			$nick = (isset($user->nickname) && $user->nickname) ? $user->nickname : $user->display_name;
			if ($nick) {
				$content = $nick;
				$raw = \LWS\Adminpanel\Tools\Conveniences::argIsTrue($atts['raw']);
				if( \LWS\Adminpanel\Tools\Conveniences::argIsTrue($atts['title']) )
					$content = \LWS\WOOREWARDS\PRO\Core\UserTitle::getDisplayName($user, $content, $raw ? '' : 'display');
				if( !$raw )
					$content = sprintf("<span class='wr-nickname-display'>%s</span>", $content);
			}
		}
		return $content;
	}
}