<?php
/**
 * Public-facing functionality
 */

if ( ! defined( 'WPINC' ) ) {
    die;
}

class WP_RSS_Importer_Public {

    /**
     * The ID of this plugin
     */
    private $plugin_name;

    /**
     * The version of this plugin
     */
    private $version;

    /**
     * Initialize the class
     */
    public function __construct( $plugin_name, $version ) {
        $this->plugin_name = $plugin_name;
        $this->version = $version;

        // Add hooks for source attribution
        add_filter( 'post_class', array( $this, 'add_rss_import_class' ), 10, 3 );
        add_filter( 'the_title', array( $this, 'add_source_data_attribute' ), 10, 2 );
    }

    /**
     * Register the stylesheets for the public-facing side
     */
    public function enqueue_styles() {
        wp_enqueue_style(
            $this->plugin_name,
            WP_RSS_IMPORTER_PLUGIN_URL . 'public/css/public.css',
            array(),
            $this->version,
            'all'
        );
    }

    /**
     * Register the JavaScript for the public-facing side
     */
    public function enqueue_scripts() {
        // Add any public scripts here if needed in the future
    }

    /**
     * Add RSS import class and data attribute to posts
     *
     * @param array $classes Post classes
     * @param string $class Additional classes
     * @param int $post_id Post ID
     * @return array Modified classes
     */
    public function add_rss_import_class( $classes, $class, $post_id ) {
        $source_id = get_post_meta( $post_id, '_source_id', true );

        if ( $source_id ) {
            $classes[] = 'rss-imported-post';

            // Get the feed source name
            $source_post = get_post( $source_id );
            if ( $source_post ) {
                $source_name = sanitize_html_class( $source_post->post_title );
                $classes[] = 'rss-source-' . $source_name;
            }
        }

        return $classes;
    }

    /**
     * Add data attribute with source name to post titles
     *
     * @param string $title Post title
     * @param int $post_id Post ID
     * @return string Modified title
     */
    public function add_source_data_attribute( $title, $post_id ) {
        // Skip if we don't have a valid post ID
        if ( ! $post_id ) {
            return $title;
        }

        // Skip in admin area
        if ( is_admin() ) {
            return $title;
        }

        $source_id = get_post_meta( $post_id, '_source_id', true );

        if ( $source_id ) {
            $source_post = get_post( $source_id );
            if ( $source_post ) {
                $source_name = esc_attr( $source_post->post_title );
                return '<span class="rss-title" data-source="' . $source_name . '">' . $title . '</span>';
            }
        }

        return $title;
    }
}
