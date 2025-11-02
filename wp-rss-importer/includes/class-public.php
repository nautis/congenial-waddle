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
        add_filter( 'the_content', array( $this, 'add_source_attribution' ), 10, 1 );
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
                $source_name = esc_html( $source_post->post_title );
                $source_author = get_post_meta( $post_id, '_source_author', true );

                // Build the title with source prefix
                $modified_title = '<span class="rss-title" data-source="' . esc_attr( $source_name ) . '">';
                $modified_title .= '<span class="rss-source-prefix">' . $source_name . ':</span> ';
                $modified_title .= $title;

                // Add author if exists
                if ( ! empty( $source_author ) ) {
                    $modified_title .= ' <span class="rss-author">by ' . esc_html( $source_author ) . '</span>';
                }

                // Add external link icon
                $modified_title .= ' &#8599;';
                $modified_title .= '</span>';

                return $modified_title;
            }
        }

        return $title;
    }

    /**
     * Add source attribution box to RSS imported posts
     *
     * @param string $content Post content
     * @return string Modified content with attribution
     */
    public function add_source_attribution( $content ) {
        // Skip in admin area
        if ( is_admin() ) {
            return $content;
        }

        // Only show on singular post views
        if ( ! is_singular() ) {
            return $content;
        }

        // Get the current post ID
        $post_id = get_the_ID();
        if ( ! $post_id ) {
            return $content;
        }

        // Check if this is an RSS imported post
        $source_id = get_post_meta( $post_id, '_source_id', true );
        if ( ! $source_id ) {
            return $content;
        }

        // Get source information
        $source_post = get_post( $source_id );
        if ( ! $source_post ) {
            return $content;
        }

        $source_name = esc_html( $source_post->post_title );
        $source_author = get_post_meta( $post_id, '_source_author', true );
        $source_permalink = get_post_meta( $post_id, '_source_permalink', true );

        // Build attribution HTML
        $attribution = '<div class="rss-source-attribution">';

        if ( $source_author ) {
            $attribution .= '<strong>Author:</strong> ' . esc_html( $source_author ) . ' | ';
        }

        $attribution .= '<strong>Source:</strong> ' . $source_name;

        if ( $source_permalink ) {
            $attribution .= ' | <a href="' . esc_url( $source_permalink ) . '" target="_blank" rel="noopener noreferrer">View Original Article &#8599;</a>';
        }

        $attribution .= '</div>';

        // Append attribution to content
        return $content . $attribution;
    }
}
