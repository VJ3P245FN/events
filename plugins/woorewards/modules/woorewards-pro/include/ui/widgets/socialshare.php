<?php
namespace LWS\WOOREWARDS\PRO\Ui\Widgets;

// don't call the file directly
if( !defined( 'ABSPATH' ) ) exit();

/** Provide a widget for customer to share a content on social medias.
 * Can be used as a Widget, a Shortcode [lws_social_share]. */
class SocialShareWidget extends \LWS\WOOREWARDS\Ui\Widget
{
	const URL_ARG = 'wrshare';
	const URL_ARG_P = 'wrshare2';
	const META_KEY = 'lws_woorewards_social_share_token_';

	private $stygen = false;

	public static function install()
	{
		self::register(get_class());
		$me = new self(false);
		\add_shortcode('lws_social_share', array($me, 'shortcode'));

		\add_filter('lws_adminpanel_stygen_content_get_'.'wr_social_share', array($me, 'template'));

		\add_filter('query_vars', array($me, 'varsReferral'));
		\add_action('parse_query', array($me, 'grabReferral'));
		\add_filter('lws_woorewards_fresh_user_sponsored_by', array($me, 'sponsorship'), 11, 3);

		\add_action('wp_enqueue_scripts', array($me, 'registerScripts'));
		\add_action('admin_enqueue_scripts', array($me, 'registerScripts'));

		/** Admin */
		\add_filter('lws_woorewards_referral_shortcodes', array($me, 'admin'), 20);
	}

	public function admin($fields)
	{
		$fields['socialshare'] = array(
			'id'    => 'lws_woorewards_sc_social',
			'title' => __("Social Share", 'woorewards-pro'),
			'type'  => 'shortcode',
			'extra' => array(
				'shortcode' => '[lws_social_share]',
				'description' =>  __("Use this shortcode to display the social share widget on your pages.", 'woorewards-pro'),
				'options'   => array(
					array(
						'option' => 'header',
						'desc'   => __("A title displayed on top of the widget.", 'woorewards-pro'),
					),
					array(
						'option' => 'text',
						'desc'   => __("The text displayed before the share buttons.", 'woorewards-pro'),
					),
					array(
						'option' => 'url',
						'desc'   => __("(Optional) By default, the widget shares the url of the page it’s displayed on. You can override that setting by setting an url in this option.", 'woorewards-pro'),
					),
					'networks' => array(
						'option'  => 'networks',
						'desc'    => __("(Optional) Comma separated list of Social Media to show. Available Networks are :", 'woorewards-pro'),
						'options' => array(),
					),
					array(
						'option'  => 'showunconnected',
						'desc'    => __("(Optional) The widget must be displayed even if users are not connected.", 'woorewards-pro'),
						'example' => '[lws_social_share showunconnected="yes"]'
					),
					array(
						'option'  => 'alt',
						'desc'    => __("(Optional) A text to display if users are not connected.", 'woorewards-pro'),
					),
					array(
						'option'  => 'popup',
						'desc'    => __("(Optional) Open share dialog as popup instead of a new tab.", 'woorewards-pro'),
						'example' => '[lws_social_share popup="yes"]'
					),
				),
			)
		);
		foreach (\LWS\WOOREWARDS\PRO\Core\Socials::instance()->asDataSource() as $source) {
			$fields['socialshare']['extra']['options']['networks']['options'][] = array(
				'option' => $source['value'],
				'desc'   => $source['label'],
			);
		}
		$fields['socialsharestyle'] = array(
			'id' => 'lws_woorewards_social_share_template',
			'type' => 'stygen',
			'extra' => array(
				'purpose' => 'filter',
				'template' => 'wr_social_share',
				'html' => false,
				'css' => LWS_WOOREWARDS_PRO_CSS . '/templates/social-share.css',
				'subids' => array(
					'lws_woorewards_social_share_widget_message' => "WooRewards - Social Share - Title",
					'lws_woorewards_social_share_widget_text' => "WooRewards - Social Share - Description",
				)
			)
		);
		return $fields;
	}

	function registerScripts()
	{
		\wp_register_style('woorewards-social-share', LWS_WOOREWARDS_PRO_CSS.'/templates/social-share.css?stygen=lws_woorewards_social_share_template', array(), LWS_WOOREWARDS_PRO_VERSION);
		\wp_register_style('woorewards-social-share-override', LWS_WOOREWARDS_PRO_CSS . '/social-share-override.css', array(), LWS_WOOREWARDS_PRO_VERSION);
		\wp_register_script('woorewards-social-share',LWS_WOOREWARDS_PRO_JS.'/social-share.js',array('jquery', 'lws-tools'),LWS_WOOREWARDS_PRO_VERSION, true);
	}


	protected function enqueueScripts($demo=false)
	{
		\wp_enqueue_style('lws-icons');
		if( !$demo )
		{
			\wp_enqueue_script('jquery');
			\wp_enqueue_script('lws-tools');
			\wp_enqueue_script('woorewards-social-share');
			// cause stygen already include it
			\wp_enqueue_style('woorewards-social-share');
			\wp_enqueue_style('woorewards-social-share-override');
		}

		\do_action('lws_woorewards_socials_scripts', $demo);
	}

	/** Will be instanciated by WordPress at need */
	public function __construct($asWidget=true)
	{
		if( $asWidget )
		{
			parent::__construct(
				'lws_woorewards_social_share',
				__("MyRewards Social share", 'woorewards-pro'),
				array(
					'description' => __("Provide Social Share links to your customers.", 'woorewards-pro')
				)
			);
		}
	}

	function template($snippet){
		$this->stygen = true;
		$snippet = $this->shortcode();
		$this->stygen = false;
		return $snippet;
	}

	/**	Display the widget,
	 *	@see https://developer.wordpress.org/reference/classes/wp_widget/
	 * 	display parameters in $args
	 *	get option from $instance */
	public function widget($args, $instance)
	{
		if( !empty(\get_current_user_id()) || !empty(\get_option('lws_woorewards_social_display_unconnected', '')) )
		{
			echo $args['before_widget'];
			if( is_array($instance) && isset($instance['title']) && !empty($instance['title']) )
			{
				echo $args['before_title'];
				echo \apply_filters('widget_title', $instance['title'], $instance);
				echo $args['after_title'];
			}
			if (\is_array($instance)) {
				// let reload items since it was saved with widget
				$instance = \array_diff_key($instance, array('networks'=>'', 'showunconnected' => '', 'popup' => ''));
			}
			echo $this->shortcode($instance, '', 'widget');
			echo $args['after_widget'];
		}
	}

	/** ensure all required fields exist. */
	public function update($new_instance, $old_instance)
	{
		$new_instance = \wp_parse_args(
			array_merge($old_instance, $new_instance),
			$this->defaultArgs()
		);

		\do_action('wpml_register_single_string', 'Widgets', "WooRewards - Social Share - Title", $new_instance['header']);
		\do_action('wpml_register_single_string', 'Widgets', "WooRewards - Social Share - Description", $new_instance['text']);

		return $new_instance;
	}

	/** Widget parameters (admin) */
	public function form($instance)
	{
		$instance = \wp_parse_args($instance, $this->defaultArgs());

		// title
		$this->eFormFieldText(
			$this->get_field_id('title'),
			__("Title", 'woorewards-pro'),
			$this->get_field_name('title'),
			is_array($instance) && isset($instance['title']) ? \esc_attr($instance['title']) : ''
		);
		// header
		$this->eFormFieldText(
			$this->get_field_id('header'),
			__("Header", 'woorewards-pro'),
			$this->get_field_name('header'),
			\esc_attr($instance['header']),
			\esc_attr(_x("Share that content on Social Medias", "frontend widget", 'woorewards-pro'))
		);
		// text
		$this->eFormFieldText(
			$this->get_field_id('text'),
			__("Text displayed to users", 'woorewards-pro'),
			$this->get_field_name('text'),
			\esc_attr($instance['text']),
			\esc_attr(_x("Earn loyalty points by sharing this page with your friends on social medias", "frontend widget", 'woorewards-pro'))
		);
		// url
		$this->eFormFieldText(
			$this->get_field_id('url'),
			__("Shared url (Optional)", 'woorewards-pro'),
			$this->get_field_name('url'),
			\esc_attr($instance['url'])
		);
	}

	protected function defaultArgs()
	{
		/** Backwards compatibility with widget */
		$socials = \lws_get_option('lws_woorewards_smshare_socialmedias', \LWS\WOOREWARDS\PRO\Core\Socials::instance()->getSupportedNetworks());
		return array(
			'networks' => $socials ? \trim(\implode(',', $socials)) : '',
			'title'    => '',
			'header'   => '',
			'text'     => '',
			'url'      => '',
			'showunconnected' => \get_option('lws_woorewards_social_display_unconnected', ''),
			'popup'    => \get_option('lws_woorewards_social_share_popup', '')
		);
	}

	public function getOrCreateToken($userId, $social)
	{
		$token = \get_user_meta($userId, self::META_KEY.$social, true);
		if( empty($token) && ($user = \get_user_by('ID', $userId)) )
		{
			$token = \sanitize_key(\wp_hash($social.json_encode($user).rand()));
			\update_user_meta($userId, self::META_KEY.$social, $token);
		}
		return $token;
	}

	public function sponsorship($sponsor, $user, $email)
	{
		if( !$sponsor->id && \get_option('lws_woorewards_socialshare_back_give_sponsorship', '') )
		{
			$sponsorship = new \LWS\WOOREWARDS\Core\Sponsorship();
			$ref = $sponsorship->getCurrentReferral();

			if( $ref->user_id && $ref->hash && $ref->origin && \LWS\WOOREWARDS\PRO\Core\Socials::instance()->isSupportedNetwork($ref->origin) )
			{
				if( $ref->user_id != $user->ID )
				{
					$meta = $this->getMetaByReferral($ref->hash);
					if( $ref->user_id == $meta->user_id )
					{
						$sponsor->id = $ref->user_id;
						$sponsor->origin = $meta->social;
					}
				}
			}
		}
		return $sponsor;
	}

	function varsReferral($vars)
	{
		$vars[] = self::URL_ARG;
		$vars[] = self::URL_ARG_P;
		return $vars;
	}

	/** Keep referral in session to let visitor continues without losing referral info.
	 * @see \LWS\WOOREWARDS\Core\Sponsorship::setCurrentReferral() */
	public function grabReferral(&$query)
	{
		$referral = isset($query->query[self::URL_ARG]) ? trim($query->query[self::URL_ARG]) : '';
		$hash = isset($query->query[self::URL_ARG_P]) ? trim($query->query[self::URL_ARG_P]) : '';

		if( !empty($referral) && !empty($hash) )
		{
			$sponsorship = new \LWS\WOOREWARDS\Core\Sponsorship();
			$ref = $sponsorship->getCurrentReferral();

			if( $ref->hash != $hash || !$ref->user_id || !$ref->origin )
			{
				$meta = $this->getMetaByReferral($referral);
				\do_action('lws_woorewards_social_backlink', $meta->user_id, $meta->social, $hash);
				$ref->user_id = $meta->user_id;
				$ref->hash = $referral;
				$ref->origin = $meta->social;
			}
			$sponsorship->setCurrentReferral($ref->user_id, $ref->hash, $ref->origin);
			if( \get_option('lws_woorewards_redirect_after_referral_grab', 'on') )
			{
				\wp_redirect(\remove_query_arg(array(self::URL_ARG, self::URL_ARG_P)));
				exit();
			}
		}
	}

	protected function getMetaByReferral($referral)
	{
		global $wpdb;
		$metakey = self::META_KEY . '%';
		$meta = $wpdb->get_row($wpdb->prepare(
			"SELECT user_id, meta_key FROM {$wpdb->usermeta} WHERE meta_key LIKE '{$metakey}' AND meta_value=%s",
			$referral
		));
		if( $meta )
		{
			$meta->social = substr($meta->meta_key, strlen(self::META_KEY));
			return $meta;
		}
		return (object)array('user_id' => false, 'meta_key' => false, 'social' => false);
	}

	/** @brief shortcode [lws_sponsorship]
	 *	Display input box to set sponsored email, then a div for server answer. */
	public function shortcode($atts = array(), $content = '', $origin = 'shortcode')
	{
		$atts = \LWS\Adminpanel\Tools\Conveniences::sanitizeAttr(\wp_parse_args($atts, $this->defaultArgs()));
		if( empty($userId = \get_current_user_id()) )
		{
			if (!\LWS\Adminpanel\Tools\Conveniences::argIsTrue($atts['showunconnected'])) {
				return $content;
			}
		}

		$demo = $this->stygen;
		$nonce = $demo ? '' : \esc_attr(\wp_create_nonce('lws_woorewards_socialshare'));
		$hash = '';
		if( !$demo && $userId )
		{
			if( !empty($atts['url']) )
				$hash = \LWS\WOOREWARDS\PRO\Core\Socials::instance()->getCustomPageHash($atts['url']);
			else
				$hash = \LWS\WOOREWARDS\PRO\Core\Socials::instance()->getCurrentPageHash();
		}
		$escHash = \esc_attr($hash);

		$popup = \LWS\Adminpanel\Tools\Conveniences::argIsTrue($atts['popup']) ? ' data-popup="on"' : '';
		$buttons = '';
		$socials = $atts['networks'] ? \explode(',', $atts['networks']) : array();
		foreach ($socials as $social) {
			$icon = \LWS\WOOREWARDS\PRO\Core\Socials::instance()->getIcon($social);
			$href = '#';
			$target='';
			if( !$demo )
			{
				$url = '';
				if( $userId )
				{
					if( !empty($atts['url']) )
					{
						$url = \add_query_arg(
							array(
								self::URL_ARG => $this->getOrCreateToken($userId, $social),
								self::URL_ARG_P => $hash
							),
							$atts['url']
						);
					}else{
						$url = \LWS\WOOREWARDS\PRO\Core\Socials::instance()->getCurrentPageUrl(array(
							self::URL_ARG => $this->getOrCreateToken($userId, $social),
							self::URL_ARG_P => $hash
						));
					}
				}
				else
				{
					if( !empty($atts['url']) )
						$url = $atts['url'];
					else
						$url = \LWS\WOOREWARDS\PRO\Core\Socials::instance()->getCurrentPageUrl();
				}
				$href = \esc_attr(\LWS\WOOREWARDS\PRO\Core\Socials::instance()->getShareLink($social, $url));
				$target='_blank';
			}
			$buttons .= "<a target='{$target}'{$popup} href='{$href}' class='lwss_selectable lws-woorewards-social-button {$icon}' data-n='{$nonce}' data-s='{$social}' data-p='{$escHash}' data-type='{$social} link'></a>";
		}

		if ('widget' == $origin) {
			if (!$atts['header'])
				$atts['header'] = \lws_get_option('lws_woorewards_social_share_widget_message', __("Share that content on Social Medias", 'woorewards-pro'));
			if (!$atts['text'])
				$atts['text'] = \lws_get_option('lws_woorewards_social_share_widget_text', __("Earn loyalty points by sharing this page with your friends on Social Medias", 'woorewards-pro'));
			if (!$demo) {
				$atts['header'] = \apply_filters('wpml_translate_single_string', $atts['header'], 'Widgets', "WooRewards - Social Share - Title");
				if ($userId) {
					$atts['text'] = \apply_filters('wpml_translate_single_string', $atts['text'], 'Widgets', "WooRewards - Social Share - Description");
				} else {
					$atts['text'] = \lws_get_option('lws_woorewards_social_text_unconnected', __("Only logged in users can earn points for sharing", 'woorewards-pro'));
					$atts['text'] = \apply_filters('wpml_translate_single_string', $atts['text'], 'Widgets', "WooRewards - Social Share - Not connected");
				}
			}
		} else {
			if (!$demo && !$userId && isset($atts['alt']) && $atts['alt']) {
				$atts['text'] = $atts['alt'];
			}
		}

		$this->enqueueScripts($demo);
		$content = '';
		if ($atts['header']) {
			$content .= <<<EOT
			<div class='lwss_selectable lwss_modify lws-woorewards-social_share-description' data-id='lws_woorewards_social_share_widget_message' data-type='Header'>
				<span class='lwss_modify_content'>{$atts['header']}</span>
			</div>
EOT;
		}
		if ($atts['text']) {
			$content .= <<<EOT
			<div class='lwss_selectable lwss_modify lws-woorewards-social_share-text' data-id='lws_woorewards_social_share_widget_text' data-type='Message to users'>
				<span class='lwss_modify_content'>{$atts['text']}</span>
			</div>
EOT;
		}
		return <<<EOT
<div class='lwss_selectable lws-woorewards-social_share-widget' data-type='Main'>
	{$content}
	<div class='lwss_selectable lws-woorewards-social_share-btline' data-type='Buttons Line'>
		{$buttons}
	</div>
</div>
EOT;
	}
}
