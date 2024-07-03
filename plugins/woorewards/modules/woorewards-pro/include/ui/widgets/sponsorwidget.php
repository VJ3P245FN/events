<?php
namespace LWS\WOOREWARDS\PRO\Ui\Widgets;

// don't call the file directly
if( !defined( 'ABSPATH' ) ) exit();

/** Provide a widget for customer to input email for sponsorship.
 * Can be used as a Widget, a Shortcode [lws_sponsorship] or a Guttenberg block. */
class SponsorWidget extends \LWS\WOOREWARDS\Ui\Widget
{
	private $stygen = false;

	public static function install()
	{
		self::register(get_class());
		$me = new self(false);
		\add_action('wp_ajax_lws_woorewards_add_sponsorship', array($me, 'request'));
		\add_action('wp_ajax_nopriv_lws_woorewards_add_sponsorship', array($me, 'request'));

		\add_shortcode('lws_sponsorship', array($me, 'shortcode'));
		\add_shortcode('lws_sponsorship_nonce_input', array($me, 'getNonceInput'));

		\add_filter('lws_adminpanel_stygen_content_get_'.'wr_sponsorship', array($me, 'template'));

		\add_action('wp_enqueue_scripts', array($me, 'registerScripts'));
		\add_action('admin_enqueue_scripts', array($me, 'registerScripts'));

		/** Admin */
		\add_filter('lws_woorewards_referral_shortcodes', array($me, 'admin'), 20);
	}

	/** parse request then addRelationship, then send mail to sponsor.
	 * echo result as json and die. */
	function request()
	{
		if( isset($_REQUEST['sponsored_email']) && isset($_REQUEST['sponsorship_nonce']) )
		{
			if( \wp_verify_nonce($_REQUEST['sponsorship_nonce'], 'lws_woorewards_sponsorship_email') )
			{
				$user = \wp_get_current_user();
				if( !$user || !$user->ID )
				{
					// find user by email
					if( isset($_REQUEST['sponsor_email']) && ($email = \sanitize_email($_REQUEST['sponsor_email'])) )
						$user = \get_user_by('email', $email);

					if( !$user || !$user->ID )
					{
						$redirect = \get_permalink(\get_option('lws_woorewards_sponsorhip_user_notfound', false));
						\wp_send_json(array(
							'succes'   => false,
							'error'    => __("Unknown user.", 'woorewards-pro'),
							'redirect' => ($redirect ? $redirect : ''),
						));
					}
				}

				$sponsorship = new \LWS\WOOREWARDS\Core\Sponsorship();
				$result = $sponsorship->addRelationship($user, $_REQUEST['sponsored_email']);
				if( \is_wp_error($result) )
				{
					\wp_send_json(array(
						'succes' => false,
						'error'  => $result->get_error_message()
					));
				}
				else if( !$result )
				{
					\wp_send_json(array(
						'succes' => false,
						'error'  => __("Unexpected error. Please retry later.", 'woorewards-pro')
					));
				}
				else
				{
					\wp_send_json(array(
						'succes'  => true,
						'message' => \apply_filters('wpml_translate_single_string',
							\lws_get_option('lws_wooreward_sponsorship_success', __("A mail has been sent to your friend about us.", 'woorewards-pro')),
							'Widgets',
							"WooRewards - Sponsor Widget - Success"
						)
					));
				}
			}
		}
	}

	public function admin($fields)
	{
		$fields['mailsponsorship'] = array(
			'id' => 'lws_woorewards_sc_email_sponsorship',
			'title' => __("Email Referral", 'woorewards-pro'),
			'type' => 'shortcode',
			'extra' => array(
				'shortcode' => '[lws_sponsorship header="your header" button="Send" unlogged="true"]',
				'description' =>  __("This shortcode shows to customers a referral email form.", 'woorewards-pro'),
				'options'   => array(
					array(
						'option' => 'header',
						'desc' => __("The text displayed before the email referral.", 'woorewards-pro'),
					),
					array(
						'option' => 'button',
						'desc' => __("The text displayed in the Submit button", 'woorewards-pro'),
					),
					array(
						'option'  => 'unlogged',
						'desc'    => __('(Optional) If set to ON, unlogged users will be able to use the email referral.', 'woorewards-pro'),
					),
					array(
						'option'  => 'fallback',
						'desc'    => __('(Optional) A text to display for unlogged users instead of the form.', 'woorewards-pro'),
						'example' => sprintf('[lws_sponsorship fallback="%s"]', \esc_attr(__("Please log in if you want to refer your friends", 'woorewards-pro'))),
					),
				),
			)
		);
		$fields['sp_mail_widget'] = array(
			'id' => 'lws_woorewards_sponsor_template',
			'type' => 'stygen',
			'extra' => array(
				'purpose' => 'filter',
				'template' => 'wr_sponsorship',
				'html' => false,
				'css' => LWS_WOOREWARDS_PRO_CSS . '/templates/sponsor.css',
				'subids' => array(
					'lws_woorewards_sponsor_widget_title' => "WooRewards - Sponsor Widget - Title",
					'lws_woorewards_sponsor_widget_submit' => "WooRewards - Sponsor Widget - Button",
					'lws_woorewards_sponsor_widget_placeholder' => "WooRewards - Sponsor Widget - Placeholder",
					'lws_wooreward_sponsorship_success' => "WooRewards - Sponsor Widget - Success",
				)
			)
		);
		return $fields;
	}

	function registerScripts()
	{
		\wp_register_script('woorewards-sponsor', LWS_WOOREWARDS_PRO_JS.'/sponsor.js', array('jquery', 'lws-tools'), LWS_WOOREWARDS_PRO_VERSION);
		\wp_register_style('woorewards-sponsor', LWS_WOOREWARDS_PRO_CSS.'/templates/sponsor.css?stygen=lws_woorewards_sponsor_template', array(), LWS_WOOREWARDS_PRO_VERSION);
	}

	protected function enqueueScripts()
	{
		if (!$this->stygen) {
			\wp_enqueue_script('woorewards-sponsor');
		}
		\wp_enqueue_style('woorewards-sponsor');
	}

	/** Will be instanciated by WordPress at need */
	public function __construct($asWidget=true)
	{
		if ($asWidget) {
			parent::__construct(
				'lws_woorewards_sponsorship',
				__("MyRewards Referral Mailing", 'woorewards-pro'),
				array(
					'description' => __("Let your customers refer new customers.", 'woorewards-pro')
				)
			);
		}
	}

	public function template($snippet='')
	{
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
		if( \get_current_user_id() || \get_option('lws_woorewards_sponsorship_allow_unlogged', '') )
		{
			$instance['unlogged'] = 'on';
			echo $args['before_widget'];
			echo $args['before_title'];
			echo \apply_filters('widget_title', empty($instance['title']) ? _x("Referral", "frontend widget", 'woorewards-pro') : $instance['title'], $instance);
			echo $args['after_title'];
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

		\do_action('wpml_register_single_string', 'Widgets', "WooRewards - Sponsor Widget - Title", $new_instance['header']);
		\do_action('wpml_register_single_string', 'Widgets', "WooRewards - Sponsor Widget - Button", $new_instance['button']);

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
			\esc_attr($instance['title']),
			\esc_attr(_x("Referral", "frontend widget", 'woorewards-pro'))
		);
		// header
		$this->eFormFieldText(
			$this->get_field_id('header'),
			__("Header", 'woorewards-pro'),
			$this->get_field_name('header'),
			\esc_attr($instance['header']),
			\esc_attr(_x("Refer your friend", "frontend widget", 'woorewards-pro'))
		);
	}

	protected function defaultArgs()
	{
		return array(
			'title'  => '',
			'header' => '',
			'button' => '',
			'unlogged' => \get_option('lws_woorewards_sponsorship_allow_unlogged', ''),
			'fallback' => '',
		);
	}

	public function getNonceInput($atts=array(), $content='')
	{
		$nonce = \esc_attr(\wp_create_nonce('lws_woorewards_sponsorship_email'));
		return "<input type='hidden' class='lws_woorewards_sponsorship_nonce' name='sponsorship_nonce' value='{$nonce}'>";
	}

	/** @brief shortcode [lws_sponsorship]
	 *	Display input box to set sponsored email, then a div for server answer. */
	public function shortcode($atts=array(), $content='')
	{
		$atts = \LWS\Adminpanel\Tools\Conveniences::sanitizeAttr(\shortcode_atts($this->defaultArgs(), $atts, 'lws_sponsorship'));
		if( empty($atts['header']) )
			$atts['header'] = \lws_get_option('lws_woorewards_sponsor_widget_title', __("Refer your friend(s)", 'woorewards-pro'));
		if( empty($atts['button']) )
			$atts['button'] = \lws_get_option('lws_woorewards_sponsor_widget_submit', __("Submit", 'woorewards-pro'));
		$ph = \esc_attr(\lws_get_option('lws_woorewards_sponsor_widget_placeholder', __("my.friend@example.com, my.other.friend@example.com", 'woorewards-pro')));
		$phs = \esc_attr(\lws_get_option('lws_woorewards_sponsor_widget_sponsor', __("Your email address", 'woorewards-pro')));

		if( !$this->stygen ) // not demo
		{
			$atts['header'] = \apply_filters('wpml_translate_single_string', $atts['header'], 'Widgets', "WooRewards - Referral Widget - Title");
			$atts['button'] = \apply_filters('wpml_translate_single_string', $atts['button'], 'Widgets', "WooRewards - Referral Widget - Button");
			$phs = \apply_filters('wpml_translate_single_string', $phs, 'Widgets', "WooRewards - Sponsor Widget - Referral placeholder");
			$ph = \apply_filters('wpml_translate_single_string', $ph, 'Widgets', "WooRewards - Sponsor Widget - Referral Placeholder");
		}

		$this->enqueueScripts();

		$errMsg = '';
		if (!$this->stygen) {
			$errMsg = sprintf(
				' data-wait="%s" data-err0="%s" data-err1="%s"',
				\esc_attr(__("Sending the referral request ...", 'woorewards-pro')),
				\esc_attr(__("An internal server error occured. Please retry later.", 'woorewards-pro')),
				\esc_attr(__("Server error", 'woorewards-pro'))
			);
		}

		$hidden = '';
		$form = "<div class='lwss_selectable lws_woorewards_sponsorship_widget' data-type='Main'{$errMsg}>";

		$form .= "<p class='lwss_selectable lwss_modify lws_woorewards_sponsorship_description' data-id='lws_woorewards_sponsor_widget_title' data-type='Header'>";
		$form .= "<span class='lwss_modify_content'>{$atts['header']}</span>";
		$form .= "</p><div class='lwss_selectable lws_woorewards_sponsorship_form' data-type='Form'>";
		if( empty($user = \wp_get_current_user()) || empty($user->ID) )
		{
			if (\LWS\Adminpanel\Tools\Conveniences::argIsTrue($atts['unlogged'])) {
				$form .= "<div  class='lwss_selectable lws_woorewards_sponsorship_input' data-type='Input'>";
				$form .= "<input class='lwss_selectable lwss_modify lws_woorewards_sponsorship_host_field' data-type='Field' data-id='lws_woorewards_sponsor_widget_sponsor' name='sponsor_email' type='email' placeholder='$phs' />";
				$form .= "</div>";
			} else {
				if ($atts['fallback']) {
					$txt = \html_entity_decode($atts['fallback']);
				} else {
					$txt = \lws_get_option('lws_wooreward_sponsorship_nouser', __("Please log in if you want to refer your friends", 'woorewards-pro'));
					$txt = \apply_filters('wpml_translate_single_string', $txt, 'Widgets', "WooRewards - Sponsor Widget - Need log in");
				}
				$form .= "<p class='lws_wooreward_sponsorship_fallback'>{$txt}</p>";
			}
		}
		if (($user && $user->ID) || \LWS\Adminpanel\Tools\Conveniences::argIsTrue($atts['unlogged']))
		{
			$form .= $this->getNonceInput();
			$form .= "<div class='lwss_selectable lws_woorewards_sponsorship_input' data-type='Input'>";
			$form .= "<input class='lwss_selectable lwss_modify lws_woorewards_sponsorship_field' data-type='Field' data-id='lws_woorewards_sponsor_widget_placeholder' name='sponsored_email' type='email' placeholder='$ph' />";
			$form .= "</div>";
			$form .= "<div class='lwss_selectable lwss_modify lws_woorewards_sponsorship_submit' data-id='lws_woorewards_sponsor_widget_submit' data-type='Submit'>";
			$form .= "<span class='lwss_modify_content'>{$atts['button']}</span>";
			$form .= "</div>";
		}
		$form .= "</div>";
		$hidden = " style='display:none;'";
		if ($this->stygen) {
			$hidden =  '';
			if (!$content) {
				$content = \lws_get_option('lws_wooreward_sponsorship_success', __("A mail has been sent to your friend about us.", 'woorewards-pro'));
			}
		}
		$form .= "<p class='lwss_selectable lwss_modify lws_woorewards_sponsorship_feedback' data-type='Feedback' data-id='lws_wooreward_sponsorship_success'$hidden>";
		$form .= "<span class='lwss_modify_content'>{$content}</span>";
		$form .= "</p>";
		$form .= "</div>";
		return $form;
	}
}