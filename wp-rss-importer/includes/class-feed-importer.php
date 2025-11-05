<?php
/**
 * Feed Importer Class - Handles RSS/Atom feed parsing and import
 */

if ( ! defined( 'WPINC' ) ) {
    die;
}

class WP_RSS_Importer_Feed_Importer {

    /**
     * Fetch and import a single feed source
     *
     * @param int $source_id The feed source post ID
     * @return bool|WP_Error
     */
    public function import_feed( $source_id ) {
        // Check feed type and route to appropriate importer
        $feed_type = get_post_meta( $source_id, '_feed_type', true );

        // Default to RSS for backward compatibility
        if ( empty( $feed_type ) ) {
            $feed_type = 'rss';
        }

        // Route to appropriate importer
        if ( $feed_type === 'wordpress_api' ) {
            require_once WP_RSS_IMPORTER_PLUGIN_DIR . 'includes/class-wp-api-importer.php';
            $importer = new WP_RSS_Importer_WP_API_Importer();
            return $importer->import_feed( $source_id );
        }

        // Default: RSS/Atom import
        return $this->import_rss_feed( $source_id );
    }

    /**
     * Import RSS/Atom feed
     *
     * @param int $source_id The feed source post ID
     * @return bool|WP_Error
     */
    private function import_rss_feed( $source_id ) {
        $feed_url = get_post_meta( $source_id, '_feed_url', true );
        $limit = get_post_meta( $source_id, '_feed_limit', true );
        $keyword_filter = get_post_meta( $source_id, '_keyword_filter', true );

        if ( empty( $feed_url ) ) {
            return new WP_Error( 'no_feed_url', __( 'No feed URL specified.', 'wp-rss-importer' ) );
        }

        // Fetch the feed
        $feed = fetch_feed( $feed_url );

        if ( is_wp_error( $feed ) ) {
            update_post_meta( $source_id, '_last_error', $feed->get_error_message() );
            update_post_meta( $source_id, '_last_fetch', current_time( 'mysql' ) );
            return $feed;
        }

        // Get feed items
        $max_items = ! empty( $limit ) && is_numeric( $limit ) ? intval( $limit ) : 0;
        $items = $feed->get_items( 0, $max_items );

        $imported_count = 0;

        foreach ( $items as $item ) {
            // Apply keyword filter if set
            if ( ! empty( $keyword_filter ) ) {
                $title = $item->get_title();
                $content = $item->get_content();

                if ( stripos( $title, $keyword_filter ) === false &&
                     stripos( $content, $keyword_filter ) === false ) {
                    continue;
                }
            }

            // Check if item already exists
            if ( $this->item_exists( $item->get_permalink() ) ) {
                continue;
            }

            // Import the item
            if ( $this->create_feed_item( $item, $source_id ) ) {
                $imported_count++;
            }
        }

        // Update source meta
        update_post_meta( $source_id, '_last_fetch', current_time( 'mysql' ) );
        update_post_meta( $source_id, '_last_error', '' );
        update_post_meta( $source_id, '_last_import_count', $imported_count );

        return true;
    }

    /**
     * Check if a feed item already exists
     *
     * @param string $permalink The item's permalink
     * @return bool
     */
    private function item_exists( $permalink ) {
        $args = array(
            'post_type'      => 'post',
            'post_status'    => 'any',
            'posts_per_page' => 1,
            'meta_query'     => array(
                array(
                    'key'     => '_source_permalink',
                    'value'   => $permalink,
                    'compare' => '='
                )
            ),
            'fields' => 'ids'
        );

        $query = new WP_Query( $args );
        return $query->have_posts();
    }

    /**
     * Create a feed item post
     *
     * @param SimplePie_Item $item The feed item
     * @param int $source_id The feed source ID
     * @return int|bool Post ID on success, false on failure
     */
    private function create_feed_item( $item, $source_id ) {
        $title = $item->get_title();
        $content = $item->get_content();
        $excerpt = $this->get_excerpt( $content, 250 );
        $permalink = $item->get_permalink();
        $author = $item->get_author();
        $author_name = $author ? $author->get_name() : '';
        $date = $item->get_date( 'Y-m-d H:i:s' );

        // Get or create the "News" category
        $news_category = get_term_by( 'slug', 'news', 'category' );
        if ( ! $news_category ) {
            $news_category_id = wp_insert_term( 'News', 'category', array( 'slug' => 'news' ) );
            if ( is_wp_error( $news_category_id ) ) {
                $news_category_id = 0;
            } else {
                $news_category_id = $news_category_id['term_id'];
            }
        } else {
            $news_category_id = $news_category->term_id;
        }

        // Create the post as regular WordPress post
        $post_data = array(
            'post_type'     => 'post',
            'post_title'    => sanitize_text_field( $title ),
            'post_content'  => wp_kses_post( $content ),
            'post_excerpt'  => sanitize_text_field( $excerpt ),
            'post_status'   => 'publish',
            'post_date'     => $date ? $date : current_time( 'mysql' ),
            'post_category' => array( $news_category_id ),
        );

        $post_id = wp_insert_post( $post_data );

        if ( is_wp_error( $post_id ) || ! $post_id ) {
            return false;
        }

        // Get feed source name for tagging
        $source_post = get_post( $source_id );
        if ( $source_post ) {
            $source_name = $source_post->post_title;
            // Create or assign tag based on feed source name
            wp_set_post_tags( $post_id, $source_name, true );
        }

        // Save metadata
        update_post_meta( $post_id, '_source_permalink', esc_url_raw( $permalink ) );
        update_post_meta( $post_id, '_source_author', sanitize_text_field( $author_name ) );
        update_post_meta( $post_id, '_source_id', $source_id );

        // Get and save featured image
        $image_url = $this->get_featured_image( $item );
        if ( $image_url ) {
            $this->set_featured_image( $post_id, $image_url );
        }

        return $post_id;
    }

    /**
     * Get excerpt from content
     *
     * @param string $content The full content
     * @param int $length Maximum character length
     * @return string
     */
    private function get_excerpt( $content, $length = 250 ) {
        $content = strip_tags( $content );
        $content = strip_shortcodes( $content );

        if ( strlen( $content ) <= $length ) {
            return $content;
        }

        $content = substr( $content, 0, $length );
        $last_space = strrpos( $content, ' ' );

        if ( $last_space !== false ) {
            $content = substr( $content, 0, $last_space );
        }

        return $content . '...';
    }

    /**
     * Get featured image URL from feed item
     *
     * @param SimplePie_Item $item The feed item
     * @return string|bool Image URL or false
     */
    private function get_featured_image( $item ) {
        // Method 1: Try media:thumbnail namespace (common in RSS feeds)
        $namespaces = $item->get_item_tags( 'http://search.yahoo.com/mrss/', 'thumbnail' );
        if ( ! empty( $namespaces[0]['attribs']['']['url'] ) ) {
            return $namespaces[0]['attribs']['']['url'];
        }

        // Method 2: Try media:content namespace
        $namespaces = $item->get_item_tags( 'http://search.yahoo.com/mrss/', 'content' );
        if ( ! empty( $namespaces[0]['attribs']['']['url'] ) ) {
            $type = isset( $namespaces[0]['attribs']['']['type'] ) ? $namespaces[0]['attribs']['']['type'] : '';
            if ( empty( $type ) || strpos( $type, 'image/' ) === 0 ) {
                return $namespaces[0]['attribs']['']['url'];
            }
        }

        // Method 3: Try enclosure (media attachment)
        $enclosure = $item->get_enclosure();
        if ( $enclosure ) {
            $link = $enclosure->get_link();
            $type = $enclosure->get_type();

            // Accept if it's an image type or if no type specified but URL looks like image
            if ( ( $type && strpos( $type, 'image/' ) === 0 ) ||
                 ( ! $type && preg_match( '/\.(jpg|jpeg|png|gif|webp)(\?|$)/i', $link ) ) ) {
                return $link;
            }
        }

        // Method 4: Try to get image from content
        $content = $item->get_content();
        if ( $content ) {
            preg_match( '/<img[^>]+src=["\']([^"\']+)["\'][^>]*>/i', $content, $matches );
            if ( ! empty( $matches[1] ) ) {
                return $matches[1];
            }
        }

        // Method 5: Try to get image from description (some feeds only have images here)
        $description = $item->get_description();
        if ( $description && $description !== $content ) {
            preg_match( '/<img[^>]+src=["\']([^"\']+)["\'][^>]*>/i', $description, $matches );
            if ( ! empty( $matches[1] ) ) {
                return $matches[1];
            }
        }

        // Method 6: Try Atom-specific summary with images
        $summary = $item->get_item_tags( 'http://www.w3.org/2005/Atom', 'summary' );
        if ( ! empty( $summary[0]['data'] ) ) {
            preg_match( '/<img[^>]+src=["\']([^"\']+)["\'][^>]*>/i', $summary[0]['data'], $matches );
            if ( ! empty( $matches[1] ) ) {
                return $matches[1];
            }
        }

        return false;
    }

    /**
     * Set featured image from URL
     *
     * @param int $post_id The post ID
     * @param string $image_url The image URL
     * @return bool
     */
    private function set_featured_image( $post_id, $image_url ) {
        require_once( ABSPATH . 'wp-admin/includes/media.php' );
        require_once( ABSPATH . 'wp-admin/includes/file.php' );
        require_once( ABSPATH . 'wp-admin/includes/image.php' );

        // Download image to temp file
        $temp_file = download_url( $image_url );

        if ( is_wp_error( $temp_file ) ) {
            // Log the download error for debugging
            error_log( sprintf(
                'WP RSS Importer: Failed to download image %s for post %d. Error: %s',
                $image_url,
                $post_id,
                $temp_file->get_error_message()
            ) );
            return false;
        }

        // Get file info
        $file_info = pathinfo( $image_url );
        $file_name = sanitize_file_name( $file_info['basename'] );

        // Remove query parameters from filename (e.g., ?v=123)
        $file_name = preg_replace( '/\?.*$/', '', $file_name );

        // Use WordPress's file type checking (more reliable than mime_content_type)
        $wp_filetype = wp_check_filetype( $file_name, null );

        // Prepare file array
        $file = array(
            'name'     => $file_name,
            'type'     => $wp_filetype['type'],
            'tmp_name' => $temp_file,
            'error'    => 0,
            'size'     => filesize( $temp_file ),
        );

        // Upload file to media library
        $attachment_id = media_handle_sideload( $file, $post_id );

        if ( is_wp_error( $attachment_id ) ) {
            @unlink( $temp_file );
            // Log the sideload error for debugging
            error_log( sprintf(
                'WP RSS Importer: Failed to sideload image %s for post %d. Error: %s',
                $image_url,
                $post_id,
                $attachment_id->get_error_message()
            ) );
            return false;
        }

        // Fix file permissions to ensure WordPress can modify the file
        $uploaded_file_path = get_attached_file( $attachment_id );
        if ( $uploaded_file_path && file_exists( $uploaded_file_path ) ) {
            // Set file permissions to 0644 (readable by all, writable by owner)
            @chmod( $uploaded_file_path, 0644 );

            // Also fix permissions on the directory if needed
            $upload_dir = dirname( $uploaded_file_path );
            if ( is_dir( $upload_dir ) ) {
                @chmod( $upload_dir, 0755 );
            }
        }

        // Set as featured image
        set_post_thumbnail( $post_id, $attachment_id );

        return true;
    }
}
