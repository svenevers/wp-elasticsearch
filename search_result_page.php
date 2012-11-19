<?php get_header(); ?>
<div id="container" style="float: left;">
    <div id="content" role="main">

        <?php
        global $query_string;
        global $elasticaFacets;
        $offset = 0;
        if ( get_query_var( 'page' ) != "" ) {
            $offset = ( max( 1, get_query_var( 'page' ) ) - 1 ) * 4;
        }
        $limit = 4;
        require_once( "lib" . DIRECTORY_SEPARATOR . "Searcher.php" );
        $searcher = new Searcher( get_option( 'elasticsearch_settings_server' ) );
        $facetArr = array();
        if ( get_option( 'elasticsearch_result_category_facet' ) ) {
            array_push( $facetArr, 'cats' );
        }
        if ( get_option( 'elasticsearch_result_tags_facet' ) ) {
            array_push( $facetArr, 'tags' );
        }
        if ( get_option( 'elasticsearch_result_author_facet' ) ) {
            array_push( $facetArr, 'author' );
        }
        //In order to use search for specfic index type, give that type to 6th parameter
        $search_results = $searcher->search( $_GET , $facetArr, $offset, $limit, get_option( 'elasticsearch_settings_index_name' ), false );

        //prepare pagination variables
        $pagination_args = array(
            'total_pages' => $search_results->getTotalHits(),
            'current_page' => max( 1, get_query_var( 'page' ) ),
            'range' => 4,
            'items_per_page' => 2
        );

        $search_result_count = $searcher->search( $_GET , $facetArr, false, false, get_option( 'elasticsearch_settings_index_name' ), false )->count();
        ?>

            <?php if ( $search_result_count > 0 ): ?>
                <h3>Showing <?=( $offset + 1 )?>-<?=( ( $offset + $limit ) >= $search_result_count ) ? $search_result_count : ( $offset + $limit )?> of <?=$search_result_count?> result(s) for search term "<span><?=$searcher->extract_query_string($query_string, 's')?></span>"</h3>
            <?php else: ?>
                <h3>No result found for term "</span><?=$searcher->extract_query_string($query_string, 's')?></span>"</h3>
            <?php endif; ?>



        <?php foreach ($search_results->getResults() as $search_result) { ?>
            <?php $search_data = $search_result->getData(); ?>
            <article id="post-<?php echo $search_data['id']; ?>">
                <header class="entry-header">
                    <h2 class="entry-title"><a href="<?php echo $search_data["uri"]; ?>" title="<?php printf( esc_attr__( 'Permalink to %s' ), $search_data["title"] ); ?>" rel="bookmark"><?php echo $search_data["title"]; ?></a></h2>

                    <div class="entry-meta">
                        <?php the_time('M d, Y'); ?>
                    </div><!-- .entry-meta -->
                </header><!-- .entry-header -->
                <div class="entry-content">
                    <p>
                        <?php echo substr( $search_data["content"], 0, 140 ); ?>
                        <?php echo ( strlen( $search_data["content"] ) > 140 ) ? "..." : ""; ?>
                        <a href="<?php echo $search_data["uri"]; ?>"><?php echo __( 'Continue reading <span class="meta-nav">&rarr;</span>' ) ?></a>
                    </p>
                </div>
            </article><!-- #post-<?php echo $search_data['id']; ?> -->
        <?php } ?>
            <?php if ( $search_result_count > $pagination_args['items_per_page'] ): ?>
                <article>
                    <?php paginate( $pagination_args ); ?>
                </article>
            <?php endif; ?>
    </div>
</div>
<?php $elasticaFacets = $search_results->getFacets(); ?>

<?php get_sidebar();?>
<?php get_footer(); exit; ?>
<?php
/**
 * Pagination helper
 */
function paginate( $pagination_arr ) {
    $range = $pagination_arr['range'];
    $show_items = ( $range * 2 ) + 1;

    $paged = $pagination_arr['current_page'];
    if( empty( $paged ) ) {
        $paged = 1;
    }

    $pages = ceil( $pagination_arr['total_pages'] / $pagination_arr['items_per_page'] ) - 1;
    if( !$pages ) {
        $pages = 1;
    }

    if( $pages != 1) {
        echo "<div class=\"pagination\"><span>Page " . $paged . " of " . $pages . "</span>";
        if( $paged > 2 && $paged > $range + 1 && $show_items < $pages ) {
            echo "<a href='" . add_query_arg( 'page', 1 ) . "'>&laquo; First</a>";
        }
        if( $paged > 1 && $show_items < $pages ) {
            echo "<a href='" . add_query_arg( 'page', ( $paged - 1 ) ) . "'>&lsaquo; Previous</a>";
        }

        for ( $i = 1; $i <= $pages; $i++ ) {
            if ( $pages != 1 && ( !( $i >= $paged + $range + 1 || $i <= $paged - $range - 1 ) || $pages <= $show_items ) ) {
                echo ( $paged == $i )? "<span class=\"current\">" . $i . "</span>":"<a href='" . add_query_arg( 'page', $i ) . "' class=\"inactive\">" . $i . "</a>";
            }
        }

        if ( $paged < $pages && $show_items < $pages ) {
            echo "<a href=\"" . add_query_arg( 'page', ( $paged + 1 ) ) . "\">Next &rsaquo;</a>";
        }
        if ( $paged < $pages - 1 &&  $paged + $range - 1 < $pages && $show_items < $pages ) {
            echo "<a href='" . add_query_arg( 'page', $pages ) . "'>Last &raquo;</a>";
        }
        echo "</div>\n";
    }
}
?>