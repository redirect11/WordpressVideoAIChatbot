<?php


use Orhanerday\OpenAi\OpenAi;


class Video_Ai_OpenAi {
    private $client;
    private $communityopenai;
    private $tutorUtils;
    private $wa = [];
    private $ig = [];

    /**
	 * Initialize the class and set its properties.
	 *
	 * @since    1.0.0
	 * @param      string    $plugin_name       The name of this plugin.
	 * @param      string    $version    The version of this plugin.
	 */
	public function __construct($communityopenai) {
        $options = get_option('video_ai_chatbot_options');
       

        //inizializzare tutte le options utilizzate in questa classe
        if($options) {
            $api_key = isset($options['openai_api_key_field']) ? $options['openai_api_key_field'] :'';
            if ($api_key) {
                $this->activate($api_key);
            }
            $this->communityopenai = $communityopenai;
            $this->tutorUtils = new CustomTutorUtils();
            $waAssistants = get_option('video_ai_whatsapp_assistants', array());
            if(isset($waAssistants) && count($waAssistants) > 0) {
                foreach($waAssistants as $assistant) {
                    $this->wa[$assistant['assistant']] = new Video_Ai_Chatbot_Wa_Webhooks($this, $assistant['token'], $assistant['outgoingNumberId'], $assistant['assistant']);
                }
            }
            $igAssistants = get_option('video_ai_instagram_assistants', array());
            if(isset($igAssistants) && count($igAssistants) > 0) {
                foreach($igAssistants as $assistant) {
                    $this->ig[$assistant['assistant']] = new Video_Ai_Chatbot_Instagram($this, $assistant['token'], $assistant['instagramId'], $assistant['assistant']);
                }
            }
        }
        //$this->delete_transcriptions_content();
	}

    public function activate($apiKey)
    {
        if(!$this->is_active() && $apiKey) {
            $this->client = new OpenAi($apiKey);
            $this->client ->setAssistantsBetaVersion("v2");
        }
    }

    public function is_active() {
        return isset($this->client);
    }

    public function get_handover_notification_numbers() {
        $options = get_option('video_ai_chatbot_notification_numbers',[]);
        return new WP_REST_Response($options, 200);
    }

    //implementa la funzione set_handover_notification_numbers
    public function set_handover_notification_numbers($params) {
        $options = $params['video_ai_chatbot_notification_numbers'] ? json_decode($params['video_ai_chatbot_notification_numbers'], true) : [];
        update_option('video_ai_chatbot_notification_numbers', $options);
        return new WP_REST_Response(['message' => 'Handover notification numbers updated successfully'], 200);
    }

    public function get_handover_notification_emails() {
        $options = get_option('video_ai_chatbot_notification_emails',[]);
        return new WP_REST_Response($options, 200);
    }

    //implementa la funzione set_handover_notification_numbers
    public function set_handover_notification_emails($params) {
        $options = $params['video_ai_chatbot_notification_emails'] ? json_decode($params['video_ai_chatbot_notification_emails'], true) : [];
        update_option('video_ai_chatbot_notification_emails', $options);
        return new WP_REST_Response(['message' => 'Handover notification emails updated successfully'], 200);
    }

    //Una funzione che verifica se un thread è un thread di whatsapp. Accedi ai metadati del tred per vedere se il campo 'wa' esiste. Richiedi i trhread a openai
    public function is_whatsapp_thread($thread_id) {
        $thread = $this->client->retrieveThread($thread_id);
        $thread = json_decode($thread, true);
        if(isset($thread['metadata']['wa'])) {
            return true;
        }
        return false;
    }

    public function is_ig_thread($thread_id) {
        $thread = $this->client->retrieveThread($thread_id);
        $thread = json_decode($thread, true);
        if(isset($thread['metadata']['ig'])) {
            return true;
        }
        return false;
    }


    private function get_assistant_name_from_assistant_id($assistantId) {
        $assistants = $this->get_assistants();
        foreach($assistants as $assistant) {
            if($assistant['id'] === $assistantId) {
                return $assistant['name'];
            }
        }
        return null;
    }

    public function get_file($vectorStoreId) {
        //retrieve file from local get_all_options
        $openAiFiles = $this->communityopenai->retrieveVectorStoreFiles($vectorStoreId);
        $savedFiles = get_option('video_ai_chatbot_files', []);
        $foundFile = null;

        //search for the only file present in the vector store that is saved in video_ai_chatbot_files
        foreach($savedFiles as $savedFile) {
            foreach($openAiFiles['data'] as $file) {
                if(isset($savedFile['vector_store_id']) && $savedFile['vector_store_id'] == $vectorStoreId) {
                    // Aggiungi il contenuto del file alla risposta
                    $foundFile = $savedFile;
                    break;
                }
            }
        }
        return $foundFile;
    }

    public function get_gpt_models() {
        $response = $this->client->listModels();
        $response = json_decode($response, true);
        return $response;
    }

    private function get_all_registered_user_threads() {
        $users = get_users();
        $allThreads = [];
        foreach ($users as $user) {
            $user_id = $user->ID;
            $userThreads =  get_user_meta($user_id, 'openai_thread_id', true);
            
            foreach ($userThreads as $assistantId => $threadId) {
                $thread = $this->client->retrieveThread($threadId);
                $thread = json_decode($thread, true);
                $is_handover_thread = isset($thread['metadata']['handover']) && $thread['metadata']['handover'] === "true";
                $allThreads[] = [
                    'thread_id' => $threadId,
                    'assistant_id' => $assistantId,
                    'user_id' => $user_id,
                    'userName' => $user->user_login,
                    'assistant_name' => $this->get_assistant_name_from_assistant_id($assistantId),
                    'messages' => [],

                ];
            }
        }
        $threadsToSend = [];
        foreach ($allThreads as $thread) {
            $query = ['limit' => 50];
            try {
                $messages = $this->client->listThreadMessages($thread['thread_id'], $query);
                $messages = json_decode($messages, true);
                //check if metadata is set and contains handover 
                $messages = $messages['data'];
                $remappedMessages = array_map(function($entry) {
                    return [
                        'sender' => $entry['role'],
                        'text' => $entry['content'][0]['text']['value'],
                        'timestamp' => $entry['created_at'],
                        'handover_message' => isset($entry['metadata']) && isset($entry['metadata']['handover_message']) && $entry['metadata']['handover_message'] === "true",
                        'type' => isset($entry['metadata']) && isset($entry['metadata']['wa']) ? 
                                    'wa' 
                                    : (isset($entry['metadata']) && isset($entry['metadata']['ig']) ? 'ig' : 'chat')
                    ];
                }, $messages);
                usort($remappedMessages, function($a, $b) {
                    return $b['timestamp'] < $a['timestamp'] ? 1 : -1;
                });
                $thread['messages'] = $remappedMessages;
                $threadsToSend[] = $thread;
            } catch (Exception $e) {
                error_log('Error getting messages for thread ' . $thread['thread_id'] . ' for user ' . $thread['user_id'] . ': ' . $e->getMessage());
            }
        }
        return new WP_REST_Response($threadsToSend, 200);
    }

    //implementa la funzione get_all_thread_messages
    public function get_all_thread_messages() {
        // Cicla tutti gli utenti per ottenere i thread degli utenti
        $start_time = microtime(true); 
        $users = get_users();
        $allThreads = [];
        foreach($users as $user) {
            $user_id = $user->ID;
            $userThreads =  get_user_meta($user_id, 'openai_thread_id', true);
            $userThreads = json_decode($userThreads, true);
            //$userThreads = json_decode($userThreads, true);
            if(isset($userThreads) && count($userThreads) > 0) {	
                foreach($userThreads as $assistantId => $threadId) {
                    $thread = $this->client->retrieveThread($threadId);
                    $thread = json_decode($thread, true);
                    $is_handover_thread = isset($thread['metadata']['handover']) && $thread['metadata']['handover'] === "true";
                    $allThreads[] = [
                        'thread_id' => $threadId,
                        'assistant_id' => $assistantId,
                        'user_id' => $user_id,
                        'userName' => $user->user_login,
                        'assistantName' => $this->get_assistant_name_from_assistant_id($assistantId),
                        'messages' => [],
                        'is_handover_thread' => $is_handover_thread,
                        'type' => 'chat'
                    ];
                }
            }
        }

        $middle_time = microtime(true) - $start_time; 
        error_log('get_all_thread_messages_request middle_time: ' . $middle_time);
        
        // Ottieni i thread delle sessioni
        $waAssistants = get_option('video_ai_whatsapp_assistants', []);
        $igAssistants = get_option('video_ai_instagram_assistants', []);

        $sessionsThreads = get_option('video_ai_chatbot_threads', []);
        if (isset($sessionsThreads)) {
            foreach ($sessionsThreads as $sessionId => $session) {
                foreach ($session as $assistantId => $threadId) {
                    $waAssistant = array_filter($waAssistants, function($assistant) use ($assistantId) {
                        return $assistant['assistant'] === $assistantId;
                    });

                    $igAssistant = array_filter($igAssistants, function($assistant) use ($assistantId) {
                        return $assistant['assistant'] === $assistantId;
                    });
                    
                    $thread = $this->client->retrieveThread($threadId);
                    $thread = json_decode($thread, true);
                    error_log('get_all_thread_messages_request thread: ' . json_encode($thread));
                    $is_wa_thread = isset($thread['metadata']['wa']);
                    $is_ig_thread = isset($thread['metadata']['ig']);
                    $is_anoymous_thread = !isset($thread['metadata']['wa']) && !isset($thread['metadata']['ig']);
                    $is_handover_thread = isset($thread['metadata']['handover']) && $thread['metadata']['handover'] === "true";
                    if($is_wa_thread) {
                        $allThreads[] = [
                            'thread_id' => $threadId,
                            'assistantName' => $this->get_assistant_name_from_assistant_id($assistantId),
                            'user_id' => $sessionId,
                            'userName' => '+' . $sessionId,
                            'outgoingNumberId' => $waAssistant[0]['outgoingNumberId'],
                            'messages' => [],
                            'is_handover_thread' => $is_handover_thread,
                            'type' => 'wa'
                        ];
                    } else if($is_ig_thread) {
                        $user_profile = $this->ig[$assistantId]->get_user_profile($sessionId); 
                        $allThreads[] = [
                            'thread_id' => $threadId,
                            'assistantName' => $this->get_assistant_name_from_assistant_id($assistantId),
                            'user_id' => $sessionId,
                            'userName' => $sessionId,
                            'instagramId' => $igAssistant[0]['instagramId'],
                            'messages' => [],
                            'is_handover_thread' => $is_handover_thread,
                            'type' => 'ig',
                            'user_profile' => $user_profile
                        ];
                    } 
                    else if($is_anoymous_thread) {
                        $allThreads[] = [
                            'thread_id' => $threadId,
                            'assistantName' => $this->get_assistant_name_from_assistant_id($assistantId),
                            'user_id' => $sessionId,
                            'userName' => $sessionId,
                            'messages' => [],
                            'is_handover_thread' => $is_handover_thread,
                            'type' => 'anon'
                        ];
                    }
                }
            }
        }
    
        // Recupera i messaggi per ogni thread
        $threadsToSend = [];
        foreach ($allThreads as $thread) {
            $query = ['limit' => 50];
            try {
                $messages = $this->client->listThreadMessages($thread['thread_id'], $query);
                $messages = json_decode($messages, true);
                if(isset($messages['error'])) {
                    error_log('get_all_thread_messages_request error: ' . $messages['error']['message']);
                    continue;
                }

                //check if metadata is set and contains handover 
                $messages = $messages['data'];
                $remappedMessages = array_map(function($entry) use ($thread) {
                    $message = [
                        'sender' => $entry['role'],
                        'text' => $entry['content'][0]['text']['value'],
                        'timestamp' => $entry['created_at'],
                        'handover_message' => isset($entry['metadata']) && isset($entry['metadata']['handover_message']) && $entry['metadata']['handover_message'] === "true",
                        'type' => $thread['type']
                    ];

                    if($message['type'] === 'wa') {
                        $message['outgoingNumberId'] = $thread['outgoingNumberId'];
                    } else if($message['type'] === 'ig') {
                        $message['instagramId'] = $thread['instagramId'];
                        //$message['sender']
                    }

                    return $message;
                }, $messages);
                usort($remappedMessages, function($a, $b) {
                    return $b['timestamp'] < $a['timestamp'] ? 1 : -1;
                });
                $thread['messages'] = $remappedMessages;
                $threadsToSend[] = $thread;
            } catch (Exception $e) {
                error_log('Error getting messages for thread ' . $thread['thread_id'] . ' for user ' . $thread['user_id'] . ': ' . $e->getMessage());
            }
        }
        $end_time = microtime(true) - $start_time;
        error_log('get_all_thread_messages_request end_time: ' . $end_time);
        return new WP_REST_Response($threadsToSend, 200);
    }

    public function get_current_user_thread_message() {
        $user_id = apply_filters('determine_current_user', true);
        if($user_id == 0) {
            $user = wp_get_current_user();
            $user_id = isset($user->ID) ? $user->ID : null;
        }
        $sessionId = isset($_COOKIE['video_ai_chatbot_session_id']) ? $_COOKIE['video_ai_chatbot_session_id'] : null;


        $assistant_id = $this->get_last_assistant_used_for_user($user_id);
        if(!isset($assistant_id) || !$assistant_id) {
            return new WP_REST_Response(['message' => 'Assistant ID not found for current_user_thread'], 400);
        }

        $thread_id = $this->get_thread_id_for_user($user_id, $sessionId, $assistant_id);
        $thread = $this->client->retrieveThread($thread_id);
        $thread = json_decode($thread, true);

        $messages = $this->get_thread_messages($assistant_id);
        $messages = !is_array($messages) ? json_decode($messages, true) : $messages;    

        if(isset($messages['error'])) {
            return new WP_REST_Response(['message' => 'Thread not found'], 200);
        }
            
        //leggi il campo created_at dell'ultimo messaggio e se è passato più di 15 minuti da allora, allora setta handover = "false"
        $handover = false;
        if( isset($thread['metadata']['handover']) && $thread['metadata']['handover'] === "true") {
            $handover = true;
            $lastMessage = $messages['data'][0];
            $lastMessageCreatedAt = $lastMessage['created_at'];
            $currentTime = time();
            $diff = $currentTime - $lastMessageCreatedAt;
            if($diff > 900) {
                $this->set_thread_handover($thread_id, false);
                $handover = false;
            }
        } 

        // Se non esiste un thread per l'utente corrente restituisci un errore
        if (!$messages) {
            return new WP_REST_Response(['message' => 'Thread not found'], 404);
        }
        $threadData = [
            'thread_id' => $thread_id,
            'assistant_id' => $assistant_id,
            'user_id' => isset($user_id) ? $user_id : $sessionId,
            'userName' => isset($user->user_login) ? $user->user_login : $sessionId,
            'assistant_name' => $this->get_assistant_name_from_assistant_id($assistant_id),
            'messages' => $messages,
            'is_handover_thread' => $handover,
        ];
        // Restituisci il thread dell'utente corrente
        return new WP_REST_Response($threadData, 200);
    }

    // Implementa la funzione get_thread_request per l'endpoint get-thread 
    public function get_thread_messages_for_thread_id($thread_id) {
        // Ottieni l'ID dell'utente corrente
        if( $thread_id) { 
            $messages = $this->get_thread_messages(null, $thread_id);
            $messages = json_decode($messages, true);
            if(!isset($messages['data'])) {
                return new WP_REST_Response(['message' => 'Thread not found'], 404);
            }
            $remappedMessages = array_map(function($entry) {
                $data = [
                    'sender' => $entry['role'],
                    'text' => $entry['content'][0]['text']['value'],
                    'timestamp' => $entry['created_at'],
                    'type' => isset($entry['metadata']) && isset($entry['metadata']['wa']) ? 
                                    'wa' 
                                    : (isset($entry['metadata']) && isset($entry['metadata']['ig']) ? 'ig' : 'chat')
                ];
                if(isset($entry['metadata']) && isset($entry['metadata']['handover_message']) && $entry['metadata']['handover_message'] === "true") {
                    $data['handover_message'] = true;
                }
                return $data;
            }, $messages['data']);	
            usort($remappedMessages, function($a, $b) {
                return $b['timestamp'] < $a['timestamp'] ? 1 : -1;
            });
            $messages = $remappedMessages;

        } else {
            $user_id = apply_filters('determine_current_user', true);
            if($user_id == 0) {
                $user = wp_get_current_user();
                $user_id = isset($user->ID) ? $user->ID : null;
            }

            $assistant_id = $this->get_last_assistant_used_for_user($user_id);
    
            if(!isset($assistant_id) || !$assistant_id) {
                return new WP_REST_Response(['message' => 'Assistant ID not found for get_thread_messages_request'], 400);
            }
    
            $messages = $this->get_thread_messages($assistant_id);
            $messages = json_decode($messages, true);
        }


        
        // Se non esiste un thread per l'utente corrente restituisci un errore
        if (!$messages) {
            return new WP_REST_Response(['message' => 'Thread not found'], 404);
        }
        // Restituisci il thread dell'utente corrente
        return new WP_REST_Response($messages, 200);
    }


    public function get_thread_messages($assistantId, $thread_id = null) {
        if(!isset($thread_id)) {
            $user_id = apply_filters('determine_current_user', true);
            if($user_id == 0) {
                $user = wp_get_current_user();
                $user_id = isset($user->ID) ? $user->ID : null;
            }
            $userSessionId =  isset($_COOKIE['video_ai_chatbot_session_id']) ? $_COOKIE['video_ai_chatbot_session_id'] : null;
            $thread_id = $this->get_thread_id_for_user($user_id, $userSessionId, $assistantId);
            if(!isset($thread_id) || !$thread_id) {
                return [];
            }
        }
        $query = ['limit' => 50];
        $messages = $this->client->listThreadMessages($thread_id, $query);
        return $messages;
    }

    public function set_all_options($params) {
        // Salva le opzioni
        if(isset($params['video_ai_whatsapp_assistants'])) {
            $waAssistants = json_decode($params['video_ai_whatsapp_assistants'], true);
            unset($params['video_ai_whatsapp_assistants']);
            if(is_array($waAssistants) && count($waAssistants) > 0) {
                $this->wa = [];
                foreach($waAssistants as $assistant) {
                    $this->wa[$assistant['assistant']] = new Video_Ai_Chatbot_Wa_Webhooks($this, $assistant['token'], $assistant['outgoingNumberId'], $assistant['assistant']);
                }
            }
        }
        if(isset($params['video_ai_instagram_assistants'])) {
            $igAssistants = json_decode($params['video_ai_instagram_assistants'], true);
            unset($params['video_ai_instagram_assistants']);
            if(is_array($igAssistants) && count($igAssistants) > 0) {
                $this->ig = [];
                foreach($igAssistants as $assistant) {
                    $this->ig[$assistant['assistant']] = new Video_Ai_Chatbot_Instagram($this, $assistant['token'], $assistant['instagramId'], $assistant['assistant']);
                }
            }
        }
        $updated = update_option('video_ai_chatbot_options', $params);
        $updatedWa = update_option('video_ai_whatsapp_assistants', $waAssistants);
        $updatedIg = update_option('video_ai_instagram_assistants', $igAssistants);
        return $updated && $updatedWa;
    }
    
    public function get_css_themes() {
        $themes_dir = plugin_dir_path(__FILE__) . '../public/css/themes'; // Percorso alla cartella dei temi CSS
        $themes_files = scandir($themes_dir);
        $css_themes = [];

        foreach ($themes_files as $file) {
            if (pathinfo($file, PATHINFO_EXTENSION) === 'css') {
                $css_themes[] = $file;
            }
        }
    
        return $css_themes;
    }
    

    public function get_all_options() {
        $options = get_option('video_ai_chatbot_options');
        $waAssistants = get_option('video_ai_whatsapp_assistants', []);
        $igAssistants = get_option('video_ai_instagram_assistants', []);

        if (false === $options) {
            return new WP_Error('no_options', 'Nessuna opzione trovata', ['status' => 404]);
        }
        // Ciclo tutte le options. Quelle che sono relative a whatsapp e openai possono 
        // essere restituite solo se l'utente ha i permessi necessari
        foreach ($options as $key => $value) {
            if (strpos($key, 'openai_') === 0) {
                if (!current_user_can('manage_options')) {
                    unset($options[$key]);
                    unset($options['video_ai_whatsapp_assistants']);
                    unset($options['video_ai_instagram_assistants']);
                }
            }
        }
        if(current_user_can('manage_options')) {
            $options['video_ai_whatsapp_assistants'] = $waAssistants;
            $options['video_ai_instagram_assistants'] = $igAssistants;
        }
        return $options;
    }



    public function get_transcriptions() {
        $option_name = 'video_ai_chatbot_transcriptions';
        $transcriptions = get_option($option_name, []);
        $transcriptions = wp_parse_args($transcriptions, []);
        return $transcriptions;
    }

    public function delete_local_files_data() {
        //delete transcriptions and files. Only local, no OPenai
        $deleteTranscriptions = delete_option("video_ai_chatbot_transcriptions");
        //$deleteFiles = delete_option("video_ai_chatbot_files");
        if($deleteTranscriptions) {
            return true;
        } else {
            throw new Exception('Failed to delete local files and transcriptions');
        }
    }

    //Funzione callback per cancellare un assistente
    public function delete_assistant($assistant_id) {
        if(!$assistant_id) {
            throw new Exception('Assistant ID not found for delete_assistant');
        }

        $response = $this->client->deleteAssistant($assistant_id);
        $decoded_res = json_decode($response);
        if(isset($decoded_res->error))
        {
            throw new Exception($decoded_res->error->message);
        } 
        return $this->get_assistants();
    }


    public function handle_retrieve_vector_store_files($vectorStoreId, $parameters) {
        $after = isset($parameters['after']) ? $parameters['after'] : null;
        $before = isset($parameters['before']) ? $parameters['before'] : null;
        $response = $this->communityopenai->retrieveVectorStoreFiles($vectorStoreId, $after, $before);

        if(!isset($response)) {
            error_log('Failed to retrieve vector store files with ID: ' . $vectorStoreId);
            throw new Exception('Failed to retrieve vector store files with ID ' . $vectorStoreId);
        }

        if(isset($response['error'])) {
            error_log('handle_retrieve_vector_store_files error: ' . json_encode($response));
            throw new Exception($response['error']['message']);
        }

        if(isset($response['data']['error'])) {
            error_log('handle_retrieve_vector_store_files error2: ' . json_encode($response));
            throw new Exception($response['data']['error']['message']);
        }

        // Ottieni i file salvati
        $savedFiles = get_option('video_ai_chatbot_files', []);
        // Cerca i file restituiti tra quelli salvati


        //filter all savedFiles from opeanAiFiles
        $foundFiles = array_filter($response['data'], function($file) use ($response, $savedFiles) {
            foreach($savedFiles as $savedFile) {
                if(isset($savedFile) && $savedFile['id'] == $file['id']) {
                    return false;
                }
            }
            return true;
        });
        
        if(!isset($response)) {
            throw new Exception('Failed to retrieve vector store files with ID ' . $vectorStoreId);
        }
        return array_values($foundFiles);
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
        //print the assistant ids property for each updatedTranscription

        update_option($option_name, $toUpdate);
    }


    private function cleanUpTranscriptionsFromAssistantId($assistantId) {
        $option_name = 'video_ai_chatbot_transcriptions';
        $transcriptions = get_option($option_name, []);
        $func = function($transcription) use ($assistantId) {
            if(in_array($assistantId, $transcription['assistant_id'])) {
                // Elimina l'ID dell'assistente
                $transcription['assistant_id'] = array_diff($transcription['assistant_id'], [$assistantId]);
            }
            return $transcription; 
        };
        if(!is_array($transcriptions)) { //workaround for old options data
           update_option($option_name, []);
           $transcriptions = [];
        }
        $updatedTranscriptions = array_map($func, $transcriptions);
        //print the assistant ids property for eac updatedTranscription

        update_option($option_name, $updatedTranscriptions ? $updatedTranscriptions : []);
    }

    public function update_assistant_trascrizioni($id, $name, $data, $vectorStoreIds, $files) {

        if(count($vectorStoreIds) > 0) {
            $deleted = $this->communityopenai->deleteVectorStore($vectorStoreIds[0]);
            if(!$deleted) {
                throw new Exception('Failed to delete vector store');
            }
        }


        $vectorStoreId = $this->communityopenai->createVectorStore('vs_name_'.str_replace(' ', '_', $name));
        error_log('create vector store id: ' . $vectorStoreId);
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


        $data['tool_resources']['file_search']['vector_store_ids'] = [ $vectorStoreId ] ;
        array_push($data['tools'], $this->get_functions());
        array_push($data['tools'], $this->is_user_registered_function());
        array_push($data['tools'], $this->add_product_to_cart_function());
        array_push($data['tools'], $this->handover_function());
        array_push($data['tools'], $this->get_products_courses_map_function());
        array_push($data['tools'], $this->get_products_function());
        array_push($data['tools'], $this->send_studio_booking_email_function());
        return $data;

    }

    public function update_assistant($id, $name, $prompt, $vectorStoreIds, $files, $type, $metadata, $model = 'gpt-3.5-turbo') {
        

        if(!isset($this->client) || !isset($this->communityopenai)) {
            error_log('OpenAI client not initialized');
            return new WP_REST_Response(['message' => 'OpenAI client not initialized'], 400);
        }

        if(!$id) {
            error_log('Assistant ID not found in update_assistant params');
            return new WP_REST_Response(['message' => 'Assistant ID not found in update_assistant params'], 400);
        }

        if(!$name) {
            error_log('Name not found');
            return new WP_REST_Response(['message' => 'Name not found'], 400);
        }

        if(!$prompt) {
            error_log('Prompt not found');
            return new WP_REST_Response(['message' => 'Prompt not found'], 400);
        }

        if(!isset($vectorStoreIds)) {
            error_log('Vector store IDs not found');
            return new WP_REST_Response(['message' => 'Vector store IDs not found'], 400);
        }

        if(!$type) {
            error_log('Type not found');
            return new WP_REST_Response(['message' => 'Type not found'], 400);
        }

        // if($type !== 'trascrizioni') {
        //     return new WP_REST_Response(['message' => 'Invalid type'], 400);
        // }

        $data = [
            'model' => $model,
            'name' => $name,
            'description' => $name,
            'instructions' => $prompt,
            'tools' => [array('type' => "file_search")],
            'metadata' => $metadata
        ];


        try {
            if($type == 'trascrizioni') {
                $data = $this->update_assistant_trascrizioni($id, $name, $data, $vectorStoreIds, $files);
                $vectorStoreIds = $data['tool_resources']['file_search']['vector_store_ids'];
                if(isset($files) && count($files) > 0) {
                    $savedFiles = get_option('video_ai_chatbot_transcriptions', []);
                    //find file with file_id
                    $updatedFiles = array_map(function($file) use ($files, $vectorStoreIds) {
                        foreach($files as $fileId) {
                            if(isset($file) && $file['id'] == $fileId) {
                                return ['id' => $file['id'], 
                                        'vector_store_id' => $vectorStoreIds[0] , 
                                        'file_name' => $file['file_name'], 
                                        'file_content' => $file['file_content']];
                            }
                        }
                        return $file;
                    }, $savedFiles); 

                    update_option('video_ai_chatbot_transcriptions', $updatedFiles);    
                }
            } else if($type == 'preventivi' && count($files) > 0) {     
                try {
                    error_log('create vector store file count: ' . count($files));
                    error_log('create vector store vector store id: ' . $vectorStoreIds[0]);
                    $response = $this->communityopenai->createVectorStoreFiles($vectorStoreIds[0], $files);
                    //update video_ai_chatbot_files
                    error_log('create vector store files response: ' . json_encode($response));
                    $savedFiles = get_option('video_ai_chatbot_files', []);
                    error_log('saved files');
                    //find file with file_id
                    $updatedFiles = array_map(function($file) use ($files, $vectorStoreIds) {
                        if(isset($file) && $file['id'] == $files[0]) {
                            return ['id' => $file['id'], 
                                    'vector_store_id' => $vectorStoreIds[0] , 
                                    'file_name' => $file['file_name'], 
                                    'file_content' => $file['file_content']];
                        }
                        return $file;
                    }, $savedFiles); 

                    update_option('video_ai_chatbot_files', $updatedFiles);

                    // array_push($data['tools'], $this->get_allproducts_functions());
                    // array_push($data['tools'], $this->add_product_to_cart_function());
                    array_push($data['tools'], $this->get_products_function());
                    array_push($data['tools'], $this->handover_function());
                    array_push($data['tools'], $this->send_studio_booking_email_function());
                           
                } catch(Exception $e) {
                    error_log('error: ' . $e->getMessage());
                    return new WP_REST_Response(['message' => $e->getMessage()], 500);
                }
            } else {
                throw new Exception('Invalid assistant type');
            }
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

    public function set_thread_handover($threadId, $isHandover) {
        $thread = $this->client->retrieveThread($threadId);
        $thread = json_decode($thread, true);
        $metadata = $thread['metadata'];
        $metadata['handover'] = $isHandover ? "true" : "false";
        $data = ['metadata' => $metadata];
        $response = $this->client->modifyThread($threadId, $data);
        return $response;
    } 

    public function user_handover($thread_id, $message) {
        $response = null;
        try {
            $query = ['limit' => 10];
            $runs = $this->cancel_all_runs_for_thread_id($thread_id);
            $response = $this->set_thread_handover($thread_id, true);
            $msgData = [
                'role' => 'assistant',
                'content' => $message,
                'metadata' => [
                    'postprompt' => 'false',
                    'handover_message' => 'true'
                ]
            ];
            $message = $this->client->createThreadMessage($thread_id, $msgData);
        } catch (Exception $e) {
            return $response;
        }
        return $response;
    }

    public function terminate_handover($thread_id) {
        $response = null;
        try {
            $thread = $this->client->retrieveThread($thread_id);
            $thread = json_decode($thread, true);
            $metadata = $thread['metadata'];
            $metadata['handover'] = "false";
            $data = ['metadata' => $metadata];
            $response = $this->client->modifyThread($thread_id, $data);
        } catch (Exception $e) {
            return $response;
        }
        return $response;
    }

    public function delete_transcription($file_id, $remote = true) {
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

    public function delete_file($file_id, $remote = true) {
        $deleted_file = $file_id;
        if($remote) {
            // Elimina il file da OpenAI
            $deleted_file = $this->client->deleteFile($file_id);
            $deleted_file = json_decode($deleted_file, true);
            if(isset($deleted_file['error'])) {
                throw new Exception($deleted_file['error']['message']);
            }
        }

        return $this->delete_local_file($file_id);
    }


    public function upload_file($c_file, $file_name, $file_content) {
        
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

        $files = get_option('video_ai_chatbot_files', []);
        $files = wp_parse_args($files, []);
        $files[] = ['id' => $response['id'], 'vector_store_id' => '' , 'file_name' => $file_name, 'file_content' => $file_content];
        update_option('video_ai_chatbot_files', $files);

        return new WP_REST_Response(['message' => 'File uploaded successfully', 'file_id' => $response['id']], 200);
    }

    public function delete_transcriptions_content() {
        $option_name = 'video_ai_chatbot_transcriptions';
        $transcriptions = get_option($option_name, []);
        $updated_transcriptions = array_map(function($transcription) {
            $transcription['transcription'] = '';
            return $transcription;
        }, $transcriptions);
        update_option($option_name, $updated_transcriptions);
    }

    public function upload_transcription($c_file, $file_name, $transcription) {
    
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
            $newTrans['transcription']['transcription'] = "";
            error_log('newTrans: ' . json_encode($newTrans));
            $transcriptions[] = $newTrans;
            update_option($option_name, $transcriptions);

            if($transcription['assistant_id'] && $file_id) {
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
                    throw new Exception($uploadException->getMessage());
                    // Handle re-upload failure
                }
            }
        }
        $option_name = 'video_ai_chatbot_transcriptions';
        update_option($option_name, $transcriptions);

        // update_option($option_name, $transcriptions);
        return $transcriptions;
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

    private function get_allcourses_functions() {
        return [
            'type' => "function",
            'function' => [
                'name' => 'get_all_courses',
                'description' => 'Get all available courses on the platform',
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


    private function get_allproducts_functions() {
        return [
            'type' => "function",
            'function' => [
                'name' => 'get_all_products',
                'description' => 'Get all available produtcts on the platform',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'products' => [
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

    private function get_allproducts_with_courses_functions() {
        return [
            'type' => "function",
            'function' => [
                'name' => 'get_all_products_with_courses',
                'description' => 'Get all available products and courses on the platform',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'products' => [
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

    private function is_user_registered_function() {
        return [
            'type' => "function",
            'function' => [
                'name' => 'is_user_registered',
                'description' => 'Check if the user is registered',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'is_registered' => [
                            'type' => 'string'
                        ]
                    ]
                ]
            ]
        ];
    }

    private function get_products_courses_map_function() {
        return [
            'type' => "function",
            'function' => [
                'name' => 'get_products_courses_map',
                'description' => 'Get all associated products and courses. Used to map products to courses',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'associations' => [
                            'type' => 'array',
                            'items' => [
                                'type' => 'object',
                                'properties' => [
                                    'courseid' => [
                                        'type' => 'string',
                                    ],
                                    'productId' => [
                                        'type' => 'string',
                                    ]
                                ]
                            ]
                        ]
                    ]
                ]
            ]
        ];
    }

    private function add_product_to_cart_function() {
        return [
            'type' => "function",
            'function' => [
                'name' => 'add_product_to_cart',
                'description' => 'Add a product to the WooCommerce cart',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'product_id' => [
                            'type' => 'string',
                        ]
                    ]
                ]
            ]
        ];
    }

    private function handover_function() {
        return [
            'type' => "function",
            'function' => [
                'name' => 'handover',
                'description' => 'pass conversation to human operator',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'handover_motivation' => [
                            'type' => 'string',
                        ],
                        'user_phone_number' => [
                            'type' => 'string',
                        ],
                        'user_email' => [
                            'type' => 'string',
                        ],
                        'user_name' => [
                            'type' => 'string',
                        ],
                    ],
                    'required' => []
                    ]
                ]
        ];
    }


    private function send_studio_booking_email_function() {
        return [
            'type' => "function",
            'function' => [
                'name' => 'send_studio_booking_email',
                'description' => 'notify via email about the booking of the studio session',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'motivation' => [
                            'type' => 'string',
                        ],
                        'user_phone_number' => [
                            'type' => 'string',
                        ],
                        'user_email' => [
                            'type' => 'string',
                        ],
                        'user_name' => [
                            'type' => 'string',
                        ],
                    ],
                    'required' => []
                    ]
                ]
        ];
    }


    private function get_products_function() {
        return [
            'type' => "function",
            'function' => [
                'name' => 'get_products',
                'description' => 'Get available products on the platform filtered by params',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'firstParam' => [
                            'type' => 'string',
                            'enum' => ['Corso', 'Bundle', 'Chain', 'Template', 'One to One', 'Sample Pack', 'Plugin']
                        ],
                        'secondParam' => [
                            'type' => 'string',
                            'enum' => ['Mixing', 'Mastering', 'Produzione', 'Recording', 'One to One', 'Voce']
                        ], 
                    ],
                ]
            ]
        ];
    }



    	/**
     * Aggiunge un prodotto al carrello di WooCommerce.
     *
     * @param int $product_id ID del prodotto da aggiungere.
     * @param int $quantity Quantità del prodotto da aggiungere. Default è 1.
     * @return bool True se il prodotto è stato aggiunto con successo, false altrimenti.
     */


    public function add_product_to_cart( $product_id, $quantity = 1 ) {
        // Verifica se WooCommerce è attivo
        if ( ! class_exists( 'WooCommerce' ) ) {
            return false;
        }

        // Load cart functions which are loaded only on the front-end.
        include_once WC_ABSPATH . 'includes/wc-cart-functions.php';
        include_once WC_ABSPATH . 'includes/class-wc-cart.php';

        if (null === WC()->cart) {
            wc_load_cart();
        }

        // Aggiungi il prodotto al carrello
        $added = WC()->cart->add_to_cart( $product_id, $quantity );

        // Verifica se il prodotto è stato aggiunto con successo
        if ( $added ) {
            return true;
        }

        return false;
    }


    function get_products($firstParam, $secondParam) {
        if(!isset($firstParam)) {
            error_log('First param not found');
            return [];
        }

        $taxQuery = array(
            'relation' => 'AND',
            array(
                'taxonomy' => 'product_cat',
                'field' => 'slug',
                'terms' => $firstParam
            ),
        );

        if(isset($secondParam)) {
            $taxQuery[] = array(
                'taxonomy' => 'product_cat',
                'field' => 'slug',
                'terms' => $secondParam
            );
        }

        $args = array(
            'post_type' => 'product',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'tax_query' => $taxQuery
        );


        // Esegue la query dei prodotti
        $products = get_posts( $args );
        $product_list = array();

         // Itera su ogni prodotto e ottiene le informazioni complete
        foreach ( $products as $product ) {
            $product_obj = wc_get_product( $product->ID );
            if ( $product_obj->is_purchasable() ) {
                $product_list[] = array(
                    'id' => $product->ID,
                    'name' => $product->post_title,
                    'description' => $product->post_content,
                    'short_description' => $product->post_excerpt,
                    'permalink' => get_permalink($product->ID),
                    'price' => round($product_obj->get_price(), 2),
                    'regular_price' => $product_obj->get_regular_price(),
                    'sale_price' => $product_obj->get_sale_price(),
                    'image_url' => wp_get_attachment_url( $product_obj->get_image_id() ),
                    'categories' => wp_get_post_terms( $product->ID, 'product_cat', array( 'fields' => 'names' ) ),
                    'tags' => wp_get_post_terms( $product->ID, 'product_tag', array( 'fields' => 'names' ) )
                );
            }
        }

        return $product_list;
    }

    /**
 * Ottiene l'elenco di tutti i prodotti acquistabili su WooCommerce con tutte le informazioni
 *
 * @return array Elenco dei prodotti con tutte le informazioni
 */
function get_wc_products_full_info() {
    // Assicurati che WooCommerce sia attivo
    if ( ! in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {
        error_log('WooCommerce is not active');
        return;
    }
    // Argomenti per la query dei prodotti
    $args = array(
        'post_type' => 'product',
        'post_status' => 'publish',
        'posts_per_page' => -1,
    );

    // Esegue la query dei prodotti
    $products = get_posts( $args );
    $product_list = array();

    // Itera su ogni prodotto e ottiene le informazioni complete
    foreach ( $products as $product ) {
        $product_obj = wc_get_product( $product->ID );
        if ( $product_obj->is_purchasable() ) {
            $product_list[] = array(
                'id' => $product->ID,
                'name' => $product->post_title,
                'description' => $product->post_content,
                'short_description' => $product->post_excerpt,
                'price' => $product_obj->get_price(),
                'regular_price' => $product_obj->get_regular_price(),
                'sale_price' => $product_obj->get_sale_price(),
                'image_url' => wp_get_attachment_url( $product_obj->get_image_id() ),
                'categories' => wp_get_post_terms( $product->ID, 'product_cat', array( 'fields' => 'names' ) ),
                'tags' => wp_get_post_terms( $product->ID, 'product_tag', array( 'fields' => 'names' ) )
            );
        }
    }

    error_log('count products: ' . count($product_list));
    return $product_list;
}

    private function get_base_url() {
        $protocol = isset($_SERVER['HTTPS']) && 
        $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://';
        $base_url = $protocol . $_SERVER['HTTP_HOST'] . '/';
        if(str_contains($base_url, 'localhost')) {
            $base_url = 'http://127.0.0.1:10010/';
        }
        return $base_url;
    }

    public function create_assistant($name, $prompt, $type, $metadata, $files, $model = 'gpt-3.5-turbo') {
        if(!isset($this->client) || !isset($this->communityopenai)) {
            error_log('OpenAI client not initialized');
            return new WP_REST_Response(['message' => 'OpenAI client not initialized'], 400);
        }

        try {
            $vectorStoreId = $this->communityopenai->createVectorStore('vs_name_'.str_replace(' ', '_', $name));
                    
            if(!$vectorStoreId) {
                error_log('Failed to create vector store');
                throw new Exception('Failed to create vector store');
            }
            $vectorStoreFiles = [];
            if($files && count($files) > 0) {
                $vectorStoreFiles = $this->communityopenai->createVectorStoreFiles($vectorStoreId, $files); 
                $savedFiles = get_option('video_ai_chatbot_files', []);

                if(count($savedFiles) > 0) {
                    foreach($files as $file) {
                        foreach($savedFiles as &$savedFile) {
                            if($savedFile['id'] == $file) {
                                // Aggiorna l'elemento
                                $savedFile['vector_store_id'] = $vectorStoreId;
                                break;
                            }
                        }
                    }
                    // Salva l'array aggiornato
                    update_option('video_ai_chatbot_files', $savedFiles);
                }
            }          
        } catch (Exception $e) {
            return new WP_REST_Response(['message' => $e->getMessage()], 500);
        }

        $metadata = array_merge($metadata, ['type' => $type, 'url' => $this->get_base_url()]);

        $data = [
            'model' => $model,
            'name' => $name,
            'description' => $name,
            'instructions' => $prompt,
            'tools' => [
                [
                    'type' => "file_search"
                ]                
            ],
            'tool_resources'=> [ 'file_search' => [ 'vector_store_ids' => [$vectorStoreId] ]],
            'metadata' => $metadata
        ];

        if($type == 'trascrizioni') {
            array_push($data['tools'], $this->get_functions());
            array_push($data['tools'], $this->get_products_courses_map_function());
            array_push($data['tools'], $this->is_user_registered_function());
            array_push($data['tools'], $this->add_product_to_cart_function());
            array_push($data['tools'], $this->handover_function());
            array_push($data['tools'], $this->get_products_function());
            array_push($data['tools'], $this->send_studio_booking_email_function());
        } else if($type == 'preventivi') {
            // array_push($data['tools'], $this->get_allproducts_functions());
            // array_push($data['tools'], $this->add_product_to_cart_function());
            array_push($data['tools'], $this->get_products_function());
            array_push($data['tools'], $this->handover_function());
            array_push($data['tools'], $this->send_studio_booking_email_function());
        }
    
        try {
            $response =  $this->client->createAssistant($data);
            $decoded_res = json_decode($response,true);          
            if ($response && isset($decoded_res['id'])) {
                try {
                    if($vectorStoreFiles && count($vectorStoreFiles) > 0 && $type == 'trascrizioni') {
                        foreach($files as $file) {
                            $transcription = $this->getTranscriptionByFileId($file);
                            if($transcription) {
                                array_push($transcription['assistant_id'], $decoded_res['id']);
                                $this->update_transcription($file, $transcription);
                            }
                        }
                    }
                } catch (Exception $e) {
                    error_log('error: ' . $e->getMessage());
                    return new WP_REST_Response(['message' => $e->getMessage()], 500);
                }   
                return new WP_REST_Response(['message' => 'Assistant created successfully', 'assistant_id' => $decoded_res['id']], 200);
            } else {
                error_log('Failed to create assistant');
                return new WP_REST_Response(['message' => 'Failed to create assistant'], 500);
            }
        } catch (Exception $e) {
            error_log('error: ' . $e->getMessage());
            return new WP_REST_Response(['message' => $e->getMessage()], 500);
        }
    }



    

    public function get_filtered_assistants() {

        $current_user = wp_get_current_user();
        $user_roles = $current_user->roles;
    
        if (in_array('abbonato', $user_roles)) {
            $user_role = 'abbonato';
        } else if (in_array('customer', $user_roles)) {
            $user_role = 'cliente';
        } else if (in_array('subscriber', $user_roles)) {
            $user_role = 'registrato';
        } else {
            $user_role = 'non_registrato';
        }

        // Recupera tutti gli assistenti
        $assistants = $this->get_assistants();

        // Filtra gli assistenti in base al ruolo dell'utente
        $filtered_assistants = array_filter($assistants, function($assistant) use ($user_role) {
            $level = isset($assistant['metadata']['roles']) ? $assistant['metadata']['roles'] : null;
            $roles = explode('|', $level);

            switch ($user_role) {
                case 'non_registrato':
                    return in_array('non_registrato', $roles);
                case 'registrato':
                    return in_array('registrato', $roles);
                case 'cliente':
                    return in_array('cliente', $roles);
                case 'abbonato':
                    return in_array('abbonato', $roles);
                default:
                    return false;
            }
            
        });

        return array_values($filtered_assistants);
    }

    public function get_assistants() {
        // Recupera gli assistenti utilizzando le API di OpenAI
        if(!isset($this->client)) {
            error_log('OpenAI client not initialized');
            return [];
        }
        $query = ['limit' => 100];
        $assistants = $this->client->listAssistants($query);
        $assistants = json_decode($assistants, true);
        if(isset($assistants['error'])) {
            throw new Exception($assistants['error']['message']);
        }
        $filtered = array_filter($assistants['data'], function($v) {
            $url = $v['metadata']['url'];
            return !isset($url) || $url == $this->get_base_url();
        });
        return array_values($filtered);
    }



    
    public function handle_wa_chatbot_request($input_text, $assistant_id, $phoneNumber) {
        $result = $this->handle_chatbot_request($input_text, $assistant_id, $phoneNumber, 'wa');
        // if($result->error) {
        //     return new WP_REST_Response(['message' => $result->message], 500);
        // }
        // $cb($result);
        return $result; // new WP_REST_Response(['message' => $result->message], 200);
    }

    public function handle_ig_chatbot_request($input_text, $assistant_id, $accountId) {
        $result = $this->handle_chatbot_request($input_text, $assistant_id, $accountId, 'ig');
        // if($result->error) {
        //     return new WP_REST_Response(['message' => $result->message], 500);
        // }
        // $cb($result);
        return $result; // new WP_REST_Response(['message' => $result->message], 200);
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
            $userThreads = get_user_meta($user_id, 'openai_thread_id', true);
            $userThreads  = !is_array($userThreads) ? json_decode($userThreads, true) : $userThreads;
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

    public function get_last_assistant_used_for_user($user_id) {
        if ($user_id != 0) {
            $userAssistant = get_user_meta($user_id, 'video_ai_chatbot_last_assistant', true);
            return $userAssistant;
        } else {
            $sessions = get_option('video_ai_chatbot_sessions', []);
            $userSessionId = isset($_COOKIE['video_ai_chatbot_session_id']) ? $_COOKIE['video_ai_chatbot_session_id'] : null;
            if (isset($sessions[$userSessionId])) {
                $sessionInfo = $sessions[$userSessionId];
                if(isset($sessionInfo['last_assistant'])) {
                    return $sessionInfo['last_assistant'];
                }
            }
        }
        return false;
    }

    private function set_last_assistant_used_for_user($user_id, $assistant_id, $userSessionId = null) {
        if ($user_id != 0) {
            update_user_meta($user_id, 'video_ai_chatbot_last_assistant', $assistant_id);
        } else {
            $sessions = get_option('video_ai_chatbot_sessions', []);
            if(!isset($userSessionId)) {
                $userSessionId = $_COOKIE['video_ai_chatbot_session_id'];
            }
            if(isset($userSessionId)) {
                $sessionInfo = [
                    'last_assistant' => $assistant_id
                ];
                $sessions[$userSessionId] = $sessionInfo;
                update_option('video_ai_chatbot_sessions', $sessions);
            }
        }
    }

    private function search_and_delete_thread_id_for_user($user_id, $assistant_id) {
        $threadId = null;
        if ($user_id != 0) {
            $userThreads = get_user_meta($user_id, 'openai_thread_id', true);
            $userThreads = !is_array($userThreads) ? json_decode($userThreads, true) : $userThreads;
            $threadId = $userThreads[$assistant_id];
            if(isset($userThreads[$assistant_id])) {
                unset($userThreads[$assistant_id]);
            }
            update_user_meta($user_id, 'openai_thread_id', $userThreads);
        } else {
            $threads = get_option('video_ai_chatbot_threads', []);
            $userSessionId = isset($_COOKIE['video_ai_chatbot_session_id']) ? $_COOKIE['video_ai_chatbot_session_id'] : null;

            if (isset($userSessionId) && isset($threads[$userSessionId])) {
                $threadId = $threads[$userSessionId][$assistant_id];
                unset($threads[$userSessionId][$assistant_id]);
                update_option('video_ai_chatbot_threads', $threads);
            }
        }
        return $threadId;
    }

    public function cancel_all_runs() {
        $user_id = apply_filters('determine_current_user', true);
        if($user_id == 0) {
            $user = wp_get_current_user();
            $user_id = isset($user->ID) ? $user->ID : 0;
        }
        $assistant_id = $this->get_last_assistant_used_for_user($user_id);
        $thread_id = $this->get_thread_id_for_user($user_id, $_COOKIE['video_ai_chatbot_session_id'], $assistant_id);
        $query = ['limit' => 50];
        $runs = $this->client->listRuns($thread_id, $query);
        $runs = json_decode($runs, true);
        
        if(!isset($runs) || !isset($runs['data'])) {
            return new WP_REST_Response(['message' => 'No runs found'], 500);
        }

        if(count($runs['data']) > 0) {
            foreach ($runs['data'] as $run) {
                if(!($run['status'] == 'completed' || $run['status'] == 'failed' || $run['status'] == 'expired')) {
                    $result = $this->client->cancelRun($thread_id, $run['id']);
                }
            }
        }

        return new WP_REST_Response(['message' => 'Runs cancelled successfully'], 200);
    }

    public function cancel_all_runs_for_user($user_id) {
        $assistant_id = $this->get_last_assistant_used_for_user($user_id);
        $thread_id = $this->get_thread_id_for_user($user_id, $_COOKIE['video_ai_chatbot_session_id'], $assistant_id);
        $query = ['limit' => 50];
        $runs = $this->client->listRuns($thread_id, $query);
        $runs = json_decode($runs, true);
        
        if(!isset($runs) || !isset($runs['data'])) {
            return new WP_REST_Response(['message' => 'No runs found'], 500);
        }

        if(count($runs['data']) > 0) {
            foreach ($runs['data'] as $run) {
                if(!($run['status'] == 'completed' || $run['status'] == 'failed' || $run['status'] == 'expired')) {
                    $result = $this->client->cancelRun($thread_id, $run['id']);
                }
            }
        }

        return new WP_REST_Response(['message' => 'Runs cancelled successfully'], 200);
    }

    public function cancel_all_users_runs() {
        $users = get_users();
        foreach ($users as $user) {
            $this->cancel_all_runs_for_user($user->ID);
        }
        //iterate on video_ai_chatbot_sessions and delete all the threads
        $sessions = get_option('video_ai_chatbot_sessions', []);
        foreach ($sessions as $key => $session) {
            $this->cancel_all_runs_for_user($key);
        }
        return new WP_REST_Response(['message' => 'Runs cancelled successfully'], 200);
    }



    public function cancel_all_runs_for_thread_id($thread_id) {
        $query = ['limit' => 50];
        $runs = $this->client->listRuns($thread_id, $query);
        $runs = json_decode($runs, true);
        
        if(!isset($runs) || !isset($runs['data'])) {
            return new WP_REST_Response(['message' => 'No runs found'], 500);
        }

        if(count($runs['data']) > 0) {
            foreach ($runs['data'] as $run) {
                if(!($run['status'] == 'completed' || $run['status'] == 'failed' || $run['status'] == 'expired')) {
                    $result = $this->client->cancelRun($thread_id, $run['id']);
                }
            }
        }

        return new WP_REST_Response(['message' => 'Runs cancelled successfully'], 200);
    }

    private function remove_pattern($dataMessage) {
        $pattern = '/【.*?†source】/u';
        // Replace the pattern with an empty string
        $dataMessage['value'] = preg_replace($pattern, '', $dataMessage['value']);
        return $dataMessage;
    }

    public function handle_chatbot_request($input_text, $assistant_id, $userSessionId, $type = null, $userpostprompt = null) {
        if (!isset($this->client)) {
            throw new Exception('OpenAI client not initialized');
        }

        try {
            if (!isset($assistant_id)) {
                throw new Exception('Assistant not found');
            }

            $user_id = apply_filters('determine_current_user', true);
            if($user_id == 0) {
                $user = wp_get_current_user();
                $user_id = $user->ID;
            }
            
            $this->set_last_assistant_used_for_user($user_id, $assistant_id, $type ? $userSessionId : null);

            $additional_instructions = '';	
            if($user_id != 0) {
                $user_display_name = get_userdata($user_id)->display_name;
                $additional_instructions = 'Il nome dell\'utente è: ' . $user_display_name;
            }
            $threadId = $this->get_thread_id_for_user($user_id, $userSessionId, $assistant_id);

            error_log('threadId: ' . $threadId);
            $thread = $this->get_or_create_thread($threadId, $assistant_id, $userSessionId, $type);

            $handover = "false";
            if(isset($thread['metadata']['handover']) && $thread['metadata']['handover'] == 'true') {
                $handover = "true";

                $messages = $this->client->listThreadMessages($thread['id'], $query);
                $messages = json_decode($messages, true);
                if(!isset($messages) || isset($messages['error'])) {
                    $handover = "false";
                }
                
                //leggi il campo created_at dell'ultimo messaggio e se è passato più di 15 minuti da allora, allora setta handover = "false"
                $lastMessage = $messages['data'][0];
                $lastMessageCreatedAt = $lastMessage['created_at'];
                $currentTime = time();
                $diff = $currentTime - $lastMessageCreatedAt;
                if($diff > 900) {
                    $handover = "false";
                }

                
            }

            $query = ['limit' => 10];
            $messages = $this->client->listThreadMessages($thread['id'], $query);

            $msgData = [
                'role' => 'user',
                'content' => $input_text,
                'metadata' => [
                    'postprompt' => isset($userpostprompt) ? $userpostprompt : 'false',
                    'handover_message' => $handover
                ]
            ];

            
            $message = $this->client->createThreadMessage($thread['id'], $msgData);


            if (!$message) {
                throw new Exception('no message received');
            }
            if (isset($message->error)) {
                error_log('message error: ' . json_encode($message));
                throw new Exception($message['error']['message']);
            }

            //verifica se tra i metadati del thread c'è il campo handover == "true"
            if($handover == "true") {
                //$this->search_and_delete_thread_id_for_user($user_id);
                $data = [
                    'success' => true,
                    "error" => false,
                    "message" => [ 'value' => 'handover'],
                    'id' => $thread['id'],
                ];
                return $data;
            }


            $runData = [
                'assistant_id' => $assistant_id,
                'max_prompt_tokens' => 50000,
            ];

            if($additional_instructions != "") {
                $runData['additional_instructions'] = $additional_instructions;
            }

            $run = $this->client->createRun($thread['id'], $runData);
            $run = json_decode($run, true);

            if (isset($run['error'])) {
                $run = $run['error']['message'];
                error_log('run error: ' . json_encode($run));
                throw new Exception($run);
            }

            $start_time = time();
            // Wait for the response to be ready
            while (($run['status'] == "in_progress" || $run['status'] == "queued") || $run['status'] == "requires_action" && (time() - $start_time) < 5000) {
                //error_log('run status: ' . json_encode($run['status']));
                sleep(1);
                if($run['status'] == "requires_action" ) {
                    try {
                        error_log('userId ' . $user_id);
                        error_log('sessionId '. $userSessionId);
                        error_log('isset($user_id) '. isset($user_id));
                        $user_display_name = "";
                        if(isset($user_id)) {
                            $user_display_name = get_userdata($user_id)->display_name;
                        }
                        $userId = !isset($user_id) || !$user_id ? $userSessionId : $user_id;
                        $run = $this->handle_assistant_function_call($userId, $run, $user_display_name);
                        error_log('run: FUNCTION CALL userid ' . $user_id);
                        error_log('run: FUNCTION CALL sessionid ' . $userSessionId);
                        error_log('run: FUNCTION CALL ' . json_encode($run));
                    } catch (Exception $e) {
                        throw new Exception($e->getMessage());
                    }
                } else {
                    $run = $this->client->retrieveRun($thread['id'], $run['id']);
                    $run = json_decode($run, true);
                    error_log('run: NORMAL userid' . $user_id);
                    error_log('run: NORMAL sessionid' . $userSessionId);
                    error_log('run: NORMAL ' . json_encode($run));	
                }
            }

            if(!isset($run)) {
                throw new Exception('Failed to handle run function call');
            }

            if ($run['status'] == "in_progress" || $run['status'] == "queued") {
                throw new Exception('Timeout');
            }

            $query = ['limit' => 20];
            $messages = $this->client->listThreadMessages($thread['id'], $query);
            $messages = json_decode($messages, true);

            //error_log('messages: ' . json_encode($messages));

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

            $updatedThread = $this->client->retrieveThread($thread['id']);
            $handoverMessage = false;
            if(isset($updatedThread) && !isset($updatedThread['error'])) {
                $updatedThread = json_decode($updatedThread, true);
                if(isset($updatedThread['metadata']['handover']) && $updatedThread['metadata']['handover'] == 'true') {
                    $handoverMessage = true;
                }
            }

            $data = [
                "error" => false,
                "message" => $this->remove_pattern($messages['data'][0]['content'][0]['text']),
                'is_handover_message' => $handoverMessage,
                'id' => $messages['data'][0]['id'],
                'created_at' => $messages['data'][0]['created_at'],
            ];

            error_log('handle_chatbot_request data: ' . json_encode($data));

            return $data;
        } catch (Exception $e) {
            $data = [
                "error" => true,
                "message" => $e->getMessage(),
                "run_cancelled" => $this->cancel_all_runs()
            ];
            return $data;
        }
    }

    private function handle_assistant_function_call($userId, $run, $userName="") {
        error_log('handle_assistant_function_call userId: ' . $userId);
        if (!isset($this->client)) {
            return null;
        }
        
        if(isset($run['required_action']['submit_tool_outputs']) && isset($run['required_action']['submit_tool_outputs']['tool_calls'])) {
            $toolCalls = $run['required_action']['submit_tool_outputs']['tool_calls'];
            $outputs = array_map(function($toolCall) use ($userId, $run, $userName) {
                error_log('Called fucntion: ' . $toolCall['function']['name']);
                if($toolCall['function']['name'] == 'get_user_courses') {
                    return [
                        'tool_call_id' => $toolCall['id'],
                        'output' =>  json_encode($this->tutorUtils->get_active_courses_by_user($userId))
                    ];
                }
                if($toolCall['function']['name'] == 'get_all_courses') {
                    return [
                        'tool_call_id' => $toolCall['id'],
                        'output' =>  json_encode($this->tutorUtils->course())
                    ];
                }		
                if($toolCall['function']['name'] == 'get_all_products') {
                    return [
                        'tool_call_id' => $toolCall['id'],
                        'output' =>  json_encode($this->get_wc_products_full_info())
                    ];
                }
                if($toolCall['function']['name'] == 'get_products_courses_map') {
                    return [
                        'tool_call_id' => $toolCall['id'],
                        'output' =>  json_encode($this->tutorUtils->get_products_courses_map())
                    ];
                }
                if($toolCall['function']['name'] == 'get_all_products_with_courses') {
                    return [
                        'tool_call_id' => $toolCall['id'],
                        'output' =>  json_encode($this->tutorUtils->get_products_with_courses())
                    ];
                }		
                if($toolCall['function']['name'] == 'is_user_registered') {
                    return [
                        'tool_call_id' => $toolCall['id'],
                        'output' =>  json_encode((apply_filters('determine_current_user', true) != 0))
                    ];
                }
                if($toolCall['function']['name'] == 'add_product_to_cart') {
                    $arguments = $toolCall['function']['arguments'];
                    error_log('arguments: ' . json_encode($arguments));
                    $arguments = json_decode($arguments);
                    $product_id = intval($arguments->product_id);
                    if(!isset($product_id) || $product_id == 0) {
                        throw new Exception('Product ID not found');
                    }
                    error_log('product_id: ' . $product_id);

                    return [
                        'tool_call_id' => $toolCall['id'],
                        'output' =>  json_encode($this->add_product_to_cart($product_id))
                    ];
                }
                if($toolCall['function']['name'] == 'handover') {
                    $arguments = $toolCall['function']['arguments'];
                    $arguments = json_decode($arguments, true);
                    error_log('handover arguments: ' . json_encode($arguments));
                    if(isset($arguments)) {
                        $motivation = $arguments['handover_motivation'];
                        $user_phone_number = $arguments['user_phone_number'];
                        $user_email = $arguments['user_email'];
                        if(!isset($userName) || empty($userName)) {
                            $userName = $arguments['user_name'];
                        }
                    }
                    $handoverData = $this->handover($run, $arguments, $userId, $user_phone_number, $user_email, $userName);
                    return [
                        'tool_call_id' => $toolCall['id'],
                        'output' =>  json_encode($handoverData)
                    ];
                }
                if($toolCall['function']['name'] == 'send_studio_booking_email') {
                    $arguments = $toolCall['function']['arguments'];
                    $arguments = json_decode($arguments, true);
                    error_log('send_studio_booking_email arguments: ' . json_encode($arguments));
                    if(isset($arguments)) {
                        $motivation = $arguments['motivation'];
                        $user_phone_number = $arguments['user_phone_number'];
                        $user_email = $arguments['user_email'];
                        if(!isset($userName) || empty($userName)) {
                            $userName = $arguments['user_name'];
                        }
                    }
                    $emailData = $this->send_studio_booking_email($run, $arguments, $userId, $user_phone_number, $user_email, $userName);
                    return [
                        'tool_call_id' => $toolCall['id'],
                        'output' =>  json_encode($emailData)
                    ];
                }
                if($toolCall['function']['name'] == 'get_products') {
                    error_log('get_products toolCall: ' . json_encode($toolCall));
                    $arguments = $toolCall['function']['arguments'];
                    $arguments = json_decode($arguments, true);
                    error_log('get_products arguments: ' . json_encode($arguments));
                    if(isset($arguments)) {
                        $firstParam = $arguments['firstParam'];
                        $secondParam = $arguments['secondParam'];
                    }
                    $products = $this->get_products($firstParam, $secondParam);
                    error_log('get_products products: ' . json_encode($products));
                    return [
                        'tool_call_id' => $toolCall['id'],
                        'output' =>  json_encode($products)
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

    private function handover($run, $motivation, $userId, $user_phone_number = "", $user_email = "", $userName = "") {
        $assistant_id = $run['assistant_id'];
        $result = $this->set_thread_handover($run['thread_id'], true);
        $notificationNumbers = get_option('video_ai_chatbot_notification_numbers', []);
        $notificationEmails = get_option('video_ai_chatbot_notification_emails', []);
        $waAssistant = null;
        if($this->is_ig_thread($run['thread_id'])) {
            $user_profile = $this->ig[$assistant_id]->get_user_profile($userId);
            error_log('user_profile: ' . json_encode($user_profile));
        } else {

            error_log('userId: ' . $userId);
        }
        foreach($this->wa as $assistant) {
            if($assistant->get_assistant_id() == $assistant_id) {
                $waAssistant = $assistant;
                break;
            }
        }
        if(isset($waAssistant)) {
            foreach ($notificationNumbers as $number) {
                error_log('number: ' . $number);
                $because = $motivation ? $motivation['handover_motivation']  : 'sconosciuta';
                $response = $waAssistant->send_whatsapp_message($number, 
                'Assistenza dell\'operatore richiesta per l\'utente: '. $userName .'. Motivazione: ' . $because . " Numero di telefono: " . $user_phone_number . "Email: " . $user_email, false);
            }
        }
        if(isset($notificationEmails)) {
            foreach ($notificationEmails as $email) {
                error_log('email: ' . $email);
                $because = $motivation ? $motivation['handover_motivation']  : 'sconosciuta';
                $response = wp_mail($email,
                                    "[CHATBOT] - Richiesta di handover da parte di " . $userName, 
                                    'Assistenza dell\'operatore richiesta per l\'utente: '. $userName .'. Motivazione: ' . $because . ". Numero di telefono: " . $user_phone_number . " Email: " . $user_email);
                error_log('notificationEmails response: ' . $response);
            }
        }
        $result = [
            'success' => true,
            'error' => false,
            'message' => 'handover'
        ];
        return $result;
    }

    private function send_studio_booking_email($run, $motivation, $userId, $user_phone_number = "", $user_email = "", $userName = "") {
        $assistant_id = $run['assistant_id'];
        //$result = $this->set_thread_handover($run['thread_id'], true);
        $notificationNumbers = get_option('video_ai_chatbot_notification_numbers', []);
        $notificationEmails = get_option('video_ai_chatbot_notification_emails', []);
        $waAssistant = null;
        if($this->is_ig_thread($run['thread_id'])) {
            $user_profile = $this->ig[$assistant_id]->get_user_profile($userId);
            error_log('user_profile: ' . json_encode($user_profile));
        } else {

            error_log('userId: ' . $userId);
        }
        foreach($this->wa as $assistant) {
            if($assistant->get_assistant_id() == $assistant_id) {
                $waAssistant = $assistant;
                break;
            }
        }
        if(isset($waAssistant)) {
            foreach ($notificationNumbers as $number) {
                error_log('number: ' . $number);
                $because = $motivation ? $motivation['motivation']  : 'sconosciuta';
                $response = $waAssistant->send_whatsapp_message($number, 
                'Assistenza dell\'operatore richiesta per l\'utente: '. $userName .'. Motivazione: ' . $because . " Numero di telefono: " . $user_phone_number . "Email: " . $user_email, false);
            }
        }
        if(isset($notificationEmails)) {
            foreach ($notificationEmails as $email) {
                error_log('email: ' . $email);
                $because = $motivation ? $motivation['motivation']  : 'sconosciuta';
                $response = wp_mail($email,
                                    "[CHATBOT] - Richiesta di prenotazione sala da parte di " . $userName, 
                                    'Assistenza dell\'operatore richiesta per l\'utente: '. $userName .'. Motivazione: ' . $because . ". Numero di telefono: " . $user_phone_number . " Email: " . $user_email);
                error_log('notificationEmails response: ' . $response);
            }
        }
        $result = [
            'success' => true,
            'error' => false,
            'message' => 'handover'
        ];
        return $result;
    }

    private function get_or_create_thread($threadId, $assistant_id, $userSessionId, $type = null) {
        if (isset($threadId)) {
            $thread = $this->client->retrieveThread($threadId);
            $thread = json_decode($thread, true);

            if (isset($thread->error)) {
                $thread = false;
            }
        }

        if (!isset($thread) || isset($thread['error'])) {
            $mData = [
                'messages' => [
                    [
                        'role' => 'assistant',
                        'content' => get_option('video_ai_chatbot_options')['video_ai_chatbot_welcome_message_field']
                    ]
                ]
            ];

            error_log('createThread type: '. $type);
            if(!empty($type)) {
                $mData['metadata'] = [
                    $type => 'true'
                ];
            }

            error_log('createThread mData: ' . json_encode($mData));
            $thread = $this->client->createThread($mData);
            $thread = json_decode($thread, true);
            if (isset($thread['error']) || !isset($thread['id'])) {
                error_log('thread error: ' . json_encode($thread));
                throw new Exception($thread['error']['message']);
            }

            $user_id = apply_filters('determine_current_user', true);
            if($user_id == 0) {
                $user = wp_get_current_user();
                $user_id = isset($user->ID) ? $user->ID : null;
            }
            if (isset($user_id) && $user_id != 0) {
                $userThreads = get_user_meta($user_id, 'openai_thread_id', true);
                $userThreads = !is_array($userThreads) ? json_decode($userThreads, true): $userThreads;
                $userThreads[$assistant_id] = $thread['id'];
                update_user_meta($user_id, 'openai_thread_id', json_encode($userThreads));
            }
            if (isset($userSessionId)) {
                $threads = get_option('video_ai_chatbot_threads', []);
                if(!isset($threads[$userSessionId])) {
                    $threads[$userSessionId] = [];
                }
                $threads[$userSessionId][$assistant_id] = $thread['id'];
                update_option('video_ai_chatbot_threads', $threads);
            }
        }

        if (!isset($thread['id'])) {
            throw new Exception('Failed to create thread');
        }

        return $thread;
    }

    public function handle_thread_deletion_for_thread_id($user_id, $assistant_id) {
        $result = null;
        if($user_id != 0) {
            $threadId = $this->search_and_delete_thread_id_for_user($user_id, $assistant_id);
        }
        error_log('handle_thread_deletion_for_thread_id threadId: ' . $threadId);
		if ($threadId) {
			$result = $this->client->deleteThread($threadId);
			if ($result) {
				delete_user_meta($user_id, 'openai_thread_id');
			} 
		}
	
		return $result;
	}


    public function handle_thread_deletion($assistant_id) {
        // $user_id = get_current_user_id();
        if(!isset($assistant_id)) {
            return new WP_REST_Response(['message' => 'Assistant not found'], 404);
        }
        $user_id = apply_filters( 'determine_current_user', true );
        if($user_id == 0) {
            $user = wp_get_current_user();
            $user_id = $user->ID;
        }
        error_log('handle_thread_deletion user_id: ' . $user_id);
        try{
            $result = $this->handle_thread_deletion_for_thread_id($user_id, $assistant_id);
            if(!isset($result)) {
                return new WP_REST_Response(['message' => 'Thread not found'], 404);
            }
            return new WP_REST_Response(json_decode($result), 200);
        } catch (Exception $e) {
            return new WP_REST_Response(['message' => $e->getMessage()], 500);
        }	
    }


    public function delete_all_threads() {
        $threads = get_option('video_ai_chatbot_threads', []);
        foreach($threads as $userSessionId => $thread) {
            foreach($thread as $assistantId => $threadId) {
                $result = $this->client->deleteThread($threadId);
                if ($result) {
                    unset($threads[$userSessionId][$assistantId]);
                }
            }
        }
        update_option('video_ai_chatbot_threads', $threads);
        return new WP_REST_Response(['message' => 'All threads deleted successfully'], 200);
    }


    public function delete_unused_vector_stores() {
        $vectorStores = $this->communityopenai->retrieveVectorStores();
        //$response = json_decode($response, true);
        //$vectorStores = $response['data'];
        try{ 
            $vectorStores = array_filter($vectorStores, function($vectorStore) {
                return $vectorStore->fileCounts->total === 0 && $this->checkIfStoredAssistantsContainsVectorStore($vectorStore->id);
            });
        } catch (Exception $e) {
            throw new Exception($e->getMessage());
        }
        foreach($vectorStores as $vectorStore) {
            try {
                $this->communityopenai->deleteVectorStore($vectorStore->id);
            } catch (Exception $e) {
                throw new Exception($e->getMessage());
            }
        }
        return true;   
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
        $assistants = array_filter($assistants, function($assistant) use ($vectorStoreId) {
            return in_array($vectorStoreId, $assistant['tool_resources']['file_search']['vector_store_ids']);
        });
        return count($assistants) > 0;
    }


}

