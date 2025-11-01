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
}
