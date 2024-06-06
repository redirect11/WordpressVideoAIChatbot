<?php

use Orhanerday\OpenAi\OpenAi;
        
class Video_Ai_OpenAi {
    private $client;


    /**
	 * Initialize the class and set its properties.
	 *
	 * @since    1.0.0
	 * @param      string    $plugin_name       The name of this plugin.
	 * @param      string    $version    The version of this plugin.
	 */
	public function __construct() {
        $api_key = get_option('video_ai_chatbot_options')['openai_api_key_field'];
		if ($api_key) {
            $this->activate($api_key);
		}
	}

    public function is_active() {
        return isset($this->client);
    }
    
    public function register_api_hooks() {
        // Registra il nuovo endpoint per ottenere gli assistenti
        register_rest_route('video-ai-chatbot/v1', '/assistants/', [
            'methods' => 'GET',
            'callback' => [$this, 'get_assistants'],
            'permission_callback' => '__return_true'
        ]);

        // Endpoint per invio messaggi con verifica della sessione
        register_rest_route('video-ai-chatbot/v1', '/chatbot/', [
            'methods' => 'POST',
            'callback' => [$this, 'handle_chatbot_request'],
            'permission_callback' => '__return_true'
        ]);

        register_rest_route('video-ai-chatbot/v1', '/create-assistant/', [
            'methods' => 'POST',
            'callback' => [$this, 'create_assistant'],
            'permission_callback' => '__return_true'
        ]);

        register_rest_route('video-ai-chatbot/v1', '/update-assistant/', [
            'methods' => 'POST',
            'callback' => [$this, 'update_assistant'],
            'permission_callback' => '__return_true'
        ]);

        register_rest_route('video-ai-chatbot/v1', '/upload-transcription/', [
            'methods' => 'POST',
            'callback' => [$this, 'upload_transcription'],
            'permission_callback' => '__return_true'
        ]);

        register_rest_route('myplugin/v1', '/delete-transcription/(?P<file_id>[a-zA-Z0-9_-]+)', [
            'methods' => 'DELETE',
            'callback' => [$this, 'delete_transcription_request'],
            'permission_callback' => '__return_true'
        ]);

        register_rest_route('video-ai-chatbot/v1', '/sync-transcriptions/', [
            'methods' => 'POST',
            'callback' => [$this, 'sync_transcriptions'],
            'permission_callback' => '__return_true'
        ]);
    }

    public function activate($apiKey)
    {
        if(!$this->is_active() && $apiKey) {
            $this->client = new OpenAi($apiKey);
        }
    }

    public function update_assistant(WP_REST_Request $request) {
        $parameters = $request->get_json_params();
        $id = sanitize_text_field($parameters['id']);
        $name = sanitize_text_field($parameters['name']);
        $prompt = sanitize_textarea_field($parameters['prompt']);
        $data = [
            'name' => $name,
            'instructions' => $prompt,
            'model' => 'gpt-3.5-turbo',
        ];

        try {
            $response = $this->client->modifyAssistant($id, $data);
            $decoded_res = json_decode($response);
            if(isset($decoded_res->error))
            {
                throw new Exception($decoded_res->error->message);
            } 
            return new WP_REST_Response(['message' => 'Assistant updated successfully', 'assistants' => $this->get_assistants()], 200);
        } catch (Exception $e) {
            return new WP_REST_Response(['message' => $e->getMessage()], 500);
        }
    }

    public function delete_transcription_request(WP_REST_Request $request) {
        $file_id = sanitize_text_field($request->get_param('file_id'));
        // Eliminare il file da OpenAI
        try {
            $response = $this->delete_transcription($file_id);
        } catch (Exception $e) {
            return new WP_REST_Response(['message' => 'File deleted locally but failed to delete from OpenAI: ' . $e->getMessage()], 500);
        }

        return new WP_REST_Response(['message' => 'Transcription deleted successfully'], 200);
    }

    private function delete_transcription($file_id) {
        $option_name = 'video_ai_chatbot_transcriptions';
        $transcriptions = get_option($option_name, []);

        $updated_transcriptions = array_filter($transcriptions, function($transcription) use ($file_id) {
            return $transcription['file_id'] !== $file_id;
        });
        
        if (count($transcriptions) === count($updated_transcriptions)) {
            return new WP_REST_Response(['message' => 'File ID not found'], 404);
        }

        update_option($option_name, array_values($updated_transcriptions));
        return $this->client->deleteFile($file_id);
    }

    public function upload_transcription(WP_REST_Request $request) {
        if (empty($_FILES['file']['tmp_name'])) {
            return new WP_REST_Response(['message' => 'No file uploaded'], 400);
        }
    
        $file = $_FILES['file'];
        $file_content = file_get_contents($file['tmp_name']);
        $transcription = json_decode($file_content, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            return new WP_REST_Response(['message' => 'Invalid JSON file'], 400);
        }
    
        $tmp_file = $file['tmp_name'];
        $file_name = basename($_FILES['file']['name']);
        $c_file = curl_file_create($tmp_file, $_FILES['file']['type'], $file_name);
    
        // Upload the file to OpenAI
        try {
            $response = $this->client->uploadFile([
                'file' => $c_file,
                'purpose' => 'assistants'
            ]);
            
            $response = json_decode($response, true);

            if(isset($response['error'])) {
                throw new Exception($response['error']['message']);
            }

            $file_id = $response['id'];
    
            // Save the transcription and file_id as an option
            $option_name = 'video_ai_chatbot_transcriptions';
            $transcriptions = get_option($option_name, []);
            $transcriptions[] = [
                'file_name' => $file_name,
                'file_id' => $file_id,
                'assistant_id' => $transcription['assistant_id'],
                'assistant_name' => $transcription['assistant_name'],
                'transcription' => $transcription['transcription']
            ];
            update_option($option_name, $transcriptions);

            echo '<h1> Assistant ID: '. var_dump($transcription['assistant_id']) . '</h1>';
            echo '<h1> file_id: '. $file_id . '</h1>';
            if($transcription['assistant_id'] && $file_id) {
                foreach($transcription['assistant_id'] as $assistant_id) {
                    echo '<h1> SONO DENTRO </h1>';
                    $file = $this->client->createAssistantFile($assistantId, [$file_id]);
                    $file = json_decode($file, true);
                    echo '<h1> file: '. var_dump($file) . '</h1>';
                    if(isset($response['error'])) {
                        throw new Exception($response['error']['message']);
                    }
                }
            }
    
            return new WP_REST_Response(['message' => 'File uploaded successfully', 'file_id' => $file_id], 200);
        } catch (Exception $e) {
            return new WP_REST_Response(['message' => $e->getMessage()], 500);
        }
    }

    public function sync_transcriptions() {
        $option_name = 'video_ai_chatbot_transcriptions';
        $transcriptions = get_option($option_name, []);

        $response = $this->client->listFiles();
        $response = json_decode($response, true);

        foreach ($response['data'] as $key => $file) {
            $file_id = $file['id'];
            $found = false;
            foreach ($transcriptions as $transcription) {
                if ($transcription['file_id'] === $file_id) {
                    $found = true;
                    break;
                }
            }

            if (!$found) {
                $response = $this->client->deleteFile($file_id);
                $response = json_decode($response, true);
            }
        }
    
        foreach ($transcriptions as $key => $transcription) {
            // Check if the file exists on OpenAI
            try {
                $response = $this->client->retrieveFile($transcription['file_id']);
                $response = json_decode($response, true);
                if(isset($response['error'])) {
                    throw new Exception($response['error']['message']);
                }
            } catch (Exception $e) {
                // File not found on OpenAI, re-upload it
                //echo '<h1> udsjhafkjsdhf'. $e->getMessage() . '</h1>';
                try {

                    $file_content = json_encode($transcription['transcription']);
                    $tmp_file = tmpfile();
                    fwrite($tmp_file, $file_content);
                    $file_name = $transcription['file_name'] ? $transcription['file_name'] : 'transcription-' . $transcription['file_id'] . '.json';
                    $c_file = curl_file_create(stream_get_meta_data($tmp_file)['uri'], 'application/json', $file_name);
                    
                    $response = $this->client->uploadFile([
                        'file' => $c_file,
                        'purpose' => 'assistants'
                    ]);
                    
                    $response = json_decode($response, true);
    
                    // Update the file_id
                    //echo '<h1> udsjhafkjsdhf'. $response['id'] . '</h1>';
                    $transcriptions[$key]['file_id'] = $response['id'];
                } catch (Exception $uploadException) {
                    return new WP_REST_Response(['message' => $uploadException->getMessage()], 500);
                    // Handle re-upload failure
                }
            }
        }
        $option_name = 'video_ai_chatbot_transcriptions';
        update_option($option_name, $transcriptions);

        // update_option($option_name, $transcriptions);
        return new WP_REST_Response(['message' => 'Files synced successfully', 'transcriptions' => $transcriptions], 200);
    }

    public function create_assistant(WP_REST_Request $request) {
        $parameters = $request->get_json_params();
        $name = sanitize_text_field($parameters['name']);
        $prompt = sanitize_textarea_field($parameters['prompt']);
        if(!isset($this->client)) {
            return new WP_REST_Response(['message' => 'OpenAI client not initialized'], 400);
        }

        $data = [
            'model' => 'gpt-3.5-turbo',
            'name' => $name,
            'description' => $name,
            'instructions' => $prompt,
            'tools' => [array('type' => "file_search")],
        ];
    
        try {
            $response =  $this->client->createAssistant($data);
            $decoded_res = json_decode($response,true);
            if ($response && isset($decoded_res['id'])) {
                return new WP_REST_Response(['message' => 'Assistant created successfully', 'assistant_id' => $decoded_res['id']], 200);
            } else {
                return new WP_REST_Response(['message' => 'Failed to create assistant'], 500);
            }
        } catch (Exception $e) {
            return new WP_REST_Response(['message' => $e->getMessage()], 500);
        }
    }

    public function createAssistant($name, $prompt) {

        

        return $response;
    }

    public function get_assistants() {
        if(!isset($this->client)) {
            return new WP_REST_Response(['message' => 'OpenAI client not initialized'], 400);
        }
        // Recupera gli assistenti utilizzando le API di OpenAI
        $query = ['limit' => 10];
        $assistants = $this->client->listAssistants($query); 
        return new WP_REST_Response(json_decode($assistants), 200);
    }

    public function handle_chatbot_request(WP_REST_Request $request) {

        if (!isset($this->client)) {
            return new WP_REST_Response(['message' => 'OpenAI client not initialized'], 400);
        }

        $parameters = $request->get_json_params();
        $input_text = $parameters['message'];
        $assistant_id = $parameters['assistant_id'];

        try {
            if (!isset( $assistant_id )) {
                throw new Exception('Assistant not found');
            }

            // $user_id = get_current_user_id();
            $user_id = apply_filters( 'determine_current_user', true );
            if($user_id != 0) {
                $threadId = get_user_meta($user_id, 'openai_thread_id', true);
            }
            else if(isset($_COOKIE['video_ai_chatbot_session_id'])) {
                $threadId = get_option('thread_id'.$_COOKIE['video_ai_chatbot_session_id']);
            }
            $thread = false;

            if($threadId) {

                //echo '<h1> retrieving thread </h1>';
                $thread = $this->client->retrieveThread($threadId);
                $thread = json_decode($thread, true);
                //echo '<h1>'. $thread . '</h1>';
                if(isset($thread->error))
                {
                    $thread = false;
                } 
            }
            

            if(!$thread || isset($thread->error)) {
                //echo '<h1> Creating thread </h1>';
                $mData = [
                    'messages' => [
                        [
                            'role' => 'user',
                            'content' => $input_text,
                        ],
                    ],
                ];

                $thread = $this->client->createThread($mData);
                $thread = json_decode($thread, true);
                if(isset($thread->error) || !isset($thread['id']))
                {
                    //$thread = false;
                    //throw new Exception($thread['error']['message'], 500);
                    return new WP_REST_Response(['message' => $thread], 500);
                } 
                //return new WP_REST_Response(['message' => $e->getMessage()], 500);
                if($user_id != 0) {
                    update_user_meta($user_id, 'openai_thread_id', $thread['id']);
                } 
                if(isset($_COOKIE['video_ai_chatbot_session_id'])) {
                    update_option('thread_id'.$_COOKIE['video_ai_chatbot_session_id'], $thread['id']);
                }
            } else {

                if (!isset($thread['id'])) {
                    throw new Exception('Failed to create thread');
                }
    
                $msgData = [
                    'role' => 'user',
                    'content' => $input_text,
                ];
                
                $message = $this->client->createThreadMessage($thread['id'], $msgData);
        
        
                if(!$message ) {
                    throw new Exception('no message received', 500);
                }
                if(isset($message->error))
                {
                    throw new Exception($message['error']['message'], 500);     
                }
            }
  
            $runData = [
                'assistant_id' => $assistant_id,
                //'tool_choice' => [ 'type' => 'file_search' ]
            ];
    
            $run = $this->client->createRun($thread['id'], $runData);
            $run = json_decode($run, true);
    

            if(isset($run['error']))
            { 
                $run = $run['error']['message'];
                throw new Exception($run, 500);
                //throw new Exception(['message' => $run['error']['message']], 500);
            }
    
    
            $start_time = time();
            // Wait for the response to be ready
            while (($run['status'] == "in_progress" || $run['status'] == "queued") && (time() - $start_time) < 5000) {
                sleep(2);
                $run = $this->client->retrieveRun($thread['id'], $run['id']);
                $run = json_decode($run, true);    
            }
    
            if($run['status'] == "in_progress" || $run['status'] == "queued") {
                throw new Exception('Timeout', 500);
            }           
    
            $query = ['limit' => 10];
            $messages = $this->client->listThreadMessages($thread['id'], $query);
            $messages = json_decode($messages, true);
    
            
            if(!$messages) {
                throw new Exception('no messages received', 500);
            }
    
            if(isset($messages['error']))
            {
                throw new Exception(['message' => $messages['error']['message']], 500);     
            }

            if(count($messages['data'][0]['content'][0]['text']['annotations']) > 0)
            {
                $annotations = $messages['data'][0]['content'][0]['text']['annotations'];
                $messages['data'][0]['content'][0]['text']['annotations'] = $annotations;
                
            }
    
            return new WP_REST_Response(['message' => $messages['data'][0]['content'][0]['text']], 200);
        } catch (Exception $e) {
            return new WP_REST_Response(['message' => $e->getMessage()], 500);
        }
    }

    public function handle_thread_deletion() {
        // $user_id = get_current_user_id();
        $user_id = apply_filters( 'determine_current_user', true );
        echo '<h1>'.  $user_id  . '</h1>';
        if($user_id != 0) {
            $threadId = get_user_meta($user_id, 'openai_thread_id', true);
        }
        echo '<h1>Thread: '. $threadId . '</h1>';
		if ($threadId) {
			$result = $this->client->deleteThread($threadId);
            echo '<h1>'. $result . '</h1>';
			if ($result) {
				delete_user_meta($user_id, 'openai_thread_id');
				add_settings_error('openai_cancel_thread', 'openai_thread_deleted', 'Thread cancellato con successo.', 'updated');
			} else {
				add_settings_error('openai_cancel_thread', 'openai_thread_deletion_failed', 'Cancellazione del thread fallita.', 'error');
			}
		}
	
		return new WP_REST_Response(json_decode($result), 200);
	}
}

