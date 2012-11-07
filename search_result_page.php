<?php get_header(); ?>
<section id="primary">
    <div id="content" role="main">

        <?php
        global $query_string;
        $offset = 0;
        if ( get_query_var( 'page' ) != "" ) {
            $offset = ( max( 1, get_query_var( 'page' ) ) - 1 ) * 4;
        }
        $limit = 4;
        require_once( "lib" . DIRECTORY_SEPARATOR . "Searcher.php" );
        $searcher = new Searcher( get_option( 'searchbox_settings_server' ) );
        $facetArr = array();
        if ( get_option( 'searchbox_result_category_facet' ) ) {
            array_push( $facetArr, 'cats' );
        }
        if ( get_option( 'searchbox_result_tags_facet' ) ) {
            array_push( $facetArr, 'tags' );
        }
        if ( get_option( 'searchbox_result_author_facet' ) ) {
            array_push( $facetArr, 'author' );
        }
        //In order to use search for specfic index type, give that type to 5th parameter
        $search_results = $searcher->search( $_GET , $facetArr, $offset, $limit, false );

        //prepare pagination variables
        $pagination_args = array(
            'total_pages' => $search_results->getTotalHits(),
            'current_page' => max( 1, get_query_var( 'page' ) ),
            'range' => 4,
            'items_per_page' => 2
        );

        $search_result_count = $searcher->search( $_GET , $facetArr, false, false, false )->count();
        ?>
            <header class="page-header">
                <h1 class="page_title">Search Results for "<span><?=$searcher->extract_query_string($query_string, 's')?></span>"</h1>
                <h3 class="page_title">Showing <?=( $offset + 1 )?>-<?=( ( $offset + $limit ) >= $search_result_count ) ? $search_result_count : ( $offset + $limit )?> of <?=$search_result_count?> result(s)</h3>
            </header>


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
            <article>
                <?php paginate( $pagination_args ); ?>
            </article>
    </div>
</section>
<?php $elasticaFacets = $search_results->getFacets(); ?>
<?php if ( !empty( $elasticaFacets ) ): ?>
<section id="secondary" class="widget-area" role="complementary">
    <aside id="text-6" class="widget widget_text">
        <h3 class="widget-title">Searchbox IO Facet Area</h3>
            <div class="textwidget">
                This area is contains faceted search terms of given result set
            </div>
    </aside>
    <?php if ( ( get_option( 'searchbox_result_tags_facet' ) ) ): ?>
    <!-- Tags -->
    <aside id="archives-3" class="widget">
        <h3 class="widget-title">Tags</h3>
        <?php if ( !empty( $elasticaFacets['tags']['terms'] ) ): ?>
        <ul>
            <?php foreach ( $elasticaFacets['tags']['terms'] as $elasticaFacet ) { ?>
            <li>
                <input type="checkbox" name="facet-tag" value="<?php  echo $elasticaFacet['term']; ?>"/>
                <a href="javascript:;" title="tags" class="facet-search-link" onclick="searchlink(this)" data="<?php  echo $elasticaFacet['term']; ?>"><?php  echo $elasticaFacet['term'] . "(" . $elasticaFacet['count'] . ")"; ?></a>
            </li>
            <?php } ?>
        </ul>
        <?php endif; ?>
    </aside>
    <?php endif; ?>

    <?php if ( ( get_option( 'searchbox_result_category_facet' ) ) ): ?>
    <!-- Categories -->
    <aside id="archives-3" class="widget">
        <h3 class="widget-title">Categories</h3>
        <?php if ( !empty( $elasticaFacets['cats']['terms'] ) ): ?>
        <ul>
            <?php foreach ( $elasticaFacets['cats']['terms'] as $elasticaFacet ) { ?>
            <li>
                <a href="javascript:;" title="cats" class="facet-search-link" onclick="searchlink(this)" data="<?php  echo $elasticaFacet['term']; ?>"><?php  echo $elasticaFacet['term'] . "(" . $elasticaFacet['count'] . ")"; ?></a>
            </li>
            <?php } ?>
        </ul>
        <?php endif; ?>
    </aside>
    <?php endif; ?>

    <?php if ( ( get_option( 'searchbox_result_author_facet' ) ) ): ?>
    <!-- Author -->
    <aside id="archives-3" class="widget">
        <h3 class="widget-title">Author</h3>
        <?php if ( !empty( $elasticaFacets['author']['terms'] ) ): ?>
        <ul>
            <?php foreach ( $elasticaFacets['author']['terms'] as $elasticaFacet ) { ?>
            <li>
                <a href="javascript:;" title="author" class="facet-search-link" onclick="searchlink(this)" data="<?php  echo $elasticaFacet['term']; ?>"><?php  echo $elasticaFacet['term'] . "(" . $elasticaFacet['count'] . ")"; ?></a>
            </li>
            <?php } ?>
        </ul>
        <?php endif; ?>
    </aside>
    <?php endif; ?>
</section>
<form name="search-form-hidden" id="search-form-hidden" action="<?php echo site_url(); ?>">
    <input type="hidden" name="s" id="searchbox-s" value="<?php echo ( empty( $_GET['s'] ) ) ? '' : $_GET['s']; ?>"/>
    <input type="hidden" name="tags" id="searchbox-tags" value="<?php echo ( empty( $_GET['tags'] ) ) ? '' : $_GET['tags'];?>"/>
    <input type="hidden" name="cats" id="searchbox-cats" value="<?php echo ( empty( $_GET['cats'] ) ) ? '' : $_GET['cats'];?>"/>
    <input type="hidden" name="author" id="searchbox-author" value="<?php echo ( empty( $_GET['author'] ) ) ? '' : $_GET['author'];?>"/>
</form>
    <script type="text/javascript">
        function searchlink(element) {
            document.getElementById("searchbox-" + element.title).value = element.getAttribute("data");
            document.forms["search-form-hidden"].submit();
        }
    </script>
<?php endif; ?>
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