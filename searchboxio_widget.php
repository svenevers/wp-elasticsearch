<?php
class searchboxio_widget extends WP_Widget {
    private $widget_title = "Elasticsearch Facet Widget";

    public function __construct() {
        parent::__construct(
            'widget_elasticsearch',
            'Elasticsearch Facet Widget',
            array(
                'description' => __( 'Elasticsearch Facet Widget', 'text_domain' ),
                'classname' => 'Wp_Searchbox_IO'
            )
        );
    }

    function get_elasticsearch_facet_area() {
        global $elasticaFacets;
        if ( !empty( $elasticaFacets ) ): ?>
            <?php if ( ( get_option( 'searchbox_result_tags_facet' ) ) ): ?>
            <?php if ( !empty( $elasticaFacets['tags']['terms'] ) ): ?>
                <!-- Tags -->
                    <h5>Tags</h5>
                    <ul>
                        <?php foreach ( $elasticaFacets['tags']['terms'] as $elasticaFacet ) { ?>
                        <li>
                            <input type="checkbox" name="facet-tag" value="<?php  echo $elasticaFacet['term']; ?>"/>
                            <a href="javascript:;" title="tags" class="facet-search-link" onclick="searchlink(this)" data="<?php  echo $elasticaFacet['term']; ?>"><?php  echo $elasticaFacet['term'] . "(" . $elasticaFacet['count'] . ")"; ?></a>
                        </li>
                        <?php } ?>
                    </ul>
                <?php endif; ?>
            <?php endif; ?>

            <?php if ( ( get_option( 'searchbox_result_category_facet' ) ) ): ?>
            <?php if ( !empty( $elasticaFacets['cats']['terms'] ) ): ?>
                <!-- Categories -->
                    <h5>Categories</h5>
                    <ul>
                        <?php foreach ( $elasticaFacets['cats']['terms'] as $elasticaFacet ) { ?>
                        <li>
                            <a href="javascript:;" title="cats" class="facet-search-link" onclick="searchlink(this)" data="<?php  echo $elasticaFacet['term']; ?>"><?php  echo $elasticaFacet['term'] . "(" . $elasticaFacet['count'] . ")"; ?></a>
                        </li>
                        <?php } ?>
                    </ul>
                <?php endif; ?>
            <?php endif; ?>

            <?php if ( ( get_option( 'searchbox_result_author_facet' ) ) ): ?>
            <?php if ( !empty( $elasticaFacets['author']['terms'] ) ): ?>
                <!-- Author -->
                    <h5>Author</h5>
                    <ul>
                        <?php foreach ( $elasticaFacets['author']['terms'] as $elasticaFacet ) { ?>
                        <li>
                            <a href="javascript:;" title="author" class="facet-search-link" onclick="searchlink(this)" data="<?php  echo $elasticaFacet['term']; ?>"><?php  echo $elasticaFacet['term'] . "(" . $elasticaFacet['count'] . ")"; ?></a>
                        </li>
                        <?php } ?>
                    </ul>
                <?php endif; ?>
            <?php endif; ?>

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
        <?php endif;
    }

    //Front-end display
    function widget( $args, $instance ) {
        global $elasticaFacets;
        if ( !empty( $elasticaFacets ) ) {
            extract( $args );
            $title = apply_filters( 'widget_title', $instance['title'] );
            echo $before_widget;
            echo $before_title . $title . $after_title;
            $this->get_elasticsearch_facet_area();
            echo $after_widget;
        }
    }

    //Backend display
    function form( $instance ) {
        $form_html = '';
        $defaults = array(
            'title' => $this->widget_title
        );
        $instance = wp_parse_args( (array) $instance, $defaults );
        $form_html .= '<p>' .
                       '<label for="' . $this->get_field_id( 'title' ) . '">' . _e('Title:', 'framework') . '</label>' .
			           '<input type="text" class="widefat" id="' . $this->get_field_id( 'title' ) . '" name="' . $this->get_field_name( 'title' ) . '" value="' . $instance['title'] . '" />' .
		              '</p>';
        echo $form_html;
    }

    //Backend update for elasticsearch widget
    function update( $new_instance, $old_instance ) {
        $instance = $old_instance;
        $instance['title'] = strip_tags( $new_instance['title'] );
        return $instance;
    }
}