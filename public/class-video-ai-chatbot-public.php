<?php

/**
 * The public-facing functionality of the plugin.
 *
 * @link       https://localhost
 * @since      1.0.0
 *
 * @package    Video_Ai_Chatbot
 * @subpackage Video_Ai_Chatbot/public
 */

/**
 * The public-facing functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the public-facing stylesheet and JavaScript.
 *
 * @package    Video_Ai_Chatbot
 * @subpackage Video_Ai_Chatbot/public
 * @author     Daniele Napolitano <p.d.napolitano@gmail.com>
 */
class Video_Ai_Chatbot_Public {

	private $openai;

	/**
	 * The ID of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $plugin_name    The ID of this plugin.
	 */
	private $plugin_name;

	/**
	 * The version of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $version    The current version of this plugin.
	 */
	private $version;

	/**
	 * Initialize the class and set its properties.
	 *
	 * @since    1.0.0
	 * @param      string    $plugin_name       The name of the plugin.
	 * @param      string    $version    The version of this plugin.
	 */
	public function __construct( $plugin_name, $version, $openai ) {

		$this->plugin_name = $plugin_name;
		$this->version = $version;
		$this->openai = $openai;
	}

	public function openai_assistant_shortcode($atts) {
		$options = get_option('video_ai_chatbot_options');
		if (!empty($options['video_ai_enable_chatbot_field'])) {
			ob_start();
			?>
				<div id="openai-chatbot"></div>
			<?php
			return ob_get_clean();
		}
		return '';
	}

	public function init_cookies() {
		if(!isset($_COOKIE['video_ai_chatbot_session_id']))
		{
			$hash = bin2hex(random_bytes(18));
			setcookie( 'video_ai_chatbot_session_id', $hash, 0, COOKIEPATH, COOKIE_DOMAIN);
		}
	}

	/**
	 * Register the stylesheets for the public-facing side of the site.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_styles() {

		/**
		 * This function is provided for demonstration purposes only.
		 *
		 * An instance of this class should be passed to the run() function
		 * defined in Video_Ai_Chatbot_Loader as all of the hooks are defined
		 * in that particular class.
		 *
		 * The Video_Ai_Chatbot_Loader will then create the relationship
		 * between the defined hooks and the functions defined in this
		 * class.
		 */

		$options = get_option('video_ai_chatbot_options');
		if(!empty($options['video_ai_chatbot_theme'])) {
			wp_enqueue_style('openai-assistant-theme', plugins_url('css/themes/' . $options['video_ai_chatbot_theme'] . '.css', __FILE__));
		}

	}

	/**
	 * Register the JavaScript for the public-facing side of the site.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_scripts() {

		/**
		 * This function is provided for demonstration purposes only.
		 *
		 * An instance of this class should be passed to the run() function
		 * defined in Video_Ai_Chatbot_Loader as all of the hooks are defined
		 * in that particular class.
		 *
		 * The Video_Ai_Chatbot_Loader will then create the relationship
		 * between the defined hooks and the functions defined in this
		 * class.
		 */

		//wp_enqueue_script( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'js/video-ai-chatbot-public.js', array( 'jquery' ), $this->version, false );
		$options = get_option('video_ai_chatbot_options');
		if (isset($options) && !empty($options['video_ai_enable_chatbot_field'])) {
			if (!$this->openai->is_active()) {
				$api_key = isset($options['openai_api_key_field']) ? $options['openai_api_key_field'] : null;
				if (!$api_key) {
					echo '<p>Please configure your OpenAI API key in the settings.</p>';
					return;
				}
				$this->openai->activate($api_key);
			}
			wp_enqueue_script('openai-assistant-react', plugins_url('../assets/js/main.bundle.js', __FILE__), array(), '1.0', true);
			wp_enqueue_script('font-awesome', 'https://kit.fontawesome.com/24d02441cf.js', array(), null, true); //todo security
			$welcome_message = get_option('video_ai_chatbot_options')['video_ai_chatbot_welcome_message_field'];
			if (empty($welcome_message)) {
				$welcome_message = 'Ciao, come posso aiutarti?';
			}
			$assistants = $this->openai->get_filtered_assistants();
			$messages = $this->openai->get_current_user_thread_message();
			$chatbot_name = get_option('video_ai_chatbot_options')['video_ai_chatbot_name_field'];
			$user_id = apply_filters('determine_current_user', true);
			$user_display_name = '';
            if($user_id != 0) {
                $user_display_name = get_userdata($user_id)->display_name;
            }
			wp_localize_script('openai-assistant-react', 
							   'ChatbotData', 
							   array(
								'assistants' => $assistants, 
								'welcomeMessage' => $welcome_message, 
								'chatbotName' => $chatbot_name,
								'icon' => plugins_url('svg/chatbot-icon.svg', __FILE__),
								'messages' => $messages,
								'userDisplayName' => $user_display_name,
							   )
							);
		}

	}


}
