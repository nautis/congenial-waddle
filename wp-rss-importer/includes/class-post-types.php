<?php
/**
 * Register custom post types and taxonomies
 */

if ( ! defined( 'WPINC' ) ) {
    die;
}

class WP_RSS_Importer_Post_Types {

    /**
     * Register custom post types
     */
    public function register_post_types() {
        $this->register_feed_source_post_type();
    }

    /**
     * Register Feed Source custom post type
     */
    private function register_feed_source_post_type() {
        $labels = array(
            'name'                  => _x( 'Feed Sources', 'Post type general name', 'wp-rss-importer' ),
            'singular_name'         => _x( 'Feed Source', 'Post type singular name', 'wp-rss-importer' ),
            'menu_name'             => _x( 'RSS Importer', 'Admin Menu text', 'wp-rss-importer' ),
            'name_admin_bar'        => _x( 'Feed Source', 'Add New on Toolbar', 'wp-rss-importer' ),
            'add_new'               => __( 'Add New', 'wp-rss-importer' ),
            'add_new_item'          => __( 'Add New Feed Source', 'wp-rss-importer' ),
            'new_item'              => __( 'New Feed Source', 'wp-rss-importer' ),
            'edit_item'             => __( 'Edit Feed Source', 'wp-rss-importer' ),
            'view_item'             => __( 'View Feed Source', 'wp-rss-importer' ),
            'all_items'             => __( 'Feed Sources', 'wp-rss-importer' ),
            'search_items'          => __( 'Search Feed Sources', 'wp-rss-importer' ),
            'parent_item_colon'     => __( 'Parent Feed Sources:', 'wp-rss-importer' ),
            'not_found'             => __( 'No feed sources found.', 'wp-rss-importer' ),
            'not_found_in_trash'    => __( 'No feed sources found in Trash.', 'wp-rss-importer' ),
        );

        $args = array(
            'labels'                => $labels,
            'public'                => false,
            'publicly_queryable'    => false,
            'show_ui'               => true,
            'show_in_menu'          => true,
            'query_var'             => true,
            'rewrite'               => array( 'slug' => 'feed-source' ),
            'capability_type'       => 'post',
            'has_archive'           => false,
            'hierarchical'          => false,
            'menu_position'         => 25,
            'menu_icon'             => 'dashicons-rss',
            'supports'              => array( 'title' ),
            'show_in_rest'          => false,
        );

        register_post_type( 'feed_source', $args );
    }

    /**
     * Register taxonomies
     */
    public function register_taxonomies() {
        // Register category taxonomy for feed sources
        $labels = array(
            'name'              => _x( 'Feed Categories', 'taxonomy general name', 'wp-rss-importer' ),
            'singular_name'     => _x( 'Feed Category', 'taxonomy singular name', 'wp-rss-importer' ),
            'search_items'      => __( 'Search Feed Categories', 'wp-rss-importer' ),
            'all_items'         => __( 'All Feed Categories', 'wp-rss-importer' ),
            'parent_item'       => __( 'Parent Feed Category', 'wp-rss-importer' ),
            'parent_item_colon' => __( 'Parent Feed Category:', 'wp-rss-importer' ),
            'edit_item'         => __( 'Edit Feed Category', 'wp-rss-importer' ),
            'update_item'       => __( 'Update Feed Category', 'wp-rss-importer' ),
            'add_new_item'      => __( 'Add New Feed Category', 'wp-rss-importer' ),
            'new_item_name'     => __( 'New Feed Category Name', 'wp-rss-importer' ),
            'menu_name'         => __( 'Categories', 'wp-rss-importer' ),
        );

        $args = array(
            'hierarchical'      => true,
            'labels'            => $labels,
            'show_ui'           => true,
            'show_admin_column' => true,
            'query_var'         => true,
            'rewrite'           => array( 'slug' => 'feed-category' ),
            'show_in_rest'      => true,
        );

        register_taxonomy( 'feed_category', array( 'feed_source' ), $args );
    }
}
