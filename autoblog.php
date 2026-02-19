<?php

/**
 * The plugin bootstrap file
 *
 * This file is read by WordPress to generate the plugin information in the plugin
 * administrative area. This file also includes all of the plugin dependencies.
 *
 * @link              https://crediblemark.com
 * @since             1.0.0
 * @package           Autoblog
 *
 * @wordpress-plugin
 * Plugin Name:       Autoblog AI
 * Plugin URI:        https://crediblemark.com
 * Description:       An intelligent autoblog plugin that scrapes, processes, and publishes content using AI.
 * Version:           1.0.0
 * Author:            CredibleMark
 * Author URI:        https://crediblemark.com
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       autoblog
 * Domain Path:       /languages
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Currently plugin version.
 * Start at version 1.0.0 and use SemVer - https://semver.org
 * Rename this for your plugin and update it as you release new versions.
 */
define( 'AUTOBLOG_VERSION', '1.0.0' );

/**
 * Payload path
 */
define( 'AUTOBLOG_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'AUTOBLOG_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

/**
 * Autoload dependencies
 */
if ( file_exists( AUTOBLOG_PLUGIN_DIR . 'vendor/autoload.php' ) ) {
	require_once AUTOBLOG_PLUGIN_DIR . 'vendor/autoload.php';
}

/**
 * The code that runs during plugin activation.
 * This action is documented in includes/Core/Activator.php
 */
function activate_autoblog() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/Core/Activator.php';
	\Autoblog\Core\Activator::activate();
}

/**
 * The code that runs during plugin deactivation.
 * This action is documented in includes/Core/Deactivator.php
 */
function deactivate_autoblog() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/Core/Deactivator.php';
	\Autoblog\Core\Deactivator::deactivate();
}

register_activation_hook( __FILE__, 'activate_autoblog' );
register_deactivation_hook( __FILE__, 'deactivate_autoblog' );

/**
 * The core plugin class that is used to define internationalization,
 * admin-specific hooks, and public-facing site hooks.
 */
require plugin_dir_path( __FILE__ ) . 'includes/Core/Autoblog.php';

/**
 * Begins execution of the plugin.
 *
 * Since everything within the plugin is registered via hooks,
 * then kicking off the plugin from this point in the file does
 * not affect the page life cycle.
 *
 * @since    1.0.0
 */
function run_autoblog() {

	$plugin = new \Autoblog\Core\Autoblog();
	$plugin->run();

}
run_autoblog();
