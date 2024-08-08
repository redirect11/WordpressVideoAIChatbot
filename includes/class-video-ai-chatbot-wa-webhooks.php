<?php

       
class Video_Ai_Chatbot_Wa_Webhooks {

    private $openai;
    private $token;
    private $phone_id;
    private $assistantId;

    /**
	 * Initialize the class and set its properties.
	 *
	 * @since    1.0.0
	 * @param      string    $plugin_name       The name of this plugin.
	 * @param      string    $version    The version of this plugin.
	 */
	public function __construct($openai, $token, $phone_id, $assistantId) {
        error_log("Constructing wa webhooks");
        $this->openai = $openai;
        $this->token = $token;
        $this->phone_id = $phone_id;
        $this->assistantId = $assistantId;
        if($token && $phone_id && $assistantId) {
            error_log("Phone ID: " . $this->phone_id);
            add_action('rest_api_init', [$this, 'register_api_hooks']);
            $this->activate($token, $phone_id, $assistantId);
        }
	}

    public function get_phone_id() {
        return $this->phone_id;
    }

    public function get_assistant_id() {
        return $this->assistantId;
    }

    public function is_active() {
        return isset($this->token) && isset($this->phone_id) && isset($this->assistantId);
    }

    public function activate($token, $phone_id, $assistantId)
    {
        if(!$this->is_active() && $token && $phone_id && $assistantId) {
            error_log('Activating wa webhooks');
            $this->token = $token;
            $this->phone_id = $phone_id;
            $this->assistantId = $assistantId;
            error_log("Phone ID: " . $this->phone_id);
        }
    }
  
    public function register_api_hooks() {
        // Registra l'endpoint per le richieste POST
        error_log("Registering API hooks");
        error_log("Phone ID: " . $this->phone_id);

        register_rest_route('video-ai-chatbot/v1/' . $this->phone_id, '/webhook/', [
            'methods' => 'POST',
            'callback' => [$this, 'handle_post_request'],
            'permission_callback' => '__return_true',
        ]);

        // Registra l'endpoint per le richieste GET
        register_rest_route('video-ai-chatbot/v1/' . $this->phone_id, '/webhook/', [
            'methods' => 'GET',
            'callback' => [$this, 'handle_get_request'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route('video-ai-chatbot/v1/' . $this->phone_id, '/handover_message/', [
            'methods' => 'POST',
            'callback' => [$this, 'handover_message'],
            'permission_callback' => '__return_true',
        ]);

        //registra un endopoint per terminare l'handover
        register_rest_route('video-ai-chatbot/v1/' . $this->phone_id, '/terminate_handover/(?P<thread_id>[a-zA-Z0-9_-]+)', [
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

    //funzione per handover_message
    public function handover_message(WP_REST_Request $request) {
        $headers = $request->get_headers();
        $data = $request->get_body_params();
        //error_log della variabile $_POST
        error_log("POST: " . json_encode($_POST, JSON_PRETTY_PRINT));
        $phone_number = $data['phone_number'];
        $message = $data['message'];
        $thread_id = $data['thread_id'];
        error_log("-------------- New Request POST --------------");
        error_log("Headers: " . json_encode($headers, JSON_PRETTY_PRINT));
        error_log("phone_number: " . $phone_number);
        error_log("message: " . $message);
        error_log("thread_id: " . $thread_id);
        $result_send = $this->send_whatsapp_message($phone_number, $message, false);
        error_log("result_send: " . json_encode($result_send , JSON_PRETTY_PRINT));
        $response = $this->openai->user_handover($thread_id, $message);
        return new WP_REST_Response(['message' => 'Message sent'], 200);
    }
    
    public function handle_post_request(WP_REST_Request $request) {
        $id = spl_object_hash($this);
        error_log("ID: " . $id);
        $headers = $request->get_headers();
        $body = $request->get_body();
    
        error_log("-------------- New Request POST --------------");
        error_log("Headers: " . json_encode($headers, JSON_PRETTY_PRINT));
        error_log("Body: " . json_encode(json_decode($body), JSON_PRETTY_PRINT));

        $data = json_decode($body, true);

        // Verifica che il JSON contenga i campi necessari
        if (isset($data['entry'][0]['changes'][0]['value']['contacts'][0]['wa_id']) 
            && isset($data['entry'][0]['changes'][0]['value']['messages'][0]['text']['body'])) {
            error_log("Data is valid");
            $phoneNumber = $data['entry'][0]['changes'][0]['value']['contacts'][0]['wa_id'];
            $messageText = $data['entry'][0]['changes'][0]['value']['messages'][0]['text']['body'];
            $assistantId = $this->assistantId; // Sostituisci con l'ID dell'assistente desiderato
        
            // Chiama la funzione handle_wa_chatbot_request
            $response = $this->openai->handle_wa_chatbot_request($messageText, $assistantId, $phoneNumber);
            error_log("handle_post_request response: " . json_encode($response, JSON_PRETTY_PRINT));
            if($response['error']) 
            {
                return new WP_REST_Response(['message' => 'Invalid request data'], 400);
            }
            if($response['message']['value'] == "handover") {
                //error_log("result_send: " . json_encode($result_send , JSON_PRETTY_PRINT));
                return new WP_REST_Response(['message' => 'Handover'], 200);
            }


            error_log("messageText: " . $response['message']['value']);
            error_log("phoneNumber: " . $phoneNumber);
            error_log("assistantId: " . $assistantId);
            $result_send = $this->send_whatsapp_message($phoneNumber, $response['message']['value'], false);
            error_log("result_send: " . json_encode($result_send , JSON_PRETTY_PRINT));
    
            return new WP_REST_Response(['message' => $response['message']['value']], 200);
        } else if (isset($data['entry'][0]['changes'][0]['value']['statuses'])) {
            error_log("Statuses: " . json_encode($data['entry'][0]['changes'][0]['value']['statuses'], JSON_PRETTY_PRINT));
            return new WP_REST_Response(['message' => 'Statuses received'], 200);
        }  else {
            return new WP_REST_Response(['message' => 'Invalid request data'], 400);
        }

        return new WP_REST_Response(['message' => 'Thank you for the message'], 200);
    }

    private function removeCitations($text) {
        // Utilizza una regex per trovare e rimuovere le sottostringhe nel formato 【numero:numero†testo】
        $pattern = '/【\d+:\d+†[^】]+】/';
        return preg_replace($pattern, '', $text);
    }

    public function send_whatsapp_message($phone_number, $body_text, $enable_link_preview) {
        error_log("Sending message to: " . $phone_number);
        error_log("Message: " . $body_text);
        error_log("Enable link preview: " . $enable_link_preview);
        $url = "https://graph.facebook.com/v19.0/" . $this->phone_id ."/messages";
        $headers = [
            "Authorization: Bearer ". $this->token,
            "Content-Type: application/json"
        ];
    
        $data = [
            "messaging_product" => "whatsapp",
            "recipient_type" => "individual",
            "to" => $phone_number,
            "type" => "text",
            "text" => [
                "preview_url" => $enable_link_preview,
                "body" => $this->removeCitations($body_text)
            ]
        ];
    
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    
        $response = curl_exec($ch);
        if (curl_errno($ch)) {
            $error_msg = curl_error($ch);
            curl_close($ch);
            throw new Exception("cURL error: $error_msg");
        }
        curl_close($ch);
        error_log("Response: " . $response);
        return json_decode($response, true);
    }

    
    public function handle_get_request(WP_REST_Request $request) {
        $headers = $request->get_headers();
        $params = $request->get_query_params();
    
        $mode = isset($params['hub_mode']) ? $params['hub_mode'] : null;
        $token = isset($params['hub_verify_token']) ? $params['hub_verify_token'] : null;
        $challenge = isset($params['hub_challenge']) ? $params['hub_challenge'] : null;
    
        error_log("Mode: " . $mode);
        error_log("Token: " . $token);
        error_log("Challenge: " . $challenge);

        error_log("-------------- New Request GET --------------");
        error_log("Headers: " . json_encode($headers, JSON_PRETTY_PRINT));
        error_log("Body: " . json_encode($params, JSON_PRETTY_PRINT));
    
        if ($mode && $token) {
            if ($mode === 'subscribe' && $token === '12345') {
                error_log("WEBHOOK_VERIFIED");
                $intChallenge = (int)$challenge;
                return new WP_REST_Response($intChallenge, 200);
            } else {
                error_log("Responding with 403 Forbidden");
                return new WP_REST_Response('Forbidden', 403);
            }
        } else {
            error_log("Replying Thank you.");
            return new WP_REST_Response(['message' => 'Thank you for the message'], 200);
        }
    }
}

