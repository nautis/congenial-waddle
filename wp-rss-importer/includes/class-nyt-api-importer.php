<?php
/**
 * NY Times API Importer Class - Handles importing from NY Times Article Search API
 */

if ( ! defined( 'WPINC' ) ) {
    die;
}

class WP_RSS_Importer_NYT_API_Importer {

    /**
     * NY Times API base URL
     */
    const API_BASE_URL = 'https://api.nytimes.com/svc/search/v2/articlesearch.json';

    /**
     * Fetch and import from NY Times API
     *
     * @param int $source_id The feed source post ID
     * @return bool|WP_Error
     */
    public function import_feed( $source_id ) {
        $api_key = get_post_meta( $source_id, '_nyt_api_key', true );
        $search_query = get_post_meta( $source_id, '_nyt_search_query', true );
        $limit = get_post_meta( $source_id, '_feed_limit', true );
        $keyword_filter = get_post_meta( $source_id, '_keyword_filter', true );

        if ( empty( $api_key ) ) {
            return new WP_Error( 'no_api_key', __( 'No NY Times API key specified.', 'wp-rss-importer' ) );
        }

        if ( empty( $search_query ) ) {
            $search_query = 'timepiece OR horology OR "luxury watches" OR "mechanical watch"'; // Default search
        }

        // Build API request URL
        $api_url = add_query_arg( array(
            'q'       => urlencode( $search_query ),
            'api-key' => $api_key,
            'sort'    => 'newest',
            'page'    => 0,
        ), self::API_BASE_URL );

        $imported_count = 0;
        $max_items = ! empty( $limit ) && is_numeric( $limit ) ? intval( $limit ) : 10;

        // Fetch articles
        $response = wp_remote_get( $api_url, array(
            'timeout' => 30,
        ) );

        if ( is_wp_error( $response ) ) {
            update_post_meta( $source_id, '_last_error', $response->get_error_message() );
            update_post_meta( $source_id, '_last_fetch', current_time( 'mysql' ) );
            return $response;
        }

        $response_code = wp_remote_retrieve_response_code( $response );

        if ( $response_code !== 200 ) {
            $error_message = sprintf(
                __( 'NY Times API returned status code %d. Please verify your API key is valid.', 'wp-rss-importer' ),
                $response_code
            );
            update_post_meta( $source_id, '_last_error', $error_message );
            update_post_meta( $source_id, '_last_fetch', current_time( 'mysql' ) );
            return new WP_Error( 'api_error', $error_message );
        }

        $body = wp_remote_retrieve_body( $response );
        $data = json_decode( $body, true );

        if ( ! isset( $data['response']['docs'] ) || ! is_array( $data['response']['docs'] ) ) {
            $error_message = __( 'Invalid response from NY Times API.', 'wp-rss-importer' );
            update_post_meta( $source_id, '_last_error', $error_message );
            update_post_meta( $source_id, '_last_fetch', current_time( 'mysql' ) );
            return new WP_Error( 'api_error', $error_message );
        }

        $articles = $data['response']['docs'];

        foreach ( $articles as $article ) {
            // Check if we've reached the limit
            if ( $imported_count >= $max_items ) {
                break;
            }

            // Apply keyword filter if set
            if ( ! empty( $keyword_filter ) ) {
                $headline = isset( $article['headline']['main'] ) ? $article['headline']['main'] : '';
                $abstract = isset( $article['abstract'] ) ? $article['abstract'] : '';
                $snippet = isset( $article['snippet'] ) ? $article['snippet'] : '';

                if ( stripos( $headline, $keyword_filter ) === false &&
                     stripos( $abstract, $keyword_filter ) === false &&
                     stripos( $snippet, $keyword_filter ) === false ) {
                    continue;
                }
            }

            // Check if item already exists
            $web_url = isset( $article['web_url'] ) ? $article['web_url'] : '';
            if ( empty( $web_url ) || $this->item_exists( $web_url ) ) {
                continue;
            }

            // Import the item
            if ( $this->create_feed_item( $article, $source_id ) ) {
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
     * Create a feed item post from NY Times API data
     *
     * @param array $article The article data from NY Times API
     * @param int $source_id The feed source ID
     * @return int|bool Post ID on success, false on failure
     */
    private function create_feed_item( $article, $source_id ) {
        // Extract article data
        $headline = isset( $article['headline']['main'] ) ? $article['headline']['main'] : '';
        $abstract = isset( $article['abstract'] ) ? $article['abstract'] : '';
        $lead_paragraph = isset( $article['lead_paragraph'] ) ? $article['lead_paragraph'] : '';
        $snippet = isset( $article['snippet'] ) ? $article['snippet'] : '';
        $web_url = isset( $article['web_url'] ) ? $article['web_url'] : '';
        $pub_date = isset( $article['pub_date'] ) ? $article['pub_date'] : current_time( 'mysql' );

        // Generate content from available text
        $content = '';
        if ( ! empty( $lead_paragraph ) ) {
            $content = '<p>' . $lead_paragraph . '</p>';
        } elseif ( ! empty( $abstract ) ) {
            $content = '<p>' . $abstract . '</p>';
        } elseif ( ! empty( $snippet ) ) {
            $content = '<p>' . $snippet . '</p>';
        }

        // Use abstract or snippet for excerpt
        $excerpt = ! empty( $abstract ) ? $abstract : $snippet;
        $excerpt = $this->get_excerpt( $excerpt, 250 );

        // Get author from byline
        $author_name = '';
        if ( isset( $article['byline']['original'] ) ) {
            $author_name = $article['byline']['original'];
        } elseif ( isset( $article['byline']['person'][0]['firstname'] ) && isset( $article['byline']['person'][0]['lastname'] ) ) {
            $author_name = $article['byline']['person'][0]['firstname'] . ' ' . $article['byline']['person'][0]['lastname'];
        }

        // Convert pub_date to WordPress format
        try {
            $date_obj = new DateTime( $pub_date );
            $wp_date = $date_obj->format( 'Y-m-d H:i:s' );
        } catch ( Exception $e ) {
            $wp_date = current_time( 'mysql' );
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

        // Create the post
        $post_data = array(
            'post_type'     => 'post',
            'post_title'    => sanitize_text_field( wp_strip_all_tags( $headline ) ),
            'post_content'  => wp_kses_post( $content ),
            'post_excerpt'  => sanitize_text_field( wp_strip_all_tags( $excerpt ) ),
            'post_status'   => 'publish',
            'post_date'     => $wp_date,
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
        update_post_meta( $post_id, '_source_permalink', esc_url_raw( $web_url ) );
        update_post_meta( $post_id, '_source_author', sanitize_text_field( $author_name ) );
        update_post_meta( $post_id, '_source_id', $source_id );

        // Get and save featured image from multimedia array
        $image_url = $this->get_featured_image( $article );
        if ( $image_url ) {
            $this->set_featured_image( $post_id, $image_url );
        }

        return $post_id;
    }

    /**
     * Get featured image URL from NY Times article multimedia
     *
     * @param array $article The article data
     * @return string|bool Image URL or false
     */
    private function get_featured_image( $article ) {
        if ( ! isset( $article['multimedia'] ) || ! is_array( $article['multimedia'] ) ) {
            return false;
        }

        // Look for the largest/best quality image
        foreach ( $article['multimedia'] as $media ) {
            if ( isset( $media['url'] ) && ! empty( $media['url'] ) ) {
                $url = $media['url'];

                // Check if URL is already absolute (starts with http:// or https://)
                if ( strpos( $url, 'http://' ) === 0 || strpos( $url, 'https://' ) === 0 ) {
                    return $url;
                }

                // Otherwise, construct full URL - NY Times multimedia URLs may be relative
                $image_url = 'https://www.nytimes.com/' . ltrim( $url, '/' );
                return $image_url;
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

        // Get file info and remove query parameters BEFORE sanitizing
        $file_info = pathinfo( $image_url );
        $file_name = $file_info['basename'];

        // Remove query parameters from filename (e.g., ?v=123) BEFORE sanitizing
        $file_name = preg_replace( '/\?.*$/', '', $file_name );

        // Now sanitize the cleaned filename
        $file_name = sanitize_file_name( $file_name );

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
