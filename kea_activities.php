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

class KeaActivities
{
   
    public function __construct() 
    {

        global $wpdb;
        $this->wpdb = $wpdb;
        $this->kea_table_name1 = $this->wpdb->prefix . "kea_activity_gap_fill"; 
        // register actions
        add_action('init', array($this, 'activity_gap_fill_custom_post_type'));
        add_action( 'rest_after_insert_activity_gap_fill', array($this, 'save_activity_gap_fill_meta' ));
        add_action( 'init', array($this, 'activity_gap_fill_register_post_meta' ));
        add_action( 'init', array($this, 'activity_gap_fill_register_block' ));
        add_action( 'init', array($this, 'wporg_register_taxonomy_english' ));
        add_filter( 'rest_prepare_activity_gap_fill', array($this, 'get_activity_gap_fill_posts'));
        register_activation_hook( __FILE__, array($this,'activity_gap_fill_activated' ));

    } 


    

    private function get_json_from_xml_string($xml_string, $encode)
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
    
        if ($encode)
        {
            return json_encode($json_obj);
        }
        else
        {
            return $json_obj;
        }
    
    }

    public function activity_gap_fill_custom_post_type() {
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

    public function save_activity_gap_fill_meta($post)
    {

        $post_id = $post->ID;
        $author_id = get_post_field( 'post_author', $post_id );
        $author_email = get_the_author_meta("user_email", $author_id);
        
        /*
            $author_first_name = get_the_author_meta("first_name", $author_id);
            $author_last_name = get_the_author_meta("last_name", $author_id);
            $author_name = $author_first_name + " " + $author_last_name;
        */
        $post_meta = get_post_meta($post_id); //from cache if poss or from db.
        $post_xml_meta = $post_meta["_activity_gap_fill_meta"][0];
        $post_with_key_meta = $post_meta["_with_key_gap_fill_meta"][0];
        $post_without_key_meta = $post_meta["_without_key_gap_fill_meta"][0];
        //array of term objs or false if none
        //as on the fe it would be nice to do this dynamically TODO
        $grammar_terms = get_the_terms($post, "grammar");
        $russian_grammar_terms = get_the_terms($post, "russian_grammar");
        
        $labels = array();

        function addTerm($terms, &$target)
        {
            
            if ($terms === false)
            {
                return;
            }
            foreach ($terms as $term)
            {
                array_push($target, $term->name);
            }

        }

        addTerm($grammar_terms,  $labels);
        addTerm($russian_grammar_terms, $labels);

        $json = $this->get_json_from_xml_string($post_xml_meta, false);
        $json->labels = $labels;
        $json_string = json_encode($json);

        
        $result = $this->wpdb->replace($this->kea_table_name1, array(
            'kea_activity_gap_fill_post_id' => $post_id, 
            'kea_activity_gap_fill_post_json' => $json_string, 
            'kea_activity_gap_fill_post_author_id' => $author_id, 
            'kea_activity_gap_fill_with_key_key' => $post_with_key_meta, 
            'kea_activity_gap_fill_without_key_key' => $post_without_key_meta
        ),array( '%d', '%s', '%d', '%d' ,'%d')); 
    

        if ($result === false)
        {
            //this prevents json being returned from rest and causes fe to produce a non-informative UI error
            //TODO - can we modify the rest json reponse to produce a custom UI error 
            die("Error saving data - please contact support");
            
        }

    }

    
    public function activity_gap_fill_register_post_meta() {

        function convert_xml_to_json($xml_string)
        {
            
            if (isset($_GET['data']) && ($_GET['data'] == 'json'))
            {
                return $this->get_json_from_xml_string($xml_string, true);
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

    public function activity_gap_fill_activated()
    {
        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
        
        $db_version = '1.1';
        $charset_collate = $this->wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS $this->kea_table_name1 (
            kea_activity_gap_fill_id bigint NOT NULL AUTO_INCREMENT,
            kea_activity_gap_fill_post_id bigint NOT NULL,
            kea_activity_gap_fill_post_json text NOT NULL,
            kea_activity_gap_fill_post_author_id bigint NOT NULL,
            kea_activity_gap_fill_with_key_key int NOT NULL,
            kea_activity_gap_fill_without_key_key int NOT NULL,
            PRIMARY KEY  kea_activity_gap_fill_id (kea_activity_gap_fill_id),
            UNIQUE (kea_activity_gap_fill_post_id)
        ) $charset_collate;";
        dbDelta( $sql );

        $table = $this->kea_table_name1;

        if ( $this->wpdb->get_var("SHOW TABLES LIKE '$table'") != $this->kea_table_name1 ) {
            echo "ERROR. Table not created. Please contact support";
            die;
        }

        add_option( 'kea_activity_gap_fill_db_version', $db_version );
    }

    public function activity_gap_fill_register_block() {

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

    public function wporg_register_taxonomy_english() {

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
            'name'              => _x( 'English Grammar', 'taxonomy general name' ),
            'singular_name'     => _x( 'English Grammar', 'taxonomy singular name' ),
            'search_items'      => __( 'Search English Grammar' ),
            'all_items'         => __( 'All English Grammar' ),
            'parent_item'       => __( 'Parent Level' ),
            'parent_item_colon' => __( 'Parent Level:' ),
            'edit_item'         => __( 'Edit English Grammar' ),
            'update_item'       => __( 'Update English Grammar' ),
            'add_new_item'      => __( 'Add New English Grammar Term' ),
            'new_item_name'     => __( 'New Grammar Name' ),
            'menu_name'         => __( 'English Grammar' ),
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
        
        
        //RUSSIAN
    
        $labels = array(
            'name'              => _x( 'Russian Grammar', 'taxonomy general name' ),
            'singular_name'     => _x( 'Russian Grammar', 'taxonomy singular name' ),
            'search_items'      => __( 'Search Russian Grammar' ),
            'all_items'         => __( 'All Russian Grammar' ),
            'parent_item'       => __( 'Parent Level' ),
            'parent_item_colon' => __( 'Parent Level:' ),
            'edit_item'         => __( 'Edit Grammar' ),
            'update_item'       => __( 'Update Russian Grammar' ),
            'add_new_item'      => __( 'Add New Russian Grammar Term' ),
            'new_item_name'     => __( 'New Grammar Name' ),
            'menu_name'         => __( 'Russian Grammar' ),
            );
            
            $args = array(
            'hierarchical'          => true,
            'labels'                => $labels,
            'show_ui'               => true,
            'show_admin_column'     => true,
            'query_var'             => true,
            'rewrite'               => array( 'slug' => 'russian_grammar' ),
            'show_in_rest'          => true,
            'rest_base'             => 'russian_grammar_terms',
            'rest_controller_class' => 'WP_REST_Terms_Controller',
            );
    
        register_taxonomy( 'russian_grammar', array( 'activity_gap_fill' ), $args);
    
        wp_insert_term(
                'Спряжение глаголов',
                'russian_grammar',
                array(
                'description' => 'Спряжение глаголов',
                'slug'        => 'russian-conjugation-of-verbs' //parent if hier
                )
        );
    
        /*
        wp_delete_term(46, 'ages');
        wp_delete_term(8, 'ages');
        */
    
    }

    public function get_activity_gap_fill_posts( $data ) {

        $data->data['title']['rendered'] = strip_tags($data->data['title']['rendered']);
        return $data;
    }
            
   
}

$kea_activities = new KeaActivities();


?>