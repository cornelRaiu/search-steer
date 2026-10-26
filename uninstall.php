<?php

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

if ( ! is_readable( __DIR__ . '/vendor/autoload.php' ) ) {
	return;
}

require_once __DIR__ . '/vendor/autoload.php';

if ( ! is_multisite() ) {
	SearchSteer\Install\Uninstaller::run();
	return;
}

foreach ( get_sites(
	[
		'fields' => 'ids',
		'number' => 0,
	]
) as $mwtss_site_id ) {
	switch_to_blog( (int) $mwtss_site_id );
	SearchSteer\Install\Uninstaller::run();
	restore_current_blog();
}
