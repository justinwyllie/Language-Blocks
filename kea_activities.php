<?php
/*
Plugin Name: KEA_Activities
Plugin URI: https://kazanenglishacademy.com/plugins
Description: Plugin to register all the exercise post types and manage them
Version: 1.0
Author: Justin Wyllie
Author URI:https://kazanenglishacademy.com/plugins
Textdomain: kea
License: copyright Justin Wyllie
*/



//include_once('migration.php');





class KeaActivities
{
    private $caps;
    public $support_email;
    public $support_email_subject;
    public $wpdb;
    public $kea_table_name1;
    public $posts_table;
   
    public function __construct() 
    {

        if (isset( $_SERVER['SERVER_NAME']))
        {
            $server = $_SERVER['SERVER_NAME'];
        }
        else
        {
            $server = '-';
        }

        $this->support_email = SUPPORT_EMAIL;
        $this->support_email_subject = "Error on web site " . $server . " in script " . basename(__FILE__); //shared with kea-repi TODO
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

        add_action( 'admin_enqueue_scripts', array($this, 'register_plugin_scripts' ));


        add_filter('pre_get_posts', array($this, 'limit_posts_for_current_author'));

        
        add_filter('rest_pre_insert_kea_activity', array($this, 'validate_activity_title_length'), 10, 2);


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

    //TODO shared with kea_repi TODO - use PHPMailer to control all headers this will probaby fail 
    public function mail_error($message)
    {


        $no_reply = DO_NOT_REPLY_EMAIL;

        $headers = "From: $no_reply" . "\r\n" .
                    "Reply-To: $no_reply" . "\r\n" .
                'X-Mailer: PHP/' . phpversion();
        mail($this->support_email, $this->support_email_subject, $message, $headers);
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

    public function register_plugin_scripts()
    {
      
        if (isset($_GET['page']) && $_GET['page'] === 'communication_settings') {
            wp_register_script( 'bootstrap-js', plugins_url('/assets/bootstrap.min.js', __FILE__ ), array(  ), time()); 
            wp_enqueue_script( 'bootstrap-js' );
        }
       
    }



    public function validate_activity_title_length($prepared_post, $request) {
        $min_length = POST_TITLE_LENGTH; // Set your desired maximum length

        if (isset($prepared_post->post_status) && ($prepared_post->post_status  == 'draft'))
        {
            return $prepared_post;
        }
        
        if (isset($prepared_post->post_title) && strlen($prepared_post->post_title) < $min_length) {

            return new WP_Error(
                'rest_title_too_short',
                sprintf(__('Post title cannot be less than %d characters'), $min_length, strlen($prepared_post->post_title)),
                array('status' => 400)
            );
        }
   
        return $prepared_post;
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


    public function kea_activity_custom_post_type() {


        if ($this->can_create_and_manage_activities_by_id(get_current_user_id()))
        {
            register_post_type('kea_activity',
                array(
                    'labels'      => array(
                        'name'          => __('Задания', 'kea'),
                        'singular_name' => __('Activities', 'kea'),
                        'menu_name' => 'Задания', 
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
                    'name'          => __('Задания', 'kea'),
                    'singular_name' => __('Activities', 'kea'),
               
                    'menu_name' => 'Задания', 
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



    private function convert_gapfill_form_data_to_json($form_data)
    {
        
        $processed_json = new StdClass();

        $processed_json->activity_type = $form_data['activity_type'];
        $processed_json->legacy_name = $form_data['legacyName']; //TODO fix in client side
        $processed_json->title = $form_data['title'];
        $processed_json->models = $form_data['models'];
        $processed_json->explanation = $form_data['explanation'];

        $processed_json->instructions = new StdClass();

        foreach ($form_data['instructions'] as $instruction) {
            $lang = $instruction['lang'];
            $text = $instruction['text'];
            $processed_json->instructions->$lang = $text;
        }

        $processed_json->questions = [];
        foreach ($form_data['questions'] as $index => $question)
        {
            $question['questionNumber'] = (string) ($index + 1);
            $processed_json->questions[] = (object) $question;
        }
        
        return $processed_json;
    }

    private function convert_multiplechoice_form_data_to_json($form_data)
    {

        $processed_json = new StdClass();

        $processed_json->activity_type = $form_data['activity_type'];
        $processed_json->legacy_name = $form_data['legacyName']; //TODO fix in client side
        $processed_json->title = $form_data['title'];
        $processed_json->models = $form_data['models'];
        $processed_json->explanation = $form_data['explanation'];

        $processed_json->instructions = new StdClass();

        foreach ($form_data['instructions'] as $instruction) {
            $lang = $instruction['lang'];
            $text = $instruction['text'];
            $processed_json->instructions->$lang = $text;
        }


        $processed_json->questions = [];

        if (isset($form_data['questions']) && is_array($form_data['questions'])) {
            $question_number = 1;
            foreach ($form_data['questions'] as $question) {
                if (isset($question['question']) && isset($question['answers']) && is_array($question['answers'])) {
                    $new_question = new stdClass(); 

                    $new_question->question = $question['question'];
                    $new_question->questionNumber = $question_number;
                    $question_number++;
                    $new_question->answers = new stdClass();
                    foreach ($question['answers'] as $index => $answer) {
                        $new_question->answers->{$answer} = ($index === 0) ? 'correct' : 'incorrect';
                    }
                    $processed_json->questions[] = $new_question;
                }
            }
        }

        return $processed_json;
    
    }

    private function convert_gapfill_form_data_to_xml($form_data)
    {
        
        $xmlDoc = new DOMDocument('1.0', 'UTF-8');
        $xmlDoc->formatOutput = true;
        
        $rootNode = $xmlDoc->createElement('activity');
        $rootNode->setAttribute('type', 'gapfill');
        $xmlDoc->appendChild($rootNode);

        $legacyNameNode = $xmlDoc->createElement('legacyName', $form_data['legacyName']);
        $rootNode->appendChild($legacyNameNode);

        $titleNode = $xmlDoc->createElement('title', $form_data['title']);
        $rootNode->appendChild($titleNode);
        
        $modelsNode = $xmlDoc->createElement('models', $form_data['models']);
        $rootNode->appendChild($modelsNode);
        
        $explanationNode = $xmlDoc->createElement('explanation', $form_data['explanation']);
        $rootNode->appendChild($explanationNode);
        
        $instructionsNode = $xmlDoc->createElement('instructions');
        foreach ($form_data['instructions'] as $item) {
            $iNode = $xmlDoc->createElement('instruction');
            $iNode->setAttribute('lang', $item['lang']);
            $iNode->appendChild($xmlDoc->createTextNode($item['text']));
            $instructionsNode->appendChild($iNode);
        }
        $rootNode->appendChild($instructionsNode);
        $questionsNode = $xmlDoc->createElement('questions');
        foreach ($form_data['questions'] as $i => $item) {
            $qNode = $xmlDoc->createElement('q' . $i);
            $qNode->setAttribute('questionNumber', (string)($i + 1));
            $qNode->setAttribute('answer', rtrim($item['answer']));
            $qNode->appendChild($xmlDoc->createTextNode($item['question']));
            $questionsNode->appendChild($qNode);
        }
        $rootNode->appendChild($questionsNode);
        
        return $xmlDoc->saveXML();

    }

    private function convert_multiplechoice_form_data_to_xml($form_data)
    {
        $xmlDoc = new DOMDocument('1.0', 'UTF-8');
        $xmlDoc->formatOutput = true;
        
        $rootNode = $xmlDoc->createElement('activity');
        $rootNode->setAttribute('type', 'multiplechoice');
        $xmlDoc->appendChild($rootNode);

        $legacyNameNode = $xmlDoc->createElement('legacyName', $form_data['legacyName']);
        $rootNode->appendChild($legacyNameNode);

        $titleNode = $xmlDoc->createElement('title', $form_data['title']);
        $rootNode->appendChild($titleNode);
        
        $modelsNode = $xmlDoc->createElement('models', $form_data['models']);
        $rootNode->appendChild($modelsNode);
        
        $explanationNode = $xmlDoc->createElement('explanation', $form_data['explanation']);
        $rootNode->appendChild($explanationNode);
        
        $instructionsNode = $xmlDoc->createElement('instructions');
        foreach ($form_data['instructions'] as $item) {
            $iNode = $xmlDoc->createElement('instruction');
            $iNode->setAttribute('lang', $item['lang']);
            $iNode->appendChild($xmlDoc->createTextNode($item['text']));
            $instructionsNode->appendChild($iNode);
        }
        $rootNode->appendChild($instructionsNode);
        
        $questionsNode = $xmlDoc->createElement('questions');

        foreach ($form_data['questions'] as $i => $item) {
            $qNode = $xmlDoc->createElement('q' . $i);
            $qNode->setAttribute('questionNumber', (string)($i + 1));
            $question = $xmlDoc->createElement('question', $item['question']);
            $answers = $xmlDoc->createElement('answers');
            foreach($item['answers'] as $z => $answer)
            {
                $answerNode = $xmlDoc->createElement('answer', $answer);
                if ($z === 0)
                {
                    $answerNode->setAttribute('variant', 'correct');
                }
                else
                {
                    $answerNode->setAttribute('variant', 'incorrect');
                }
                $answers->appendChild($answerNode);

            }
            $qNode->appendChild($question);
            $qNode->appendChild($answers);
            $questionsNode->appendChild($qNode);
        }
        $rootNode->appendChild($questionsNode);
        
        return $xmlDoc->saveXML();
    }

    private function convert_form_data($form_data)
    {
        $type = $form_data['activity_type'];
        $converters = [
            'gapfill' => [
                'xml' => 'convert_gapfill_form_data_to_xml',
                'json' => 'convert_gapfill_form_data_to_json'
            ],
            'multiplechoice' => [
                'xml' => 'convert_multiplechoice_form_data_to_xml', 
                'json' => 'convert_multiplechoice_form_data_to_json'
            ]
        ];
        
        if (isset($converters[$type])) {
            $xml = $this->{$converters[$type]['xml']}($form_data);
            $json = $this->{$converters[$type]['json']}($form_data);
            return ['xml' => $xml, 'json' => $json];
        } else {
            return false;
        }
    }

    //after post save update the xml meta and the json meta and the xml meta and, for now, the json table (will be deleted. probably)
    //if this falis then we have a post without the json meta (the key one)
    //TODO this puts activity_type into a meta field _kea_activity_type, the json table and the json meta - which is considered authoriative in the node?!
    //it looks like we get it off the json from the table but if not there from the field in the json table.
    //note that the teacher side gets it from the meta value
    public function save_activity_meta($post)
    {
      
        $post_content = $post->post_content;
        $post_id = $post->ID;
        $blocks = parse_blocks($post_content);
        if (count($blocks) < 1)
        {
            return;
        }

        //activity_type - json, json table, attirbutes, and 
        $block = $blocks[0];
       
        if ($block['blockName'] === 'activities/activity-gap-fill' || $block['blockName'] === 'activities/activity-multiple-choice') {

            $attributes = $block['attrs']; // Direct access to attributes array
            $form_data = $attributes['formData'] ?? null;
            $activity_type = $attributes['activityType'];
            //TODO - do we need to do this again?
            $activity_type_result = update_post_meta($post_id, '_kea_activity_type', $activity_type);
            
            
            if (!empty($form_data)) {

                $form_data['title'] = $post->post_title;
                $form_data['activity_type'] = $activity_type;
                
                $formatted_data = $this->convert_form_data($form_data);
 
            }
            else
            {
                $this->mail_error("In save_activity_meta it seems after an activity was saved the additional save of meta and json did not happen. $post_id");
                 return;
            }
        }
        else
        {
            return;
        }
    


      
        $author_id = get_post_field( 'post_author', $post_id );
        $post_meta = get_post_meta($post_id); 

        $xml_result = update_post_meta($post_id, '_kea_activity_xml', $formatted_data['xml']);
        if ($xml_result === false)
        {
            $this->mail_error("In save_activity_meta the additional save of xml meta  did not happen or was the same!. $post_id");
        }

        $post_with_key_meta = intval($post_meta["_with_key_meta"][0]);
        $post_without_key_meta = intval($post_meta["_without_key_meta"][0]); 
        $post_assignment_key_meta = intval($post_meta["_assignment_key_meta"][0]); 



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

        $new_json = $formatted_data['json'];
        $new_json->labels = $labels;
        $new_json->ages_bands = $ages_bands;
        $new_json->levels = $levels;
        $json_string = json_encode($new_json);

        
       //attributes is just the form, without taxonomy. so - could get it all with a query,
       //but this is so we can get form-data+post-taxonomy in one simple query in node
        $json_result = update_post_meta($post_id, "_kea_activity_json", wp_slash($json_string)); //wp_slash to doube slash to overcome db unslash
        if ($json_result === false)
        {
            $this->mail_error("In save_activity_meta the additional save of json meta  did not happen or was the same!. $post_id");
        }
       
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
            $this->mail_error("In save_activity_meta the additional save of json to db did not happen [2]. $post_id");
            return;
            
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



    
    public function kea_activity_register_post_meta() {

        

        //todo - this is historical - now we use _kea_activity_xml but prior to Nov 25 we used this so historical data has this key
        register_post_meta( 'kea_activity', '_kea_activity_meta', array(
            'type'         => 'string',   
            'single'       => true,       
            'show_in_rest' => false, 
            'auth_callback' => function() {
            return current_user_can( 'edit_published_posts' );

        } 
        )) ;

        register_post_meta( 'kea_activity', '_kea_activity_xml', array(
            'type'         => 'string',   
            'single'       => true,       
            'show_in_rest' => false, 
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

        //TODO be careful with the dependency here if we decide to load 'activity-editor' only on admin side
        //also if we don't need this dependency i.e. bootstrap CSS we could move this to the theme where it belongs 
        //TODO - take the CSS which is in the file and put it in the custom.scss file - it can be part of the build.
        wp_enqueue_style("splash", plugins_url( '/assets/styles.css', __FILE__ ), array('activity-editor'), filemtime( plugin_dir_path( __FILE__ ) . 'assets/styles.css'));
     
      
  

        wp_enqueue_script('settings', plugins_url( 'scripts/settings.js', __FILE__ ), array(), filemtime( plugin_dir_path( __FILE__ ) . '/scripts/settings.js'));

        wp_localize_script('settings', 'kea_language_blocks', array(
            'settings' => array(
                'domain' => array(
                    'type' => 'slashes',
                    'domainForUsers' => DOMAIN_FOR_USERS,
                ),
                'site' => 'repititor',
                'defaultUserLang' => 'en',
                'page_title_length' => POST_TITLE_LENGTH
            ),
            'SHOWLOGIN' => true
        ));

        $deps = $asset_file['dependencies'];
        $deps[] = 'settings';

    
      
        
        wp_register_script(
            'activity-script',
            plugins_url( 'build/index.js', __FILE__ ), 
            $deps,
            $asset_file['version'],
            time(),
       
    
      
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
                'activityType' => array('type' => 'string')
            )
        ) );

        //TODO i think we can remove this
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
            'attributes' => array(
                'formData' => array('type' => 'object', 'default' => array()),
                'activityType' => array('type' => 'string')
            )
        ) );


        
        function render_simple_breadcrumb_block($attributes, $content) {
          
            $wrapper_attributes = get_block_wrapper_attributes();
    
            $output = '<div ' . $wrapper_attributes . '>';
            $output .= '<nav aria-label="breadcrumb">';
            $output .= '<ol class="breadcrumb">';
            $output .= '<li class="breadcrumb-item"><a href="' . esc_url(home_url('/')) . '">Home</a></li>';
            $output .= '<li class="breadcrumb-item active" aria-current="page">' . get_the_title() . '</li>';
            $output .= '</ol></nav>';
            $output .= '</div>';
            
            return $output;
        }

        register_block_type( 'activities/breadcrumbs', array(
            'api_version' => 2,
            'title' => 'Breadcrumbs (top-level only)',
            'editor_style' => 'activity-editor',      
            'editor_script' => 'activity-script',
            'render_callback' => 'render_simple_breadcrumb_block',
            'attributes' => array(
               
            )
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