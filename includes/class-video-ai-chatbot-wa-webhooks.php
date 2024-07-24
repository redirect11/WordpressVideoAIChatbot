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
	public function __construct($openai) {
        $this->openai = $openai;
        $options = get_option("video_ai_chatbot_options");
        if($options) {
            $token = isset($options['openai_whatsapp_token_field']) ? $options['openai_whatsapp_token_field'] :'';
            $phone_id = isset($options['openai_whatsapp_outcoming_number_id_field']) ? $options['openai_whatsapp_outcoming_number_id_field'] :'';
            $assistantId = isset($options['openai_whatsapp_associate_assistant_field']) ? $options['openai_whatsapp_associate_assistant_field'] :'';
            error_log("Video_Ai_Chatbot_Wa_Webhooks assistantId: " . $assistantId);
            error_log("Video_Ai_Chatbot_Wa_Webhooks phone_id: " . $phone_id);
            error_log("Video_Ai_Chatbot_Wa_Webhooks token: " . $token);
            if($token && $phone_id && $assistantId) {
                $this->activate($token, $phone_id, $assistantId);
            }
        }
	}

    public function is_active() {
        return isset($this->token) && isset($this->phone_id) && isset($this->assistantId);
    }

    public function activate($token, $phone_id, $assistantId)
    {
        error_log("is_active: " . $this->is_active());
        error_log("token: " . $token);
        error_log("phone_id: " . $phone_id);
        error_log("assistantId: " . $assistantId);
        if(!$this->is_active() && $token && $phone_id && $assistantId) {
            error_log('Activating wa webhooks');
            $this->token = $token;
            $this->phone_id = $phone_id;
            $this->assistantId = $assistantId;
        }
    }
  
    public function register_api_hooks() {
        // Registra l'endpoint per le richieste POST
        register_rest_route('video-ai-chatbot/v1', '/webhook/', [
            'methods' => 'POST',
            'callback' => [$this, 'handle_post_request'],
            'permission_callback' => '__return_true',
        ]);

        // Registra l'endpoint per le richieste GET
        register_rest_route('video-ai-chatbot/v1', '/webhook/', [
            'methods' => 'GET',
            'callback' => [$this, 'handle_get_request'],
            'permission_callback' => '__return_true',
        ]);

    }
    
    public function handle_post_request(WP_REST_Request $request) {
        $headers = $request->get_headers();
        $body = $request->get_body();
    
        error_log("-------------- New Request POST --------------");
        error_log("Headers: " . json_encode($headers, JSON_PRETTY_PRINT));
        error_log("Body: " . json_encode(json_decode($body), JSON_PRETTY_PRINT));

        $data = json_decode($body, true);

        // Verifica che il JSON contenga i campi necessari
        if (isset($data['entry'][0]['changes'][0]['value']['contacts'][0]['wa_id']) && isset($data['entry'][0]['changes'][0]['value']['messages'][0]['text']['body'])) {
            error_log("Data is valid");
            $phoneNumber = $data['entry'][0]['changes'][0]['value']['contacts'][0]['wa_id'];
            $messageText = $data['entry'][0]['changes'][0]['value']['messages'][0]['text']['body'];
            $assistantId = $this->assistantId; // Sostituisci con l'ID dell'assistente desiderato
        
            // Chiama la funzione handle_wa_chatbot_request
            $response = $this->openai->handle_wa_chatbot_request($messageText, $assistantId, $phoneNumber);
            error_log("response: " . json_encode($response, JSON_PRETTY_PRINT));
            if($response['error']) 
            {
                return new WP_REST_Response(['message' => 'Invalid request data'], 400);
            }

            $access_token = $this->token;

            error_log("messageText: " . $response['message']['value']);
            error_log("phoneNumber: " . $phoneNumber);
            error_log("assistantId: " . $assistantId);
            error_log("access_token: " . $access_token);
            error_log("response: " . json_encode($response, JSON_PRETTY_PRINT));
            $result_send = $this->send_whatsapp_message($phoneNumber, $response['message']['value'], false, $access_token);
            error_log("result_send: " . json_encode($result_send , JSON_PRETTY_PRINT));
    
            return new WP_REST_Response(['message' => $response['message']['value']], 200);
        } else {
            return new WP_REST_Response(['message' => 'Invalid request data'], 400);
        }

        return new WP_REST_Response(['message' => 'Thank you for the message'], 200);
    }

    private function removeCitations($text) {
        // Utilizza una regex per trovare e rimuovere le sottostringhe nel formato 【numero:numero†testo】
        $pattern = '/【\d+:\d+†[^】]+】/';
        return preg_replace($pattern, '', $text);
    }

    private function send_whatsapp_message($phone_number, $body_text, $enable_link_preview) {
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
    
        return json_decode($response, true);
    }
    
    // // Esempio di utilizzo
    // try {
    //     $phone_number = "393668043550";
    //     $message = "Hello, this is a test message!";
    //     $access_token = "EAAGYdfnk7YYBO9cAL4FNGGHEwk8KBA7z7XmovTalBOEerx6zQ4ZCwdZAMNKPVKPZChTv2nFASq2hBwZBdhLjJ0sWnsAWAxRZAipq96Jnr4gZBqfAAgyeIhAznZAzjFzS0lBafZB9uP4NMM0ZBU68Xm6fIsWgXAdXBQ9pdHY9mLPhUZAfW4uA40ZBw3OcGyU849jE1vORqi6pIZAuHZCxvAL5I";
    
    //     $response = send_whatsapp_message($phone_number, $message, $access_token);
    //     print_r($response);
    // } catch (Exception $e) {
    //     echo 'Error: ' . $e->getMessage();
    // }
    
    
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

