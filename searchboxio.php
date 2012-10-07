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
    var $searchbox_api_key;
    var $searchbox_settings_server;
    var $searchbox_setting_api_key_checkbox;

    //indexing settings
    var $searchbox_index_pages;
    var $searchbox_index_posts;
    var $searchbox_index_comments;
    var $searchbox_index_users;
    var $searchbox_delete_page_on_remove;
    var $searchbox_delete_post_on_remove;
    var $searchbox_delete_comment_on_remove;
    var $searchbox_delete_user_on_remove;
    var $searchbox_delete_page_on_unpublish;
    var $searchbox_delete_post_on_unpublish;

    //search result settings
    var $searchbox_result_tags_facet;
    var $searchbox_result_category_facet;
    var $searchbox_result_type_facet;
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
        add_action( 'wp_print_styles', array( &$this, 'searchbox_theme_css' ) );

        //frontend hooks
        add_action( 'save_post', array( &$this, 'index_post' ) );
        add_action( 'delete_post', array( &$this, 'delete_post' ) );
        add_action( 'template_redirect', array( &$this, 'search_term') );

        //server settings
        $this->searchbox_api_key = get_option( "searchbox_api_key" );
        $this->searchbox_setting_api_key_checkbox = get_option( "searchbox_setting_api_key_checkbox" );
        $this->searchbox_settings_server = get_option( "searchbox_settings_server" );

        //indexing settings
        $this->searchbox_index_pages = get_option( "searchbox_index_pages" );
        $this->searchbox_index_posts = get_option( "searchbox_index_posts" );
        $this->searchbox_index_comments = get_option( "searchbox_index_comments" );
        $this->searchbox_index_users = get_option( "searchbox_index_users" );
        $this->searchbox_delete_page_on_remove = get_option( "searchbox_delete_page_on_remove" );
        $this->searchbox_delete_post_on_remove = get_option( "searchbox_delete_post_on_remove" );
        $this->searchbox_delete_comment_on_remove = get_option( "searchbox_delete_comment_on_remove" );
        $this->searchbox_delete_user_on_remove = get_option( "searchbox_delete_user_on_remove" );
        $this->searchbox_delete_page_on_unpublish = get_option( "searchbox_delete_page_on_unpublish" );
        $this->searchbox_delete_post_on_unpublish = get_option( "searchbox_delete_post_on_unpublish" );

        //search result settings
        $this->searchbox_result_tags_facet = get_option( "searchbox_result_tags_facet" );
        $this->searchbox_result_category_facet = get_option( "searchbox_result_category_facet" );
        $this->searchbox_result_type_facet = get_option( "searchbox_result_type_facet" );
        $this->searchbox_result_author_facet = get_option( "searchbox_result_author_facet" );

        //misc.
        $this->plugin_url = plugins_url() . DIRECTORY_SEPARATOR . basename( dirname( __FILE__ ) ) . DIRECTORY_SEPARATOR;
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
        add_meta_box('searchbox_indexing_configuration_section', 'Indexing Configurations', array(&$this, 'on_indexing_conf'), $this->pagehook, 'normal', 'core');
        add_meta_box('searchbox_search_result_configuration_section', 'Search Result Configurations', array(&$this, 'on_search_result_conf'), $this->pagehook, 'normal', 'core');
        add_meta_box('searchbox_indexing_operation_section', 'Indexing Operations', array(&$this, 'on_indexing_operations_conf'), $this->pagehook, 'normal', 'core');
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
            // postboxes setup
            postboxes.add_postbox_toggles('<?php echo $this->pagehook; ?>');
        });
        var waitImg="<img src='/wp-admin/images/loading.gif'/>";
        var options = {
            beforeSubmit:	showWait,
            success:		showResponse,
            url:			ajaxurl
        };
        // bind to the form's submit event
        jQuery('#searchbox_form_server_settings').submit(function(){
            jQuery(this).ajaxSubmit(options);
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
        jQuery('#searchbox_index_optimize').submit(function(){
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
        jQuery('#searchbox_setting_api_key_checkbox').change( function() {
            if(this.checked) {
                jQuery('#searchbox_api_key').parent().show("fast");
                jQuery('#searchbox_settings_server').val("<?php echo ModelBase::$_SEARCHBOX_URL . $this->searchbox_api_key; ?>");
            } else {
                jQuery('#searchbox_api_key').parent().hide("fast");
                jQuery('#searchbox_settings_server').val("<?php echo $this->searchbox_settings_server; ?>");
                jQuery('#searchbox_api_key').val("<?php echo $this->searchbox_api_key; ?>")
            }
        });
        jQuery('#searchbox_api_key').keyup(function(){
            jQuery('#searchbox_settings_server').val("<?php echo ModelBase::$_SEARCHBOX_URL; ?>" + jQuery('#searchbox_api_key').val() );
            var last_char = jQuery('#searchbox_settings_server').val().substr(jQuery('#searchbox_settings_server').val().length - 1);
            if ( last_char != "/" ) {
                jQuery('#searchbox_settings_server').val(jQuery('#searchbox_settings_server').val() + "/" );
            }

        });
        jQuery('#searchbox_settings_server').blur(function(){
            var last_char = jQuery('#searchbox_settings_server').val().substr(jQuery('#searchbox_settings_server').val().length - 1);
            if ( last_char != "/" ) {
                jQuery('#searchbox_settings_server').val(jQuery('#searchbox_settings_server').val() + "/" );
            }
        });
        //options.success=refreshImage;
        // pre-submit callback
        function showWait(formData, jqForm, options){
            jqForm.children('#board').html("<div class='updated fade'>please wait... "+waitImg+"</div>");
            //var queryString = jQuery.param(formData);
            //alert('About to submit: \n\n' + queryString);
            return true;
        }
        // post-submit callback
        function showResponse(responseText, statusText, xhr, $form){
            $form.children('#board').html(responseText);
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

    //server configuration section content
    function on_server_conf() {
        $this->form_start('searchbox_form_server_settings');
        $this->form_component( "I have searchbox.io api key ", "checkbox", "searchbox_setting_api_key_checkbox", $this->searchbox_setting_api_key_checkbox );
        if ( $this->searchbox_setting_api_key_checkbox ) {
            $this->form_component( "searchbox.io API key:", "text", "searchbox_api_key", $this->searchbox_api_key );
        } else {
            $this->form_component( "searchbox.io API key:", "text", "searchbox_api_key", $this->searchbox_api_key, true );
        }
        $this->form_component( "Elasticsearch server:", "text", "searchbox_settings_server", $this->searchbox_settings_server );
        $this->form_end();
    }

    //indexing configuration section content
    function on_indexing_conf() {
        $this->form_start('searchbox_form_indexing_settings');
        $this->form_component( "Index Pages: ", "checkbox", "searchbox_index_pages", $this->searchbox_index_pages );
        $this->form_component( "Index Posts: ", "checkbox", "searchbox_index_posts", $this->searchbox_index_posts );
        $this->form_component( "Index Comments: ", "checkbox", "searchbox_index_comments", $this->searchbox_index_comments );
        $this->form_component( "Index Users: ", "checkbox", "searchbox_index_users", $this->searchbox_index_users );
        $this->form_component( "Delete Page Index on Remove: ", "checkbox", "searchbox_delete_page_on_remove", $this->searchbox_delete_page_on_remove );
        $this->form_component( "Delete Post Index on Remove: ", "checkbox", "searchbox_delete_post_on_remove", $this->searchbox_delete_post_on_remove );
        $this->form_component( "Delete Comment Index on Remove: ", "checkbox", "searchbox_delete_comment_on_remove", $this->searchbox_delete_comment_on_remove );
        $this->form_component( "Delete User Index on Remove: ", "checkbox", "searchbox_delete_user_on_remove", $this->searchbox_delete_user_on_remove );
        $this->form_component( "Delete Page Index on Unpublish: ", "checkbox", "searchbox_delete_page_on_unpublish", $this->searchbox_delete_page_on_unpublish );
        $this->form_component( "Delete Post Index on Unpublish: ", "checkbox", "searchbox_delete_post_on_unpublish", $this->searchbox_delete_post_on_unpublish );
        $this->form_end();
    }

    //search result configuration section content
    function on_search_result_conf() {
        $this->form_start('searchbox_form_search_result_settings');
        $this->form_component( "Category Facet: ", "checkbox", "searchbox_result_tags_facet", $this->searchbox_result_tags_facet );
        $this->form_component( "Tag Facet: ", "checkbox", "searchbox_result_category_facet", $this->searchbox_result_category_facet );
        $this->form_component( "Author Facet: ", "checkbox", "searchbox_result_author_facet", $this->searchbox_result_author_facet );
        $this->form_end();
    }

    //Some indexing operations on admin panel option page
    function on_indexing_operations_conf() {
        $this->custom_buttons( "searchbox_index_all_posts", "Index All Posts" );
        $this->custom_buttons( "searchbox_index_all_pages", "Index All Pages" );
        $this->custom_buttons( "searchbox_index_optimize", "Optimize Index" );
        $this->custom_buttons( "searchbox_delete_all_posts", "Delete Index" );
        $this->custom_buttons( "searchbox_server_check", "Check Server Status" );
    }

    function searchbox_option_update() {
        $options['searchbox_form_server_settings'] = array(
            'searchbox_api_key',
            'searchbox_settings_server',
            'searchbox_setting_api_key_checkbox'
        );
        $options['searchbox_form_indexing_settings'] = array(
            'searchbox_index_pages',
            'searchbox_index_posts',
            'searchbox_index_comments',
            'searchbox_index_users',
            'searchbox_delete_page_on_remove',
            'searchbox_delete_post_on_remove',
            'searchbox_delete_comment_on_remove',
            'searchbox_delete_user_on_remove',
            'searchbox_delete_page_on_unpublish',
            'searchbox_delete_post_on_unpublish'
        );
        $options['searchbox_form_search_result_settings'] = array(
            'searchbox_result_tags_facet',
            'searchbox_result_category_facet',
            'searchbox_result_type_facet',
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
        if ( !empty( $_POST['action'] ) && $_POST['action'] == 'searchbox_index_all_posts' ) {
            $this->index_all_posts();
        }
        echo "<div class='updated fade'>" . __( 'Index All Post Operation Finished' ) . "</div>";
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
            $model_post->delete($post_id);
        }
    }

    /**
     * Loads specified theme of plugin
     */
    function searchbox_theme_css() {
        $name = "style-searchbox.css";
        $css_file = WP_PLUGIN_DIR . DIRECTORY_SEPARATOR . "wpsearchboxio" . DIRECTORY_SEPARATOR . "css" . DIRECTORY_SEPARATOR . $name;
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
        //Default it gets last 5 posts, so give your parameters. Further detail here : http://codex.wordpress.org/Template_Tags/get_posts
        $args = array(
            'numberposts'     => 99999,
            'offset'          => 0,
            'post_status'     => 'publish',
            'orderby'         => 'post_date'
        );
        $posts = get_posts( $args );
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
        }
        $model_post = new ModelPost();
        $model_post->buildIndexDataBulk( $post_data );
        $model_post->documentType = ModelPost::$_TYPE;
        $model_post->documentPrefix = ModelPost::$_PREFIX;
        $model_post->serverUrl = get_option( 'searchbox_settings_server' );
        $model_post->index(true);
    }



    function delete_all_posts() {
        require_once( "lib" . DIRECTORY_SEPARATOR . "ModelPost.php" );
        $model_post = new ModelPost( null, null, null, null, null, get_option( 'searchbox_settings_server' ) );
        $model_post->deleteAll();
    }

}
//create an instance of plugin
if ( class_exists( 'Wp_Searchbox_IO' ) ) {
    $wp_searchbox_io = new Wp_Searchbox_IO();
}
