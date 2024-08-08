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

	}

}
