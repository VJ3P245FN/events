<?php
namespace LWS\WOOREWARDS\PRO\Ui\AdminScreens;
// don't call the file directly
if (!defined('ABSPATH')) exit();


/** NOT USED ANYMORE - SHOULD BE REMOVED IN A FUTURE VERSION */

class Socials
{
	static function getTab()
	{
		if (isset($_GET['fb_hide']) && $_GET['fb_hide']) {
			if (\wp_verify_nonce(substr($_GET['fb_hide'], 1), 'lws_woorewards_facebook_settings_hidden')) {
				\update_option('lws_woorewards_facebook_settings_hidden', substr($_GET['fb_hide'], 0, 1) == 'y');
				if (\wp_redirect(\remove_query_arg('fb_hide')))
					exit;
			}
		}

		$tab = array(
			'id'	    => 'social_media',
			'title'	  =>  __("Social Media", 'woorewards-pro'),
			'icon'	  => 'lws-icon-network-communication',
			'vertnav' => true,
			'groups'  => array(),
			'delayedFunction' => array(__CLASS__, 'showFacebookSwitcher'),
		);
		if (\get_option('lws_woorewards_facebook_settings_hidden')) {
			$tab['groups'] = array(
				'social_share'   => self::getGroupSocialShare(),
			);
		} else {
			$tab['groups'] = array(
				'fb_settings'    => self::getGroupSettingsFacebook(),
				'social_connect' => self::getGroupSocialConnect(),
				'social_share'   => self::getGroupSocialShare(),
				'fb_logs'        => self::getGroupLogsFacebook(),
			);
		}
		return $tab;
	}

	static function showFacebookSwitcher()
	{
		$text = '';
		$nonce = \esc_attr(\wp_create_nonce('lws_woorewards_facebook_settings_hidden'));
		if (\get_option('lws_woorewards_facebook_settings_hidden')) {
			$text = sprintf('<a href="%2$s">%1$s</a>',
				__("Restore Facebook settings", 'woorewards-pro'),
				\add_query_arg('fb_hide', 'n' . $nonce)
			);
		} else {
			$text = sprintf('<a href="%2$s">%1$s</a>',
				__("Hide Facebook settings", 'woorewards-pro'),
				\add_query_arg('fb_hide', 'y' . $nonce)
			);
		}
		$disclamer = sprintf('%3$s<br/>%4$s<br/>%2$s <a target="_blank" href="%1$s">%1$s</a>',
				'https://developers.facebook.com/support/bugs/199217515444932/',
				__("Last ticket opened on Facebook Support :", 'woorewards-pro'),
				__("Since we released this feature, many bugs happened on facebook’s solutions. Despite the tickets opened on their platform, they failed to address the issues.", 'woorewards-pro'),
				__("Therefore, this feature can only be accessed by customers with already valid settings.", 'woorewards-pro')
			);
		echo "<div style='padding-right:40px;text-align:right;'><small>{$text}<br/>{$disclamer}</div>";
	}

	static function getGroupSettingsFacebook()
	{
		return array(
			'id'     => 'sm_settings',
			'icon'	 => 'lws-icon-facebook2',
			'title'  => __("Facebook Settings", 'woorewards-pro'),
			'text'   => array(
				'join' => '<br/>',
				__("You need to setup different tools if you want your customers to earn points for actions they perform on Facebook", 'woorewards-pro'),
				self::getVerifiedStatus('facebook')[0],
			),
			'fields' => array(
				'guide' => array(
					'id'    => 'facebook_documentation',
					'title' => sprintf('<b>%s</b>', __("Setup Instructions", 'woorewards-pro')),
					'type'  => 'custom',
					'extra' => array(
						'content' => "<div style='font-size:16px'>" . sprintf(__("Please follow this %s to set up you Facebook App and start giving points for Facebook actions .", 'woorewards-pro'), '<a href="https://plugins.longwatchstudio.com/docs/woorewards-4/facebook/" target="_blank">' . __('documentation', 'woorewards-pro') .'</a>') . "</div>",
					)
				),
				'app_id' => array(
					'id'    => 'lws_woorewards_wh_fb_app_id',
					'title' => __("Your App Id", 'woorewards-pro'),
					'type'  => 'text',
					'extra' => array(
						'tooltips' => sprintf(__("Find it in your %s page.", 'woorewards-pro'), '<a href="https://developers.facebook.com/apps/" target="_blank">developers.facebook.com</a>'),
					)
				),
				'page_url' => array(
					'id'    => 'lws_woorewards_wh_fb_page_url',
					'title' => __("Your Facebook page URL", 'woorewards-pro'),
					'type'  => 'text',
					'extra' => array(
						'size' => '60',
						'tooltips' => __("The address of your Facebook Page", 'woorewards-pro'),
					)
				),
				'callback' => array(
					'id'    => 'lws_webhooks_callback',
					'title' => __("Your Callback URL", 'woorewards-pro'),
					'type'  => 'custom',
					'extra' => array(
						'gizmo'    => true,
						'content'  => \apply_filters('lws_format_copypast', \get_rest_url(null, \LWS\WOOREWARDS\PRO\Core\WebHooks::getNamespace('facebook'))),
						'tooltips' => __("Validation requests and Webhook notifications for this object will be sent to this URL.", 'woorewards-pro'),
					)
				),
				'token' => array(
					'id'    => 'lws_woorewards_wh_fb_token',
					'title' => __("Your Verify Token", 'woorewards-pro'),
					'type'  => 'custom',
					'extra' => array(
						'gizmo'    => true,
						'content'  => \apply_filters('lws_format_copypast', \LWS\WOOREWARDS\PRO\Core\WebHooks::getToken()),
						'tooltips' => __("Token that Facebook will echo back to you as part of callback URL verification.", 'woorewards-pro'),
					)
				),
				'sdk' => array(
					'id'    => 'lws_woorewards_wh_fb_sdk_embedded',
					'title' => __("Allow Facebook SDK on your pages", 'woorewards-pro'),
					'type'  => 'box',
					'extra' => array(
						'class' => 'lws_checkbox',
						'tooltips' => __("Required to detect your visitors Facebook accounts. The only reliable way to link facebook accounts to your customers.", 'woorewards-pro'),
					)
				),
				'byname' => array(
					'id'    => 'lws_woorewards_wh_fb_recognition_by_name',
					'title' => __("Allow recognition by name", 'woorewards-pro'),
					'type'  => 'box',
					'extra' => array(
						'class'    => 'lws_checkbox',
						'tooltips' => implode('<br/>', array(
							__("Give points to your customer if his display name match the Facebook name, even if he didn't explicitly link his facebook account with your site.", 'woorewards-pro'),
							__("Name matching is not absolute. It can leads to reward the wrong user.", 'woorewards-pro'),
						)),
					)
				),
			)
		);
	}

	static function getGroupSocialConnect()
	{
		require_once LWS_WOOREWARDS_PRO_INCLUDES . '/ui/widgets/socialnetworkconnect.php';
		$source = \LWS\WOOREWARDS\PRO\Ui\Widgets\SocialNetworkConnect::getSupportedNetworks();
		$defaults = \array_column($source, 'value');

		$text = array(
			'join' => '<br/>',
			__("Your customers need to connect their social medias accounts if they want to earn points. Use this widget to let them connect their accounts.", 'woorewards-pro'),
		);
		$values = \get_option('lws_woorewards_social_connect_medias');
		if (!(is_array($values) && $values))
			$values = $defaults;
		if (in_array('facebook', $values)) {
			if (!\get_option('lws_woorewards_wh_fb_sdk_embedded')) {
				$text[] = array(
					'tag' => 'div style="color:red;"',
					__("You have to enable the Facebook SDK above to have this button working.")
				);
			}
		}

		$subids = array();
		foreach($values as $v){
			$subids['lws_woorewards_social_connect_btn_' . $v] = ('WooRewards Social Network Connect - Button ' . $v);
			$subids['lws_woorewards_social_connect_verified_' . $v] = ('WooRewards Social Network Connect - Verified Account ' . $v);
		}

		return array(
			'id'     => 'social_connect',
			'icon'	=> 'lws-icon-plug-2',
			'title'  => __("Social Media Connect", 'woorewards-pro'),
			'text'   => $text,
			'extra'    => array('doclink' => \LWS\WOOREWARDS\PRO\DocLinks::get('api')),
			'fields' => array(
				'medias' => array(
					'id'    => 'lws_woorewards_social_connect_medias',
					'title' => __("Social Medias connectors", 'woorewards-pro'),
					'type'  => 'lacchecklist',
					'extra' => array(
						'tooltips' => __("Select the social medias connect buttons you want to show.", 'woorewards-pro'),
						'source'   => $source,
						'default'  => $defaults,
					)
				),
				'widget' => array(
					'id' => 'lws_woorewards_social_connect',
					'type' => 'stygen',
					'extra' => array(
						'purpose' => 'filter',
						'template' => 'wr_social_connect',
						'html' => false,
						'css' => LWS_WOOREWARDS_PRO_CSS . '/templates/socialnetworkconnect.css',
						'subids' => $subids,
					)
				),
			)
		);
	}

	static function getGroupSocialShare()
	{
		return array(
			'id' => 'social_share',
			'icon'	=> 'lws-icon-network-communication',
			'title' => __("Social Share", 'woorewards-pro'),
			'text' => __("With this Widget, customers can share a page link on social media.", 'woorewards-pro'),
			'extra'    => array('doclink' => \LWS\WOOREWARDS\PRO\DocLinks::get('social')),
			'fields' => array(
				array(
					'id' => 'lws_woorewards_smshare_socialmedias',
					'title' => __("Social Media", 'woorewards-pro'),
					'type' => 'lacchecklist',
					'extra' => array(
						'source'  => \LWS\WOOREWARDS\PRO\Core\Socials::instance()->asDataSource(),
						'default' => \LWS\WOOREWARDS\PRO\Core\Socials::instance()->getSupportedNetworks(),
						'help' => __("Select the social medias for which you want share buttons. Save your settings to see them appear on the styling tool", 'woorewards-pro'),
					)
				),
				'smdispunc' => array(
					'id'    => 'lws_woorewards_social_display_unconnected',
					'title' => __("Display Buttons if users not connected", 'woorewards-pro'),
					'type'  => 'box',
					'extra' => array(
						'default' => '',
						'class' => 'lws_checkbox',
					)
				),
				'smdisconnected'    => array(
					'id' => 'lws_woorewards_social_text_unconnected',
					'title' => __("Text displayed if user not connected", 'woorewards-pro'),
					'type' => 'text',
					'extra' => array(
						'size' => '50',
						'placeholder' => __("Only logged in users can earn points for sharing", 'woorewards-pro'),
						'wpml' => "WooRewards - Social Share - Not connected",
					)
				),
				'smpopup' => array(
					'id'    => 'lws_woorewards_social_share_popup',
					'title' => __("Open share dialog as popup", 'woorewards-pro'),
					'type'  => 'box',
					'extra' => array(
						'default' => '',
						'class' => 'lws_checkbox',
						'tooltips' => __("Default behavior opens share dialog in a new tab.", 'woorewards-pro')
					)
				),
				array(
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
				)
			)
		);
	}

	static function getGroupLogsFacebook()
	{
		$lookback = \date_create('now', function_exists('\wp_timezone') ? \wp_timezone() : null)->setTime(0, 0);
		$lookback->sub(new \DateInterval(\get_option('lws_woorewards_wh_lookback_period', 'P1D')));
		global $wpdb;
		$likes = $wpdb->get_var($wpdb->prepare(
			"SELECT COUNT(*) FROM {$wpdb->lwsWebhooksEvents} WHERE creation >= DATE(%s)",
			$lookback->format('Y-m-d')
		));

		return array(
			'id'     => 'logs',
			'title'  => __("Facebook Logs", 'woorewards-pro'),
			'icon'	 => 'lws-icon-bug',
			'text'   => sprintf(__("%d new like(s) since %s.", 'woorewards-pro'), $likes, \date_i18n(\get_option('date_format') . ' ' . \get_option('time_format'), $lookback->getTimestamp()+$lookback->getOffset())),
			'fields' => array(
				'check' => array(
					'id'    => 'lws_woorewards_wh_fb_last_subscription',
					'title' => __("Last successfull subscription check (GMT)", 'woorewards-pro'),
					'type'  => 'text',
					'extra' => array(
						'readonly' => true,
					)
				),
				'date' => array(
					'id'    => 'lws_woorewards_wh_fb_last_event_date',
					'title' => __("Last Webhooks call date (GMT)", 'woorewards-pro'),
					'type'  => 'text',
					'extra' => array(
						'readonly' => true,
					)
				),
				'post' => array(
					'id'    => 'lws_woorewards_wh_fb_last_event_post',
					'title' => __("Last Webhooks call content", 'woorewards-pro'),
					'type'  => 'textarea',
					'extra' => array(
						'readonly' => true,
						'rows'     => 5,
					)
				),
			)
		);
	}

	/**	Say about network settings,
	 *	If social event collect is confirmed.
	 *	@return false|array [html string, bool]. */
	static function getVerifiedStatus($network)
	{
		if ('facebook' == $network) {
			$verify    = \get_option('lws_woorewards_wh_fb_event_verify');
			$confirmed = false;
			$status    = 'unknown';
			$class     = 'wr-wh-fb-verified';

			if ($verify) {
				if( \get_option('lws_woorewards_wh_fb_app_id') != $verify['app_id'] ){
					$class .= ' obsolete';
					$status = implode('<br/>', array(
						__("Your settings has never been verified for this App Id.", 'woorewards-pro'),
						__("After all your Facebook App settings are done, please like one of your page post.", 'woorewards-pro'),
					));
				}else {
					$confirmed = true;
					$class .= ' confirmed';
					$status = __("Your settings has been verified.", 'woorewards-pro');
				}
			}else {
				$class .= ' never';
				$status = implode('<br/>', array(
					__("Your settings have never been verified.", 'woorewards-pro'),
					__("After finishing all your Facebook App settings, please like one of your page posts to verify the connection.", 'woorewards-pro'),
				));
			}
			return array("<span class='{$class}'>{$status}</span>", $confirmed);
		}
		else {
			return false;
		}
	}
}
