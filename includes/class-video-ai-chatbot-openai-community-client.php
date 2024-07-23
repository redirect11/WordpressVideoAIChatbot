<?php

//use OpenAI;
        
class Video_Ai_Community_OpenAi {
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
        // register_rest_route('video-ai-chatbot/v1', '/addfilestoassistant/', [
        //     'methods' => 'GET',
        //     'callback' => [$this, 'get_assistants'],
        //     'permission_callback' => '__return_true'
        // ]);
    }

    public function activate($apiKey)
    {
        if(!$this->is_active() && $apiKey) {
            $this->client = OpenAI::factory()
                ->withApiKey($apiKey)
                ->withHttpClient($client = new \GuzzleHttp\Client([])) // default: HTTP client found using PSR-18 HTTP Client Discovery
                ->withHttpHeader('OpenAI-Beta', 'assistants=v2')
                ->make();
            
        }
    }

    public function createVectorStore($vectorStoreName)
    {
        if($this->is_active()) {

            $response = $this->client->vectorStores()->create([
                'file_ids' => [],
                'name' => $vectorStoreName,
            ]);

            if (isset($response->id)) {
                return $response->id;
            } else {
                throw new Exception('Failed to create vector store:' . json_encode($response));
            }
        } else {
            throw new Exception('Community ai client not active');
        }
    }

    public function createVectorStoreFiles($vectorStoreId, $files)
    {
        error_log('files: ' . json_encode($files));
        if($this->is_active()) {
            if(count($files) > 1) {
                $response = $this->client->vectorStores()->batches()->create(
                    vectorStoreId: $vectorStoreId,
                    parameters: [
                        'file_ids' => $files,
                    ]
                );
                while($response->status == 'processing') {
                    sleep(1);
                    $response = $this->client->vectorStores()->files()->retrieve(
                        vectorStoreId: $vectorStoreId,
                        fileBatchId: $response->id,
                    );
                }
                // $response = $this->client->vectorStores()->files()->list(
                //     vectorStoreId: $vectorStoreId,
                //     parameters: [
                //         'limit' => 10,
                //     ],
                // );
                // foreach ($response->data as $result) {
                //     $result->id; // 'file-fUU0hFRuQ1GzhOweTNeJlCXG'
                // }
            } else {
                $response = $this->client->vectorStores()->files()->create(
                    vectorStoreId: $vectorStoreId,
                    parameters: [
                        'file_id' => $files[0],
                    ]
                );

            }

            if (isset($response->id)) {
                return $files;
            } else {
                throw new Exception('Failed to create vector store: '. json_encode($response));
            }

        } else {
            throw new Exception('Community ai client not active');
        }
    }


    public function deleteVectorStore($vectorStoreId)
    {
        if($this->is_active()) {
            try {
                $response = $this->client->vectorStores()->delete(
                    vectorStoreId: $vectorStoreId,
                );
            } catch (Exception $e) {
                return $vectorStoreId;
            }

            if (isset($response->id) && isset($response->deleted) && $response->deleted == true) {
                return $response->id;
            } else if(isset($response->deleted) && $response->deleted == false) {
                throw new Exception('Vector store not deleted: '. json_encode($response));
            } else {
                throw new Exception('Failed to delete vector store: '. json_encode($response));
            }

        } else {
            throw new Exception('Community ai client not active');
        }
    }

    public function retrieveVectorStoreIdFromAssistantId($assistantId)
    {
        if($this->is_active()) {
            $response = $this->client->assistants()->retrieve($assistantId);
            error_log('response: ' . json_encode($response));
            if (isset($response) && isset($response->toolResources->fileSearch->vectorStoreIds)) {
                $vector_store_ids = $response->toolResources->fileSearch->vectorStoreIds;
                if (isset($vector_store_ids)) {
                    return $vector_store_ids;
                } else {
                    throw new Exception('Failed to retrieve vector store id: '. json_encode($response));
                }
            } else {
                throw new Exception('Failed to retrieve vector store id: '. json_encode($response));
            }
        } else {
            throw new Exception('Community ai client not active');
        } 
    }

    public function retrieveVectorStoreFiles($vectorStoreId, $after = null, $before = null)
    {
        if($this->is_active()) {
            //$responseData = 
            $parameters = [
                'limit' => 100,
            ];
            if($before) {
                $parameters['before'] = $before;
            }
            if($after) {
                $parameters['after'] = $after;
            }
            
            $response = $this->client->vectorStores()->files()->list(
                vectorStoreId: $vectorStoreId,
                parameters: $parameters,
            );

            error_log('response: ' . json_encode($response));
            if(isset($response) && isset($response->data)) {
                $arrayResponse= $response->toArray();
                return $arrayResponse;
            } else {
                throw new Exception('Failed to retrieve vector store files: '. json_encode($response));
            }

        } else {
            throw new Exception('Community ai client not active');
        }
    }

    public function retrieveVectorStoreStatus($vectorStoreId)
    {
        if($this->is_active()) {
            $response = $this->client->vectorStores()->retrieve($vectorStoreId);

            if (isset($response->status) && isset($response->id)) {
                return $response->status;
            } else {
                throw new Exception('Failed to retrieve vector store: '. json_encode($response));
            }

        } else {
            throw new Exception('Community ai client not active');
        }
    }

    public function submit_function_call_output($runId, $threadId, $output) {
        if($this->is_active()) {
            try{

                $parameters = [
                    'tool_outputs' => $output,
                ];

                error_log('submit_function_call_output: ' . $runId . ' ' . $threadId . ' ' . json_encode($output) . ' ' . json_encode($parameters));

                $response = $this->client->threads()->runs()->submitToolOutputs(
                    threadId: $threadId,
                    runId: $runId,
                    parameters: [
                        'tool_outputs' => $output,
                    ]
                );

                if (isset($response->id)) {
                    return $response->toArray();
                } else {
                    $response = $this->client->threads()->runs()->cancel(
                        threadId: $threadId,
                        runId: $runId,
                    );
                    throw new Exception('Failed to submit function call output. Canceling run: '. json_encode($response));
                }
            } catch (Exception $e) {
                error_log('error: ' . json_encode($e));
                $response = $this->client->threads()->runs()->cancel(
                    threadId: $threadId,
                    runId: $runId,
                );
                throw new Exception('Failed to submit function call output. Canceling run: '. json_encode($e) . 'Run: ' . json_encode($response));
            }
        } else {
            throw new Exception('Community ai client not active');
        }
    }

    public function retrieveVectorStores() {
        if($this->is_active()) {
            $response = $this->client->vectorStores()->list(
                parameters: [
                    'limit' => 10,
                ],
            );
            if (isset($response->data)) {
                return $response->data;
            } else {
                throw new Exception('Failed to retrieve vector stores: '. json_encode($response));
            }

        } else {
            throw new Exception('Community ai client not active');
        }
    }

}

