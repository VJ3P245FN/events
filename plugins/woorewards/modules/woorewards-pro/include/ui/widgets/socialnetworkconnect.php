<?php
namespace LWS\WOOREWARDS\PRO\Ui\Widgets;

// don't call the file directly
if( !defined( 'ABSPATH' ) ) exit();

/** Manage link with facebook user account.
 *	Provide a button to let user connect. */
class SocialNetworkConnect extends \LWS\WOOREWARDS\Ui\Widget
{
	private $stygen = false;
	
	static function install()
	{
		self::register(get_class());
		$me = new self();

		\add_shortcode('wr_social_connect', array($me, 'shortcode'));
		\add_filter('lws_adminpanel_stygen_content_get_'.'wr_social_connect', array($me, 'template'));

		\add_action('wp_enqueue_scripts', array($me, 'scripts'));
		\add_action('wp_ajax_lws_webhooks_register_account', array($me, 'linkRemoteAccount'));
	}

	/** Will be instanciated by WordPress at need */
	public function __construct($asWidget = true)
	{
		if( $asWidget )
		{
			parent::__construct(
				'lws_woorewards_socialnetworkconnect',
				__("WooRewards Social Network Connect", 'woorewards-pro'),
				array(
					'description' => __("Let your customers link their social network accounts.", 'woorewards-pro')
				)
			);
		}
	}

	protected function defaultArgs()
	{
		return array(
			'title' => __("Connect your social media accounts", 'woorewards-pro'),
			'description' => __('Connect your social medias accounts to start receiving loyalty points for your activity', 'woorewards-pro'),
			'networks' => '',
		);
	}

	public function template($snippet)
	{
		$this->stygen = true;
		$networks = \get_option('lws_woorewards_social_connect_medias');
		if (!$networks)
			$networks = \array_column(self::getSupportedNetworks(), 'value');

		$snippet = $this->getButtons(is_array($networks) ? $networks : explode(',', $networks), $this->defaultArgs()['description']);
		$this->stygen = false;
		return $snippet;
	}

	public function widget($args, $instance)
	{
		if (!\get_option('lws_woorewards_wh_fb_sdk_embedded'))
			return '';
		if (!\get_current_user_id())
			return '';
		$networks = \get_option('lws_woorewards_social_connect_medias', false);
		if (!(is_array($networks) && $networks))
			$networks = \array_column(self::getSupportedNetworks(), 'value');
		if (!$networks)
			return '';

		$instance = \wp_parse_args($instance, $this->defaultArgs());

		\wp_enqueue_script('lws_webhooks_facebook_account');
		\wp_enqueue_style('wr_social_connect');
		\wp_enqueue_style('lws-icons');

		echo $args['before_widget'];
		if ($instance['title']) {
			echo $args['before_title'];
			echo \apply_filters('widget_title', $instance['title'], $instance);
			echo $args['after_title'];
		}
		echo $this->getButtons(is_array($networks) ? $networks : explode(',', $networks), $instance['description']);
		echo $args['after_widget'];
	}

	function shortcode($atts = array(), $content = '')
	{
		if (!\get_option('lws_woorewards_wh_fb_sdk_embedded'))
			return '';
		if (!\get_current_user_id())
			return '';
		$networks = isset($atts['networks']) ? \trim($atts['networks']) : false;
		if (!$networks)
			$networks = \get_option('lws_woorewards_social_connect_medias', false);
		if (!$networks)
			return '';

		\wp_enqueue_script('lws_webhooks_facebook_account');
		\wp_enqueue_style('wr_social_connect');
		\wp_enqueue_style('lws-icons');
		return $this->getButtons(is_array($networks) ? $networks : explode(',', $networks));
	}

	function getButtons(array $networks, $description='')
	{
		$content = "<div class='lws-wr-snc-wrapper' data-selector='.lws-wr-snc-wrapper' data-type='Main Container'>";
		if ($description) {
			$description = \apply_filters('wpml_translate_single_string', $description, 'Widgets', "WooRewards Social Network Connect - description");
			$content .= <<<EOT
<div class='connect-header' data-selector='.lws-wr-snc-wrapper .connect-header' data-type="Widget Description">
	{$description}
</div>
EOT;
		}

		foreach($networks as $network) {
			$network = \trim(\strtolower($network));

			if ('facebook' == $network) {
				$content .= $this->getButtonFacebook($this->stygen);
			}else if ('instagram' == $network) {
				$content .= $this->getButtonInstagram($this->stygen);
			}
		}
		$content .= '</div>';
		return $content;
	}

	function getButtonFacebook($demo=false)
	{
		$hidden = ($demo ? '' : ' hidden');
		$button = \lws_get_option('lws_woorewards_social_connect_btn_facebook', __("Connect your Facebook Account", 'woorewards-pro'));
		$button = \apply_filters('wpml_translate_single_string', $button, 'Widgets', "WooRewards Social Network Connect - Button facebook");
		$verified = \lws_get_option('lws_woorewards_social_connect_verified_facebook', __("Your Facebook account is connected and verified. You can earn loyalty points for your Facebook actions", 'woorewards-pro'));
		$verified = \apply_filters('wpml_translate_single_string', $verified, 'Widgets', "WooRewards Social Network Connect - Verified Account facebook");
		$connected = __("Your Facebook account is connected but not verified yet", 'woorewards-pro');
		$pageurl = \lws_get_option('lws_woorewards_wh_fb_page_url','');
		if(!empty($pageurl)){
			$connected .= "<br/>" . sprintf(__("Please verify your connection by going to this %s and like/comment one of the posts and refresh this page.", 'woorewards-pro'), "<a href='{$pageurl}' target='_blank'>" . __("Facebook Page", 'woorewards-pro') . "</a>");
		}
		$blocked = __("Facebook Login is blocked by your browser. Please deactivate your ad blocker.", 'woorewards-pro');

		return <<<EOT
<div class='social_network_connect snc-container' data-selector='.lws-wr-snc-wrapper .snc-container' data-type="Social Media Container">
	<button class='fb-button lws_webhooks_facebook_login_button{$hidden}' data-selector='.lws-wr-snc-wrapper .fb-button' data-type="Facebook Button">
		<div class='fb-button-icon lws-icon-logo-fb-simple' data-selector='.container .fb-button .fb-button-icon' data-type="FB Button Icon"></div>
		<div class='fb-button-text' data-type="FB Button Text"  data-selector='.container .fb-button .fb-button-text' data-editable='text' data-id='lws_woorewards_social_connect_btn_facebook'>
			<div class='lwss_modify_content'>{$button}</div>
		</div>
	</button>
	<div class='fb-message connected lws_webhooks_facebook_login_ok{$hidden}' data-selector='.lws-wr-snc-wrapper .fb-message.connected' data-type="Facebook Connected Text">
		{$connected}
	</div>
	<div class='fb-message verified lws_webhooks_facebook_login_verified{$hidden}' data-selector='.lws-wr-snc-wrapper .fb-message.verified' data-type="Facebook Verified Text" data-editable='text' data-id='lws_woorewards_social_connect_verified_facebook'>
		<div class='lwss_modify_content'>{$verified}</div>
	</div>
	<div class='fb-message blocked lws_webhooks_facebook_login_blocked{$hidden}' data-selector='.lws-wr-snc-wrapper .fb-message.blocked' data-type="Facebook Blocked Text">
		{$blocked}
	</div>
</div>
EOT;
	}

	function getButtonInstagram($demo=false)
	{
		$hidden = ($demo ? '' : ' hidden');
		$button = \lws_get_option('lws_woorewards_social_connect_btn_instagram', __("Connect your Instagram Account", 'woorewards-pro'));
		$button = \apply_filters('wpml_translate_single_string', $button, 'Widgets', "WooRewards Social Network Connect - Button instagram");
		$connected = __("Your Instagram account is connected", 'woorewards-pro');
		$blocked = __("Instagram SDK is blocked by your browser", 'woorewards-pro');

		return <<<EOT
<div class='social_network_connect snc-container' data-selector='.lws-wr-snc-wrapper .snc-container' data-type="Social Media Container">
	<button class='ig-button lws_webhooks_instagram_login_button{$hidden}' data-selector='.lws-wr-snc-wrapper .ig-button' data-type="Instagram Button">
		<div class='ig-button-icon lws-icon-logo-instagram' data-selector='.snc-container .ig-button .ig-button-icon' data-type="IG Button Icon"></div>
		<div class='ig-button-text' data-type="IG Button Text"  data-selector='.snc-container .ig-button .ig-button-text' data-editable='text' data-id='lws_woorewards_social_connect_btn_instagram'>
			<div class='lwss_modify_content'>{$button}</div>
		</div>
	</button>
	<div class='ig-button-ok lws_webhooks_instagram_login_ok{$hidden}' data-selector='.lws-wr-snc-wrapper .ig-button-ok' data-type="Instagram Connected">
		<div class='ig-button-ok-icon lws-icon-logo-instagram' data-selector='.snc-container .ig-button-ok .ig-button-ok-icon' data-type="IG Connected Icon"></div>
		<div class='ig-button-ok-text' data-type="IG Connected Text"  data-selector='.snc-container .ig-button-ok .ig-button-ok-text'>{$connected}</div>
	</div>
	<div class='ig-button-nok lws_webhooks_instagram_login_blocked{$hidden}' data-selector='.lws-wr-snc-wrapper .ig-button-nok' data-type="Instagram Blocked">
		<div class='ig-button-nok-icon lws-icon-logo-instagram' data-selector='.snc-container .ig-button-nok .ig-button-nok-icon' data-type="IG Blocked Icon"></div>
		<div class='ig-button-nok-text' data-type="IG Blocked Text"  data-selector='.snc-container .ig-button-nok .ig-button-nok-text'>{$blocked}</div>
	</div>
</div>
EOT;

	}

	/** apply translations */
	function update($instance, $previous)
	{
		$instance = \wp_parse_args($instance, $this->defaultArgs());
		\do_action('wpml_register_single_string', 'Widgets', "WooRewards Social Network Connect - description", $instance['description']);
		return $instance;
	}

	static function getSupportedNetworks()
	{
		return array(
			array('value' => 'facebook', 'label' => __("Facebook", 'woorewards-pro')),
			//array('value' => 'instagram', 'label' => __("Instagram", 'woorewards-pro')),
		);
	}

	/** Widget parameters (admin) */
	public function form($instance)
	{
		$defaults = $this->defaultArgs();
		$instance = \wp_parse_args($instance, $defaults);

		// title
		$this->eformFieldText(
			$this->get_field_id('title'),
			__("Title", 'woorewards-pro'),
			$this->get_field_name('title'),
			\esc_attr($instance['title']),
			\esc_attr($defaults['title'])
		);

		// description
		$this->eformFieldText(
			$this->get_field_id('description'),
			__("Header", 'woorewards-pro'),
			$this->get_field_name('description'),
			\esc_attr($instance['description']),
			\esc_attr($defaults['description'])
		);
	}

	function scripts()
	{
		if (!\get_option('lws_woorewards_wh_fb_sdk_embedded'))
			return;
		if (!\get_current_user_id())
			return '';

		\wp_register_style('wr_social_connect', LWS_WOOREWARDS_PRO_CSS . '/templates/socialnetworkconnect.css?stygen=lws_woorewards_social_connect', array(), LWS_WOOREWARDS_PRO_VERSION);
		\wp_register_script('lws_facebook_connect_sdk', 'https://connect.facebook.net/en_US/sdk.js', array(), LWS_WOOREWARDS_PRO_VERSION, false);
		\wp_register_script('lws_webhooks_facebook_account', LWS_WOOREWARDS_PRO_JS.'/fb-login.js', array('jquery', 'lws_facebook_connect_sdk'), LWS_WOOREWARDS_PRO_VERSION, true);

		$userId = \get_current_user_id();
		$data = array(
			'app_id'    => \get_option('lws_woorewards_wh_fb_app_id'),
			'version'   => 'v9.0',
			'connected' => $userId ? 'y' : 'n',
			'known'     => 'y',
			'verified'  => 'y',
			'nonce'     => '',
			'url'       => '',
		);
		if (!(\get_user_meta($userId, 'lws_woorewards_facebook_user_inapp_id', true))) {
			$data['known'] = 'n';
			$data['nonce'] = \wp_create_nonce('lws_webhooks_facebook_account');
			$data['url'] = \admin_url('/admin-ajax.php');
		}
		if (!(\get_user_meta($userId, 'lws_woorewards_facebook_user_id', true))){
			$data['verified'] = 'n';
		}
		\wp_localize_script('lws_webhooks_facebook_account', 'lws_webhooks_facebook', $data);
	}

	/** WR-PRO #190 */
	function linkRemoteAccount()
	{
		$social = isset($_GET['social']) ? \sanitize_key($_GET['social']) : false;
		$remoteId = isset($_GET['remote_id']) ? \sanitize_key($_GET['remote_id']) : false;
		$remoteName = isset($_GET['remote_name']) ? \sanitize_text_field($_GET['remote_name']) : false;
		$remoteAppId = isset($_GET['remote_inapp']) ? \sanitize_key($_GET['remote_inapp']) : false;
		$nonce = isset($_GET['nonce']) ? \sanitize_key($_GET['nonce']) : false;
		$userId = \get_current_user_id();

		if ($social && ($remoteId || $remoteName) && $userId && $nonce && \wp_verify_nonce($nonce, 'lws_webhooks_facebook_account')) {
			if( 'facebook' == $social ) {
				$set = array();
				if (!\get_user_meta($userId, 'lws_woorewards_facebook_user_id', true)) {
					if ($remoteId)
						$set[] = \update_user_meta($userId, 'lws_woorewards_facebook_user_id', $remoteId);
					if ($remoteName)
						$set[] = \update_user_meta($userId, 'lws_woorewards_facebook_user_name', $remoteName);
				}
				\update_user_meta($userId, 'lws_woorewards_facebook_user_inapp_id', $remoteAppId);
				\wp_send_json(array('status' => 'success', 'updated' => count(\array_filter($set))));
			}
		}
		\wp_send_json(array('status' => 'failed'));
	}
}
