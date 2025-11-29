<?php 

function identify_json_table_data_with_no_questions()
{

    global $wpdb;
    $activities = $wpdb->get_results("
    SELECT a.kea_activity_post_id, a.kea_activity_ex_type, a.kea_activity_post_json, p.ID
    FROM {$wpdb->prefix}kea_activity a
    INNER JOIN {$wpdb->posts} p ON a.kea_activity_post_id = p.ID
    WHERE a.kea_activity_ex_type IN ('gapfill', 'mulitplechoice' )
");

    foreach ($activities as $activity) {

        
        $ids = [];
        $result = str_contains($activity->kea_activity_post_json, '"questions":[]');
        
        if ($result)
        {
            var_dump($activity->kea_activity_post_id);
        }
        


    }

}

function migrate_json_from_table_to__kea_activity_json()
{

    global $wpdb;
    
    // Get all activities that exist in both tables
    $activities = $wpdb->get_results("
        SELECT a.kea_activity_post_id, a.kea_activity_ex_type, a.kea_activity_post_json, p.ID
        FROM {$wpdb->prefix}kea_activity a
        INNER JOIN {$wpdb->posts} p ON a.kea_activity_post_id = p.ID
        WHERE a.kea_activity_ex_type IN ('gapfill', 'mulitplechoice' )
    ");
    
    if (empty($activities)) {
        error_log("No activities found to migrate activity types");
        return;
    }
    
    $migrated_count = 0;
    $error_count = 0;
    
    foreach ($activities as $activity) {
        try {
            // Update post meta with the activity type
            $result = update_post_meta(
                $activity->ID, 
                '_kea_activity_json', 
                $activity->kea_activity_post_json
            );
            
            if ($result !== false) {
                $migrated_count++;
                error_log("Successfully set _kea_activity_json for post ID: {$activity->ID} ");
            } else {
                throw new Exception("Failed to update post meta for post ID: {$activity->ID}");
            }
            
        } catch (Exception $e) {
            $error_count++;
            error_log("Migration error for post ID {$activity->ID}: " . $e->getMessage());
        }
    }
    
    error_log("Activity type migration completed: {$migrated_count} successful, {$error_count} errors");
    
    return [
        'success' => $migrated_count,
        'errors' => $error_count
    ];

}





function migrate_kea_activity_types_to_post_meta() {
    global $wpdb;
    
    // Get all activities that exist in both tables
    $activities = $wpdb->get_results("
        SELECT a.kea_activity_post_id, a.kea_activity_ex_type, p.ID
        FROM {$wpdb->prefix}kea_activity a
        INNER JOIN {$wpdb->posts} p ON a.kea_activity_post_id = p.ID
        WHERE a.kea_activity_ex_type IN ('gapfill', 'multiplechoice')
    ");
    
    if (empty($activities)) {
        error_log("No activities found to migrate activity types");
        return;
    }
    
    $migrated_count = 0;
    $error_count = 0;
    
    foreach ($activities as $activity) {
        try {
            // Update post meta with the activity type
            $result = update_post_meta(
                $activity->ID, 
                '_kea_activity_type', 
                $activity->kea_activity_ex_type
            );
            
            if ($result !== false) {
                $migrated_count++;
                error_log("Successfully set post meta for post ID: {$activity->ID} with type: {$activity->kea_activity_ex_type}");
            } else {
                throw new Exception("Failed to update post meta for post ID: {$activity->ID}");
            }
            
        } catch (Exception $e) {
            $error_count++;
            error_log("Migration error for post ID {$activity->ID}: " . $e->getMessage());
        }
    }
    
    error_log("Activity type migration completed: {$migrated_count} successful, {$error_count} errors");
    
    return [
        'success' => $migrated_count,
        'errors' => $error_count
    ];
}

// Run the migration
// migrate_kea_activity_types_to_post_meta();

function migrate_kea_activities_to_blocks_multiplechoice() {

    global $wpdb;
    $activities = $wpdb->get_results("
    SELECT a.kea_activity_post_id, a.kea_activity_post_json, p.post_content, p.ID
    FROM {$wpdb->prefix}kea_activity a
    INNER JOIN {$wpdb->posts} p ON a.kea_activity_post_id = p.ID 
    WHERE a.kea_activity_post_json != '' AND a.kea_activity_ex_type = 'multiplechoice' ");

    if (empty($activities)) {
        error_log("No activities found to migrate");
        return;
    }

    //AND p.ID = 4424

    $migrated_count = 0;
    $error_count = 0;

    foreach ($activities as $activity) 
    {
        try {
            $source_data = json_decode($activity->kea_activity_post_json, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new Exception('Invalid JSON source: ' . json_last_error_msg());
            }
            
            $block_attributes = [
                'activityType' => $source_data['activity_type'] ?? 'multiplechoice',
                'formData' => [
                    'set' => true,
                    'legacyName' => $source_data['legacy_name'] ?? '',
                    'models' => $source_data['models'] ?? '',
                    'explanation' => $source_data['explanation'] ?? '',
                    'questions' => [],
                    'instructions' => []
                ]
            ];
            
            // Convert questions - extract just the answer keys in array format
            if (isset($source_data['questions']) && is_array($source_data['questions'])) {
                foreach ($source_data['questions'] as $question) {
                    $converted_question = [
                        'question' => str_replace('"', '\"', $question['question'] ?? ''),
                        'answers' => []
                    ];
                    
                    // Extract answer keys (A, B, C, D) into a simple array - this assumes the array order has been maintained even though it was an object
                    //check this when we anually review exercises 
                    if (isset($question['answers']) && is_array($question['answers'])) {
                        $converted_question['answers'] = array_keys($question['answers']);
                        foreach ($converted_question['answers'] as &$ans)
                        {
                            $ans = str_replace('"', '\"', $ans);
                        }
                    }
                    
                    $block_attributes['formData']['questions'][] = $converted_question;
                }
            }
            
            // Convert instructions from object to array format
            if (isset($source_data['instructions']) && is_array($source_data['instructions'])) {
                foreach ($source_data['instructions'] as $lang => $text) {
                    $block_attributes['formData']['instructions'][] = [
                        'lang' => $lang,
                        'text' => $text
                    ];
                }
            }

            
            
            // Create the new block content
            $block_json = json_encode($block_attributes, JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE);
            $new_content = "<!-- wp:activities/activity-multiple-choice {$block_json} -->\n\n<!-- /wp:activities/activity-multiple-choice -->";
            
            // Update the post
            $result = wp_update_post([
                'ID' => $activity->ID,
                'post_content' => $new_content
            ]);
            
            if ($result && !is_wp_error($result)) {
                $migrated_count++;
                error_log("Successfully migrated post ID: {$activity->ID}");
            } else {
                throw new Exception("Failed to update post ID: {$activity->ID}");
            }
            

            
          
        }    catch (Exception $e) {
        $error_count++;
        error_log("Migration error for post ID {$activity->ID}: " . $e->getMessage());
        }



    }

    error_log("Migration completed: {$migrated_count} successful, {$error_count} errors");
}

function migrate_kea_activities_to_blocks_gapfill() {
    global $wpdb;
    error_log("fix up");
    // Get all activities that exist in both tables
    $activities = $wpdb->get_results("
        SELECT a.kea_activity_post_id, a.kea_activity_post_json, p.post_content, p.ID
        FROM {$wpdb->prefix}kea_activity a
        INNER JOIN {$wpdb->posts} p ON a.kea_activity_post_id = p.ID 
        WHERE a.kea_activity_post_json != '' AND a.kea_activity_ex_type = 'gapfill' 
    ");
    //A
    
    if (empty($activities)) {
        error_log("No activities found to migrate");
        return;
    }
    
    $migrated_count = 0;
    $error_count = 0;
    
    foreach ($activities as $activity) {




        try {
            // Decode the source JSON
            $source_data = json_decode($activity->kea_activity_post_json, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new Exception("Invalid JSON for post ID: {$activity->ID}");
            }
            
            // Build the new block attributes
            $block_attributes = [
                'activityType' => $source_data['activity_type'] ?? 'gapfill',
                'formData' => [
                    'set' => true,
                    'legacyName' => $source_data['legacy_name'] ?? '',
                    'models' => $source_data['models'] ?? '',
                    'explanation' => $source_data['explanation'] ?? '',
                    'questions' => [],
                    'instructions' => []
                ]
            ];
            
            if (!empty($source_data['questions']) && is_array($source_data['questions'])) {
                $question_number = 1;
                foreach ($source_data['questions'] as $question) {
                    $block_attributes['formData']['questions'][] = [
                        'question' => str_replace('"', '\"', $question['question'] ?? ''),
                        'answer' => str_replace('"', '\"', $question['answer'] ?? ''),
                        'questionNumber' => (string)$question_number
                    ];
                    $question_number++;
                }
            }
            
            // Transform instructions
            if (!empty($source_data['instructions']) && is_array($source_data['instructions'])) {
                $block_attributes['formData']['instructions'] = [
                    [
                        'lang' => 'en',
                        'text' => $source_data['instructions']['en'] ?? ''
                    ],
                    [
                        'lang' => 'ru',
                        'text' => $source_data['instructions']['ru'] ?? ''
                    ]
                ];
            }
            
     
            
            // Create the new block content
            $block_json = json_encode($block_attributes, JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE);
            $new_content = "<!-- wp:activities/activity-gap-fill {$block_json} -->\n\n<!-- /wp:activities/activity-gap-fill -->";
            
            // Update the post
            $result = wp_update_post([
                'ID' => $activity->ID,
                'post_content' => $new_content
            ]);
            
            if ($result && !is_wp_error($result)) {
                $migrated_count++;
                error_log("Successfully migrated post ID: {$activity->ID}");
            } else {
                throw new Exception("Failed to update post ID: {$activity->ID}");
            }
            
        } catch (Exception $e) {
            $error_count++;
            error_log("Migration error for post ID {$activity->ID}: " . $e->getMessage());
        }
    }
    
    error_log("Migration completed: {$migrated_count} successful, {$error_count} errors");
}

// Run the migration (you can call this function where needed)
// migrate_kea_activities_to_blocks();

    function get_json_from_xml_gapfill($xml_string)
    {


        $xml = new SimpleXMLElement($xml_string);
        $activity_type = (string) $xml['type'];

        error_log("TTTTTTTTTTTTTTTTTTTTTTTTTTTTTTTTTTTTTTTTTTTTTTTTTTTTTTTTTYPE");
        error_log($activity_type );

    
        if ($activity_type != 'activity_gap_fill')
        {
            return null;
        }
        
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

    
            return json_encode($json_obj);
        
    
    
    }

    function migrate_old_xml_key_to_new_xml_key()
    {

        /*
        INSERT INTO aguc4I_postmeta (post_id, meta_key, meta_value)
SELECT post_id, '_kea_activity_xml', meta_value
FROM aguc4I_postmeta
WHERE meta_key = '_kea_activity_meta'
AND post_id IN (
    SELECT ID 
    FROM aguc4I_posts 
    WHERE post_type = 'kea_activity'
);

DELETE FROM aguc4I_postmeta 
WHERE meta_key = '_kea_activity_meta'
AND post_id IN (
    SELECT ID 
    FROM aguc4I_posts 
    WHERE post_type = 'kea_activity'
);


*/

    }

    //this probably wiped out a lot of the json ??? but didn't run on test... but, if there was no xml data or void, this would have resulted in
    //the empty json 
    //? if opened an ex with new code before migration that would have wiped the json table data
    function migrate_xml_activities_to_kea_tableXXX() {
        global $wpdb;
        
        // Get posts that have _kea_activity_meta but aren't in kea_activity table yet
        $posts_to_migrate = $wpdb->get_results("
            SELECT 
                p.ID as post_id,
                p.post_author,
                meta_activity.meta_value as activity_xml,
                meta_with_key.meta_value as with_key,
                meta_without_key.meta_value as without_key,
                meta_assignment.meta_value as assignment_key
            FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} meta_activity 
                ON p.ID = meta_activity.post_id 
                AND meta_activity.meta_key = '_kea_activity_meta'
            LEFT JOIN {$wpdb->postmeta} meta_with_key 
                ON p.ID = meta_with_key.post_id 
                AND meta_with_key.meta_key = '_with_key_meta'
            LEFT JOIN {$wpdb->postmeta} meta_without_key 
                ON p.ID = meta_without_key.post_id 
                AND meta_without_key.meta_key = '_without_key_meta'
            LEFT JOIN {$wpdb->postmeta} meta_assignment 
                ON p.ID = meta_assignment.post_id 
     
                WHERE ka.kea_activity_post_id IS NULL
                AND p.post_status != 'trash'
        ");
        
        if (empty($posts_to_migrate)) {
            error_log("No XML activities found to migrate");
            return;
        }
        
        $migrated_count = 0;
        $error_count = 0;
        
        foreach ($posts_to_migrate as $post) {
            try {
                // Convert XML to JSON using your function
                $json_data = get_json_from_xml_gapfill($post->activity_xml);


                error_log("---------------------------------------------------------");
                error_log($json_data);
                error_log("---------------------------------------------------------");
                
                // Skip if conversion returned null
                if ($json_data === null) {
                    error_log("Skipping post ID {$post->post_id}: XML conversion returned null");
                    continue;
                }






                
                // Validate that we got valid JSON
                if (json_decode($json_data) === null && json_last_error() !== JSON_ERROR_NONE) {
                    throw new Exception("Invalid JSON returned from XML conversion for post ID: {$post->post_id}");
                }
                
                // Insert into kea_activity table
                $result = $wpdb->replace(
                    $wpdb->prefix . 'kea_activity',
                    [
                        'kea_activity_post_id' => $post->post_id,
                        'kea_activity_ex_type' => 'gapfill',
                        'kea_activity_post_json' => $json_data,
                        'kea_activity_post_author_id' => $post->post_author,
                        'kea_activity_with_key_key' => $post->with_key ?: 0,
                        'kea_activity_without_key_key' => $post->without_key ?: 0,
                        'kea_activity_assignment_key' => $post->assignment_key ?: 0
                    ],
                    [
                        '%d', // post_id
                        '%s', // ex_type
                        '%s', // post_json
                        '%d', // author_id
                        '%d', // with_key
                        '%d', // without_key
                        '%d'  // assignment_key
                    ]
                );
                
                if ($result !== false) {
                    $migrated_count++;
                    error_log("Successfully migrated XML activity for post ID: {$post->post_id}");
                } else {
                    throw new Exception("Database insert failed for post ID: {$post->post_id}. Error: " . $wpdb->last_error);
                }
                
            } catch (Exception $e) {
                $error_count++;
                error_log("Migration error for post ID {$post->post_id}: " . $e->getMessage());
            }
        }
        
        error_log("XML migration completed: {$migrated_count} successful, {$error_count} errors");
        
        return [
            'migrated' => $migrated_count,
            'errors' => $error_count,
            'total_processed' => count($posts_to_migrate)
        ];
    }
    
    // Run the migration
    // migrate_xml_activities_to_kea_table();

    function fix_up()
    {
    
        if (isset($_GET['fixup']))
        {
            if ($_GET['fixup'] == 1)
            {
                //migrate_kea_activities_to_blocks_gapfill();
            }
    
            if ($_GET['fixup'] == 2)
            {
                //migrate_kea_activity_types_to_post_meta();
            }
    
            if ($_GET['fixup'] == 3)
            {
                //migrate_xml_activities_to_kea_table();
            }
    
            if ($_GET['fixup'] == 4)
            {
                //migrate_kea_activities_to_blocks_multiplechoice();
            }

            if ($_GET['fixup'] == 5)
            {
               
               //migrate_kea_activities_to_blocks_gapfill();
               //migrate_kea_activities_to_blocks_multiplechoice();
               //migrate_old_xml_key_to_new_xml_key();
              // migrate_kea_activity_types_to_post_meta();
              //  migrate_json_from_table_to__kea_activity_json();
              //identify_json_table_data_with_no_questions();

            }

            
    
            
        }
    
    }
    fix_up();
?>