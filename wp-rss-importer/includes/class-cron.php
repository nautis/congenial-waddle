<?php
/**
 * Cron Job Handler
 */

if ( ! defined( 'WPINC' ) ) {
    die;
}

class WP_RSS_Importer_Cron {

    /**
     * Fetch all active feed sources
     */
    public function fetch_all_feeds() {
        // Get all published feed sources
        $sources = get_posts( array(
            'post_type'      => 'feed_source',
            'posts_per_page' => -1,
            'post_status'    => 'publish',
        ) );

        if ( empty( $sources ) ) {
            return;
        }

        $importer = new WP_RSS_Importer_Feed_Importer();

        foreach ( $sources as $source ) {
            // Import the feed
            $result = $importer->import_feed( $source->ID );

            // Log errors if any
            if ( is_wp_error( $result ) ) {
                error_log( sprintf(
                    'WP RSS Importer: Failed to fetch feed "%s" (ID: %d). Error: %s',
                    $source->post_title,
                    $source->ID,
                    $result->get_error_message()
                ) );
            }
        }
    }
}
