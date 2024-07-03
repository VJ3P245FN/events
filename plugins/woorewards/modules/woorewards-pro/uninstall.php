<?php
if( !defined( 'WP_UNINSTALL_PLUGIN' ) ) exit();

delete_site_option('lws-ap-activation-woorewards');
delete_site_option('lws-license-key-woorewards');
delete_site_option('lws-ap-release-woorewards');

// remove custom role (if still without capacity )
foreach( \wp_roles()->get_names() as $value => $label )
{
	if( 0 === strpos($value, 'lws_wr_') )
	{
		foreach( \get_users(array('role'=>$value)) as $user )
			$user->remove_role($value);

		if( ($role = \wp_roles()->get_role($value)) && empty($role->capabilities) )
			\remove_role($value);
	}
}
