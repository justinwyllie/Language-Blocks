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
    private $caps;
   
    public function __construct() 
    {

        global $wpdb;
        $this->wpdb = $wpdb;
        $this->kea_table_name1 = $this->wpdb->prefix . "kea_activity"; 
        // register actions
        add_action('init', array($this, 'kea_activity_custom_post_type'));
        add_action( 'rest_after_insert_kea_activity', array($this, 'save_activity_meta' ));
        add_action( 'init', array($this, 'kea_activity_register_post_meta' ));
        register_activation_hook( __FILE__, array($this,'kea_activity_activated' )); 
        add_filter( 'rest_prepare_activity', array($this, 'get_kea_activity_posts'));
        add_action( 'init', array($this, 'wporg_register_taxonomy_english' ));

        add_action( 'init', array($this, 'kea_activity_register_block' ));
        add_action( 'rest_api_init', array($this, 'json_rest_route'));
   
        //TODO - this is a hack - people who can edit_pages ie. eds can do these things with taxonomies 
        //people who can edit_posts can do this thing. - not sure how to do this. create new caps and assign to roles ?
        //
        $this->caps =  array(
            'manage_terms'  => 'delete_others_pages',
            'edit_terms'    => 'delete_others_pages',
            'delete_terms'  => 'delete_others_pages',
            'assign_term'  => 'edit_posts'
            
        );

    } 


    public function get_json_from_xml_string($xml_string, $encode, $activity_type)
    {

        $call = "get_json_from_xml_string_" . $activity_type;
        return call_user_func( array($this, $call), $xml_string, $encode);

    }

    
    //called from outside of site e.g. via a rest call class is not instaniiated when 
    //used to both return json and to save a json string in the 'special' table
    private function get_json_from_xml_string_gapfill($xml_string, $encode)
    {

        $xml = new SimpleXMLElement($xml_string);
        $activity = (string) $xml->activity;

        $activity_type = "gapfill";
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
        $json_obj->activity_type = $activity_type; 
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

    private function get_json_from_xml_string_multiplechoice($xml_string, $encode)
    {

        $xml = new SimpleXMLElement($xml_string);
        $activity = (string) $xml->activity;
        $activity_type = "multiplechoice";
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
        $json_obj->activity_type = $activity_type; 
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

   
        foreach ($xml->questions->children() as $questionNode)
        {
            $question_obj = new StdClass();
            $question = $questionNode->xpath("question");
            
            $question_obj->question = (string) $question[0];
            $question_obj->question = strip_tags($question_obj->question); 
            $question_obj->answers =  array();

            $answers = $questionNode->xpath("answers");
            //var_dump($answers);die();
            $question_obj->answers = array();
            foreach ($answers[0] as $answer)
            {
                $variant = (string) $answer["variant"][0];
                $text = (string) $answer;
                
        
                $question_obj->answers[strip_tags($text)] = $variant;
            }

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

    public function kea_activity_custom_post_type() {

        register_post_type('kea_activity',
            array(
                'labels'      => array(
                    'name'          => __('Activities', 'kea'),
                    'singular_name' => __('Activities', 'kea'),
                ),
                    'public'      => true,
                    'show_in_rest' => true,
                    'rest_base'    => 'kea_activity',
                    'has_archive' => true,
                    'supports' => array( 'title', 'editor', 'custom-fields', 'revisions', 'author' ),
                    'rewrite'     => array( 'slug' => 'gap_fill' )
                    //'taxonomies'  => array( 'category', 'post_tag' )
            )
        );

       
    }

    public function save_activity_meta($post)
    {

        $post_id = $post->ID;
        $author_id = get_post_field( 'post_author', $post_id );
        
        
        /*
            $author_first_name = get_the_author_meta("first_name", $author_id);
            $author_last_name = get_the_author_meta("last_name", $author_id);
            $author_name = $author_first_name + " " + $author_last_name;
        */
        $post_meta = get_post_meta($post_id); //from cache if poss or from db.
        $post_xml_meta = $post_meta["_kea_activity_meta"][0];
        $activity_type = $post_meta["_kea_activity_type"][0];

        if (empty($post_xml_meta))
        {
            return;
        }

        $post_with_key_meta = intval($post_meta["_with_key_meta"][0]);
        $post_without_key_meta = intval($post_meta["_without_key_meta"][0]); 

        //var_dump("_without_key_gap_fill_meta", $post_without_key_meta);

        //this was obtained in rest_prepare_activity_gap_fill and sent to f/e via meta for display : 
        //we get it now from there and not from post and database so we are sure we have one seen by user
        $author_email = $post_meta["_author_email"][0]; 
       
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

        $json = $this->get_json_from_xml_string($post_xml_meta, false, $activity_type);
        $json->labels = $labels;
     
    
        $json_string = json_encode($json);
       

          //var_dump($json_string);

        /*
         wp replace -> mysql replace
         REPLACE works exactly like INSERT, except that if an old row in the table has the same 
         value as a new row for a PRIMARY KEY or a UNIQUE index, the old row is deleted before the new 
         row is inserted. See Section 13.2.7, “INSERT Statement”.
         https://dev.mysql.com/doc/refman/8.0/en/replace.html 
         TODO maybe matter to use INSERT INTO UPDATE ON DUPLICATE though it looks like might have to build query manually for that
         */
                                 
       
        //TODO it is not post type - it is activity type
       
         
        $result = $this->wpdb->replace($this->kea_table_name1, array(
            'kea_activity_post_id' => $post_id, 
            'kea_activity_post_type' => $json->activity_type, 
            'kea_activity_post_json' => $json_string, 
            'kea_activity_post_author_id' => $author_id, 
            'kea_activity_with_key_key' => $post_with_key_meta, 
            'kea_activity_author_email' => $author_email,
            'kea_activity_without_key_key' => $post_without_key_meta
            
        ), array( '%d', '%s', '%s', '%d', '%d' ,'%s', '%d')); 


        if ($result === false)
        {
            //this prevents json being returned from rest and causes fe to produce a non-informative UI error
            //TODO - can we modify the rest json reponse to produce a custom UI error 
            die("Error saving data - please contact support");
            
        }

    }


    //https://developer.wordpress.org/rest-api/extending-the-rest-api/adding-custom-endpoints/
    //check - can only allow from github and no other source CORS 
    //https://dev.kazanenglishacademy.com/wp-json/kea_activities/v1/json_post/2750/1784148523   
    //https://dev.kazanenglishacademy.com/wp-json/kea_activities/v1/json_post/2750/2147483647
    //TODO restrict this see CORS doc
    public function json_rest_route()
    {
        register_rest_route( 'kea_activities/v1', '/json_post/(?P<post_id>\d+)/(?P<key>\d+)', array(
            'methods' => 'GET',
            'callback' => array($this, 'special_get_json_for_ad_hoc_projects'),
            'permission_callback' => function () {
                return __return_true();
            } 
            
        ) );
    }

    /*
    'permission_callback' => function () {
                return current_user_can( 'read' );
            } */

    public function special_get_json_for_ad_hoc_projects($request)
    {

        $key = $request['key'];
        $post_id = $request['post_id'];

        $sql = $this->wpdb->prepare( "SELECT * FROM {$this->kea_table_name1} WHERE kea_activity_post_id = %d", $post_id );
        $results = $this->wpdb->get_results( $sql ); //TODO handle error array of objects
        $ex = $results[0];
        
       

        $ex_json = json_decode($ex->kea_activity_post_json);
        
     //   var_dump($results[0]);
       

        if ($key == $ex->kea_activity_with_key_key)
        {
            $ex_json->mode = "withkey";
        }
        else
        {
            function lose_answers($q)
            {
                
              
                $q->answer = preg_replace('/[^\|]/', "", $q->answer);
                return $q;
            }

            $ex_json->mode = "withoutkey";
            
            $questions = $ex_json->questions;
            $questionsWithoutAnswers = array_map('lose_answers', $questions);
            $ex_json->questions = $questionsWithoutAnswers;
        }

        $ex_json->success = true;
        return $ex_json;
    }

    public function maybe_convert_xml_to_json2($xml_string)
    {
        
        if (isset($_GET['data']) && ($_GET['data'] == 'json'))
        {
            //return KeaActivities::get_json_from_xml_string($xml_string, true);
            return $this->get_json_from_xml_string($xml_string, true, $_GET['activity_type']); //NOT TESTED TODO
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

    
    public function kea_activity_register_post_meta() {

        

        //this registers a meta field for this post type and also makes it show in rest
        register_post_meta( 'kea_activity', '_kea_activity_meta', array(
            'show_in_rest' => array(
                'single' => true,
                'type' => 'string', 
                'prepare_callback' => function ( $value ) {
                    $json = $this->maybe_convert_xml_to_json2($value);
                    return $json;
                },
            ),
            'auth_callback' => function() {
            return current_user_can( 'edit_posts' );
            /* (callable) Optional. A function or method to call when 
            performing edit_post_meta, add_post_meta, and delete_post_meta capability checks. */
        } 
        ) );

        register_post_meta( 'kea_activity', '_kea_activity_type', array(
            'show_in_rest' => array(
                'single' => true,
                'type' => 'string', 
             ),
            'auth_callback' => function() {
            return current_user_can( 'edit_posts' );
            /* (callable) Optional. A function or method to call when 
            performing edit_post_meta, add_post_meta, and delete_post_meta capability checks. */
        } 
        ) );

        register_post_meta( 'kea_activity', '_author_email', array(
            'show_in_rest' => array(
                'single' => true,
                'type' => 'string', 
             ),
            'auth_callback' => function() {
            return current_user_can( 'edit_posts' );
            /* (callable) Optional. A function or method to call when 
            performing edit_post_meta, add_post_meta, and delete_post_meta capability checks. */
        } 
        ) );

        //this registers a meta field for this post type and also makes it show in rest
        register_post_meta( 'kea_activity', '_with_key_meta', array(
            'show_in_rest' => array(
                'single' => true,
                'type' => 'string',  //it doesn't in fact accept number or integer
            ),
            'auth_callback' => function() {
            return current_user_can( 'edit_posts' );
        
        } 
        ) );

        //this registers a meta field for this post type and also makes it show in rest
        register_post_meta( 'kea_activity', '_without_key_meta', array(
            'show_in_rest' => array(
                'single' => true,
                'type' => 'string', //it doesn't in fact accept number or integer
    
            ),
            'auth_callback' => function() {
            return current_user_can( 'edit_posts' );

            } 
        ) );
    }

    //TODO this is untested because we have manually run alter table statements.
    public function kea_activity_activated()
    {
        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
        
        $db_version = '1.1';
        $charset_collate = $this->wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS $this->kea_table_name1 (
            kea_activity_id bigint NOT NULL AUTO_INCREMENT,
            kea_activity_post_id bigint NOT NULL,
            kea_activity_post_type ENUM('gapfill', 'multiplechoice') NOT NULL,
            kea_activity_post_json text NOT NULL,
            kea_activity_post_author_id bigint NOT NULL,
            kea_activity_with_key_key bigint NOT NULL,
            kea_activity_without_key_key bigint NOT NULL,
            kea_activity_author_email varchar(100) NOT NULL,
            PRIMARY KEY  kea_activity_id (kea_activity_id),
            UNIQUE (kea_activity_post_id)
        ) $charset_collate;";
        dbDelta( $sql );

        $table = $this->kea_table_name1;

        if ( $this->wpdb->get_var("SHOW TABLES LIKE '$table'") != $this->kea_table_name1 ) {
            echo "ERROR. Table not created. Please contact support";
            die;
        }

        add_option( 'kea_activity_db_version', $db_version );
    }




    //gap fill block
    public function kea_activity_register_block() {

        // automatically load dependencies and version
        $asset_file = include( plugin_dir_path( __FILE__ ) . 'build/index.asset.php');
    
        $css = "/wp-content/plugins/kea_activities/build/index.css";
        //wp_enqueue_style("kea_activities_css",  $css, array(), wp_get_theme()->get( 'Version' )  );


        wp_register_style(
            'activity-editor',
            $css,
            array( 'wp-edit-blocks' ),
            filemtime( plugin_dir_path( __FILE__ ) . 'build/index.css' )
        );
        wp_enqueue_style("activity-editor");

        /*
        wp_register_style(
            'activity-gap-fill-editor',
            plugins_url( 'build/index.css', __FILE__ ),
            array( 'wp-edit-blocks'),
            filemtime( plugin_dir_path( __FILE__ ) . 'build/index.css' )
        );
        */
      
        
        wp_register_script(
            'activity-script',
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
            'editor_style' => 'activity-editor',      
            'editor_script' => 'activity-script',
        ) );

        register_block_type( 'activities/activity-multiple-choice', array(
            'api_version' => 2,
            'title' => 'Activity Multiple Chocie',
            'editor_style' => 'activity-editor',      
            'editor_script' => 'activity-script',
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
            'capabilities'          => $this->caps
          
            );
            
        $post_types = get_post_types();  
        //if the plugin is running on the home site attach this taxonomy to video too.  
        $target_post_types_levels = array( 'kea_activity' );  
        if (array_key_exists("video", $post_types))
        {
            $target_post_types_levels[] = "video";
        }
    
        if (array_key_exists("kea_vocab_item", $post_types))
        {
            $target_post_types_levels[] = "kea_vocab_item";
        }
        

        register_taxonomy( 'levels', $target_post_types_levels, $args );  
    
        
        /*
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
        */
    
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
            'capabilities'          => $this->caps
            );
    
        $target_post_types_age_bands = array( 'kea_activity' );
        
        //not sure we need ages as the ages are given in the taxonomy for 'themes'
        //TODO - check logic
        //they have to be so we can get a simple request for all terms for e.g. themes/kids
        if (array_key_exists("kea_vocab_item", $post_types))
        {
            $target_post_types_age_bands[] = "kea_vocab_item";
        }
    
        //$post_types are we sure we have kea_video_item?  
        register_taxonomy( 'ages_bands', $target_post_types_age_bands, $args);
    
        /*
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
        */
    

    
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
            'capabilities'          => $this->caps
            );


       
            
        //if the plugin is being run on the main site with video posts associate taxonomy to that
        $target_post_types_grammar = array( 'kea_activity' );
        
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
            'capabilities'          => $this->caps
            );
    
        register_taxonomy( 'russian_grammar', array( 'kea_activity' ), $args);
    
     
    
        /*
        wp_delete_term(46, 'ages');
        wp_delete_term(8, 'ages');
        */
    
    }

    //Filters the post data for a REST API response. https://developer.wordpress.org/reference/hooks/rest_prepare_this-post_type/
    //strange - docs say 3 params but only 1 expected. 
    public function get_kea_activity_posts( $response ) {

        $response->data['title']['rendered'] = strip_tags($response->data['title']['rendered']);
        return $response;
    }
            
   
}

$kea_activities2 = new KeaActivities();



?>