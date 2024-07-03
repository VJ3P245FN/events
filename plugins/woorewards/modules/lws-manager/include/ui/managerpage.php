<?php
namespace LWS\Manager\Ui;

// don't call the file directly
if( !defined( 'ABSPATH' ) ) exit();
require_once LWS_MANAGER_INCLUDES . '/ui/page.php';

/** Add a special page to manage the plugin installation. */
class ManagerPage extends \LWS\Manager\Ui\Page
{
	protected $license = false;
	protected $uuid    = '';

	function suffixPage(string $page){return $page.'_lic';}
	function getTabId(){return 'lic';}

	/**	@param $targetPage (bool|string|array) if false, create a dedicated page; else insert in specifed page[s]. */
	static function install($file, $adminPageId, $uuid, $def=false, $targetPage=false)
	{
		$me = new self($file, $adminPageId, $targetPage, true);
		$me->setProductId($uuid);

		\add_filter('lws_adm_menu_license_url', array($me, 'getScreenUrl'), 10, 3);
		\add_filter('lws_adm_menu_license_status', array($me, 'getManagerStatus'), 10, 3);
		/** Get all under license plugins
		 *	@param array */
		\add_filter('lws_adminpanel_licenses_status', array($me, 'filterManagers'));
		/** Migration
		 *	@param string plugin slug */
		\add_action('lws_adminpanel_licenses_migration', array($me, 'migrateFromV3'), 10, 1);
		/** after update, link to admin page */
		\add_filter('install_plugin_complete_actions', array($me, 'afterInstallActions'), 10, 3);
		\add_filter('update_plugin_complete_actions', array($me, 'afterUpdateActions'), 10, 2);

		if( isset($_REQUEST['option_page']) )
		{
			\add_filter('pre_update_option_'.$me->getManager()->getKeyOption(), array($me, 'preUpdateKey'), 10, 3);
			\add_filter('pre_update_option_'.$me->getActionOption(), array($me, 'preUpdateAction'), 10, 3);
			\add_filter('pre_update_option_'.$me->getRecheckOption(), array($me, 'preUpdateRecheck'), 10, 3);
		}

		if( $def )
			define($def, $me->getManager()->isRunning());
		$me->getManager()->installUpdater();
		$manager =& $me->getManager();
		\LWS\Manager\API::register($manager, $uuid);
	}

	static function installAddon($file, $masterSlug, $addonUuid, $def)
	{
		$me = new self($file, '');
		$me->setProductId($addonUuid);
		\add_filter('lws_adm_addons_'.$masterSlug, array($me, 'listAddons'));

		if (isset($_REQUEST['option_page'])) {
			\add_filter('pre_update_option_' . $me->getAddonActionOption(), array($me, 'preUpdateAddonAction'), 10, 3);
		}
		if ($def) {
			define($def, $me->getManager()->isRunning());
		}
		$me->getManager()->installUpdater();
		$manager =& $me->getManager();
		\LWS\Manager\API::register($manager, $addonUuid);
	}

	static function registerAddon($file, $masterSlug, $addonUuid)
	{
		$me = new self($file, '');
		$me->setProductId($addonUuid);
		\add_filter('lws_adm_addons_'.$masterSlug, array($me, 'listAddons'));
		$me->noKey = true;
		$me->getManager()->installUpdater(true);
	}

	function listAddons($list)
	{
		$list[$this->getManager()->getSlug()] = $this;
		return $list;
	}

	/** always empty the value after usage. */
	function preUpdateAddonAction($value, $old=false, $option=false)
	{
		$manager =& $this->getManager();
		$expected = 'toggle_' . $manager->getSlug();
		if( $expected == $value )
		{
			if( $manager->isActive() )
			{
				$manager->deactivate();
			}
			else
			{
				$opt = $manager->getKeyOption();
				$key = isset($_POST[$opt]) ? \sanitize_text_field($_POST[$opt]) : false;
				if( $key )
				{
					if( $manager->activate($key, $manager->getKey(), false) )
						$manager->updateKey($key);
				}
				else
				{
					\lws_admin_add_notice_once('lws_addon_toggle', __("Please set a the extension license key to activate it.", 'lwsmanager'), array('level'=>'error'));
				}
			}
		}
		return '';
	}

	/** always empty the value after usage. */
	function preUpdateRecheck($value, $old=false, $option=false)
	{
		if ('recheck' == $value) {
			$result = $this->getManager()->check(null);
			if (true === $result) {
				if ($this->getManager()->isRunning()) {
					\lws_admin_add_notice_once('lws_license_force_check',
						__("License resumed. Enjoy your plugin.", 'lwsmanager'),
						array('level'=>'success')
					);
				} else {
					\lws_admin_add_notice_once('lws_license_force_check',
						__("Cannot resume your license. Please ensure you renewed your subscription first.", 'lwsmanager'),
						array('level'=>'notice')
					);
				}
			} elseif (false === $result) {
				\lws_admin_add_notice_once('lws_license_force_check',
					__("Your license seems to be not active anymore. Perhaps your subscription has been cancelled.", 'lwsmanager'),
					array('level'=>'warning')
				);
			} else {
				\lws_admin_add_notice_once('lws_license_force_check',
					__("There was a problem establishing a connection to the license service. Please retry later.", 'lwsmanager'),
					array('level'=>'error')
				);
			}
		}
		return '';
	}

	/** always empty the value after usage. */
	function preUpdateAction($value, $old=false, $option=false)
	{
		if( 'deactivate' == $value )
		{
			$this->getManager()->deactivate();
		}
		else if( 'trial' == $value )
		{
			$this->getManager()->startTry();
		}
		return '';
	}

	function preUpdateKey($value, $old=false, $option=false)
	{
		$actOpt = $this->getActionOption();
		$action = isset($_POST[$actOpt]) ? !empty($_POST[$actOpt]) : false;
		if($action) return $old;

		static $mutex = false; // prevent recursion
		if ($mutex) return $value;
		$mutex = true;

		$value = \trim($value);
		if (!$this->getManager()->activate($value, $old)) {
			$value = $old;
		} else {
			// in case of multi-site
			$this->getManager()->updateGlobalOption($option, $value);
		}

		$mutex = false;
		return $value;
	}

	function getManagerStatus($status, $mainId, $pageId)
	{
		if( $this->pageQueryArgs && $this->myPages )
		{
			if( isset($this->myPages[$pageId]) )
				$status = $this->_getStatus($status);
		}
		return $status;
	}

	function filterManagers($managers=array())
	{
		$manager =& $this->getManager();
		$managers[$manager->getSlug()] = $this->_getStatus($manager->getPluginInfo());
		return $managers;
	}

	private function _getStatus($status=array())
	{
		if( !\is_array($status) )
			$status = array();
		$manager =& $this->getManager();

		$status['lite']    = $manager->isLite();
		$status['active']  = $manager->maybeActive();
		$status['trial']   = !$status['active'] && $manager->isTrial();
		$status['expired'] = ($status['active'] ? $manager->isPremiumExpired() : $manager->isTrialExpired());

		if( $status['trial'] && !$status['expired'] && ($e = $manager->getTrialEnding()) )
			$status['soon'] = $e->diff(\date_create(), true)->format('%a');

		$status['subscription'] = $manager->getSubscriptionEnd();
		if( $status['subscription'] )
			$status['subscription'] = $status['subscription']->format('Y-m-d');

		$status['trial_available'] = $manager->isTrialAvailable();

		return $status;
	}

	protected function getTab($full=true)
	{
		$page = array(
			'id'     => $this->getTabId(),
			'title'  => __("License Management", 'lwsmanager'),
			'icon'   => 'lws-icon-key',
			'nosave' => true,
		);
		if (!$full)
			return $page;

		$manager =& $this->getManager();
		$groups = array();

		if( $manager->isLite() )
		{
			if( $manager->isRunning() )
				$groups = $this->addGroupUpdateInfo($groups);

			if( $manager->isLiteAvailable() )
				$groups = $this->addGroupFreeVersion($groups);
		}

		if( $manager->isActive() )
		{
			$groups = $this->addGroupActivePro($groups);
			if( $manager->isSubscription() )
				$groups = $this->addGroupSubscription($groups);
		}
		else
		{
			$groups = $this->addGroupIdleKey($groups);
			$groups = $this->addGroupPurchaisePro($groups);

			if( !$manager->isTrial() && $manager->isTrialAvailable() )
				$groups = $this->addGroupTeaseTrial($groups);
		}

		if ($this->isAlternativeEnabled()) {
			$groups = $this->addGroupFingerprint($groups);
		}
		$groups = $this->addGroupAddons($groups);
		$groups = $this->addGroupAddonTeasers($groups);

		if (isset($_GET['lws-log']) && 'lic' == $_GET['lws-log']) {
			$groups = $this->addGroupLogs($groups);
		}

		\ksort($groups);
		$page['groups'] = $groups;
		$page['delayedFunction'] = array($this, 'smallLinks');
		return $page;
	}

	function smallLinks()
	{
		$links = array(
			array(
				\esc_attr(\add_query_arg(array('lws-log'=>'lic'))),
				__('Show logs', 'lwsmanager'),
			),
			array(
				\esc_attr(\add_query_arg(array('lws-alt'=>'on'))),
				__('Alternative activation', 'lwsmanager'),
			),
		);
		echo <<<EOT
<div style='padding:20px;gap:20px;text-align:right;'>
	<small>
		<a href='{$links[0][0]}'>{$links[0][1]}</a>
		/
		<a href='{$links[1][0]}'>{$links[1][1]}</a>
	</small>
</div>
EOT;
	}

	protected function isAlternativeEnabled()
	{
		if (isset($_GET['lws-alt']) && 'on' == $_GET['lws-alt'])
			return true;
		else
			return !empty(\get_option('lws_lic_alternative_enabled', ''));
	}

	private function getServerIp()
	{
		$ip = \gethostname();
		if ($ip) {
			$ip = \gethostbynamel($ip);
			if ($ip)
				$ip = implode(', ', $ip);
		}
		return $ip;
	}

	/** Propose the license activation alternative. */
	private function addGroupFingerprint($groups)
	{
		$manager =& $this->getManager();
		if ($key = $manager->getKey())
			$key = \apply_filters('lws_format_copypast', $key);
		else
			$key = sprintf('<b>%s</b>', __("The key you received by email with your order.", 'lwsmanager'));

		$formLink = 'https://plugins.longwatchstudio.com/remote-plugin-activation/';
		if (\defined('LWS_DEV_ALT') && LWS_DEV_ALT)
			$formLink = LWS_DEV_ALT;

		$groups['10.alt'] = array(
			'id'     => 'addons',
			'icon'   => 'lws-icon-click',
			'title'  => __("Remote activation", 'lwsmanager'),
			'color'  => '#e16921',
			'class'  => 'onecol',
			//~ 'class'  => 'half',
			'text'   => implode('<br/>', array(
				__("If your server can't reach our license server, it's probably because it's blocked by our firewall.", 'lwsmanager'),
				__("It can happen if you use shared hosting and if another website sharing your IP address is fraudulent.", 'lwsmanager'),
				__("You can use the following procedure to activate your license remotely. You will be notified by email when new versions are available.", 'lwsmanager'),
				__("You'll have to update the plugin manually", 'lwsmanager'),
				'',
				sprintf(
					__("Please visit %s and fill the form to register your website and activate your license.", 'lwsmanager'),
					sprintf(
						'<a target="_blank" href="%s">%s</a>',
						\esc_attr($formLink),
						__("Long Watch Studio Remote Activation", LWS_MANAGER_INCLUDES)
					)
				),
			)),
			'fields' => array(
				array(
					'id'    => 'lws_mgr_url',
					'title' => __("Your website URL", 'lwsmanager'),
					'type'  => 'custom',
					'extra' => array(
						'gizmo'   => true,
						'content' => \apply_filters('lws_format_copypast', \get_home_url(\get_main_network_id())),
					)
				),
				array(
					'id'    => 'lws_mgr_url',
					'title' => __("Your website fingerprint", 'lwsmanager'),
					'type'  => 'custom',
					'extra' => array(
						'gizmo'   => true,
						'content' => \apply_filters('lws_format_copypast', $manager->getFingerprint(true)),
					)
				),
				array(
					'id'    => 'lws_mgr_key',
					'title' => __("Your license key", 'lwsmanager'),
					'type'  => 'custom',
					'extra' => array(
						'gizmo'   => true,
						'content' => $key,
					)
				),
			),
		);
		return $groups;
	}

	private function addGroupLogs($groups)
	{
		global $wpdb;
		$pLen = strlen($wpdb->base_prefix);
		$logs = array();
		foreach ($wpdb->get_col('show TABLES LIKE "%options"') as $t) {
			$actions = $wpdb->get_results(
				"SELECT SUBSTRING(`option_name`,18) as `action`, `option_value` as `value` FROM `{$t}` WHERE `option_name` LIKE 'lws_last_license_%'"
			);
			if ($actions)
				$logs[substr($t, $pLen)] = $actions;
		}
		foreach ($wpdb->get_col('show TABLES LIKE "%sitemeta"') as $t) {
			$actions = $wpdb->get_results(
				"SELECT SUBSTRING(`meta_key`,18) as `action`, `meta_value` as `value` FROM `{$t}` WHERE `meta_key` LIKE 'lws_last_license_%'"
			);
			if ($actions)
				$logs[substr($t, $pLen)] = $actions;
		}

		$ip = implode(' | ', array(
			$this->getServerIp(),
			isset($_SERVER['SERVER_ADDR']) ? $_SERVER['SERVER_ADDR'] : '—',
			isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '—',
		));
		if ($logs) {
			foreach ($logs as $source => $actions) {
				$group = array(
					'id'     => 'lws-log',
					'icon'   => 'lws-icon-debug',
					'title'  => sprintf(__("License logs (%s)", 'lwsmanager'), $source),
					'color'  => '#6EB579',
					'class'  => 'onecol',
					'text'   => array('tag'=>'small', sprintf(__('Your server IP: %s', 'lwsmanager'), $ip)),
					'fields' => array(),
				);
				foreach ($actions as $action) {
					$action->value = \maybe_unserialize($action->value);
					if (!\is_array($action->value))
						continue;

					foreach ($action->value as $plugin => $log) {
						$title = ($action->action . ': ' . $plugin);
						$group['fields'][$title] = array(
							'id'    => 'lws-license-logs-' . $title,
							'title' => $title,
							'type'  => 'custom',
							'extra' => array(
								'gizmo'   => true,
								'content' => sprintf(
									'<div style="display:none">%s</div><pre>%s</pre>',
									\esc_html(base64_encode(serialize($log))),
									\esc_html(print_r($this->censureLog($log), true))
								),
							)
						);
					}
				}
				$groups['9999.logs.'.$source] = $group;
			}
		} else {
			$groups['9999.logs'] = array(
				'id'     => 'lws-log',
				'icon'   => 'lws-icon-debug',
				'title'  => __("License logs", 'lwsmanager'),
				'color'  => '#6EB579',
				'class'  => 'onecol',
				'text'   => array(
					'join'=> '<br/>',
					__('No log', 'lwsmanager'),
					array('tag'=>'small', sprintf(__('Your server IP: %s', 'lwsmanager'), $ip)),
				),
			);
		}
		return $groups;
	}

	private function censureLog($log)
	{
		if (\is_array($log) && isset($log['data']) && \is_array($log['data'])) {
			foreach ($log['data'] as $index => $data) {
				if (\is_object($data) && isset($data->status) && 'success' == $data->status) {
					$log['data'][$index] = (object)array(
						'success' => $data->status,
						'message' => isset($data->message) ? $data->message : '',
						'...'     => '...',
					);
					if (isset($log['lastRequest']))
						$log['lastRequest'] = '...';
				}
			}
		}
		return $log;
	}

	private function getAddonManagerFieldContent($fieldId)
	{
		$manager =& $this->getManager();
		$opt     = \esc_attr($manager->getKeyOption());
		$key     = \esc_attr($manager->getKey());
		$value   = \esc_attr('toggle_' . $manager->getSlug());
		$button  = $manager->isActive() ? __("Deactivate my license", 'lwsmanager') : __("Activate license", 'lwsmanager');

		$content = <<<EOT
<div class='lws-addon-license-field'>
	<input lass='lws-input' size='30' type='text' value='{$key}' name='{$opt}'>
	<button type='submit' class='lws-button-link' value='{$value}' name='{$fieldId}'>$button</button>
</div>
EOT;
		return $content;
	}

	private function addGroupAddons($groups)
	{
		$manager =& $this->getManager();
		$addons = \apply_filters('lws_adm_addons_'.$manager->getSlug(), array());
		if( $addons )
		{
			$text = array(
				__("The following extensions have been found on your system.", 'lwsmanager'),
				__("Here you manage the license keys for each one of them.", 'lwsmanager'),
			);
			if( !$manager->isActive() )
				$text[] = sprintf(__("The Premium version of %s must be active for extensions to work.", 'lwsmanager'), $manager->getName());

			$fields = array(array(), array());
			foreach($addons as $slug => $addon)
			{
				$fieldId = $addon->getAddonActionOption();
				$index = (isset($addon->noKey) && $addon->noKey) ? 1 : 0;
				$fields[$index][$slug] = array(
					'id'    => $fieldId,
					'type'  => 'custom',
					'title' => $addon->getManager()->getName(),
				);
				if ($index) {
					$fields[$index][$slug]['extra'] = array(
						'gizmo'   => true,
						'content' => sprintf('<button disabled class="lws-adm-btn">%s</button>', __('Activated', 'lwsmanager')),
					);
				} else {
					$fields[$index][$slug]['extra'] = array(
						'content' => $addon->getAddonManagerFieldContent($fieldId),
					);
				}
			}

			$groups['50.addons'] = array(
				'id'     => 'addons',
				'icon'   => 'lws-icon-books',
				'title'  => __("Installed Extensions", 'lwsmanager'),
				'color'  => '#336666',
				'class'  => 'onecol',
				'text'  => sprintf('<p>%s</p>', implode('</p><p>', $text)),
				'fields' 	=> \array_merge($fields[0], $fields[1]),
			);
		}
		return $groups;
	}

	private function addGroupAddonTeasers($groups)
	{
		$manager =& $this->getManager();
		$teasers = \apply_filters('lws_adm_teasers_'.$manager->getSlug(), array(), $this->_getStatus($manager->getPluginInfo()));
		if( $teasers )
		{
			$text = array(
				__("Make your site even greater.", 'lwsmanager'),
				sprintf('<a href="%s" target="_blank">%s</a>', \esc_attr($manager->getRemoteUrl()), __("All these extensions are available here.", 'lwsmanager')),
			);
			if( !$this->getManager()->isActive() )
				$text[] = __("Addons need an active Premium version.", 'lwsmanager');

			$fields = array();
			foreach($teasers as $k => $content)
			{
				$fields[$k] = array(
					'id'    => 'teaser_'.$k,
					'type'  => 'custom',
					'extra' => array(
						'gizmo'   => true,
						'content' => $content,
					),
				);
			}

			$groups['90.teasers'] = array(
				'id'     => 'teasers',
				'icon'   => 'lws-icon-show-more',
				'title'  => __("Available Extensions", 'lwsmanager'),
				'color'  => '#018A37',
				'class'  => 'onecol',
				'text'  => sprintf('<p>%s</p>', implode('</p><p>', $text)),
				'fields' 	=> $fields,
			);
		}
		return $groups;
	}

	/// current is activated but still only lite code exists
	private function addGroupUpdateInfo($groups)
	{
		$manager = & $this->getManager();
		$manager->clearUpdateTransient();

		$url = \esc_attr(\add_query_arg('force-check', '1', \admin_url('update-core.php')));
		$link = sprintf('<a href="%s">%s</a>', $url, __("WordPress Updates")); // use WP translation
		$name = sprintf('<b>%s</b>', $manager->getName());
		$text = array(
			__('<b>Your license is activated.</b>', 'lwsmanager'),
			sprintf(__('Look in %1$s page for a %2$s update.', 'lwsmanager'), $link,$name),
			__('You should have to click "<i>Check Again</i>" button to force WordPress refresh its list.', 'lwsmanager'),
			__('If the Plugin Update still does not appears, please wait few minutes and try again.', 'lwsmanager'),
		);
		if( !$manager->isActive() )
			$text[0] = __('<b>Your Trial is active.</b>', 'lwsmanager');

		$content = sprintf("<a class='lws-button-link' href='%s' target='_blank'>%s</a>", $url, __("Check for Updates", 'lwsmanager'));

		$groups['00.update'] = array(
			'id'     => 'update',
			'icon'   => 'lws-icon-settings-gear',
			'title'  => __("An update is waiting for you", 'lwsmanager'),
			'color'  => '#a4489a',
			'class'  => 'onecol',
			'text'  => sprintf('<p>%s</p>', implode('</p><p>', $text)),
			'fields' 	=> array(
				'custom' 	=> array(
					'id'    => 'custom',
					'type'  => 'custom',
					'extra' => array(
						'gizmo'   => true,
						'content' => $content,
					),
				),
			)
		);
		return $groups;
	}

	/// current is pro, tell about maintenance
	private function addGroupSubscription($groups)
	{
		$manager = & $this->getManager();
		$expired = !$manager->isSubscriptionActive();
		$link = $manager->getRemoteMyAccountURL();
		$zomb = $manager->isZombie();

		$text = array();
		if( $expired )
		{
			$text[] = __("Your Subscription is currently inactive.", 'lwsmanager');
			if( $zomb )
				$text[] = __("You can't download new versions or send requests to the support.", 'lwsmanager');
			else
				$text[] = __("You can't use Premium features of this plugin, download new versions or send requests to the support.", 'lwsmanager');
			$text[] = __("You can resume your subscription at any time to get access to updates and support.", 'lwsmanager');
			$content = sprintf("<a class='lws-button-link' href='%s' target='_blank'>%s</a>", \esc_attr($link), __("Resume my subscription", 'lwsmanager'));
		}
		else
		{
			$text[] = __("Your Subscription is currently active.", 'lwsmanager');
			$ending = $manager->getSubscriptionEnd();
			if( $ending )
			{
				if( $zomb )
					$text[] = __("Updates and Support are available until :", 'lwsmanager');
				else
					$text[] = __("To avoid a service interruption, you should renew your subscription before :", 'lwsmanager');
				$content  = sprintf("<div class='lws-license-big-text'>%s</div>", \date_i18n(\get_option('date_format'), $ending->getTimestamp()));
				$content .= sprintf("<a class='lws-button-link' href='%s' target='_blank'>%s</a>", \esc_attr($link), __("See my subscription", 'lwsmanager'));
			}
			else
			{
				if( $zomb )
					$text[] = __("Your Subscription includes Updates and Support.", 'lwsmanager');
				else
					$text[] = __("Your Subscription includes the usage of this plugin, updates and support.", 'lwsmanager');
				$content = sprintf("<a class='lws-button-link' href='%s' target='_blank'>%s</a>", \esc_attr($link), __("Manage my subscription", 'lwsmanager'));
			}
		}

		if( $zomb )
			$title = __("Maintenance and Updates", 'lwsmanager');
		else
			$title = __("Usage Permission", 'lwsmanager');
		$groups['12.subscript'] = array(
			'id'		=> 'subscript',
			'icon'		=> 'lws-icon-version',
			'title' 	=> $title,
			'color' 	=> $expired ? '#cc1d25' : '#018A37',
			'class'		=> 'half',
			'text'  	=> implode('</br>', $text),
			'fields' 	=> array(
				'custom' 	=> array(
					'id'    => 'custom',
					'type'  => 'custom',
					'extra' => array(
						'gizmo'   => true,
						'content' => $content,
					),
				),
			)
		);

		if ($expired) {
			$button = sprintf(
				"<button type='submit' name='%s' value='recheck' class='button'>%s</button>",
				$this->getRecheckOption(),
				__("Force a license check", 'lwsmanager')
			);

			$groups['12.subscript']['fields']['recheck'] = array(
				'id'    => $this->getRecheckOption(),
				'type'  => 'custom',
				'extra' => array(
					'content' => sprintf('<p>%s</p>', sprintf(
						__("Or, if you already renewed your subscription %s.", 'lwsmanager'),
						$button
					)),
				),
			);
		}

		return $groups;
	}

	/// current was trial, but since expired, the free remains, hope for a pro
	private function addGroupIdleKey($groups)
	{
		$manager = & $this->getManager();
		$text = array(
			__("If you have a Premium License Key, please input it in the field below and click on the Activation button.", 'lwsmanager'),
			__("If the activation is successful, the Premium version will be activated and installed automatically.", 'lwsmanager'),
		);

		$content = sprintf(
			"<button type='submit' class='lws-button-link'>%s</button>",
			__("Activate my license", 'lwsmanager')
		);

		$groups['31.idle'] = array(
			'id'     => 'idle',
			'icon'   => 'lws-icon-key',
			'title'  => sprintf(__("%s Premium License", 'lwsmanager'), $manager->getName()),
			'color'  => '#336666',
			'class'  => 'half',
			'text'   => implode('</br>', $text),
			'fields' => array(
				'key' => array(
					'id'    => $manager->getKeyOption(),
					'type'  => 'text',
					'title' => __('License Key', 'lwsmanager'),
					'extra' => array(
						'size' => '30',
						'placeholder' => 'XX-XXXX-XXXX-XXXX-XXXX',
						'help' => __("Your license key has been provided to you in the Order Confirmation eMail.", 'lwsmanager'),
					)
				),
				'custom' => array(
					'id'    => 'custom',
					'type'  => 'custom',
					'extra' => array(
						'gizmo'   => true,
						'content' => $content,
					)
				),
			)
		);
		return $groups;
	}

	private function getAddonActionOption()
	{
		return ('lws-addon-action-'.$this->getManager()->getSlug());
	}

	private function getActionOption()
	{
		return ('lws-license-action-'.$this->getManager()->getSlug());
	}

	private function getRecheckOption()
	{
		return ('lws-license-recheck-'.$this->getManager()->getSlug());
	}

	/// current activated pro
	private function addGroupActivePro($groups)
	{
		$manager = & $this->getManager();
		$text = array(
			sprintf(__("You're actually using a licensed version of %s Premium.", 'lwsmanager'), $manager->getName()),
			__("Your license key is :", 'lwsmanager'),
		);

		$content = sprintf("<div class='lws-license-big-text'>%s</div>", $manager->getKey());
		$content .= sprintf(
			"<button type='submit' name='%s' value='deactivate' class='lws-button-link'>%s</button>",
			$this->getActionOption(),
			__("Deactivate my license", 'lwsmanager')
		);

		$details = sprintf(__("If you deactivate your license, you will be reverted to the free version of %s. Your license count will be updated on our server and you'll be able to activate your license on another website.", 'lwsmanager'), $manager->getName());
		$content .= "<div class='lws-license-small-text'>{$details}</div>";
		$subscription  = __("Deactivating your license won't cancel your subscription!", 'lwsmanager');
		$subscription2 = __("If you want to cancel your subscription, do it on ", 'lwsmanager');
		$content .= "<p style='text-align:justify'><b>{$subscription}</b> {$subscription2}<a href='https://plugins.longwatchstudio.com' target='_blank'>https://plugins.longwatchstudio.com</a></p>";

		$groups['11.pro'] = array(
			'id'     => 'pro',
			'icon'   => 'lws-icon-key',
			'title'  => sprintf(__("%s Premium License", 'lwsmanager'), $manager->getName()),
			'color'  => '#336666',
			'class'  => 'half',
			'text'   => implode('</br>', $text),
			'fields' => array(
				'custom' => array(
					'id'    => $this->getActionOption(),
					'type'  => 'custom',
					'extra' => array(
						'content' => $content,
					),
				),
			)
		);
		return $groups;
	}

	/// current is free version
	private function addGroupFreeVersion($groups)
	{
		$manager = & $this->getManager();
		$teaser = \apply_filters('lws_adm_license_trial_teaser_texts', __("This version contains only a few features of the premium version.", 'lwsmanager'), $manager->getSlug());
		$text = array(
			sprintf(__("You're actually using the free version of %s.", 'lwsmanager'), $manager->getName()),
			$teaser,
			sprintf(__("If you're happy with %s Standard features, <b>please consider reviewing it</b> on wordpress.org", 'lwsmanager'), $manager->getName()),
		);

		if( $manager->isTrialAvailable() )
			$text[] = __("If you want to gain access to more features, you can try the premium version for free for 30 days.", 'lwsmanager');

		$content = sprintf(
			"<a class='lws-button-link' href='%s' target='_blank'>%s</a>",
			\esc_attr(sprintf('https://wordpress.org/support/plugin/%s/reviews/#new-post', $manager->getSlug())),
			__("Review on wordpress.org", 'lwsmanager')
		);

		$groups['21.free'] = array(
			'id'     => 'free',
			'icon'   => 'lws-icon-free',
			'title'  => __("Free Version", 'lwsmanager'),
			'color'  => '#016087',
			'class'  => 'half',
			'text'   => implode('</br>', $text),
			'fields' => array(
				'custom' 	=> array(
					'id'    => 'custom',
					'type'  => 'custom',
					'extra' => array(
						'gizmo'   => true,
						'content' => $content,
					),
				),
			)
		);
		return $groups;
	}

	/// current is free version, never try more
	private function addGroupTeaseTrial($groups)
	{
		$manager = & $this->getManager();
		$teaser = \apply_filters('lws_adm_license_trial_teaser_texts', __("This version contains only a few features of the premium version.", 'lwsmanager'), $manager->getSlug());
		$text = sprintf(
			'%s</br>%s<ul><b><li>%s</li><li>%s</li><li>%s</li></b></ul>',
			$teaser,
			sprintf(__("Try %s Premium for free for 30 days.", 'lwsmanager'), $manager->getName()),
			sprintf(__("Instant access to all %s Premium features.", 'lwsmanager'), $manager->getName()),
			__("No payment required.", 'lwsmanager'),
			__("No registration required.", 'lwsmanager')
		);

		$content = sprintf(
			"<button type='submit' name='%s' value='trial' class='lws-button-link'>%s</button>",
			$this->getActionOption(),
			__("Start the free trial", 'lwsmanager')
		);

		$groups['32.lite2trial'] = array(
			'id'     => 'lite2trial',
			'icon'   => 'lws-icon-free',
			'title'  => sprintf(__("Free %s Premium Trial", 'lwsmanager'), $manager->getName()),
			'color'  => '#018A07',
			'class'  => 'half',
			'text'   => $text,
			'fields' => array(
				'custom' 	=> array(
					'id'    => $this->getActionOption(),
					'type'  => 'custom',
					'extra' => array(
						'content' => $content,
					)
				),
			)
		);
		return $groups;
	}

	/// current is trial version
	private function addGroupPurchaisePro($groups)
	{
		$manager = & $this->getManager();
		$text = array();
		$content = '';

		if( $t = $manager->isTrial() )
			$text[] = sprintf(__("You're actually trying %s Premium for free for a limited period of time. You will be reminded that your trial period is about to end 5 days and 3 days before it ends.", 'lwsmanager'), $manager->getName());
		else
			$text[] = sprintf(__("You're actually trying %s Free with limited features.", 'lwsmanager'), $manager->getName());
		$text[] = sprintf(__("If you like %s, you can purchase a license on our website by clicking on the button below.", 'lwsmanager'), $manager->getName());

		if( $t && $ending = $manager->getTrialEnding() )
		{
			$diff = $ending->diff(\date_create(), true)->format('%a');
			$text[] = '';
			$text[] = __("Your trial will expire in :", 'lwsmanager');
			$remainings = sprintf("%d %s", $diff, __("Days", 'lwsmanager'));
			$content = "<div class='lws-license-big-text'>{$remainings}</div>";
		}

		$content .= sprintf(
			"<a class='lws-button-link' href='%s' target='_blank'>%s</a>",
			\esc_attr(\apply_filters('lws_adm_license_product_page_url', $manager->getPluginURI(), $manager->getSlug())),
			sprintf(__("Purchase %s Premium", 'lwsmanager'), $manager->getName())
		);

		$details = __("Premium Version is a paid service. This service features the plugin, the support and regular updates. You can cancel your subscription at any time by changing your preferences on your account on plugins.longwatchstudio.com. Cancelling your subscription will remove the access to premium features, support and updates.", 'lwsmanager');
		$content .= "<div class='lws-license-small-text'>{$details}</div>";

		$groups['22.trial2pro'] = array(
			'id'		=> 'trial2pro',
			'icon'		=> 'lws-icon-free',
			'title' 	=> sprintf(__("Purchase %s Premium", 'lwsmanager'), $manager->getName()),
			'color' 	=> '#018A07',
			'class'		=> 'half',
			'text'  	=> implode('</br>', $text),
			'fields' 	=> array(
				'custom' 	=> array(
					'id'    => 'custom',
					'type'  => 'custom',
					'extra' => array(
						'gizmo'   => true,
						'content' => $content,
					)
				),
			)
		);
		return $groups;
	}

	private function setProductId($uuid)
	{
		$this->uuid = $uuid;
	}

	/** @return \LWS\Manager\Core\Manager */
	private function &getManager()
	{
		if( false === $this->license ){
			require_once LWS_MANAGER_INCLUDES . '/core/manager.php';
			$this->license = new \LWS\Manager\Core\Manager($this->file, $this->uuid);
		}
		return $this->license;
	}

	function migrateFromV3($slug)
	{
		$manager =& $this->getManager();
		if( $manager->getSlug() == $slug )
		{
			$token = $manager->getKey();
			if( !$token )
				return;
			if( $manager->isRunning() )
				return;

			$ending = \get_site_option('lws-license-end-'.$slug);
			if( $ending )
			{
				$ending = \date_create($ending);
				if( !$ending || $ending < \date_create()->setTime(0,0) )
					return;
				if( !$manager->isTrialAvailable() )
					return;

				error_log("Go migrate license to trial $slug for key: ".$manager->getKey());
				if( $manager->startTry(false, $ending) )
				{
					error_log(sprintf("Trial for %s with key [%s] succeed.", $slug, $manager->getKey()));
				}
				else
				{
					\lws_admin_add_notice(
						'lws_lic_udt_error_'.$slug,
						implode('<br/>', array(
							sprintf(__("The Trial License found for the plugin <b>%s</b> cannot be migrated to the new system.", 'lwsmanager'), $manager->getName()),
							__("If you are sure your license should still be valid, try to restart the trial manually or contact the support of the plugin.", 'lwsmanager'),
						)),
						array('level'=>'error')
					);
				}
			}
			else
			{
				error_log("Go migrate license to pro $slug for key: ".$manager->getKey());
				if( $manager->activate($token, false, false, '4') )
				{
					error_log(sprintf("License for %s with key [%s] succeed.", $slug, $manager->getKey()));
				}
				else
				{
					\lws_admin_add_notice(
						'lws_lic_udt_error_'.$slug,
						implode('<br/>', array(
							sprintf(__("The license found for the plugin <b>%s</b> cannot be migrated to the new system.", 'lwsmanager'), $manager->getName()),
							__("If you are sure your license should still be valid, try to reactivate it manually or contact the support of the plugin.", 'lwsmanager'),
						)),
						array('level'=>'error')
					);
				}
			}
		}
	}

	/** Add backlink to plugin license page after plugin install.
	 *	In addition to 'Go to Plugins page' */
	function afterInstallActions($actions, $api, $plugin)
	{
		$manager =& $this->getManager();
		if( $manager->getBasename() == $plugin )
		{
			$args = false;
			if( $this->pageQueryArgs )
				$args = $this->pageQueryArgs;
			if( !$args && $this->myPages )
				$args = array('page' => \reset($this->myPages));

			if( $args )
			{
				$actions['go_to_lws'] = sprintf(
					'<a href="%s" target="_parent">%s</a>',
					\add_query_arg($args, \admin_url('admin.php')),
					sprintf(__('Go to <b>%s</b> Settings', 'lwsmanager'), $manager->getName())
				);
			}
		}
		return $actions;
	}

	/** Add backlink to plugin main admin page after plugin update.
	 *	In addition to 'Go to Plugins page' */
	function afterUpdateActions($actions, $plugin)
	{
		$manager =& $this->getManager();
		if( $manager->getBasename() == $plugin )
		{
			$url = $this->getMainScreenUrl();
			if( $url )
			{
				$actions['go_to_lws'] = sprintf(
					'<a href="%s" target="_parent">%s</a>',
					$url,
					sprintf(__('Go to <b>%s</b> Settings', 'lwsmanager'), $manager->getName())
				);
			}
		}
		return $actions;
	}
}
