<?php
/*
Plugin Name: KEA_Activities
Plugin URI: https://kazanenglishacademy.com/plugins
Description: Plugin to register all the exercise post types
Version: 1.0
Author: Justin Wyllie
Author URI:https://kazanenglishacademy.com/plugins
Textdomain: kea
License: copyright Justin Wyllie
*/

/* add custom post type */

function activity_gap_fill_custom_post_type() {
    register_post_type('activity_gap_fill',
        array(
            'labels'      => array(
                'name'          => __('Gap Fill Activity', 'kea'),
                'singular_name' => __('Gap Fill Activity', 'kea'),
            ),
                'public'      => true,
                'show_in_rest' => true,
                'rest_base'    => 'activity_gap_fills',
                'has_archive' => true,
                'supports' => array( 'title', 'editor', 'custom-fields', 'revisions' ),
                'rewrite'     => array( 'slug' => 'gap_fill' ),
        )
    );
}
add_action('init', 'activity_gap_fill_custom_post_type');

/* add custom field to custom post type 

? does it have to be a meta box? what is the difference? */

//https://developer.wordpress.org/plugins/metadata/custom-meta-boxes/

//think this creates the box on the editor page which we don't need as we use block
/*
function add_activity_gap_fill_meta_boxes()
{
    add_meta_box("activity_gap_fill_meta", "Activity Gap Fill XML", 
        "get_activity_gap_fill_meta_box", "activity_gap_fill", "normal", "high");
}   


//function to get the meta box data for the custom post
function get_activity_gap_fill_meta_box($post)
{
        // Add a nonce field so we can check for it later. TODO - do I need to do this?
        //is my rest api
        //wp_nonce_field( 'global_notice_nonce', 'global_notice_nonce' ); TODO
        $value = get_post_meta( $post->ID, '_activity_gap_fill_meta', true );
  
        echo '<textarea style="width:100%" id="activity_gap_fill_meta" name="activity_gap_fill_meta">' .  $value . '</textarea>';
    
}
*/

//function to save the meta box data for the custom post
function save_activity_gap_fill_meta($post_id)
{
    /*
    Возвращает
int|true|false.

true - при успешном обновлении.
false - при неудаче. Или когда было передано такое же значение поля (как в бд).
ID первичного поля таблицы метаполей (meta_id), когда было создано новое поле.
*/ 


ob_start();
var_dump("debug", $post_id , $_POST);
error_log(ob_get_clean(), 4);



    if ( array_key_exists( 'activity_gap_fill_meta', $_POST ) ) {
        
        update_post_meta(
            $post_id,
            '_activity_gap_fill_meta',
            $_POST['activity_gap_fill_meta']
        );
    }
}

add_action( 'save_post', 'save_activity_gap_fill_meta' );
//add_action( 'admin_init', 'add_activity_gap_fill_meta_boxes' );



/* get custom field of custom post type in rest 
function get_meta_data( $post, $field_name, $request ) {

    
    
    if (!empty($post)) {
        //todo - this seems a bit weird; why is it not an object?
        if (is_array($post)) {
            $postObj = (object) $post;
        } else
        {
            $postObj = $post;//hopefully    
        }
        $data =  get_post_meta( $postObj->id, $field_name, true);
        return $data;

    }
}


function adding_test_meta_rest() {
   
    register_rest_field( 'activity_gap_fill',
        '_activity_gap_fill_meta',
        array(
            'get_callback'      => 'get_meta_data',
            'update_callback'   => null,
            'schema'            => null,
        )
    );
}
*/ 


//add_action( 'rest_api_init', 'adding_test_meta_rest' );

/* add a meta box via gutenberg - how much of the above is duplicated ? 
e.g add_meta_box and resp. register_rest_field 
https://igmoweb.com/2020/12/23/register_post_meta-vs-register_rest_field/
*/ 
//https://developer.wordpress.org/block-editor/how-to-guides/metabox/meta-block-2-register-meta/

function activity_gap_fill_register_post_meta() {
    //this registers a meta field for this post type and also makes it show in rest
    register_post_meta( 'activity_gap_fill', '_activity_gap_fill_meta', array(
        'show_in_rest' => true, /* this repalces register_rest_field */
        'single' => true,
        'type' => 'string', 
        
        'auth_callback' => function() {
        return current_user_can( 'edit_posts' );
    } 
    ) );
}
add_action( 'init', 'activity_gap_fill_register_post_meta' );



//add database tables on plugin activation

function activity_gap_fill_activated()
{
    require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
    global $wpdb;
    $db_version = '1.0';
    $table_name1 = $wpdb->prefix . "kea_activity_types"; 
    $table_name2 = $wpdb->prefix . "kea_activities"; 
    $charset_collate = $wpdb->get_charset_collate();

    $sql1 = "CREATE TABLE IF NOT EXISTS $table_name1 (
		  activity_type_id mediumint NOT NULL AUTO_INCREMENT,
	      activity_type_name varchar(255) NOT NULL,
	     PRIMARY KEY activity_type_id (activity_type_id),
	     UNIQUE (activity_type_name)
	) $charset_collate;";
    dbDelta( $sql1 );


    $sql2 = "CREATE TABLE IF NOT EXISTS $table_name2 (
        activity_id bigint(20) NOT NULL AUTO_INCREMENT,
		created timestamp NOT NULL default CURRENT_TIMESTAMP,
		meta_id bigint(20) UNSIGNED NOT NULL,
		activity_type_id mediumint NOT NULL,
		age tinyint NOT NULL,
		PRIMARY KEY activity_id (activity_id),
    	FOREIGN KEY (`meta_id`) REFERENCES `wp_postmeta` (`meta_id`) ON DELETE CASCADE
    ) $charset_collate;";
    dbDelta( $sql2 );

    
    $sql3 = "INSERT INTO  $table_name1 VALUES(null, \"activity_gap_fill\");";
    dbDelta( $sql3 );

 
    add_option( 'activity_gap_fill_db_version', $db_version );
}


register_activation_hook( __FILE__, 'activity_gap_fill_activated' );


/*----------------------- Gutenberg --------------------*/


//https://developer.wordpress.org/block-editor/how-to-guides/block-tutorial/writing-your-first-block-type/
function activity_gap_fill_register_block() {
 
    // automatically load dependencies and version
    $asset_file = include( plugin_dir_path( __FILE__ ) . 'build/index.asset.php');

  
 

    wp_register_style(
        'activity-gap-fill-editor',
        plugins_url( 'build/index.css', __FILE__ ),
        array( 'wp-edit-blocks' ),
        filemtime( plugin_dir_path( __FILE__ ) . 'build/index.css' )
    );

 
    wp_register_script(
        'activity-gap-fill-script',
        plugins_url( 'build/index.js', __FILE__ ), 
        $asset_file['dependencies'],
        $asset_file['version']
    );
    /*
    wp_enqueue_script('activity_gap_fill_script');
    wp_enqueue_style('activity_gap_fill-editor');
    wp_enqueue_style('activity_gap_fill-editor-front');
    */
    //
    register_block_type( 'activities/activity-gap-fill', array(
        'api_version' => 2,
        'title' => 'Activity Gap Fill',
        'style' => 'activity-gap-fill-editor-front',
        'editor_style' => 'activity-gap-fill-editor',      
        'editor_script' => 'activity-gap-fill-script',
    ) );
 
}
add_action( 'init', 'activity_gap_fill_register_block' );




?>