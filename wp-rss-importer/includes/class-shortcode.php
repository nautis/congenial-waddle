<?php
/**
 * Shortcode Handler
 * Outputs HTML matching Mura theme structure exactly
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
            'cols'       => '4',     // Number of columns (1, 2, 3, 4)
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
                return '<p class="wp-rss-aggregator-no-items">' . __( 'No feed items found.', 'wp-rss-importer' ) . '</p>';
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
            $this->render_template( $atts['template'], $feed_query, $atts );

            // Pagination
            if ( $atts['pagination'] === 'on' && $feed_query->max_num_pages > 1 ) {
                $this->render_pagination( $feed_query );
            }
        } else {
            echo '<p class="wp-rss-aggregator-no-items">' . __( 'No feed items found.', 'wp-rss-importer' ) . '</p>';
        }

        wp_reset_postdata();

        return ob_get_clean();
    }

    /**
     * Render template - Matches Mura exactly
     *
     * @param string $template Template name
     * @param WP_Query $query The query object
     * @param array $atts Shortcode attributes
     */
    private function render_template( $template, $query, $atts ) {
        $cols = intval( $atts['cols'] );
        $cols_class = 'cols-' . $cols;

        // Output container matching Mura's structure
        echo '<div class="content-area post-grid ' . esc_attr( $cols_class ) . ' grid">';

        while ( $query->have_posts() ) {
            $query->the_post();

            $source_permalink = get_post_meta( get_the_ID(), '_source_permalink', true );
            $source_author = get_post_meta( get_the_ID(), '_source_author', true );
            $source_id = get_post_meta( get_the_ID(), '_source_id', true );
            $source_name = $source_id ? get_the_title( $source_id ) : '';

            // Build article classes
            $article_classes = array(
                'post-' . get_the_ID(),
                'post',
                'type-post',
                'status-publish',
                'format-standard',
            );

            if ( has_post_thumbnail() ) {
                $article_classes[] = 'has-post-thumbnail';
            }

            $article_classes[] = 'hentry';
            $article_classes[] = 'article';

            if ( has_excerpt() || get_the_excerpt() ) {
                $article_classes[] = 'has-excerpt';
            }

            $article_classes[] = 'thumbnail-landscape'; // Default aspect ratio
            $article_classes[] = 'default'; // Post style

            ?>
            <article id="post-<?php echo get_the_ID(); ?>" class="<?php echo esc_attr( implode( ' ', $article_classes ) ); ?>">

                <div class="formats-key">
                    <!-- Format indicators would go here -->
                </div>

                <div class="post-inner">

                    <?php if ( has_post_thumbnail() ) : ?>
                        <div class="thumbnail-wrapper">
                            <figure class="post-thumbnail">
                                <a href="<?php echo esc_url( $source_permalink ); ?>" target="_blank" rel="noopener noreferrer">
                                    <?php the_post_thumbnail( 'medium_large' ); ?>
                                </a>
                            </figure>
                        </div>
                    <?php endif; ?>

                    <div class="entry-wrapper">

                        <header class="entry-header">

                            <div class="formats-key">
                                <!-- Format indicators would go here -->
                            </div>

                            <h3 class="entry-title">
                                <a href="<?php echo esc_url( $source_permalink ); ?>" rel="bookmark">
                                    <?php the_title(); ?>
                                </a>
                            </h3>

                        </header>

                        <?php if ( has_excerpt() || get_the_excerpt() ) : ?>
                        <div class="entry-content excerpt">
                            <?php echo wp_trim_words( get_the_excerpt(), 30, '...' ); ?>
                        </div>
                        <?php endif; ?>

                    </div><!-- .entry-wrapper -->

                </div><!-- .post-inner -->

            </article>
            <?php
        }

        echo '</div><!-- .content-area -->';
    }

    /**
     * Render pagination - Matches Mura/WordPress exactly
     *
     * @param WP_Query $query The query object
     */
    private function render_pagination( $query ) {
        ?>
        <nav class="navigation pagination" aria-label="Posts pagination">
            <h2 class="screen-reader-text"><?php _e( 'Posts pagination', 'wp-rss-importer' ); ?></h2>
            <div class="nav-links">
                <ul class="page-numbers">
                    <?php
                    $pagination = paginate_links( array(
                        'total'     => $query->max_num_pages,
                        'current'   => max( 1, get_query_var( 'paged' ) ),
                        'type'      => 'array',
                        'prev_text' => '<span>' . __( 'Older Posts', 'wp-rss-importer' ) . '</span>',
                        'next_text' => '<span>' . __( 'Newer Posts', 'wp-rss-importer' ) . '</span>',
                    ) );

                    if ( $pagination ) {
                        foreach ( $pagination as $page ) {
                            echo '<li>' . $page . '</li>';
                        }
                    }
                    ?>
                </ul>
            </div>
        </nav>
        <?php
    }
}
