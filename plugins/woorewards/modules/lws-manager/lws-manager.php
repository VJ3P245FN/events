<?php
/**
 * Plugin Name: LWS Manager
 * Description: Manage LWS plugins install and support.
 * Plugin URI: https://plugins.longwatchstudio.com
 * Author: Long Watch Studio
 * Author URI: https://longwatchstudio.com
 * Version: 2.4.5
 * Text Domain: lwsmanager
 *
 * Copyright (c) 2022 Long Watch Studio (email: plugins@longwatchstudio.com). All rights reserved.
 *
 */

// don't call the file directly
if( !defined( 'ABSPATH' ) ) exit();

add_filter('lws_manager_instance', function($instance){
	if (!$instance) {
		require_once \dirname(__FILE__) . '/include/manager.php';
		$instance = (object)array(
			'version'  => '9.9.9', // hardcoded for backward compatibility but not used anymore
			'instance' => new \LWS\Manager\Manager(),
		);
	}
	return $instance;
}, -2004005); // priority based on version => -((major*1000 + minor)*1000) + bugfix
