<?php

/**
 * Fired during plugin activation
 *
 * @link       https://localhost
 * @since      1.0.0
 *
 * @package    Video_Ai_Chatbot
 * @subpackage Video_Ai_Chatbot/includes
 */

/**
 * Fired during plugin activation.
 *
 * This class defines all code necessary to run during the plugin's activation.
 *
 * @since      1.0.0
 * @package    Video_Ai_Chatbot
 * @subpackage Video_Ai_Chatbot/includes
 * @author     Daniele Napolitano <p.d.napolitano@gmail.com>
 */
class Video_Ai_Chatbot_Activator {

	public static function create_table() {
		global $wpdb;
        $table_name = $wpdb->prefix . 'video_ai_chatbot_messages';
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id INT(11) NOT NULL AUTO_INCREMENT,
            user_id TEXT NOT NULL,
            assistant_id TEXT NOT NULL,
			assistantName TEXT NOT NULL,
			userName TEXT,
			is_handover_thread TEXT NOT NULL,
			chatType TEXT NOT NULL,
			outgoingNumberId TEXT,
			instagramId TEXT,
			facebookId TEXT,
			thread_id TEXT NOT NULL,
            thread TEXT NOT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME DEFAULT NULL,
            PRIMARY KEY (id)
        ) $charset_collate;";

        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
        dbDelta( $sql );
    }

	/**
	 * Short Description. (use period)
	 *
	 * Long Description.
	 *
	 * @since    1.0.0
	 */
	public static function activate() {
		// //inizializza tutte le options contenute nel gruppo video_ai_chatbot_options
		// $options = array(
		// 	'video_ai_chatbot_name_field' => '',
		// 	'video_ai_enable_chatbot_field' => 0,
		// 	'video_ai_chatbot_welcome_message_field' => '',
		// 	'video_ai_chatbot_theme' => '',
		// 	'openai_api_key_field' => '',
		// );
		// update_option('video_ai_chatbot_options', $options);

		//inizializza tutte le options contenute nel gruppo video_ai_chatbot_options solo se non sono presenti nell'array
		//if(!get_option('video_ai_whatsapp_assistants')) {
			update_option('video_ai_whatsapp_assistants', array());
		//}

		$options = get_option('video_ai_chatbot_options');
		if (empty($options)) {
			$options = array(
				'video_ai_chatbot_name_field' => '',
				'video_ai_enable_chatbot_field' => 0,
				'video_ai_chatbot_welcome_message_field' => '',
				'video_ai_chatbot_theme' => '',
				'video_ai_whatsapp_assistants' => array(),
				'openai_api_key_field' => '',
			);
			update_option('video_ai_chatbot_options', $options);
		} else if(!array_key_exists('openai_api_key_field', $options)) {
			$options['openai_api_key_field'] = '';
			update_option('video_ai_chatbot_options', $options);
		} else if(!array_key_exists('video_ai_chatbot_theme', $options)) {
			$options['video_ai_chatbot_theme'] = '';
			update_option('video_ai_chatbot_options', $options);
		} else if(!array_key_exists('video_ai_chatbot_welcome_message_field', $options)) {
			$options['video_ai_chatbot_welcome_message_field'] = '';
			update_option('video_ai_chatbot_options', $options);
		} else if(!array_key_exists('video_ai_enable_chatbot_field', $options)) {
			$options['video_ai_enable_chatbot_field'] = 0;
			update_option('video_ai_chatbot_options', $options);
		} else if(!array_key_exists('video_ai_chatbot_name_field', $options)) {
			$options['video_ai_chatbot_name_field'] = '';
			update_option('video_ai_chatbot_options', $options);
		}

		Video_Ai_Chatbot_Activator::create_table();

	}



}
