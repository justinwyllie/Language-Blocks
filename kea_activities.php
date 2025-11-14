<?php
/*
Plugin Name: KEA_Activities
Plugin URI: https://kazanenglishacademy.com/pluginssave_activity_meta
Description: Plugin to register all the exercise post types and manage them
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
        $this->posts_table = $this->wpdb->prefix . "posts"; 
        // register actions
        add_action('init', array($this, 'kea_activity_custom_post_type'));
        add_action( 'rest_after_insert_kea_activity', array($this, 'save_activity_meta' ));
        add_action( 'init', array($this, 'kea_activity_register_post_meta' ));
        register_activation_hook( __FILE__, array($this,'kea_activity_activated' )); 
        add_filter( 'rest_prepare_activity', array($this, 'get_kea_activity_posts'));

        add_filter('manage_kea_activity_posts_columns', array($this, 'kea_activitiy_add_type_column'));
        add_action('manage_kea_activity_posts_custom_column', array($this, 'kea_activity_populate_type_column'), 10, 2);

        add_action( 'init', array($this, 'wporg_register_taxonomy_english' ));
        //add_action( 'set_object_terms', array( $this, 'block_non_permitted_working_with_cats' ), 10, 6 );

        add_action( 'init', array($this, 'kea_activity_register_block' ));
        add_action( 'rest_api_init', array($this, 'json_rest_route'));
        //add_action('admin_init', array($this, 'fix_post_roles'));
        add_filter('pre_get_posts', array($this, 'limit_posts_for_current_author'));


        add_filter( 'rest_grammar_collection_params', array($this, 'increase_grammar_terms_per_page_limit'), 10, 2 );
        add_filter( 'rest_russian_grammar_collection_params', array($this, 'increase_grammar_terms_per_page_limit'), 10, 2 );

   
        //TODO - this is a hack - people who can edit_pages ie. eds can do these things with taxonomies 
        //people who can edit_posts can do this thing. - not sure how to do this. create new caps and assign to roles ?
        //delete_others_pages = editor   edit_posts = contrib
        $this->caps =  array(
            'manage_terms'  => 'delete_others_pages',
            'edit_terms'    => 'delete_others_pages',
            'delete_terms'  => 'delete_others_pages',
            'assign_terms'  => 'edit_posts'
            
        );

    } 


    
    public function error_log($object)
    {

        ob_start();                   
        var_dump( $object );           
        $contents = ob_get_contents(); 
        ob_end_clean();               
        error_log( $contents );       
    }

    //? does this effect the default call from the frontpage when react/wp renders the custom taxonomy box on a post?
    //it should allow it but would still need to modify the built in react/wp call
    public function increase_grammar_terms_per_page_limit($query_params, $taxonomy )
    {
        if ( isset( $query_params['per_page'] ) ) {
            //TODO could use wp_count_terms to set this to the existing number, but then would need to get this in the front-end as well. for now 1000. 
            $query_params['per_page']['maximum'] = 1000; 
        }
        return $query_params;
    }

    public function increase_russian_grammar_terms_per_page_limit($query_params, $taxonomy )
    {
        if ( isset( $query_params['per_page'] ) ) {
            //TODO could use wp_count_terms to set this to the existing number, but then would need to get this in the front-end as well. for now 1000. 
            $query_params['per_page']['maximum'] = 1000; 
        }
        return $query_params;
    }

    //TODO copied from kea_repi 
    private function is_admin_by_user_id($user_id)
    {
        if (  user_can($user_id, 'activate_plugins') )
        {
            return true;
        }
        else
        {
            return false;
        }

    }
    //TODO copied from kea_repi 
    private function can_create_and_manage_activities_by_id($user_id)
    {
        return $this->is_admin_by_user_id($user_id);
    }
   

    /*
    * this is pretty brutal. makes no distinction re. post tyoes
    * says: if this is admin page and user is not admin and we are getting posts
    * then modify the posts query so if user cannot edit others posts which will be true if e.g. author 
    * then add a only this user id to the query which gets the list of posts to display
    * there is no capability for viewing posts in the edit list  - so we cannot simply take away a capability 
 
    */
    public function limit_posts_for_current_author($query)
    {

     
        
        global $pagenow;

        if( 'edit.php' != $pagenow || !$query->is_admin )
            return $query;

       

        if( !current_user_can( 'edit_others_posts' ) ) {
            global $user_ID;
            $query->set('author', $user_ID );
            
        }


       
        return $query;
        

    }


    //we don't register the post type with category - but, just in case
    //see kea_activities_repi revert_master_category_assignment
    public function block_non_permitted_working_with_catsXXX($object_id, $terms, $tt_ids, $taxonomy, $append, $old_tt_ids )
    {
        if ( 'category' !== $taxonomy ) {
            return;
        }

        

        foreach ($terms as $term_id)
        {
            $term = get_term($term_id);
            if ($term && !is_wp_error($term)) {
                $term_name = $term->name;
                
                if (strtolower($term_name) == 'master')
                {
                    if (!$this->can_create_and_manage_activities_by_id(get_current_user_id()))
                        {
                            remove_action( 'set_object_terms', array( $this, 'block_non_permitted_working_with_cats' ), 10 );
                            error_log("removing an illicit attempt to set category term by non creator");
                            
                            // Revert to the old terms that were stored in $old_tt_ids
                            wp_set_object_terms( $object_id, $old_tt_ids, $taxonomy, false );
                                    
                            // Add the action back for future operations
                            add_action( 'set_object_terms', array( $this, 'block_non_permitted_working_with_cats' ), 10, 6 );
                        }
                }

            }
        }

        
        
    }

    //there is no cap about viewing posts in the admin list
    public function fix_post_roles()
    {


        //$role = get_role('author');
        //$caps = $role->capabilities;
        //error_log(print_r($caps, true));
        //$role->add_cap('read_posts', false);
        //global $wp_roles;
        //$wp_roles->remove_cap( 'author', 'delete_posts' );
    }


    public function get_json_from_xml_string($xml_string, $encode)
    {
     
        
        $xml = new SimpleXMLElement($xml_string);
        $activity_type = (string) $xml['type'];

      
        //fixup historic data
        if (empty($activity_type) )
        {
            $activity_type = "gapfill";
        }
        $call = "get_json_from_xml_" . $activity_type;
        return call_user_func( array($this, $call), $xml, $encode, $activity_type);

    }

    
    //called from outside of site e.g. via a rest call class is not instaniiated when 
    //used to both return json and to save a json string in the 'special' table
    private function get_json_from_xml_gapfill($xml, $encode, $activity_type)
    {

        
        $activity = (string) $xml->activity;

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

    private function get_json_from_xml_multiplechoice($xml, $encode, $activity_type)
    {


        $activity = (string) $xml->activity;
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

            $question_obj->questionNumber = (string) $questionNode['questionNumber'];

            $answers = $questionNode->xpath("answers");
            $question_obj->answers = array();
            foreach ($answers[0] as $answer)
            {
                $variant = (string) $answer["variant"][0];
                $text = (string) $answer;
                $text = strip_tags($text);
                $question_obj->answers += [$text => $variant];
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


        if ($this->can_create_and_manage_activities_by_id(get_current_user_id()))
        {
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
                        'rewrite'     => array( 'slug' => 'gapfill' ),
                        'taxonomies'  => array( 'category' )
                )
            );

            wp_insert_term(
                'Master',  // Term name
                'category',         // Taxonomy
                array(
                    'description' => 'Source Activity',
                    'slug'        => 'master', 
                    'parent'      => 0 
                )
            );
        }
        else
        {
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
                    'rewrite'     => array( 'slug' => 'gapfill' ),
   
            )
        );
        }

        
      
    }



    public function save_activity_meta($post)
    {

        error_log("save_activity_meta called");

        $post_id = $post->ID;
        $author_id = get_post_field( 'post_author', $post_id );
        
        
        /*
            $author_first_name = get_the_author_meta("first_name", $author_id);
            $author_last_name = get_the_author_meta("last_name", $author_id);
            $author_name = $author_first_name + " " + $author_last_name;
        */
        $post_meta = get_post_meta($post_id); //from cache if poss or from db.
        $post_xml_meta = $post_meta["_kea_activity_meta"][0];
        if   (isset($post_meta["_kea_activity_type"]) ) {
                $activity_type = $post_meta["_kea_activity_type"][0];
        }
        else
        {
            $activity_type = "gapfill"; //historical reasons
        }

        if (empty($post_xml_meta))
        {
            return;
        }

        $post_with_key_meta = intval($post_meta["_with_key_meta"][0]);
        $post_without_key_meta = intval($post_meta["_without_key_meta"][0]); 
        $post_assignment_key_meta = intval($post_meta["_assignment_key_meta"][0]); 


        //array of term objs or false if none
        //as on the fe it would be nice to do this dynamically TODO
        $grammar_terms = get_the_terms($post, "grammar");
        $russian_grammar_terms = get_the_terms($post, "russian_grammar");
        $ages_bands_values = get_the_terms($post, "ages_bands");
        $levels_values = get_the_terms($post, "levels");

        
        $labels = array();
        $ages_bands = array();
        $levels = array();

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

        addTerm($ages_bands_values, $ages_bands);
        addTerm($levels_values, $levels);



        $json = $this->get_json_from_xml_string($post_xml_meta, false);
        $json->labels = $labels;

        $json->ages_bands = $ages_bands;
        $json->levels = $levels;
        
     
    
        $json_string = json_encode($json);
       

      

        
       //experimental
       update_post_meta($post_id, "_kea_activity_json", wp_slash($json_string));
       
        /*
        wp replace -> mysql replace
        REPLACE works exactly like INSERT, except that if an old row in the table has the same 
        value as a new row for a PRIMARY KEY or a UNIQUE index, the old row is deleted before the new 
        row is inserted. See Section 13.2.7, “INSERT Statement”.
        https://dev.mysql.com/doc/refman/8.0/en/replace.html 
        TODO maybe matter to use INSERT INTO UPDATE ON DUPLICATE though it looks like might have to build query manually for that
        */
                                

        $result = $this->wpdb->replace($this->kea_table_name1, array(
            'kea_activity_post_id' => $post_id, 
            'kea_activity_ex_type' => $activity_type, 
            'kea_activity_post_json' => $json_string, 
            'kea_activity_post_author_id' => $author_id, 
            'kea_activity_with_key_key' => $post_with_key_meta, 
            'kea_activity_without_key_key' => $post_without_key_meta,
            'kea_activity_assignment_key' => $post_assignment_key_meta,
            
        ), array( '%d', '%s', '%s', '%d', '%d' ,'%s', '%d', '%d')); 


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
        
        register_rest_route( 'kea_activities/v1', '/json_post2/(?P<slug>\S+)', array(
            'methods' => 'GET',
            'callback' => array($this, 'special_get_json_for_ad_hoc_projects_via_slug'),
            'permission_callback' => function () {
                return __return_true();
            } 
            
        ) );

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
            $ex_json->post_id = $post_id;
            
            $questions = $ex_json->questions;
            $questionsWithoutAnswers = array_map('lose_answers', $questions);
            $ex_json->questions = $questionsWithoutAnswers;
        }

        $ex_json->success = true;
        return $ex_json;
    }

    //always returns with answer key as we do not have a 'key' on the request from KEA
    public function special_get_json_for_ad_hoc_projects_via_slug($request)
    {
       
        $slug = $request['slug'];
       
 
        //this relies on post_name being unique though it is not enforfced in the DB!!!! TODO - a process to check
        $sql = $this->wpdb->prepare( "SELECT a.* FROM {$this->kea_table_name1} as a INNER JOIN {$this->posts_table} as p
            ON a.kea_activity_post_id = p.ID WHERE p.post_name = %s", $slug );

        $results = $this->wpdb->get_results( $sql ); //TODO handle error array of objects


        if (empty($results))
        {
            $result = new stdClass();
            $result->success = false;
            return $result;

        }

        $ex = $results[0];
        
        
        
        $ex_json = json_decode($ex->kea_activity_post_json);
  
        
    

        $ex_json->mode = "withkey";
        
    
        $ex_json->success = true;
        return $ex_json;
    }

    public function maybe_convert_xml_to_json2($xml_string)
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

    
    public function kea_activity_register_post_meta() {

        

        //todo - this should be called _kea_activity_xml
        register_post_meta( 'kea_activity', '_kea_activity_meta', array(
            'type'         => 'string',   
            'single'       => true,       
            'show_in_rest' => true, 
                'prepare_callback' => function ( $value ) {
                    $json = $this->maybe_convert_xml_to_json2($value); //i think this was for the student site on github which got its json from here? not sure used
                    return $json;
                },
            
            'auth_callback' => function() {
            return current_user_can( 'edit_published_posts' );

        } 
        )) ;



        register_post_meta( 'kea_activity', '_kea_activity_type', array(
            'type'         => 'string',   
            'single'       => true,       
            'show_in_rest' => true, 
            'auth_callback' => function() {
            return current_user_can( 'edit_published_posts' );
      
        } 
        ) );

        //this registers a meta field for this post type and also makes it show in rest
        register_post_meta( 'kea_activity', '_with_key_meta', array(
            'type'         => 'string',   
            'single'       => true,       
            'show_in_rest' => true, 
            'auth_callback' => function() {
            return current_user_can( 'edit_published_posts' ); //TODO check perms
        
        } 
        ) );


        //this registers a meta field for this post type and also makes it show in rest
        register_post_meta( 'kea_activity', '_without_key_meta', array(
            'type'         => 'string',   
            'single'       => true,       
            'show_in_rest' => true, 
            'auth_callback' => function() {
            return current_user_can( 'edit_published_posts' );

            } 
        ) ); 

        register_post_meta( 'kea_activity', '_assignment_key_meta', array(
            'type'         => 'string',   
            'single'       => true,       
            'show_in_rest' => true,  
            'auth_callback' => function() {
            return current_user_can( 'edit_posts' );

            } 
        ) );

        register_post_meta( 'kea_activity', 'test', array(
            'type'         => 'string',   
            'single'       => true,       
            'show_in_rest' => true,  
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
        add_option( 'kea_activity_db_version', $db_version );
        
        $charset_collate = $this->wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS $this->kea_table_name1 (
            kea_activity_id bigint NOT NULL AUTO_INCREMENT,
            kea_activity_post_id bigint NOT NULL,
            kea_activity_ex_type ENUM('gapfill', 'multiplechoice') NOT NULL,
            kea_activity_post_json text NOT NULL,
            kea_activity_post_author_id bigint NOT NULL,
            kea_activity_with_key_key bigint NOT NULL,
            kea_activity_without_key_key bigint NOT NULL,
            PRIMARY KEY  kea_activity_id (kea_activity_id),
            UNIQUE (kea_activity_post_id)
        ) $charset_collate;";
        dbDelta( $sql );

        $table = $this->kea_table_name1;

        if ( $this->wpdb->get_var("SHOW TABLES LIKE '$table'") != $this->kea_table_name1 ) {
            echo "ERROR. Table not created. Please contact support";
            die;
        }

        

        if (get_option('kea_activity_db_version') < 2.1)
        {//dbDelta does not work with IF NOT EXISTS - it handles this for you. /wp-admin/includes/upgrade.php : this will result in an ALTER table statement
            $sql_update_1 = "CREATE TABLE $this->kea_table_name1 (
                kea_activity_id bigint NOT NULL AUTO_INCREMENT,
                kea_activity_post_id bigint NOT NULL,
                kea_activity_ex_type ENUM('gapfill', 'multiplechoice') NOT NULL,
                kea_activity_post_json text NOT NULL,
                kea_activity_post_author_id bigint NOT NULL,
                kea_activity_with_key_key bigint NOT NULL,
                kea_activity_without_key_key bigint NOT NULL,
                kea_activity_assignment_key bigint NOT NULL DEFAULT 0,
                PRIMARY KEY  kea_activity_id (kea_activity_id),
                UNIQUE (kea_activity_post_id)
            ) $charset_collate;";
            $result = dbDelta( $sql_update_1 );
            update_option( 'kea_activity_db_version', 2.1);
        }
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
            array( 'wp-edit-blocks', 'wp-admin' ),
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

        wp_enqueue_script('settings', plugins_url( 'scripts/settings.js', __FILE__ ), array(), "1");

        $deps = $asset_file['dependencies'];
        $deps[] = 'settings';
      
        
        wp_register_script(
            'activity-script',
            plugins_url( 'build/index.js', __FILE__ ), 
            $deps,
            $asset_file['version']
        );

        //
        register_block_type( 'activities/activity-gap-fill', array(
            'api_version' => 2,
            'title' => 'Activity Gap Fill',
            'editor_style' => 'activity-editor',      
            'editor_script' => 'activity-script',
            'render_callback' => 'render_activity_gap_fill_block',
            'attributes' => array(
                'formData' => array('type' => 'object', 'default' => array()),
            )
        ) );

        function render_activity_gap_fill_block($attributes, $content) {
            // Return empty string - no frontend output
            return '';
            
            // OR process the data server-side when needed
            // return process_activity_data($attributes['formData']);
        }

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

    /*
        _kea_activity_type meta to appear as a column in posts lists in the admin panel
    */
    public function kea_activitiy_add_type_column($columns)
    {
        $new_columns = array_merge(array_slice($columns, 0, 2, true), array('activity_type' => __('Activity Type', 'kea')),
            array_slice($columns, 2, count($columns) - 1, true));
        return $new_columns;
    }

    public function kea_activity_populate_type_column($column, $post_id)
    {
           
            if ($column == 'activity_type')
            {
                $activity_type = get_post_meta($post_id, '_kea_activity_type', true);
                if (!empty($activity_type)) {
                    echo '<span>'; _e($activity_type, 'kea'); echo '</span>';
                }
            }
    
    }
           
    
}

$kea_activities2 = new KeaActivities();



?>