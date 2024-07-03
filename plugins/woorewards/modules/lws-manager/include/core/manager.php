<?php
namespace LWS\Manager\Core;

// don't call the file directly
if( !defined( 'ABSPATH' ) ) exit();


/** This code is never used unless user set a key.
 *	Then, we go out of WordPress flow.
 *	Usually new code is downloaded. */
class Manager
{
	const API_VERSION      = '1.1'; //use latest available API
	const CHECK_INTERVAL   = 'P1DT6H';
	private $trialWarnings = array(5, 3);
	private $lastRequest   = false;
	private $lastCUrl      = false;
	private $fk            = false;
	private $lite          = null;
	private $file          = '';
	private $uuid          = '';
	private $basename      = false;
	private $slug          = false;
	private $minifiedSlug  = false;
	private $plugin        = false;

	function __construct($file, $uuid)
	{
		$this->file = $file;
		$this->uuid = $uuid;
	}

	function getFingerprint($shape=false)
	{
		$fp = $this->getGlobalOption('lws_licmgr_fingerprint');
		if (!$fp) {
			$fp = \strtolower(\wp_generate_password(16, false));
			$this->updateGlobalOption('lws_licmgr_fingerprint', $fp);
		}
		return $shape ? \implode('-', \str_split($fp, 4)) : $fp;
	}

	function getSiteUrl()
	{
		if (defined('LWS_SITEURL') && LWS_SITEURL)   $url = LWS_SITEURL;
		elseif (defined('WP_SITEURL') && WP_SITEURL) $url = WP_SITEURL;
		else $url = $this->getGlobalOption('siteurl');
		return \preg_replace('@^https?://@i', '', $url);
	}

	function getRemoteUrl($path='')
	{
		$url = 'https://plugins.longwatchstudio.com/';
		if( defined('LWS_DEV') && LWS_DEV )
			$url = \is_string(LWS_DEV) ? LWS_DEV : \site_url();

		if( $path && \is_string($path) )
			$url = (\rtrim($url, '/') . '/' . \ltrim($path, '/'));

		$url = \add_query_arg(array('lang'=>\get_locale()), $url);
		return $url;
	}

	function isRunning()
	{
		if( $this->isActive() )
		{
			if( $this->isZombie() )
				return true;
			else
				return !$this->isSubscription() || $this->isSubscriptionActive();
		}
		else
			return $this->isTrial();
	}

	/// true premium
	function isActive()
	{
		if( !($key = $this->getKey()) ) return false;
		if( !($value = $this->getGlobalOption($this->getId(), '')) ) return false;
		if( !($value = $this->recurringCheck($value)) ) return false;
		return !$this->isExpired($value, true);
	}

	function maybeActive()
	{
		if( !($key = $this->getKey()) ) return false;
		if( !($value = $this->getGlobalOption($this->getId(), '')) ) return false;
		return true;
	}

	private function getActionKey()
	{
		$k = 'woo_sl_action';
		if ($this->fk)
			$k = 'lwswcslf_action';
		elseif( !$this->maybeActive() && $this->isTrial() )
			$k = 'lwswcsl_action';
		return $k;
	}

	function isPremiumExpired()
	{
		return $this->isExpired($this->getGlobalOption($this->getId(), ''), true);
	}

	/** If trial, expiration leads to free version.
	 *	If pro, expiration deny new updates */
	function isExpired($value, $lastChance=false)
	{
		if( !$value )
			return true;
		if( 'inf' == $value )
			return false;
		if( !\is_numeric($value) )
			return true;
		if( $d = \date_create()->setTimestamp($value)->setTime(0,0) )
		{
			if( \date_create()->setTime(0,0) <= $d )
				return false;
			elseif( $lastChance )
			{
				$ts = $d->getTimestamp();
				if( $this->getGlobalOption($this->getId('lwslastchance_')) != $ts )
				{
					$this->updateGlobalOption($this->getId('lwslastchance_'), $ts);
					return !$this->check(false);
				}
			}
		}
		return true;
	}

	/** Is the Pro/Trial version installed or only the free one.
	 *	Don't care about activation or not. */
	function isLite()
	{
		if( null === $this->lite )
		{
			$this->lite = !\apply_filters('lws-ap-release-'.$this->getSlug(), '');
		}
		return $this->lite;
	}

	/** Only if downloaded from WordPress and trial exists but never started */
	function isTrialAvailable()
	{
		if( !$this->isLite() )
			return false;
		if( $this->isTrialConsumed() )
			return false;
		return \apply_filters('lws_adm_license_trial_version_exists', false, $this->getSlug());
	}

	function isLiteAvailable()
	{
		return \apply_filters('lws_adm_license_free_version_exists', true, $this->getSlug());
	}

	function isSubscriptionActive()
	{
		$support = $this->getGlobalOption($this->getId('lwssupport_'));
		if( !$support )
			return false;
		else if( \is_numeric($support) )
			return \time() <= $support;
		else
			return \in_array($support, array('active', 'pending-cancel'));
	}

	/** @return false|\DateTime */
	function getSubscriptionEnd()
	{
		$support = $this->getGlobalOption($this->getId('lwssupport_'));
		if( $support && \is_numeric($support) )
			return \date_create()->setTimestamp($support);
		return false;
	}

	function isZombie()
	{
		return ('on' == $this->getGlobalOption($this->getId('lwszombie_'), ''));
	}

	function isSubscription()
	{
		return \boolval($this->getGlobalOption($this->getId('lwssupport_'), ''));
	}

	function isTrial()
	{
		if( !($value = $this->getGlobalOption($this->getId('lwstrial_'), '')) ) return false;
		return !$this->isExpired($value);
	}

	function isTrialExpired()
	{
		$trial = $this->getGlobalOption($this->getId('lwstrial_'), false);
		if( false === $trial )
			return false;
		return $this->isExpired($trial);
	}

	function isTrialConsumed()
	{
		if( false !== $this->getGlobalOption($this->getId('lwstrial_'), false) )
			return true;
		if( false !== $this->getGlobalOption($this->getId(), false) )
			return true;
		return false;
	}

	/** @return false|\DateTime instance */
	function getTrialEnding()
	{
		$ts = $this->getGlobalOption($this->getId('lwstrial_'), 0);
		if( $ts && \is_numeric($ts) )
			return \date_create()->setTimestamp($ts);
		return false;
	}

	function startTry($update=true, $forceDate=false)
	{
		$this->ignoreSavingConfirmation();
		if( $this->isTrialConsumed() )
		{
			$this->notice(__("Seems like your trial period has been already consumed.", 'lwsmanager'));
			return false;
		}

		$args = array(
			'lwswcsl_action'     => 'activate',
			'product_unique_id' => $this->uuid,
			'domain'            => $this->getSiteUrl(),
		);
		$requestUri = \add_query_arg($args, $this->getRemoteUrl());
		$data       = \wp_remote_get($requestUri, $this->getRemoteGetArgs());

		if( \is_wp_error($data) || !\in_array(\intval($data['response']['code']), array(200, 301, 302)) )
		{
			$this->notice(__("There was a problem establishing a connection to the trial server.", 'lwsmanager'));
			return false;
		}

		$dataBody = \json_decode($data['body'], false);
		$this->log('starttry', $dataBody);
		if( \is_array($dataBody) )
			$dataBody = \end($dataBody);
		if( \is_object($dataBody) && isset($dataBody->status) )
		{
			/// s100 first time key activation
			/// s101 key already activated for domain
			/// s205 key is active and valid for domain ( when assign_domain_to_key_on_status_check option active )
			/// s215 key is active and valid for domain
			if( $dataBody->status == 'success' && \in_array($dataBody->status_code, array('s100', 's101', 's205', 's215')) && isset($dataBody->trial_status, $dataBody->trial_expire) )
			{
				$txt = array(__("Update to the premium Trial is now available.", 'lwsmanager'));
				if( isset($dataBody->message) )
					$txt[] = sprintf('<div class="lws-license-small-text">%s</div>', $this->serverMessage($dataBody->message, $dataBody->status_code));
				$this->notice(implode('</br>', $txt), 'success');

				$d = \absint($forceDate ? $forceDate->getTimestamp() : $dataBody->trial_expire);
				$e = \date_i18n(\get_option('date_format'), $d);
				if( $d > \time() ){
					$notice = \apply_filters('lws_adm_trialstart_msg', '', $this->getSlug(), $e, $d);
					if (!$notice)
						$notice = sprintf(__('The Trial for <i>%2$s</i> will expire the <b>%1$s</b>.', 'lwsmanager'), $e, $this->getName());
					$this->notice($notice, 'warning', '-e', false);
				}
				else
					$this->notice(sprintf(__('The Trial for <i>%2$s</i> already expired the <b>%1$s</b>.', 'lwsmanager'), $e, $this->getName()), 'error', '-e');

				$this->updateGlobalOption($this->getId('lwstrial_'), $d ? $d : 0);

				if( $update && $this->isLite() )
				{
					// page will be redirected after option saved, go to update if required
					\add_filter('wp_redirect', array($this, 'redirectToUpdate'), 50, 2);
				}
				return true;
			}
			else
			{
				$txt = array(__("There was a problem activating the Trial. You may retry later.", 'lwsmanager'));
				if( isset($dataBody->message) )
					$txt[] = $this->serverMessage($dataBody->message, $dataBody->status_code);
				$this->notice(implode('</br>', $txt));
			}
		}
		else
		{
			$this->notice(__("There was a problem establishing a connection to the license service.", 'lwsmanager'));
		}

		return false;
	}

	/** @return false or DateTime instance */
	function getEnding($asTimestamp=false)
	{
		$ts = $this->getGlobalOption($this->getId(), '');
		if( \is_numeric($ts) )
		{
			$d = \date_create()->setTimestamp($ts);
			if( $d )
				return $asTimestamp ? $d->getTimestamp() : $d;
		}
		return false;
	}

	function getKeyOption()
	{
		return 'lws-license-key-' . $this->getSlug();
	}

	function updateGlobalOption($name, $value)
	{
		return \update_network_option(\get_main_network_id(), $name, $value);
	}

	function getGlobalOption($name, $default=false)
	{
		return \get_network_option(\get_main_network_id(), $name ,$default);
	}

	function getKey()
	{
		$option = $this->getKeyOption();
		$key = $this->getGlobalOption($option);
		if( !$key && \is_multisite() )
		{
			global $wpdb;
			$key = $wpdb->get_var($wpdb->prepare("SELECT option_value FROM {$wpdb->options} WHERE option_name=%s", $option)); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			if( $key )
				$this->updateGlobalOption($option, $key);
		}
		return $key;
	}

	function updateKey($value)
	{
		return $this->updateGlobalOption($this->getKeyOption(), $value);
	}

	function getName()
	{
		$name = $this->getPluginInfo()['Name'];
		if( !$name )
			$name = $this->getSlug();
		return $name;
	}

	function getPluginURI()
	{
		$uri = $this->getPluginInfo()['PluginURI'];
		if( !$uri )
			$uri = $this->getPluginInfo()['AuthorURI'];
		return $uri;
	}

	function getPluginVersion()
	{
		return $this->getPluginInfo()['Version'];
	}

	function getPluginAuthor()
	{
		return $this->getPluginInfo()['Author'];
	}

	function getBasename()
	{
		if( false === $this->basename )
		{
			$this->basename = \plugin_basename($this->file);
		}
		return $this->basename;
	}

	function getSlug()
	{
		if( false === $this->slug )
		{
			$this->slug = \strtolower(\basename($this->getBasename(), '.php'));
		}
		return $this->slug;
	}

	function getPluginInfo()
	{
		if( false === $this->plugin )
		{
			if( !\function_exists('\get_plugin_data') )
				require_once(ABSPATH . 'wp-admin/includes/plugin.php');

			$this->plugin = \get_plugin_data($this->file, false);
			$this->plugin = array_merge(array(
				'Name'      => '',
				'Version'   => '',
				'Author'    => '',
				'AuthorURI' => '',
				'PluginURI' => '',
			), $this->plugin);
		}
		return $this->plugin;
	}

	private function getMinifiedSlug($slug)
	{
		if( false === $this->minifiedSlug )
		{
			$this->minifiedSlug = '';
			$l = strlen($slug);
			for( $i=0 ; $i<$l ; $i+=3 )
				$this->minifiedSlug .= $slug[$i];
		}
		return $this->minifiedSlug;
	}

	function getId($prefix='lwslic_')
	{
		$slug = $this->getSlug();
		return $prefix.$this->getMinifiedSlug($slug).'_'.substr(\md5(\implode('.', array(
			DB_HOST,
			DB_NAME,
			$this->getGlobalOption('initial_db_version'),
			$slug,
			'lws',
		))), 0, 16);
	}

	/**	@param $active bool|DateTime
	 *	@return bool|\DateTime */
	private function recurringCheck($active)
	{
		if( !\is_admin() || (defined('DOING_AJAX') && DOING_AJAX) )
			return $active;

		if( $active && self::CHECK_INTERVAL && $this->uuid )
		{
			$k = $this->getId('lwschk_');
			$d = $this->getGlobalOption($k);
			if( $d )
			{
				if( \time() > $d )
				{
					$this->updateGlobalOption($k, \date_create()->add(new \DateInterval(self::CHECK_INTERVAL))->getTimestamp());
					if( !($active = $this->check(true)) )
						$this->updateGlobalOption($k, false);
				}
			}
			else
			{
				$this->updateGlobalOption($k, \date_create()->add(new \DateInterval(self::CHECK_INTERVAL))->getTimestamp());
			}
		}
		return $active;
	}

	function deactivate($key=false, $obsoleteKey=false)
	{
		if( false === $key )
			$key = $this->getKey();

		$args = array(
			'woo_sl_action'     => 'deactivate',
			'licence_key'       => $key,
			'product_unique_id' => $this->uuid,
			'domain'            => $this->getSiteUrl(),
		);
		$requestUri = \add_query_arg($args, $this->getRemoteUrl());
		$data       = \wp_remote_get($requestUri, $this->getRemoteGetArgs());

		if( \is_wp_error($data) || !\in_array(\intval($data['response']['code']), array(200, 301, 302)) )
		{
			$this->notice(__("There was a problem establishing a connection to the license server.", 'lwsmanager'));
			return false;
		}

		$dataBody = \json_decode($data['body'], false);
		$this->log('deactivate', $dataBody);
		if( \is_array($dataBody) )
			$dataBody = \end($dataBody);
		return $this->deactivateFromData($dataBody, $key, true, $obsoleteKey);
	}

	function deactivateFromData($dataBody, $key=false, $notice=false, $obsoleteKey=false)
	{
		if( \is_object($dataBody) && isset($dataBody->status) )
		{
			if( $dataBody->status == 'success' )
			{
				$level = $obsoleteKey ? 'info' : 'success';
				$txt = array();
				if( $obsoleteKey )
					$txt[] = sprintf(__("The previews license key (%s) has been deactivated.", 'lwsmanager'), $key);

				if( isset($dataBody->message) )
					$txt[] = sprintf('<div class="lws-license-small-text">%s</div>', $this->serverMessage($dataBody->message, $dataBody->status_code));
				else
					$txt[] = __("Licence Key Successfully Unassigned.", 'lwsmanager');

				if ($notice)
					$this->notice(implode('</br>', $txt), $level);
				if ($this->isSubscriptionActive())
					$this->updateGlobalOption($this->getId('lwssupport_'), 'deactivated');
				$this->updateGlobalOption($this->getId(), '');
				$this->updateGlobalOption($this->getId('lwschk_'), false);
				\do_action('lws_manager_license_inactive', $this->getSlug());
				return true;
			}
			else
			{
				$level = $obsoleteKey ? 'warning' : 'error';
				$txt = array();
				if( $obsoleteKey )
					$txt[] = sprintf(__("There was a problem deactivating the previews license key (%s).", 'lwsmanager'), $key);
				else{
					$txt[] = __("There was a problem deactivating the license.", 'lwsmanager');
					$this->updateGlobalOption($this->getId(), '');
					$this->updateGlobalOption($this->getId('lwschk_'), false);
				}
				if( isset($dataBody->message) )
					$txt[] = $this->serverMessage($dataBody->message, $dataBody->status_code);
				if ($notice)
					$this->notice(implode('</br>', $txt), $level);
			}
		}
		else
		{
			if ($notice)
				$this->notice(__("There was a problem establishing a connection to the license service.", 'lwsmanager'));
		}
		return false;
	}

	/** @return bool|\DateTime */
	function check($silentError=true)
	{
		$args = array(
			'woo_sl_action'     => 'status-check',
			'licence_key'       => $this->getKey(),
			'product_unique_id' => $this->uuid,
			'domain'            => $this->getSiteUrl(),
		);
		$requestUri = \add_query_arg($args, $this->getRemoteUrl());
		$data       = \wp_remote_get($requestUri, $this->getRemoteGetArgs());

		if( \is_wp_error($data) || !\in_array(\intval($data['response']['code']), array(200, 301, 302)) )
		{
			$detail = \is_wp_error($data) ? $data->get_error_message() : $data['response']['code'];
			error_log("There was a problem establishing a connection to the license server. ".$detail);
			return $silentError;
		}

		$dataBody = \json_decode($data['body'], false);
		$this->log('status-check', $dataBody);
		if( \is_array($dataBody) )
			$dataBody = \end($dataBody);
		if( \is_object($dataBody) && isset($dataBody->status) )
		{
			$this->clearNotice('error', '-c');
			if( 'success' == $dataBody->status && \in_array($dataBody->status_code, array('s205', 's215')) )
			{
				$d = (isset($dataBody->licence_expire) && $dataBody->licence_expire) ? \date_create($dataBody->licence_expire) : false;
				$this->clearNotice(array('error', 'warning'), '-e');
				if( $d && $d->getTimestamp() != $this->getEnding(true) )
				{
					$e = \date_i18n(\get_option('date_format'), $d->getTimestamp());
					if( $d->getTimestamp() < \date_create()->setTime(0,0,0)->getTimestamp() ){
						$this->notice(sprintf(__('The license <b>%3$s</b> for <i>%2$s</i> expired the <b>%1$s</b> unless you opted for automatic renewal.', 'lwsmanager'), $e, $this->getName(), $this->getKey()), 'error', '-e', false);
					}else{
						$this->clearNotice('error', '-e');
						$this->notice(sprintf(__('The license <b>%3$s</b> for <i>%2$s</i> should be renewed on <b>%1$s</b>.', 'lwsmanager'), $e, $this->getName(), $this->getKey())
						. ' ' . __("Please ensure your payment method still is valid.", 'lwsmanager'),
						'warning', '-e', false);
					}
				}

				$this->updateGlobalOption($this->getId(), $d ? $d->getTimestamp() : 'inf');
				$this->readSubscription($dataBody);
				\do_action('lws_manager_license_active', $this->getSlug());
				return true;
			}
			else
			{
				$this->notice(sprintf(__('The license <b>%2$s</b> for <i>%1$s</i> expired.', 'lwsmanager'), $this->getName(), $this->getKey()), 'error', '-c', false);
				$this->readSubscription($dataBody, true);
				$this->updateGlobalOption($this->getId(), '');
				$dataBody->slug = $this->getSlug();
				$dataBody->license = $this->getkey();
				error_log('Manager check: '.json_encode($dataBody,  JSON_PRETTY_PRINT|JSON_INVALID_UTF8_IGNORE|JSON_PARTIAL_OUTPUT_ON_ERROR));
				\do_action('lws_manager_license_inactive', $this->getSlug());
				return false;
			}
		}
		else
		{
			error_log("There was a problem establishing a connection to the license service.");
		}
		return $silentError;
	}

	function activate($value, $old=false, $update=true, $z=false)
	{
		if( !$value )
			$value = $old;
		if( $old && $old != $value && $this->isActive() )
			$this->deactivate($old, true);

		$args = array(
			'woo_sl_action'     => 'activate',
			'licence_key'       => $value,
			'product_unique_id' => $this->uuid,
			'domain'            => $this->getSiteUrl(),
		);
		$requestUri = \add_query_arg($args, $this->getRemoteUrl());
		$data       = $this->doRemoteGet($requestUri, $this->getRemoteGetArgs());

		if( \is_wp_error($data) || !\in_array(\intval($data['response']['code']), array(200, 301, 302)) )
		{
			\update_option('lws_lic_alternative_enabled', 'on'); // let's show an alternative way
			$this->notice(__("There was a problem establishing a connection to the license server.", 'lwsmanager'));
			if (\is_wp_error($data))
				$this->log('activate', $data->get_error_message());
			else
				$this->log('activate', 'Bad response code: ' . $data['response']['code']);
			return false;
		}

		$dataBody = \json_decode($data['body'], false);
		$this->log('activate', $dataBody);
		if( \is_array($dataBody) )
			$dataBody = \end($dataBody);

		return $this->activateFromData($dataBody, $value, $update, $z, true);
	}

	public function activateFromData($dataBody, $key, $update=true, $z=false, $notice=false)
	{
		if( \is_object($dataBody) && isset($dataBody->status) )
		{
			$this->clearNotice('error', '-c');
			/// s100 first time key activation
			/// s101 key already activated for domain
			/// s205 key is active and valid for domain ( when assign_domain_to_key_on_status_check option active )
			/// s215 key is active and valid for domain
			if( $dataBody->status == 'success' && \in_array($dataBody->status_code, array('s100', 's101', 's205', 's215')) && isset($dataBody->licence_status) )
			{
				\update_option('lws_lic_alternative_enabled', ''); // no need for alternative way

				if ($notice) {
					$this->notice($this->serverMessage($dataBody->message, $dataBody->status_code), 'success');
				}

				$d = (isset($dataBody->licence_expire) && $dataBody->licence_expire) ? \date_create($dataBody->licence_expire) : false;
				if( $d )
				{
					$e = \date_i18n(\get_option('date_format'), $d->getTimestamp());
					if ($notice) {
						if( $d->getTimestamp() >= \date_create()->setTime(0,0,0)->getTimestamp() ) {
							$this->notice(sprintf(__('The license <b>%3$s</b> for <i>%2$s</i> should be renewed on <b>%1$s</b>.', 'lwsmanager'), $e, $this->getName(), $key)
								. ' ' . __("Please ensure your payment method still is valid.", 'lwsmanager'),
								'warning', '-e', false
							);
						} else {
								$this->notice(sprintf(__('The license <b>%3$s</b> for <i>%2$s</i> already expired the <b>%1$s</b>.', 'lwsmanager'), $e, $this->getName(), $key), 'error', '-e');
						}
					}
				} else {
					if ($notice)
						$this->clearNotice(array('error', 'warning'), '-e');
				}
				if( '4' === $z )
					$dataBody->zombie = 'on';

				$this->updateGlobalOption($this->getId(), $d ? $d->getTimestamp() : 'inf');
				$this->readSubscription($dataBody);
				\lws_admin_delete_notice('trial-ends-'.$this->getSlug());

				if( $update && $this->isLite() )
				{
					// page will be redirected after option saved, go to update if required
					\add_filter('wp_redirect', array($this, 'redirectToUpdate'), 50, 2);
				}
				\do_action('lws_manager_license_active', $this->getSlug());
				return true;
			}
			else
			{
				$txt = array(sprintf(__("There was a problem activating the license (%s).", 'lwsmanager'), $key));
				if( isset($dataBody->message) )
					$txt[] = $this->serverMessage($dataBody->message, $dataBody->status_code);
				if ($notice)
					$this->notice(implode('</br>', $txt));
			}
		}
		else
		{
			if ($notice)
				$this->notice(__("There was a problem establishing a connection to the license service.", 'lwsmanager'));
		}

		return false;
	}

	private function notice($msg, $level='error', $suffix='', $once=true)
	{
		$k = ('lws_lic_udt_'.$level.'_'.$this->getSlug().$suffix);
		if( $msg )
		{
			\lws_admin_add_notice($k, $msg, array('level' => $level, 'once'=>$once));
			$this->ignoreSavingConfirmation();
		}
		else
			\lws_admin_delete_notice($k);
	}

	private function clearNotice($levels='error', $suffix='')
	{
		if( !is_array($levels) )
			$levels = array($levels);
		foreach( $levels as $level )
			\lws_admin_delete_notice('lws_lic_udt_'.$level.'_'.$this->getSlug().$suffix);
	}

	/** @see https://woosoftwarelicense.com/documentation/explain-api-status-codes/ */
	private function serverMessage($msg, $code)
	{
		switch($code)
		{
			case 'e002': return __("Invalid licence key.", 'lwsmanager');
			case 'e110': return __("Invalid licence key or licence not active for domain.", 'lwsmanager');
			case 'e112': return __("You had reached the maximum number of domains for this key.", 'lwsmanager');
			case 'e301': return __("Licence Key does not match this product.", 'lwsmanager');
			case 's201': return __("Licence Key Successfully Unassigned.", 'lwsmanager');
			case 's203': return __("Licence Key Is Unassigned.", 'lwsmanager');
			case 's205': return __("Licence key Is Active and Valid for Domain.", 'lwsmanager');
			case 's215': return __("Licence key Is Active and Valid for Domain.", 'lwsmanager');
		}
		return $msg;
	}

	/// keep them in database in case of debug
	private function log($action, $data)
	{
		$k = 'lws_last_license_' . $action;
		$d = \get_option($k, array());
		$data = array(
			'log_date' => \date('Y-m-d H:i:s'),
			'data' => $data,
		);

		if (false !== $this->lastRequest)
			$data['lastRequest'] = $this->lastRequest;
		$this->lastRequest = false;
		if (false !== $this->lastCUrl)
			$data['lastCUrl'] = $this->lastCUrl;
		$this->lastCUrl = false;

		\update_option($k, \array_merge(\is_array($d) ? $d : array(), array($this->getSlug() => $data)), false);
	}

	/** remove any 'Settings saved.' notice. */
	private function ignoreSavingConfirmation()
	{
		\add_filter('pre_set_transient_settings_errors', function(){\lws_admin_delete_notice('lws_ap_page');}, 20);
	}

	private function getTransientUpdateKey()
	{
		$request = \add_query_arg($this->prepareRequest('plugin_update', true), $this->getRemoteUrl());
		$transientKey = ($this->getSlug() . '-lwsudt-' . \md5($request));
		return $transientKey;
	}

	public function clearUpdateTransient()
	{
		\delete_site_transient($this->getTransientUpdateKey());
	}

	/** Psycho mode, we really do not want to replace a pro version by the free one.
	 * @param bool   $reply Holds the current filtered response.
	 * @param string $package The path to the package file for the update.
	 * @return false|\WP_Error False to proceed with the update as normal,
	 * anything else to be returned instead of updating. */
	public function preventDownloadFree($reply, $package, $upgrader, $hookExtra=array())
	{
		$isMe = false;
		if ($hookExtra && \is_array($hookExtra) && isset($hookExtra['plugin'])) {
			$isMe = ($this->getBasename() == $hookExtra['plugin']);
		}

		if ($isMe) {
			if (false !== strpos($package, 'downloads.wordpress.org')
			|| false !== strpos($package, '/wordpress.org/')) {
				// reject download from host of free version
				return new \WP_Error('no_package', sprintf(
					__("You have a %s PRO version installed but license seems deactivated. Please try to activate your license again before update the plugin.", 'lwsmanager'),
					$this->getName()
				));
			}
		}
		return $reply;
	}

	/** Ensure no reference to worpress hosted free version exists anymore.
	 *	Be sure WP will not DL the free again. */
	public function cleanFreeReferences($plugins)
	{
		if ($plugins && \is_object($plugins) && isset($plugins->response) && \is_array($plugins->response)) {
			$path = $this->getBasename();
			if (isset($plugins->response[$path])) {
				// look for me
				$info =& $plugins->response[$path];
				if (isset($info->package)) {
					if (false !== strpos($info->package, 'downloads.wordpress.org')) {
						// remove references to the host of free version
						$plugins->no_update[$path] = $plugins->response[$path];
						unset($plugins->response[$path]);
					}
				} elseif (isset($info->url)) {
					if (false !== strpos($info->url, '/wordpress.org/')) {
						// remove references to the host of free version
						$plugins->no_update[$path] = $plugins->response[$path];
						unset($plugins->response[$path]);
					}
				}
			}
		}
		return $plugins;
	}

	public function checkForUpdate($plugins)
	{
		if (!($plugins && \is_object($plugins)))
			return $plugins;

		if (!(isset($plugins->response) && \is_array($plugins->response)))
			$plugins->response = array();
		if (!(isset($plugins->no_update) && \is_array($plugins->no_update)))
			$plugins->no_update = array();
		$path = $this->getBasename();

		// for now, set as no_update until proof of the opposite
		if (isset($plugins->response[$path])) {
			$plugins->no_update[$path] = $plugins->response[$path];
			unset($plugins->response[$path]);
		} else {
			$plugins->no_update[$path] = (object)array(
				'id'             => 'w.org/plugins/' . $this->getSlug(),
				'slug'           => $this->getSlug(),
				'plugin'         => $path,
				'new_version'    => $this->getPluginVersion(),
				'url'            => '',
				'package'        => '',
				'upgrade_notice' => '',
			);
		}

		//check if cached
		$transientKey = $this->getTransientUpdateKey();
		$data = \get_site_transient($transientKey);
		if( false === $data )
		{
			$request = \add_query_arg($this->prepareRequest('plugin_update'), $this->getRemoteUrl());
			$data = \wp_remote_get($request, $this->getRemoteGetArgs());

			if( \is_wp_error($data) || !\in_array(\intval($data['response']['code']), array(200, 301, 302)) )
			{
				// server call fail
				return $plugins;
			}

			\set_site_transient($transientKey, $data, 60 * 60 * 4 ); // expiration = 4h
		}

		$dataBody = \json_decode($data['body'], false);
		$this->log('plugin_update', $dataBody);

		if( \is_array($dataBody) && $dataBody )
		{
			$dataBody = \end($dataBody);
			$response = (\is_object($dataBody) && isset($dataBody->message)) ? $dataBody->message : false;

			if( \is_object($response) && $response ) // Feed the update data into WP updater
			{
				$response = $this->postprocessResponse($response);
				$plugins->response[$path] = $response;
				unset($plugins->no_update[$path]);
				return $plugins;
			}
		}

		return $plugins;
	}

	function doRemoteGet($uri, $args)
	{
		$this->lastRequest = false;
		\add_action('http_api_debug', array($this, 'grabRequestDebug'), 10, 5);
		$this->lastCUrl = false;
		\add_action('http_api_curl', array($this, 'grabCUrlDebug'), 10, 3);

		$response = \wp_remote_get($uri, $args);

		\remove_action('http_api_debug', array($this, 'grabRequestDebug'), 10);
		\remove_action('http_api_curl', array($this, 'grabCUrlDebug'), 10);
		return $response;
	}

	function grabRequestDebug($response, $type, $action, $parsed_args, $url)
	{
		$this->lastRequest = array(
			'response'    => $response,
			'type'        => $type, // hardcoded $type = 'response'
			'action'      => $action, // hardcoded $action = 'Requests'
			'parsed_args' => $parsed_args,
			'url'         => $url,
		);
	}

	function grabCUrlDebug(&$cUrl, $request, $url)
	{
		$this->lastCUrl = array(
			'cUrl'    => curl_getinfo($cUrl),
			'request' => $request,
			'url'     => $url,
		);
	}

	function getRemoteGetArgs()
	{
		global $wp_version;
		return array(
			'timeout'    => 20,
			'user-agent' => ('WordPress/' . $wp_version . '; ' . get_bloginfo('url')),
		);
	}

	/** get plugin info for wp 'plugin_api' hook
	 *	@param $def false
	 *	@param $action 'plugin_information'
	 *	@param $args object instance */
	public function checkRemoteInfo($def, $action, $args)
	{
		if( !(\is_object($args) && isset($args->slug) && $this->getSlug() == $args->slug) )
			return $def;

		$request = \add_query_arg($this->prepareRequest($action), $this->getRemoteUrl());
		$data = wp_remote_get($request, $this->getRemoteGetArgs());

		if( \is_wp_error($data) || !\in_array(\intval($data['response']['code']), array(200, 301, 302)) )
		{
			$txt = __('An Unexpected HTTP Error occurred during the API request.' , 'lwsmanager');
			return new \WP_Error('plugins_api_failed', $txt, $data);
		}

		$dataBody = \json_decode($data['body'], false);
		$this->log($action, $dataBody);
		if( \is_array($dataBody) )
			$dataBody = \end($dataBody);

		$response = false;
		if( $dataBody && \is_object($dataBody) && isset($dataBody->message) )
			$response = $dataBody->message;

		if( \is_object($response) && $response )
		{
			if( isset($response->licence_expire) )
			{
				if( $response->licence_expire && $this->isActive() && ($d = \date_create($response->licence_expire)) )
					$this->updateGlobalOption($this->getId(), $d->getTimestamp());
				unset($response->licence_expire);
			}
			if( isset($response->trial_expire) )
			{
				if( $this->isTrial() )
				{
					$ending = $this->getGlobalOption('lws-license-end-'.$args->slug);
					if( $ending && ($ending = \date_create($ending)) )
						$response->trial_expire = \min($ending->getTimestamp(), $response->trial_expire);
					$this->updateGlobalOption($this->getId('lwstrial_'), \absint($response->trial_expire));
				}
				unset($response->trial_expire);
			}
			$this->readSubscription($response);
			return $this->postprocessResponse($response);
		}
		else
		{
			$txt = __('Unexpected response from API.' , 'lwsmanager');
			return new \WP_Error('plugins_api_failed', $txt, $response);
		}
	}

	private function prepareRequest($action, $real=false)
	{
		global $wp_version;
		$slug = $this->getSlug();

		$query = array(
			$this->getActionKey()   => $action,
			'product_unique_id'     => $this->uuid,
			'licence_key'           => $this->fk ? $this->fk : $this->getKey(),
			'version'               => $this->getPluginVersion(),
			'domain'                => $this->getSiteUrl(),
			'wp-version'            => $wp_version,
			'api_version'           => self::API_VERSION,
		);

		if( !$real && !$this->fk && $this->isLite() && ($this->isActive() || $this->isTrial()) )
			$query['version'] = '0.0.0'; // we have to go to pro, so let it be newer

		return $query;
	}

	private function readSubscription(&$response, $force=false)
	{
		if (!$force && ($this->fk || !$this->maybeActive())) {
			return;
		}

		if( isset($response->subscription_status) )
		{
			$was = $this->isSubscriptionActive();
			$this->updateGlobalOption($this->getId('lwssupport_'), $response->subscription_status);

			if( !$this->isSubscriptionActive() )
			{
				if( $was )
				{
					if( $this->isZombie() )
					{
						$msg = sprintf(
							__('Your support access for the plugin <b>%1$s</b> is no longer available. Please visit %2$s to expend your support period.', 'lwsmanager'),
							$this->getName(),
							sprintf('<a href="%s" target="_blank">%s</a>', $this->getRemoteMyAccountURL(), $this->getPluginAuthor())
						);
						\lws_admin_add_notice('lwssupport_e_'.$this->getSlug(), $msg, array('level' => 'info'));
					}
					else
					{
						$msg = sprintf(
							__('Your subscription to plugin <b>%1$s</b> Premium Services expired. Please visit %2$s to expend your license period.', 'lwsmanager'),
							$this->getName(),
							sprintf('<a href="%s" target="_blank">%s</a>', $this->getRemoteMyAccountURL(), $this->getPluginAuthor())
						);
						\lws_admin_add_notice('lwssupport_e_'.$this->getSlug(), $msg, array('level' => 'error'));
					}
				}
			}
			else
			{
				\lws_admin_delete_notice('lwssupport_e_'.$this->getSlug());
			}
			unset($response->subscription_status);
		}
		else
			$this->updateGlobalOption($this->getId('lwssupport_'), '');

		if( isset($response->zombie) )
		{
			$this->updateGlobalOption($this->getId('lwszombie_'), $response->zombie);
		}
	}

	function getRemoteMyAccountURL()
	{
		$page = '/my-account/'; // (?) /my-account/subscriptions/
		return \apply_filters('lws_adm_license_remote_myaccount_url', $this->getRemoteUrl($page), $this->getSlug());
	}

	private function postprocessResponse($response)
	{
		//include slug and plugin data
		$response->slug    = $this->getSlug();
		$response->plugin  = $this->getBasename();

		//if sections are being set
		if( isset($response->sections) )
			$response->sections = (array)$response->sections;
		//if banners are being set
		if( isset($response->banners) )
			$response->banners = (array)$response->banners;
		//if icons being set, convert to array
		if( isset($response->icons) )
			$response->icons = (array)$response->icons;

		return $response;
	}

	function installUpdater($force=false)
	{
		// Take over the update check
		$this->addFilters();
		// Take over the Plugin info screen
		\add_filter('plugins_api', array($this, 'checkRemoteInfo') , PHP_INT_MAX, 3);

		if ($this->isTrial())
			$this->noticeTrialEndsSoon();
		if ($force)
			$this->fk = 'longwatchstudio';
	}

	// Warn before trial ends
	private function noticeTrialEndsSoon()
	{
		if( $e = $this->getTrialEnding() )
		{
			$diff = $e->diff(\date_create(), true)->format('%a');
			$last = \get_option($this->getId('lasttdiff_'), PHP_INT_MAX);
			foreach( $this->trialWarnings as $delay )
			{
				if( $delay < $last && $diff <= $delay )
				{
					\update_option($this->getId('lasttdiff_'), $delay);
					$k = 'trial-ends-'.$this->getSlug();
					$link = sprintf(
						"<a href='%s' target='_blank'>%s</a>",
						\esc_attr(\apply_filters('lws_adm_license_product_page_url', $this->getPluginURI(), $this->getSlug())),
						sprintf(__("%s Premium", 'lwsmanager'), $this->getName())
					);
					$date = \date_i18n(\get_option('date_format'), $e->getTimestamp());
					$msg = \apply_filters('lws_adm_trialend_msg', '', $this->getSlug(), $date, $link, $delay, $diff, $e);
					if (!$msg)
						$msg = sprintf('<h2>' . __('Your Trial period expires the %1$d. Consider purchasing %2$s.', 'lwsmanager') . '</h2>', $date, $link);
					\lws_admin_add_notice($k, $msg, array('level' => 'warning', 'dismissible' => true, 'forgettable' => true));
				}
			}
		}
	}

	protected function addFilters()
	{
		\add_filter('pre_set_site_transient_update_plugins', array($this, 'checkForUpdate'), PHP_INT_MAX);
		\add_filter('site_transient_update_plugins', array($this, 'cleanFreeReferences'), PHP_INT_MAX);
		\add_filter('pre_site_transient_update_plugins', array($this, 'cleanFreeReferences'), PHP_INT_MAX);
		\add_filter('upgrader_pre_download', array($this, 'preventDownloadFree'), PHP_INT_MAX, 4);
	}

	/** tweak option saved to try to update to premium
	 * if code is not already here, instead of returning
	 * to original options page. */
	function redirectToUpdate($location, $status)
	{
		if( $this->isLite() && ($this->isActive() || $this->isTrial()) )
		{
			// ensure we filter update check now
			if( !\has_filter('pre_set_site_transient_update_plugins', array($this, 'checkForUpdate')) ) {
				$this->addFilters();
			}

			// let usual trick performs itself, force the update info refresh
			$this->clearUpdateTransient();
			$transients = \get_site_transient('update_plugins');
			// ensure transient minimal values
			if( !(\is_object($transients) && isset($transients->response)) )
			{
				$transients = (object)array(
					'last_checked' => \time(),
					'response'     => array(),
					'no_update'    => array(),
					'translations' => array(),
				);
			}
			\set_site_transient('update_plugins', $transients);

			// get it again
			$transients = \get_site_transient('update_plugins');
			$path = $this->getBasename();
			if( !(\is_object($transients) && isset($transients->response, $transients->response[$path])) )
				return $location;

			// go to running update page with our plugin selected
			$args = array(
				'action' => 'upgrade-plugin',
				'plugin' => $path,
				'_wpnonce' => \wp_create_nonce('upgrade-plugin_' . $path),
			);
			$location = \self_admin_url('update.php');
			$location = \add_query_arg($args, $location);
		}
		return $location;
	}
}
