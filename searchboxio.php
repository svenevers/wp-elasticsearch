<?php
/*
    Plugin Name: WpSearchboxIO
    Plugin URI: http://www.searchbox.io
    Description: Index your wordpress site with elastic search.
    Version: 1.0
    Author: Hüseyin BABAL
    Author URI: http://www.searchbox.io
    Tags: elasticsearch, index
*/

class Wp_Searchbox_IO {

    //server settings
    var $searchbox_settings_server;

    //indexing settings
    var $searchbox_delete_post_on_remove;
    var $searchbox_delete_post_on_unpublish;
    var $searchbox_settings_index_name;

    //search result settings
    var $searchbox_result_tags_facet;
    var $searchbox_result_category_facet;
    var $searchbox_result_author_facet;

    //misc.
    var $version = '1.0';
    var $plugin_url = '';


    

    function Wp_Searchbox_IO() {
        //admin panel hooks
        add_action( 'admin_menu', array( &$this, 'on_admin_menu' ) );
        add_action( 'wp_ajax_searchbox_option_update', array( &$this, 'searchbox_option_update' ) );
        add_action( 'wp_ajax_searchbox_index_all_posts', array( &$this, 'searchbox_index_all_posts' ) );
        add_action( 'wp_ajax_searchbox_delete_all_posts', array( &$this, 'searchbox_delete_all_posts' ) );
        add_action( 'wp_ajax_check_server_status', array( &$this, 'searchbox_check_server_status' ) );
        add_action( 'wp_print_styles', array( &$this, 'searchbox_theme_css' ) );
        register_activation_hook( __FILE__, array( &$this, 'on_plugin_init' ) );

        //frontend hooks
        add_action( 'save_post', array( &$this, 'index_post' ) );
        add_action( 'delete_post', array( &$this, 'delete_post' ) );
        add_action( 'template_redirect', array( &$this, 'search_term') );

        //server settings
        $this->searchbox_settings_server = get_option( "searchbox_settings_server" );

        //indexing settings
        $this->searchbox_delete_post_on_remove = get_option( "searchbox_delete_post_on_remove" );
        $this->searchbox_delete_post_on_unpublish = get_option( "searchbox_delete_post_on_unpublish" );
        $this->searchbox_settings_index_name = get_option( "searchbox_settings_index_name" );

        //search result settings
        $this->searchbox_result_tags_facet = get_option( "searchbox_result_tags_facet" );
        $this->searchbox_result_category_facet = get_option( "searchbox_result_category_facet" );
        $this->searchbox_result_author_facet = get_option( "searchbox_result_author_facet" );

        //misc.
        $this->plugin_url = plugins_url() . DIRECTORY_SEPARATOR . basename( dirname( __FILE__ ) ) . DIRECTORY_SEPARATOR;
    }

    //plugin init
    function on_plugin_init() {
        //Default plugin values
        update_option( 'searchbox_result_category_facet', true );
        update_option( 'searchbox_result_tags_facet', true );
        update_option( 'searchbox_result_author_facet', true );
    }

    //admin menu option
    function on_admin_menu() {
        //add option page to settings menu on admin panel
        if (function_exists('add_menu_page')) {
            $this->pagehook = add_menu_page( 'WPSearchboxIO Index/Search Manager', "WPSearchboxIO", 'administrator', basename( __FILE__ ),
                array( &$this, 'on_show_page' ), plugins_url( 'wpsearchboxio/images/searchboxio.ico' ) );
        } else {
            $this->pagehook = add_options_page( 'WPSearchboxIO', "WPSearchboxIO", 'manage_options', 'WP-Searchbox-IO', array( &$this, 'on_show_page' ) );
        }
        //register  option page hook
        add_action( 'load-'.$this->pagehook, array( &$this, 'on_load_page' ) );
    }

    //this page will be rendered on admin panel option page
    function on_load_page() {
        //wordpress specific js files
        wp_enqueue_script('common');
        wp_enqueue_script('wp-lists');
        wp_enqueue_script('postbox');
        wp_enqueue_script('jquery-form');

        //configuration sections
        add_meta_box('searchbox_server_configuration_section', 'Server Configurations', array(&$this, 'on_server_conf'), $this->pagehook, 'normal', 'core');
        add_meta_box('searchbox_indexing_operation_section', 'Indexing Operations', array(&$this, 'on_indexing_operations_conf'), $this->pagehook, 'normal', 'core');
        add_meta_box('searchbox_indexing_configuration_section', 'Indexing Configurations', array(&$this, 'on_indexing_conf'), $this->pagehook, 'normal', 'core');
        add_meta_box('searchbox_search_result_configuration_section', 'Search Result Configurations', array(&$this, 'on_search_result_conf'), $this->pagehook, 'normal', 'core');
    }

    //check permission and show admin panel option page
    function on_show_page() {
        require_once( "lib" . DIRECTORY_SEPARATOR . "ModelPost.php" );
        if ( !current_user_can( 'manage_options' ) ) {
            wp_die(__('You do not have enough privilege to access this page.'));
        }
        //we need the global screen column value to beable to have a sidebar in WordPress 2.8
        global $screen_layout_columns;

        ?>
    <div id="wp-searchbox-io" class="wrap">
        <style>.leftlabel{display:block;width:250px;float:left} .rightvalue{width:350px;} .checkwidth{width:25px;}</style>
        <?php screen_icon('options-general'); ?>
        <h2>WP Searchbox IO</h2>
        <p>WP Searchbox IO is a wordpress plugin that lets you index your entire website components by using <a href="http://www.elasticsearch.org/" target="_blank">elasticsearch</a>.
            You can see detailed information about searchbox io <a href="https://searchbox.io/" target="_blank">here</a></p>
        <div id="poststuff" class="metabox-holder<?php echo 2 == $screen_layout_columns ? ' has-right-sidebar' : ''; ?>">
            <div id="side-info-column" class="inner-sidebar">
                <?php /*do_meta_boxes($this->pagehook, 'side', $data);*/ ?>
            </div>
            <div id="post-body" class="has-sidebar">
                <div id="post-body-content" class="has-sidebar-content">
                    <?php do_meta_boxes($this->pagehook, 'normal', $data); ?>
                </div>
            </div>
            <br class="clear"/>
        </div>
    </div>
    <script type="text/javascript">
        //<![CDATA[
        jQuery(document).ready( function($) {
            // close postboxes that should be closed
            $('.if-js-closed').removeClass('if-js-closed').addClass('closed');
            //Desired close sections
            $('#searchbox_indexing_configuration_section').addClass('closed');
            $('#searchbox_search_result_configuration_section').addClass('closed');
            // postboxes setup
            postboxes.add_postbox_toggles('<?php echo $this->pagehook; ?>');

            checkServerStatusAjax(false);
        });
        var waitImg="<img src='/wp-admin/images/loading.gif'/>";
        var options = {
            beforeSubmit:	showWait,
            success:		showResponse,
            url:			ajaxurl
        };
        // bind to the form's submit event
        jQuery('#searchbox_form_server_settings').submit(function($){
            var url = jQuery('#searchbox_settings_server').val();
            var last_char = jQuery('#searchbox_settings_server').val().substr(jQuery('#searchbox_settings_server').val().length - 1);
            if ( last_char != "/" ) {
                url = jQuery('#searchbox_settings_server').val() + "/";
            }
            jQuery(this).ajaxSubmit(options);
            checkServerStatusAjax(url);
            return false;
        });
        jQuery('#searchbox_form_indexing_settings').submit(function(){
            jQuery(this).ajaxSubmit(options);
            return false;
        });
        jQuery('#searchbox_form_search_result_settings').submit(function(){
            jQuery(this).ajaxSubmit(options);
            return false;
        });
        jQuery('#searchbox_index_all_posts').submit(function(){
            jQuery(this).ajaxSubmit(options);
            return false;
        });
        jQuery('#searchbox_index_all_pages').submit(function(){
            jQuery(this).ajaxSubmit(options);
            return false;
        });
        jQuery('#searchbox_delete_all_posts').submit(function(){
            jQuery(this).ajaxSubmit(options);
            return false;
        });
        jQuery('#searchbox_server_check').submit(function(){
            jQuery(this).ajaxSubmit(options);
            return false;
        });
        jQuery('#searchbox_settings_server').blur(function(){
            var last_char = jQuery('#searchbox_settings_server').val().substr(jQuery('#searchbox_settings_server').val().length - 1);
            if ( last_char != "/" ) {
                jQuery('#searchbox_settings_server').val(jQuery('#searchbox_settings_server').val() + "/" );
            }
        });
        // pre-submit callback
        function showWait(formData, jqForm, options){
            jqForm.children('#board').html("<div class='updated fade'>please wait... "+waitImg+"</div>");
            jqForm.find('.button-primary').attr('disabled', 'disabled');
            return true;
        }
        // post-submit callback
        function showResponse(responseText, statusText, xhr, $form){
            $form.children('#board').html(responseText);
            $form.find('.button-primary').removeAttr('disabled');
            closeResponseMessage($form.children('#board'));
        }

        //remove response text after 5 secs.
        function closeResponseMessage(elem) {
            var t = setTimeout(function() { elem.empty(); }, 5000);
        }

        function checkServerStatusAjax(serverUrl){
            var url = false;
            if (serverUrl) {
                url = serverUrl;
            }
            jQuery.post(ajaxurl, { action: "check_server_status", url: url },
                    function(data) {
                        if (data) {
                            jQuery("#server-status-div").css({'background':'green'}).attr({'title':'Running'});
                        } else {
                            jQuery("#server-status-div").css({'background':'red'}).attr({'title':'Down'});
                        }
                    }, 'json');
        }
        //]]>
    </script><?php
    }

    //form tag opening
    private function form_start( $form_id ) {

		$html = '<form action="#" method="post" id="' . $form_id . '">' .
		        '<input type="hidden" name="action" value="searchbox_option_update"/>' .
                '<input type="hidden" name="section_type" value="' . $form_id . '"/>';
        echo $html;
    }

    //build specific form element
    private function form_component( $label_text, $type, $name, $value, $disabled = false ) {
        $disabled_html = '';
        if ( $disabled ) {
            $disabled_html = " style=\"display:none;\"";
        }
        $html = '<div' . $disabled_html . '><span class="leftlabel">' . $label_text . '</span><input type="' . $type .'" ';
        if ( $type == 'checkbox' ) {
            $html .= 'value="true" class="checkwidth" ';
            if ( $value ) {
                $html .= 'checked="true" ';
            }
        } else {
            $html .= 'value="' . $value . '" class="rightvalue" ';
        }
        $html .= 'id="' . $name . '" name="' . $name . '" />';
        echo $html . "<br><br>" . '</div>' . "\n";
    }

    //form tag ending
    private function form_end() {
        $html = '<p>' .
                    '<input type="submit" value="Save Changes" class="button-primary" name="Submit"/>' .
                    '<input type="reset" value="Reset" class="button-primary" name="Reset"/>' .
                '</p>' .
                '<div id="board" name="board"></div>' .
		        '</form>';
        echo $html;
    }

    private function custom_buttons( $form_id , $button_name ) {
        $html = '<form action="#" method="post" id="' . $form_id . '">' .
            '<input type="hidden" name="action" value="' . $form_id . '"/>';
        $html .= '<p>' .
            '<input type="submit" value="' . $button_name . '" class="button-primary" name="Submit"/>' .
            '</p>' .
            '<div id="board" name="board"></div>' .
            '</form>';
        echo $html;
    }

    private function custom_status($status = 'red') {
        if ($status) {
            $background = 'green';
            $title = 'Running';
        } else {
            $background = 'red';
            $title = 'Down';
        }
        $html ='<div><span class="leftlabel">Server Status:</span> <div id="server-status-div" style="height: 20px; width: 20px; background: ' . $background . '; border-radius: 15px; position: absolute; left: 265px;" title="' . $title . '"></div></div><br/><br/><br/>';
        echo $html;
    }

    private function custom_text($label, $text) {
        $html ='<div><span class="leftlabel">' . $label . '</span> <div style="position: absolute; left: 265px;">' . $text . '</div></div><br/><br/><br/>';
        echo $html;
    }

    //server configuration section content
    function on_server_conf() {
        require_once( "lib" . DIRECTORY_SEPARATOR . "ModelPost.php" );
        $model = new ModelPost();
        $model->serverUrl = $this->searchbox_settings_server;
        $this->form_start('searchbox_form_server_settings');
        $this->form_component( "Elasticsearch server:", "text", "searchbox_settings_server", $this->searchbox_settings_server );
        $this->custom_status( true );
        $this->custom_text( "Total Index Count:", "<b>" . $model->checkIndexCount() . "</b>" );
        $this->form_end();
    }

    //indexing configuration section content
    function on_indexing_conf() {
        require_once( "lib" . DIRECTORY_SEPARATOR . "ModelPost.php" );
        $this->form_start('searchbox_form_indexing_settings');
        $this->form_component( "Default Post Index Name:", "text", "searchbox_settings_index_name", ( ( strlen( $this->searchbox_settings_index_name ) < 1 ) ? ModelPost::$_TYPE : $this->searchbox_settings_index_name ) );
        $this->form_component( "Delete Post Index on Remove: ", "checkbox", "searchbox_delete_post_on_remove", $this->searchbox_delete_post_on_remove );
        $this->form_component( "Delete Post Index on Unpublish: ", "checkbox", "searchbox_delete_post_on_unpublish", $this->searchbox_delete_post_on_unpublish );
        $this->form_end();
    }

    //search result configuration section content
    function on_search_result_conf() {
        $this->form_start('searchbox_form_search_result_settings');
        $this->form_component( "Category Facet: ", "checkbox", "searchbox_result_category_facet", $this->searchbox_result_category_facet );
        $this->form_component( "Tag Facet: ", "checkbox", "searchbox_result_tags_facet", $this->searchbox_result_tags_facet );
        $this->form_component( "Author Facet: ", "checkbox", "searchbox_result_author_facet", $this->searchbox_result_author_facet );
        $this->form_end();
    }

    //Some indexing operations on admin panel option page
    function on_indexing_operations_conf() {
        $this->custom_buttons( "searchbox_index_all_posts", "Index All Posts" );
        $this->custom_buttons( "searchbox_delete_all_posts", "Delete Documents" );
    }

    function searchbox_option_update() {
        $options['searchbox_form_server_settings'] = array(
            'searchbox_settings_server'
        );
        $options['searchbox_form_indexing_settings'] = array(
            'searchbox_delete_post_on_remove',
            'searchbox_delete_post_on_unpublish',
            'searchbox_settings_index_name'
        );
        $options['searchbox_form_search_result_settings'] = array(
            'searchbox_result_tags_facet',
            'searchbox_result_category_facet',
            'searchbox_result_author_facet'
        );

        if ( !empty( $_POST['action'] ) && $_POST['action'] == 'searchbox_option_update' ) {
            foreach ( $options[$_POST['section_type']] as $section_field ) {
                $option_value = false;
                if ( array_key_exists( $section_field, $_POST ) ) {
                    $option_value = $_POST[$section_field];
                }

                update_option( $section_field, $option_value );
            }
        }

        echo "<div class='updated fade'>" . __( 'Options Updated' ) . "</div>";
        die;
    }

    /**
     * Admin panel actions start
     */
    /**
     * Index all pages ajax
     */
    function searchbox_index_all_posts() {
        $document_count = 0;
        if ( !empty( $_POST['action'] ) && $_POST['action'] == 'searchbox_index_all_posts' ) {
            $document_count = $this->index_all_posts();
        }
        echo "<div class='updated fade'>" . __( 'Index All Post Operation Finished, ' . $document_count . ' document(s) sent to the Elasticsearch server' ) . "</div>";
        die;
    }

    function searchbox_delete_all_posts() {
        if ( !empty( $_POST['action'] ) && $_POST['action'] == 'searchbox_delete_all_posts' ) {
            $this->delete_all_posts();
        }
        echo "<div class='updated fade'>" . __( 'Delete All Post Operation Finished' ) . "</div>";
        die;
    }

    /**
     * Check server status ajax
     */
    function searchbox_check_server_status() {
        $ret = false;
        if ( !empty( $_POST['action'] ) && $_POST['action'] == 'check_server_status' ) {
            if ( $_POST['url'] !== 'false' ) {
                $ret = $this->check_server_status( $_POST['url'] );
            } else {
                $ret = $this->check_server_status( get_option( 'searchbox_settings_server' ) );
            }
        }
        echo json_encode($ret);
        die;
    }

    /**
     * Admin panel action end
     */

    /**
     * Index post on post create/update
     * @param $post_id
     */
    function index_post( $post_id ) {
        require_once( "lib" . DIRECTORY_SEPARATOR . "ModelPost.php" );
        //post
        if ( is_object( $post_id ) ) {
            $post = $post_id;
            $postId = $post->ID;
        } else {
            $post = get_post( $post_id );
        }
        if ($post->post_status != 'publish') {
            if ( get_option( 'searchbox_delete_post_on_unpublish' ) ) {
                $this->delete_post( $post_id );
            }
        } else {
            //tags
            $tags = get_the_tags( $post->ID );

            //url
            $url = get_permalink( $post_id );

            //categories
            $post_categories = wp_get_post_categories( $post_id );
            $cats = array();

            foreach( $post_categories as $c ) {
                $cat = get_category( $c );
                $cats[] = array( 'name' => $cat->name, 'slug' => $cat->slug );
            }

            //get author info
            $user_info = get_userdata( $post->post_author );
            $model_post = new ModelPost($post, $tags, $url, $cats, $user_info->user_login, get_option( 'searchbox_settings_server' ) );
            $model_post->documentIndex = get_option( 'searchbox_settings_index_name' );
            $model_post->index();
        }
    }

    /**
     * Delete operation on post delete or status unpublish action
     * @param $post_id
     */
    function delete_post( $post_id ) {
        if ( get_option( "searchbox_delete_post_on_remove" ) ) {
            $model_post = new ModelPost( null, null, null, null, null, get_option( 'searchbox_settings_server' ) );
            $model_post->documentIndex = get_option( 'searchbox_settings_index_name' );
            $model_post->delete($post_id);
        }
    }

    /**
     * Loads specified theme of plugin
     */
    function searchbox_theme_css() {
        $name = "style-searchbox.css";
        $css_file = WP_PLUGIN_DIR . DIRECTORY_SEPARATOR . "wp-elasticsearch" . DIRECTORY_SEPARATOR . "css" . DIRECTORY_SEPARATOR . $name;
        if ( false !== @file_exists( $css_file ) ) {
            $css = $this->plugin_url . DIRECTORY_SEPARATOR . "css" . DIRECTORY_SEPARATOR . $name;
        } else {
            $css = false;
        }
        wp_enqueue_style( 'style-searchbox', $css, false, $this->version, 'screen' );
    }
    /**
     * Handles wp search
     */
    function search_term() {
        if ( is_search() && !empty( $_GET['s'] ) ) {
            // If there is a template file then we use it
            if ( file_exists( dirname( __FILE__ ) . DIRECTORY_SEPARATOR . 'search_result_page.php' ) ) {
                // use plugin supplied file
                include_once( dirname( __FILE__ ) . DIRECTORY_SEPARATOR . 'search_result_page.php' );
            } else {
                return;
            }
        }
    }

    /**
     * Indexes all post
     */
    function index_all_posts() {
        require_once( "lib" . DIRECTORY_SEPARATOR . "ModelPost.php" );
        $model_post = new ModelPost();
        $model_post->documentIndex = get_option( 'searchbox_settings_index_name' );
        $model_post->serverUrl = get_option( 'searchbox_settings_server' );
        if ($model_post->checkIndexExists() != 200) {
            $model_post->createIndexName();
        }
        //Default it gets last 5 posts, so give your parameters. Further detail here : http://codex.wordpress.org/Template_Tags/get_posts
        $args = array(
            'numberposts'     => 99999,
            'offset'          => 0,
            'post_status'     => 'publish',
            'orderby'         => 'post_date'
        );
        $posts = get_posts( $args );
        $document_count = 0;
        foreach ($posts as $post) {
            //tags
            $tags = get_the_tags( $post->ID );

            //url
            $url = get_permalink( $post->ID );

            //categories
            $post_categories = wp_get_post_categories( $post->ID );
            $cats = array();

            foreach( $post_categories as $c ) {
                $cat = get_category( $c );
                $cats[] = array( 'name' => $cat->name, 'slug' => $cat->slug );
            }

            //get author info
            $user_info = get_userdata( $post->post_author );
            $post_data[] = array(
                'post' => $post,
                'tags' => $tags,
                'cats' => $cats,
                'author' => $user_info->user_login,
                'uri' => $url
            );
            $document_count++;
        }

        $model_post->buildIndexDataBulk( $post_data );
        $model_post->documentIndex = get_option( 'searchbox_settings_index_name' );
        $model_post->documentPrefix = ModelPost::$_PREFIX;
        $model_post->serverUrl = get_option( 'searchbox_settings_server' );
        $model_post->checkIndexExists();
        $model_post->index(true);
        return $document_count;
    }



    function delete_all_posts() {
        require_once( "lib" . DIRECTORY_SEPARATOR . "ModelPost.php" );
        $model_post = new ModelPost( null, null, null, null, null, get_option( 'searchbox_settings_server' ) );
        $model_post->documentIndex = get_option( 'searchbox_settings_index_name' );
        $model_post->deleteAll();
    }

    function check_server_status($url) {
        require_once( "lib" . DIRECTORY_SEPARATOR . "ModelPost.php" );
        $model_post = new ModelPost();
        $model_post->documentIndex = get_option( 'searchbox_settings_index_name' );
        $model_post->serverUrl = $url;
        return $model_post->checkServerStatus();
    }

}

//create an instance of plugin
if ( class_exists( 'Wp_Searchbox_IO' ) ) {
    if ( !isset( $wp_searchbox_io ) ) {
        $wp_searchbox_io = new Wp_Searchbox_IO;
    }
}
