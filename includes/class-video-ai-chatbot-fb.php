<?php
 
class Video_Ai_Chatbot_Facebook {

    private $openai;
    private $token;
    private $assistantId;
    private $accountId;

    public function __construct($openai, $token, $accountId, $assistantId) {
        $this->openai = $openai;
        $this->token = $token;
        $this->assistantId = $assistantId;
        $this->accountId = $accountId;
        if($token && $assistantId && $accountId) {
            add_action('rest_api_init', [$this, 'register_api_hooks']);
            $this->activate($token, $accountId, $assistantId);
        }
    }

    public function is_active() {
        return isset($this->token) && isset($this->accountId) && isset($this->assistantId);
    }

    public function activate($token, $accountId, $assistantId)
    {
        if(!$this->is_active() && $token && $accountId && $assistantId) {
            $this->token = $token;
            $this->accountId = $accountId;
            $this->assistantId = $assistantId;
        }
    }
    public function register_api_hooks() {
        register_rest_route('video-ai-chatbot/v1/' . $this->accountId, '/webhook/', [
            'methods' => 'POST',
            'callback' => [$this, 'handle_post_request'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route('video-ai-chatbot/v1/' . $this->accountId, '/webhook/', [
            'methods' => 'GET',
            'callback' => [$this, 'handle_get_request'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route('video-ai-chatbot/v1/' . $this->accountId, '/handover_message/', [
            'methods' => 'POST',
            'callback' => [$this, 'handover_message'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route('video-ai-chatbot/v1/' . $this->accountId, '/terminate_handover/(?P<thread_id>[a-zA-Z0-9_-]+)', [
            'methods' => 'GET',
            'callback' => [$this, 'terminate_handover'],
            'permission_callback' => '__return_true',
        ]);

        //registra un hook per recuperare il profilo dell'utente instagram
        register_rest_route('video-ai-chatbot/v1/' . $this->accountId, '/get_user_profile/(?P<user_id>[a-zA-Z0-9_-]+)', [
            'methods' => 'GET',
            'callback' => [$this, 'get_user_profile_request'],
            'permission_callback' => '__return_true',
        ]);

    }


    public function get_user_profile_request(WP_REST_Request $request) {
        $user_id = $request['user_id'];
        if(!isset($user_id)) {
            return new WP_RaEST_Response(['message' => 'Invalid request data'], 400);
        }
        try { 
            $profile_data = $this->get_user_profile($user_id);

            error_log("User Profile: " . json_encode($profile_data, JSON_PRETTY_PRINT));
            return $profile_data;
        } catch (Exception $e) {
            return new WP_REST_Response(['message' => $e->getMessage()], 400);
        }
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

    private function generateKey($token) {
        $hash = 0;
        for ($i = 0; $i < strlen($token); $i++) {
            $hash = ($hash << 5) - $hash + ord($token[$i]);
            $hash = $hash & 0xFFFFFFFF; // Convert to 32bit integer
        }
        return str_pad(substr(abs($hash), 0, 5), 5, '0', STR_PAD_LEFT);
    }

    //funzione per handover_message
    public function handover_message(WP_REST_Request $request) {
        $headers = $request->get_headers();
        $data = $request->get_body_params();
        //error_log della variabile $_POST
        $instagramId = $data['instagramId'];
        $message = $data['message'];
        $thread_id = $data['thread_id'];
        $result_send = $this->send_instagram_message($instagramId, $message);
        $response = $this->openai->user_handover($thread_id, $message);
        return new WP_REST_Response(['message' => 'Message sent'], 200);
    }
    

    public function send_instagram_message($recipient_id, $message_text) {
        $url = "https://graph.instagram.com/v20.0/me/messages";
        $body = json_encode([
            'recipient' => ['id' => $recipient_id],
            'message' => ['text' => $message_text]
        ]);
    
        $response = wp_remote_post($url, [
            'body' => $body,
            'headers' => [
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $this->token
            ]
        ]);
    
        if (is_wp_error($response)) {
            error_log("Error sending message: " . $response->get_error_message());
            return new WP_REST_Response(['message' => 'Error sending message'], 400);
        }
    
        $response_body = wp_remote_retrieve_body($response);
        $data = json_decode($response_body, true);
    
        if (isset($data['error'])) {
            error_log("Error in API response: " . json_encode($data['error'], JSON_PRETTY_PRINT));
            return new WP_REST_Response(['message' => 'Error in API response'], 400);
        }
    
        return new WP_REST_Response($data, 200);
    }

    public function handle_post_request(WP_REST_Request $request) {
        $id = spl_object_hash($this);
        $headers = $request->get_headers();
        $body = $request->get_body();

        $data = json_decode($body, true);

        // Verifica che il JSON contenga i campi necessari
        if (isset($data['entry'][0]['messaging'][0]['sender']['id']) 
            && isset($data['entry'][0]['messaging'][0]['message']['text'])) {
            error_log("Data is valid");
            $senderId = $data['entry'][0]['messaging'][0]['sender']['id'];
            $messageText = $data['entry'][0]['messaging'][0]['message']['text'];
            $is_echo = isset($data['entry'][0]['messaging'][0]['message']['is_echo']) ? $data['entry'][0]['messaging'][0]['message']['is_echo'] : false;
        
            if(!$is_echo) {
    
                $response = $this->openai->handle_ig_chatbot_request($messageText, $this->assistantId, $senderId);

                if($response['error']) {
                    return new WP_REST_Response(['message' => 'Error processing request'], 400);
                }
                error_log("handle_post_request response: " . json_encode($response, JSON_PRETTY_PRINT));
                if($response['error']) 
                {
                    return new WP_REST_Response(['message' => 'Invalid request data'], 400);
                }
                if($response['message']['value'] == "handover") {
                    //error_log("result_send: " . json_encode($result_send , JSON_PRETTY_PRINT));
                    return new WP_REST_Response(['message' => 'Handover'], 200);
                }

                $assistantResponse = $response['message'];
                $splittedResponse = $this->splitStringAtParagraphEnd($assistantResponse['value']);
                
                foreach ($splittedResponse as $responsePart) {
                    $send_response = $this->send_instagram_message($senderId, $responsePart);
                    if ($send_response->get_status() !== 200) {
                        return $send_response;
                    }
                }
    
                return new WP_REST_Response(['message' => 'Message sent successfully'], 200);
            } else {
                return new WP_REST_Response(['message' => 'Message is echo'], 200);
            }
        } else {
            return new WP_REST_Response(['message' => 'Invalid request data'], 400);
        }
    }

    private function removeCitations($text) {
        // Utilizza una regex per trovare e rimuovere le sottostringhe nel formato 【numero:numero†testo】
        $pattern = '/【\d+:\d+†[^】]+】/';
        return preg_replace($pattern, '', $text);
    }


    
    public function handle_get_request(WP_REST_Request $request) {
        $headers = $request->get_headers();
        $params = $request->get_query_params();
    
        $mode = isset($params['hub_mode']) ? $params['hub_mode'] : null;
        $token = isset($params['hub_verify_token']) ? $params['hub_verify_token'] : null;
        $challenge = isset($params['hub_challenge']) ? $params['hub_challenge'] : null;
    
    
        if ($mode && $token) {
            if ($mode === 'subscribe' && $token === '12345') {
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

    public function get_user_profile($user_id) {
        $url = "https://graph.instagram.com/$user_id?fields=name,username,profile_pic,follower_count,is_user_follow_business,is_business_follow_user&access_token=" . $this->token;
        $response = wp_remote_get($url);

        if (is_wp_error($response)) {
            error_log("Error fetching user profile: " . $response->get_error_message());
            throw new Exception("Error fetching user profile");
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (isset($data['error'])) {
            error_log("Error in API response: " . json_encode($data['error'], JSON_PRETTY_PRINT));
            throw new Exception("Error fetching user profile");
        }

        return $data;
    }
 

    private function splitStringAtParagraphEnd($text) {
        $maxLength = 950;
        $result = [];

        while (strlen($text) > $maxLength) {
            // Trova l'ultimo punto prima del novecentocinquantesimo carattere
            $splitPosition = strrpos(substr($text, 0, $maxLength), '.');

            // Se non trovi un punto, fai lo split al novecentocinquantesimo carattere
            if ($splitPosition === false) {
                $splitPosition = $maxLength;
            }

            // Dividi il testo in due parti
            $part = substr($text, 0, $splitPosition + 1);
            $text = substr($text, $splitPosition + 1);

            // Rimuovi eventuali spazi bianchi all'inizio della parte rimanente
            $text = ltrim($text);

            // Aggiungi la parte al risultato
            $result[] = $part;
        }

        // Aggiungi l'ultima parte rimanente
        if (!empty($text)) {
            $result[] = $text;
        }

        return $result;
    }

}
