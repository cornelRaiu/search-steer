<?php
/**
 * Plugin Name:       Search Steer
 * Plugin URI:        https://www.master-webtools.com/plugins/search-steer/
 * Description:       Hide posts from site search, or steer search per post: keywords bring a post into the results, stop keywords keep it out.
 * Version:           1.0.0
 * Requires at least: 6.6
 * Requires PHP:      7.4
 * Author:            Cornel Raiu
 * Author URI:        https://www.cornelraiu.com/
 * License:           GPLv3 or later
 * License URI:       https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain:       search-steer
 * Domain Path:       /languages
 *
 * @package SearchSteer
 */

defined( 'ABSPATH' ) || exit;

// Keep this file free of newer PHP syntax so the notice below can still show on old PHP versions.
$mwtss_problem = '';
if ( version_compare( PHP_VERSION, '7.4', '<' ) || version_compare( get_bloginfo( 'version' ), '6.6', '<' ) ) {
	$mwtss_problem = 'requirements';
} elseif ( ! is_readable( __DIR__ . '/vendor/autoload.php' ) ) {
	$mwtss_problem = 'vendor';
}

if ( '' !== $mwtss_problem ) {
	add_action(
		'admin_notices',
		static function () use ( $mwtss_problem ) {
			global $pagenow;

			// Only on the Plugins screen, and only to the people who can fix it.
			if ( 'plugins.php' !== $pagenow || ! current_user_can( 'activate_plugins' ) ) {
				return;
			}

			$message = 'requirements' === $mwtss_problem
				? __( 'Search Steer needs PHP 7.4 or later and WordPress 6.6 or later, so it is not running. Update PHP or WordPress to use it.', 'search-steer' )
				: __( 'Search Steer is missing its vendor folder, so it is not running. Run composer install, or reinstall the plugin from WordPress.org.', 'search-steer' );

			printf( '<div class="notice notice-error"><p>%s</p></div>', esc_html( $message ) );
		}
	);
	return;
}

require_once __DIR__ . '/vendor/autoload.php';

SearchSteer\Plugin::boot( __FILE__ );
