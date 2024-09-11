<?php
class Video_Ai_Chatbot_Api {

    private $openai;

    public function __construct(Video_Ai_OpenAi $openai) {
        $this->openai = $openai;
    }

    public function permission_callback ( WP_REST_Request $request ) {

        if(! is_user_logged_in()) {
             return false;
        }
        // if(!current_user_can('edit_pages')) { 
        //     error_log('User cannot edit pages');
        //     return false;
        // }
        if(wp_verify_nonce($request->get_header('X-WP-Nonce'),'wp_rest') === false ) {
            if($request->get_header('Authorization')) {
                return true;
                // $token = explode(' ', $request->get_header('Authorization'));
                // $token = $token[1];
                // $decoded = JWT::decode($token, AUTH_KEY, array('HS256'));
                // if($decoded->data->nonce !== $request->get_header('X-WP-Nonce')) {
                //     error_log('Invalid nonce');
                //     return false;
                // }
            } else {
                error_log('Invalid nonce');
                return false; 
            }
        }
        return true;
    }
    

    public function register_api_hooks() {
        // Registra il nuovo endpoint per ottenere gli assistenti
        register_rest_route('video-ai-chatbot/v1', '/assistants/', [
            'methods' => 'GET',
            'callback' => [$this, 'get_assistants_request'],
            'permission_callback' => [$this, 'permission_callback']
        ]);

        // Endpoint per invio messaggi con verifica della sessione
        register_rest_route('video-ai-chatbot/v1', '/chatbot/', [
            'methods' => 'POST',
            'callback' => [$this, 'handle_wp_chatbot_request'],
            'permission_callback' => '__return_true'
        ]);

        register_rest_route('video-ai-chatbot/v1', '/create-assistant/', [
            'methods' => 'POST',
            'callback' => [$this, 'create_assistant_request'],
            'permission_callback' => [$this, 'permission_callback']
        ]);

        register_rest_route('video-ai-chatbot/v1', '/update-assistant/', [
            'methods' => 'POST',
            'callback' => [$this, 'update_assistant_request'],
            'permission_callback' => [$this, 'permission_callback']
        ]);

        register_rest_route('video-ai-chatbot/v1', '/upload-transcription/', [
            'methods' => 'POST',
            'callback' => [$this, 'upload_transcription_request'],
            'permission_callback' => [$this, 'permission_callback']
        ]);

        register_rest_route('video-ai-chatbot/v1', '/upload-file/', [
            'methods' => 'POST',
            'callback' => [$this, 'upload_file_request'],
            'permission_callback' => [$this, 'permission_callback']
        ]);

        register_rest_route('video-ai-chatbot/v1', '/delete-transcription/(?P<file_id>[a-zA-Z0-9_-]+)', [
            'methods' => 'DELETE',
            'callback' => [$this, 'delete_transcription_request'],
            'permission_callback' => [$this, 'permission_callback']
        ]);

        register_rest_route('video-ai-chatbot/v1', '/sync-transcriptions/', [
            'methods' => 'POST',
            'callback' => [$this, 'sync_transcriptions'],
            'permission_callback' => [$this, 'permission_callback']
        ]);

        register_rest_route('video-ai-chatbot/v1', '/transcriptions', [
            'methods' => 'GET',
            'callback' => [$this, 'get_transcriptions_request'],
            'permission_callback' => [$this, 'permission_callback']
        ]);

        register_rest_route('video-ai-chatbot/v1', '/vector-store-files/', [
            'methods' => 'GET',
            'callback' => [$this, 'handle_retrieve_vector_store_files_request'],
            'permission_callback' => [$this, 'permission_callback']
        ]);


        //registra una rout per cancellare un assistente
        register_rest_route('video-ai-chatbot/v1', '/delete-assistant/(?P<assistant_id>[a-zA-Z0-9_-]+)', [
            'methods' => 'DELETE',
            'callback' => [$this, 'delete_assistant'],
            'permission_callback' => [$this, 'permission_callback']
        ]);

        //registra una rout per cancellare diles e trascrizioni locali
        register_rest_route('video-ai-chatbot/v1', '/delete-local-files-data', [
            'methods'=> 'GET',
            'callback'=> [$this, 'delete_local_files_data'],
            'permission_callback' => [$this, 'permission_callback']
        ]);

        register_rest_route('video-ai-chatbot/v1', '/delete-unused-vector-stores', [
            'methods' => 'GET',
            'callback' => [$this, 'delete_unused_vector_stores'],
            'permission_callback' => [$this, 'permission_callback']
        ]);

        

        register_rest_route('video-ai-chatbot/v1', '/get-options/(?P<key>\w+)', [
            'methods' => 'GET',
            'callback' => [$this, 'get_options'],
            'permission_callback' => [$this, 'permission_callback'],
            'args' => [
                'key' => [
                    'validate_callback' => function($param, $request, $key) {
                        return is_string($param);
                    },
                    'required' => true,
                ],
            ],
        ]);
        
        register_rest_route('video-ai-chatbot/v1', '/set-options/(?P<key>\w+)', [
            'methods' => 'POST',
            'callback' => [$this, 'set_options'],
            'permission_callback' => [$this, 'permission_callback'],
            'args' => [
                'key' => [
                    'validate_callback' => function($param, $request, $key) {
                        return is_string($param);
                    },
                    'required' => true,
                ],
                'value' => [
                    'validate_callback' => function($param, $request, $key) {
                        // Qui puoi aggiungere una validazione specifica per il valore, se necessario
                        return true;
                    },
                    'required' => true,
                ],
            ],
        ]);
        
        register_rest_route('video-ai-chatbot/v1', '/get-all-options', [
            'methods' => 'GET',
            'callback' => [$this, 'get_all_options_request'],
            'permission_callback' => [$this, 'permission_callback']
        ]);
        
        // Aggiungi questa chiamata a register_rest_route nel metodo dove registri le altre route
        register_rest_route('video-ai-chatbot/v1', '/set-all-options', [
            'methods' => 'POST',
            'callback' => [$this, 'set_all_options_request'],
            'permission_callback' => [$this, 'permission_callback']//, // Assicurati che solo gli utenti autorizzati possano modificare le opzioni
            // 'args' => [
            //     'options' => [
            //         'required' => true,
            //         'validate_callback' => function($param, $request, $key) {
            //             // Qui puoi aggiungere una validazione specifica per le opzioni, se necessario
            //             return is_array($param);
            //         },
            //     ],
            // ],
        ]);
        
        // Aggiungi questa chiamata a register_rest_route nel metodo dove registri le altre route
        register_rest_route('video-ai-chatbot/v1', '/get-css-themes', [
            'methods' => 'GET',
            'callback' => [$this, 'get_css_themes'],
            'permission_callback' => [$this, 'permission_callback'] // Cambia questo in base alle tue necessità di autorizzazione
        ]);

        //registra una route per cancel_all_runs
        register_rest_route('video-ai-chatbot/v1', '/cancel-all-runs', [
            'methods' => 'GET',
            'callback' => [$this, 'cancel_all_runs'],
            'permission_callback' => [$this, 'permission_callback']
        ]);

        //registra un'api per ottenere tutti i messaggi di tutti i thread
        register_rest_route('video-ai-chatbot/v1', '/get-all-thread-messages/', [
            'methods' => 'GET',
            'callback' => [$this, 'get_all_thread_messages_request'],
            'permission_callback' => [$this, 'permission_callback']
        ]);

        //registra un hook per ottenere tutti i messaggi di un thread
        register_rest_route('video-ai-chatbot/v1', '/get-thread-messages/(?P<thread_id>[a-zA-Z0-9_-]+)', [
            'methods' => 'GET',
            'callback' => [$this, 'get_thread_messages_request'],
            'permission_callback' => [$this, 'permission_callback']
        ]);

        //register delete_all_threads route
        register_rest_route('video-ai-chatbot/v1', '/delete-all-threads', [
            'methods' => 'GET',
            'callback' => [$this, 'delete_all_threads'],
            'permission_callback' => [$this, 'permission_callback']
        ]);

        //registra una route per cancel_all_users_runs
        register_rest_route('video-ai-chatbot/v1', '/cancel-all-users-runs', [
            'methods' => 'GET',
            'callback' => [$this, 'cancel_all_users_runs'],
            'permission_callback' => [$this, 'permission_callback']
        ]);

        //registra un hoock per settare i numeri di nofitica per l'hadover
        register_rest_route('video-ai-chatbot/v1', '/set-handover-notification-numbers', [
            'methods' => 'POST',
            'callback' => [$this, 'set_handover_notification_numbers'],
            'permission_callback' => [$this, 'permission_callback']
        ]);

        //registra un hoock per ottenere i numeri di notifica per l'handover
        register_rest_route('video-ai-chatbot/v1', '/get-handover-notification-numbers', [
            'methods' => 'GET',
            'callback' => [$this, 'get_handover_notification_numbers'],
            'permission_callback' => [$this, 'permission_callback']
        ]);

        //registra un hoock per settare i numeri di nofitica per l'hadover
        register_rest_route('video-ai-chatbot/v1', '/set-handover-notification-emails', [
            'methods' => 'POST',
            'callback' => [$this, 'set_handover_notification_emails'],
            'permission_callback' => [$this, 'permission_callback']
        ]);

        //registra un hoock per ottenere i numeri di notifica per l'handover
        register_rest_route('video-ai-chatbot/v1', '/get-handover-notification-emails', [
            'methods' => 'GET',
            'callback' => [$this, 'get_handover_notification_emails'],
            'permission_callback' => [$this, 'permission_callback']
        ]);
        

        //registra una hook per la callback get_current_user_thread_message_request
        register_rest_route('video-ai-chatbot/v1', '/get-current-user-thread-messages', [
            'methods' => 'GET',
            'callback' => [$this, 'get_current_user_thread_message_request'],
            'permission_callback' =>  '__return_true'
        ]);	

        //registra una route per richiedere uno specifico fiele contenuto in video_ai_chatbot_files
        register_rest_route('video-ai-chatbot/v1', '/get-file/(?P<vector_store_id>[a-zA-Z0-9_-]+)', [
            'methods' => 'GET',
            'callback' => [$this, 'get_file_request'],
            'permission_callback' => [$this, 'permission_callback']
        ]);

        //registra una rout per recuperare tutti i gpt models da openai 
        register_rest_route('video-ai-chatbot/v1', '/get-gpt-models', [
            'methods' => 'GET',
            'callback' => [$this, 'get_gpt_models_request'],
            'permission_callback' => [$this, 'permission_callback']
        ]);

        //registra una route per cancellare il thread dell'utente corrente. assistant_id come parametro
        register_rest_route('video-ai-chatbot/v1', '/delete-current-user-thread/(?P<assistant_id>[a-zA-Z0-9_-]+)', [
            'methods' => 'DELETE',
            'callback' => [$this, 'delete_current_user_thread_request'],
            'permission_callback' => [$this, 'permission_callback']
        ]);

        register_rest_route('video-ai-chatbot/v1/chat', '/handover_message/', [
            'methods' => 'POST',
            'callback' => [$this, 'handover_message'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route('video-ai-chatbot/v1/chat', '/terminate_handover/(?P<thread_id>[a-zA-Z0-9_-]+)', [
            'methods' => 'GET',
            'callback' => [$this, 'terminate_handover'],
            'permission_callback' => '__return_true',
        ]);
    }

    //funzione per terminare l'handover
    public function terminate_handover(WP_REST_Request $request) {
        $thread_id = $request['thread_id'];
        if(!isset($thread_id)) {
            return new WP_REST_Response(['message' => 'Invalid request data'], 400);
        }
        $response = $this->openai->terminate_handover($thread_id);
        error_log("terminate_handover response: " . json_encode($response, JSON_PRETTY_PRINT));
        $response = json_decode($response, true);
        if(isset($response['error']) && $response['error']) {
            return new WP_REST_Response(['message' => 'Error terminating handover'], 400);
        }
        return new WP_REST_Response(['message' => 'Handover Terminated'], 200);
    }

    public function handover_message(WP_REST_Request $request) {
        $headers = $request->get_headers();
        $data = $request->get_body_params();
        //error_log della variabile $_POST
        error_log("POST: " . json_encode($_POST, JSON_PRETTY_PRINT));
        $message = $data['message'];
        $thread_id = $data['thread_id'];
        error_log("-------------- New Request POST --------------");
        error_log("message: " . $message);
        error_log("thread_id: " . $thread_id);
        error_log("result_send: " . json_encode($result_send , JSON_PRETTY_PRINT));
        $response = $this->openai->user_handover($thread_id, $message);
        return new WP_REST_Response(['message' => 'Message sent'], 200);
    }

    public function delete_current_user_thread_request(WP_REST_Request $request) {
        $assistant_id = sanitize_text_field($request->get_param('assistant_id'));
        error_log('assistant_id: ' . $assistant_id);
        return $this->openai->handle_thread_deletion($assistant_id);
    }

    public function get_gpt_models_request() {
        try {
            $gptModels = $this->openai->get_gpt_models();
            return new WP_REST_Response($gptModels, 200);
        } catch (Exception $e) {
            return new WP_REST_Response(['message' => $e->getMessage()], 500);
        }
    }

    public function get_file_request(WP_REST_Request $request) {
        $file_id = sanitize_text_field($request->get_param('vector_store_id'));
        if(!isset($file_id)) {
            return new WP_REST_Response(['message' => 'Empty file parameter'], 404);
        }
        try {
            $fileData = $this->openai->get_file($file_id);
            if(!isset($fileData)) {
                return new WP_REST_Response(['message' => 'File not found'], 404);
            }
        } catch (Exception $e) {
            return new WP_REST_Response(['message' => 'Failed to get file', 'error' => $e->getMessage()], 500);
        }
        return new WP_REST_Response($fileData, 200);
    }

    public function get_assistants_request() {
        try {
            $assistants = $this->openai->get_assistants();
            return new WP_REST_Response($assistants, 200);
        } catch (Exception $e) {
            return new WP_REST_Response(['message' => $e->getMessage()], 500);
        }
    }

    
    public function get_filtered_assistants_request() {
        try {
            $assistants = $this->openai->get_filtered_assistants();
            return new WP_REST_Response($assistants, 200);
        } catch (Exception $e) {
            return new WP_REST_Response(['message' => $e->getMessage()], 500);
        }
    }


    public function handle_wp_chatbot_request(WP_REST_Request $request) {
        $parameters = $request->get_json_params();
        $input_text = $parameters['message'];
        $assistant_id = $parameters['assistant_id'];
        $postprompt = $parameters['postprompt'];

        $result = $this->openai->handle_chatbot_request($input_text, $assistant_id, $_COOKIE['video_ai_chatbot_session_id'], false, $postprompt);
        if($result['error']) {
            return new WP_REST_Response($result, 500);
        }
        return new WP_REST_Response($result, 200);
    }

    public function create_assistant_request(WP_REST_Request $request) {
        $parameters = $request->get_json_params();
        $name = sanitize_text_field($parameters['name']);
        $prompt = sanitize_textarea_field($parameters['prompt']);
        $type = sanitize_text_field($parameters['type']);
        $metadata = $parameters['metadata'];
        $files = $parameters['files'];
        $model = $parameters['model'];
        return $this->openai->create_assistant($name, $prompt, $type, $metadata, $files, $model);
    }

    public function update_assistant_request(WP_REST_Request $request) {
        $parameters = $request->get_json_params();
        error_log('parameters: ' . json_encode($parameters));
        $id = sanitize_text_field($parameters['id']);
        $name = sanitize_text_field($parameters['name']);
        $prompt = sanitize_textarea_field($parameters['prompt']);
        $files= $parameters['files'];
        $vectorStoreIds = $parameters['vector_store_ids'];
        $type = sanitize_text_field($parameters['type']);
        $metadata = $parameters['metadata'];
        $model = $parameters['model'];
        return $this->openai->update_assistant($id, $name, $prompt, $vectorStoreIds, $files, $type, $metadata, $model);
    }

    public function upload_transcription_request(WP_REST_Request $request) {
        $old_file_id = sanitize_text_field($request->get_param('file_id'));
        if($old_file_id && !is_numeric($old_file_id) ) {
            try {
                $deletedFile = $this->openai->delete_transcription($old_file_id);
            } catch (Exception $e) {
                return new WP_REST_Response(['message' => 'Failed to delete old transcription', 'error' => $e->getMessage()], 500);
            }
        }
        if (empty($_FILES['file']['tmp_name'])) {
            return new WP_REST_Response(['message' => 'No file uploaded'], 400);
        }
    
        $file = $_FILES['file'];
        $file_content = file_get_contents($file['tmp_name']);
        $transcription = json_decode($file_content, true);
        error_log('transcription: ' . json_encode($transcription));	
        if (json_last_error() !== JSON_ERROR_NONE) {
            return new WP_REST_Response(['message' => 'Invalid JSON file'], 400);
        }
    
        $tmp_file = $file['tmp_name'];
        $file_name = basename($_FILES['file']['name']);
        $c_file = curl_file_create($tmp_file, $_FILES['file']['type'], $file_name);
        return $this->openai->upload_transcription($c_file, $file_name, $transcription);
    }

    public function upload_file_request(WP_REST_REquest $request) {
        $old_file_id = sanitize_text_field($request->get_param('file_id'));
        error_log('old_file_id: ' . $old_file_id);
        if(!empty($old_file_id) && !is_numeric($old_file_id) ) {
            try {
                $deletedFile = $this->openai->delete_file($old_file_id, true);
                error_log('deleted file: ' . $deletedFile);
            } catch (Exception $e) {
                return new WP_REST_Response(['message' => 'Failed to delete old file', 'error' => $e->getMessage()], 500);
            }
        }

        if (empty($_FILES['file']['tmp_name'])) {
            return new WP_REST_Response(['message' => 'No file uploaded'], 400);
        }
    
        $file = $_FILES['file'];    
        $tmp_file = $file['tmp_name'];
        $file_content = file_get_contents($tmp_file);
        $file_name = basename($_FILES['file']['name']);
        $c_file = curl_file_create($tmp_file, $_FILES['file']['type'], $file_name);
        return $this->openai->upload_file($c_file, $file_name, $file_content);
    }

    public function delete_transcription_request(WP_REST_Request $request) {
        $file_id = sanitize_text_field($request->get_param('file_id'));
        // Eliminare il file da OpenAI
        try {
            $response = $this->openai->delete_transcription($file_id);
        } catch (Exception $e) {
            return new WP_REST_Response(['message' => 'File deleted locally but failed to delete from OpenAI', 'error' => $e->getMessage()], 500);
        }

        return new WP_REST_Response(['message' => 'Transcription deleted successfully', 'deleted_file_id' => $response], 200);
    }

    public function sync_transcriptions(WP_REST_Request $request) {
        try {
            $transcriptions = $this->openai->sync_transcriptions();
        } catch (Exception $e) {
            return new WP_REST_Response(['message' => $uploadException->getMessage()], 500);
        }
        return new WP_REST_Response(['message' => 'Files synced successfully', 'transcriptions' => $transcriptions], 200);    }

    public function get_transcriptions_request() {
        try {
            $transcriptions = $this->openai->get_transcriptions();
            return new WP_REST_Response($transcriptions, 200);
        } catch (Exception $e) {
            return new WP_REST_Response(['message' => 'Failed to get transcriptions', 'error' => $e->getMessage()], 500);
        }
    }

    public function handle_retrieve_vector_store_files_request(WP_REST_Request $request) {
        try {
            $vectorStoreId = sanitize_text_field($request->get_param('vector_store_id'));
            $parameters = $request->get_param('parameters');
            $response = $this->openai->handle_retrieve_vector_store_files($vectorStoreId, $parameters);
            return new WP_REST_Response($response, 200);
        } catch (Exception $e) {
            error_log('error: ' . $e->getMessage());
            return new WP_REST_Response(['error' => 'Vector store not found', 'message' => $e->getMessage()], 500);        }
    }

    public function delete_assistant(WP_REST_Request $request) {
        $assistant_id = sanitize_text_field($request->get_param('assistant_id'));
        try {
            $assistants = $this->openai->delete_assistant($assistant_id);
            return new WP_REST_Response(['message' => 'Assistant deleted successfully', 'assistants' => $assistants], 200);
        } catch (Exception $e) {
            return new WP_REST_Response(['message' => 'Failed to delete assistant', 'error' => $e->getMessage()], 500);
        }
    }

    public function delete_local_files_data() {
        try {
            $this->openai->delete_local_files_data();
            return new WP_REST_Response(['message' => 'Local files and data deleted successfully'], 200);
        } catch (Exception $e) {
            return new WP_REST_Response(['message' => 'Failed to delete local files and data', 'error' => $e->getMessage()], 500);
        }
    }

    public function delete_unused_vector_stores() {
        try {
            $this->openai->delete_unused_vector_stores();
            return new WP_REST_Response(['message' => 'Unused vector stores deleted successfully'], 200);
        } catch (Exception $e) {
            return new WP_REST_Response(['message' => 'Failed to delete unused vector stores', 'error' => $e->getMessage()], 500);
        }
    }

    public function get_options(WP_REST_Request $request) {
        $key = sanitize_text_field($request->get_param('key'));
        $options = get_option('video_ai_chatbot_options');
        if (isset($options[$key])) {
            return new WP_REST_Response($options[$key], 200);
        } else {
            return new WP_REST_Response(['message' => 'Option not found'], 404);
        }
    }

    public function set_options(WP_REST_Request $request) {
        $key = $request->get_param('key');
        $value = $request->get_param('value');
        $options = get_option('video_ai_chatbot_options');
        if (!$options) {
            $options = [];
        }
        $options[$key] = $value;
        update_option('video_ai_chatbot_options', $options);
        return new WP_REST_Response(['message' => 'Options updated successfully'], 200);
    }

    public function get_all_options_request() {
        $options = $this->openai->get_all_options();
        return new WP_REST_Response($options, 200);
    }

    public function set_all_options_request(WP_REST_Request $request) {
        try{ 
            $params = $request->get_body_params();
            $updated = $this->openai->set_all_options($params);
            if ($updated) {
                return new WP_REST_Response(['message' => 'Options updated successfully'], 200);
            } else {
                return new WP_REST_Response(['message' => 'No update needed'], 204);
            }
        } catch (Exception $e) {
            return new WP_REST_Response(['message' => 'Failed to update options', 'error' => $e->getMessage()], 500);
        }
        return new WP_REST_Response(['message' => 'Options updated successfully'], 200);
    }

    public function get_css_themes() {
        $themes = $this->openai->get_css_themes();
        if(!$themes) {
            return new WP_REST_Response(['message' => 'No themes found'], 404);
        }
        return new WP_REST_Response($themes, 200);
    }

    public function get_thread_messages_request(WP_REST_Request $request) {
        $thread_id = sanitize_text_field($request->get_param('thread_id'));
        return $this->openai->get_thread_messages_for_thread_id($thread_id);
    }
    
    public function cancel_all_runs() {
        return $this->openai->cancel_all_runs();
    }

    public function get_all_thread_messages_request() {
        return $this->openai->get_all_thread_messages();
    }

    public function delete_all_threads() {
        return $this->openai->delete_all_threads();
    }

    public function cancel_all_users_runs() {
        return $this->openai->cancel_all_users_runs();
    }

    public function set_handover_notification_numbers(WP_REST_Request $request) {
        $params = $request->get_body_params();
        return $this->openai->set_handover_notification_numbers($params);
    }

    public function get_handover_notification_numbers() {
        return $this->openai->get_handover_notification_numbers();
    }

    public function set_handover_notification_emails(WP_REST_Request $request) {
        $params = $request->get_body_params();
        return $this->openai->set_handover_notification_emails($params);
    }

    public function get_handover_notification_emails() {
        return $this->openai->get_handover_notification_emails();
    }

    public function get_current_user_thread_message_request() {
        return $this->openai->get_current_user_thread_message();
    }
}


?>