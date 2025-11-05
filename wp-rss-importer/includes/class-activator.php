<?php
/**
 * Fired during plugin activation
 */

if ( ! defined( 'WPINC' ) ) {
    die;
}

class WP_RSS_Importer_Activator {

    /**
     * Activation tasks
     */
    public static function activate() {
        // Register custom post types so we can flush rewrite rules
        require_once WP_RSS_IMPORTER_PLUGIN_DIR . 'includes/class-post-types.php';
        $post_types = new WP_RSS_Importer_Post_Types();
        $post_types->register_post_types();
        $post_types->register_taxonomies();

        // Flush rewrite rules
        flush_rewrite_rules();

        // Schedule cron job if not already scheduled
        if ( ! wp_next_scheduled( 'wp_rss_importer_fetch_feeds' ) ) {
            wp_schedule_event( time(), 'hourly', 'wp_rss_importer_fetch_feeds' );
        }
    }
}
