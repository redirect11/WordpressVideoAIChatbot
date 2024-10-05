<?php

class Video_AI_Chatbot_Messages_DB {
    private $table_name;

    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'video_ai_chatbot_messages';
    }

    public function save_thread( $user_id, 
                    	         $assistant_id,  
                                 $assistantName, 
                                 $userName,  
                                 $is_handover_thread,
                                 $chatType,
                                 $thread_id, 
                                 $thread, 
                                 $outgoingNumberId = "" ,
                                 $instagramId = "" ,
                                 $facebookId = "") {
        global $wpdb;
    
        error_log("userId: $user_id, assistantId: $assistant_id, assistantName: $assistantName, userName: $userName, is_handover_thread: $is_handover_thread, chatType: $chatType, thread_id: $thread_id, thread: $thread, outgoingNumberId: $outgoingNumberId, instagramId: $instagramId, facebookId: $facebookId");
        // Verifica se esiste già un record con lo stesso user_id e assistant_id
        $existing_thread = $wpdb->get_var( $wpdb->prepare(
            "SELECT id FROM $this->table_name WHERE user_id = %d AND assistant_id = %d",
            $user_id, $assistant_id
        ));
    
        if ( $existing_thread ) {
            // Se esiste, aggiorna il campo thread di quel record
            $wpdb->update(
                $this->table_name,
                array(
                    'thread' => $thread,
                    'updated_at' => current_time( 'mysql' ),
                    'is_handover_thread' => $is_handover_thread,
                ),
                array(
                    'id' => $existing_thread
                )
            );
        } else {
            // Se non esiste, inserisci un nuovo record
            $wpdb->insert(
                $this->table_name,
                array(
                    'user_id' => $user_id,
                    'assistant_id' => $assistant_id,
                    'assistantName' => $assistantName,
                    'userName' => $userName,
                    'is_handover_thread' => $is_handover_thread,
                    'chatType' => $chatType,
                    'outgoingNumberId' => $outgoingNumberId,
                    'instagramId' => $instagramId,
                    'facebookId' => $facebookId,
                    'thread_id' => $thread_id,
                    'thread' => $thread,
                    'created_at' => current_time( 'mysql' ),
                )
            );
        }
    }

    //funzione per aggiornare il campo is_handover_thread
    public function update_handover_thread( $user_id, $assistant_id, $is_handover_thread ) {
        global $wpdb;
        $wpdb->update(
            $this->table_name,
            array(
                'is_handover_thread' => $is_handover_thread,
            ),
            array(
                'user_id' => $user_id,
                'assistant_id' => $assistant_id,
            )
        );
    }

    //funzione per aggiornare il campo is_handover_thread con thread_id
    public function update_handover_thread_by_thread_id( $thread_id, $is_handover_thread ) {
        global $wpdb;
        $wpdb->update(
            $this->table_name,
            array(
                'is_handover_thread' => $is_handover_thread,
            ),
            array(
                'thread_id' => $thread_id,
            )
        );
    }

    public function get_thread( $user_id, $assistant_id ) {
        global $wpdb;
        $sql = $wpdb->prepare(
            "SELECT * FROM $this->table_name WHERE user_id = %d AND assistant_id = %d",
            $user_id,
            $assistant_id
        );
        return $wpdb->get_results( $sql );
    }

    public function delete_thread( $user_id, $assistant_id ) {
        global $wpdb;
        $wpdb->delete(
            $this->table_name,
            array(
                'user_id' => $user_id,
                'assistant_id' => $assistant_id,
            )
        );
    }

    public function get_all_threads( $page = 1, $per_page = 10 ) {
        global $wpdb;
        $offset = ( $page - 1 ) * $per_page;
        $sql = $wpdb->prepare(
            "SELECT * FROM $this->table_name LIMIT %d OFFSET %d",
            $per_page,
            $offset
        );
        return $wpdb->get_results( $sql );
    }

    //funzione get_thread_by_thread_id
    public function get_thread_by_thread_id( $thread_id ) {
        global $wpdb;
        $sql = $wpdb->prepare(
            "SELECT * FROM $this->table_name WHERE thread_id = %s",
            $thread_id
        );
        return $wpdb->get_results( $sql );
    }
}