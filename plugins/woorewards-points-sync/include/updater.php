<?php
namespace LWS\WR_POINTS_SYNC;
// don't call the file directly
if( !defined( 'ABSPATH' ) ) exit();

/** satic class to manage activation and version updates. */
class Updater
{
	static function checkUpdate()
	{
		$reload = false;
		$wpInstalling = \wp_installing();
		\wp_installing(true); // should force no cache

		$from = \get_option('lws_woorewards-points-sync_version', '0');
		if( version_compare($from, LWS_WR_POINTS_SYNC_VERSION, '<') )
		{
			\wp_suspend_cache_invalidation(false);
			$me = new self();
			$reload = $me->update($from, LWS_WR_POINTS_SYNC_VERSION);
			\update_option('lws_woorewards-points-sync_version', LWS_WR_POINTS_SYNC_VERSION);
		}

		\wp_installing($wpInstalling);
		if( $reload )
		{
			// be sure to reload pools after update
			\wp_redirect($_SERVER['REQUEST_URI']);
		}
	}

	/** Update
	 * @param $fromVersion previously registered version.
	 * @param $toVersion actual version. */
	function update($fromVersion, $toVersion)
	{
		$reload = false;
		$this->from = $fromVersion;
		$this->to = $toVersion;

		$this->database();

		if( \version_compare($fromVersion, '0.0.2', '<') )
		{

			$reload = true;
		}

		return $reload;
	}

	protected function database()
	{
		global $wpdb;
		$charset_collate = $wpdb->get_charset_collate();

		$syncs = <<<EOT
CREATE TABLE `{$wpdb->lwsWRPointsSync}` (
	`id` BIGINT(20) NOT NULL AUTO_INCREMENT,
	`status` VARCHAR(20) DEFAULT 'pending' COMMENT 'pending, done, failed',
	`reference` VARCHAR(40) NOT NULL COMMENT 'xx_options.option_name = lws_wr_pts_sync_remotes',
	`user_id` BIGINT(20) NOT NULL,
	`reason` TEXT NOT NULL DEFAULT '' COMMENT 'Operation details',
	`points` int(10) NOT NULL COMMENT 'Value to sync',
	`action` VARCHAR(3) NOT NULL COMMENT 'add, sub, set',
	`origin` tinytext NOT NULL DEFAULT '' COMMENT 'eg. unlockable/event post id (max 255 char)',
	`creation` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Trial activation date',
	`last_attempt` TIMESTAMP NULL DEFAULT NULL COMMENT 'just before api call',
	`last_success` TIMESTAMP NULL DEFAULT NULL COMMENT 'Sync date for success status',
	`last_failure` TIMESTAMP NULL DEFAULT NULL COMMENT 'Sync date for failure status',
	`sync_status` VARCHAR(8) NOT NULL DEFAULT '' COMMENT 'status code for last successfull exchange',
	`sync_count` int(4) NOT NULL DEFAULT 0 COMMENT 'Number of attempt',
	PRIMARY KEY `id`  (`id`),
	KEY `status` (`status`),
	KEY `reference` (`reference`),
	KEY `sync_count` (`sync_count`)
	) {$charset_collate};
EOT;

		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
		$this->grabLog();
		dbDelta($syncs);
		$this->releaseLog();
	}

	/// dbDelta could write on standard output @see releaseLog()
	protected function grabLog()
	{
		ob_start(function($msg){
			if( !empty($msg) )
				error_log($msg);
		});
	}

	/// @see grabLog()
	protected function releaseLog()
	{
		ob_end_flush();
	}
}
