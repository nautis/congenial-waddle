<?php
/**
 * The core plugin class
 */

if ( ! defined( 'WPINC' ) ) {
    die;
}

class WP_RSS_Importer {

    /**
     * The loader that's responsible for maintaining and registering all hooks
     */
    protected $loader;

    /**
     * The unique identifier of this plugin
     */
    protected $plugin_name;

    /**
     * The current version of the plugin
     */
    protected $version;

    /**
     * Initialize the plugin
     */
    public function __construct() {
        $this->version = WP_RSS_IMPORTER_VERSION;
        $this->plugin_name = 'wp-rss-importer';

        $this->load_dependencies();
        $this->define_admin_hooks();
        $this->define_public_hooks();
    }

    /**
     * Load the required dependencies
     */
    private function load_dependencies() {
        require_once WP_RSS_IMPORTER_PLUGIN_DIR . 'includes/class-loader.php';
        require_once WP_RSS_IMPORTER_PLUGIN_DIR . 'includes/class-post-types.php';
        require_once WP_RSS_IMPORTER_PLUGIN_DIR . 'includes/class-admin.php';
        require_once WP_RSS_IMPORTER_PLUGIN_DIR . 'includes/class-feed-importer.php';
        require_once WP_RSS_IMPORTER_PLUGIN_DIR . 'includes/class-cron.php';

        $this->loader = new WP_RSS_Importer_Loader();
    }

    /**
     * Register all hooks related to admin area functionality
     */
    private function define_admin_hooks() {
        $post_types = new WP_RSS_Importer_Post_Types();
        $this->loader->add_action( 'init', $post_types, 'register_post_types' );
        $this->loader->add_action( 'init', $post_types, 'register_taxonomies' );

        $admin = new WP_RSS_Importer_Admin( $this->plugin_name, $this->version );
        $this->loader->add_action( 'admin_menu', $admin, 'add_admin_menu' );
        $this->loader->add_action( 'admin_enqueue_scripts', $admin, 'enqueue_styles' );
        $this->loader->add_action( 'admin_enqueue_scripts', $admin, 'enqueue_scripts' );
        $this->loader->add_action( 'add_meta_boxes', $admin, 'add_meta_boxes' );
        $this->loader->add_action( 'save_post_feed_source', $admin, 'save_feed_source_meta', 10, 2 );
    }

    /**
     * Register cron hooks for feed importing
     */
    private function define_public_hooks() {
        $cron = new WP_RSS_Importer_Cron();
        $this->loader->add_action( 'wp_rss_importer_fetch_feeds', $cron, 'fetch_all_feeds' );
    }

    /**
     * Run the loader to execute all hooks
     */
    public function run() {
        $this->loader->run();
    }

    /**
     * Get the plugin name
     */
    public function get_plugin_name() {
        return $this->plugin_name;
    }

    /**
     * Get the version number
     */
    public function get_version() {
        return $this->version;
    }
}
