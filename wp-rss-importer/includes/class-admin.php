<?php
/**
 * Admin-specific functionality
 */

if ( ! defined( 'WPINC' ) ) {
    die;
}

class WP_RSS_Importer_Admin {

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
     * Register the stylesheets for the admin area
     */
    public function enqueue_styles() {
        wp_enqueue_style(
            $this->plugin_name,
            WP_RSS_IMPORTER_PLUGIN_URL . 'admin/css/admin.css',
            array(),
            $this->version,
            'all'
        );
    }

    /**
     * Register the JavaScript for the admin area
     */
    public function enqueue_scripts() {
        wp_enqueue_script(
            $this->plugin_name,
            WP_RSS_IMPORTER_PLUGIN_URL . 'admin/js/admin.js',
            array( 'jquery' ),
            $this->version,
            false
        );
    }

    /**
     * Add admin menu items
     */
    public function add_admin_menu() {
        // Settings page under Feed Sources
        add_submenu_page(
            'edit.php?post_type=feed_source',
            __( 'Settings', 'wp-rss-importer' ),
            __( 'Settings', 'wp-rss-importer' ),
            'manage_options',
            'wp-rss-importer-settings',
            array( $this, 'render_settings_page' )
        );

        // Manual Fetch page
        add_submenu_page(
            'edit.php?post_type=feed_source',
            __( 'Fetch Feeds Now', 'wp-rss-importer' ),
            __( 'Fetch Feeds Now', 'wp-rss-importer' ),
            'manage_options',
            'wp-rss-importer-fetch',
            array( $this, 'render_fetch_page' )
        );
    }

    /**
     * Add meta boxes for feed source
     */
    public function add_meta_boxes() {
        add_meta_box(
            'feed_source_details',
            __( 'Feed Source Details', 'wp-rss-importer' ),
            array( $this, 'render_feed_source_meta_box' ),
            'feed_source',
            'normal',
            'high'
        );

        add_meta_box(
            'feed_source_options',
            __( 'Import Options', 'wp-rss-importer' ),
            array( $this, 'render_feed_options_meta_box' ),
            'feed_source',
            'normal',
            'default'
        );

        add_meta_box(
            'feed_source_status',
            __( 'Feed Status', 'wp-rss-importer' ),
            array( $this, 'render_feed_status_meta_box' ),
            'feed_source',
            'side',
            'default'
        );
    }

    /**
     * Render feed source details meta box
     */
    public function render_feed_source_meta_box( $post ) {
        wp_nonce_field( 'wp_rss_importer_meta_box', 'wp_rss_importer_meta_box_nonce' );

        $feed_url = get_post_meta( $post->ID, '_feed_url', true );
        $feed_type = get_post_meta( $post->ID, '_feed_type', true );
        if ( empty( $feed_type ) ) {
            $feed_type = 'rss'; // Default to RSS for backward compatibility
        }
        ?>
        <table class="form-table">
            <tr>
                <th><label for="feed_type"><?php _e( 'Feed Type', 'wp-rss-importer' ); ?></label></th>
                <td>
                    <select id="feed_type" name="feed_type" class="regular-text">
                        <option value="rss" <?php selected( $feed_type, 'rss' ); ?>><?php _e( 'RSS/Atom Feed', 'wp-rss-importer' ); ?></option>
                        <option value="wordpress_api" <?php selected( $feed_type, 'wordpress_api' ); ?>><?php _e( 'WordPress REST API', 'wp-rss-importer' ); ?></option>
                        <option value="nytimes_api" <?php selected( $feed_type, 'nytimes_api' ); ?>><?php _e( 'NY Times API', 'wp-rss-importer' ); ?></option>
                    </select>
                    <p class="description"><?php _e( 'Select the type of feed source', 'wp-rss-importer' ); ?></p>
                </td>
            </tr>
            <tr>
                <th><label for="feed_url"><?php _e( 'Feed URL', 'wp-rss-importer' ); ?></label></th>
                <td>
                    <input type="url" id="feed_url" name="feed_url" value="<?php echo esc_attr( $feed_url ); ?>" class="large-text" required>
                    <p class="description" id="feed_url_description_rss" style="<?php echo ( $feed_type !== 'rss' ) ? 'display:none;' : ''; ?>">
                        <?php _e( 'Enter the RSS or Atom feed URL (e.g., https://example.com/feed)', 'wp-rss-importer' ); ?>
                    </p>
                    <p class="description" id="feed_url_description_wp_api" style="<?php echo ( $feed_type !== 'wordpress_api' ) ? 'display:none;' : ''; ?>">
                        <?php _e( 'Enter the WordPress site URL (e.g., https://example.com) - the plugin will automatically use /wp-json/wp/v2/posts', 'wp-rss-importer' ); ?>
                    </p>
                    <p class="description" id="feed_url_description_nyt_api" style="<?php echo ( $feed_type !== 'nytimes_api' ) ? 'display:none;' : ''; ?>">
                        <?php _e( 'Not used for NY Times API (uses search query below)', 'wp-rss-importer' ); ?>
                    </p>
                </td>
            </tr>
            <tr class="nytimes-api-field" style="<?php echo ( $feed_type !== 'nytimes_api' ) ? 'display:none;' : ''; ?>">
                <th><label for="nyt_api_key"><?php _e( 'NY Times API Key', 'wp-rss-importer' ); ?></label></th>
                <td>
                    <input type="text" id="nyt_api_key" name="nyt_api_key" value="<?php echo esc_attr( get_post_meta( $post->ID, '_nyt_api_key', true ) ); ?>" class="large-text">
                    <p class="description"><?php _e( 'Get your free API key from https://developer.nytimes.com', 'wp-rss-importer' ); ?></p>
                </td>
            </tr>
            <tr class="nytimes-api-field" style="<?php echo ( $feed_type !== 'nytimes_api' ) ? 'display:none;' : ''; ?>">
                <th><label for="nyt_search_query"><?php _e( 'Search Query', 'wp-rss-importer' ); ?></label></th>
                <td>
                    <input type="text" id="nyt_search_query" name="nyt_search_query" value="<?php echo esc_attr( get_post_meta( $post->ID, '_nyt_search_query', true ) ); ?>" class="large-text" placeholder='watch OR watches'>
                    <p class="description"><?php _e( 'Search query for NY Times articles. Use OR to combine terms. Leave empty for default: "watch OR watches"', 'wp-rss-importer' ); ?></p>
                </td>
            </tr>
            <tr class="nytimes-api-field" style="<?php echo ( $feed_type !== 'nytimes_api' ) ? 'display:none;' : ''; ?>">
                <th><label for="nyt_section"><?php _e( 'Section/Desk Filter', 'wp-rss-importer' ); ?></label></th>
                <td>
                    <input type="text" id="nyt_section" name="nyt_section" value="<?php echo esc_attr( get_post_meta( $post->ID, '_nyt_section', true ) ); ?>" class="regular-text" placeholder="">
                    <p class="description"><?php _e( 'Optional: Filter by section (e.g., "Style") or news desk (e.g., "desk:Styles"). Leave empty to search all.', 'wp-rss-importer' ); ?></p>
                </td>
            </tr>
            <tr class="nytimes-api-field" style="<?php echo ( $feed_type !== 'nytimes_api' ) ? 'display:none;' : ''; ?>">
                <th><label for="nyt_date_filter_days"><?php _e( 'Date Filter (Days)', 'wp-rss-importer' ); ?></label></th>
                <td>
                    <input type="number" id="nyt_date_filter_days" name="nyt_date_filter_days" value="<?php echo esc_attr( get_post_meta( $post->ID, '_nyt_date_filter_days', true ) ); ?>" class="small-text" placeholder="90" min="1">
                    <p class="description"><?php _e( 'Optional: Only import articles from the last N days (e.g., 90 for last 3 months). Leave empty for all dates.', 'wp-rss-importer' ); ?></p>
                </td>
            </tr>
        </table>
        <script>
        jQuery(document).ready(function($) {
            $('#feed_type').on('change', function() {
                var feedType = $(this).val();

                // Hide all descriptions
                $('#feed_url_description_rss').hide();
                $('#feed_url_description_wp_api').hide();
                $('#feed_url_description_nyt_api').hide();

                // Show/hide NY Times API fields
                if (feedType === 'nytimes_api') {
                    $('.nytimes-api-field').show();
                    $('#feed_url_description_nyt_api').show();
                    $('#feed_url').prop('required', false);
                } else {
                    $('.nytimes-api-field').hide();
                    $('#feed_url').prop('required', true);

                    if (feedType === 'wordpress_api') {
                        $('#feed_url_description_wp_api').show();
                    } else {
                        $('#feed_url_description_rss').show();
                    }
                }
            });
        });
        </script>
        <?php
    }

    /**
     * Render feed options meta box
     */
    public function render_feed_options_meta_box( $post ) {
        $limit = get_post_meta( $post->ID, '_feed_limit', true );
        $keyword_filter = get_post_meta( $post->ID, '_keyword_filter', true );
        ?>
        <table class="form-table">
            <tr>
                <th><label for="feed_limit"><?php _e( 'Limit', 'wp-rss-importer' ); ?></label></th>
                <td>
                    <input type="number" id="feed_limit" name="feed_limit" value="<?php echo esc_attr( $limit ); ?>" min="0" class="small-text">
                    <p class="description"><?php _e( 'Maximum number of items to import from this feed (0 = unlimited)', 'wp-rss-importer' ); ?></p>
                </td>
            </tr>
            <tr>
                <th><label for="keyword_filter"><?php _e( 'Keyword Filter', 'wp-rss-importer' ); ?></label></th>
                <td>
                    <input type="text" id="keyword_filter" name="keyword_filter" value="<?php echo esc_attr( $keyword_filter ); ?>" class="regular-text">
                    <p class="description"><?php _e( 'Only import items containing this keyword in title or content (leave empty to import all)', 'wp-rss-importer' ); ?></p>
                </td>
            </tr>
        </table>
        <?php
    }

    /**
     * Render feed status meta box
     */
    public function render_feed_status_meta_box( $post ) {
        $last_fetch = get_post_meta( $post->ID, '_last_fetch', true );
        $last_error = get_post_meta( $post->ID, '_last_error', true );
        $last_count = get_post_meta( $post->ID, '_last_import_count', true );

        ?>
        <div class="feed-status">
            <p>
                <strong><?php _e( 'Last Fetch:', 'wp-rss-importer' ); ?></strong><br>
                <?php echo $last_fetch ? esc_html( $last_fetch ) : __( 'Never', 'wp-rss-importer' ); ?>
            </p>
            <p>
                <strong><?php _e( 'Items Imported:', 'wp-rss-importer' ); ?></strong><br>
                <?php echo $last_count !== '' ? esc_html( $last_count ) : __( 'N/A', 'wp-rss-importer' ); ?>
            </p>
            <?php if ( $last_error ) : ?>
            <p class="error-message">
                <strong><?php _e( 'Last Error:', 'wp-rss-importer' ); ?></strong><br>
                <span style="color: #dc3232;"><?php echo esc_html( $last_error ); ?></span>
            </p>
            <?php endif; ?>

            <?php if ( $post->ID ) : ?>
            <p>
                <a href="<?php echo admin_url( 'edit.php?post_type=feed_item&source_id=' . $post->ID ); ?>" class="button button-secondary">
                    <?php _e( 'View Imported Items', 'wp-rss-importer' ); ?>
                </a>
            </p>
            <p>
                <a href="<?php echo wp_nonce_url( admin_url( 'edit.php?post_type=feed_source&page=wp-rss-importer-fetch&source_id=' . $post->ID ), 'fetch_feed_' . $post->ID ); ?>" class="button button-primary">
                    <?php _e( 'Fetch Now', 'wp-rss-importer' ); ?>
                </a>
            </p>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Save feed source meta box data
     */
    public function save_feed_source_meta( $post_id, $post ) {
        // Check nonce
        if ( ! isset( $_POST['wp_rss_importer_meta_box_nonce'] ) ) {
            return;
        }

        if ( ! wp_verify_nonce( $_POST['wp_rss_importer_meta_box_nonce'], 'wp_rss_importer_meta_box' ) ) {
            return;
        }

        // Check autosave
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return;
        }

        // Check permissions
        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            return;
        }

        // Save feed type
        if ( isset( $_POST['feed_type'] ) ) {
            $feed_type = sanitize_text_field( $_POST['feed_type'] );
            if ( in_array( $feed_type, array( 'rss', 'wordpress_api', 'nytimes_api' ) ) ) {
                update_post_meta( $post_id, '_feed_type', $feed_type );
            }
        }

        // Save feed URL
        if ( isset( $_POST['feed_url'] ) ) {
            update_post_meta( $post_id, '_feed_url', esc_url_raw( $_POST['feed_url'] ) );
        }

        // Save limit
        if ( isset( $_POST['feed_limit'] ) ) {
            update_post_meta( $post_id, '_feed_limit', absint( $_POST['feed_limit'] ) );
        }

        // Save keyword filter
        if ( isset( $_POST['keyword_filter'] ) ) {
            update_post_meta( $post_id, '_keyword_filter', sanitize_text_field( $_POST['keyword_filter'] ) );
        }

        // Save NY Times API key
        if ( isset( $_POST['nyt_api_key'] ) ) {
            update_post_meta( $post_id, '_nyt_api_key', sanitize_text_field( $_POST['nyt_api_key'] ) );
        }

        // Save NY Times search query
        if ( isset( $_POST['nyt_search_query'] ) ) {
            update_post_meta( $post_id, '_nyt_search_query', sanitize_text_field( $_POST['nyt_search_query'] ) );
        }

        // Save NY Times section filter
        if ( isset( $_POST['nyt_section'] ) ) {
            update_post_meta( $post_id, '_nyt_section', sanitize_text_field( $_POST['nyt_section'] ) );
        }

        // Save NY Times date filter
        if ( isset( $_POST['nyt_date_filter_days'] ) ) {
            update_post_meta( $post_id, '_nyt_date_filter_days', absint( $_POST['nyt_date_filter_days'] ) );
        }
    }

    /**
     * Render settings page
     */
    public function render_settings_page() {
        if ( isset( $_POST['wp_rss_importer_settings_submit'] ) ) {
            check_admin_referer( 'wp_rss_importer_settings' );

            update_option( 'wp_rss_importer_update_interval', sanitize_text_field( $_POST['update_interval'] ) );
            update_option( 'wp_rss_importer_global_limit', absint( $_POST['global_limit'] ) );

            echo '<div class="notice notice-success"><p>' . __( 'Settings saved.', 'wp-rss-importer' ) . '</p></div>';
        }

        $update_interval = get_option( 'wp_rss_importer_update_interval', 'hourly' );
        $global_limit = get_option( 'wp_rss_importer_global_limit', 0 );
        ?>
        <div class="wrap">
            <h1><?php _e( 'WP RSS Importer Settings', 'wp-rss-importer' ); ?></h1>

            <form method="post" action="">
                <?php wp_nonce_field( 'wp_rss_importer_settings' ); ?>

                <table class="form-table">
                    <tr>
                        <th><label for="update_interval"><?php _e( 'Update Interval', 'wp-rss-importer' ); ?></label></th>
                        <td>
                            <select id="update_interval" name="update_interval">
                                <option value="hourly" <?php selected( $update_interval, 'hourly' ); ?>><?php _e( 'Hourly', 'wp-rss-importer' ); ?></option>
                                <option value="twicedaily" <?php selected( $update_interval, 'twicedaily' ); ?>><?php _e( 'Twice Daily', 'wp-rss-importer' ); ?></option>
                                <option value="daily" <?php selected( $update_interval, 'daily' ); ?>><?php _e( 'Daily', 'wp-rss-importer' ); ?></option>
                            </select>
                            <p class="description"><?php _e( 'How often should feeds be automatically fetched?', 'wp-rss-importer' ); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="global_limit"><?php _e( 'Global Item Limit', 'wp-rss-importer' ); ?></label></th>
                        <td>
                            <input type="number" id="global_limit" name="global_limit" value="<?php echo esc_attr( $global_limit ); ?>" min="0" class="small-text">
                            <p class="description"><?php _e( 'Default limit for all feeds (0 = unlimited). Individual feed limits override this.', 'wp-rss-importer' ); ?></p>
                        </td>
                    </tr>
                </table>

                <p class="submit">
                    <input type="submit" name="wp_rss_importer_settings_submit" class="button button-primary" value="<?php _e( 'Save Settings', 'wp-rss-importer' ); ?>">
                </p>
            </form>
        </div>
        <?php
    }

    /**
     * Render manual fetch page
     */
    public function render_fetch_page() {
        // Handle single source fetch
        if ( isset( $_GET['source_id'] ) && isset( $_GET['_wpnonce'] ) ) {
            $source_id = absint( $_GET['source_id'] );

            if ( wp_verify_nonce( $_GET['_wpnonce'], 'fetch_feed_' . $source_id ) ) {
                $importer = new WP_RSS_Importer_Feed_Importer();
                $result = $importer->import_feed( $source_id );

                if ( is_wp_error( $result ) ) {
                    echo '<div class="notice notice-error"><p>' . esc_html( $result->get_error_message() ) . '</p></div>';
                } else {
                    echo '<div class="notice notice-success"><p>' . __( 'Feed fetched successfully!', 'wp-rss-importer' ) . '</p></div>';
                }
            }
        }

        // Handle all feeds fetch
        if ( isset( $_POST['fetch_all_feeds'] ) ) {
            check_admin_referer( 'fetch_all_feeds' );

            $cron = new WP_RSS_Importer_Cron();
            $cron->fetch_all_feeds();

            echo '<div class="notice notice-success"><p>' . __( 'All feeds fetched successfully!', 'wp-rss-importer' ) . '</p></div>';
        }

        ?>
        <div class="wrap">
            <h1><?php _e( 'Fetch Feeds Manually', 'wp-rss-importer' ); ?></h1>

            <p><?php _e( 'Click the button below to manually fetch all feed sources now.', 'wp-rss-importer' ); ?></p>

            <form method="post" action="">
                <?php wp_nonce_field( 'fetch_all_feeds' ); ?>
                <p class="submit">
                    <input type="submit" name="fetch_all_feeds" class="button button-primary" value="<?php _e( 'Fetch All Feeds Now', 'wp-rss-importer' ); ?>">
                </p>
            </form>

            <hr>

            <h2><?php _e( 'Feed Sources', 'wp-rss-importer' ); ?></h2>

            <?php
            $sources = get_posts( array(
                'post_type'      => 'feed_source',
                'posts_per_page' => -1,
                'post_status'    => 'publish',
            ) );

            if ( $sources ) {
                echo '<table class="wp-list-table widefat fixed striped">';
                echo '<thead><tr><th>' . __( 'Feed Source', 'wp-rss-importer' ) . '</th><th>' . __( 'Last Fetch', 'wp-rss-importer' ) . '</th><th>' . __( 'Items Imported', 'wp-rss-importer' ) . '</th><th>' . __( 'Action', 'wp-rss-importer' ) . '</th></tr></thead>';
                echo '<tbody>';

                foreach ( $sources as $source ) {
                    $last_fetch = get_post_meta( $source->ID, '_last_fetch', true );
                    $last_count = get_post_meta( $source->ID, '_last_import_count', true );

                    echo '<tr>';
                    echo '<td><strong>' . esc_html( $source->post_title ) . '</strong></td>';
                    echo '<td>' . ( $last_fetch ? esc_html( $last_fetch ) : __( 'Never', 'wp-rss-importer' ) ) . '</td>';
                    echo '<td>' . ( $last_count !== '' ? esc_html( $last_count ) : __( 'N/A', 'wp-rss-importer' ) ) . '</td>';
                    echo '<td><a href="' . wp_nonce_url( admin_url( 'edit.php?post_type=feed_source&page=wp-rss-importer-fetch&source_id=' . $source->ID ), 'fetch_feed_' . $source->ID ) . '" class="button button-small">' . __( 'Fetch Now', 'wp-rss-importer' ) . '</a></td>';
                    echo '</tr>';
                }

                echo '</tbody></table>';
            } else {
                echo '<p>' . __( 'No feed sources found. Please add a feed source first.', 'wp-rss-importer' ) . '</p>';
            }
            ?>
        </div>
        <?php
    }
}
