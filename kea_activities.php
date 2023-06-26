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

/* add custom post type
https://wordpress.stackexchange.com/questions/275543/custom-post-types-filtered-by-taxonomy-using-rest-api-v2
https://wordpress.stackexchange.com/questions/165610/get-posts-under-custom-taxonomy 

*/

/* 
add custom post type
*/
/*
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
*/
/* TODO sanitise custom post save
currently it lets you save a <script> tag in in the title!!!
https://stackoverflow.com/questions/5151409/what-action-can-i-use-in-wordpress-that-triggers-whenever-a-custom-post-is-saved
*/

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
                //'taxonomies'  => array( 'category', 'post_tag' )
        )
    );
}
add_action('init', 'activity_gap_fill_custom_post_type');


/*
ob_start();
var_dump("debug", $post_id , $_POST);
error_log(ob_get_clean(), 4);
*/


//save the postId and the data to the special table - need to convert to json and add the key type (withKey or withoutKey) and the taxonmy to this json
//so it is a self-contained unit

//$json = get_json_from_xml_string($_POST['activity_gap_fill_meta']);
//$with_key_meta = $_POST['with_key_gap_fill_meta'];
//$without_key_meta = $_POST['without_key_gap_fill_meta'];

//var_dump("test", $without_key_meta);
     

function save_activity_gap_fill_meta($post)
{

    
    $post_id = $post->ID;
    $post_meta = get_post_meta($post_id);
    $post_xml_meta = $post_meta["_activity_gap_fill_meta"][0];
    $post_with_key_meta = $post_meta["_with_key_gap_fill_meta"][0];
    $post_without_key_meta = $post_meta["_without_key_gap_fill_meta"][0];
   // $post_labels_meta = $post_meta["_labels"][0];
   var_dump(get_the_terms($post_id, 'grammar' ));

   



}

add_action( 'rest_after_insert_activity_gap_fill', 'save_activity_gap_fill_meta' );





function get_json_from_xml_string($xml_string)
{
    $xml = new SimpleXMLElement($xml_string);
    $legacy_name = (string) $xml->legacyName;
    $legacy_name = strip_tags($legacy_name);
    $title = (string) $xml->title;
    $title = strip_tags($title);
    $models = (string) $xml->models;
    $models = strip_tags($models, ['<em>','<strong>','<br>']);
    $explanation = (string) $xml->explanation;
    $explanation = strip_tags($explanation, ['<em>','<strong>','<br>']);
    $instructions = $xml->instructions;
    $questions = $xml->questions;

    $json_obj = new StdClass();
    $json_obj->legacy_name = $legacy_name; 
    $json_obj->title = $title; 
    $json_obj->models = $models; 
    $json_obj->explanation = $explanation; 

    $json_obj->instructions = new StdClass();
    $json_obj->questions = [];

    foreach ($xml->instructions->children() as $instruction)
    {
        $lang = $instruction['lang'];
        $json_obj->instructions->$lang = (string) $instruction;
        $json_obj->instructions->$lang = strip_tags($json_obj->instructions->$lang);
    }

    foreach ($xml->questions->children() as $question)
    {
        $question_obj = new StdClass();
        $question_obj->question = (string) $question;
        $question_obj->question = strip_tags($question_obj->question); 
        $question_obj->answer = (string) $question['answer'];
        $question_obj->answer = strip_tags($question_obj->answer);
        $question_obj->questionNumber = (string) $question['questionNumber'];
        $question_obj->questionNumber = strip_tags($question_obj->questionNumber);
        $json_obj->questions[] = $question_obj;
    }

    return json_encode($json_obj);

}


function activity_gap_fill_register_post_meta() {

    function convert_xml_to_json($xml_string)
    {
        
        if (isset($_GET['data']) && ($_GET['data'] == 'json'))
        {
            return get_json_from_xml_string($xml_string);
        }
        else
        {
            if (str_contains($xml_string, "<script"))
            {
                return "";
            }
            else
            {
                return $xml_string;
            }
        }
    }

    //this registers a meta field for this post type and also makes it show in rest
    register_post_meta( 'activity_gap_fill', '_activity_gap_fill_meta', array(
        'show_in_rest' => array(
            'single' => true,
            'type' => 'string', 
            'prepare_callback' => function ( $value ) {
                $json = convert_xml_to_json($value);
                return $json;
            },
        ),
        'auth_callback' => function() {
        return current_user_can( 'edit_posts' );
        /* (callable) Optional. A function or method to call when 
        performing edit_post_meta, add_post_meta, and delete_post_meta capability checks. */
    } 
    ) );

    //this registers a meta field for this post type and also makes it show in rest
    register_post_meta( 'activity_gap_fill', '_with_key_gap_fill_meta', array(
        'show_in_rest' => array(
            'single' => true,
            'type' => 'string',  //it doesn't in fact accept number or integer
        ),
        'auth_callback' => function() {
        return current_user_can( 'edit_posts' );
      
    } 
    ) );

    //this registers a meta field for this post type and also makes it show in rest
    register_post_meta( 'activity_gap_fill', '_without_key_gap_fill_meta', array(
        'show_in_rest' => array(
            'single' => true,
            'type' => 'string', //it doesn't in fact accept number or integer
 
        ),
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
    $table_name1 = $wpdb->prefix . "kea_activity_gap_fill"; 
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE IF NOT EXISTS $table_name1 (
		kea_activity_gap_fill_id bigint NOT NULL AUTO_INCREMENT,
        kea_activity_gap_fill_post_id bigint NOT NULL,
        kea_activity_gap_fill_post_json text NOT NULL,
    	PRIMARY KEY  kea_activity_gap_fill_id (kea_activity_gap_fill_id),
	    UNIQUE (kea_activity_gap_fill_post_id)
	) $charset_collate;";
    dbDelta( $sql );

    if ( $wpdb->get_var("SHOW TABLES LIKE '$table_name1'") != $table_name1 ) {
        echo "ERROR. Table not created. Please contact support";
        die;
    }
    

 
    add_option( 'kea_activity_gap_fill_db_version', $db_version );
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


/* custom taxonomy 

Level: beginner, intermediate, advanced
Age: kids, teenager, adult
Theme: 

filter posts by custom taxon: https://wordpress.stackexchange.com/questions/165610/get-posts-under-custom-taxonomy
custom rest: https://developer.wordpress.org/rest-api/reference/taxonomies/#definition-example-request
and 

- get lists of terms etc: https://torquemag.io/2014/10/working-taxonomies-using-json-rest-api/

? json_url( 'posts?filter[category_name]=force_users&filter[tag]=sith' ); 

https://developer.wordpress.org/rest-api/extending-the-rest-api/adding-rest-api-support-for-custom-content-types/

https://developer.wordpress.org/reference/functions/register_taxonomy/
Note: If you want to ensure that your custom taxonomy behaves like a tag,
 you must add the option 'update_count_callback' => '_update_post_term_count'.

 support multiple langs: TODO
 https://wordpress.stackexchange.com/questions/135747/best-pratice-to-make-taxonomy-terms-translatable-without-changing-slugs
slugs should not change - but display labels should. 
should change when user changes lang. in f/e. 

custom:
https://developer.wordpress.org/rest-api/extending-the-rest-api/adding-custom-endpoints/ 

todo - security on rest api? 

rest for level:
    https://dev.kazanenglishacademy.com/wp-json/wp/v2/grammar_terms
    https://dev.kazanenglishacademy.com/wp-json/wp/v2/levels - gets list of all terms for level (or ages)
    https://dev.kazanenglishacademy.com/wp-json/wp/v2/levels/2 Gets info about level term 2

    https://dev.kazanenglishacademy.com/wp-json/wp/v2/activity_gap_fills - gets all gap fills then eg
    https://dev.kazanenglishacademy.com/wp-json/wp/v2/activity_gap_fills/34 - gets you one by id of post 

    get all gap fills at level 2 (beginner):
    https://dev.kazanenglishacademy.com/wp-json/wp/v2/activity_gap_fills/?level=2
    looks like it has to be by id. 

    get all gap fills at beginner level (2) for kids (5):
        https://dev.kazanenglishacademy.com/wp-json/wp/v2/activity_gap_fills/?level=2&age=5

    //not tested - get taxonomy via WP register rather than own rest request:
    https://wordpress.stackexchange.com/questions/352323/how-to-return-a-list-of-custom-taxonomy-terms-via-the-gutenberg-getentityrecords


    https://developer.wordpress.org/block-editor/reference-guides/data/data-core/ 
    getEntityRecords ?? does this get terms? 

    //how to update block when tags change e.g. in their sep block:
    //looks like you can get the tags this way? https://github.com/WordPress/gutenberg/issues/19486
    wp.data.useSelect( select => select( 'core/editor' ).getEditedPostAttribute( 'tags' ) );
    wp.coreData.useEntityProp( 'postType', postType, 'tags' ); 
*/


function wporg_register_taxonomy_english() {

    //Levels 

    $labels = array(
        'name'              => _x( 'Levels', 'taxonomy general name' ),
        'singular_name'     => _x( 'Level', 'taxonomy singular name' ),
        'search_items'      => __( 'Search Levels' ),
        'all_items'         => __( 'All Levels' ),
        'parent_item'       => __( 'Parent Level' ),
        'parent_item_colon' => __( 'Parent Level:' ),
        'edit_item'         => __( 'Edit Level' ),
        'update_item'       => __( 'Update Level' ),
        'add_new_item'      => __( 'Add New Level' ),
        'new_item_name'     => __( 'New Level Name' ),
        'menu_name'         => __( 'Level' ),
      );
     
      $args = array(
        'hierarchical'          => true,
        'labels'                => $labels,
        'show_ui'               => true,
        'show_admin_column'     => true,
        'query_var'             => true,
        'rewrite'               => array( 'slug' => 'level' ),
        'show_in_rest'          => true,
        'rest_base'             => 'levels',
        'rest_controller_class' => 'WP_REST_Terms_Controller',
      );
     
    $post_types = get_post_types();  
    //if the plugin is running on the home site attach this taxonomy to video too.  
    $target_post_types_levels = array( 'activity_gap_fill' );  
    if (array_key_exists("video", $post_types))
    {
        $target_post_types_levels[] = "video";
    }

    if (array_key_exists("kea_vocab_item", $post_types))
    {
        $target_post_types_levels[] = "kea_vocab_item";
    }
  
    register_taxonomy( 'levels', $target_post_types_levels, $args );  


    

    wp_insert_term(
        'Beginner',
        'levels',
        array(
          'description' => 'Beginner Level',
          'slug'        => 'beginner' //parent if hier
        )
    );
    wp_insert_term(
        'Intermediate',
        'levels',
        array(
          'description' => 'Intermediate Level',
          'slug'        => 'intermediate' //parent if hier
        )
    );
    wp_insert_term(
        'Advanced',
        'levels',
        array(
          'description' => 'Advanced Level',
          'slug'        => 'advanced' //parent if hier
        )
    );

    //Ages 

    $labels = array(
        'name'              => _x( 'Age', 'taxonomy general name' ),
        'singular_name'     => _x( 'Age', 'taxonomy singular name' ),
        'search_items'      => __( 'Search Ages' ),
        'all_items'         => __( 'All Ages' ),
        'parent_item'       => __( 'Parent Level' ),
        'parent_item_colon' => __( 'Parent Level:' ),
        'edit_item'         => __( 'Edit Age' ),
        'update_item'       => __( 'Update Age' ),
        'add_new_item'      => __( 'Add New Age' ),
        'new_item_name'     => __( 'New Age Name' ),
        'menu_name'         => __( 'Age' ),
      );
     
      $args = array(
        'hierarchical'          => true,
        'labels'                => $labels,
        'show_ui'               => true,
        'show_admin_column'     => true,
        'query_var'             => true,
        'rewrite'               => array( 'slug' => 'age_band' ),
        'show_in_rest'          => true,
        'rest_base'             => 'ages_bands',
        'rest_controller_class' => 'WP_REST_Terms_Controller',
      );

    $target_post_types_age_bands = array( 'activity_gap_fill' );
  
    //not sure we need ages as the ages are given in the taxonomy for 'themes'
    //TODO - check logic
    //they have to be so we can get a simple request for all terms for e.g. themes/kids
    if (array_key_exists("kea_vocab_item", $post_types))
    {
        $target_post_types_age_bands[] = "kea_vocab_item";
    }

    //$post_types are we sure we have kea_video_item?  
    register_taxonomy( 'ages_bands', $target_post_types_age_bands, $args);

    wp_insert_term(
        'Kids',
        'ages_bands',
        array(
          'description' => 'Kids (7-12)',
          'slug'        => 'kids' //parent if hier
        )
    );
    wp_insert_term(
        'Teens',
        'ages_bands',
        array(
          'description' => 'Teenagers',
          'slug'        => 'teens' //parent if hier
        )
    );
    wp_insert_term(
        'Adult',
        'ages_bands',
        array(
          'description' => 'Adults',
          'slug'        => 'adults' //parent if hier
        )
    );

    unregister_taxonomy("age_groups");
    unregister_taxonomy("age_bands");
    wp_delete_term(87, 'age_groups');
    wp_delete_term(88, 'age_groups');
    wp_delete_term(89, 'age_groups');

    //Grammar 

    $labels = array(
        'name'              => _x( 'Grammar', 'taxonomy general name' ),
        'singular_name'     => _x( 'Grammar', 'taxonomy singular name' ),
        'search_items'      => __( 'Search Grammar' ),
        'all_items'         => __( 'All Grammar' ),
        'parent_item'       => __( 'Parent Level' ),
        'parent_item_colon' => __( 'Parent Level:' ),
        'edit_item'         => __( 'Edit Grammar' ),
        'update_item'       => __( 'Update Grammar' ),
        'add_new_item'      => __( 'Add New Grammar Term' ),
        'new_item_name'     => __( 'New Grammar Name' ),
        'menu_name'         => __( 'Grammar' ),
      );
     
      $args = array(
        'hierarchical'          => true,
        'labels'                => $labels,
        'show_ui'               => true,
        'show_admin_column'     => true,
        'query_var'             => true,
        'rewrite'               => array( 'slug' => 'grammar' ),
        'show_in_rest'          => true,
        'rest_base'             => 'grammar_terms',
        'rest_controller_class' => 'WP_REST_Terms_Controller',
      );
     
    //if the plugin is being run on the main site with video posts associate taxonomy to that
    $target_post_types_grammar = array( 'activity_gap_fill' );
  
    if (array_key_exists("video", $post_types))
    {
        $target_post_types_grammar[] = "video";
    }

    register_taxonomy( 'grammar', $target_post_types_grammar, $args );
    /* ????
   One thing to note, if you want more than 10 results, you should add 
   ?per_page=100 at the end of your URL. If you have more than 100 results. 
   You need to use some pagination, i.e. to get the second page: 
   /wp-json/wp/v2/priority-tags?per_page=100&page=2 – 
   who knows?
 */

    wp_insert_term(
        'Adjectives',
        'grammar',
        array(
          'description' => 'Adjectives',
          'slug'        => 'adjectives' //parent if hier
        )
    );

    
    wp_insert_term(
        'Passive',
        'grammar',
        array(
          'description' => 'Passive',
          'slug'        => 'passive' //parent if hier
        )
    );

    wp_insert_term(
        'Middle Voice',
        'grammar',
        array(
          'description' => 'Middle Voice',
          'slug'        => 'middle-voice' ,
          'parent' =>110

        )
    );
   
   
 

    /*
    wp_delete_term(46, 'ages');
    wp_delete_term(8, 'ages');
    */

}
add_action( 'init', 'wporg_register_taxonomy_english' );

function get_activity_gap_fill_posts( $data, $post, $context ) {

    $data->data['title']['rendered'] = strip_tags($data->data['title']['rendered']);
    return $data;
}
add_filter( 'rest_prepare_activity_gap_fill', 'get_activity_gap_fill_posts', 10, 3 );

?>