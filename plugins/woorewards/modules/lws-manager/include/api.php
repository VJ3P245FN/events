<?php
namespace LWS\Manager;

// don't call the file directly
if( !defined( 'ABSPATH' ) ) exit();


/** Let external server call local website about activation. */
class API
{
	private $manager = null;
	private $data = array();

	static function register(&$manager, $productUId)
	{
		if (isset($_GET['lws_lapi']) && self::isAuthorizedServer()) {
			if ('*' === $_GET['lws_lapi']) {
				$me = new self();
				$me->lite($manager);
				exit;
			} else {
				$slug = \sanitize_key($_GET['lws_lapi']);
				if ($slug && $slug == $productUId) {
					$me = new self();
					$me->run($manager);
					exit;
				}
			}
		}
	}

	static protected function getArg($name, $default=false)
	{
		return isset($_GET[$name]) ? \trim($_GET[$name]) : $default;
	}

	static protected function getArgKey($name, $default=false)
	{
		return isset($_GET[$name]) ? \sanitize_key($_GET[$name]) : $default;
	}

	/** ping only */
	protected function lite(&$manager)
	{
		$this->manager =& $manager;
		$this->doPing();
		$this->output();
	}

	protected function run(&$manager)
	{
		$this->manager =& $manager;
		switch (self::getArgKey('action')) {
			case 'activate':
				$this->doActivation();
				break;
			case 'deactivate':
				$this->doDeactivation();
				break;
			case 'check':
				$this->doCheckRequest();
				break;
			case 'ping':
			default: // ping
				$this->doPing();
		}
		$this->output();
	}

	function doPing()
	{
		$this->add('ping', $this->getManager()->getSiteUrl());
		$this->add('version', $this->getManager()->getPluginVersion());
		$hash = self::getArgKey('h', false);
		if (false != $hash) {
			$this->add('ctrl', $this->getControl() === $hash ? 'y' : 'n');
		}
		$this->add('status', $this->getManager()->isActive() ? 'active' : 'idle');
	}

	function doCheckRequest()
	{
		$this->add('ping', $this->getManager()->getSiteUrl());
		$this->add('version', $this->getManager()->getPluginVersion());
		$ctrl = (self::getArgKey('h', false) === $this->getControl($this->getManager()->getKey()));
		$this->add('ctrl', $ctrl ? 'y' : 'n');

		if (!$ctrl) {
			$this->add('status', 'fail');
			$this->add('message', __("Security check failed.", 'lwsmanager'));
		} else {
			$this->getManager()->updateGlobalOption($this->getManager()->getId('lwschk_'), \time() - 60);
			$this->add('status', 'asap');
		}
	}

	function doDeactivation()
	{
		$this->add('ping', $this->getManager()->getSiteUrl());
		$this->add('version', $this->getManager()->getPluginVersion());
		$ctrl = (self::getArgKey('h', false) === $this->getControl());
		$this->add('ctrl', $ctrl ? 'y' : 'n');

		if (!$ctrl) {
			$this->add('status', 'fail');
			$this->add('message', __("Security check failed.", 'lwsmanager'));
		} else {
			$key = isset($_POST['licence_key']) ? \sanitize_text_field($_POST['licence_key']) : false;
			$query = isset($_POST['query']) ? \json_decode($_POST['query']) : null;
			if (!($query && \is_object($query) && $key)) {
				$this->add('status', 'fail');
				$this->add('message', __("Unexpected or corrupted data.", 'lwsmanager'));
			} elseif ($key != $this->getManager()->getKey()) {
				$this->add('status', 'mismatch');
				$this->add('message', __("Local and remote key mismatch.", 'lwsmanager'));
			} elseif ($this->getManager()->deactivateFromData($query, $key, false, false)) {
				$this->add('status', 'deactivated');
			} else {
				$this->add('status', 'fail');
			}
		}
	}

	function doActivation()
	{
		$this->add('ping', $this->getManager()->getSiteUrl());
		$this->add('version', $this->getManager()->getPluginVersion());
		$ctrl = (self::getArgKey('h', false) === $this->getControl());
		$this->add('ctrl', $ctrl ? 'y' : 'n');

		if (!$ctrl) {
			$this->add('status', 'fail');
			$this->add('message', __("Security check failed.", 'lwsmanager'));
		} else {
			$query = isset($_POST['query']) ? \json_decode($_POST['query']) : null;
			$key = isset($_POST['licence_key']) ? \sanitize_text_field($_POST['licence_key']) : false;
			$rec = isset($_POST['recurrence']) ? \absint($_POST['recurrence']) : false;
			$override = ('allow' == \sanitize_key(isset($_POST['override']) ? $_POST['override'] : 'allow'));

			// bad request
			if (!($query && \is_object($query) && $key && $rec)) {
				$this->add('status', 'fail');
				$this->add('message', __("Unexpected or corrupted data.", 'lwsmanager'));
				return;
			}
			// mismatch test
			if (!$override) {
				$local = $this->getManager()->getKey();
				if ($local && ($local != $key)) {
					$this->add('status', 'mismatch');
					$this->add('message', __("Local and remote key mismatch.", 'lwsmanager'));
					return;
				}
			}
			// usual case
			if ($this->getManager()->activateFromData($query, $key, false, false, false)) {
				$this->add('status', 'activated');
				// remove hook before saving the key
				\remove_all_actions('pre_update_option_' . $this->getManager()->getKeyOption(), 10);
				$this->getManager()->updateKey($key);
				$this->getManager()->updateGlobalOption($this->getManager()->getId('lwschk_'), $rec);
				return;
			}
			// fallback
			$this->add('status', 'fail');
		}
	}

	private function getControl($salt=false)
	{
		return \hash('md5', \serialize(array(
			'action' => self::getArgKey('action', ''),
			'slug'   => self::getArgKey('lws_lapi', ''),
			'r'      => self::getArgKey('r', '-'),
			'salt'   => $salt ? $salt : $this->getManager()->getFingerprint(),
		)));
	}

	function output()
	{
		\header('Content-Type: text/plain');
		foreach ($this->data as $k => $v) {
			echo sprintf("%s: %s\n", $k, $v);
		}
	}

	function add($data, $value=false)
	{
		if (\is_array($data))
			$this->data = \array_merge($this->data, $data);
		else
			$this->data[$data] = $value;
	}

	protected function &getManager()
	{
		return $this->manager;
	}

	/** Test a fixed user agent to not mess-up with usual behavior. */
	static protected function isAuthorizedServer()
	{
		if (!isset($_SERVER['HTTP_USER_AGENT']))
			return false;
		return \preg_match('`WordPress/\s*[-\.\+\w]+\s*;\s*https://plugins.longwatchstudio.com`i', $_SERVER['HTTP_USER_AGENT']);
	}
}