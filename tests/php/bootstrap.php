<?php

$mwtss_root = dirname( __DIR__, 2 );

require_once $mwtss_root . '/vendor/autoload.php';

// Hosted setups such as wp-env provide their own test library. Otherwise use the wp-phpunit package.
$mwtss_tests_dir = getenv( 'WP_TESTS_DIR' );
if ( ! $mwtss_tests_dir ) {
	$mwtss_tests_dir = getenv( 'WP_PHPUNIT__DIR' );
	putenv( 'WP_PHPUNIT__TESTS_CONFIG=' . __DIR__ . '/wp-tests-config.php' );
}

define( 'WP_TESTS_PHPUNIT_POLYFILLS_PATH', $mwtss_root . '/vendor/yoast/phpunit-polyfills' );

require_once $mwtss_tests_dir . '/includes/functions.php';

tests_add_filter(
	'muplugins_loaded',
	static function () use ( $mwtss_root ) {
		require $mwtss_root . '/search-steer.php';
	}
);

require $mwtss_tests_dir . '/includes/bootstrap.php';
