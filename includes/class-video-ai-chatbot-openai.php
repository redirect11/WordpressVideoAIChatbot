<?php

use Orhanerday\OpenAi\OpenAi;
use GuzzleHttp\Client;
use SimpleJWTLogin\Modules\UserProperties;
//use TutorUtils;

        
use WP_REST_Request;

class Video_Ai_OpenAi {
    private $client;
    private $communityopenai;
    private $tutorUtils;


    /**
	 * Initialize the class and set its properties.
	 *
	 * @since    1.0.0
	 * @param      string    $plugin_name       The name of this plugin.
	 * @param      string    $version    The version of this plugin.
	 */
	public function __construct($communityopenai) {
        $options = get_option('video_ai_chatbot_options');
        if($options) {
            $api_key = isset($options['openai_api_key_field']) ? $options['openai_api_key_field'] :'';
            if ($api_key) {
                $this->activate($api_key);
            }
        }
        $this->communityopenai = $communityopenai;
        $this->tutorUtils = new TutorUtils();
	}

    public function is_active() {
        return isset($this->client);
    }

    public function permission_callback ( WP_REST_Request $request ) {
        error_log('permission_callback');
        error_log($request->get_header('X-WP-Nonce'));
        if(! is_user_logged_in()) {
            error_log('User not logged in');
             return false;
        }
        // if(!current_user_can('edit_pages')) { 
        //     error_log('User cannot edit pages');
        //     return false;
        // }
        if(wp_verify_nonce($request->get_header('X-WP-Nonce'),'wp_rest') === false ) {
            if($request->get_header('Authorization')) {
                error_log('Auth token:' . $request->get_header('Authorization'));
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
            'callback' => [$this, 'create_assistant'],
            'permission_callback' => [$this, 'permission_callback']
        ]);

        register_rest_route('video-ai-chatbot/v1', '/update-assistant/', [
            'methods' => 'POST',
            'callback' => [$this, 'update_assistant'],
            'permission_callback' => [$this, 'permission_callback']
        ]);

        register_rest_route('video-ai-chatbot/v1', '/upload-transcription/', [
            'methods' => 'POST',
            'callback' => [$this, 'upload_transcription'],
            'permission_callback' => [$this, 'permission_callback']
        ]);

        register_rest_route('video-ai-chatbot/v1', '/upload-file/', [
            'methods' => 'POST',
            'callback' => [$this, 'upload_file'],
            'permission_callback' => [$this, 'permission_callback']
        ]);

        register_rest_route('myplugin/v1', '/delete-transcription/(?P<file_id>[a-zA-Z0-9_-]+)', [
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
        register_rest_route('video-ai-chatbot', '/delete-local-files-data', [
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
            'callback' => [$this, 'get_all_options'],
            'permission_callback' => [$this, 'permission_callback']
        ]);
        
        // Aggiungi questa chiamata a register_rest_route nel metodo dove registri le altre route
        register_rest_route('video-ai-chatbot/v1', '/set-all-options', [
            'methods' => 'POST',
            'callback' => [$this, 'set_all_options'],
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

        // Registra un nuov endpoint per richiedere il thread dell'utente corrente
        register_rest_route('video-ai-chatbot/v1', '/get-thread-messages/', [
            'methods' => 'GET',
            'callback' => [$this, 'get_thread_messages_request'],
            'permission_callback' => [$this, 'permission_callback']
        ]);

        //registra una route per cancel_all_runs
        register_rest_route('video-ai-chatbot/v1', '/cancel-all-runs', [
            'methods' => 'GET',
            'callback' => [$this, 'cancel_all_runs'],
            'permission_callback' => [$this, 'permission_callback']
        ]);
    }


    // Implementa la funzione get_thread_request per l'endpoint get-thread 
    public function get_thread_messages_request() {
        // Ottieni l'ID dell'utente corrente
        $user_id = apply_filters('determine_current_user', true);

        $assistant_id = $this->get_last_assistant_used_for_user($user_id);

        if(!isset($assistant_id) || !$assistant_id) {
            return new WP_REST_Response(['message' => 'Assistant ID not found'], 400);
        }

        $messages = $this->get_thread_messages($assistant_id);
        
        // Se non esiste un thread per l'utente corrente restituisci un errore
        if (!$messages) {
            return new WP_REST_Response(['message' => 'Thread not found'], 404);
        }
        // Restituisci il thread dell'utente corrente
        return new WP_REST_Response(json_decode($messages, true), 200);
    }

    public function get_thread_messages($assistantId) {
        $user_id = apply_filters('determine_current_user', true);
        $userSessionId =  $_COOKIE['video_ai_chatbot_session_id'];
        $threadId = $this->get_thread_id_for_user($user_id, $userSessionId, $assistantId);
        if(!isset($threadId) || !$threadId) {
            return [];
        }
        $query = ['limit' => 10];
        $messages = $this->client->listThreadMessages($threadId, $query);
        return $messages;
    }

    public function set_all_options(WP_REST_Request $request) {
        try {
            $options = $request->get_body_params();
            error_log('options: ' . json_encode($options));
            // if (!is_array($options)) {

            //     return new WP_REST_Response(['message' => 'Invalid options format'], 400);
            // }
        
            // Valida ulteriormente $options qui se necessario
        
            // Salva le opzioni

            $old = get_option('video_ai_chatbot_options');
            error_log('Current video_ai_chatbot_options: ' . json_encode($old));
            error_log('New video_ai_chatbot_options: ' . json_encode($options));

            $updated = update_option('video_ai_chatbot_options', $options);
        
            if ($updated) {
                return new WP_REST_Response(['message' => 'Options updated successfully'], 200);
            } else {
                return new WP_REST_Response(['message' => 'No update needed'], 204);
            }
        } catch (Exception $e) {
            return new WP_REST_Response(['message' => $e->getMessage()], 500);
        }
    }
    

    public function get_css_themes(WP_REST_Request $request) {
        $themes_dir = plugin_dir_path(__FILE__) . '../public/css/themes'; // Percorso alla cartella dei temi CSS
        $themes_files = scandir($themes_dir);
        $css_themes = [];
    
        foreach ($themes_files as $file) {
            if (pathinfo($file, PATHINFO_EXTENSION) === 'css') {
                $css_themes[] = $file;
            }
        }
    
        if (empty($css_themes)) {
            return new WP_REST_Response(['message' => 'No CSS themes found'], 404);
        } else {
            return new WP_REST_Response($css_themes, 200);
        }
    }

    public function get_options(WP_REST_Request $request) {
        $key = $request->get_param('key');
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

    public function get_all_options(WP_REST_Request $request) {
        $options = get_option('video_ai_chatbot_options');
        if (false === $options) {
            return new WP_Error('no_options', 'Nessuna opzione trovata', ['status' => 404]);
        }
        // Ciclo tutte le options. Quelle che sono relative a whatsapp e openai possono 
        // essere restituite solo se l'utente ha i permessi necessari
        foreach ($options as $key => $value) {
            if (strpos($key, 'openai_') === 0) {
                if (!current_user_can('manage_options')) {
                    unset($options[$key]);
                }
            }
        }
        error_log('options: ' . json_encode($options));
        return new WP_REST_Response($options, 200);
    }

    public function activate($apiKey)
    {
        if(!$this->is_active() && $apiKey) {
            $this->client = new OpenAi($apiKey);
            $this->client ->setAssistantsBetaVersion("v2");
        }
    }

    public function get_transcriptions_request(WP_REST_Request $request) {
        $option_name = 'video_ai_chatbot_transcriptions';
        $transcriptions = get_option($option_name, []);
        $transcriptions = wp_parse_args($transcriptions, []);
        return new WP_REST_Response($transcriptions, 200);
    }

    public function delete_local_files_data() {
        //delete transcriptions and files. Only local, no OPenai
        error_log("delete_local_files_data");
        $deleteTranscriptions = delete_option("video_ai_chatbot_transcriptions");
        $deleteFiles = delete_option("video_ai_chatbot_files");
        error_log("deleteTranscriptions: " . $deleteTranscriptions);
        error_log("deleteFiles: " . $deleteFiles);
        if($deleteFiles && $deleteTranscriptions) {
            echo "Dati cancellati con successo.";
            //add_settings_error('openai_delete_files_data_options', 'openai_file_deleted', 'Dati cancellati con successo.', 'updated');
            return new WP_REST_Response(['message' => 'Local files and transcriptions deleted successfully'], 200);
        } else {
            echo "Errore nella cancellazione dei file.";
            //add_settings_error('openai_delete_files_data_options', 'openai_file_deletion_failed', 'Cancellazione dei file fallita.', 'error');
            return new WP_REST_Response(['message' => 'Failed to delete local files and transcriptions'], 500);
        }
    }

    //Funzione callback per cancellare un assistente
    public function delete_assistant(WP_REST_Request $request) {
        $assistant_id = sanitize_text_field($request->get_param('assistant_id'));
        if(!$assistant_id) {
            return new WP_REST_Response(['message' => 'Assistant ID not found'], 400);
        }
        try {
            $response = $this->client->deleteAssistant($assistant_id);
            $decoded_res = json_decode($response);
            if(isset($decoded_res->error))
            {
                throw new Exception($decoded_res->error->message);
            } 
            return new WP_REST_Response(['message' => 'Assistant deleted successfully', 'assistants' => $this->get_assistants_request()], 200);
        } catch (Exception $e) {
            return new WP_REST_Response(['message' => $e->getMessage()], 500);
        }
    }

    private function handle_retrieve_vector_store_files($vectorStoreId, $after = null, $before = null) {
        $response = $this->communityopenai->retrieveVectorStoreFiles($vectorStoreId, $after, $before);
        error_log('response: ' . json_encode($response));
        // Ottieni i file salvati
        $savedFiles = get_option('video_ai_chatbot_files', []);
        error_log('savedFiles: ' . print_r($savedFiles, true));
        // Cerca i file restituiti tra quelli salvati

        foreach($savedFiles as $savedFile) {
            error_log('savedFile: ' . json_encode($savedFile));
            foreach($response['data'] as &$file) {
                error_log('file: ' . json_encode($file));
                if($savedFile['vector_store_id'] && $savedFile['vector_store_id'] == $vectorStoreId) {
                    // Aggiungi il contenuto del file alla risposta
                    $file['file_content'] = $savedFile['file_content'];
                    $file['file_name'] = $savedFile['file_name'];
                    break;
                }
            }
        }
        return $response;
    }

    public function handle_retrieve_vector_store_files_request(WP_REST_Request $request) {
        $vectorStoreId = sanitize_text_field($request->get_param('vector_store_id'));
        $parameters = $request->get_param('parameters');
        error_log('vectorStoreId: ' . $vectorStoreId);
        $response = [];
        try {
            $response = $this->handle_retrieve_vector_store_files($vectorStoreId, $parameters['after'], $parameters['before']);
        } catch (Exception $e) {
            error_log('error: ' . $e->getMessage());
            return new WP_REST_Response(['error' => 'Vector store not found', 'message' => $e->getMessage()], 500);
        }
        error_log('response: ' . json_encode($response));
        return new WP_REST_Response($response, 200);
    }

    private function getTranscriptionByAssistantId($assistantId) {
        $option_name = 'video_ai_chatbot_transcriptions';
        $transcriptions = get_option($option_name, []);
        $assistantTranscriptions = [];
        foreach ($transcriptions as $transcription) {
            if (in_array($assistantId, $transcription['assistant_id'])) {
                $assistantTranscriptions[] = $transcription;
            }
        }
        return $assistantTranscriptions;
    }

    private function updateTranscriptionsWithAssistantId($assistantId, $fileIds) {
        error_log("updateTranscriptionsWithAssistantId");
        $option_name = 'video_ai_chatbot_transcriptions';
        $transcriptions = get_option($option_name, []);
        //find all transcriptions within fileIds and update assistant_id in place
        $toUpdate = array_map(function($transcription) use ($assistantId, $fileIds) {
            if(in_array($transcription['file_id'], $fileIds)) {
                // Aggiungi l'ID dell'assistente
                array_push($transcription['assistant_id'], $assistantId);
            }
            return $transcription;
        }, $transcriptions);
        error_log('toUpdate: ' . json_encode($toUpdate));
        //print the assistant ids property for each updatedTranscription
        foreach($toUpdate as $transcription) {
            error_log('assistant_id: ' . json_encode($transcription['assistant_id']));
        }
        update_option($option_name, $toUpdate);
    }


    private function cleanUpTranscriptionsFromAssistantId($assistantId) {
        error_log("cleanUpTranscriptionsFromAssistantId");
        $option_name = 'video_ai_chatbot_transcriptions';
        $transcriptions = get_option($option_name, []);
        error_log('transcriptions: ' . json_encode($transcriptions));
        $func = function($transcription) use ($assistantId) {
            if(in_array($assistantId, $transcription['assistant_id'])) {
                // Elimina l'ID dell'assistente
                $transcription['assistant_id'] = array_diff($transcription['assistant_id'], [$assistantId]);
            }
            return $transcription; 
        };
        $updatedTranscriptions = array_map($func, $transcriptions);
        //print the assistant ids property for eac updatedTranscription
        foreach($updatedTranscriptions as $transcription) {
            error_log('assistant_id: ' . json_encode($transcription['assistant_id']));
        }
        update_option($option_name, $updatedTranscriptions ? $updatedTranscriptions : []);
    }

    public function update_assistant_trascrizioni($id, $name, $data, $vectorStoreIds, $files) {

        error_log('update_assistant_trascrizioni');
        $deleted = $this->communityopenai->deleteVectorStore($vectorStoreIds[0]);
        error_log('deleted: ' . $deleted);
        if(!$deleted) {
            throw new Exception('Failed to delete vector store');
        }

        $vectorStoreId = $this->communityopenai->createVectorStore('vs_name_'.str_replace(' ', '_', $name));
        if(!$vectorStoreId) {
            throw new Exception('Failed to create the new vector store');
        }

        if($files && count($files) > 0) {
            $files = $this->communityopenai->createVectorStoreFiles($vectorStoreId, $files); 
            if(!$files) {
                throw new Exception('Failed to create vector store files');
            }
        }


        // Aggiorna le trascrizioni con l'ID dell'assistente
        $this->cleanUpTranscriptionsFromAssistantId($id);
        // Aggiorna le trascrizioni con l'ID dell'assistente

        if($files && count($files) > 0) {
            $this->updateTranscriptionsWithAssistantId($id, $files);
        }

        error_log('DATAAAA: ' . json_encode($data));       

        $data['tool_resources']['file_search']['vector_store_ids'] = [ $vectorStoreId ] ;
        array_push($data['tools'], $this->get_functions());

        return $data;

    }

    public function update_assistant(WP_REST_Request $request) {
        $parameters = $request->get_json_params();
        $id = sanitize_text_field($parameters['id']);
        $name = sanitize_text_field($parameters['name']);
        $prompt = sanitize_textarea_field($parameters['prompt']);
        $files= $parameters['files'];
        $vectorStoreIds = $parameters['vector_store_ids'];
        $type = sanitize_text_field($parameters['type']);

        error_log('vectorStoreIds: ' . json_encode($vectorStoreIds));
        
        if(!isset($this->client) || !isset($this->communityopenai)) {
            return new WP_REST_Response(['message' => 'OpenAI client not initialized'], 400);
        }

        if(!$id) {
            return new WP_REST_Response(['message' => 'Assistant ID not found'], 400);
        }

        if(!$name) {
            return new WP_REST_Response(['message' => 'Name not found'], 400);
        }

        if(!$prompt) {
            return new WP_REST_Response(['message' => 'Prompt not found'], 400);
        }

        if(!$vectorStoreIds) {
            return new WP_REST_Response(['message' => 'Vector store IDs not found'], 400);
        }

        if(!$type) {
            return new WP_REST_Response(['message' => 'Type not found'], 400);
        }

        // if($type !== 'trascrizioni') {
        //     return new WP_REST_Response(['message' => 'Invalid type'], 400);
        // }

        $data = [
            'model' => 'gpt-3.5-turbo',
            'name' => $name,
            'description' => $name,
            'instructions' => $prompt,
            'tools' => [array('type' => "file_search")]
        ];


        try {
            if($type == 'trascrizioni') {
                $data = $this->update_assistant_trascrizioni($id, $name, $data, $vectorStoreIds, $files);
                error_log('data: ' . json_encode($data));
            } else if($type == 'preventivi' && count($files) > 0) {     
                try {
                    $this->communityopenai->createVectorStoreFiles($vectorStoreIds[0], $files);
                    //update video_ai_chatbot_files
                    $savedFiles = get_option('video_ai_chatbot_files', []);
                    //find file with file_id
                    $updatedFiles = array_map(function($file) use ($files, $vectorStoreIds) {
                        if($file['id'] == $files[0]) {
                            return ['id' => $file['id'], 
                                    'vector_store_id' => $vectorStoreIds[0] , 
                                    'file_name' => $file['file_name'], 
                                    'file_content' => $file['file_content']];
                        }
                    }, $savedFiles); 
                           
                    error_log('updatedFiles: ' . json_encode($updatedFiles));
                    update_option('video_ai_chatbot_files', $updatedFiles);     
                } catch(Exception $e) {
                    error_log('error: ' . $e->getMessage());
                    return new WP_REST_Response(['message' => $e->getMessage()], 500);
                }
            } else {
                throw new Exception('Invalid assistant type');
            }
            $response = $this->client->modifyAssistant($id, $data);
            $decoded_res = json_decode($response);
            error_log('decoded_res: ' . $response);
            if(isset($decoded_res->error))
            {
                throw new Exception($decoded_res->error->message);
            } 

            
            return new WP_REST_Response(['message' => 'Assistant updated successfully', 'assistants' => $this->get_assistants_request()], 200);
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
            return new WP_REST_Response(['message' => 'File deleted locally but failed to delete from OpenAI', 'error' => $e->getMessage()], 500);
        }

        return new WP_REST_Response(['message' => 'Transcription deleted successfully', 'deleted_file_id' => $response], 200);
    }

    private function delete_transcription($file_id, $remote = true) {
        $option_name = 'video_ai_chatbot_transcriptions';
        $transcriptions = get_option($option_name, []);

        $deleted_file = $file_id;
        if($remote) {
            // Elimina il file da OpenAI
            $deleted_file = $this->client->deleteFile($file_id);
            $deleted_file = json_decode($deleted_file, true);
            if(isset($deleted_file['error'])) {
                return $file_id;
            }
        }

        $updated_transcriptions = array_filter($transcriptions, function($transcription) use ($file_id) {
            return $transcription['file_id'] !== $file_id;
        });
        
        if (count($transcriptions) === count($updated_transcriptions)) {
            throw new Exception('File ID not found');
        }

        update_option($option_name, array_values($updated_transcriptions));
        return $deleted_file;
    }

    //scrivi la funzione deleteFile che cancella un file da video_ai_chatbot_files
    private function delete_local_file($file_id) {
        $option_name = 'video_ai_chatbot_files';
        $files = get_option($option_name, []);
        $updated_files = array_filter($files, function($file) use ($file_id) {
            return $file['id'] !== $file_id;
        });
                
        if (count($files) === count($updated_files)) {
            throw new Exception('File ID not found');
        }
        update_option($option_name, array_values($updated_files));
        return $file_id;
    }

    private function delete_file($file_id, $remote = true) {
        $deleted_file = $file_id;
        if($remote) {
            // Elimina il file da OpenAI
            $deleted_file = $this->client->deleteFile($file_id);
            $deleted_file = json_decode($deleted_file, true);
            error_log('deleted_file: ' . json_encode($deleted_file));
            if($deleted_file['error']) {
                throw new Exception($deleted_file['error']['message']);
            }
        }

        return $this->delete_local_file($file_id);
    }


    public function upload_file(WP_REST_REquest $request)
    {
        $old_file_id = sanitize_text_field($request->get_param('file_id'));
        if($old_file_id && !is_numeric($old_file_id) ) {
            try {
                $deletedFile = $this->delete_file($old_file_id);
                error_log('deletedFile: ' . json_encode($deletedFile));
            } catch (Exception $e) {
                return new WP_REST_Response(['message' => 'Failed to delete old transcription', 'error' => $e->getMessage()], 500);
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
    
        // Upload the file to OpenAI
        try {
            $response = $this->client->uploadFile([
                'file' => $c_file,
                'purpose' => 'assistants'
            ]);
            $response = json_decode($response, true);
        } catch (Exception $e) {
            return new WP_REST_Response(['message' => $e->getMessage()], 500);
        }
        error_log('response: ' . json_encode($response));

        $files = get_option('video_ai_chatbot_files', []);
        $files = wp_parse_args($files, []);
        $files[] = ['id' => $response['id'], 'vector_store_id' => '' , 'file_name' => $file_name, 'file_content' => $file_content];
        error_log('files: ' . json_encode($files));
        update_option('video_ai_chatbot_files', $files);

        return new WP_REST_Response(['message' => 'File uploaded successfully', 'file_id' => $response['id']], 200);
    }

    public function upload_transcription(WP_REST_Request $request) {
        $old_file_id = sanitize_text_field($request->get_param('file_id'));
        if($old_file_id && !is_numeric($old_file_id) ) {
            try {
                $deletedFile = $this->delete_transcription($old_file_id);
                error_log('deletedFile: ' . json_encode($deletedFile));
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
            $transcriptions = wp_parse_args($transcriptions, []);
            $newTrans  = [
                'file_name' => $file_name,
                'file_id' => $file_id,
                'assistant_id' => $transcription['assistant_id'],
                'assistant_name' => $transcription['assistant_name'],
                'transcription' => $transcription['transcription']
            ];
            $transcriptions[] = $newTrans;
            error_log('transcription: ' . json_encode($newTrans));
            update_option($option_name, $transcriptions);

            if($transcription['assistant_id'] && $file_id) {
                error_log('assistant_id: ' . json_encode($transcription['assistant_id']));
                error_log('file_id: ' . $file_id);
                foreach($transcription['assistant_id'] as $assistant_id) {
                    try {
                        $vector_store_ids = $this->communityopenai->retrieveVectorStoreIdFromAssistantId($assistant_id);
                        //todo 
                        $file = $this->communityopenai->createVectorStoreFiles($vector_store_ids[0], [$file_id]);
                    } catch (Exception $e) {
                        return new WP_REST_Response(['message' => $e->getMessage()], 500);
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

    private function getTranscriptionByFileId($fileId) {
        $option_name = 'video_ai_chatbot_transcriptions';
        $transcriptions = get_option($option_name, []);
        foreach ($transcriptions as $transcription) {
            if ($transcription['file_id'] === $fileId) {
                return $transcription;
            }
        }
        return null;
    }

    private function update_transcription($file_id, $transcription) {
        $option_name = 'video_ai_chatbot_transcriptions';
        $transcriptions = get_option($option_name, []);
        $updated_transcriptions = array_map(function($t) use ($file_id, $transcription) {
            if ($t['file_id'] === $file_id) {
                return $transcription;
            }
            return $t;
        }, $transcriptions);
        update_option($option_name, $updated_transcriptions);
    }

    private function get_functions() {
        return [
            'type' => "function",
            'function' => [
                'name' => 'get_user_courses',
                'description' => 'Get user subscribed courses',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'courses' => [
                            'type' => 'array',
                            'items' => [
                                'type' => 'string'
                            ]
                        ]
                    ]
                ]
            ]
        ];
    }

    private function get_base_url() {
        $protocol = isset($_SERVER['HTTPS']) && 
        $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://';
        $base_url = $protocol . $_SERVER['HTTP_HOST'] . '/';
        return $base_url;
    }

    public function create_assistant(WP_REST_Request $request) {
        $parameters = $request->get_json_params();
        error_log('parameters: ' . json_encode($parameters));
        $name = sanitize_text_field($parameters['name']);
        $prompt = sanitize_textarea_field($parameters['prompt']);
        $type = sanitize_text_field($parameters['type']);
        if(!isset($this->client) || !isset($this->communityopenai)) {
            return new WP_REST_Response(['message' => 'OpenAI client not initialized'], 400);
        }

        try {
            $vectorStoreId = $this->communityopenai->createVectorStore('vs_name_'.str_replace(' ', '_', $name));
                    
            if(!$vectorStoreId) {
                throw new Exception('Failed to create vector store');
            }

            $files = [];
            if($parameters['files'] && count($parameters['files']) > 0) {

                $files = $this->communityopenai->createVectorStoreFiles($vectorStoreId, $parameters['files']); 

                if($type == 'preventivi') {
                    $savedFiles = get_option('video_ai_chatbot_files', []);
                    if(count($savedFiles) > 0) {
                        foreach($parameters['files'] as $file) {
                            foreach($savedFiles as &$savedFile) {
                                if($savedFile['id'] == $file) {
                                    // Aggiorna l'elemento
                                    $savedFile['vector_store_id'] = $vectorStoreId;
                                    break;
                                }
                            }
                        }
                        // Salva l'array aggiornato
                        error_log('savedFiles: ' . json_encode($savedFiles));
                        update_option('video_ai_chatbot_files', $savedFiles);

                    } else {
                        throw new Exception('No files found');
                    }
                }
            }          
        } catch (Exception $e) {
            return new WP_REST_Response(['message' => $e->getMessage()], 500);
        }


        $data = [
            'model' => 'gpt-3.5-turbo',
            'name' => $name,
            'description' => $name,
            'instructions' => $prompt,
            'tools' => [
                [
                    'type' => "file_search"
                ]                
            ],
            'tool_resources'=> [ 'file_search' => [ 'vector_store_ids' => [$vectorStoreId] ]],
            'metadata' => [
                'type' => $type,
                'url' => $this->get_base_url()
            ]
        ];

        if($type == 'trascrizioni') {
            array_push($data['tools'], $this->get_functions());
        }
    
        try {
            $response =  $this->client->createAssistant($data);
            $decoded_res = json_decode($response,true);          
            if ($response && isset($decoded_res['id'])) {
                try {
                    if($files && count($files) > 0 && $type == 'trascrizioni') {
                        foreach($parameters['files'] as $file) {
                            $transcription = $this->getTranscriptionByFileId($file);
                            if($transcription) {
                                array_push($transcription['assistant_id'], $decoded_res['id']);
                                $this->update_transcription($file, $transcription);
                            }
                        }
                    }
                } catch (Exception $e) {
                    return new WP_REST_Response(['message' => $e->getMessage()], 500);
                }   
                return new WP_REST_Response(['message' => 'Assistant created successfully', 'assistant_id' => $decoded_res['id']], 200);
            } else {
                return new WP_REST_Response(['message' => 'Failed to create assistant'], 500);
            }
        } catch (Exception $e) {
            return new WP_REST_Response(['message' => $e->getMessage()], 500);
        }
    }

    public function get_assistants() {
        // Recupera gli assistenti utilizzando le API di OpenAI
        if(!isset($this->client)) {
            return [];
        }
        $query = ['limit' => 100];
        $assistants = $this->client->listAssistants($query);
        $assistants = json_decode($assistants, true);
        if(isset($assistants['error'])) {
            throw new Exception($assistants['error']['message']);
        }
        error_log('assistants: ' . json_encode($assistants['data']));
        $filtered = array_filter($assistants['data'], function($v) {
            $url = $v['metadata']['url'];
            error_log("v: "  . json_encode($url));
            error_log("base_url: "  . $this->get_base_url());	
            return !isset($url) || $url == $this->get_base_url();
        });
        error_log('filtered: ' . json_encode($filtered));
        return array_values($filtered);
    }

    public function get_assistants_request() {
        if(!isset($this->client)) {
            return new WP_REST_Response(['success' => false, 'message' => 'OpenAI client not initialized'], 400);
        }
        try {
            $assistants = $this->get_assistants();
            return new WP_REST_Response($assistants, 200);
        } catch (Exception $e) {
            return new WP_REST_Response(['message' => $e->getMessage()], 500);
        }
    }

    public function handle_wa_chatbot_request($input_text, $assistant_id, $phoneNumber) {
        $result = $this->handle_chatbot_request($input_text, $assistant_id, $phoneNumber);
        // if($result->error) {
        //     return new WP_REST_Response(['message' => $result->message], 500);
        // }
        // $cb($result);
        return $result; // new WP_REST_Response(['message' => $result->message], 200);
    }

    public function handle_wp_chatbot_request(WP_REST_Request $request) {
        $parameters = $request->get_json_params();
        $input_text = $parameters['message'];
        $assistant_id = $parameters['assistant_id'];
        $postprompt = $parameters['postprompt'];

        $result = $this->handle_chatbot_request($input_text, $assistant_id, $_COOKIE['video_ai_chatbot_session_id'], $postprompt);
        if($result['error']) {
            return new WP_REST_Response(['message' => $result['message']], 500);
        }
        return new WP_REST_Response(['message' => $result['message']], 200);
    }

    private function replace_placeholders($text, $variables) {
        foreach ($variables as $key => $value) {
            $placeholder = '${' . $key . '}';
            $text = str_replace($placeholder, $value, $text);
        }
        return $text;
    }
    
    private function get_thread_id_for_user($user_id, $userSessionId, $assistant_id)
    {
        $threadId = null;
        if ($user_id != 0) {
            $userThreads  = json_decode(get_user_meta($user_id, 'openai_thread_id', true),true);
            error_log('userThreads: ' . json_encode($userThreads));
        } else if (isset($userSessionId)) {
            //$threadId = get_option('thread_id' . $userSessionId);
            $threads = get_option('video_ai_chatbot_threads', []);
            if (isset($threads[$userSessionId])) {
                $userThreads = $threads[$userSessionId];
            }
        }
        if(isset($userThreads) && isset($userThreads[$assistant_id])) {
            $threadId = $userThreads[$assistant_id];
        }
        //ritorna un il threadId insieme true se l'user_id != 0, true altrimenti
        return $threadId;

    }

    private function get_last_assistant_used_for_user($user_id) {
        if ($user_id != 0) {
            $userAssistant = get_user_meta($user_id, 'video_ai_chatbot_last_assistant', true);
            return $userAssistant;
        } else {
            $sessions = get_option('video_ai_chatbot_sessions', []);
            $userSessionId = $_COOKIE['video_ai_chatbot_session_id'];
            if (isset($sessions[$userSessionId])) {
                $sessionInfo = $sessions[$userSessionId];
                if(isset($sessionInfo['last_assistant'])) {
                    return $sessionInfo['last_assistant'];
                }
            }
        }
        return false;
    }

    private function set_last_assistant_used_for_user($user_id, $assistant_id) {
        if ($user_id != 0) {
            update_user_meta($user_id, 'video_ai_chatbot_last_assistant', $assistant_id);
        } else {
            $sessions = get_option('video_ai_chatbot_sessions', []);
            $userSessionId = $_COOKIE['video_ai_chatbot_session_id'];
            $sessionInfo = [
                'last_assistant' => $assistant_id
            ];
            $sessions[$userSessionId] = $sessionInfo;
            update_option('video_ai_chatbot_sessions', $sessions);
        }
    }

    private function search_and_delete_thread_id_for_user($user_id) {
        if ($user_id != 0) {
            delete_user_meta($user_id, 'openai_thread_id');
        } else {
            $threads = get_option('video_ai_chatbot_threads', []);
            $userSessionId = $_COOKIE['video_ai_chatbot_session_id'];
            if (isset($threads[$userSessionId])) {
                unset($threads[$userSessionId]);
                update_option('video_ai_chatbot_threads', $threads);
            }
        }
    }

    public function cancel_all_runs() {
        $user_id = apply_filters('determine_current_user', true);
        $assistant_id = $this->get_last_assistant_used_for_user($user_id);
        $thread_id = $this->get_thread_id_for_user($user_id, $_COOKIE['video_ai_chatbot_session_id'], $assistant_id);
        $query = ['limit' => 50];
        $runs = $this->client->listRuns($thread_id, $query);
        $runs = json_decode($runs, true);
        
        if(!isset($runs)) {
            return new WP_REST_Response(['message' => 'No runs found'], 500);
        }

        if(count($runs['data']) > 0) {
            foreach ($runs['data'] as $run) {
                if(!($run['status'] == 'completed' || $run['status'] == 'failed' || $run['status'] == 'expired')) {
                    $result = $this->client->cancelRun($thread_id, $run['id']);
                    error_log('result: ' . json_encode($result));
                }
            }
        }
        // if (isset($runs['error'])) {
        //     return new WP_REST_Response(['message' => $runs['error']['message']], 500);
        // }
        // foreach ($runs['data'] as $run) {
        //     $this->client->cancelRun($thread_id, $run['id']);
        // }
        return new WP_REST_Response(['message' => 'Runs cancelled successfully'], 200);
    }


    public function handle_chatbot_request($input_text, $assistant_id, $userSessionId, $userpostprompt = null) {
        if (!isset($this->client)) {
            return new WP_REST_Response(['message' => 'OpenAI client not initialized'], 400);
        }

        // if($userpostprompt != null && $userpostprompt != "" && $userpostprompt == "true") {
        //     return new WP_REST_Response(['assistant_choice' => true], 200);
        // } 

        try {
            if (!isset($assistant_id)) {
                throw new Exception('Assistant not found');
            }

            $user_id = apply_filters('determine_current_user', true);
            $this->set_last_assistant_used_for_user($user_id, $assistant_id);

            $additional_instructions = '';	
            if($user_id != 0) {
                $user_display_name = get_userdata($user_id)->display_name;

                error_log('user_display_name: ' . $user_display_name);
    
                if ($user_display_name && isset($userpostprompt) && $userpostprompt == "true") {
                    $additional_instructions = "Ciao mi chiamo " . $user_display_name . ". Tu chi sei?";
                }
            }
            $threadId = $this->get_thread_id_for_user($user_id, $userSessionId, $assistant_id);

            $thread = $this->get_or_create_thread($threadId, $assistant_id);

            $msgData = [
                'role' => 'user',
                'content' => $additional_instructions != "" ? $additional_instructions : $input_text,
                'metadata' => [
                    'postprompt' => isset($userpostprompt) ? $userpostprompt : 'false'
                ]
            ];

            $message = $this->client->createThreadMessage($thread['id'], $msgData);

            error_log('message: ' . json_encode($message));

            if (!$message) {
                throw new Exception('no message received');
            }
            if (isset($message->error)) {
                throw new Exception($message['error']['message']);
            }

            $runData = [
                'assistant_id' => $assistant_id,
            ];

            // if($additional_instructions != "") {
            //     $runData['additional_instructions'] = $additional_instructions;
            // }

            $run = $this->client->createRun($thread['id'], $runData);
            $run = json_decode($run, true);

            if (isset($run['error'])) {
                $run = $run['error']['message'];
                throw new Exception($run);
            }

            $start_time = time();
            // Wait for the response to be ready
            while (($run['status'] == "in_progress" || $run['status'] == "queued") || $run['status'] == "requires_action" && (time() - $start_time) < 5000) {
                error_log('run status: ' . json_encode($run['status']));
                sleep(1);
                if($run['status'] == "requires_action" ) {
                    try {
                        $run = $this->handle_assistant_function_call($user_id, $run);
                        error_log('run: FUNCTION CALL ' . json_encode($run));
                    } catch (Exception $e) {
                        throw new Exception($e->getMessage());
                    }
                } else {
                    $run = $this->client->retrieveRun($thread['id'], $run['id']);
                    $run = json_decode($run, true);
                    error_log('run: NORMAL ' . json_encode($run));	
                }
            }

            if(!isset($run)) {
                throw new Exception('Failed to handle run function call');
            }

            if ($run['status'] == "in_progress" || $run['status'] == "queued") {
                throw new Exception('Timeout');
            }

            $query = ['limit' => 10];
            $messages = $this->client->listThreadMessages($thread['id'], $query);
            $messages = json_decode($messages, true);

            error_log('messages: ' . json_encode($messages));

            if (!$messages) {
                throw new Exception('no messages received');
            }

            if (isset($messages['error'])) {
                throw new Exception($messages['error']['message']);
            }

            if (count($messages['data'][0]['content'][0]['text']['annotations']) > 0) {
                $annotations = $messages['data'][0]['content'][0]['text']['annotations'];
                $messages['data'][0]['content'][0]['text']['annotations'] = $annotations;
            }

            $data = [
                "error" => false,
                "message" => $messages['data'][0]['content'][0]['text'],
            ];

            return $data;
        } catch (Exception $e) {
            $data = [
                "error" => true,
                "message" => $e->getMessage(),
            ];
            return $data;
        }
    }

    private function handle_assistant_function_call($userId, $run) {
        error_log('handle_assistant_function_call run: ' . json_encode($run));
        if (!isset($this->client)) {
            return null;
        }
        
        if(isset($run['required_action']['submit_tool_outputs']) && isset($run['required_action']['submit_tool_outputs']['tool_calls'])) {
            $toolCalls = $run['required_action']['submit_tool_outputs']['tool_calls'];
            $outputs = array_map(function($toolCall) use ($userId) {
                if($toolCall['function']['name'] == 'get_user_courses') {
                    error_log('Called fucntion: ' . $toolCall['function']['name']);
                    return [
                        'tool_call_id' => $toolCall['id'],
                        'output' =>  json_encode($this->tutorUtils->get_active_courses_by_user($userId))
                    ];
                }	
            }, $toolCalls);

            $run = $this->communityopenai->submit_function_call_output($run['id'], $run['thread_id'], $outputs);
            //$run = $run->toArray();
            if(isset($run['error'])) {
                throw new Exception($run['error']['message']);
            }
            return $run; 
        }
        return $run;
    }

    private function get_or_create_thread($threadId, $assistant_id) {
        if (isset($threadId)) {
            $thread = $this->client->retrieveThread($threadId);
            $thread = json_decode($thread, true);

            if (isset($thread->error)) {
                $thread = false;
            }
        }

        if (!$thread || isset($thread['error'])) {
            $mData = [
                'messages' => [
                    [
                        'role' => 'assistant',
                        'content' => get_option('video_ai_chatbot_options')['video_ai_chatbot_welcome_message_field']
                    ]
                ]
            ];

            $thread = $this->client->createThread($mData);
            $thread = json_decode($thread, true);
            if (isset($thread['error']) || !isset($thread['id'])) {
                throw new Exception($thread['error']['message']);
            }

            $user_id = apply_filters('determine_current_user', true);
            if ($user_id != 0) {
                $userThreads = json_decode(get_user_meta($user_id, 'openai_thread_id',true), true);
                $userThreads[$assistant_id] = $thread['id'];
                update_user_meta($user_id, 'openai_thread_id', json_encode($userThreads));
            }
            if (isset($userSessionId)) {
                $threads = get_option('video_ai_chatbot_threads', []);
                $threads[$userSessionId][$assistant_id] = $thread['id'];
                update_option('video_ai_chatbot_threads', $threads);
            }
        }

        if (!isset($thread['id'])) {
            throw new Exception('Failed to create thread');
        }

        return $thread;
    }

    public function handle_thread_deletion() {
        // $user_id = get_current_user_id();
        $user_id = apply_filters( 'determine_current_user', true );
        echo '<h1>'.  $user_id  . '</h1>';
        if($user_id != 0) {
            $threadId = $this->search_and_delete_thread_id_for_user($user_id);
        }
		if ($threadId) {
			$result = $this->client->deleteThread($threadId);
			if ($result) {
				delete_user_meta($user_id, 'openai_thread_id');
				add_settings_error('openai_cancel_thread', 'openai_thread_deleted', 'Thread cancellato con successo.', 'updated');
			} else {
				add_settings_error('openai_cancel_thread', 'openai_thread_deletion_failed', 'Cancellazione del thread fallita.', 'error');
			}
		}
	
		return new WP_REST_Response(json_decode($result), 200);
	}

    public function delete_unused_vector_stores() {
        $vectorStores = $this->communityopenai->retrieveVectorStores();
        //$response = json_decode($response, true);
        //$vectorStores = $response['data'];
        try{ 
            $vectorStores = array_filter($vectorStores, function($vectorStore) {
                error_log("files count" . $vectorStore->fileCounts->total);
                error_log("checkIfStoredAssistantsContainsVectorStore" . $this->checkIfStoredAssistantsContainsVectorStore($vectorStore->id));
                return $vectorStore->fileCounts->total === 0 && $this->checkIfStoredAssistantsContainsVectorStore($vectorStore->id);
            });
        } catch (Exception $e) {
            return new WP_REST_Response(['message' => $e->getMessage()], 500);
        }
        foreach($vectorStores as $vectorStore) {
            try {
                $this->communityopenai->deleteVectorStore($vectorStore->id);
            } catch (Exception $e) {
                return new WP_REST_Response(['message' => $e->getMessage()], 500);
            }
        }
        return new WP_REST_Response(['message' => 'Empty vector stores deleted successfully'], 200);
    }

    private function checkIfStoredAssistantsContainsVectorStore($vectorStoreId) {
        $assistants = $this->get_assistants();
        if(isset($assistants['error'])) {
            throw new Exception($assistants['error']['message']);
        }
        if(!$assistants || !isset($assistants['data'])) {
            throw new Exception('No assistants found');
        }
        $assistants = $assistants['data'];
        error_log('assistants: ' . json_encode($assistants));   
        $assistants = array_filter($assistants, function($assistant) use ($vectorStoreId) {
            return in_array($vectorStoreId, $assistant['tool_resources']['file_search']['vector_store_ids']);
        });
        return count($assistants) > 0;
    }


}

