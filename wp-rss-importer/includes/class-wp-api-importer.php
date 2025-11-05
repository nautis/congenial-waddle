<?php
/**
 * WordPress REST API Importer Class - Handles importing from WordPress REST API
 */

if ( ! defined( 'WPINC' ) ) {
    die;
}

class WP_RSS_Importer_WP_API_Importer {

    /**
     * Fetch and import from WordPress REST API
     *
     * @param int $source_id The feed source post ID
     * @return bool|WP_Error
     */
    public function import_feed( $source_id ) {
        $base_url = get_post_meta( $source_id, '_feed_url', true );
        $limit = get_post_meta( $source_id, '_feed_limit', true );
        $keyword_filter = get_post_meta( $source_id, '_keyword_filter', true );

        if ( empty( $base_url ) ) {
            return new WP_Error( 'no_feed_url', __( 'No site URL specified.', 'wp-rss-importer' ) );
        }

        // Normalize the base URL (remove trailing slash)
        $base_url = rtrim( $base_url, '/' );

        // Build the API endpoint URL
        $api_url = $base_url . '/wp-json/wp/v2/posts';

        // Add per_page parameter if limit is set
        $per_page = ! empty( $limit ) && is_numeric( $limit ) ? intval( $limit ) : 10;
        $api_url = add_query_arg( 'per_page', min( $per_page, 100 ), $api_url ); // Max 100 per WordPress API

        $imported_count = 0;
        $page = 1;
        $total_to_import = ! empty( $limit ) && is_numeric( $limit ) ? intval( $limit ) : 0;

        // Fetch posts (with pagination if needed)
        while ( true ) {
            $paged_url = add_query_arg( 'page', $page, $api_url );

            $response = wp_remote_get( $paged_url, array(
                'timeout' => 30,
                'headers' => array(
                    'Accept' => 'application/json',
                ),
            ) );

            if ( is_wp_error( $response ) ) {
                update_post_meta( $source_id, '_last_error', $response->get_error_message() );
                update_post_meta( $source_id, '_last_fetch', current_time( 'mysql' ) );
                return $response;
            }

            $response_code = wp_remote_retrieve_response_code( $response );

            if ( $response_code !== 200 ) {
                $error_message = sprintf(
                    __( 'API returned status code %d. Please verify the site URL is correct.', 'wp-rss-importer' ),
                    $response_code
                );
                update_post_meta( $source_id, '_last_error', $error_message );
                update_post_meta( $source_id, '_last_fetch', current_time( 'mysql' ) );
                return new WP_Error( 'api_error', $error_message );
            }

            $body = wp_remote_retrieve_body( $response );
            $posts = json_decode( $body, true );

            if ( ! is_array( $posts ) || empty( $posts ) ) {
                break; // No more posts
            }

            foreach ( $posts as $post_data ) {
                // Check if we've reached the limit
                if ( $total_to_import > 0 && $imported_count >= $total_to_import ) {
                    break 2; // Break out of both loops
                }

                // Apply keyword filter if set
                if ( ! empty( $keyword_filter ) ) {
                    $title = isset( $post_data['title']['rendered'] ) ? $post_data['title']['rendered'] : '';
                    $content = isset( $post_data['content']['rendered'] ) ? $post_data['content']['rendered'] : '';
                    $excerpt = isset( $post_data['excerpt']['rendered'] ) ? $post_data['excerpt']['rendered'] : '';

                    if ( stripos( $title, $keyword_filter ) === false &&
                         stripos( $content, $keyword_filter ) === false &&
                         stripos( $excerpt, $keyword_filter ) === false ) {
                        continue;
                    }
                }

                // Check if item already exists
                $permalink = isset( $post_data['link'] ) ? $post_data['link'] : '';
                if ( empty( $permalink ) || $this->item_exists( $permalink ) ) {
                    continue;
                }

                // Import the item
                if ( $this->create_feed_item( $post_data, $source_id, $base_url ) ) {
                    $imported_count++;
                }
            }

            // Check if we should continue to next page
            if ( count( $posts ) < $per_page ) {
                break; // No more pages
            }

            $page++;
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
     * Create a feed item post from WordPress API data
     *
     * @param array $post_data The post data from WordPress API
     * @param int $source_id The feed source ID
     * @param string $base_url The base URL of the WordPress site
     * @return int|bool Post ID on success, false on failure
     */
    private function create_feed_item( $post_data, $source_id, $base_url ) {
        // Extract post data
        $title = isset( $post_data['title']['rendered'] ) ? $post_data['title']['rendered'] : '';
        $content = isset( $post_data['content']['rendered'] ) ? $post_data['content']['rendered'] : '';
        $excerpt_data = isset( $post_data['excerpt']['rendered'] ) ? $post_data['excerpt']['rendered'] : '';
        $permalink = isset( $post_data['link'] ) ? $post_data['link'] : '';
        $date = isset( $post_data['date'] ) ? $post_data['date'] : current_time( 'mysql' );

        // If content is empty, try to use excerpt or generate from Yoast data
        if ( empty( $content ) && empty( $excerpt_data ) ) {
            // Try to get description from Yoast meta
            if ( isset( $post_data['yoast_head_json']['description'] ) ) {
                $excerpt_data = $post_data['yoast_head_json']['description'];
            } elseif ( isset( $post_data['yoast_head_json']['og_description'] ) ) {
                $excerpt_data = $post_data['yoast_head_json']['og_description'];
            }
        }

        // Generate excerpt from content or use provided excerpt
        if ( ! empty( $content ) ) {
            $excerpt = $this->get_excerpt( $content, 250 );
        } elseif ( ! empty( $excerpt_data ) ) {
            $excerpt = $this->get_excerpt( $excerpt_data, 250 );
        } else {
            $excerpt = '';
        }

        // Get author info
        $author_name = '';
        if ( isset( $post_data['yoast_head_json']['author'] ) ) {
            $author_name = $post_data['yoast_head_json']['author'];
        }

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
        $post_data_insert = array(
            'post_type'     => 'post',
            'post_title'    => sanitize_text_field( wp_strip_all_tags( $title ) ),
            'post_content'  => wp_kses_post( $content ),
            'post_excerpt'  => sanitize_text_field( wp_strip_all_tags( $excerpt ) ),
            'post_status'   => 'publish',
            'post_date'     => $date,
            'post_category' => array( $news_category_id ),
        );

        $post_id = wp_insert_post( $post_data_insert );

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
        $featured_media_id = isset( $post_data['featured_media'] ) ? intval( $post_data['featured_media'] ) : 0;
        if ( $featured_media_id > 0 ) {
            $image_url = $this->get_featured_image_url( $featured_media_id, $base_url );
            if ( $image_url ) {
                $this->set_featured_image( $post_id, $image_url );
            }
        } else {
            // Try to get image from Yoast meta or content
            $image_url = $this->get_image_from_meta( $post_data, $content );
            if ( $image_url ) {
                $this->set_featured_image( $post_id, $image_url );
            }
        }

        return $post_id;
    }

    /**
     * Get featured image URL from WordPress API
     *
     * @param int $media_id The media ID
     * @param string $base_url The base URL of the WordPress site
     * @return string|bool Image URL or false
     */
    private function get_featured_image_url( $media_id, $base_url ) {
        $base_url = rtrim( $base_url, '/' );
        $media_url = $base_url . '/wp-json/wp/v2/media/' . $media_id;

        $response = wp_remote_get( $media_url, array(
            'timeout' => 15,
            'headers' => array(
                'Accept' => 'application/json',
            ),
        ) );

        if ( is_wp_error( $response ) || wp_remote_retrieve_response_code( $response ) !== 200 ) {
            return false;
        }

        $body = wp_remote_retrieve_body( $response );
        $media_data = json_decode( $body, true );

        // Try to get the largest available image
        if ( isset( $media_data['media_details']['sizes']['full']['source_url'] ) ) {
            return $media_data['media_details']['sizes']['full']['source_url'];
        } elseif ( isset( $media_data['source_url'] ) ) {
            return $media_data['source_url'];
        } elseif ( isset( $media_data['guid']['rendered'] ) ) {
            return $media_data['guid']['rendered'];
        }

        return false;
    }

    /**
     * Get image from post meta or content
     *
     * @param array $post_data The post data
     * @param string $content The post content
     * @return string|bool Image URL or false
     */
    private function get_image_from_meta( $post_data, $content ) {
        // Try Yoast SEO og:image
        if ( isset( $post_data['yoast_head_json']['og_image'][0]['url'] ) ) {
            return $post_data['yoast_head_json']['og_image'][0]['url'];
        }

        // Try to extract first image from content
        if ( ! empty( $content ) ) {
            preg_match( '/<img[^>]+src=["\']([^"\']+)["\'][^>]*>/i', $content, $matches );
            if ( ! empty( $matches[1] ) ) {
                return $matches[1];
            }
        }

        return false;
    }

    /**
     * Get excerpt from content
     *
     * @param string $content The full content
     * @param int $length Maximum character length
     * @return string
     */
    private function get_excerpt( $content, $length = 250 ) {
        $content = wp_strip_all_tags( $content );
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

        // If wp_check_filetype didn't detect the type, fallback to checking extension manually
        if ( empty( $wp_filetype['type'] ) ) {
            // Get MIME type directly from file
            $finfo = finfo_open( FILEINFO_MIME_TYPE );
            $mime_type = finfo_file( $finfo, $temp_file );
            finfo_close( $finfo );
            $wp_filetype['type'] = $mime_type;
        }

        // Log for debugging
        error_log( sprintf(
            'WP RSS Importer: Image %s - filename: %s, detected type: %s',
            $image_url,
            $file_name,
            $wp_filetype['type']
        ) );

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
