<?php
namespace LWS\Manager;
// don't call the file directly
if( !defined( 'ABSPATH' ) ) exit();

class Manager
{
	/** Register a plugin with premium
	 *  @param $file main php file of the plugin.
	 *	@param $adminPageId the id of the main administration page. */
	function install($file, $adminPageId, $uuid, $def=false, $targetPage=false)
	{
		$this->init();

		require_once LWS_MANAGER_INCLUDES . '/ui/managerpage.php';
		\LWS\Manager\Ui\ManagerPage::install($file, $adminPageId, $uuid, $def, $targetPage);

		require_once LWS_MANAGER_INCLUDES . '/ui/supportpage.php';
		if( !$targetPage )
			$targetPage = $adminPageId.'_lic';
		\LWS\Manager\Ui\SupportPage::install($file, $adminPageId, $targetPage);
	}

	/** Register an addon plugin (p)
	 *  @param $file main php file of the plugin.
	 *	@param $masterSlug the plugin slug, this one belong to. */
	function add($file, $masterSlug, $addonUuid, $def=false)
	{
		$this->init();

		require_once LWS_MANAGER_INCLUDES . '/ui/managerpage.php';
		\LWS\Manager\Ui\ManagerPage::installAddon($file, $masterSlug, $addonUuid, $def);
	}

	/** Register an addon plugin (f)
	 *  @param $file main php file of the plugin.
	 *	@param $masterSlug the plugin slug, this one belong to. */
	function register($file, $masterSlug, $addonUuid)
	{
		$this->init();

		require_once LWS_MANAGER_INCLUDES . '/ui/managerpage.php';
		\LWS\Manager\Ui\ManagerPage::registerAddon($file, $masterSlug, $addonUuid);
	}

	function test($file, $adminPageId='', $uuid='')
	{
		$this->init();

		if( $uuid )
		{
			require_once LWS_MANAGER_INCLUDES . '/ui/managerpage.php';
			$tab = true;
			if( \is_array($adminPageId) && $adminPageId ){
				$tab = $adminPageId;
				$adminPageId = \reset($adminPageId);
			}
			\LWS\Manager\Ui\ManagerPage::install($file, $adminPageId, $uuid, false, $tab);
		}

		require_once LWS_MANAGER_INCLUDES . '/core/manager.php';
		$lic = new \LWS\Manager\Core\Manager($file, $uuid);
		return $lic->isRunning();
	}

	protected function init()
	{
		if (!defined('LWS_MANAGER_PATH')) {
			define('LWS_MANAGER_PATH',     \realpath(\dirname(__FILE__).'/..'));
			define('LWS_MANAGER_FILE',     LWS_MANAGER_PATH . '/lws-manager.php');
			define('LWS_MANAGER_INCLUDES', LWS_MANAGER_PATH . '/include');
			define('LWS_MANAGER_URL',      \plugins_url('', LWS_MANAGER_FILE));
			define('LWS_MANAGER_JS',       \plugins_url('/js', LWS_MANAGER_FILE));
			define('LWS_MANAGER_CSS',      \plugins_url('/styling/css', LWS_MANAGER_FILE));
			define('LWS_MANAGER_DOMAIN',  'lwsmanager');
			define('LWS_MANAGER_VERSION' , '2.4.5');

			require_once LWS_MANAGER_INCLUDES . '/api.php';
		}
	}

	/** Read version from main plugin file.
	 *	@return string */
	public function v()
	{
		static $version = '';
		if( empty($version) ){
			if( !function_exists('get_plugin_data') ) require_once(ABSPATH . 'wp-admin/includes/plugin.php');
			$data = \get_plugin_data(LWS_MANAGER_FILE, false);
			$version = (isset($data['Version']) ? $data['Version'] : '0');
		}
		return $version;
	}
}