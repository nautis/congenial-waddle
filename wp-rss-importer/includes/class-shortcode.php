<?php
/**
 * Shortcode Handler
 */

if ( ! defined( 'WPINC' ) ) {
    die;
}

class WP_RSS_Importer_Shortcode {

    /**
     * Render the shortcode
     *
     * @param array $atts Shortcode attributes
     * @return string
     */
    public function render_shortcode( $atts ) {
        $atts = shortcode_atts( array(
            'sources'    => '',      // Comma-separated source IDs
            'feeds'      => '',      // Comma-separated source slugs
            'exclude'    => '',      // Comma-separated source IDs to exclude
            'limit'      => 10,      // Number of items to display
            'category'   => '',      // Category slug(s)
            'pagination' => 'on',    // Enable/disable pagination
            'template'   => 'default', // Template to use
            'page'       => 1,       // Current page
        ), $atts, 'wp-rss-aggregator' );

        // Build query args
        $query_args = array(
            'post_type'      => 'feed_item',
            'post_status'    => 'publish',
            'posts_per_page' => intval( $atts['limit'] ),
            'orderby'        => 'date',
            'order'          => 'DESC',
        );

        // Handle pagination
        if ( $atts['pagination'] === 'on' ) {
            $paged = get_query_var( 'paged' ) ? get_query_var( 'paged' ) : intval( $atts['page'] );
            $query_args['paged'] = $paged;
        }

        // Filter by source IDs
        if ( ! empty( $atts['sources'] ) ) {
            $source_ids = array_map( 'trim', explode( ',', $atts['sources'] ) );
            $query_args['meta_query'][] = array(
                'key'     => '_source_id',
                'value'   => $source_ids,
                'compare' => 'IN',
            );
        }

        // Filter by feed slugs
        if ( ! empty( $atts['feeds'] ) ) {
            $feed_slugs = array_map( 'trim', explode( ',', $atts['feeds'] ) );
            $source_ids = array();

            foreach ( $feed_slugs as $slug ) {
                $source = get_page_by_path( $slug, OBJECT, 'feed_source' );
                if ( $source ) {
                    $source_ids[] = $source->ID;
                }
            }

            if ( ! empty( $source_ids ) ) {
                $query_args['meta_query'][] = array(
                    'key'     => '_source_id',
                    'value'   => $source_ids,
                    'compare' => 'IN',
                );
            }
        }

        // Exclude sources
        if ( ! empty( $atts['exclude'] ) ) {
            $exclude_ids = array_map( 'trim', explode( ',', $atts['exclude'] ) );
            $query_args['meta_query'][] = array(
                'key'     => '_source_id',
                'value'   => $exclude_ids,
                'compare' => 'NOT IN',
            );
        }

        // Filter by category
        if ( ! empty( $atts['category'] ) ) {
            $categories = array_map( 'trim', explode( ',', $atts['category'] ) );

            // Get sources from these categories
            $source_ids = array();
            foreach ( $categories as $category ) {
                $term = get_term_by( 'slug', $category, 'feed_category' );
                if ( $term ) {
                    $sources = get_posts( array(
                        'post_type'      => 'feed_source',
                        'posts_per_page' => -1,
                        'tax_query'      => array(
                            array(
                                'taxonomy' => 'feed_category',
                                'field'    => 'term_id',
                                'terms'    => $term->term_id,
                            ),
                        ),
                        'fields' => 'ids',
                    ) );

                    $source_ids = array_merge( $source_ids, $sources );
                }
            }

            if ( ! empty( $source_ids ) ) {
                $query_args['meta_query'][] = array(
                    'key'     => '_source_id',
                    'value'   => array_unique( $source_ids ),
                    'compare' => 'IN',
                );
            } else {
                // No sources in this category, return empty
                return '<p>' . __( 'No feed items found.', 'wp-rss-importer' ) . '</p>';
            }
        }

        // Set relation for meta_query if multiple conditions
        if ( isset( $query_args['meta_query'] ) && count( $query_args['meta_query'] ) > 1 ) {
            $query_args['meta_query']['relation'] = 'AND';
        }

        // Execute query
        $feed_query = new WP_Query( $query_args );

        // Start output buffering
        ob_start();

        if ( $feed_query->have_posts() ) {
            $this->render_template( $atts['template'], $feed_query );

            // Pagination
            if ( $atts['pagination'] === 'on' && $feed_query->max_num_pages > 1 ) {
                $this->render_pagination( $feed_query );
            }
        } else {
            echo '<p>' . __( 'No feed items found.', 'wp-rss-importer' ) . '</p>';
        }

        wp_reset_postdata();

        return ob_get_clean();
    }

    /**
     * Render template
     *
     * @param string $template Template name
     * @param WP_Query $query The query object
     */
    private function render_template( $template, $query ) {
        echo '<div class="wp-rss-aggregator-items">';

        while ( $query->have_posts() ) {
            $query->the_post();

            $source_permalink = get_post_meta( get_the_ID(), '_source_permalink', true );
            $source_author = get_post_meta( get_the_ID(), '_source_author', true );
            $source_id = get_post_meta( get_the_ID(), '_source_id', true );
            $source_name = $source_id ? get_the_title( $source_id ) : '';

            ?>
            <article class="feed-item" id="feed-item-<?php echo get_the_ID(); ?>">
                <?php if ( has_post_thumbnail() ) : ?>
                    <div class="feed-item-thumbnail">
                        <a href="<?php echo esc_url( $source_permalink ); ?>" target="_blank" rel="noopener">
                            <?php the_post_thumbnail( 'medium' ); ?>
                        </a>
                    </div>
                <?php endif; ?>

                <div class="feed-item-content">
                    <h3 class="feed-item-title">
                        <a href="<?php echo esc_url( $source_permalink ); ?>" target="_blank" rel="noopener">
                            <?php the_title(); ?>
                        </a>
                    </h3>

                    <?php if ( $source_author || $source_name ) : ?>
                    <div class="feed-item-meta">
                        <?php if ( $source_author ) : ?>
                            <span class="feed-item-author"><?php _e( 'By', 'wp-rss-importer' ); ?> <?php echo esc_html( $source_author ); ?></span>
                        <?php endif; ?>
                        <?php if ( $source_name ) : ?>
                            <span class="feed-item-source"><?php _e( 'from', 'wp-rss-importer' ); ?> <?php echo esc_html( $source_name ); ?></span>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>

                    <div class="feed-item-excerpt">
                        <?php the_excerpt(); ?>
                    </div>

                    <div class="feed-item-footer">
                        <a href="<?php echo esc_url( $source_permalink ); ?>" target="_blank" rel="noopener" class="read-more">
                            <?php _e( 'Read More', 'wp-rss-importer' ); ?> &raquo;
                        </a>
                        <span class="feed-item-date"><?php echo get_the_date(); ?></span>
                    </div>
                </div>
            </article>
            <?php
        }

        echo '</div>';
    }

    /**
     * Render pagination
     *
     * @param WP_Query $query The query object
     */
    private function render_pagination( $query ) {
        $pagination = paginate_links( array(
            'total'     => $query->max_num_pages,
            'current'   => max( 1, get_query_var( 'paged' ) ),
            'format'    => '?paged=%#%',
            'prev_text' => __( '&laquo; Previous', 'wp-rss-importer' ),
            'next_text' => __( 'Next &raquo;', 'wp-rss-importer' ),
        ) );

        if ( $pagination ) {
            echo '<div class="wp-rss-aggregator-pagination">';
            echo $pagination;
            echo '</div>';
        }
    }
}
