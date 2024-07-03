<?php
namespace LWS\WOOREWARDS\PRO\Mails;

// don't call the file directly
if( !defined( 'ABSPATH' ) ) exit();

/** Setup mail about achievement unlocked.
 * $data should be an array as:
 *	*	'user' => a WP_User instance
 *	*	'type' => the reward type (origin Unlockable type)
 *	* 'unlockable' => a Unlockable instance
 *	*	'reward' => depends on Unlockable type: WC_Coupon, string, array... */
class Achieved
{
	protected $template = 'wr_achieved';

	public function __construct()
	{
		add_filter( 'lws_woorewards_mails', array($this, 'addTemplate'), 20 );
		\add_filter('lws_mail_arguments_' . $this->template, array($this, 'attributes'), 10, 2);
		add_filter( 'lws_mail_settings_' . $this->template, array( $this , 'settings' ) );
		add_filter( 'lws_mail_body_' . $this->template, array( $this , 'body' ), 10, 3 );
	}

	public function addTemplate($arr)
	{
		$arr[] = $this->template;
		return $arr;
	}

	public function attributes($settings, $data)
	{
		if( \is_wp_error($data) )
			$data = $this->placeholders();

		$name = $this->getUserName($data);
		foreach( array('subject', 'title', 'header', 'footer', 'preheader') as $key )
			$settings[$key] = str_replace('[user_name]', $name, $settings[$key]);
		return $settings;
	}

	public function settings( $settings )
	{
		$settings['domain']        = 'woorewards';
		$settings['settings']      = 'Achievement';
		$settings['settings_name'] = __("Achievement", 'woorewards-pro');
		$settings['about']         = __("Sent to customers when they unlock an achievement", 'woorewards-pro');
		$settings['subject']       = __("New achievement completed !", 'woorewards-pro');
		$settings['title']         = __("Achievement complete", 'woorewards-pro');
		$settings['header']        = __("With that achievement, you received the following badge", 'woorewards-pro');
		$settings['footer']        = __("Powered by MyRewards", 'woorewards-pro');
		$settings['doclink']       = \LWS\WOOREWARDS\PRO\DocLinks::get('emails');
		$settings['icon']          = 'lws-icon-trophy';
		$settings['css_file_url']  = LWS_WOOREWARDS_PRO_CSS . '/mails/achieved.css';
		$settings['fields']['enabled'] = array(
			'id' => 'lws_woorewards_enabled_mail_' . $this->template,
			'title' => __("Enabled", 'woorewards-pro'),
			'type' => 'box',
			'extra'=> array(
				'default' => '',
				'layout' => 'toggle',
			),
		);
		$settings['about'] .= '<br/><span class="lws_wr_email_shortcode_help">'.sprintf(__("Use the shortcode %s to insert the name of the user", 'woorewards-pro'),'<b>[user_name]</b>').'</span>';
		return $settings;
	}


	public function body( $html, $data, $settings )
	{
		if( !empty($html) )
			return $html;
		if( empty(\get_option('lws_woorewards_manage_badge_enable', 'on')) )
			return __("Badges and achievements are deactivated. Check your MyRewards &gt; General Settings.", 'woorewards-pro');
		if( $demo = \is_wp_error($data) )
			$data = $this->placeholders();

		$html = \apply_filters('lws_woorewards_achieved_custom_type_mail_content', false, $data, $settings, $demo);
		return !empty($html) ? $html : $this->getDefault($data, $settings, $demo);
	}

	protected function getDefault($data, $settings, $demo=false)
	{
		$values = array(
			'title'  => $data['unlockable']->getTitle(),
			'detail' => $data['unlockable']->getCustomDescription()
		);

		if( empty($img = $data['unlockable']->getThumbnailImage()) && $demo )
			$img = "<div class='lws-achievement-thumbnail'><img src='".LWS_WOOREWARDS_PRO_IMG.'/cat.png'."'/></div>";

		return <<<EOT
<tr><td class='lws-middle-cell'>
	<table class='lwss_selectable lws-achievement-table' data-type='Badges Table'>
		<tr>
			<td><div class='lwss_selectable lws-achievement-img' data-type='Badge Image'>{$img}</div></td>
			<td>
				<div class='lwss_selectable lws-achievement-title' data-type='Badge Title'>{$values['title']}</div>
				<div class='lwss_selectable lws-achievement-detail' data-type='Badge Description'>{$values['detail']}</div>
			</td>
		</tr>
	</table>
</td></tr>
EOT;
	}

	protected function placeholders()
	{
		$unlockable = \LWS\WOOREWARDS\Collections\Unlockables::instanciate()->create('lws_woorewards_pro_unlockables_badge')->last();
		//$unlockable->setTestValues();
		//$unlockable->setThumbnail(LWS_WOOREWARDS_PRO_IMG.'/cat.png');
		$unlockable->setTitle('The Cat');
		$unlockable->setDescription('Look at this beautiful cat');
		$user = \wp_get_current_user();

		return array(
			'user' => $user,
			'type' => $unlockable->getType(),
			'unlockable' => $unlockable,
			'reward' => array()
		);
	}

	function getUserName($data)
	{
		$name = '';
		if( isset($data['user']) && $data['user'] && \is_a($data['user'], '\WP_User') )
		{
			if( $data['user']->display_name )
				$name = $data['user']->display_name;
			else if( $data['user']->user_nicename )
				$name = $data['user']->user_nicename;
			else
				$name = $data['user']->user_login;
		}
		return $name;
	}

}
?>
