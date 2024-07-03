<?php
namespace LWS\WOOREWARDS\PRO\Ui\Legacy;

// don't call the file directly
if( !defined( 'ABSPATH' ) ) exit();

/** Display a link to a site page for sharing on social networks.
 *	Could be a simple link or a QR code. */
class ReferralWidget extends \LWS\WOOREWARDS\Ui\Widget
{
	private $stygen = false;
	
	public static function install()
	{
		self::register(get_class());
		$me = new self(false);

		/* Very old: Keep for compatiblity purposes */
		\add_shortcode('lws_referral', array($me, 'shortcode'));
		/* before v4.9.8 Shortcode */
		\add_shortcode('lws_sponsorship_link', array($me, 'shortcode'));

		\add_filter('lws_adminpanel_stygen_content_get_'.'wr_referral', array($me, 'template'));
		\add_action('wp_enqueue_scripts', array($me, 'registerScripts'));
		\add_action('admin_enqueue_scripts', array($me, 'registerScripts'));

		/** Admin */
		\add_filter('lws_woorewards_legacy_shortcodes', array($me, 'admin'), 20);
	}

	public function admin($fields)
	{
		$fields['sponsorshiplink'] = array(
			'id' => 'lws_woorewards_sc_link_sponsorship',
			'title' => __("Referral Link", 'woorewards-pro'),
			'type' => 'shortcode',
			'extra' => array(
				'shortcode' => '[lws_sponsorship_link header="your header" display="link"]',
				'description' =>  __("This shortcode shows to customers a referral link or QR Code.", 'woorewards-pro'),
				'options'   => array(
					array(
						'option' => 'header',
						'desc' => __("The text displayed before the referral link or QR Code.", 'woorewards-pro'),
					),
					array(
						'option' => 'display',
						'desc' => array(
							__("Select the display type you want to show to customers : ", 'woorewards-pro'),
							array('tag' => 'ul',
								array('both'  , __("QR Code and Link will be displayed", 'woorewards-pro')),
								array('qrcode', __("Only the QR Code will be displayed", 'woorewards-pro')),
								array('link'  , __("Only the Link will be displayed", 'woorewards-pro')),
							),
						),
					),
					array(
						'option' => 'url',
						'desc' => __("By default, the shortcode shares the url of the page it’s displayed on. You can override that setting by setting an url in this option.", 'woorewards-pro'),
					),
				),
				'style_url' => '#lws_group_targetable_referral',
			)
		);
		return $fields;
	}

	function registerScripts()
	{
		\wp_register_script('woorewards-referral',LWS_WOOREWARDS_PRO_JS.'/legacy/referral.js',array('jquery'),LWS_WOOREWARDS_PRO_VERSION);
		\wp_register_style('woorewards-referral', LWS_WOOREWARDS_PRO_CSS.'/templates/referral.css?stygen=lws_woorewards_referral_template', array(), LWS_WOOREWARDS_PRO_VERSION);
	}

	protected function enqueueScripts()
	{
		\wp_enqueue_style('lws-icons');
		\wp_enqueue_script('lws-qrcode-js');
		if( !$this->stygen )
		{
			\wp_enqueue_script('woorewards-referral');
		}
		\wp_enqueue_style('woorewards-referral');
	}

	/** Will be instanciated by WordPress at need */
	public function __construct($asWidget=true)
	{
		if( $asWidget )
		{
			parent::__construct(
				'lws_woorewards_referral',
				__("MyRewards Referral Link", 'woorewards-pro'),
				array(
					'description' => __("Provide a Referral link to your customers.", 'woorewards-pro')
				)
			);
		}
	}

	function template($snippet=''){
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
		if( !empty(\get_current_user_id()) )
		{
			echo $args['before_widget'];
			if( is_array($instance) && isset($instance['title']) && !empty($instance['title']) )
			{
				echo $args['before_title'];
				echo \apply_filters('widget_title', $instance['title'], $instance);
				echo $args['after_title'];
			}
			if( isset($instance['url']) && !empty($instance['url']) )
			$instance['url'] = \apply_filters('wpml_translate_single_string', $instance['url'], 'Widgets', "WooRewards - Sponsorship Widget - Redirection");
			echo $this->shortcode($instance);
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

		\do_action('wpml_register_single_string', 'Widgets', "WooRewards - Sponsorship Widget - Header", $new_instance['header']);
		if( !empty($new_instance['url']) )
			\do_action('wpml_register_single_string', 'Widgets', "WooRewards - Sponsorship Widget - Redirection", $new_instance['url']);

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
			\esc_attr(_x("Share that referral link", "frontend widget", 'woorewards-pro'))
		);
		// behavior
		$this->eFormFieldRadio(
			$this->get_field_id('display'),
			__("Display", 'woorewards-pro'),
			$this->get_field_name('display'),
			array(
				'link'	=> __("Link", 'woorewards-pro'),
				'qrcode'=> __("QR Code", 'woorewards-pro'),
				'both'	=> __("Both", 'woorewards-pro'),
			),
			$instance['display']
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
		return array(
			'title'  => '',
			'header'  => '',
			'url'  => '',
			'display'  => '',
		);
	}

	/** @brief shortcode [lws_referral]
	 *	 */
	public function shortcode($atts=array(), $content='')
	{
		$this->enqueueScripts();
		$atts = \wp_parse_args($atts, $this->defaultArgs());
		if( empty($userId = \get_current_user_id()) )
			return $content;

		if( !isset($atts['header']) || empty($atts['header']) )
		$atts['header'] = \lws_get_option('lws_woorewards_referral_widget_message', __("Share that Referral link", 'woorewards-pro'));
		if( !$this->stygen )
			$atts['header'] = \apply_filters('wpml_translate_single_string', $atts['header'], 'Widgets', "WooRewards - Sponsorship Widget - Header");
		if( !isset($atts['display']) || empty($atts['display']) )
			$atts['display'] = \lws_get_option('lws_woorewards_sponsorship_link_display', 'link');

		$url = '';
		if( isset($atts['url']) && $atts['url'] )
			$url = \add_query_arg('referral', $this->getOrCreateToken($userId), $atts['url']);
		else if( $defpage = \get_option('lws_woorewards_sponsorship_link_page') )
			$url = \add_query_arg('referral', $this->getOrCreateToken($userId), \get_permalink($defpage));
		else if( $this->stygen )
			$url = \add_query_arg('referral', $this->getOrCreateToken($userId), \home_url());
		else // current page
			$url = \add_query_arg('referral', $this->getOrCreateToken($userId), \LWS\Adminpanel\Tools\Conveniences::getCurrentPermalink());

		if (!\is_admin() && !$this->stygen && \get_option('lws_woorewards_sponsorship_tinify_enabled', ''))
			$url = \LWS\WOOREWARDS\Ui\Shortcodes\ReferralLink::tinifyUrl($url);

		$content = 	"<div class='lwss_selectable lws-woorewards-referral-widget' data-type='Main'>";
		$content .= "<div class='lwss_selectable lwss_modify lws-woorewards-referral-description' data-id='lws_woorewards_referral_widget_message' data-type='Header'>";
		$content .= "<span class='lwss_modify_content'>{$atts['header']}</span>";
		$content .= "</div>";

		if($atts['display']=='qrcode' || $atts['display']=='both')
		{
			$link = \esc_attr($url);
			$content .= "<div class='lwss_selectable lws-woorewards-spqrcode-wrapper' data-type='QR Code Wrapper'>";
			$content .= "<div class='lwss_selectable lws-woorewards-spqrcode qrcode' tabindex='0' data-type='QR Code' data-qrcode='{$link}'></div>";
			$content .= "<div class='lwss_selectable lws-woorewards-spqrcode-copy-icon lws-icon lws-icon-copy qrcopy' data-type='Copy QR Code'></div>";
			$content .= "</div>";
		}
		if($atts['display']=='link' || $atts['display']=='both')
		{
			$link = \htmlentities($url);
			$content .= "<div class='lwss_selectable lws-woorewards-referral-field-copy lws_referral_value_copy' data-type='Referral link'>";
			$content .= "<div class='lwss_selectable lws-woorewards-referral-field-copy-text content' tabindex='0' data-type='Link'>{$link}</div>";
			$content .= "<div class='lwss_selectable lws-woorewards-referral-field-copy-icon lws-icon lws-icon-copy copy' data-type='Copy button'></div>";
			$content .= "</div>";
		}
		$content .= "</div>";

		return $content;
	}

	public function getOrCreateToken($userId)
	{
		static $proxy = false;
		if (!$proxy)
			$proxy = new \LWS\WOOREWARDS\Ui\Shortcodes\ReferralLink();
		return $proxy->getOrCreateToken($userId);
	}
}