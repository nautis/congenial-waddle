<?php
/**
 * Plugin Name: WP RSS Importer
 * Plugin URI: https://github.com/nautis/congenial-waddle
 * Description: Import and aggregate content from RSS, Atom, and other syndication feeds with keyword filtering and category management.
 * Version: 1.0.0
 * Author: Your Name
 * Author URI: https://github.com/nautis
 * License: GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain: wp-rss-importer
 * Domain Path: /languages
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
    die;
}

// Plugin version
define( 'WP_RSS_IMPORTER_VERSION', '1.0.0' );
define( 'WP_RSS_IMPORTER_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'WP_RSS_IMPORTER_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

/**
 * The code that runs during plugin activation.
 */
function activate_wp_rss_importer() {
    require_once WP_RSS_IMPORTER_PLUGIN_DIR . 'includes/class-activator.php';
    WP_RSS_Importer_Activator::activate();
}

/**
 * The code that runs during plugin deactivation.
 */
function deactivate_wp_rss_importer() {
    require_once WP_RSS_IMPORTER_PLUGIN_DIR . 'includes/class-deactivator.php';
    WP_RSS_Importer_Deactivator::deactivate();
}

register_activation_hook( __FILE__, 'activate_wp_rss_importer' );
register_deactivation_hook( __FILE__, 'deactivate_wp_rss_importer' );

/**
 * The core plugin class
 */
require WP_RSS_IMPORTER_PLUGIN_DIR . 'includes/class-wp-rss-importer.php';

/**
 * Begins execution of the plugin.
 */
function run_wp_rss_importer() {
    $plugin = new WP_RSS_Importer();
    $plugin->run();
}
run_wp_rss_importer();
