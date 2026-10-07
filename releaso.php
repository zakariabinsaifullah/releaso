<?php
/**
 * Plugin Name:       Releaso
 * Plugin URI:        https://gutenbergkits.com/releaso
 * Description:       Beautiful, filterable changelogs for your plugins. Pulls release notes from WordPress.org, installed plugins, GitHub or any readme.txt / CHANGELOG.md / JSON URL, and lets you write, paste or import releases. Shown with the Releaso block or the [releaso] shortcode.
 * Version:           1.0.0
 * Requires at least: 6.5
 * Requires PHP:      7.4
 * Author:            Gutenbergkits
 * Author URI:        https://gutenbergkits.com
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       releaso
 * Domain Path:       /languages
 *
 * @package Releaso
 */

defined( 'ABSPATH' ) || exit;

define( 'RELEASO_VERSION', '1.0.0' );
define( 'RELEASO_FILE', __FILE__ );
define( 'RELEASO_DIR', __DIR__ );
define( 'RELEASO_URL', plugin_dir_url( __FILE__ ) );
define( 'RELEASO_MIN_PHP', '7.4' );

if ( version_compare( PHP_VERSION, RELEASO_MIN_PHP, '<' ) ) {
	add_action(
		'admin_notices',
		static function () {
			echo '<div class="notice notice-error"><p>';
			/* translators: %s: required PHP version */
			echo esc_html( sprintf( __( 'Releaso requires PHP %s or newer.', 'releaso' ), RELEASO_MIN_PHP ) );
			echo '</p></div>';
		}
	);
	return;
}

require_once RELEASO_DIR . '/includes/autoload.php';

register_activation_hook( __FILE__, array( \Releaso\Core\Installer::class, 'activate' ) );
register_deactivation_hook( __FILE__, array( \Releaso\Core\Installer::class, 'deactivate' ) );

add_action( 'plugins_loaded', array( \Releaso\Plugin::class, 'instance' ) );

/**
 * The plugin instance, for use by themes and add-ons.
 *
 * @return \Releaso\Plugin
 */
function releaso() {
	return \Releaso\Plugin::instance();
}
