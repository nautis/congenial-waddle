<?php
/**
 * Fired during plugin deactivation
 */

if ( ! defined( 'WPINC' ) ) {
    die;
}

class WP_RSS_Importer_Deactivator {

    /**
     * Deactivation tasks
     */
    public static function deactivate() {
        // Clear scheduled cron job
        $timestamp = wp_next_scheduled( 'wp_rss_importer_fetch_feeds' );
        if ( $timestamp ) {
            wp_unschedule_event( $timestamp, 'wp_rss_importer_fetch_feeds' );
        }

        // Flush rewrite rules
        flush_rewrite_rules();
    }
}
